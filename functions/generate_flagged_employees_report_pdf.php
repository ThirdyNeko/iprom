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
// an internal HTTP request to get_flagged_employees.php, which ALSO calls
// session_start(). PHP's default session handler locks the session file for
// the life of the request that holds it — so without this, that inner
// request blocks waiting for this outer request's lock, times out, and you
// get a corrupted/empty response instead of a PDF.
session_write_close();

require('../fpdf/fpdf.php');

// ─── Params ────────────────────────────────────────────────────────────────
$branch = $_GET['branch'] ?? 'ALL';
$brand  = $_GET['brand']  ?? 'ALL';
$period = $_GET['period'] ?? 'all'; // all | lt15 | 15_30 | 1_2mo | 2mo_plus

$branchLabel = $_GET['branch_label'] ?? ($branch === 'ALL' ? 'All Branches' : $branch);
$brandLabel  = $_GET['brand_label']  ?? ($brand  === 'ALL' ? 'All Brands'   : $brand);

// ─── Helpers ───────────────────────────────────────────────────────────────
function fpdf_str($s): string {
    $s = (string)$s;
    if (!mb_check_encoding($s, 'UTF-8')) {
        $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8');
    }
    $result = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $s);
    return $result === false ? '' : $result;
}

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
// get_flagged_employees.php already applies the branch/brand/period
// filtering and the flag-count / oldest-flag logic — single source of
// truth, no SQL duplicated here (same pattern as the vacant/complete
// plantilla fetches above).
$employees = fetchInternalJson('get_flagged_employees.php', [
    'branch' => $branch,
    'brand'  => $brand,
    'period' => $period,
]);

if (empty($employees)) {
    ob_end_clean();
    header('Content-Type: text/plain');
    echo 'No flagged employees were found for the selected filters.';
    exit;
}

// get_flagged_employees.php already returns rows ordered oldest-flag-first
// (ORDER BY oldest_flag_date ASC == largest "days since" first), so no
// re-sort needed here.
$combined = array_map(function ($e) {
    return [
        str_replace(',', ', ', (string)($e['branches'] ?? '')),
        str_replace(',', ', ', (string)($e['brands'] ?? '')),
        $e['name'] ?? '',
        (string)($e['flag_count'] ?? 0),
        $e['days_since_label'] ?? '',
    ];
}, $employees);

// ─── PDF class ─────────────────────────────────────────────────────────────
class ReportPDF extends FPDF {
    public $letterheadImage = '../assets/icons/CROWN_FLAG.png';
    public $imgW = 130;  // 1321:826 aspect ratio preserved (130 x 81.3mm)
    public $imgH = 81.3;
    public $opacity = 0.1;
    public $contentStartY = 15;
    public $reportTitle = '';
    public $reportSubtitle = '';
    public $reportSubtitle2 = []; // array of ['text' => ..., 'bold' => bool] segments, rendered left-aligned on one line
    public $colHeaders = [];
    public $colWidths = [];
    public $headerRowH = 6;


