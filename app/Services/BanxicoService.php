<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class BanxicoService
{
    private const BASE_URL  = 'https://www.banxico.org.mx/SieAPIRest/service/v1/series/SF43718/datos';
    private const CACHE_KEY = 'banxico_usd_fix_rate';
    private const CACHE_TTL = 3600; // 1 hora

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

        $endDate   = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime('-7 days'));
        $url       = self::BASE_URL . "/{$startDate}/{$endDate}";

        $response = Http::timeout(10)
            ->get($url, ['token' => $token]);

        if (!$response->successful()) {
            throw new RuntimeException('Error al consultar Banxico: HTTP ' . $response->status());
        }

        $datos = $response->json('bmx.series.0.datos') ?? [];

        $available = array_filter($datos, fn($d) => !empty($d['dato']) && $d['dato'] !== 'N/E');

        $last = end($available);

        if (!$last) {
            throw new RuntimeException('Banxico no devolvió tipo de cambio FIX.');
        }

        return (float) $last['dato'];
    }
}
