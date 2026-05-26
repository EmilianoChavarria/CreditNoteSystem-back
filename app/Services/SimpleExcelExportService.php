<?php

namespace App\Services;

class SimpleExcelExportService
{
    /**
     * @param array<int, string> $headers
     * @param array<int, array<int, mixed>> $rows
     */
    public function build(array $headers, array $rows, string $sheetName = 'Export'): string
    {
        $allRows = array_merge([$headers], $rows);
        $rowXml = [];

        foreach ($allRows as $rowIndex => $row) {
            $cells = [];

            foreach (array_values($row) as $value) {
                $style = $rowIndex === 0 ? ' ss:StyleID="Header"' : '';
                $cells[] = '<Cell' . $style . '><Data ss:Type="String">' . $this->xml($this->stringValue($value)) . '</Data></Cell>';
            }

            $rowXml[] = '<Row>' . implode('', $cells) . '</Row>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<?mso-application progid="Excel.Sheet"?>'
            . '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" '
            . 'xmlns:o="urn:schemas-microsoft-com:office:office" '
            . 'xmlns:x="urn:schemas-microsoft-com:office:excel" '
            . 'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" '
            . 'xmlns:html="http://www.w3.org/TR/REC-html40">'
            . '<Styles>'
            . '<Style ss:ID="Header"><Font ss:Bold="1"/><Interior ss:Color="#D9EAF7" ss:Pattern="Solid"/></Style>'
            . '</Styles>'
            . '<Worksheet ss:Name="' . $this->xml(mb_substr($sheetName, 0, 31)) . '">'
            . '<Table>' . implode('', $rowXml) . '</Table>'
            . '</Worksheet>'
            . '</Workbook>';
    }

    private function stringValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'Si' : 'No';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        return (string) $value;
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
