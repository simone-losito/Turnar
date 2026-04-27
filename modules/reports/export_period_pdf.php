<?php
// modules/reports/export_period_pdf.php

require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/ReportRepository.php';
require_once __DIR__ . '/../turni/TurniRepository.php';

require_login();

$db         = db_connect();
$reportRepo = new ReportRepository($db);
$turniRepo  = new TurniRepository($db);

// --------------------------------------------------
// FILTRI
// --------------------------------------------------
$inizioMeseIso = date('Y-m-01');
$fineMeseIso   = date('Y-m-t');

$dataDa = trim((string)($_GET['data_da'] ?? $inizioMeseIso));
$dataA  = trim((string)($_GET['data_a'] ?? $fineMeseIso));

$dataDa = normalize_date_iso($dataDa) ?: $inizioMeseIso;
$dataA  = normalize_date_iso($dataA) ?: $fineMeseIso;

if ($dataDa > $dataA) {
    [$dataDa, $dataA] = [$dataA, $dataDa];
}

$operatoreId    = (int)($_GET['operatore_id'] ?? 0);
$destinazioneId = (int)($_GET['destinazione_id'] ?? 0);

$destinazioniMultiple = $_GET['destinazioni_multiple'] ?? [];
if (!is_array($destinazioniMultiple)) {
    $destinazioniMultiple = [];
}
$destinazioniMultiple = array_values(array_unique(array_map('intval', $destinazioniMultiple)));
$destinazioniMultiple = array_filter($destinazioniMultiple, static function ($id) {
    return $id > 0;
});

// --------------------------------------------------
// DATI SUPPORTO
// --------------------------------------------------
$operatori    = $turniRepo->getOperatori();
$destinazioni = $turniRepo->getDestinazioni();

$operatoriMap = [];
foreach ($operatori as $op) {
    $operatoriMap[(int)($op['id'] ?? 0)] = $op;
}

$destinazioniMap = [];
foreach ($destinazioni as $dest) {
    $destinazioniMap[(int)($dest['id'] ?? 0)] = $dest;
}

$operatoreLabel = 'Tutti gli operatori';
if ($operatoreId > 0 && isset($operatoriMap[$operatoreId])) {
    $op = $operatoriMap[$operatoreId];
    $operatoreLabel = trim((string)($op['cognome'] ?? '') . ' ' . (string)($op['nome'] ?? ''));
    if ($operatoreLabel === '') {
        $operatoreLabel = 'Operatore #' . $operatoreId;
    }
}

$destinazioneLabel = 'Tutte le destinazioni';
if ($destinazioneId > 0 && isset($destinazioniMap[$destinazioneId])) {
    $dest = $destinazioniMap[$destinazioneId];
    $destinazioneLabel = trim((string)($dest['commessa'] ?? ''));
    if ($destinazioneLabel === '') {
        $destinazioneLabel = 'Destinazione #' . $destinazioneId;
    }
}

$multiLabels = [];
foreach ($destinazioniMultiple as $multiId) {
    if (!isset($destinazioniMap[$multiId])) {
        continue;
    }
    $nome = trim((string)($destinazioniMap[$multiId]['commessa'] ?? ''));
    if ($nome !== '') {
        $multiLabels[] = $nome;
    }
}

// --------------------------------------------------
// DATI REPORT
// --------------------------------------------------
$rows = $reportRepo->getReportPeriodo($dataDa, $dataA, [
    'operatore_id'          => $operatoreId,
    'destinazione_id'       => $destinazioneId,
    'destinazioni_multiple' => $destinazioniMultiple,
]);

$totali = $reportRepo->calcolaTotali($rows);

// --------------------------------------------------
// MINI PDF ENGINE - NO DIPENDENZE
// --------------------------------------------------
class SimplePdfReport
{
    private array $pages = [];
    private string $currentPage = '';
    private int $pageCount = 0;

    private float $pageWidth = 842;   // A4 landscape
    private float $pageHeight = 595;
    private float $margin = 28;

    private string $font = 'F1';
    private float $fontSize = 10;

    public function getPageWidth(): float
    {
        return $this->pageWidth;
    }

