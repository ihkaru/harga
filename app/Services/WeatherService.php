<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WeatherService {
    protected $apiKey;
    protected $baseUrl;

    public function __construct() {
        $this->apiKey = config('services.weather_api.key');
        $this->baseUrl = config('services.weather_api.url');
    }

    /**
     * Mengambil dan merangkum data cuaca historis untuk koordinat tertentu.
     *
     * @param float $latitude
     * @param float $longitude
     * @param int $days
     * @return array|null
     */
    public function getHistoricalWeatherSummary(float $latitude, float $longitude, int $days = 7): ?array {
        if (!$this->apiKey || !$this->baseUrl) {
            Log::error('Weather API Key or Base URL is not configured.');
            return null;
        }

        $endDate = Carbon::yesterday();
        $startDate = $endDate->copy()->subDays($days - 1);

        $url = "{$this->baseUrl}/{$latitude},{$longitude}/{$startDate->toDateString()}/{$endDate->toDateString()}";

        try {
            $response = Http::get($url, [
                'unitGroup' => 'metric',
                'key' => $this->apiKey,
                'include' => 'days',
                'elements' => 'temp,precip', // Hanya ambil suhu dan curah hujan
                'contentType' => 'json',
            ]);

            if ($response->failed()) {
                Log::error('Weather API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return null;
            }

            return $this->processWeatherData($response->json()['days']);
        } catch (\Exception $e) {
            Log::error('Exception during Weather API call', ['message' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Memproses data mentah dari API menjadi ringkasan yang berguna.
     *
     * @param array $daysData
     * @return array
     */
    private function processWeatherData(array $daysData): array {
        $totalPrecipitation = 0;
        $dryDays = 0;
        $tempSum = 0;
        $dayCount = count($daysData);

        foreach ($daysData as $day) {
            $precipitation = $day['precip'] ?? 0;
            $totalPrecipitation += $precipitation;

            if ($precipitation < 1) { // Anggap di bawah 1mm sebagai hari kering
                $dryDays++;
            }
            $tempSum += $day['temp'] ?? 0;
        }

        return [
            'total_curah_hujan_mm' => round($totalPrecipitation, 2),
            'jumlah_hari_tanpa_hujan' => $dryDays,
            'suhu_rata_rata_celsius' => $dayCount > 0 ? round($tempSum / $dayCount, 2) : 0,
        ];
    }
}
