<?php
// Buffer everything so any stray warning/whitespace from included files
// never leaks into the binary PDF stream below.
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');

// Surfaces any fatal error as readable text instead of a blank/broken PDF.
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        http_response_code(500);
        header('Content-Type: text/plain');
        echo "FATAL: {$err['message']} in {$err['file']} on line {$err['line']}";
    }
});

session_start();

// IMPORTANT: release the session file lock immediately. This script makes
// an internal HTTP request to get_employee_report.php, which ALSO calls
// session_start(). PHP's default session handler locks the session file for
// the life of the request that holds it — so without this, the inner
// request blocks waiting for this outer request's lock, times out, and you
// get a corrupted/empty response instead of a PDF.
session_write_close();

require('../fpdf/fpdf.php'); // adjust path if this file lives elsewhere
require_once '../config/db.php';
$pdo = qa_db(); // not queried directly here, kept in case you want to add auth/role checks

// ─── Params ────────────────────────────────────────────────────────────────
$branchCode  = $_GET['branch'] ?? '';
$branchLabel = $_GET['branch_label'] ?? $branchCode;

if (empty($branchCode)) {
    ob_end_clean();
    http_response_code(400);
    echo 'Missing branch parameter.';
    exit;
}

// ─── Helpers ───────────────────────────────────────────────────────────────
function fpdf_str($s): string {
    $s = (string)$s;
    // Repair genuinely invalid UTF-8 byte sequences first (this is what
    // //TRANSLIT cannot fix on its own).
    if (!mb_check_encoding($s, 'UTF-8')) {
        $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8'); // drops/replaces bad bytes
    }
    $result = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $s);
    return $result === false ? '' : $result;
}

function formatDatePdf($value) {
    if (empty($value)) return '';
    $ts = strtotime($value);
    if ($ts === false) return $value;
    return date('m/d/Y', $ts);
}

function formatFullName($last, $first, $middle, $suffix): string {
    $name = trim($last) . ', ' . trim($first);
    if (!empty(trim($suffix))) {
        $name .= ' ' . trim($suffix);
    }
    if (!empty(trim($middle))) {
        $name .= ' ' . strtoupper(substr(trim($middle), 0, 1)) . '.';
    }
    return $name;
}

function firstNonEmpty(...$values): string {
    foreach ($values as $v) {
        if (!empty(trim((string)$v))) {
            return $v;
        }
    }
    return '';
}

/**
 * Calls an existing JSON endpoint on this same server, forwarding the
 * current session cookie so role/branch-based filtering in that endpoint
 * still applies. This keeps a single source of truth for the actual
 * SQL/stored-procedure logic instead of duplicating it here.
 *
 * Retries a few times with a short backoff: if the server can only handle
 * one request at a time (e.g. PHP's built-in dev server, or a low
 * php-fpm worker count), the first attempt can fail simply because the
 * outer request hasn't finished yet. A short pause and retry lets that
 * clear up instead of failing outright.
 */
function fetchInternalJson(string $relativePath, array $params, int $maxAttempts = 3): array
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'];
    $dir    = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

    $url = $scheme . '://' . $host . $dir . '/' .
           $relativePath . '?' . http_build_query($params);

    $context = stream_context_create([
        'http' => [
            'header'        => "Cookie: " . ($_SERVER['HTTP_COOKIE'] ?? '') . "\r\n",
            'timeout'       => 15,
            'ignore_errors' => true,
        ],
        'ssl' => [
            // This is a loopback call to the same server, not to a third
            // party — the self-signed cert on the internal IP will fail
            // default verification ("Failed to enable crypto"), so it's
            // safe to relax verification here specifically.
            'verify_peer'      => false,
            'verify_peer_name' => false,
            'allow_self_signed'=> true,
        ],
    ]);

    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        $json = @file_get_contents($url, false, $context);

        $status = null;
        if (isset($http_response_header[0]) && preg_match('#HTTP/\S+\s+(\d+)#', $http_response_header[0], $m)) {
            $status = (int)$m[1];
        }

        if ($json !== false && ($status === null || $status < 400)) {
            $data = json_decode($json, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                return $data;
            }
            error_log("[fetchInternalJson] attempt $attempt: $url returned invalid JSON (" . json_last_error_msg() . "): " . substr((string)$json, 0, 500));
        } else {
            error_log("[fetchInternalJson] attempt $attempt: $url failed" . ($status ? " (HTTP $status)" : " (no response)"));
        }

        if ($attempt < $maxAttempts) {
            usleep(300000);
        }
    }

    error_log("[fetchInternalJson] giving up on $url after $maxAttempts attempts");
    return [];
}

