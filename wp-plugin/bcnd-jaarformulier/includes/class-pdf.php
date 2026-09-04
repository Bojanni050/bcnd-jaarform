<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Self-contained PDF generator (no external dependencies).
 * Produces a multi-page PDF using the built-in Helvetica fonts.
 */
class BCND_PDF {

    private $pages = [];
    private $cur = '';
    private $y = 800;
    private $pageH = 842; // A4 in points
    private $pageW = 595;
    private $marginX = 40;
    private $marginBottom = 50;

    const ACT = [
        'externe_bijscholing' => 'Externe bijscholing',
        'bcnd_bijscholing' => 'BCND-bijscholing',
        'bcnd_ledenbijeenkomst' => 'BCND ledenbijeenkomst',
        'overige_activiteit' => 'Overige activiteit',
    ];

    public static function generate($member, $form, $trainings, $overview, $norms) {
        $p = new self();
        return $p->build($member, $form, $trainings, $overview, $norms);
    }

    private function build($member, $form, $trainings, $overview, $norms) {
        $this->new_page();
        $this->text('BCND Jaarformulier Licentieleden', $this->marginX, 800, 18, true, '1E3F33');
        $this->text('Beroepsvereniging van Complementaire en Natuurlijke geneeswijzen voor Dieren', $this->marginX, 784, 9, false, '5C584A');
        $this->hline(778);
        $this->y = 760;

        $status = ucfirst(str_replace('_', ' ', $form['status']));
        $this->text('Jaar ' . $form['year'] . '   |   Status: ' . $status, $this->marginX, $this->y, 10);
        $this->y -= 26;

        // Licentielid
        $this->section('Licentielid');
        $this->kv('Naam', $member['name'], 'Lidnummer BCND', $member['member_number']);
        $this->kv('Adres', $member['address'], 'Plaats', $member['city']);
        $this->kv('Licentielid sinds', substr((string) $member['license_since'], 0, 10), 'Datum', date_i18n('d-m-Y'));
        $this->y -= 10;

        // Bijscholingen
        $this->section('Gevolgde bijscholingen (minimaal ' . $norms['points_norm'] . ' punten)');
        $cols = [
            ['Datum', 60], ['Uren', 32], ['Organisatie', 95], ['Onderwerp', 150], ['Spreker', 80], ['Type', 90], ['Pt', 25],
        ];
        $this->table_header($cols);
        foreach ($trainings as $t) {
            $row = [
                substr((string) $t['date'], 0, 10),
                (string) $t['hours'],
                $t['organization'],
                $t['subject'],
                $t['speaker'],
                isset(self::ACT[$t['activity_type']]) ? self::ACT[$t['activity_type']] : '',
                ($t['status'] === 'goedgekeurd' && $t['points'] !== null) ? (string) $t['points'] : '-',
            ];
            $this->table_row($cols, $row);
        }
        $this->y -= 6;
        $this->text('Totaal goedgekeurde punten: ' . $overview['points']['achieved'] . ' / ' . $norms['points_norm'],
            $this->marginX, $this->y, 10, true, '1E3F33');
        $this->y -= 24;

        // Consulten
        $this->ensure(120);
        $this->section('Consulten');
        $c = $overview['consults'];
        $this->kv('Totaal aantal consulten', $c['achieved'] . ' / ' . $norms['consults_norm'], 'Aantal 1e consulten', (string) $c['first_consults']);
        $this->kv('Aantal vervolgconsulten', (string) $c['followup_consults'], 'Overige activiteiten', $c['other_activities'] ?: '-');
        $this->y -= 10;

        // Afwijking
        if (!empty($form['deviation_reason'])) {
            $this->ensure(80);
            $this->section('Toelichting bij afwijking van de norm');
            $this->paragraph($form['deviation_reason']);
        }

        // Ondertekening / akkoord
        $this->signature_block($form);

        $this->y -= 14;
        $this->hline($this->y);
        $this->y -= 14;
        $this->paragraph('Automatisch gegenereerd door de BCND Nascholingsadministratie op ' . date_i18n('d-m-Y H:i') .
            '. Toegepaste normen: ' . $norms['points_norm'] . ' punten / ' . $norms['consults_norm'] .
            ' consulten (lidmaatschapsjaar ' . $norms['membership_year'] . ').', 8, '5C584A');

        $this->finish_page();
        return $this->render();
    }

