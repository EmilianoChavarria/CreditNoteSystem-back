<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"/>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: Arial, sans-serif; font-size: 7.5pt; color: #000; }
table { width: 100%; border-collapse: collapse; }
td, th { border: 1px solid #000; padding: 2px 4px; vertical-align: middle; }
.no-border td, .no-border th { border: none; }
.bold { font-weight: bold; }
.center { text-align: center; }
.right { text-align: right; }
.small { font-size: 6pt; }
.xsmall { font-size: 5.5pt; }
.large { font-size: 12pt; }
.xlarge { font-size: 16pt; font-weight: bold; }
.wrap { word-break: break-all; }
.bg-light { background-color: #f0f0f0; }
.section-header { font-weight: bold; font-size: 7pt; background: #f0f0f0; }
.bilingual { font-size: 6.5pt; }
.seal-block { word-break: break-all; font-size: 5.5pt; }
</style>
</head>
<body>

{{-- ===== ROW 1: HEADER ===== --}}
<table style="margin-bottom:0;">
  <tr>
    <td style="width:70%; border:1px solid #000; vertical-align:top; padding:4px 6px;">
      <div class="bold" style="font-size:8pt;">FACTURA / INVOICE</div>
      <div class="bold" style="font-size:9pt; margin-top:2px;">TIMKEN DE MEXICO</div>
      @php
        $ea = $xml['emisorAddr'];
        $emisorLine = trim(
          ($ea['calle']  ?? '') . ' ' .
          ($ea['noExterior'] ?? '') . ($ea['noInterior'] ? ' - ' . $ea['noInterior'] : '') . ', ' .
          ($ea['colonia'] ?? '') . ', ' .
          ($ea['municipio'] ?? '') . ', ' .
          ($ea['estado'] ?? '') . ', ' .
          ($ea['pais'] ?? '') . ', ' .
          'C.P. ' . ($ea['codigoPostal'] ?? '')
        );
      @endphp
      <div class="small">{{ $emisorLine }}</div>
      <div class="small">R.F.C. {{ $xml['emisor']['Rfc'] ?? '' }} &nbsp; Teléfono (55) 5061-4898</div>
      <div class="small">Lugar de Expedición: C.P.{{ $xml['lugarExpedicion'] }}</div>
      <div class="small">Régimen Fiscal: {{ $xml['emisor']['RegimenFiscal'] ?? '' }} - General de Ley Personas Morales</div>
      <div class="small">Uso de CFDI: {{ $xml['receptor']['UsoCFDI'] ?? '' }} - Adquisición de mercancias</div>
    </td>
    <td style="width:15%; border:1px solid #000; vertical-align:top; padding:4px;">
      <div class="bold small center">FECHA / DATE</div>
      <div class="small center" style="margin-top:2px;">
        @php
          $fecha = $xml['fecha'] ?? '';
          try { $fecha = \Carbon\Carbon::parse($fecha)->format('d/m/Y T H:i:s'); } catch(\Exception $e){}
        @endphp
        {{ $fecha }}
      </div>
      <div class="bold small center" style="margin-top:4px;">FACTURA / INVOICE</div>
      <div class="bold large center">{{ $xml['folio'] }}</div>
      <div class="small center">Folio Fiscal: {{ $xml['tfd']['UUID'] ?? '' }}</div>
    </td>
    <td style="width:15%; border:1px solid #000; vertical-align:middle; text-align:right; padding:6px;">
      <div class="xlarge" style="letter-spacing:-1px;">TIMKEN</div>
    </td>
  </tr>
</table>

{{-- ===== ROW 2: LAB / CONDICIONES / FACTOR / VIA / CLIENTE / ORD ===== --}}
@php $add = $xml['addenda']; @endphp
<table style="margin-top:-1px;">
  <tr>
    <th class="section-header center" style="width:10%;">LAB</th>
    <th class="section-header center" style="width:25%;">CONDICIONES / TERMS</th>
    <th class="section-header center" style="width:10%;">FACTOR</th>
    <th class="section-header center" style="width:18%;">VIA</th>
    <th class="section-header center" style="width:12%;">CLIENTE / CUST</th>
    <th class="section-header center" style="width:25%;">ORD DE VTA / ORD ENTRY</th>
  </tr>
  <tr>
    <td class="center">{{ $add['lab'] ?? '' }}</td>
    <td class="center">{{ $add['condiciones'] ?? $xml['condiciones'] ?? '' }}</td>
    <td class="center">{{ $add['factor'] ?? $xml['tipoCambio'] ?? '' }}</td>
    <td class="center">{{ $add['via'] ?? '' }}</td>
    <td class="center">{{ $add['cliente'] ?? '' }}</td>
    <td class="center">{{ $add['orden'] ?? '' }}</td>
  </tr>
</table>

{{-- ===== ROW 3: FACTURADO A / EMBARCADO A ===== --}}
@php
  $ra  = $xml['receptorAddr'];
  $ea2 = $xml['entregaAddr'];
  $rec = $xml['receptor'];

  $receptorAddrLine = implode(', ', array_filter([
    $ra['calle']     ?? null,
    $ra['colonia']   ?? null,
    $ra['municipio'] ?? null,
    ($ra['estado'] ?? null) ? $ra['estado'] . ', México' : null,
    ($ra['codigoPostal'] ?? null) ? 'C.P. ' . $ra['codigoPostal'] : null,
  ]));

  $entregaAddrLine = implode(', ', array_filter([
    $ea2['calle']     ?? null,
    $ea2['colonia']   ?? null,
    $ea2['municipio'] ?? null,
    ($ea2['estado'] ?? null) ? $ea2['estado'] . ', México' : null,
    ($ea2['cp'] ?? null) ? 'C.P. ' . $ea2['cp'] : null,
  ]));
@endphp
<table style="margin-top:-1px;">
  <tr>
    <td style="width:50%; vertical-align:top; padding:4px;">
      <div class="bold small">FACTURADO A / SOLD TO:</div>
      <div class="bold" style="margin-top:2px;">{{ $rec['Nombre'] ?? '' }}</div>
      <div class="small">{{ $receptorAddrLine }}</div>
      <div class="small">Régimen Fiscal del Receptor: {{ $rec['RegimenFiscalReceptor'] ?? '' }} - General de Ley Personas Morales</div>
      <div class="small">{{ $rec['Rfc'] ?? '' }}</div>
    </td>
    <td style="width:50%; vertical-align:top; padding:4px;">
      <div class="bold small">EMBARCADO A / SHIP TO:</div>
      <div class="bold" style="margin-top:2px;">{{ $ea2['nombre'] ?? $rec['Nombre'] ?? '' }}</div>
      <div class="small">{{ $entregaAddrLine }}</div>
    </td>
  </tr>
</table>

{{-- ===== PAGE INDICATOR ===== --}}
<table style="margin-top:-1px;">
  <tr>
    <td class="right small bold" style="border:1px solid #000; padding:2px 4px;">Página 1 de 1</td>
  </tr>
</table>

{{-- ===== PRODUCT TABLE HEADER ===== --}}
<table style="margin-top:-1px;">
  <tr>
    <th class="section-header center bilingual" style="width:9%;">No.PEDIDO<br/>CUST PO NBR</th>
    <th class="section-header center bilingual" style="width:8%;">REMISION<br/>DELIVERY NOTE</th>
    <th class="section-header center bilingual" style="width:5%;">CANT ORD<br/>QTY ORD</th>
    <th class="section-header center bilingual" style="width:5%;">CANT EMB<br/>QTY SHIP</th>
    <th class="section-header center bilingual" style="width:5%;">CANT BO<br/>QTY BO</th>
    <th class="section-header center bilingual" style="width:18%;">No. PARTE<br/>PART NUMBER</th>
    <th class="section-header center bilingual" style="width:10%;">PARTE CLIENTE<br/>CUST PART</th>
    <th class="section-header center bilingual" style="width:8%;">CLAVE SAT<br/>SAT CODE</th>
    <th class="section-header center bilingual" style="width:7%;">UNIDAD<br/>UNIT</th>
    <th class="section-header center bilingual" style="width:5%;">ORIG.<br/>ORIG.</th>
    <th class="section-header center bilingual" style="width:9%;">PRECIO UNITARIO<br/>UNIT PRICE</th>
    <th class="section-header center bilingual" style="width:11%;">TOTAL NETO<br/>EXT PRICE</th>
  </tr>

  {{-- Product rows --}}
  @foreach ($xml['conceptos'] as $concepto)
  <tr>
    <td class="center">{{ $concepto['noPedido'] }}</td>
    <td class="center">{{ $concepto['remision'] }}</td>
    <td class="center">{{ number_format($concepto['cantidad'], 0) }}</td>
    <td class="center">{{ number_format($concepto['cantidad'], 0) }}</td>
    <td class="center">0</td>
    <td>{{ $concepto['partNumber'] }}{{ $concepto['description'] ? ';' . $concepto['description'] : '' }}</td>
    <td class="center">{{ $concepto['custPart'] }}</td>
    <td class="center">{{ $concepto['claveProdServ'] }}</td>
    <td class="center">{{ $concepto['claveUnidad'] }}/{{ $concepto['unidad'] }}</td>
    <td class="center">{{ $concepto['orig'] }}</td>
    <td class="right">{{ number_format($concepto['valorUnitario'], 2) }}</td>
    <td class="right">{{ number_format($concepto['importe'], 2) }}</td>
  </tr>

  {{-- Tax info row --}}
  @if (!empty($concepto['traslados']))
  <tr>
    <td colspan="12" style="border-top:none; padding:1px 4px;">
      <span class="small">Objeto Impuesto: {{ $concepto['claveProdServ'] ? '02 - Sí objeto de impuesto.' : '' }}</span>
      @foreach ($concepto['traslados'] as $t)
      <span class="small">&nbsp;Impuestos Trasladados: Base ${{ number_format((float)($t['Base']??0),2) }}, Impuesto: {{ $t['Impuesto']??'' }}(IVA), TipoFactor: {{ $t['TipoFactor']??'' }}, TasaOCuota: {{ $t['TasaOCuota']??'' }}, Importe: {{ number_format((float)($t['Importe']??0),2) }}</span>
      @endforeach
      @if (!empty($concepto['pedimentos']))
      <br/><span class="small">Pedimento(s): {{ implode(', ', $concepto['pedimentos']) }}</span>
      @endif
    </td>
  </tr>
  @endif

  {{-- Summary subtotal row per concepto --}}
  <tr>
    <td colspan="4" class="center">
      {{ number_format($concepto['cantidad'],0) }} &nbsp;&nbsp; {{ number_format($concepto['cantidad'],0) }} &nbsp;&nbsp; 0
    </td>
    <td colspan="7" class="right bold small">VALOR DEL PRODUCTO:</td>
    <td class="right bold">{{ number_format($concepto['importe'], 2) }}</td>
  </tr>
  @endforeach

  {{-- Totals --}}
  <tr>
    <td colspan="11" class="right bold">SUBTOTAL</td>
    <td class="right bold">{{ number_format($xml['subTotal'], 2) }}</td>
  </tr>
  <tr>
    <td colspan="11" class="right bold">IVA 16.00%</td>
    <td class="right bold">{{ number_format($xml['totalIva'], 2) }}</td>
  </tr>
  @if (!empty($xml['addenda']['descuento']) && (float)$xml['addenda']['descuento'] > 0)
  <tr>
    <td colspan="11" class="right bold">DESCUENTO</td>
    <td class="right bold">{{ number_format((float)$xml['addenda']['descuento'], 2) }}</td>
  </tr>
  @endif
  <tr>
    <td colspan="11" class="right bold">TOTAL {{ strtoupper($xml['moneda']) }}</td>
    <td class="right bold">{{ number_format($xml['total'], 2) }}</td>
  </tr>

  {{-- Amount in words --}}
  @if (!empty($add['importeConLetra']))
  <tr>
    <td colspan="12" class="right bold small">
      {{ strtoupper($add['importeConLetra']) }}
    </td>
  </tr>
  @endif
</table>

{{-- ===== PAYMENT DISCOUNT MESSAGES ===== --}}
@if (!empty($obs3Parts))
<table style="margin-top:-1px;">
  @foreach ($obs3Parts as $msg)
  <tr>
    <td class="center small">{{ $msg }}</td>
  </tr>
  @endforeach
</table>
@endif

{{-- ===== FORMA DE PAGO + LEGAL NOTES ===== --}}
<table style="margin-top:-1px;">
  <tr>
    <td style="width:40%; vertical-align:top; padding:3px 4px;">
      <div class="small bold">Forma de Pago:</div>
      <div class="small" style="margin-top:4px;">{{ $xml['formaPago'] ?? '' }} - Por definir</div>
    </td>
    <td style="width:60%; vertical-align:top; padding:3px 4px;">
      <div class="small">MX ES PRODUCTO MEXICANO, LOS DEMAS SON PRODUCTOS DE IMPORTACION.</div>
      <div class="small">MX IS MEXICAN PRODUCT, OTHERS ARE IMPORTED PRODUCTS.</div>
      <div class="small" style="margin-top:2px;">EL CLIENTE CUENTA CON 10 DIAS HABILES PARA INFORMAR A TIMKEN DE MEXICO SOBRE CUALQUIER ANOMALIA CON LA MERCANCIA DE ESTA FACTURA, DE OTRA MANERA ESTA DEBE SER LIQUIDADA EN SU TOTALIDAD</div>
    </td>
  </tr>
</table>

{{-- ===== LEGAL DECLARATION ===== --}}
<table style="margin-top:-1px;">
  <tr>
    <td class="small" style="padding:3px 4px;">
      POR ESTE PAGARE ME (NOS) OBLIGO(AMOS) A PAGAR INCONDICIONALMENTE A LA ORDEN DE TIMKEN MEXICO, S.A. DE C.V., A LA VISTA EL IMPORTE DE ESTA FACTURA
      <span class="right" style="float:right; font-weight:bold;">ACEPTO</span>
    </td>
  </tr>
</table>

{{-- ===== CERTIFICATE INFO ROW ===== --}}
@php
    $tfd         = $xml['tfd'];
    $certEmisor  = nl2br(htmlspecialchars(chunk_split($xml['noCertificado'],           20, "\n"), ENT_QUOTES, 'UTF-8'));
    $certSat     = nl2br(htmlspecialchars(chunk_split($tfd['NoCertificadoSAT'] ?? '', 20, "\n"), ENT_QUOTES, 'UTF-8'));
@endphp
<table style="margin-top:-1px; table-layout:fixed; width:100%;">
  <tr>
    <td style="width:35%; padding:2px 4px;">
      <div class="small bold">NO. CERTIFICADO DEL EMISOR:</div>
      <div class="xsmall">{!! $certEmisor !!}</div>
    </td>
    <td style="width:35%; padding:2px 4px;">
      <div class="small bold">NO. CERTIFICADO DEL SAT:</div>
      <div class="xsmall">{!! $certSat !!}</div>
    </td>
    <td style="width:30%; padding:2px 4px;">
      <div class="small bold">FECHA Y HORA DE CERTIFICACIÓN:</div>
      <div class="xsmall">{{ $tfd['FechaTimbrado'] ?? '' }}</div>
    </td>
  </tr>
</table>

{{-- ===== SEALS ===== --}}
@php
    $chunkLen     = 240;
    $selloEmisor  = nl2br(htmlspecialchars(chunk_split($xml['sello'],            $chunkLen, "\n"), ENT_QUOTES, 'UTF-8'));
    $selloSat     = nl2br(htmlspecialchars(chunk_split($tfd['SelloSAT'] ?? '',   $chunkLen, "\n"), ENT_QUOTES, 'UTF-8'));
    $cadenaChunk  = nl2br(htmlspecialchars(chunk_split($cadenaOriginal,          $chunkLen, "\n"), ENT_QUOTES, 'UTF-8'));
@endphp
<table style="margin-top:-1px; table-layout:fixed; width:100%;">
  <tr>
    <td style="padding:2px 4px; width:100%; overflow:hidden;">
      <div class="small bold">Sello Digital del Emisor:</div>
      <div class="seal-block">{!! $selloEmisor !!}</div>
    </td>
  </tr>
  <tr>
    <td style="padding:2px 4px; width:100%; overflow:hidden;">
      <div class="small bold">Sello Digital del SAT:</div>
      <div class="seal-block">{!! $selloSat !!}</div>
    </td>
  </tr>
  <tr>
    <td style="padding:2px 4px; width:100%; overflow:hidden;">
      <div class="small bold">Cadena Original del Complemento de Certificación Digital del SAT:</div>
      <div class="seal-block">{!! $cadenaChunk !!}</div>
    </td>
  </tr>
</table>

{{-- ===== FOOTER META ===== --}}
<table style="margin-top:-1px;">
  <tr>
    <td class="xsmall" style="padding:2px 4px;">
      Moneda: <strong>{{ $xml['moneda'] }}</strong>
      &nbsp; Tipo de Cambio: <strong>{{ $xml['tipoCambio'] }}</strong>
      &nbsp; Método de pago: <strong>{{ $xml['metodoPago'] }}</strong> - Pago en parcialidades o diferido
      &nbsp; Este documento es una representación impresa de un CFDI. Versión 4.0.
      &nbsp; Efectos fiscales al pago.
      &nbsp; Tipo de Comprobante: I - Ingreso
      &nbsp; Exportación: {{ $xml['addenda']['cfdiTipo'] ?? '01' }} - No aplica
    </td>
  </tr>
</table>

</body>
</html>
