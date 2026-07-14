<?php

namespace App\Exports\Sheets;

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

/** Todas las facturas del grupo, de todos los clientes, en una sola hoja. */
class ForecastGroupAllInvoicesSheet implements
    FromCollection,
    WithHeadings,
    WithMapping,
    WithStyles,
    WithColumnWidths,
    WithTitle
{
    public function __construct(
        private readonly array $sections,
        private readonly int $month,
        private readonly int $year,
    ) {}

    public function collection(): Collection
    {
        return collect($this->sections)->flatMap(
            fn($section) => $section['invoices']->map(function ($invoice) use ($section) {
                $invoice->clienteNombre = $section['razonSocial'];
                $invoice->clienteId     = $section['clientId'];
                return $invoice;
            })
        )->values();
    }

    public function title(): string
    {
        return 'Todas las facturas';
    }

    public function headings(): array
    {
        return [
            'Cliente',
            'ID Cliente',
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
            $invoice->clienteNombre,
            $invoice->clienteId,
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
            'A' => 30,  // Cliente
            'B' => 12,  // ID Cliente
            'C' => 14,  // Folio
            'D' => 22,  // Fecha
            'E' => 16,  // SubTotal USD
            'F' => 14,  // IVA USD
            'G' => 16,  // Total USD
            'H' => 18,  // SubTotal original
            'I' => 14,  // IVA original
            'J' => 16,  // Total original
            'K' => 14,  // Moneda original
            'L' => 14,  // Tipo de cambio
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $invoices = $this->collection();
        $lastRow  = $invoices->count() + 1;

        $sheet->getStyle('A1:L1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F3864']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->getStyle("B2:L{$lastRow}")->applyFromArray([
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
        ]);

        foreach (['E', 'F', 'G', 'H', 'I', 'J'] as $col) {
            $sheet->getStyle("{$col}2:{$col}{$lastRow}")
                ->getNumberFormat()
                ->setFormatCode('#,##0.00');
        }

        foreach ($invoices as $i => $invoice) {
            if (isset($invoice->originalMoneda)) {
                $row = $i + 2;
                $sheet->getStyle("A{$row}:L{$row}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF3CD']],
                ]);
            }
        }

        return [];
    }
}