    public function getPageHeight(): float
    {
        return $this->pageHeight;
    }

    public function getMargin(): float
    {
        return $this->margin;
    }

    public function addPage(): void
    {
        if ($this->currentPage !== '') {
            $this->pages[] = $this->currentPage;
        }
        $this->currentPage = '';
        $this->pageCount++;
    }

    public function setFont(string $font, float $size): void
    {
        $this->font = $font === 'bold' ? 'F2' : 'F1';
        $this->fontSize = $size;
    }

    private function pdfY(float $topY): float
    {
        return $this->pageHeight - $topY;
    }

    private function enc(string $text): string
    {
        $text = str_replace(["\r", "\n", "\t"], ' ', $text);
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
        if ($converted !== false) {
            $text = $converted;
        }

        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace('(', '\(', $text);
        $text = str_replace(')', '\)', $text);

        return $text;
    }

    public function text(float $x, float $topY, string $text): void
    {
        $y = $this->pdfY($topY);
        $safe = $this->enc($text);
        $this->currentPage .= "BT /{$this->font} {$this->fontSize} Tf 1 0 0 1 {$x} {$y} Tm ({$safe}) Tj ET\n";
    }

    public function line(float $x1, float $topY1, float $x2, float $topY2, float $width = 1): void
    {
        $y1 = $this->pdfY($topY1);
        $y2 = $this->pdfY($topY2);
        $this->currentPage .= "{$width} w {$x1} {$y1} m {$x2} {$y2} l S\n";
    }

    public function rect(float $x, float $topY, float $w, float $h, bool $fill = false, array $rgb = [1,1,1], float $lineWidth = 1): void
    {
        $y = $this->pdfY($topY + $h);
        $cmd = $fill ? 'B' : 'S';
        $this->currentPage .= sprintf("%.3f %.3f %.3f rg %.3f %.3f %.3f RG %.2f w %.2f %.2f %.2f %.2f re %s\n",
            $rgb[0], $rgb[1], $rgb[2],
            $rgb[0], $rgb[1], $rgb[2],
            $lineWidth, $x, $y, $w, $h, $cmd
        );
    }

    public function wrapText(string $text, float $width, float $fontSize): array
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));
        if ($text === '') {
            return ['-'];
        }

        $words = explode(' ', $text);
        $lines = [];
        $line = '';

        foreach ($words as $word) {
            $candidate = $line === '' ? $word : $line . ' ' . $word;
            if ($this->textWidth($candidate, $fontSize) <= $width) {
                $line = $candidate;
                continue;
            }

            if ($line !== '') {
                $lines[] = $line;
                $line = $word;
            } else {
                $lines[] = $word;
            }
        }

        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines ?: ['-'];
    }

    public function textWidth(string $text, float $fontSize): float
    {
        $len = mb_strlen($text, 'UTF-8');
        return $len * ($fontSize * 0.52);
    }

    public function save(): string
    {
        if ($this->currentPage !== '') {
            $this->pages[] = $this->currentPage;
            $this->currentPage = '';
        }

        if (empty($this->pages)) {
            $this->pages[] = '';
        }

        $objects = [];
        $kids = [];

        // 1 catalog, 2 pages, 3 font regular, 4 font bold
        $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[2] = null;
        $objects[3] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
        $objects[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>";

        $objNum = 5;

        foreach ($this->pages as $content) {
            $stream = $content;
            $contentObj = $objNum++;
            $pageObj = $objNum++;

            $objects[$contentObj] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream";
            $objects[$pageObj] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$this->pageWidth} {$this->pageHeight}] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents {$contentObj} 0 R >>";

            $kids[] = $pageObj . " 0 R";
        }

        $objects[2] = "<< /Type /Pages /Count " . count($kids) . " /Kids [" . implode(' ', $kids) . "] >>";

        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $n => $obj) {
            $offsets[$n] = strlen($pdf);
            $pdf .= $n . " 0 obj\n" . $obj . "\nendobj\n";
        }

        $xrefPos = strlen($pdf);
        $maxObj = max(array_keys($objects));

        $pdf .= "xref\n0 " . ($maxObj + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= $maxObj; $i++) {
            $off = $offsets[$i] ?? 0;
            $pdf .= sprintf("%010d 00000 n \n", $off);
        }

        $pdf .= "trailer << /Size " . ($maxObj + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n" . $xrefPos . "\n%%EOF";

        return $pdf;
    }
}

