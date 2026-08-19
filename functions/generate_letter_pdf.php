<?php
session_start();
require('../fpdf/fpdf.php'); // adjust path
require_once '../config/db.php';
$pdo = qa_db();

// 🔥 FIX: Read JSON payload correctly
$data = json_decode(file_get_contents("php://input"), true);

// Recipient data (one recipient per call — JS loops per branch for multi-branch employees)
$recipientName = $data['recipient_name'] ?? '';
$recipientPosition = $data['recipient_position'] ?? '';
$recipientBranchName = $data['recipient_branch_name'] ?? '';
$recipientBranchCode = $data['recipient_branch_code'] ?? '';
$endDate = $data['end_date'] ?? '';
// Only update the LOA code if it was actually provided.
// This prevents existing LOA codes from being overwritten during
// reprints or cancellation flows that don't send a loa_code.
$hasLoaCode = array_key_exists('loa_code', $data);
$loaCode = $hasLoaCode ? $data['loa_code'] : null;

// Employee data
$firstName = $data['first_name'] ?? '';
$middleName = $data['middle_name'] ?? '';
$lastName = $data['last_name'] ?? '';
$suffix = $data['suffix'] ?? '';
$biometricNumber = $data['biometric_number'] ?? '';

// Build full employee name
$employeeName = trim($firstName . ' ' . $middleName . ' ' . $lastName . ' ' . $suffix);

// Issuer info — used for both the DB record and the PDF signature block.
// Two callers hit this endpoint:
//   1. pdf.js (new LOA)      -> issued_by/issued_position come from
//      window.currentUser on the client.
//   2. loa_table.js (reprint) -> issued_by/issued_position come from the
//      already-saved DB row (so a reprint always shows the ORIGINAL
//      issuer, not whoever is currently viewing/reprinting it).
// $_SESSION is only a last-resort fallback if neither is present.
$issuedBy = $data['issued_by'] ?? $_SESSION['username'] ?? '';
$issuedPosition = $data['issued_position'] ?? $_SESSION['position'] ?? '';

// "Last updated" timestamp shown in the PDF footer.
//   - Reprints (loa_table.js) pass this through from letters_of_advice.updated_at
//     (already GETDATE()-defaulted at the SQL level in fetch_loa.php).
//   - Brand-new LOAs (pdf.js) never send this field, since the record doesn't
//     exist yet -- falls back to the current time here, same as the old
//     hardcoded date('F d, Y h:i:s A') behavior.
$updatedAt = $data['updated_at'] ?? '';
$timestamp = !empty($updatedAt)
    ? date('F d, Y h:i:s A', strtotime($updatedAt))
    : date('F d, Y h:i:s A');

// Other fields
$branchCode = $data['branch'] ?? '';
$branch = '';

