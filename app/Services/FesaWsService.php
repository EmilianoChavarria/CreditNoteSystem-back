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

    private function readXmlFromStorage(string $folio, string $receptorId): string
    {
        $path = "{$folio}-{$receptorId}.xml";

        if (!Storage::disk('public')->exists($path)) {
            throw new RuntimeException("XML no encontrado en storage: {$path}");
        }

        return (string) Storage::disk('public')->get($path);
    }
}
