<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

class FesaWsService
{
    private string $wsdl;
    private string $usuario;
    private string $contrasena;
    private string $tipoComprobante;
    private string $nombreSucursal;

    public function __construct()
    {
        $modo = config('services.fesa.modo', 'PR');

        $this->wsdl = $modo === 'QA'
            ? 'http://plataformafesa.com/services/QA/ws/service.php?wsdl'
            : 'https://plataformafesa.com:443/services/PR/ws/service.php?wsdl';

        $this->usuario         = config('services.fesa.usuario', '');
        $this->contrasena      = config('services.fesa.contrasena', '');
        $this->tipoComprobante = config('services.fesa.tipo_comprobante', 'Factura');
        $this->nombreSucursal  = config('services.fesa.nombre_sucursal', 'MATRIZ');
    }

    /**
     * Llama al WS FESA con el idTransaccion y XML del CFDI.
     * Retorna el response array con 'xml' y 'pdf' en base64.
     */
    public function emitir(string $idTransaccion, string $xmlContent): array
    {
        require_once base_path('vendor/econea/nusoap/src/nusoap.php');

        $client = new \nusoap_client($this->wsdl, 'wsdl', false, false, false, false, 0, 110);

        $xmlB64   = base64_encode($xmlContent);
        $peticion  = '<usuario>'          . $this->usuario         . '</usuario>';
        $peticion .= '<contrasena>'       . $this->contrasena      . '</contrasena>';
        $peticion .= '<idTransaccion>'    . $idTransaccion         . '</idTransaccion>';
        $peticion .= '<tipoComprobante>'  . $this->tipoComprobante . '</tipoComprobante>';
        $peticion .= '<nombreSucursal>'   . $this->nombreSucursal  . '</nombreSucursal>';
        $peticion .= '<receptorId></receptorId>';
        $peticion .= '<peticion>'         . $xmlB64                . '</peticion>';
        $peticion .= '<addenda></addenda>';
        $peticion .= '<adjunto></adjunto>';
        $peticion .= '<correos></correos>';
        $peticion .= '<publicar></publicar>';

        $response = $client->call('emitir', $peticion);

        if ($client->fault) {
            throw new RuntimeException('FESA WS fault: ' . print_r($response, true));
        }

        $err = $client->getError();
        if ($err) {
            throw new RuntimeException('FESA WS error: ' . $err);
        }

        if (empty($response['status'])) {
            throw new RuntimeException('FESA WS: respuesta sin status.');
        }

        if ($response['status'] !== 'OK') {
            $desc = $response['errorDescripcion'] ?? 'Error desconocido';
            throw new RuntimeException('FESA WS: ' . $response['status'] . ' — ' . $desc);
        }

        return $response;
    }

    /**
     * Retorna el PDF decodificado de base64 para una factura.
     */
    public function getPdf(string $idTransaccion, string $folio, string $receptorId): string
    {
        $xmlContent = $this->readXmlFromStorage($folio, $receptorId);
        $response   = $this->emitir($idTransaccion, $xmlContent);

        if (empty($response['pdf'])) {
            throw new RuntimeException('FESA WS: respuesta sin campo pdf.');
        }

        return base64_decode($response['pdf']);
    }

    /**
     * Retorna el XML decodificado de base64 para una factura.
     */
    public function getXml(string $idTransaccion, string $folio, string $receptorId): string
    {
        $xmlContent = $this->readXmlFromStorage($folio, $receptorId);
        $response   = $this->emitir($idTransaccion, $xmlContent);

        if (empty($response['xml'])) {
            throw new RuntimeException('FESA WS: respuesta sin campo xml.');
        }

        return base64_decode($response['xml']);
    }