// --------------------------------------------------
// HELPERS PDF
// --------------------------------------------------
function pdf_cell_height(SimplePdfReport $pdf, string $text, float $width, float $fontSize, float $lineHeight = 11, float $paddingY = 8): float
{
    $lines = $pdf->wrapText($text, max(10, $width - 8), $fontSize);
    return max(24, count($lines) * $lineHeight + $paddingY);
}

function pdf_draw_cell(SimplePdfReport $pdf, float $x, float $y, float $w, float $h, string $text, string $font = 'regular', float $fontSize = 9, bool $fill = false, array $fillColor = [1,1,1], bool $center = false): void
{
    $pdf->rect($x, $y, $w, $h, $fill, $fillColor, 0.6);
    $pdf->setFont($font === 'bold' ? 'bold' : 'regular', $fontSize);

    $lines = $pdf->wrapText($text, max(10, $w - 8), $fontSize);
    $lineHeight = 11;
    $textTop = $y + 8;

    foreach ($lines as $i => $line) {
        $tx = $x + 4;
        if ($center) {
            $lineW = $pdf->textWidth($line, $fontSize);
            $tx = $x + max(4, ($w - $lineW) / 2);
        }
        $pdf->text($tx, $textTop + ($i * $lineHeight), $line);
    }
}

function pdf_render_header(SimplePdfReport $pdf, float &$y, string $title, string $subtitle): void
{
    $m = $pdf->getMargin();
    $w = $pdf->getPageWidth() - ($m * 2);

    $pdf->rect($m, $y, $w, 48, true, [0.10, 0.14, 0.24], 0.8);

    $pdf->setFont('bold', 20);
    $pdf->text($m + 16, $y + 18, $title);

    $pdf->setFont('regular', 10);
    $pdf->text($m + 16, $y + 34, $subtitle);

    $y += 62;
}

function pdf_render_summary(SimplePdfReport $pdf, float &$y, array $summary): void
{
    $m = $pdf->getMargin();
    $leftW  = 380;
    $rightW = $pdf->getPageWidth() - ($m * 2) - $leftW - 14;

    $boxH = 112;
    $pdf->rect($m, $y, $leftW, $boxH, true, [0.97, 0.98, 1.00], 0.8);
    $pdf->rect($m + $leftW + 14, $y, $rightW, $boxH, true, [0.97, 0.98, 1.00], 0.8);

    $pdf->setFont('bold', 11);
    $pdf->text($m + 12, $y + 16, 'Filtri applicati');
    $pdf->text($m + $leftW + 26, $y + 16, 'Totali');

    $pdf->setFont('regular', 9);

    $leftRows = [
        'Periodo: ' . $summary['periodo'],
        'Operatore: ' . $summary['operatore'],
        'Destinazione singola: ' . $summary['destinazione'],
        'Destinazioni multiple: ' . $summary['multiple'],
    ];

    $yy = $y + 34;
    foreach ($leftRows as $row) {
        $pdf->text($m + 12, $yy, $row);
        $yy += 16;
    }

    $rightRows = [
        'Turni totali: ' . $summary['turni'],
        'Giorni coinvolti: ' . $summary['giorni'],
        'Ore nette totali: ' . $summary['ore'],
        'Regola pausa: solo oltre 8 ore + pausa cantiere',
    ];

    $yy = $y + 34;
    foreach ($rightRows as $row) {
        $pdf->text($m + $leftW + 26, $yy, $row);
        $yy += 16;
    }

    $y += $boxH + 16;
}

function pdf_render_table_header(SimplePdfReport $pdf, float $y, array $cols): void
{
    $x = $pdf->getMargin();

    foreach ($cols as $col) {
        pdf_draw_cell($pdf, $x, $y, $col['w'], 24, $col['label'], 'bold', 9, true, [0.88, 0.93, 1.00], $col['center'] ?? false);
        $x += $col['w'];
    }
}

