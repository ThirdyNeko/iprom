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
$loaCode = $data['loa_code'] ?? '';

// Employee data
$firstName = $data['first_name'] ?? '';
$middleName = $data['middle_name'] ?? '';
$lastName = $data['last_name'] ?? '';
$suffix = $data['suffix'] ?? '';

// Build full employee name
$employeeName = trim($firstName . ' ' . $middleName . ' ' . $lastName . ' ' . $suffix);

// Other fields
$branchCode = $data['branch'] ?? '';
$branch = '';

if (!empty($branchCode)) {
    $stmt = $pdo->prepare("
        SELECT branch
        FROM IPROM.dbo.branches
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
        FROM IPROM.dbo.branches
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
        FROM IPROM.dbo.employee_info
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
function fpdf_str(string $s): string {
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s);
}

// ============================================================
// Save to DB (only if not already recorded)
// One row per employee + branch + effectivity date, so multi-branch
// employees get a distinct saved record for each branch's LOA.
// ============================================================
$existingStmt = $pdo->prepare("
    SELECT id
    FROM letters_of_advice
    WHERE employee_id = :employee_id
      AND branch_code = :branch_code
      AND effectivity_date = :effectivity_date
");
$existingStmt->execute([
    'employee_id'      => $promodiserId,
    'branch_code'      => $loaBranchCode,
    'effectivity_date' => $effectivityDate,
]);
$existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

if (!$existing) {
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
            issued_position
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
            :issued_position
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
        'issued_by'          => $_SESSION['username'] ?? null,
        'issued_position'    => $_SESSION['position'] ?? null,
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
$pdf->Cell(55, 7, 'Employee Name', 1, 0);
$pdf->Cell(0, 7, fpdf_str($employeeName), 1, 1);

$pdf->Cell(55, 7, 'Branch', 1, 0);
$pdf->Cell(0, 7, fpdf_str($branchDisplay), 1, 1);

$pdf->Cell(55, 7, 'Brand', 1, 0);
$pdf->Cell(0, 7, fpdf_str($brandDisplay), 1, 1);

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

$pdf->Ln(4);

// Status on the left
$pdf->SetFont('Arial', '', 10);

// Left side
$pdf->Cell(15, 7, 'Status:', 0, 0);
$pdf->Cell(90, 7, 'CONTRACTUAL', 0, 0);

// Right side
$label = 'LOA Code: ';
$value = fpdf_str($loaCode);

$labelWidth = $pdf->GetStringWidth($label);
$valueWidth = $pdf->GetStringWidth($value);

// Fill the remaining space before the LOA code
$pdf->Cell(190 - 15 - 90 - $labelWidth - $valueWidth, 7, '', 0, 0);

$pdf->SetFont('Arial', '', 10);
$pdf->Cell($labelWidth, 7, $label, 0, 0);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell($valueWidth, 7, $value, 0, 1);

$pdf->Ln(5);

// Remarks WITHOUT label
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
$pdf->Ln(15);
// Username (centered within underline width)
$pdf->SetX(10);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell($lineWidth, 6, fpdf_str($_SESSION['username'] ?? ''), 0, 1, 'L');
// Position
$pdf->SetX(10);
$pdf->SetFont('Arial', '', 11);
$pdf->Cell($lineWidth, 6, fpdf_str($_SESSION['position'] ?? ''), 0, 0, 'L');
$pdf->SetFont('Arial', '', 8);
$pdf->Cell(0, 6, date('F d, Y h:i:s A'), 0, 1, 'R');

$pdf->Output('I', 'letter_of_advice.pdf');