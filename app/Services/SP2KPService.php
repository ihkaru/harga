<?php

namespace App\Services;

use App\Models\Harga;
use App\Models\Komoditas;
use App\Supports\Helpers;
use App\Supports\TanggalMerah;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SP2KPService
{
    /**
     * Base URL for SP2KP API
     */
    static string $baseUrl = 'https://api-sp2kp.kemendag.go.id';

    /**
     * Fetch daily price data from SP2KP API
     *
     * @param string|null $startDate Format Y-m-d
     * @param string|null $endDate Format Y-m-d
     * @param int $tipeKomoditasId
     * @param int $pasarId
     * @param int $skip
     * @param int $take
     * @return array
     */
    public static function fetchDailyPriceData(
        ?string $startDate = null,
        ?string $endDate = null,
        int $tipeKomoditasId = 1,
        int $pasarId = 517,
        int $skip = 0,
        int $take = 10000
    ): array {
        $startDate = $startDate ?? Carbon::now()->subDays(3)->format('Y-m-d');
        $endDate = $endDate ?? Carbon::now()->format('Y-m-d');

        $url = self::$baseUrl . "/trx/harga-harian";

        $params = [
            'skip' => $skip,
            'take' => $take,
            'tanggal_start' => $startDate,
            'tanggal_end' => $endDate,
            'tipe_komoditas_id' => $tipeKomoditasId,
            'pasar_id' => $pasarId
        ];

        try {
            $response = Http::get($url, $params);

            // dump($response, $response->successful(), $response->json());
            if ($response->successful()) {
                return $response->json();
            } else {
                Log::error("SP2KP API request failed", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'params' => $params
                ]);
                return [];
            }
        } catch (\Exception $e) {
            Log::error("Exception when calling SP2KP API", [
                'message' => $e->getMessage(),
                'params' => $params
            ]);
            return [];
        }
    }

    /**
     * Process and save data to database
     *
     * @param array $data
     * @return int Number of records processed
     */
    public static function processAndSaveData(array $data): int
    {
        $count = 0;
        $komoditas = Komoditas::get();
        // dump($data);


        foreach ($data as $item) {
            try {
                // Parse date components
                $date = Carbon::parse($item['tanggal']);
                $year = $date->format('Y');
                $month = $date->format('m');
                $day = $date->format('d');
                // dump($item);
                $pekan = Helpers::calculateWeeks($date);
                $namaKomoditas = $item['produk']['nama'];
                $komoditasItem = $komoditas->filter(fn($i) => $i->nama == $namaKomoditas)->first();
                $idKomoditas = str_pad($komoditasItem->id_komoditas * 1, 3, '0', STR_PAD_LEFT);
                $idKomoditasHarian = "$idKomoditas-" . $date->format("Y-n-d");

                $idKomoditasPekanan = "$idKomoditas-" . $date->format("Y-n") . "-" . $pekan;
                $idKomoditasBulanan = "$idKomoditas-" . $date->format("Y-n");

                // Get responden/pedagang from the first detail item
                // $responden = '';
                // if (!empty($item['harga_harian_detail'][0]['pedagang']['nama'])) {
                //     $responden = $item['harga_harian_detail'][0]['pedagang']['nama'];
                // }

                // // Get kecamatan if available
                $kecamatan = 'Mempawah Hilir';
                // if (isset($item['pasar']['kode_kecamatan'])) {
                //     $kecamatan = $item['pasar']['kode_kecamatan'];
                // }

                // Construct data for database
                $hargaData = [
                    'id_komoditas_harian' => $idKomoditasHarian,
                    'id_komoditas_pekanan' => $idKomoditasPekanan,
                    'id_komoditas_bulanan' => $idKomoditasBulanan,
                    'id_pekan' => $pekan,
                    'tanggal' => $date->format('j/n/Y'),
                    'id_komoditas' => $idKomoditas * 1,
                    'tahun' => $year,
                    'bulan' => $month,
                    'tanggal_angka' => $day,
                    'harga' => intval($item['harga']),
                    'responden' => null,
                    'kecamatan' => $kecamatan,
                ];

                // Insert or update in database
                Harga::updateOrCreate(
                    [
                        'id_komoditas_harian' => $idKomoditasHarian,
                    ],
                    $hargaData
                );

                $count++;
            } catch (\Exception $e) {
                // dump("Error:", $e);
                Log::error("Error processing SP2KP data item", [
                    'message' => $e->getMessage(),
                    'item_id' => $item['id'] ?? 'unknown'
                ]);
            }
        }


        return $count;
    }

    /**
     * Harvest all data from SP2KP API
     *
     * @param string|null $startDate Format Y-m-d
     * @param string|null $endDate Format Y-m-d
     * @param int $tipeKomoditasId
     * @param int $pasarId
     * @return array Result statistics
     */
    public static function harvestAllData(
        ?string $startDate = null,
        ?string $endDate = null,
        int $tipeKomoditasId = 1,
        int $pasarId = 517
    ): array {
        $startDate = $startDate ?? Carbon::now()->subDays(3)->format('Y-m-d');
        $endDate = $endDate ?? Carbon::now()->format('Y-m-d');

        $skip = 0;
        $take = 10000;
        $totalProcessed = 0;
        $totalPages = 1;
        $currentPage = 1;

        do {
            $response = self::fetchDailyPriceData(
                $startDate,
                $endDate,
                $tipeKomoditasId,
                $pasarId,
                $skip,
                $take
            );

            if (empty($response) || !isset($response['data']) || empty($response['data'])) {
                break;
            }

            $processedCount = self::processAndSaveData($response['data']);
            $totalProcessed += $processedCount;
            // dump($totalProcessed);

            // Update pagination info
            if (isset($response['meta'])) {
                $totalPages = $response['meta']['last_page'] ?? 1;
                $currentPage = $response['meta']['page'] ?? 1;
            }

            // Prepare next page
            $skip += $take;
            $currentPage++;
        } while ($currentPage <= $totalPages);

        return [
            'total_processed' => $totalProcessed,
            'total_pages' => $totalPages,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
    }

    public static function updateLatestData()
    {
        // $lastDate = Carbon::createFromFormat('Y-m-d', Harga::orderByRaw("CONCAT(tahun,bulan,tanggal_angka) DESC")->first()->tanggal);
        try {
            $lastDate = Carbon::createFromFormat('d/m/Y', Harga::orderByRaw("CONCAT(tahun,bulan,tanggal_angka) DESC")->first()->tanggal);
        } catch (Exception $e) {
            $lastDate = Carbon::createFromFormat('Y-m-d', Harga::orderByRaw("CONCAT(tahun,bulan,tanggal_angka) DESC")->first()->tanggal);
        }
        $now = TanggalMerah::getHariTerakhirTidakLibur();
        if ($lastDate->lt($now) && !$lastDate->isSameDay($now)) {
            // dump($lastDate, $now);
            self::harvestAllData($lastDate, $now);
        }
    }
}