    /* ---------- layout helpers ---------- */

    private function signature_block($form) {
        $this->ensure(150);
        $this->y -= 6;
        $this->section('Ondertekening');
        $this->paragraph('Ondergetekende verklaart dit jaarformulier naar waarheid en volledig te hebben ingevuld.', 9);
        $this->y -= 26;

        $lineY = $this->y;
        $this->sigline($this->marginX, 210, $lineY);
        $this->sigline($this->marginX + 305, 200, $lineY);
        $this->y -= 12;
        $this->text('Plaats en datum', $this->marginX, $this->y, 8, false, '5C584A');
        $this->text('Handtekening licentielid', $this->marginX + 305, $this->y, 8, false, '5C584A');
        $this->y -= 30;

        if (!empty($form['status']) && $form['status'] === 'goedgekeurd') {
            $this->ensure(40);
            $reviewer = !empty($form['reviewed_by']) ? $form['reviewed_by'] : 'BCND administratie';
            $date = !empty($form['reviewed_at']) ? substr((string) $form['reviewed_at'], 0, 10) : date_i18n('Y-m-d');
            $this->text('Akkoord BCND administratie: ' . $reviewer . '  —  ' . $date, $this->marginX, $this->y, 9, true, '1E3F33');
            $this->y -= 18;
        }
    }

    private function sigline($x, $width, $y) {
        $this->cur .= sprintf("0.55 0.55 0.55 RG 0.6 w %F %F m %F %F l S\n", $x, $y, $x + $width, $y);
    }

    private function section($title) {
        $this->ensure(40);
        $this->y -= 4;
        $this->text($title, $this->marginX, $this->y, 12, true, '1E3F33');
        $this->y -= 4;
        $this->hline($this->y);
        $this->y -= 16;
    }

    private function kv($l1, $v1, $l2, $v2) {
        $this->ensure(20);
        $this->text($l1, $this->marginX, $this->y, 8.5, true, '1E3F33');
        $this->text($this->clip($v1, 45), $this->marginX + 110, $this->y, 9);
        $this->text($l2, $this->marginX + 300, $this->y, 8.5, true, '1E3F33');
        $this->text($this->clip($v2, 30), $this->marginX + 410, $this->y, 9);
        $this->y -= 18;
    }

    private function table_header($cols) {
        $this->ensure(24);
        $x = $this->marginX;
        foreach ($cols as $c) {
            $this->text($c[0], $x, $this->y, 8, true, '1E3F33');
            $x += $c[1];
        }
        $this->y -= 3;
        $this->hline($this->y);
        $this->y -= 12;
    }

    private function table_row($cols, $vals) {
        $this->ensure(16);
        $x = $this->marginX;
        foreach ($cols as $i => $c) {
            $maxchars = (int) floor($c[1] / 4.2);
            $this->text($this->clip((string) $vals[$i], $maxchars), $x, $this->y, 7.5);
            $x += $c[1];
        }
        $this->y -= 13;
    }

    private function paragraph($text, $size = 9, $color = '000000') {
        $words = preg_split('/\s+/', trim(wp_strip_all_tags($text)));
        $line = '';
        $maxchars = 105;
        foreach ($words as $w) {
            if (strlen($line . ' ' . $w) > $maxchars) {
                $this->ensure(14);
                $this->text($line, $this->marginX, $this->y, $size, false, $color);
                $this->y -= 12;
                $line = $w;
            } else {
                $line = $line === '' ? $w : $line . ' ' . $w;
            }
        }
        if ($line !== '') {
            $this->ensure(14);
            $this->text($line, $this->marginX, $this->y, $size, false, $color);
            $this->y -= 12;
        }
    }

