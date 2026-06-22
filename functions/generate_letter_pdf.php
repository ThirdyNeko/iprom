<?php
session_start();
require('../fpdf/fpdf.php'); // adjust path
require_once '../config/db.php';
$pdo = qa_db();

// 🔥 FIX: Read JSON payload correctly
$data = json_decode(file_get_contents("php://input"), true);

// Recipient data
$recipientName = $data['recipient_name'] ?? '';
$recipientPosition = $data['recipient_position'] ?? '';
$endDate = $data['end_date'] ?? '';

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
$employeeId = $data['id'] ?? '';

$remarks = $data['remarks'] ?? '';

if (!empty($employeeId)) {
    $stmt = $pdo->prepare("
        SELECT remarks
        FROM IPROM.dbo.employee_info
        WHERE id = :id
    ");

    $stmt->execute(['id' => $employeeId]);
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

$pdf = new FPDF('P', 'mm', 'Letter');
$pdf->AddPage();

$pdf->Image('../assets/icons/LETTER HEAD GENERIC.jpg', 0, 0, 216, 279);

$pdf->Ln(30);

$pdf->SetFont('Arial', '', 14);
$pdf->Cell(0, 8, 'LETTER OF ADVICE', 0, 1, 'C');

$pdf->Ln(10);

$pdf->SetFont('Arial', 'B', 11);

$pdf->Cell(150, 6, $recipientName, 0, 0);
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(0, 6, date('F d, Y'), 0, 1, 'R');

$pdf->Cell(120, 6, $recipientPosition, 0, 1);
$pdf->Cell(120, 6, $branch, 0, 1);

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
$pdf->Cell(0, 7, $branchDisplay, 1, 1);

$pdf->Cell(55, 7, 'Brand', 1, 0);
$pdf->Cell(0, 7, $brandDisplay, 1, 1);

$pdf->Cell(55, 7, 'Agency', 1, 0);
$pdf->Cell(0, 7, $agency, 1, 1);

$pdf->Cell(55, 7, 'Employment Status', 1, 0);
$pdf->Cell(0, 7, $employmentStatus, 1, 1);

$pdf->Cell(55, 7, 'Sub Status', 1, 0);
$pdf->Cell(0, 7, $subStatus, 1, 1);

$pdf->Cell(55, 7, 'Date of Effectivity', 1, 0);
$pdf->Cell(0, 7, strtoupper(date('F d, Y', strtotime($effectivityDate))), 1, 1);

$pdf->Cell(55, 7, 'To End', 1, 0);
$pdf->Cell(0, 7, strtoupper(date('F d, Y', strtotime($endDate))), 1, 1);

$pdf->Ln(4);

$pdf->Cell(15, 7, 'Status:', 0, 0);
$pdf->Cell(0, 7, 'CONTRACTUAL', 0, 1, 'L');

$pdf->Ln(5);

// Remarks WITHOUT label
$pdf->MultiCell(0, 7, $remarks);

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
$pdf->Cell($lineWidth, 6, $_SESSION['username'], 0, 1, 'L');
// Position
$pdf->SetX(10);
$pdf->SetFont('Arial', '', 11);
$pdf->Cell($lineWidth, 6, $_SESSION['position'] ?? '', 0, 1, 'L');

$pdf->Output('I', 'letter_of_advice.pdf');