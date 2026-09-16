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
     * Dapatkan daftar universitas dan program studi yang tersedia.
     *
     * @return JsonResponse
     */
    public function prodi(): JsonResponse
    {
        try {
            $data = $this->rasionalisasiService->getProdiTree();

            return response()->json([
                'status' => 'success',
                'message' => 'Daftar universitas dan program studi berhasil diambil.',
                'data' => $data,
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
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
            $hasil = $this->rasionalisasiService->hitung($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Kalkulasi rasionalisasi prodi berhasil diproses.',
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