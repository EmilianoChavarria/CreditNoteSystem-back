<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"/>
<style>
* { margin: 0; box-sizing: border-box; }
body { font-family: Arial, sans-serif; font-size: 8pt; color: #000; padding: 50px;}
table { width: 100%; border-collapse: collapse; }
td, th { border: 1px solid #000; padding: 3px 5px; vertical-align: middle; }
.label { font-size: 7pt; color: #333; }
.value { font-size: 8.5pt; font-weight: bold; }
.header-label { font-size: 6.5pt; }
.section-title { font-weight: bold; font-size: 7pt; background: #f0f0f0; }
.no-border td { border: none; }
.bold { font-weight: bold; }
.center { text-align: center; }
.right { text-align: right; }
.small { font-size: 6.5pt; }
.italic-small { font-size: 6.5pt; font-style: italic; }
.logo { font-size: 22pt; font-weight: 900; letter-spacing: -1px; }
</style>
</head>
<body>

@php
    $r = $request;
    $fmt = fn($v) => $v ? number_format((float)$v, 2) : '';
    $fmtDate = fn($d) => $d ? \Carbon\Carbon::parse($d)->format('d/m/Y') : '';

    $ivaAmount = '';
    if ($r->hasIva && $r->amount) {
        $ivaAmount = number_format((float)$r->amount * 0.16, 2);
    }

@endphp

{{-- HEADER --}}
<table style="margin-bottom:15px;">
  <tr>
    <td style="width:18%; border:none; padding:6px 8px; vertical-align:middle;">
      <div class="logo">TIMKEN</div>
    </td>
    
  </tr>
</table>

<table style="margin-bottom:15px;">
  <tr>
    <td style="width:20%; border:1px solid #000; padding:3px 5px;">
      <div class="header-label">Solicitud de crédito auditor /</div>
      <div class="header-label">Auditor Credit request</div>
      <div class="bold" style="font-size:9pt; margin-top:2px;">{{ $r->requestNumber }}</div>
    </td>
    <td style="width:20%; border:1px solid #000; padding:3px 5px;">
      <div class="header-label">Fecha de solicitud /</div>
      <div class="header-label">Request date</div>
      <div class="bold" style="margin-top:2px;">{{ $fmtDate($r->requestDate) }}</div>
    </td>
    <td style="width:20%; border:1px solid #000; padding:3px 5px;">
      <div class="header-label">Moneda emitida /</div>
      <div class="header-label">Currency</div>
      <div class="bold" style="margin-top:2px;">{{ $r->currency }}</div>
    </td>
    <td style="width:22%; border:1px solid #000; padding:3px 5px;">
      <div class="header-label">Cargo misceláneo /</div>
      <div class="header-label">Miscellaneous charge</div>
      <div class="bold" style="margin-top:2px;">{{ $r->classification?->name ?? '' }}</div>
    </td>
  </tr>
</table>

{{-- INFO ROWS --}}
<table style="margin-bottom:0;">
  <tr>
    <td style="width:40%;" class="label">Nombre del cliente / Customer name</td>
    <td style="width:60%; font-weight:bold;">{{ $customerName }}</td>
  </tr>
  <tr>
    <td class="label">Núm. Cliente / Customer No. (Sold to)</td>
    <td class="bold">{{ $externalClientId }}</td>
  </tr>
  <tr>
    <td class="label">Núm. Cliente / Customer No. (Ship to)</td>
    <td></td>
  </tr>
  <tr>
    <td class="label">Área / Area</td>
    <td class="bold">{{ $r->area }}</td>
  </tr>
  <tr>
    <td class="label">Vendedor / Sales Engineer</td>
    <td class="bold">{{ $localCustomer?->salesEngineer?->fullName ?? '' }}</td>
  </tr>
  <tr>
    <td class="label">Núm. de Factura / Invoice Number</td>
    <td class="bold">{{ $r->invoiceNumber }}</td>
  </tr>
  <tr>
    <td class="label">Fecha de factura / Invoice Date</td>
    <td class="bold">{{ $fmtDate($r->invoiceDate) }}</td>
  </tr>
  <tr>
    <td class="label">Número de remisión / Delivery Note</td>
    <td class="bold">{{ $r->deliveryNote }}</td>
  </tr>
  <tr>
    <td class="label">Solicitado por / Requested by</td>
    <td class="bold">{{ $r->user?->fullName ?? '' }}</td>
  </tr>
  <tr>
    <td class="label">Motivo por el que se solicita / Reason why you are applying</td>
    <td class="bold">{{ $r->reason?->name ?? '' }}</td>
  </tr>
  <tr>
    <td class="label">Auditado por / Audited by</td>
    <td class="bold">{{ $auditor ? strtoupper($auditor['name']) : '' }}</td>
  </tr>
  <tr>
    <td class="label">Gerentes que autorizan / Manager Approval</td>
    <td class="bold">
      @foreach($managers as $m)
        <div>{{ $m['role'] }}: {{ strtoupper($m['name']) }} {{ $m['date'] }}</div>
      @endforeach
    </td>
  </tr>
</table>

{{-- COMMENTS --}}
<table style="margin-top:15px; margin-bottom:15px;">
  <tr>
    <td>
      <div class="label">Comentarios / Comments</div>
      <div style="margin-top:3px; font-size:8pt;">{{ $r->comments }}</div>
    </td>
  </tr>
</table>

{{-- IMPORTANT NOTE --}}
<table style="margin-bottom:15px;">
  <tr>
    <td style="width:55%;" class="italic-small">
      <span class="bold small">Nota importante:</span>
      1.No se aceptará material oxidado o que tenga las cajas rayadas con pluma o plumón.
      2.Etiquetar la caja(s) con el número de RGA.
      3.El material se recibirá dentro de los 30 días a partir de la fecha de la solicitud, después de esta fecha será cancelada.
    </td>
    <td style="width:45%; font-size:8pt;">El costo del flete deberá ser pagado por el cliente</td>
  </tr>
</table>

{{-- FINANCIAL --}}
<table style="margin-bottom:15px;">
  <tr>
    <td style="width:50%; border:none; padding:0; vertical-align:top;">
      <table>
        <tr>
          <td class="label">Número de Orden SAP / SAP Order</td>
          <td class="bold">{{ $r->orderNumber }}</td>
        </tr>
        <tr>
          <td class="label">Importe / Amount</td>
          <td class="bold right">{{ $fmt($r->amount) }}</td>
        </tr>
        <tr>
          <td class="label">I.V.A. 16% / Input Tax</td>
          <td class="bold right">{{ $ivaAmount }}</td>
        </tr>
        <tr>
          <td class="label bold">Total {{ $r->currency }}</td>
          <td class="bold right">{{ $fmt($r->totalAmount) }}</td>
        </tr>
        <tr>
          <td class="label">Tipo de cambio / Exchange rate</td>
          <td class="bold right">{{ $r->exchangeRate }}</td>
        </tr>
      </table>
    </td>
    <td style="width:8px; border:none;"></td>
    <td style="width:50%; border:none; padding:0; vertical-align:top;">
      <table>
        <tr>
          <td class="label">Orden SAP Refacturación / SAP Order Re-invoice</td>
          <td class="bold">{{ $r->sapReturnOrder }}</td>
        </tr>
        <tr>
          <td class="label">Número de remisión / Delivery note</td>
          <td class="bold">{{ $r->deliveryNote }}</td>
        </tr>
        <tr>
          <td class="label">Crédito - Débito / Credit - Debit</td>
          <td class="bold">{{ $r->creditDebitRefId ?? $r->creditNumber }}</td>
        </tr>
        <tr>
          <td class="label">Factura nueva / New invoice</td>
          <td class="bold">{{ $r->newInvoice }}</td>
        </tr>
        <tr>
          <td class="label">Aprobado por Finanzas / Finance Approval</td>
          <td class="bold">{{ $finance ? strtoupper($finance['name']) : '' }}</td>
        </tr>
      </table>
    </td>
  </tr>
</table>

{{-- ITEMS TABLE --}}
<table>
  <thead>
    <tr style="background:#f0f0f0;">
      <th class="small center" style="width:9%;">Pzas solicitadas /<br>Qty to return</th>
      <th class="small center" style="width:6%;">Rec</th>
      <th class="small center" style="width:6%;">Acep</th>
      <th class="small center" style="width:20%;">Parte # / Part #</th>
      <th class="small center" style="width:15%;">SAP ID</th>
      <th class="small center" style="width:14%;">Precio Unit. /<br>Unit Price</th>
      <th class="small center" style="width:14%;">Total</th>
      <th class="small center" style="width:16%;">Motivo de rechazo /<br>Reason for rejection</th>
    </tr>
  </thead>
  <tbody>
    @php $items = $request->returnOrderRequest?->returnOrder?->items ?? collect(); @endphp
    @forelse($items as $item)
    <tr>
      <td class="center">{{ $item->quantity ?? '' }}</td>
      <td></td>
      <td></td>
      <td>{{ $item->partNumber ?? '' }}</td>
      <td>{{ $item->sapId ?? '' }}</td>
      <td class="right">{{ isset($item->unitPrice) ? number_format((float)$item->unitPrice, 2) : '' }}</td>
      <td class="right">{{ isset($item->total) ? number_format((float)$item->total, 2) : '' }}</td>
      <td></td>
    </tr>
    @empty
    <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
    <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
    <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
    @endforelse
  </tbody>
</table>

{{-- FOOTER --}}
<table style="margin-top:6px; border:none;">
  <tr class="no-border">
    <td class="small" style="border:none;">G-105 Corporate Approvals For Debit Credit Memos</td>
    <td class="small right" style="border:none;">Forma F13-05 Rev. 16 &nbsp; Fecha: Diciembre 18, 2023</td>
  </tr>
</table>

</body>
</html>
