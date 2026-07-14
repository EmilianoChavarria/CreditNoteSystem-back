<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ForecastInvoicesExport implements
    FromCollection,
    WithHeadings,
    WithMapping,
    WithStyles,
    WithColumnWidths,
    WithTitle
{
    public function __construct(
        private readonly Collection $invoices,
        private readonly string $clientName,
        private readonly int $month,
        private readonly int $year,
        private readonly ?string $sheetTitle = null,
    ) {}

    public function collection(): Collection
    {
        return $this->invoices;
    }

    public function title(): string
    {
        return $this->sheetTitle ?? "Facturas {$this->monthName()} {$this->year}";
    }

    public function headings(): array
    {
        return [
            'Folio',
            'Fecha Emisión',
            'SubTotal (USD)',
            'IVA (USD)',
            'Total (USD)',
            'SubTotal Original',
            'IVA Original',
            'Total Original',
            'Moneda Original',
            'Tipo de Cambio',
        ];
    }

    public function map($invoice): array
    {
        $converted = isset($invoice->originalMoneda);

        return [
            $invoice->folio,
            $invoice->fechaEmision instanceof \Carbon\Carbon
                ? $invoice->fechaEmision->format('d/m/Y H:i:s')
                : $invoice->fechaEmision,
            $invoice->subTotal,
            $invoice->iva,
            $invoice->total,
            $converted ? $invoice->originalSubTotal : '',
            $converted ? $invoice->originalIva      : '',
            $converted ? $invoice->originalTotal     : '',
            $converted ? $invoice->originalMoneda    : '',
            $converted ? $invoice->tipoCambio        : '',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 14,  // Folio
            'B' => 22,  // Fecha
            'C' => 16,  // SubTotal USD
            'D' => 14,  // IVA USD
            'E' => 16,  // Total USD
            'F' => 18,  // SubTotal original
            'G' => 14,  // IVA original
            'H' => 16,  // Total original
            'I' => 14,  // Moneda original
            'J' => 14,  // Tipo de cambio
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $this->invoices->count() + 1;

        // Header row
        $sheet->getStyle('A1:J1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F3864']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Right-align numeric columns C–J
        $sheet->getStyle("C2:J{$lastRow}")->applyFromArray([
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
        ]);

        // Number format for money columns
        foreach (['C', 'D', 'E', 'F', 'G', 'H'] as $col) {
            $sheet->getStyle("{$col}2:{$col}{$lastRow}")
                ->getNumberFormat()
                ->setFormatCode('#,##0.00');
        }

        // Highlight converted rows (orange tint when original columns populated)
        foreach ($this->invoices as $i => $invoice) {
            if (isset($invoice->originalMoneda)) {
                $row = $i + 2;
                $sheet->getStyle("A{$row}:J{$row}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF3CD']],
                ]);
            }
        }

        return [];
    }

    private function monthName(): string
    {
        $months = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];

        return $months[$this->month] ?? $this->month;
    }
}
