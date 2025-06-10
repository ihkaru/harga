<?php

namespace App\Console\Commands;

use App\Services\SP2KPService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateSp2kpData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sp2kp:update-data'; // Nama command kita

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Memperbarui data harga komoditas terbaru dari API SP2KP';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Memulai proses pembaruan data dari SP2KP...');
        Log::info('Cron Job: Memulai proses pembaruan data dari SP2KP.');

        try {
            // Panggil service yang berisi logika update
            SP2KPService::updateLatestData();

            $this->info('Proses pembaruan data dari SP2KP berhasil diselesaikan.');
            Log::info('Cron Job: Proses pembaruan data dari SP2KP berhasil diselesaikan.');
        } catch (\Exception $e) {
            $this->error('Terjadi kesalahan saat memperbarui data: ' . $e->getMessage());
            Log::error('Cron Job: Gagal memperbarui data SP2KP.', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        return Command::SUCCESS;
    }
}
