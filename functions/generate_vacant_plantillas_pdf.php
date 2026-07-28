<?php
// Buffer everything so any stray warning/whitespace from included files
// never leaks into the binary PDF stream below.
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');

session_start();

// IMPORTANT: release the session file lock immediately. This script makes
// internal HTTP requests to get_vacant_plantilla.php / get_complete_plantilla.php,
// which ALSO call session_start(). PHP's default session handler locks the
// session file for the life of the request that holds it — so without this,
// those inner requests block waiting for this outer request's lock, time out,
// and you get a corrupted/empty response instead of a PDF.
session_write_close();

require('../fpdf/fpdf.php');
require_once '../config/db.php';
$pdo = qa_db();

// ─── Params ────────────────────────────────────────────────────────────────
$brand  = $_GET['brand'] ?? '';
$status = $_GET['status'] ?? 'all'; // all | vacant | complete

if (empty($brand)) {
    ob_end_clean();
    http_response_code(400);
    echo 'Missing brand parameter.';
    exit;
}

// ─── Helpers ───────────────────────────────────────────────────────────────
function fpdf_str(string $s): string {
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s);
}

function formatDatePdf($value) {
    if (empty($value)) return '';
    $ts = strtotime($value);
    if ($ts === false) return $value;
    return date('m/d/Y', $ts);
}

function vacantCount($required, $assigned) {
    $r = is_numeric($required) ? (float)$required : 0;
    $a = is_numeric($assigned) ? (float)$assigned : 0;
    return (string) max(0, $r - $a);
}

function monthDaysSince($timestamp) {
    if (empty($timestamp)) return '';
    try {
        $then = new DateTime($timestamp);
    } catch (Exception $e) {
        return '';
    }
    $now  = new DateTime();
    $diff = $now->diff($then);
    $months = $diff->y * 12 + $diff->m;
    $days   = $diff->d;

    if ($months === 0) return "{$days}d";
    if ($days === 0)   return "{$months}mo";
    return "{$months}mo {$days}d";
}

