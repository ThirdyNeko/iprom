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
// internal HTTP requests to get_vacant_plantilla_branch.php /
// get_complete_plantilla_branch.php, which ALSO call session_start(). PHP's
// default session handler locks the session file for the life of the
// request that holds it — so without this, those inner requests block
// waiting for this outer request's lock, time out, and you get a
// corrupted/empty response instead of a PDF.
session_write_close();

require('../fpdf/fpdf.php');
require_once '../config/db.php';
$pdo = qa_db();

// ─── Params ────────────────────────────────────────────────────────────────
$branch      = $_GET['branch'] ?? '';
$branchLabel = $_GET['branch_label'] ?? $branch;
$status      = $_GET['status'] ?? 'all'; // all | vacant | complete
$period      = $_GET['period'] ?? 'all'; // all | lt15 | 15to30 | 1to2mo | gt2mo

if (empty($branch)) {
    ob_end_clean();
    http_response_code(400);
    echo 'Missing branch parameter.';
    exit;
}

// ─── Helpers ───────────────────────────────────────────────────────────────
function fpdf_str($s): string {
    $s = (string)$s;
    if (!mb_check_encoding($s, 'UTF-8')) {
        $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8');
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

// Raw day count since a timestamp, used for period bucketing.
function daysSince($timestamp): ?int {
    if (empty($timestamp)) return null;
    try {
        $then = new DateTime($timestamp);
    } catch (Exception $e) {
        return null;
    }
    $now = new DateTime();
    return (int) $now->diff($then)->days;
}

// Buckets a day count into one of the filter's period options.
function periodBucket(?int $days): string {
    if ($days === null) return 'unknown';
    if ($days < 15) return 'lt15';
    if ($days <= 30) return '15to30';
    if ($days <= 60) return '1to2mo';
    return 'gt2mo';
}

// Sum/format helper for the per-page summary row.
function fmtCount($n) {
    return (fmod($n, 1) === 0.0) ? number_format($n, 0) : number_format($n, 2);
}

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
$vacantData   = fetchInternalJson('get_vacant_plantilla_branch.php', ['branch' => $branch]);
$completeData = fetchInternalJson('get_complete_plantilla_branch.php', ['branch' => $branch]);

// Each row carries 'cols' (what actually prints) and 'period' (bucket used
// for filtering only — not printed as its own column).
$vacantRows = array_map(function ($p) {
    $ts = $p['timestamp'] ?? null;
    if (!$ts) {
        $updatedAt = $p['updated_at'] ?? null;
        $latestDateSeparated = $p['latest_date_separated'] ?? null;

        if ($updatedAt && $latestDateSeparated) {
            $ts = strtotime($updatedAt) >= strtotime($latestDateSeparated)
                ? $updatedAt
                : $latestDateSeparated;
        } else {
            $ts = $updatedAt ?: $latestDateSeparated ?: '';
        }
    }
    return [
        'cols' => [
            $p['branch'] ?? '',
            $p['brand'] ?? '',
            $p['required_count'] ?? '',
            $p['assigned_count'] ?? '',
            vacantCount($p['required_count'] ?? 0, $p['assigned_count'] ?? 0),
            formatDatePdf($ts),
            monthDaysSince($ts),
            '',
            '',
        ],
        'period' => periodBucket(daysSince($ts)),
    ];
}, $vacantData);

$completeRows = array_map(function ($p) {
    $ts = $p['timestamp'] ?? null;
    if (!$ts) {
        $updatedAt = $p['updated_at'] ?? null;
        $latestStartDate = $p['latest_start_date'] ?? null;

        if ($updatedAt && $latestStartDate) {
            $ts = strtotime($updatedAt) >= strtotime($latestStartDate)
                ? $updatedAt
                : $latestStartDate;
        } else {
            $ts = $updatedAt ?: $latestStartDate ?: '';
        }
    }
    return [
        'cols' => [
            $p['branch'] ?? '',
            $p['brand'] ?? '',
            $p['required_count'] ?? '',
            $p['assigned_count'] ?? '',
            '0',
            '',
            '',
            formatDatePdf($ts),
            monthDaysSince($ts),
        ],
        'period' => periodBucket(daysSince($ts)),
    ];
}, $completeData);

$combinedMeta = array_merge(
    $status === 'complete' ? [] : $vacantRows,
    $status === 'vacant' ? [] : $completeRows
);

if ($period !== 'all') {
    $combinedMeta = array_values(array_filter(
        $combinedMeta,
        fn($row) => $row['period'] === $period
    ));
}

usort($combinedMeta, function ($a, $b) {
    return strcasecmp($a['cols'][0], $b['cols'][0]) ?: strcasecmp($a['cols'][1], $b['cols'][1]);
});

$combined = array_map(fn($row) => $row['cols'], $combinedMeta);

if (empty($combined)) {
    ob_end_clean();
    header('Content-Type: text/plain');
    echo 'No plantilla records were found for the selected branch.';
    exit;
}

// ─── PDF class ─────────────────────────────────────────────────────────────
class ReportPDF extends FPDF {
    public $letterheadImage = '../assets/icons/LETTER HEAD GENERIC.jpg';
    public $imgW = 216;
    public $imgH = 279;
    public $contentStartY = 35;
    public $reportTitle = '';
    public $reportSubtitle = '';
    public $reportSubtitle2 = ''; // e.g. "For Period: 15 - 30 days" — shown below reportSubtitle, left-aligned
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
        if ($this->reportSubtitle2 !== '') {
            $this->SetFont('Arial', 'I', 9);
            $this->Cell(0, 5, $this->reportSubtitle2, 0, 1, 'L');
        }
        $this->Ln(2);

        if (!empty($this->colHeaders)) {
            $this->SetFont('Arial', 'B', 7.5);
            $this->SetFillColor(45, 104, 196);
            $this->SetTextColor(255, 255, 255);
            $lineHeight   = 3;
            $headerHeight = 6;

            $x = $this->GetX();
            $y = $this->GetY();

            foreach ($this->colHeaders as $i => $h) {
                $this->SetXY($x, $y);

                if (strpos($h, "\n") === false) {
                    $this->Cell($this->colWidths[$i], $headerHeight, $h, 1, 0, 'C', true);
                } else {
                    $this->Cell($this->colWidths[$i], $headerHeight, '', 1, 0, 'C', true);
                    $this->SetXY($x, $y);
                    $this->MultiCell($this->colWidths[$i], $lineHeight, $h, 0, 'C');
                }

                $x += $this->colWidths[$i];
            }

            $this->SetXY($this->lMargin, $y + $headerHeight);
            $this->SetTextColor(0, 0, 0);
        }
    }

    // FPDF doesn't expose page height publicly by default — needed to
    // manually compute how many rows fit before we must reserve space
    // for that page's summary row.
    function PageHeight() {
        return $this->h;
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
    'Branch', 'Brand', 'Plantilla', 'Deployed', 'Vacant',
    'Vacant Since', "Vacant\nPeriod", 'Complete Since', "Complete\nPeriod"
];
$widths  = [34, 34, 18, 18, 18, 22, 16, 22, 16];

$periodLabels = [
    'all'     => 'All',
    'lt15'    => 'Less than 15 days',
    '15to30'  => '15 - 30 days',
    '1to2mo'  => '1 month to 2 months',
    'gt2mo'   => 'More than 2 months',
];
$statusLabels = [
    'all'      => 'All',
    'complete' => 'Complete',
    'vacant'   => 'Vacant & Incomplete',
];
$statusLabel = $statusLabels[$status] ?? 'All';
$periodLabel = $periodLabels[$period] ?? 'All';

$pdf = new ReportPDF('P', 'mm', 'Letter');
$pdf->Ln(10);
$pdf->reportTitle     = fpdf_str('Branch Plantilla Records');
$pdf->reportSubtitle  = fpdf_str($statusLabel . ' statuses as of ' . $dateStr);
$pdf->reportSubtitle2 = fpdf_str($period !== 'all' ? 'For Period: ' . $periodLabel : '');
$pdf->colHeaders      = array_map('fpdf_str', $headers);
$pdf->colWidths       = $widths;

// Pagination is handled manually below so each page's summary row can be
// reserved space and kept as the last row of that page's table — FPDF's
// automatic page break has no concept of "leave room for one more row".
$pdf->SetAutoPageBreak(false);
$pdf->AddPage();

$fitSize = computeFitFontSize($pdf, $combined, $widths);
$rowH = 5;
$bottomMargin = 20; // mm reserved at the bottom of every page

$totalRows = count($combined);
$i = 0;

while ($i < $totalRows) {
    $pdf->SetFont('Arial', '', $fitSize);

    // How much vertical space is left on this page, minus one row height
    // reserved for the summary row that must close out this page's table.
    $availableHeight = $pdf->PageHeight() - $bottomMargin - $pdf->GetY() - $rowH;
    $rowsThatFit = max(1, (int) floor($availableHeight / $rowH));

    $pageRows = array_slice($combined, $i, $rowsThatFit);
    $i += count($pageRows);

    // ─ Data rows for this page ─
    foreach ($pageRows as $row) {
        foreach ($row as $c => $val) {
            $text = fitTextToWidth($pdf, fpdf_str((string)$val), $widths[$c]);
            $align = ($c === 1) ? 'L' : 'C';
            $pdf->Cell($widths[$c], $rowH, $text, 1, 0, $align);
        }
        $pdf->Ln();
        $pdf->SetFont('Arial', '', $fitSize);
    }

    // ─ Per-page summary row — part of the table, not a separate block ─
    $branchesOrBrandsOnPage = array_unique(array_map(fn($r) => $r[1], $pageRows));
    $plantillaSum = 0;
    $deployedSum  = 0;
    $vacantSum    = 0;
    foreach ($pageRows as $r) {
        $plantillaSum += is_numeric($r[2]) ? (float)$r[2] : 0;
        $deployedSum  += is_numeric($r[3]) ? (float)$r[3] : 0;
        $vacantSum    += is_numeric($r[4]) ? (float)$r[4] : 0;
    }

    $summaryRow = [
        'Count',
        (string) count($branchesOrBrandsOnPage),
        fmtCount($plantillaSum),
        fmtCount($deployedSum),
        fmtCount($vacantSum),
        '', '', '', '',
    ];

    $pdf->SetFont('Arial', 'B', $fitSize);
    $pdf->SetFillColor(230, 230, 230);
    foreach ($summaryRow as $c => $val) {
        $text = fitTextToWidth($pdf, fpdf_str((string)$val), $widths[$c]);
        $align = ($c === 1) ? 'L' : 'C';
        $pdf->Cell($widths[$c], $rowH, $text, 1, 0, $align, true);
    }
    $pdf->Ln();

    if ($i < $totalRows) {
        $pdf->AddPage();
    }
}

ob_end_clean();
$pdf->Output('I', "{$branch}_" . strtoupper($status) . "_PLANTILLAS_{$fileSuffix}.pdf");