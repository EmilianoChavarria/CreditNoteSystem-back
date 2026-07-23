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

/**
 * Una fila por factura + sus líneas de producto anidadas debajo (colapsadas por
 * default, Excel "Agrupar y esquematizar"). El producto excluido por clasificación
 * No Rodamientos se resalta para explicar por qué el total de la factura ya no
 * incluye ese importe.
 */
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
        private readonly Collection $productsByFolio = new Collection(),
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
            'Producto',
            'Descripción',
            'Cantidad',
            'Importe (USD)',
            'Clasificación',
            'Excluido',
        ];
    }

    public function map($invoice): array
    {
        $converted = isset($invoice->originalMoneda);

        $summaryRow = [
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
            '', '', '', '', '', '',
        ];

        $productRows = $this->productsForFolio($invoice->folio)
            ->map(fn($product) => [
                '', '', '', '', '', '', '', '', '', '',
                $product['noIdentificacion'],
                $product['descripcion'],
                (float) $product['cantidad'],
                (float) $product['importeUsd'],
                $product['clasificacion'] ?? 'Sin clasificar',
                $product['excluido'] ? 'Sí' : 'No',
            ])
            ->all();

        return array_merge([$summaryRow], $productRows);
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
            'K' => 22,  // Producto
            'L' => 45,  // Descripción
            'M' => 12,  // Cantidad
            'N' => 16,  // Importe USD
            'O' => 16,  // Clasificación
            'P' => 10,  // Excluido
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $rowMeta = $this->buildRowMeta();
        $lastRow = count($rowMeta) + 1;

        // Header row
        $sheet->getStyle('A1:P1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F3864']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Right-align numeric columns
        $sheet->getStyle("C2:J{$lastRow}")->applyFromArray([
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
        ]);
        $sheet->getStyle("M2:N{$lastRow}")->applyFromArray([
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
        ]);

        // Number format for money columns
        foreach (['C', 'D', 'E', 'F', 'G', 'H', 'N'] as $col) {
            $sheet->getStyle("{$col}2:{$col}{$lastRow}")
                ->getNumberFormat()
                ->setFormatCode('#,##0.00');
        }

        foreach ($rowMeta as $i => $meta) {
            $row = $i + 2;

            if ($meta['type'] === 'summary') {
                if ($meta['converted']) {
                    $sheet->getStyle("A{$row}:J{$row}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF3CD']],
                    ]);
                }
                continue;
            }

            // Línea de producto: colapsada bajo su factura (grupo de esquema de Excel)
            $sheet->getRowDimension($row)->setOutlineLevel(1)->setVisible(false);

            if ($meta['excluido']) {
                $sheet->getStyle("K{$row}:P{$row}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8D7DA']],
                    'font' => ['color' => ['rgb' => '842029']],
                ]);
            } else {
                $sheet->getStyle("K{$row}:P{$row}")->applyFromArray([
                    'font' => ['color' => ['rgb' => '666666']],
                ]);
            }
        }

        // Los controles +/- del grupo aparecen sobre la factura (fila resumen), no debajo
        $sheet->setShowSummaryBelow(false);

        return [];
    }

    /** @return Collection<int, array{noIdentificacion: string, descripcion: string, cantidad: mixed, importeUsd: float, clasificacion: ?string, excluido: bool}> */
    private function productsForFolio(string $folio): Collection
    {
        $products = $this->productsByFolio->get($folio);

        return $products instanceof Collection ? $products : collect($products ?? []);
    }

    /**
     * Replica el orden de filas que produce map(), pero solo con los metadatos
     * que necesita styles() para ubicar cada fila (resumen vs. línea de producto).
     *
     * @return array<int, array{type: string, converted?: bool, excluido?: bool}>
     */
    private function buildRowMeta(): array
    {
        $meta = [];

        foreach ($this->invoices as $invoice) {
            $meta[] = ['type' => 'summary', 'converted' => isset($invoice->originalMoneda)];

            foreach ($this->productsForFolio($invoice->folio) as $product) {
                $meta[] = ['type' => 'product', 'excluido' => (bool) $product['excluido']];
            }
        }

        return $meta;
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