$pdf = new SimplePdfReport();
$pdf->addPage();

$margin = $pdf->getMargin();
$y = $margin;

$pdfTitle = 'Turnar - Report periodo';
$pdfSubtitle = 'Esportazione PDF del report periodo';
pdf_render_header($pdf, $y, $pdfTitle, $pdfSubtitle);

pdf_render_summary($pdf, $y, [
    'periodo'      => format_date_it($dataDa) . ' - ' . format_date_it($dataA),
    'operatore'    => $operatoreLabel,
    'destinazione' => $destinazioneLabel,
    'multiple'     => !empty($multiLabels) ? implode(', ', $multiLabels) : 'Nessuna',
    'turni'        => (int)($totali['turni'] ?? 0),
    'giorni'       => (int)($totali['giorni'] ?? 0),
    'ore'          => number_format((float)($totali['ore_totali'] ?? 0), 2, ',', '.'),
]);

$cols = [
    ['label' => 'Data',        'w' => 62,  'center' => true],
    ['label' => 'Operatore',   'w' => 170],
    ['label' => 'Destinazione','w' => 185],
    ['label' => 'Comune',      'w' => 120],
    ['label' => 'Inizio',      'w' => 62,  'center' => true],
    ['label' => 'Fine',        'w' => 62,  'center' => true],
    ['label' => 'Ore',         'w' => 55,  'center' => true],
];

pdf_render_table_header($pdf, $y, $cols);
$y += 24;

$pageBottomLimit = $pdf->getPageHeight() - $margin - 22;

foreach ($rows as $row) {
    $cellValues = [
        format_date_it((string)($row['data'] ?? '')),
        (string)($row['operatore'] ?? '-'),
        (string)($row['destinazione'] ?? '-'),
        (string)($row['comune'] ?? '-'),
        (string)($row['ora_inizio'] ?? '-'),
        (string)($row['ora_fine'] ?? '-'),
        number_format((float)($row['ore_nette'] ?? 0), 2, ',', '.'),
    ];

    $rowHeight = 24;
    foreach ($cols as $idx => $col) {
        $rowHeight = max($rowHeight, pdf_cell_height($pdf, $cellValues[$idx], $col['w'], 9));
    }

    if (($y + $rowHeight) > $pageBottomLimit) {
        $pdf->addPage();
        $y = $margin;
        pdf_render_header($pdf, $y, $pdfTitle, 'Continuazione report periodo');
        pdf_render_table_header($pdf, $y, $cols);
        $y += 24;
    }

    $x = $margin;

    foreach ($cols as $idx => $col) {
        $fill = false;
        $fillColor = [1,1,1];

        if ($idx === 2) {
            $destName = mb_strtolower(trim((string)($row['destinazione'] ?? '')), 'UTF-8');
            if (in_array($destName, ['ferie', 'permessi', 'corsi di formazione', 'malattia'], true)) {
                $fill = true;
                $fillColor = [0.95, 0.92, 1.00];
            }
        }

        pdf_draw_cell(
            $pdf,
            $x,
            $y,
            $col['w'],
            $rowHeight,
            $cellValues[$idx],
            'regular',
            9,
            $fill,
            $fillColor,
            $col['center'] ?? false
        );

        $x += $col['w'];
    }

    $y += $rowHeight;
}

if (empty($rows)) {
    if (($y + 50) > $pageBottomLimit) {
        $pdf->addPage();
        $y = $margin;
        pdf_render_header($pdf, $y, $pdfTitle, 'Nessun risultato');
    }

    $pdf->rect($margin, $y, $pdf->getPageWidth() - ($margin * 2), 46, true, [0.98, 0.98, 0.99], 0.8);
    $pdf->setFont('bold', 11);
    $pdf->text($margin + 14, $y + 18, 'Nessun risultato trovato');
    $pdf->setFont('regular', 9);
    $pdf->text($margin + 14, $y + 33, 'Non sono stati trovati turni con i filtri attuali.');
    $y += 60;
}

// --------------------------------------------------
// OUTPUT PDF
// --------------------------------------------------
$filename = 'report_periodo_' . $dataDa . '_a_' . $dataA . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

echo $pdf->save();
exit;