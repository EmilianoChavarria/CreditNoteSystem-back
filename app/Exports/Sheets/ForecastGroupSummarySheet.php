<?php

namespace App\Exports\Sheets;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Resumen: ventas sumadas (subtotal, sin IVA) por cliente dentro del grupo. */
class ForecastGroupSummarySheet implements
    FromArray,
    WithHeadings,
    WithStyles,
    WithColumnWidths,
    WithTitle
{
    public function __construct(
        private readonly array $sections,
        private readonly string $groupName,
        private readonly int $month,
        private readonly int $year,
    ) {}

    public function array(): array
    {
        $rows = [];

        foreach ($this->sections as $section) {
            /** @var Collection $invoices */
            $invoices = $section['invoices'];

            $rows[] = [
                $section['clientId'],
                $section['razonSocial'],
                $invoices->count(),
                round((float) $invoices->sum('subTotal'), 2),
                $section['moneda'] ?? '',
            ];
        }

        $monedas = array_values(array_unique(array_filter(array_column($rows, 4))));

        $rows[] = [
            '',
            'TOTAL GRUPO',
            array_sum(array_column($rows, 2)),
            round(array_sum(array_column($rows, 3)), 2),
            // Con monedas mixtas el total del grupo no es sumable: se marca en vez de fingir una moneda.
            count($monedas) === 1 ? $monedas[0] : 'MIXTO',
        ];

        return $rows;
    }

    public function title(): string
    {
        return 'Resumen';
    }

    public function headings(): array
    {
        return ['ID Cliente', 'Cliente', 'No. Facturas', 'Subtotal (sin IVA)', 'Moneda'];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12,
            'B' => 40,
            'C' => 14,
            'D' => 18,
            'E' => 10,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = count($this->sections) + 2; // +1 heading, +1 total row

        $sheet->getStyle('A1:E1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F3864']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->getStyle("C2:D{$lastRow}")->applyFromArray([
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
        ]);

        $sheet->getStyle("D2:D{$lastRow}")
            ->getNumberFormat()
            ->setFormatCode('#,##0.00');

        $sheet->getStyle("A{$lastRow}:E{$lastRow}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E1F2']],
        ]);

        return [];
    }
}