    function Header() {
        $x = ($this->GetPageWidth() - $this->imgW) / 2;
        $y = ($this->GetPageHeight() - $this->imgH) / 2;
        $this->Image($this->letterheadImage, $x, $y, $this->imgW, $this->imgH);
        $this->SetY($this->contentStartY);

        if ($this->reportTitle !== '') {
            $this->SetFont('Arial', 'B', 12);
            $this->Cell(0, 6, $this->reportTitle, 0, 1, 'C');
        }
        if ($this->reportSubtitle !== '') {
            $this->SetFont('Arial', 'I', 9);
            $this->Cell(0, 5, $this->reportSubtitle, 0, 1, 'C');
        }

        if (!empty($this->reportSubtitle2)) {
            // reportSubtitle2 is an array of ['text' => ..., 'bold' => bool]
            // segments rendered left-to-right on one line, since FPDF can't
            // mix bold/regular within a single Cell().
            foreach ($this->reportSubtitle2 as $seg) {
                $this->SetFont('Arial', $seg['bold'] ? 'B' : '', 9);
                $w = $this->GetStringWidth($seg['text']) + 1;
                $this->Cell($w, 5, $seg['text'], 0, 0, 'L');
            }
            $this->Ln(5);
        } else {
            $this->Ln(2);
        }

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

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 7);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
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
$dateStr    = date('l, F d, Y h:i A');
$fileSuffix = date('Y-m-d');

$headers = ['Branch', 'Brand', 'Name', 'Flag Count', "Period"];
$widths  = [50, 40, 50, 22, 28]; // sums to 190mm, fits portrait Letter (215.9mm) w/ margins

$periodLabels = [
    'all'      => 'All',
    'lt15'     => 'Less than 15 Days',
    '15_30'    => '15 - 30 Days',
    '1_2mo'    => '1 - 2 Months',
    '2mo_plus' => 'More than 2 Months',
];
$periodLabel = $periodLabels[$period] ?? 'All';

$pdf = new ReportPDF('P', 'mm', 'Letter');
$pdf->Ln(10);
$pdf->reportTitle    = fpdf_str('Flagged Employees');
$pdf->reportSubtitle = fpdf_str('As of ' . $dateStr);

// Built as segments (rather than one concatenated string) so Header()
// can render "Branch:", "Brand:", and "Period:" in bold while the
// values stay regular weight — FPDF can't mix weights within one Cell().
$pdf->reportSubtitle2 = [
    ['text' => 'Branch: ', 'bold' => true],
    ['text' => fpdf_str($branchLabel) . '   ', 'bold' => false],
    ['text' => 'Brand: ', 'bold' => true],
    ['text' => fpdf_str($brandLabel), 'bold' => false],
];
if ($period !== 'all') {
    $pdf->reportSubtitle2[] = ['text' => '   Period: ', 'bold' => true];
    $pdf->reportSubtitle2[] = ['text' => fpdf_str($periodLabel), 'bold' => false];
}

$pdf->colHeaders = array_map('fpdf_str', $headers);
$pdf->colWidths  = $widths;

// Pagination is handled manually below so each page's summary row can be
// reserved space and kept as the last row of that page's table — FPDF's
// automatic page break has no concept of "leave room for one more row".
$pdf->SetAutoPageBreak(false);
$pdf->AddPage();

$fitSize      = computeFitFontSize($pdf, $combined, $widths);
$rowH         = 5;
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
            $text  = fitTextToWidth($pdf, fpdf_str((string)$val), $widths[$c]);
            $align = ($c === 3 || $c === 4) ? 'C' : 'L';
            $pdf->Cell($widths[$c], $rowH, $text, 1, 0, $align);
        }
        $pdf->Ln();
        $pdf->SetFont('Arial', '', $fitSize);
    }

    // ─ Per-page summary row — part of the table, not a separate block ─
    $flagSum = 0;
    foreach ($pageRows as $r) {
        $flagSum += is_numeric($r[3]) ? (float)$r[3] : 0;
    }

    $summaryRow = [
        'Count: ' . count($pageRows),
        '',
        '',
        fmtCount($flagSum),
        '',
    ];

    $pdf->SetFont('Arial', 'B', $fitSize);
    $pdf->SetFillColor(230, 230, 230);
    foreach ($summaryRow as $c => $val) {
        $text  = fitTextToWidth($pdf, fpdf_str((string)$val), $widths[$c]);
        $align = ($c === 3 || $c === 4) ? 'C' : 'L';
        $pdf->Cell($widths[$c], $rowH, $text, 1, 0, $align, true);
    }
    $pdf->Ln();

    if ($i < $totalRows) {
        $pdf->AddPage();
    }
}

ob_end_clean();
$branchTag = $branch === 'ALL' ? 'ALL_BRANCHES' : $branch;
$pdf->Output('I', "{$branchTag}_FLAGGED_EMPLOYEES_{$fileSuffix}.pdf");