if (!empty($branchCode)) {
    $stmt = $pdo->prepare("
        SELECT branch
        FROM branches
        WHERE branch_code = :code
    ");

    $stmt->execute(['code' => $branchCode]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $branch = $row['branch'] ?? $branchCode; // fallback to code if not found
}

$rovingBranches = $data['roving_branches'] ?? [];

$rovingBranchNames = [];

if (!empty($rovingBranches) && is_array($rovingBranches)) {

    $stmt = $pdo->prepare("
        SELECT branch
        FROM branches
        WHERE branch_code = :code
    ");

    foreach ($rovingBranches as $code) {
        $stmt->execute(['code' => $code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $rovingBranchNames[] = $row['branch'] ?? $code;
    }
}
$branchDisplay = $branch;

if (!empty($rovingBranchNames)) {
    $branchDisplay .= ", " . implode(", ", $rovingBranchNames);
}
$multiBrands = $data['multi_brands'] ?? [];
$brand = $data['brand'] ?? '';
$brandDisplay = $brand;
if (!empty($multiBrands)) {
    $brandDisplay .=", " . implode(", ", $multiBrands);
}
$agency = $data['agency'] ?? '';
$employmentStatus = $data['employment_status'] ?? '';
$subStatus = $data['sub_status'] ?? '';
$status = $data['status'] ?? '';

// 🔥 FIX: read the business ID (e.g. "EMP-20260707-A9D3F91E") from `employee_id`
// in the payload, not `id`.
$promodiserId = $data['employee_id'] ?? '';

// 🔥 Full set of branches this employee is assigned to (main branch + roving
// branches), used below to correctly "swap" which branch is the record's own
// branch vs. which are its roving branches, per multi-branch row.
$allEmployeeBranches = array_values(array_unique(array_merge(
    !empty($branchCode) ? [$branchCode] : [],
    is_array($rovingBranches) ? $rovingBranches : []
)));

// Branch this specific LOA record belongs to (per-branch for multi-branch employees,
// falls back to the main branch when no recipient-specific branch was sent)
$loaBranchCode = !empty($recipientBranchCode) ? $recipientBranchCode : $branchCode;

// Roving branches to store for this record: the full branch set minus
// this record's own branch, so each per-branch row correctly lists the OTHER
// branches the employee also covers (e.g. branch_code=JANI → roving=CBAT,
// and branch_code=CBAT → roving=JANI), instead of always reusing the raw
// roving_branches list unchanged across every branch call.
$rovingBranchesForRecord = array_values(array_diff($allEmployeeBranches, [$loaBranchCode]));

$remarks = $data['remarks'] ?? '';

if (!empty($promodiserId)) {
    // lookup by employee_info.employee_id (the business ID column),
    // not employee_info.id (the INT primary key).
    $stmt = $pdo->prepare("
        SELECT remarks
        FROM employee_info
        WHERE employee_id = :employee_id
    ");

    $stmt->execute(['employee_id' => $promodiserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!empty($row['remarks'])) {
        $remarks = $row['remarks'];
    }
}
$effectivityDate = $data['effectivity_date'] ?? '';

if (empty($endDate) && !empty($effectivityDate)) {
    $endDate = date('Y-m-d', strtotime($effectivityDate . ' +6 months'));
}

// Add this near the top with your other helpers
function fpdf_str($s): string
{
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string)($s ?? ''));
}

// Shrinks the font (down to $minSize) until $text fits within $maxWidth,
// so long Branch/Brand values (multi-branch/multi-brand strings) stay on
// one line instead of wrapping the row height via MultiCell.
// Returns the font size that was ultimately set.
function fpdf_fit_font(FPDF $pdf, string $text, float $maxWidth, int $maxSize = 11, int $minSize = 6, string $font = 'Arial', string $style = ''): int
{
    $size = $maxSize;
    $pdf->SetFont($font, $style, $size);

    while ($size > $minSize && $pdf->GetStringWidth($text) > $maxWidth) {
        $size--;
        $pdf->SetFont($font, $style, $size);
    }

    return $size;
}

// ============================================================
// Save to DB — update in place if a record already exists for
// this person (by name) + branch, otherwise insert a new row.
//
// Match key: first_name + middle_name + last_name + branch_code
// (not employee_id/effectivity_date — regenerating an LOA for the
// same person/branch now overwrites the prior record rather than
// creating a parallel one).
//
// NOTE: reprints (loa_table.js) will also hit this UPDATE branch,
// but since their payload's fields are sourced from this same DB
// row to begin with, it's effectively a no-op rewrite — issued_by/
// issued_position stay pinned to the original issuer either way.
// ============================================================
$existingStmt = $pdo->prepare("
    SELECT id
    FROM letters_of_advice
    WHERE first_name = :first_name
      AND ISNULL(middle_name, '') = ISNULL(:middle_name, '')
      AND last_name = :last_name
      AND branch_code = :branch_code
");
$existingStmt->execute([
    'first_name'  => $firstName,
    'middle_name' => $middleName,
    'last_name'   => $lastName,
    'branch_code' => $loaBranchCode,
]);
$existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

if ($existing) {
    $sql = "
        UPDATE letters_of_advice
        SET
            recipient_name      = :recipient_name,
            recipient_position  = :recipient_position,
            employee_id         = :employee_id,
            suffix              = :suffix,
            roving_branches     = :roving_branches,
            brand               = :brand,
            multi_brands        = :multi_brands,
            agency              = :agency,
            employment_status   = :employment_status,
            sub_status          = :sub_status,
            status              = :status,
            effectivity_date    = :effectivity_date,
            end_date            = :end_date,
            remarks             = :remarks,
            issued_by           = :issued_by,
            issued_position     = :issued_position,
            updated_at          = GETDATE()";

    if ($hasLoaCode) {
        $sql .= ",\n            loa_code = :loa_code";
    }

    $sql .= "\n        WHERE id = :id";

    $updateStmt = $pdo->prepare($sql);

    $params = [
        'recipient_name'     => $recipientName,
        'recipient_position' => $recipientPosition,
        'employee_id'        => $promodiserId,
        'suffix'             => $suffix,
        'roving_branches'    => !empty($rovingBranchesForRecord) ? implode(',', $rovingBranchesForRecord) : null,
        'brand'              => $brand,
        'multi_brands'       => !empty($multiBrands) ? implode(',', $multiBrands) : null,
        'agency'             => $agency,
        'employment_status'  => $employmentStatus,
        'sub_status'         => $subStatus,
        'status'             => $status,
        'effectivity_date'   => $effectivityDate,
        'end_date'           => $endDate,
        'remarks'            => $remarks,
        'issued_by'          => $issuedBy,
        'issued_position'    => $issuedPosition,
        'id'                 => $existing['id'],
    ];

    if ($hasLoaCode) {
        $params['loa_code'] = $loaCode;
    }

    $updateStmt->execute($params);
} else {
    $insertStmt = $pdo->prepare("
        INSERT INTO letters_of_advice (
            recipient_name,
            recipient_position,
            employee_id,
            first_name,
            middle_name,
            last_name,
            suffix,
            branch_code,
            roving_branches,
            brand,
            multi_brands,
            agency,
            employment_status,
            sub_status,
            status,
            effectivity_date,
            end_date,
            remarks,
            issued_by,
            issued_position,
            loa_code
        ) VALUES (
            :recipient_name,
            :recipient_position,
            :employee_id,
            :first_name,
            :middle_name,
            :last_name,
            :suffix,
            :branch_code,
            :roving_branches,
            :brand,
            :multi_brands,
            :agency,
            :employment_status,
            :sub_status,
            :status,
            :effectivity_date,
            :end_date,
            :remarks,
            :issued_by,
            :issued_position,
            :loa_code
        )
    ");

    $insertStmt->execute([
        'recipient_name'     => $recipientName,
        'recipient_position' => $recipientPosition,
        'employee_id'        => $promodiserId,
        'first_name'         => $firstName,
        'middle_name'        => $middleName,
        'last_name'          => $lastName,
        'suffix'             => $suffix,
        'branch_code'        => $loaBranchCode,
        'roving_branches'    => !empty($rovingBranchesForRecord) ? implode(',', $rovingBranchesForRecord) : null,
        'brand'              => $brand,
        'multi_brands'       => !empty($multiBrands) ? implode(',', $multiBrands) : null,
        'agency'             => $agency,
        'employment_status'  => $employmentStatus,
        'sub_status'         => $subStatus,
        'status'             => $status,
        'effectivity_date'   => $effectivityDate,
        'end_date'           => $endDate,
        'remarks'            => $remarks,
        'issued_by'          => $issuedBy,
        'issued_position'    => $issuedPosition,
        'loa_code'           => $loaCode,
    ]);
}

// Header branch for this specific LOA (per-branch when multi-branch, else main branch)
$headerBranch = $recipientBranchName !== '' ? $recipientBranchName : $branch;

$pdf = new FPDF('P', 'mm', 'Letter');
$pdf->AddPage();

$pdf->Image('../assets/icons/LETTER HEAD GENERIC.jpg', 0, 0, 216, 279);

$pdf->Ln(30);

$pdf->SetFont('Arial', '', 14);
$pdf->Cell(0, 8, 'LETTER OF ADVICE', 0, 1, 'C');

$pdf->Ln(10);

$pdf->SetFont('Arial', 'B', 11);

$pdf->Cell(150, 6, fpdf_str($recipientName), 0, 0);
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(0, 6, date('F d, Y'), 0, 1, 'R');

$pdf->Cell(120, 6, fpdf_str($recipientPosition), 0, 1);
$pdf->Cell(120, 6, fpdf_str($headerBranch), 0, 1);

$pdf->Ln(10);

$pdf->SetX(10);

// normal text
$pdf->SetFont('Arial', '', 11);
$pdf->Write(6, '       Please be informed that the employee named below has complied with all the requirements. Please advise him/her to report to work.');

$pdf->Ln(8);

$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 7, 'EMPLOYEE DETAILS', 0, 1);

$pdf->SetFont('Arial', '', 11);

// Rows
$pdf->Cell(55, 7, 'Biometric Number', 1, 0);
$pdf->Cell(0, 7, fpdf_str($biometricNumber), 1, 1);

$pdf->Cell(55, 7, 'Employee Name', 1, 0);
$pdf->Cell(0, 7, fpdf_str($employeeName), 1, 1);

// Branch — value font shrinks (down to 6pt) so a long roving-branch
// list stays on one line instead of wrapping the row.
// NOTE: FPDF has no GetRightMargin() getter — this file never calls
// SetMargins()/SetRightMargin(), so the constructor default of 10mm
// is used directly here instead.
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(55, 7, 'Branch', 1, 0);

$branchText = fpdf_str($branchDisplay);
$branchValueWidth = $pdf->GetPageWidth() - 10 - $pdf->GetX() - 2; // 10mm = default right margin; -2mm inner padding
fpdf_fit_font($pdf, $branchText, $branchValueWidth);
$pdf->Cell(0, 7, $branchText, 1, 1);

// Brand — same auto-fit treatment for long multi-brand lists.
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(55, 7, 'Brand', 1, 0);

$brandText = fpdf_str($brandDisplay);
$brandValueWidth = $pdf->GetPageWidth() - 10 - $pdf->GetX() - 2; // 10mm = default right margin; -2mm inner padding
fpdf_fit_font($pdf, $brandText, $brandValueWidth);
$pdf->Cell(0, 7, $brandText, 1, 1);

$pdf->SetFont('Arial', '', 11);
$pdf->Cell(55, 7, 'Agency', 1, 0);
$pdf->Cell(0, 7, fpdf_str($agency), 1, 1);

$pdf->Cell(55, 7, 'Employment Status', 1, 0);
$pdf->Cell(0, 7, fpdf_str($employmentStatus), 1, 1);

$pdf->Cell(55, 7, 'Sub Status', 1, 0);
$pdf->Cell(0, 7, fpdf_str($subStatus), 1, 1);

$pdf->Cell(55, 7, 'Date of Effectivity', 1, 0);
$pdf->Cell(0, 7, strtoupper(date('F d, Y', strtotime($effectivityDate))), 1, 1);

$pdf->Cell(55, 7, 'To End', 1, 0);
$pdf->Cell(0, 7, strtoupper(date('F d, Y', strtotime($endDate))), 1, 1);

$pdf->Ln(2);

$pdf->SetFont('Arial', 'I', 10);
$pdf->Write(6, '       This document was generated automatically by the system; no signature is required.');

$pdf->Ln(7);

// Status on the left
$pdf->SetFont('Arial', '', 10);

// Left side
$pdf->Cell(15, 7, 'Status:', 0, 0);
$pdf->Cell(90, 7, 'CONTRACTUAL', 0, 0);

// Right side
$label = 'LOA Code: ';
$value = fpdf_str($loaCode);

if (trim($value) !== '') {
    $labelWidth = $pdf->GetStringWidth($label);
    $valueWidth = $pdf->GetStringWidth($value);

    // Fill the remaining space before the LOA code
    $pdf->Cell(190 - 15 - 90 - $labelWidth - $valueWidth, 7, '', 0, 0);

    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell($labelWidth, 7, $label, 0, 0);

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell($valueWidth, 7, $value, 0, 1);
} else {
    // Nothing to show on the right side — just close the row
    $pdf->Cell(190 - 15 - 90, 7, '', 0, 1);
}


// Remarks WITHOUT label
$pdf->SetFont('Arial', '', 11);
$pdf->MultiCell(0, 7, fpdf_str($remarks));

$pdf->Ln(5);

$pdf->SetFont('Arial', '', 11);

$oldX = $pdf->GetX();
// move cursor right (indent)
$pdf->SetX(10);

$pdf->MultiCell(
    0,
    5,
    "Likewise, you are directed to conduct orientation on the following:\n\n                1. Brief history of the Company\n                2. Company Mission and Vision\n                3. General Rules and Regulations"
);

$pdf->Ln(10);

$pdf->SetX(10);

$lineWidth = 50;

$pdf->SetFont('Arial', '', 11);
$pdf->Write(6, 'Issued by:');
$pdf->Ln(10);
// Username (centered within underline width)
$pdf->SetX(10);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell($lineWidth, 6, fpdf_str($issuedBy), 0, 1, 'L');
// Position
$pdf->SetX(10);
$pdf->SetFont('Arial', '', 11);
$pdf->Cell($lineWidth, 6, fpdf_str($issuedPosition), 0, 0, 'L');
$pdf->SetFont('Arial', '', 8);
$pdf->Cell(0, 6, $timestamp, 0, 1, 'R');

$pdf->Output('I', 'letter_of_advice.pdf');