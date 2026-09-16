<?php

namespace App\Services;

use App\Models\Prodi;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use RuntimeException;

class RasionalisasiService
{
    /**
     * Hitung rasionalisasi peluang berdasarkan nilai input.
     *
     * @param float $nilaiInput
     * @return Collection
     * @throws RuntimeException
     */
    public function hitung(float $nilaiInput): Collection
    {
        $dataProdi = $this->getProdiData();

        return collect($dataProdi)->map(function ($item) use ($nilaiInput) {
            $passingGrade = (float) $item['passing_grade'];
            $selisih = round($nilaiInput - $passingGrade, 2);

            [$status, $badge] = $this->tentukanPeluang($selisih);

            return [
                'universitas'   => $item['universitas'],
                'prodi'         => $item['prodi'],
                'passing_grade' => $passingGrade,
                'kuota'         => $item['kuota'] ?? null,
                'selisih'       => $selisih,
                'status'        => $status,
                'badge'         => $badge,
            ];
        })->sortByDesc('selisih')->values();
    }

    /**
     * Mengambil data program studi dari file JSON atau database.
     *
     * @return array
     * @throws RuntimeException
     */
    public function getProdiData(): array
    {
        // 1. Cek dari database_path('data/prodi.json')
        $primaryPath = database_path('data/prodi.json');
        if (File::exists($primaryPath)) {
            $jsonContent = File::get($primaryPath);
            $decoded = json_decode($jsonContent, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        // 2. Fallback ke storage_path('app/prodi.json')
        $fallbackPath = storage_path('app/prodi.json');
        if (File::exists($fallbackPath)) {
            $jsonContent = File::get($fallbackPath);
            $decoded = json_decode($jsonContent, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        // 3. Fallback ke Database Eloquent jika tabel prodis ada isinya
        try {
            $fromDb = Prodi::all(['universitas', 'prodi', 'passing_grade', 'kuota'])->toArray();
            if (!empty($fromDb)) {
                return $fromDb;
            }
        } catch (\Throwable $e) {
            // Abaikan jika migrasi/tabel belum siap
        }

        throw new RuntimeException('Data program studi tidak ditemukan.');
    }

    /**
     * Tentukan label status dan badge warna berdasarkan selisih nilai.
     *
     * @param float $selisih
     * @return array{0: string, 1: string}
     */
    private function tentukanPeluang(float $selisih): array
    {
        if ($selisih >= 2.0) {
            return ['Peluang Sangat Besar (Aman)', 'success'];
        }

        if ($selisih >= -1.5) {
            return ['Peluang Sedang (Kompetitif)', 'warning'];
        }

        return ['Peluang Kecil (Berisiko)', 'danger'];
    }
}

