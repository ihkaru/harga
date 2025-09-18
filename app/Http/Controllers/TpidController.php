<?php

namespace App\Http\Controllers;

use App\Models\Komoditas;
use App\Services\TpidReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TpidController extends Controller {
    protected $tpidReportService;

    public function __construct(TpidReportService $tpidReportService) {
        $this->tpidReportService = $tpidReportService;
    }

    public function generateReport(Request $request) {
        // Misalkan kita mau menganalisis 'Bawang Merah, 1 kg' dengan id_komoditas = '10'
        $komoditas = Komoditas::where('id_komoditas', '10')->firstOrFail();

        // Tanggal saat ini sesuai skenario
        $currentDate = Carbon::parse('2025-09-18');

        // Hasilkan prompt
        $prompt = $this->tpidReportService->generateTpidAnalysisPrompt($komoditas, $currentDate);

        // Di sini Anda akan mengirimkan $prompt ke API LLM pilihan Anda (misal: OpenAI, Google AI)
        // $llmResponse = Http::withToken(env('OPENAI_API_KEY'))->post('https://api.openai.com/v1/chat/completions', [
        //     'model' => 'gpt-4',
        //     'messages' => [
        //         ['role' => 'user', 'content' => $prompt]
        //     ]
        // ]);

        // Untuk tujuan demonstrasi, kita tampilkan saja prompt-nya
        return response()->json([
            'komoditas' => $komoditas->nama,
            'generated_prompt_for_llm' => $prompt
        ]);
    }

    public function generateTopMoversReport(Request $request) {
        $currentDate = Carbon::parse('2025-09-18');

        // Dapatkan 5 komoditas dengan perubahan harga mingguan terbesar
        $topMovers = $this->tpidReportService->getTopMovers(5, 7);

        $reports = [];
        foreach ($topMovers as $mover) {
            $prompt = $this->tpidReportService->generateTpidAnalysisPrompt($mover['komoditas'], $currentDate);

            // Di sini Anda bisa langsung mengirim prompt ke LLM dan menyimpan hasilnya
            // atau hanya menyimpan promptnya saja.
            $reports[] = [
                'komoditas_nama' => $mover['komoditas']->nama,
                'perubahan_mingguan' => number_format($mover['change'], 2) . '%',
                'prompt_analisis' => $prompt,
                // 'hasil_analisis_llm' => $hasilDariLlm, // (setelah diproses)
            ];
        }

        return response()->json($reports);
    }

    // Pastikan Anda mendaftarkan route, misal: Route::get('/report/komoditas/{id}', [TpidController::class, 'generateSingleReport']);

    public function generateSingleReport(Request $request, $komoditasId) {
        $komoditas = Komoditas::where('id_komoditas', $komoditasId)->firstOrFail();
        $currentDate = Carbon::parse('2025-09-18');

        $prompt = $this->tpidReportService->generateTpidAnalysisPrompt($komoditas, $currentDate);

        // Kirim prompt ke LLM, dapatkan hasilnya, lalu kembalikan
        // ...

        return response()->json([
            'komoditas_nama' => $komoditas->nama,
            'prompt_analisis' => $prompt
        ]);
    }

    public function generateFullReport(Request $request) {
        $allCommodities = Komoditas::all();
        $currentDate = Carbon::parse('2025-09-18');
        $allPrompts = [];

        foreach ($allCommodities as $komoditas) {
            $allPrompts[$komoditas->nama] = $this->tpidReportService->generateTpidAnalysisPrompt($komoditas, $currentDate);
        }

        return response()->json($allPrompts);
    }
}
