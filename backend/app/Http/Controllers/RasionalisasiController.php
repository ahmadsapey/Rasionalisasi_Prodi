<?php

namespace App\Http\Controllers;

use App\Http\Requests\RasionalisasiRequest;
use App\Services\RasionalisasiService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class RasionalisasiController extends Controller
{
    public function __construct(
        protected RasionalisasiService $rasionalisasiService
    ) {
    }

    /**
     * Hitung peluang kelulusan prodi berdasarkan nilai.
     *
     * @param RasionalisasiRequest $request
     * @return JsonResponse
     */
    public function hitung(RasionalisasiRequest $request): JsonResponse
    {
        try {
            $nilaiInput = (float) $request->validated('nilai');
            $hasil = $this->rasionalisasiService->hitung($nilaiInput);

            return response()->json([
                'status' => 'success',
                'message' => 'Kalkulasi rasionalisasi prodi berhasil diproses.',
                'nilai_input' => $nilaiInput,
                'total_rekomendasi' => $hasil->count(),
                'data' => $hasil,
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}