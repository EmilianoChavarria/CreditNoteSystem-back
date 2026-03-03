<?php

namespace App\Services\Batches\Parsers;

use RuntimeException;
use SimpleXMLElement;
use Storage;

class BulkFileParser
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function parseByStoredFile(string $storedPath, string $extension): array
    {
        $absolutePath = Storage::disk('local')->path($storedPath);
        $extension = strtolower($extension);

        return match ($extension) {
            'csv', 'txt' => $this->parseDelimited($absolutePath),
            'xml' => $this->parseXml($absolutePath),
            'xlsx', 'xls' => $this->parseSpreadsheet($absolutePath),
            default => throw new RuntimeException("Formato .$extension no soportado"),
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseDelimited(string $absolutePath): array
    {
        $handle = fopen($absolutePath, 'rb');
        if (!$handle) {
            throw new RuntimeException('No se pudo leer el archivo CSV/TXT.');
        }

        $rows = [];
        $headers = null;
        $rowNumber = 1;

        while (($line = fgetcsv($handle, 0, ',')) !== false) {
            if ($headers === null) {
                $headers = array_map(fn ($header) => $this->normalizeHeader((string) $header), $line);
                $rowNumber++;
                continue;
            }

            $normalized = [];
            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }

                $normalized[$header] = isset($line[$index]) ? trim((string) $line[$index]) : null;
            }

            if ($this->isEmptyRow($normalized)) {
                $rowNumber++;
                continue;
            }

            $normalized['_rowNumber'] = $rowNumber;
            $rows[] = $normalized;
            $rowNumber++;
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseXml(string $absolutePath): array
    {
        $xml = simplexml_load_file($absolutePath);
        if (!$xml instanceof SimpleXMLElement) {
            throw new RuntimeException('No se pudo parsear el XML.');
        }

        $rows = [];
        $rowNumber = 1;

        foreach ($xml->children() as $child) {
            $row = [];
            foreach ($child->children() as $field => $value) {
                $row[$this->normalizeHeader((string) $field)] = trim((string) $value);
            }

            if ($this->isEmptyRow($row)) {
                $rowNumber++;
                continue;
            }

            $row['_rowNumber'] = $rowNumber;
            $rows[] = $row;
            $rowNumber++;
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseSpreadsheet(string $absolutePath): array
    {
        if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
            throw new RuntimeException('Para archivos xls/xlsx debes instalar phpoffice/phpspreadsheet.');
        }

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($absolutePath);
        $sheet = $spreadsheet->getActiveSheet();
        $matrix = $sheet->toArray(null, true, true, true);

        $rows = [];
        $headers = null;
        $rowNumber = 1;

        foreach ($matrix as $row) {
            $values = array_values($row);

            if ($headers === null) {
                $headers = array_map(fn ($header) => $this->normalizeHeader((string) $header), $values);
                $rowNumber++;
                continue;
            }

            $normalized = [];
            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }

                $value = $values[$index] ?? null;
                $normalized[$header] = is_string($value) ? trim($value) : $value;
            }

            if ($this->isEmptyRow($normalized)) {
                $rowNumber++;
                continue;
            }

            $normalized['_rowNumber'] = $rowNumber;
            $rows[] = $normalized;
            $rowNumber++;
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && $value !== '') {
                return false;
            }
        }

        return true;
    }

    private function normalizeHeader(string $header): string
    {
        $header = trim($header);
        if ($header === '') {
            return '';
        }

        $header = mb_strtolower($header);
        $header = preg_replace('/[^a-z0-9]+/i', '_', $header) ?? $header;

        return trim($header, '_');
    }
}
