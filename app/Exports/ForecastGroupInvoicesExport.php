<?php

namespace App\Exports;

use App\Exports\Sheets\ForecastGroupAllInvoicesSheet;
use App\Exports\Sheets\ForecastGroupSummarySheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ForecastGroupInvoicesExport implements WithMultipleSheets
{
    public function __construct(
        private readonly array $sections,
        private readonly string $groupName,
        private readonly int $month,
        private readonly int $year,
    ) {}

    public function sheets(): array
    {
        $sheets = [
            new ForecastGroupSummarySheet($this->sections, $this->groupName, $this->month, $this->year),
            new ForecastGroupAllInvoicesSheet($this->sections, $this->month, $this->year),
        ];

        $usedTitles = ['Resumen' => true, 'Todas las facturas' => true];

        foreach ($this->sections as $section) {
            $title = $this->uniqueSheetTitle($section['razonSocial'], $usedTitles);

            $sheets[] = new ForecastInvoicesExport(
                $section['invoices'],
                $section['razonSocial'],
                $this->month,
                $this->year,
                $title,
            );
        }

        return $sheets;
    }

    /** Excel sheet names: max 31 chars, no \ / ? * [ ] :, and must be unique in the workbook. */
    private function uniqueSheetTitle(string $rawName, array &$usedTitles): string
    {
        $clean = preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $rawName);
        $clean = trim($clean) ?: 'Cliente';
        $base  = mb_substr($clean, 0, 31);

        $title  = $base;
        $suffix = 2;

        while (isset($usedTitles[$title])) {
            $suffixStr = " ({$suffix})";
            $title     = mb_substr($base, 0, 31 - mb_strlen($suffixStr)) . $suffixStr;
            $suffix++;
        }

        $usedTitles[$title] = true;

        return $title;
    }
}
