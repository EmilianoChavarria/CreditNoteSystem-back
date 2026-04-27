<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class BanxicoService
{
    private const SERIES_URL = 'https://www.banxico.org.mx/SieAPIRest/service/v1/series/SF43718/datos/oportuno';
    private const CACHE_KEY  = 'banxico_usd_fix_rate';
    private const CACHE_TTL  = 3600; // 1 hora

    public function getCurrentUsdRate(): float
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return $this->fetchFromApi();
        });
    }

    private function fetchFromApi(): float
    {
        $token = config('services.banxico.token');

        if (empty($token)) {
            throw new RuntimeException('BANXICO_TOKEN no configurado.');
        }

        $response = Http::timeout(10)
            ->get(self::SERIES_URL, ['token' => $token]);

        if (!$response->successful()) {
            throw new RuntimeException('Error al consultar Banxico: HTTP ' . $response->status());
        }

        $dato = $response->json('bmx.series.0.datos.0.dato');

        if ($dato === null || $dato === 'N/E') {
            throw new RuntimeException('Banxico no devolvió tipo de cambio FIX.');
        }

        return (float) $dato;
    }
}
