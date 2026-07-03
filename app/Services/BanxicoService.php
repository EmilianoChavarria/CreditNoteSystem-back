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

    /**
     * Fetch FIX rates for a date range. Returns map [Y-m-d => rate].
     * Days with no official rate (weekends/holidays) inherit the previous available rate.
     * Result cached permanently — past rates never change.
     */
    public function getRatesByDateRange(string $startDate, string $endDate): array
    {
        $cacheKey = "banxico_rates_{$startDate}_{$endDate}";

        return Cache::remember($cacheKey, 86400, function () use ($startDate, $endDate) {
            return $this->fetchRangeFromApi($startDate, $endDate);
        });
    }

    private function fetchRangeFromApi(string $startDate, string $endDate): array
    {
        $token = config('services.banxico.token');

        if (empty($token)) {
            throw new RuntimeException('BANXICO_TOKEN no configurado.');
        }

        $url = self::BASE_URL . "/{$startDate}/{$endDate}";

        $response = Http::timeout(30)->get($url, ['token' => $token]);

        if (!$response->successful()) {
            throw new RuntimeException('Error al consultar Banxico: HTTP ' . $response->status());
        }

        $datos = $response->json('bmx.series.0.datos') ?? [];

        // Banxico returns dates as DD/MM/YYYY — normalize to Y-m-d for lookup
        $rateMap  = [];
        $lastRate = null;

        foreach ($datos as $d) {
            if (!empty($d['dato']) && $d['dato'] !== 'N/E') {
                $lastRate = (float) $d['dato'];
            }
            if ($lastRate !== null) {
                $key           = \DateTime::createFromFormat('d/m/Y', $d['fecha'])->format('Y-m-d');
                $rateMap[$key] = $lastRate;
            }
        }

        return $rateMap;
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