function fetchInternalJson(string $relativePath, array $params): array {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'];
    $dir    = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    $url    = $scheme . '://' . $host . $dir . '/' . $relativePath . '?' . http_build_query($params);

    $context = stream_context_create([
        'http' => [
            'header'  => "Cookie: " . ($_SERVER['HTTP_COOKIE'] ?? '') . "\r\n",
            'timeout' => 15,
        ],
    ]);

    $json = @file_get_contents($url, false, $context);
    if ($json === false) {
        return [];
    }
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

// ─── Fetch data ────────────────────────────────────────────────────────────
$vacantData   = fetchInternalJson('get_vacant_plantilla.php', ['brand' => $brand]);
$completeData = fetchInternalJson('get_complete_plantilla.php', ['brand' => $brand]);

$vacantRows = array_map(function ($p) {
    return [
        $p['brand'] ?? '',
        $p['branch'] ?? '',
        $p['required_count'] ?? '',
        $p['assigned_count'] ?? '',
        vacantCount($p['required_count'] ?? 0, $p['assigned_count'] ?? 0),
        formatDatePdf($p['timestamp'] ?? ''),
        monthDaysSince($p['timestamp'] ?? ''),
        '',
        '',
    ];
}, $vacantData);

$completeRows = array_map(function ($p) {
    return [
        $p['brand'] ?? '',
        $p['branch'] ?? '',
        $p['required_count'] ?? '',
        $p['assigned_count'] ?? '',
        '0',
        '',
        '',
        formatDatePdf($p['timestamp'] ?? ''),
        monthDaysSince($p['timestamp'] ?? ''),
    ];
}, $completeData);

$combined = array_merge(
    $status === 'complete' ? [] : $vacantRows,
    $status === 'vacant' ? [] : $completeRows
);

usort($combined, function ($a, $b) {
    return strcasecmp($a[0], $b[0]) ?: strcasecmp($a[1], $b[1]);
});

if (empty($combined)) {
    ob_end_clean();
    header('Content-Type: text/plain');
    echo 'No plantilla records were found for the selected brand.';
    exit;
}

// ─── PDF class ─────────────────────────────────────────────────────────────
// Overriding Header() means FPDF calls this automatically on every AddPage()
// — including automatic page breaks from SetAutoPageBreak below — so the
// letterhead + title + table column headers are guaranteed on every page.
class ReportPDF extends FPDF {
    public $letterheadImage = '../assets/icons/LETTER HEAD GENERIC.jpg';
    public $imgW = 216;
    public $imgH = 279;
    public $contentStartY = 35;
    public $reportTitle = '';
    public $reportSubtitle = '';
    public $colHeaders = [];
    public $colWidths = [];
    public $headerRowH = 6;

    function Header() {
        $this->Image($this->letterheadImage, 0, 0, $this->imgW, $this->imgH);
        $this->SetY($this->contentStartY);

        if ($this->reportTitle !== '') {
            $this->SetFont('Arial', 'B', 12);
            $this->Cell(0, 6, $this->reportTitle, 0, 1, 'C');
        }
        if ($this->reportSubtitle !== '') {
            $this->SetFont('Arial', 'I', 9);
            $this->Cell(0, 5, $this->reportSubtitle, 0, 1, 'C');
        }
        $this->Ln(2);

        if (!empty($this->colHeaders)) {
            $this->SetFont('Arial', 'B', 7.5);
            $this->SetFillColor(45, 104, 196);
            $this->SetTextColor(255, 255, 255);

            $lineHeight   = 3;
            $headerHeight = 6; // Two lines × 3mm

            $x = $this->GetX();
            $y = $this->GetY();

            foreach ($this->colHeaders as $i => $h) {
                $this->SetXY($x, $y);

                // Center single-line headers vertically
                if (strpos($h, "\n") === false) {
                    $this->Cell(
                        $this->colWidths[$i],
                        $headerHeight,
                        $h,
                        1,
                        0,
                        'C',
                        true
                    );
                } else {
                    // Draw border/background
                    $this->Cell(
                        $this->colWidths[$i],
                        $headerHeight,
                        '',
                        1,
                        0,
                        'C',
                        true
                    );

                    // Print the two-line text over it
                    $this->SetXY($x, $y);
                    $this->MultiCell(
                        $this->colWidths[$i],
                        $lineHeight,
                        $h,
                        0,
                        'C'
                    );
                }

                $x += $this->colWidths[$i];
            }

            $this->SetXY($this->lMargin, $y + $headerHeight);
            $this->SetTextColor(0, 0, 0);
        }
    }
}

// ─── Shrink-to-fit text helpers ────────────────────────────────────────────
function computeFitFontSize(FPDF $pdf, array $rows, array $widths, float $maxSize = 8, float $minSize = 5.5): float {
    $size = $maxSize;
    $pdf->SetFont('Arial', '', $size);
    $padding = 2;

    foreach ($rows as $row) {
        foreach ($row as $i => $val) {
            if (!isset($widths[$i])) continue;
            $text = fpdf_str((string)$val);
            $w = $pdf->GetStringWidth($text);
            while ($size > $minSize && $w > ($widths[$i] - $padding)) {
                $size -= 0.5;
                $pdf->SetFont('Arial', '', $size);
                $w = $pdf->GetStringWidth($text);
            }
        }
    }
    return $size;
}

function fitTextToWidth(FPDF $pdf, string $text, float $width, float $padding = 2): string {
    if ($pdf->GetStringWidth($text) <= ($width - $padding)) {
        return $text;
    }
    while (strlen($text) > 1 && $pdf->GetStringWidth($text . '...') > ($width - $padding)) {
        $text = substr($text, 0, -1);
    }
    return rtrim($text) . '...';
}

// ─── Build PDF ─────────────────────────────────────────────────────────────
$dateStr = date('l, F d, Y h:i A');

$fileSuffix = date('Y-m-d');

$headers = [
    'Brand',
    'Branch',
    'Plantilla',
    'Deployed',
    'Vacant',
    'Vacant Since',
    "Vacant\nPeriod",
    'Complete Since',
    "Complete\nPeriod"
];
$widths  = [34, 34, 18, 18, 18, 22, 16, 22, 16]; // sums to 222mm, fits landscape Letter w/ margins

$pdf = new ReportPDF('P', 'mm', 'Letter'); // portrait: 216 x 279mm
$pdf->Ln(10);
$pdf->reportTitle    = fpdf_str($brand);
$pdf->reportSubtitle = fpdf_str('As of ' . $dateStr);
$pdf->colHeaders     = array_map('fpdf_str', $headers);
$pdf->colWidths      = $widths;
$pdf->SetAutoPageBreak(true, 30); // auto page break re-calls Header() -> letterhead redrawn automatically
$pdf->AddPage();

$fitSize = computeFitFontSize($pdf, $combined, $widths);
$rowH = 5; // compact row height

$pdf->SetFont('Arial', '', $fitSize);
foreach ($combined as $row) {
    foreach ($row as $i => $val) {
        $text = fitTextToWidth($pdf, fpdf_str((string)$val), $widths[$i]);
        $align = ($i === 1) ? 'L' : 'C';
        $pdf->Cell($widths[$i], $rowH, $text, 1, 0, $align);
    }
    $pdf->Ln();
    $pdf->SetFont('Arial', '', $fitSize);
}

ob_end_clean();
$pdf->Output('I', "{$brand}_" . strtoupper($status) . "_PLANTILLAS_{$fileSuffix}.pdf");