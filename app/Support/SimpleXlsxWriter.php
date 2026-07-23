<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * Builds a minimal, valid .xlsx (OOXML) file from a single sheet of data,
 * with no external spreadsheet dependency (uses PHP's built-in ZipArchive).
 *
 * Values are written as inline strings or numbers depending on whether they
 * are numeric, so Excel treats numeric columns as numbers. This is deliberately
 * small: one sheet, no styling, enough for simple admin exports.
 */
class SimpleXlsxWriter
{
    /**
     * @param  array<int, string>       $headings  Column headings for the first row.
     * @param  array<int, array<int, mixed>> $rows  Data rows, each an ordered list of cell values.
     * @return string  The binary contents of the .xlsx file.
     */
    public static function build(array $headings, array $rows): string
    {
        $sheetRows = array_merge([$headings], $rows);

        $sheetData = '';
        foreach ($sheetRows as $rowIndex => $cells) {
            $rowNumber = $rowIndex + 1;
            $sheetData .= '<row r="' . $rowNumber . '">';

            foreach (array_values($cells) as $colIndex => $value) {
                $reference = self::columnLetter($colIndex) . $rowNumber;

                if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
                    $sheetData .= '<c r="' . $reference . '"><v>' . $value . '</v></c>';
                } else {
                    $sheetData .= '<c r="' . $reference . '" t="inlineStr"><is><t xml:space="preserve">'
                        . htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1)
                        . '</t></is></c>';
                }
            }

            $sheetData .= '</row>';
        }

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>' . $sheetData . '</sheetData>'
            . '</worksheet>';

        $files = [
            '[Content_Types].xml' =>
                '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                . '<Default Extension="xml" ContentType="application/xml"/>'
                . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                . '</Types>',
            '_rels/.rels' =>
                '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                . '</Relationships>',
            'xl/workbook.xml' =>
                '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                . '<sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets>'
                . '</workbook>',
            'xl/_rels/workbook.xml.rels' =>
                '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                . '</Relationships>',
            'xl/worksheets/sheet1.xml' => $sheetXml,
        ];

        $tempPath = tempnam(sys_get_temp_dir(), 'xlsx');
        if ($tempPath === false) {
            throw new RuntimeException('Unable to create a temporary file for the spreadsheet.');
        }

        $zip = new ZipArchive();
        if ($zip->open($tempPath, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to open the spreadsheet archive for writing.');
        }

        foreach ($files as $name => $contents) {
            $zip->addFromString($name, $contents);
        }
        $zip->close();

        $binary = file_get_contents($tempPath);
        @unlink($tempPath);

        if ($binary === false) {
            throw new RuntimeException('Unable to read the generated spreadsheet.');
        }

        return $binary;
    }

    /**
     * Convert a zero-based column index into its spreadsheet letter (0 => A, 26 => AA).
     */
    private static function columnLetter(int $index): string
    {
        $letter = '';

        for ($i = $index; $i >= 0; $i = intdiv($i, 26) - 1) {
            $letter = chr(65 + ($i % 26)) . $letter;
        }

        return $letter;
    }
}
