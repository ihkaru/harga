<?php

namespace App\Services;

use App\Models\Komoditas;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class HETService {
    protected Collection $priceControls;

    public function __construct(string $csvPath) {
        $this->priceControls = $this->loadAndParseCsv($csvPath);
    }

    /**
     * Normalisasi nama komoditas untuk matching yang lebih robust.
     * - Trim whitespace
     * - Ganti spasi ganda -> spasi tunggal
     * - Standardisasi spasi di sekitar koma
     */
    private function normalizeName(string $name): string {
        $name = trim($name);
        $name = preg_replace('/\s+/', ' ', $name); // Ganti spasi ganda dengan tunggal
        $name = preg_replace('/\s*,\s*/', ', ', $name); // Normalisasi spasi koma
        return $name;
    }

    /**
     * Memuat dan mem-parsing file CSV data HET/HAP.
     */
    private function loadAndParseCsv(string $path): Collection {
        if (!file_exists($path) || !is_readable($path)) {
            Log::error("File HET/HAP tidak ditemukan atau tidak bisa dibaca di path: {$path}");
            return collect();
        }

        $data = collect();
        if (($handle = fopen($path, "r")) !== FALSE) {
            $header = fgetcsv($handle, 1000, ","); // Baca header

            while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $rowData = array_combine($header, $row);
                // dump($rowData);
                $originalName = $rowData['Komoditas'] ?? null;

                if ($originalName) {
                    $data->push([
                        'original_name' => $originalName,
                        'normalized_name' => $this->normalizeName($originalName),
                        'zona_1' => $rowData['Zona 1'] ?? null,
                        'zona_2' => $rowData['Zona 2'] ?? null,
                        'zona_3' => $rowData['Zona 3'] ?? null,
                        'peraturan' => $rowData['Peraturan'] ?? null,
                        'hap' => $rowData['HAP'] ?? null,
                        'status' => $rowData['Status'] ?? null,
                        'het' => $rowData['HET'] ?? null,
                    ]);
                }
            }
            fclose($handle);
        }
        // dump($data);
        return $data;
    }

    /**
     * Mencari data HET/HAP untuk sebuah Komoditas model.
     */
    public function findPriceControl(Komoditas $komoditas): ?array {
        $normalizedNameToFind = $this->normalizeName($komoditas->nama);

        $found = $this->priceControls->first(function ($item) use ($normalizedNameToFind) {
            return $item['normalized_name'] === $normalizedNameToFind;
        });
        // dump("normalize " . $normalizedNameToFind, "asli: " . $komoditas->nama, $komoditas, $this->priceControls);

        return $found;
    }
}