    /**
     * Llama al WS y retorna la respuesta cruda sin validar, para inspección.
     */
    public function emitirRaw(string $idTransaccion, string $xmlContent): mixed
    {
        require_once base_path('vendor/econea/nusoap/src/nusoap.php');

        $client = new \nusoap_client($this->wsdl, 'wsdl', false, false, false, false, 0, 110);

        $xmlB64   = base64_encode($xmlContent);
        $peticion  = '<usuario>'          . $this->usuario         . '</usuario>';
        $peticion .= '<contrasena>'       . $this->contrasena      . '</contrasena>';
        $peticion .= '<idTransaccion>'    . $idTransaccion         . '</idTransaccion>';
        $peticion .= '<tipoComprobante>'  . $this->tipoComprobante . '</tipoComprobante>';
        $peticion .= '<nombreSucursal>'   . $this->nombreSucursal  . '</nombreSucursal>';
        $peticion .= '<receptorId></receptorId>';
        $peticion .= '<peticion>'         . $xmlB64                . '</peticion>';
        $peticion .= '<addenda></addenda>';
        $peticion .= '<adjunto></adjunto>';
        $peticion .= '<correos></correos>';
        $peticion .= '<publicar></publicar>';

        $response = $client->call('emitir', $peticion);

        return [
            'response' => $response,
            'fault'    => $client->fault,
            'error'    => $client->getError(),
            'request'  => $client->request,
            'raw'      => $client->response,
        ];
    }

    /**
     * Obtiene el XML de una factura desde FESA usando el idTransaccion.
     * Retorna el XML decodificado como string.
     */
    public function fetchXmlString(string $idTransaccion): string
    {
        $result = $this->emitirRaw($idTransaccion, $this->buildPlaceholderXml());

        if (!empty($result['fault']) || !empty($result['error'])) {
            $msg = $result['error'] ?: print_r($result['fault'], true);
            throw new RuntimeException('FESA WS: ' . $msg);
        }

        $xmlB64 = $result['response']['xml'] ?? null;

        if (!$xmlB64) {
            throw new RuntimeException("FESA no retornó XML para idTransaccion {$idTransaccion}.");
        }

        return base64_decode($xmlB64);
    }

    private function buildPlaceholderXml(): string
    {
        $fecha = '2020-01-01T00:00:00';

        return '<?xml version="1.0" encoding="utf-8"?>'
            . '<cfdi:Comprobante xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'
            . ' xmlns:cfdi="http://www.sat.gob.mx/cfd/4"'
            . ' xmlns:tfd="http://www.sat.gob.mx/TimbreFiscalDigital"'
            . ' xsi:schemaLocation="http://www.sat.gob.mx/cfd/4 http://www.sat.gob.mx/sitio_internet/cfd/4/cfdv40.xsd'
            . ' http://www.sat.gob.mx/TimbreFiscalDigital http://www.sat.gob.mx/sitio_internet/cfd/TimbreFiscalDigital/TimbreFiscalDigitalv11.xsd"'
            . ' Version="4.0" Folio="000000" Fecha="' . $fecha . '"'
            . ' Sello="" FormaPago="99" NoCertificado="" Certificado=""'
            . ' Moneda="MXN" TipoDeComprobante="I" MetodoPago="PPD" LugarExpedicion="00000" Exportacion="01"'
            . ' SubTotal="0.00" Total="0.00">'
            . '<cfdi:Emisor Rfc="XAXX010101000" Nombre="PLACEHOLDER" RegimenFiscal="601"/>'
            . '<cfdi:Receptor Rfc="XAXX010101000" Nombre="PLACEHOLDER" UsoCFDI="S01"'
            . ' DomicilioFiscalReceptor="00000" RegimenFiscalReceptor="616"/>'
            . '<cfdi:Conceptos>'
            . '<cfdi:Concepto ClaveProdServ="01010101" NoIdentificacion="000" Cantidad="1.000"'
            . ' ClaveUnidad="H87" Unidad="PC" Descripcion="PLACEHOLDER" ValorUnitario="0.00" Importe="0.00" ObjetoImp="01"/>'
            . '</cfdi:Conceptos>'
            . '<cfdi:Complemento><tfd:TimbreFiscalDigital/></cfdi:Complemento>'
            . '</cfdi:Comprobante>';
    }

    private function readXmlFromStorage(string $folio, string $receptorId): string
    {
        $path = "{$folio}-{$receptorId}.xml";

        if (!Storage::disk('public')->exists($path)) {
            throw new RuntimeException("XML no encontrado en storage: {$path}");
        }

        return (string) Storage::disk('public')->get($path);
    }
}