    private function clip($s, $max) {
        $s = wp_strip_all_tags((string) $s);
        if (function_exists('mb_strlen')) {
            if (mb_strlen($s) > $max) { return mb_substr($s, 0, $max - 1) . '.'; }
            return $s;
        }
        if (strlen($s) > $max) { return substr($s, 0, $max - 1) . '.'; }
        return $s;
    }

    private function ensure($needed) {
        if ($this->y - $needed < $this->marginBottom) {
            $this->finish_page();
            $this->new_page();
            $this->y = 800;
        }
    }

    private function hline($y) {
        $this->cur .= sprintf("0.66 0.66 0.66 RG 0.6 w %F %F m %F %F l S\n", $this->marginX, $y, $this->pageW - $this->marginX, $y);
    }

    private function text($str, $x, $y, $size, $bold = false, $hex = '000000') {
        $font = $bold ? '/F2' : '/F1';
        list($r, $g, $b) = $this->rgb($hex);
        $safe = $this->esc($str);
        $this->cur .= sprintf("BT %s %F Tf %F %F %F rg %F %F Td (%s) Tj ET\n", $font, $size, $r, $g, $b, $x, $y, $safe);
    }

    private function rgb($hex) {
        return [hexdec(substr($hex, 0, 2)) / 255, hexdec(substr($hex, 2, 2)) / 255, hexdec(substr($hex, 4, 2)) / 255];
    }

    private function esc($s) {
        $s = (string) $s;
        // Latin-1 for the standard Helvetica encoding.
        if (function_exists('iconv')) {
            $conv = @iconv('UTF-8', 'windows-1252//TRANSLIT', $s);
            if ($conv !== false) { $s = $conv; }
        }
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ''], $s);
    }

    private function new_page() { $this->cur = ''; $this->y = 800; }
    private function finish_page() { $this->pages[] = $this->cur; $this->cur = ''; }

    private function render() {
        $objs = [];
        $nfonts = 2;
        // 1: catalog, 2: pages, then per page: content + page obj, then fonts.
        $numPages = count($this->pages);
        $pageObjIds = [];
        $contentObjIds = [];

        $objId = 3; // 1 catalog, 2 pages tree
        foreach ($this->pages as $i => $content) {
            $contentObjIds[$i] = $objId++;
            $pageObjIds[$i] = $objId++;
        }
        $fontRegular = $objId++;
        $fontBold = $objId++;

        // Catalog
        $objs[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        // Pages tree
        $kids = implode(' ', array_map(function ($id) { return "$id 0 R"; }, $pageObjIds));
        $objs[2] = "<< /Type /Pages /Count $numPages /Kids [$kids] >>";

        foreach ($this->pages as $i => $content) {
            $len = strlen($content);
            $objs[$contentObjIds[$i]] = "<< /Length $len >>\nstream\n$content\nendstream";
            $objs[$pageObjIds[$i]] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$this->pageW} {$this->pageH}] "
                . "/Resources << /Font << /F1 $fontRegular 0 R /F2 $fontBold 0 R >> >> "
                . "/Contents {$contentObjIds[$i]} 0 R >>";
        }
        $objs[$fontRegular] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
        $objs[$fontBold] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";

        ksort($objs);
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objs as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "$id 0 obj\n$body\nendobj\n";
        }
        $xrefPos = strlen($pdf);
        $maxId = max(array_keys($objs));
        $pdf .= "xref\n0 " . ($maxId + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($id = 1; $id <= $maxId; $id++) {
            if (isset($offsets[$id])) {
                $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
            } else {
                $pdf .= "0000000000 65535 f \n";
            }
        }
        $pdf .= "trailer\n<< /Size " . ($maxId + 1) . " /Root 1 0 R >>\nstartxref\n$xrefPos\n%%EOF";
        return $pdf;
    }
}