// ─── Fetch data ────────────────────────────────────────────────────────────
$rows = fetchInternalJson('get_employee_report.php', ['branch' => $branchCode]);

if (empty($rows)) {
    ob_end_clean();
    header('Content-Type: text/plain');
    echo 'No employees were found for the selected branch.';
    exit;
}

// ─── PDF class ─────────────────────────────────────────────────────────────
// Overriding Header() means FPDF calls this automatically on every AddPage()
// — including the automatic page breaks triggered by SetAutoPageBreak below
// — so the letterhead + title + table column headers are guaranteed on
// every page without needing to manually track Y-position and re-draw.
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
    public $tableX = 0; // left X where the (centered) table starts; set before AddPage()

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
            $this->SetX($this->tableX);
            foreach ($this->colHeaders as $i => $h) {
                $this->Cell($this->colWidths[$i], $this->headerRowH, $h, 1, 0, 'C', true);
            }
            $this->Ln();
            $this->SetTextColor(0, 0, 0);
        }
    }
}

// ─── Shrink-to-fit text helpers ────────────────────────────────────────────
// Finds one font size, used consistently across the whole table body, small
// enough that every cell's text fits inside its column width — so nothing
// gets clipped or overlaps the next column. Falls back to truncating with
// "…" only if even the minimum readable size still doesn't fit (e.g. an
// unusually long name).
function computeFitFontSize(FPDF $pdf, array $rows, array $widths, float $maxSize = 8, float $minSize = 5.5): float {
    $size = $maxSize;
    $pdf->SetFont('Arial', '', $size);
    $padding = 2; // mm safety margin inside each cell

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

$headers = ['Brand', 'Name', 'Gender', 'Employment Status', 'Sub-Status', 'End Date'];
$widths  = [25, 50, 15, 27, 26, 20]; // sums to 163mm — narrower than the page, so we center it

$bodyRows = array_map(function ($r) {
    return [
        $r['brand'] ?? '',
        formatFullName(
            $r['last_name'] ?? '',
            $r['first_name'] ?? '',
            $r['middle_name'] ?? '',
            $r['suffix'] ?? ''
        ),
        strtoupper(substr(trim($r['gender'] ?? ''), 0, 1)),
        $r['employment_status'] ?? '',
        $r['sub_status'] ?? '',
        formatDatePdf(firstNonEmpty($r['end_date'] ?? '', $r['date_separated'] ?? '')),
    ];
}, $rows);

$pdf = new ReportPDF('P', 'mm', 'Letter');
$pdf->Ln(20);
$pdf->reportTitle    = fpdf_str('PROMODISER LIST OF ' . $branchLabel);
$pdf->reportSubtitle = fpdf_str('As of ' . $dateStr);
$pdf->colHeaders     = array_map('fpdf_str', $headers);
$pdf->colWidths      = $widths;

// Center the table horizontally: total table width vs. full page width.
// tableX is used both in Header() (for the header row) and in the body
// loop below (for each data row), so header and body columns line up.
$tableWidth   = array_sum($widths);
$pdf->tableX  = ($pdf->GetPageWidth() - $tableWidth) / 2;

$pdf->SetAutoPageBreak(true, 30); // auto page break re-calls Header() -> letterhead redrawn automatically
$pdf->AddPage();

$fitSize = computeFitFontSize($pdf, $bodyRows, $widths);
$rowH = 7; // compact row height

$pdf->SetFont('Arial', '', $fitSize);
foreach ($bodyRows as $row) {
    $pdf->SetX($pdf->tableX);
    foreach ($row as $i => $val) {
        $text = fitTextToWidth($pdf, fpdf_str((string)$val), $widths[$i]);
        $align = ($i === 1) ? 'L' : 'C';
        $pdf->Cell($widths[$i], $rowH, $text, 1, 0, $align);
    }
    $pdf->Ln();
    // AutoPageBreak may have fired mid-row-loop and called Header(), which
    // sets bold/italic fonts for the title block — restore body font size.
    $pdf->SetFont('Arial', '', $fitSize);
}

ob_end_clean();
$pdf->Output('I', "{$branchCode}_PROMO_LIST_{$fileSuffix}.pdf");