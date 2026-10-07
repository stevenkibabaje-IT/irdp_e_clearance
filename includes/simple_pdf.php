<?php
declare(strict_types=1);

/** Build PDF reports, certificates and transcripts with text layout and verification QR codes. */
require_once __DIR__.'/qrcode.php';

function pdf_escape(string $text): string
{
    $text = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text) ?: $text;

    return str_replace(
        ['\\', '(', ')', "\r", "\n"],
        ['\\\\', '\\(', '\\)', ' ', ' '],
        $text
    );
}

function pdf_output(array $pages, string $filename): never
{
    $objects = [];
    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
    $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
    $kids = [];
    $nextObject = 5;

    $imageObjects = [];
    $imageNames = [];
    foreach ($pages as $pageDocument) {
        $pageItems = $pageDocument['items'] ?? $pageDocument;
        foreach ($pageItems as $item) {
            if (($item['type'] ?? '') !== 'image' || !is_file($item['path'])) {
                continue;
            }

            if (!isset($imageObjects[$item['path']])) {
                $imageObjects[$item['path']] = $nextObject++;
                $imageNames[$item['path']] = 'Im' . (count($imageNames) + 1);
            }
        }
    }

    foreach ($imageObjects as $path => $objectNumber) {
        $imageInfo = getimagesize($path);
        $imageData = file_get_contents($path);
        if (!$imageInfo || $imageData === false || ($imageInfo['mime'] ?? '') !== 'image/jpeg') {
            unset($imageObjects[$path], $imageNames[$path]);
            continue;
        }

        $objects[$objectNumber] =
            '<< /Type /XObject /Subtype /Image /Width ' . $imageInfo[0] .
            ' /Height ' . $imageInfo[1] . ' /ColorSpace /DeviceRGB' .
            ' /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen($imageData) .
            " >>\nstream\n" . $imageData . "\nendstream";
    }

    foreach ($pages as $pageDocument) {
        $pageItems = $pageDocument['items'] ?? $pageDocument;
        $pageWidth = $pageDocument['width'] ?? 595;
        $pageHeight = $pageDocument['height'] ?? 842;
        $pageObject = $nextObject++;
        $contentObject = $nextObject++;
        $kids[] = $pageObject . ' 0 R';
        $stream = "q\n";

        foreach ($pageItems as $item) {
            if (($item['type'] ?? 'text') === 'rect') {
                $stream .= sprintf(
                    "%.2f %.2f %.2f rg %.2f %.2f %.2f %.2f re f\n",
                    $item['color'][0], $item['color'][1], $item['color'][2],
                    $item['x'], $item['y'], $item['width'], $item['height']
                );
                continue;
            }

            if (($item['type'] ?? 'text') === 'line') {
                $stream .= sprintf(
                    "%.2f %.2f %.2f %.2f RG %.2f %.2f m %.2f %.2f l S\n",
                    $item['color'][0], $item['color'][1], $item['color'][2], $item['width'],
                    $item['x1'], $item['y'], $item['x2'], $item['y']
                );
                continue;
            }

            if (($item['type'] ?? 'text') === 'image' && isset($imageObjects[$item['path']])) {
                $stream .= sprintf(
                    "q %.2f 0 0 %.2f %.2f %.2f cm /%s Do Q\n",
                    $item['width'],
                    $item['height'],
                    $item['x'],
                    $item['y'],
                    $imageNames[$item['path']]
                );
                continue;
            }

            $font = ($item['bold'] ?? false) ? 'F2' : 'F1';
            $stream .= sprintf(
                "0 g BT /%s %d Tf 1 0 0 1 %d %d Tm (%s) Tj ET\n",
                $font,
                $item['size'],
                $item['x'],
                $item['y'],
                pdf_escape((string)$item['text'])
            );
        }

        $stream .= "Q";
        $xObjectResource = [];
        foreach ($imageObjects as $path => $objectNumber) {
            $xObjectResource[] = '/' . $imageNames[$path] . ' ' . $objectNumber . ' 0 R';
        }
        $resources = '<< /Font << /F1 3 0 R /F2 4 0 R >>';
        if ($xObjectResource) {
            $resources .= ' /XObject << ' . implode(' ', $xObjectResource) . ' >>';
        }
        $resources .= ' >>';

        $objects[$pageObject] =
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . $pageWidth . ' ' . $pageHeight . ']' .
            ' /Resources ' . $resources .
            ' /Contents ' . $contentObject . ' 0 R >>';
        $objects[$contentObject] =
            '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";
    }

    $objects[2] =
        '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';
    ksort($objects);

    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $offsets = [];
    $max = max(array_keys($objects));

    for ($i = 1; $i <= $max; $i++) {
        if (!isset($objects[$i])) {
            continue;
        }

        $offsets[$i] = strlen($pdf);
        $pdf .= $i . " 0 obj\n" . $objects[$i] . "\nendobj\n";
    }

    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . ($max + 1) . "\n0000000000 65535 f \n";
    for ($i = 1; $i <= $max; $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
    }
    $pdf .= "trailer\n<< /Size " . ($max + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
    header('Content-Length: ' . strlen($pdf));
    header('X-Content-Type-Options: nosniff');
    echo $pdf;
    exit;
}

function pdf_report(string $title, array $lines, string $filename): never
{
    $pages = [];
    $page = [];
    $y = 760;

    $add = static function (string $text, int $size = 10, bool $bold = false) use (&$pages, &$page, &$y): void {
        if ($y < 55) {
            $pages[] = $page;
            $page = [];
            $y = 760;
        }

        $page[] = [
            'text' => $text,
            'size' => $size,
            'y' => $y,
            'x' => 45,
            'bold' => $bold,
        ];

        $y -= $size + 6;
    };

    $add($title, 18, true);
    $add('Institute of Rural Development Planning', 11);
    $add('Generated: ' . date('Y-m-d H:i:s'), 9);
    $page[] = ['type' => 'line', 'x1' => 45, 'x2' => 550, 'y' => $y + 3, 'width' => 1, 'color' => [0.03, 0.36, 0.22]];
    $add('', 9);

    foreach ($lines as $line) {
        $text = (string) $line;

        while (strlen($text) > 95) {
            $position = strrpos(substr($text, 0, 95), ' ');
            $position = $position === false ? 95 : $position;
            $add(substr($text, 0, $position), 9);
            $text = ltrim(substr($text, $position));
        }

        $add($text, 9);
    }

    $pages[] = ['items' => $page, 'width' => 595, 'height' => 842];
    pdf_output($pages, $filename);
}

function pdf_centered_text(string $text, int $size, int $pageWidth, int $y, bool $bold = false): array
{
    $estimatedWidth = strlen($text) * $size * 0.52;
    return [
        'text' => $text,
        'size' => $size,
        'x' => max(30, (int) round(($pageWidth - $estimatedWidth) / 2)),
        'y' => $y,
        'bold' => $bold,
    ];
}

function pdf_wrapped_lines(string $text, int $maximumCharacters): array
{
    $text = trim($text) ?: '-';
    $words = preg_split('/\s+/', $text) ?: ['-'];
    $lines = [];
    $line = '';

    foreach ($words as $word) {
        $candidate = $line === '' ? $word : $line . ' ' . $word;
        if ($line !== '' && strlen($candidate) > $maximumCharacters) {
            $lines[] = $line;
            $line = $word;
        } else {
            $line = $candidate;
        }
    }

    if ($line !== '') {
        $lines[] = $line;
    }

    return $lines ?: ['-'];
}

function pdf_certificate(array $data, string $filename): never
{
    $pageWidth = 842;
    $pageHeight = 595;
    $green = [0.03, 0.28, 0.16];
    $gold = [0.78, 0.58, 0.08];
    $ivory = [0.995, 0.99, 0.965];
    $muted = [0.25, 0.30, 0.27];
    $page = [
        ['type' => 'rect', 'x' => 0, 'y' => 0, 'width' => $pageWidth, 'height' => $pageHeight, 'color' => [1, 1, 1]],
        ['type' => 'rect', 'x' => 25, 'y' => 25, 'width' => 792, 'height' => 545, 'color' => $ivory],
        ['type' => 'line', 'x1' => 25, 'x2' => 817, 'y' => 570, 'width' => 3, 'color' => $green],
        ['type' => 'line', 'x1' => 36, 'x2' => 806, 'y' => 559, 'width' => 1, 'color' => $gold],
        ['type' => 'line', 'x1' => 36, 'x2' => 806, 'y' => 36, 'width' => 1, 'color' => $gold],
        ['type' => 'line', 'x1' => 25, 'x2' => 817, 'y' => 25, 'width' => 3, 'color' => $green],
    ];

    $logoPath = __DIR__ . '/../assets/img/irdp-logo.jpg';
    if (is_file($logoPath)) {
        $page[] = ['type' => 'image', 'path' => $logoPath, 'x' => 386, 'y' => 475, 'width' => 70, 'height' => 70];
    }

    $page[] = pdf_centered_text('INSTITUTE OF RURAL DEVELOPMENT PLANNING', 16, $pageWidth, 455, true);
    $page[] = ['type' => 'line', 'x1' => 288, 'x2' => 554, 'y' => 438, 'width' => 1, 'color' => $gold];
    $page[] = pdf_centered_text('STUDENT CLEARANCE CERTIFICATE', 24, $pageWidth, 400, true);
    $page[] = pdf_centered_text('This is to certify that', 12, $pageWidth, 365, false);

    $name = trim((string) $data['full_name']);
    $nameLines = pdf_wrapped_lines($name, 42);
    $nameSize = count($nameLines) > 1 || strlen($name) > 30 ? 22 : 26;
    foreach ($nameLines as $index => $nameLine) {
        $page[] = pdf_centered_text($nameLine, $nameSize, $pageWidth, 326 - ($index * 25), true);
    }
    $bodyY = 292 - ((count($nameLines) - 1) * 25);
    $page[] = pdf_centered_text('has successfully completed all 11 clearance stages.', 12, $pageWidth, $bodyY, false);
    $registrationY = $bodyY - 27;
    $page[] = pdf_centered_text('Registration Number: ' . (string) $data['registration_number'], 12, $pageWidth, $registrationY, false);
    $detailsRuleY = $registrationY - 27;
    $page[] = ['type' => 'line', 'x1' => 95, 'x2' => 747, 'y' => $detailsRuleY, 'width' => 1, 'color' => $gold];

    $leftLabelX = 95;
    $leftValueX = 205;
    $rightLabelX = 470;
    $rightValueX = 585;
    $programmeY = $detailsRuleY - 28;
    $page[] = ['text' => 'PROGRAMME', 'size' => 9, 'x' => $leftLabelX, 'y' => $programmeY, 'bold' => true];
    $page[] = ['text' => (string) ($data['programme'] ?: '-'), 'size' => 11, 'x' => $leftValueX, 'y' => $programmeY - 1, 'color' => $muted];
    $page[] = ['text' => 'ACADEMIC YEAR', 'size' => 9, 'x' => $rightLabelX, 'y' => $programmeY, 'bold' => true];
    $page[] = ['text' => (string) $data['academic_year'], 'size' => 11, 'x' => $rightValueX, 'y' => $programmeY - 1, 'color' => $muted];
    $departmentLabelY = $programmeY - 32;
    $page[] = ['text' => 'DEPARTMENT', 'size' => 9, 'x' => $leftLabelX, 'y' => $departmentLabelY, 'bold' => true];
    $departmentLines = pdf_wrapped_lines((string) ($data['department'] ?: '-'), 39);
    foreach ($departmentLines as $index => $departmentLine) {
        $page[] = ['text' => $departmentLine, 'size' => 10, 'x' => $leftValueX, 'y' => $departmentLabelY - ($index * 14), 'color' => $muted];
    }
    $page[] = ['text' => 'COMPLETION DATE', 'size' => 9, 'x' => $rightLabelX, 'y' => $departmentLabelY, 'bold' => true];
    $page[] = ['text' => (string) ($data['completed_at'] ?: '-'), 'size' => 10, 'x' => $rightValueX, 'y' => $departmentLabelY - 1, 'color' => $muted];

    $page[] = ['type' => 'rect', 'x' => 75, 'y' => 65, 'width' => 430, 'height' => 70, 'color' => [0.96, 0.965, 0.94]];
    $page[] = ['text' => 'CERTIFICATE NO.', 'size' => 8, 'x' => 95, 'y' => 112, 'bold' => true];
    $page[] = ['text' => (string) $data['certificate_number'], 'size' => 11, 'x' => 95, 'y' => 91];
    $page[] = ['text' => 'VERIFICATION CODE', 'size' => 8, 'x' => 300, 'y' => 112, 'bold' => true];
    $page[] = ['text' => (string) $data['verification_code'], 'size' => 11, 'x' => 300, 'y' => 91];
    $page[] = ['type' => 'line', 'x1' => 625, 'x2' => 755, 'y' => 91, 'width' => 1, 'color' => $green];
    $page[] = ['text' => 'AUTHORIZED OFFICER', 'size' => 9, 'x' => 646, 'y' => 74, 'bold' => true];

    pdf_output([['items' => $page, 'width' => $pageWidth, 'height' => $pageHeight]], $filename);
}

function pdf_transcript_qr(string $token,int $x,int $y,int $size): array {
    $qr=(new QRcode($token,'M'))->getBarcodeArray();$n=(int)($qr['num_rows']??0);if($n<1){return [];}
    $cell=$size/$n;$items=[];$matrix=$qr['bcode']??[];
    for($r=0;$r<$n;$r++){for($c=0;$c<$n;$c++){if(!empty($matrix[$r][$c])){$items[]=['type'=>'rect','x'=>$x+$c*$cell,'y'=>$y+($n-1-$r)*$cell,'width'=>$cell+.2,'height'=>$cell+.2,'color'=>[0,0,0]];}}}
    return $items;
}

function clearance_transcript_pages(array $data): array
{
    $snapshot = $data['snapshot'] ?? [];
    if (($snapshot['document_type'] ?? '') !== 'CLEARANCE_TRANSCRIPT'
        || count($snapshot['approvals'] ?? []) !== 11) {
        throw new RuntimeException('A complete clearance transcript snapshot is required.');
    }
    $student = $snapshot['student'];
    $approvals = $snapshot['approvals'];
    $pale = [0.95, 0.975, 0.955];
    $soft = [0.91, 0.94, 0.92];
    $verificationUrl = $data['verification_url'] ?? url('transcripts/verify.php?token=' . $data['verification_token']);

    // Wrap office and officer names, then paginate by actual row height.
    $chunks = [];
    $chunk = [];
    $available = 318;
    foreach ($approvals as $approval) {
        $approval['office_lines'] = pdf_wrapped_lines((string)$approval['office'], 36);
        $approval['officer_lines'] = pdf_wrapped_lines((string)$approval['officer_name'], 26);
        $approval['height'] = max(24, 12 * max(count($approval['office_lines']), count($approval['officer_lines'])) + 12);
        if ($chunk && $approval['height'] > $available) {
            $chunks[] = $chunk;
            $chunk = [];
            $available = 555;
        }
        $chunk[] = $approval;
        $available -= $approval['height'];
    }
    $chunks[] = $chunk;
    $pages = [];
    foreach ($chunks as $pageIndex => $chunk) {
        $items = [
            ['type' => 'rect', 'x' => 0, 'y' => 790, 'width' => 595, 'height' => 52, 'color' => [0.03, 0.36, 0.22]],
            ['type' => 'rect', 'x' => 0, 'y' => 787, 'width' => 595, 'height' => 3, 'color' => [0.72, 0.53, 0.08]],
            ['text' => 'INSTITUTE OF RURAL DEVELOPMENT PLANNING', 'x' => 58, 'y' => 817, 'size' => 12, 'bold' => true],
            ['text' => 'CLEARANCE TRANSCRIPT', 'x' => 58, 'y' => 801, 'size' => 10, 'bold' => true],
        ];
        $logoPath = __DIR__ . '/../assets/img/irdp-logo.jpg';
        if (is_file($logoPath)) {
            $items[] = ['type' => 'image', 'path' => $logoPath, 'x' => 14, 'y' => 797, 'width' => 36, 'height' => 36];
        }
        if ($pageIndex === 0) {
            $items[] = ['type' => 'rect', 'x' => 38, 'y' => 631, 'width' => 519, 'height' => 122, 'color' => $pale];
            $items[] = ['text' => 'STUDENT INFORMATION', 'x' => 44, 'y' => 736, 'size' => 11, 'bold' => true];
            $items[] = ['text' => 'Full Name: ' . $student['name'], 'x' => 44, 'y' => 718, 'size' => 9];
            $items[] = ['text' => 'Registration No.: ' . $student['registration_number'], 'x' => 44, 'y' => 702, 'size' => 9];
            $items[] = ['text' => 'Programme: ' . $student['programme'], 'x' => 44, 'y' => 686, 'size' => 9];
            foreach (pdf_wrapped_lines('Department: ' . $student['department'], 95) as $index => $line) {
                $items[] = ['text' => $line, 'x' => 44, 'y' => 670 - $index * 12, 'size' => 9];
            }
            $items[] = ['text' => 'Academic Cycle: ' . $snapshot['cycle'], 'x' => 44, 'y' => 641, 'size' => 9];
            $items[] = ['text' => 'CLEARANCE STATUS: COMPLETED', 'x' => 42, 'y' => 609, 'size' => 10, 'bold' => true];
            $items[] = ['text' => 'Started: ' . $snapshot['clearance']['started_at'], 'x' => 42, 'y' => 592, 'size' => 9];
            $items[] = ['text' => 'Completed: ' . $snapshot['clearance']['completed_at'], 'x' => 285, 'y' => 592, 'size' => 9];
            $items[] = ['text' => 'All 11 required offices approved this clearance.', 'x' => 42, 'y' => 575, 'size' => 9];
            $y = 548;
        } else {
            $items[] = ['text' => 'Registration No.: ' . $student['registration_number'], 'x' => 42, 'y' => 766, 'size' => 9];
            $y = 755;
        }
        $items[] = ['text' => 'OFFICE APPROVALS', 'x' => 42, 'y' => $y, 'size' => 11, 'bold' => true];
        $items[] = ['type' => 'rect', 'x' => 38, 'y' => $y - 34, 'width' => 519, 'height' => 22, 'color' => $soft];
        $columns = [42, 66, 255, 395, 486];
        foreach (['No.', 'Office', 'Approved by', 'Approval date', 'Status'] as $index => $label) {
            $items[] = ['text' => $label, 'x' => $columns[$index], 'y' => $y - 27, 'size' => 8, 'bold' => true];
        }
        $y -= 50;
        foreach ($chunk as $approval) {
            $items[] = ['text' => (string)$approval['step_number'], 'x' => 42, 'y' => $y, 'size' => 8];
            foreach ($approval['office_lines'] as $index => $line) {
                $items[] = ['text' => $line, 'x' => 66, 'y' => $y - $index * 12, 'size' => 8];
            }
            foreach ($approval['officer_lines'] as $index => $line) {
                $items[] = ['text' => $line, 'x' => 255, 'y' => $y - $index * 12, 'size' => 8];
            }
            $items[] = ['text' => substr($approval['approved_at'], 0, 10), 'x' => 395, 'y' => $y, 'size' => 8];
            $items[] = ['text' => $approval['status'], 'x' => 486, 'y' => $y, 'size' => 8, 'bold' => true];
            $y -= $approval['height'];
            $items[] = ['type' => 'line', 'x1' => 38, 'x2' => 557, 'y' => $y + 12, 'width' => 0.35, 'color' => $soft];
        }
        $items[] = ['text' => 'Document No.: ' . $data['document_number'], 'x' => 42, 'y' => 141, 'size' => 9, 'bold' => true];
        $items[] = ['text' => 'Issued: ' . $data['generated_at'], 'x' => 42, 'y' => 125, 'size' => 8];
        if ($pageIndex === count($chunks) - 1) {
            $items[] = ['text' => 'Scan the QR code to verify this clearance transcript.', 'x' => 42, 'y' => 94, 'size' => 8];
            $items = array_merge($items, pdf_transcript_qr($verificationUrl, 465, 36, 75));
            $items[] = ['text' => 'Scan to verify', 'x' => 468, 'y' => 25, 'size' => 7, 'bold' => true];
        } else {
            $items[] = ['text' => 'Office approvals continued on next page.', 'x' => 42, 'y' => 94, 'size' => 8];
        }
        $items[] = ['text' => 'Page ' . ($pageIndex + 1) . ' of ' . count($chunks), 'x' => 42, 'y' => 25, 'size' => 8];
        $pages[] = ['items' => $items, 'width' => 595, 'height' => 842];
    }
    return $pages;
}

function pdf_transcript(array $data, string $filename): never
{
    pdf_output(clearance_transcript_pages($data), $filename);
}
