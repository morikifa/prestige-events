<?php

function export_send(string $filename, string $content, string $contentType): void
{
    header('Content-Type: ' . $contentType);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($content));
    header('Cache-Control: no-store');
    echo $content;
    exit;
}

function export_csv(string $filename, array $header, array $rows): void
{
    $handle = fopen('php://temp', 'r+');
    fwrite($handle, "\xEF\xBB\xBF");
    fputcsv($handle, $header, ';');
    foreach ($rows as $row) {
        fputcsv($handle, array_map(static fn($value) => (string) $value, $row), ';');
    }
    rewind($handle);
    $content = stream_get_contents($handle);
    fclose($handle);
    export_send($filename, $content, 'text/csv; charset=utf-8');
}

function export_column(int $index): string
{
    $letters = '';
    $index++;
    while ($index > 0) {
        $remainder = ($index - 1) % 26;
        $letters = chr(65 + $remainder) . $letters;
        $index = (int) (($index - $remainder) / 26);
    }
    return $letters;
}

function export_xml(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function zip_store(array $files): string
{
    $local = '';
    $central = '';
    $offset = 0;
    $time = getdate();
    $dosTime = ($time['hours'] << 11) | ($time['minutes'] << 5) | (int) ($time['seconds'] / 2);
    $dosDate = (($time['year'] - 1980) << 9) | ($time['mon'] << 5) | $time['mday'];

    foreach ($files as $name => $data) {
        $nameLength = strlen($name);
        $size = strlen($data);
        $crc = crc32($data);

        $header = pack('VvvvvvVVVvv', 0x04034b50, 20, 0x0800, 0, $dosTime, $dosDate, $crc, $size, $size, $nameLength, 0);
        $local .= $header . $name . $data;

        $central .= pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50,
            20,
            20,
            0x0800,
            0,
            $dosTime,
            $dosDate,
            $crc,
            $size,
            $size,
            $nameLength,
            0,
            0,
            0,
            0,
            0,
            $offset
        ) . $name;

        $offset += strlen($header) + $nameLength + $size;
    }

    $end = pack('VvvvvVVv', 0x06054b50, 0, 0, count($files), count($files), strlen($central), strlen($local), 0);

    return $local . $central . $end;
}

function export_xlsx(string $filename, string $sheetName, array $header, array $rows): void
{
    $sheetRows = '';
    $allRows = array_merge([$header], $rows);

    foreach ($allRows as $rowIndex => $row) {
        $number = $rowIndex + 1;
        $cells = '';
        foreach (array_values($row) as $columnIndex => $value) {
            $reference = export_column($columnIndex) . $number;
            $style = $rowIndex === 0 ? ' s="1"' : '';
            if ($rowIndex > 0 && (is_int($value) || is_float($value))) {
                $cells .= '<c r="' . $reference . '"' . $style . '><v>' . $value . '</v></c>';
            } else {
                $cells .= '<c r="' . $reference . '" t="inlineStr"' . $style . '><is><t>'
                    . export_xml((string) $value) . '</t></is></c>';
            }
        }
        $sheetRows .= '<row r="' . $number . '">' . $cells . '</row>';
    }

    $files = [
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>',
        'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . export_xml($sheetName) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>',
        'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>',
        'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            . '<borders count="1"><border/></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>'
            . '</styleSheet>',
        'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>' . $sheetRows . '</sheetData>'
            . '</worksheet>',
    ];

    export_send(
        $filename,
        zip_store($files),
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    );
}
