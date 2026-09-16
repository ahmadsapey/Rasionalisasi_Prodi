<?php

namespace App\Services;

use App\Models\Prodi;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use RuntimeException;

class RasionalisasiService
{
    /**
     * Mengambil daftar program studi yang dikelompokkan berdasarkan universitas.
     *
     * @return array
     */
    public function getProdiTree(): array
    {
        $dataProdi = $this->getProdiData();
        $tree = [];

        foreach ($dataProdi as $item) {
            $univ = $item['universitas'];
            $prodiName = $item['prodi'];

            if (!isset($tree[$univ])) {
                $tree[$univ] = [];
            }

            $tree[$univ][$prodiName] = [
                'quota' => $item['quota'] ?? ($item['kuota'] ?? 100),
                'peminatan' => $item['peminatan'] ?? 'Umum',
                'status' => $item['status'] ?? 'Kompetitif',
                'competition' => $item['competition'] ?? 80,
                'alumni' => $item['alumni'] ?? 80,
                'passing_grade' => $item['passing_grade'] ?? 80.0,
                'description' => $item['description'] ?? "Program studi {$prodiName} di {$univ}.",
            ];
        }

        return $tree;
    }

    /**
     * Hitung rasionalisasi peluang berdasarkan input dari frontend.
     *
     * @param array $payload
     * @return array
     * @throws RuntimeException
     */
    public function hitung(array $payload): array
    {
        // 1. Dapatkan nilai rata-rata rapot
        $rapotAverage = $this->hitungRataRata($payload);

        $universitas = $payload['universitas'] ?? null;
        $prodiName = $payload['prodi'] ?? null;
        $akreditasi = $payload['akreditasi'] ?? 'B';
        $schoolScore = $this->getSchoolScore($akreditasi);

        $dataProdi = $this->getProdiData();

        // 2. Jika universitas & prodi spesifik dipilih (mode prediksi detail)
        if ($universitas && $prodiName) {
            $matched = collect($dataProdi)->first(function ($item) use ($universitas, $prodiName) {
                $univMatch = strcasecmp($item['universitas'], $universitas) === 0
                    || stripos($item['universitas'], $universitas) !== false
                    || stripos($universitas, $item['universitas']) !== false;

                if (!$univMatch) {
                    return false;
                }

                $cleanItemProdi = trim(preg_replace('/\s*\([^\)]*\)\s*$/i', '', $item['prodi']));
                $cleanInputProdi = trim(preg_replace('/\s*\([^\)]*\)\s*$/i', '', $prodiName));

                return strcasecmp($item['prodi'], $prodiName) === 0
                    || strcasecmp($cleanItemProdi, $cleanInputProdi) === 0
                    || stripos($item['prodi'], $cleanInputProdi) !== false
                    || stripos($prodiName, $cleanItemProdi) !== false;
            });

            if ($matched) {
                return $this->hitungPrediksiDetail($matched, $rapotAverage, $schoolScore, $akreditasi);
            }

            $fallbackProdi = [
                'universitas' => $universitas,
                'prodi' => $prodiName,
                'competition' => 80,
                'alumni' => 80,
                'quota' => 100,
                'description' => "Analisis rasionalisasi untuk program studi {$prodiName} di {$universitas}."
            ];
            return $this->hitungPrediksiDetail($fallbackProdi, $rapotAverage, $schoolScore, $akreditasi);
        }

        // 3. Mode fallback: evaluasi ranking seluruh prodi berdasarkan passing grade
        return [
            'mode' => 'rekomendasi_umum',
            'rata_rata_rapot' => $rapotAverage,
            'total_rekomendasi' => count($dataProdi),
            'rekomendasi' => collect($dataProdi)->map(function ($item) use ($rapotAverage) {
                $passingGrade = (float) ($item['passing_grade'] ?? 80.0);
                $selisih = round($rapotAverage - $passingGrade, 2);
                [$status, $badge] = $this->tentukanPeluangSelisih($selisih);

                return [
                    'universitas' => $item['universitas'],
                    'prodi' => $item['prodi'],
                    'passing_grade' => $passingGrade,
                    'kuota' => $item['quota'] ?? ($item['kuota'] ?? null),
                    'selisih' => $selisih,
                    'status' => $status,
                    'badge' => $badge,
                ];
            })->sortByDesc('selisih')->values()->all(),
        ];
    }

    /**
     * Hitung kalkulasi formula SNBP detail untuk satu pilihan jurusan.
     *
     * @param array $prodiItem
     * @param float $rapotAverage
     * @param int $schoolScore
     * @param string $akreditasi
     * @return array
     */
    private function hitungPrediksiDetail(array $prodiItem, float $rapotAverage, int $schoolScore, string $akreditasi): array
    {
        $competition = (int) ($prodiItem['competition'] ?? 80);
        $alumni = (int) ($prodiItem['alumni'] ?? 80);

        // Rumus SNBP: bobot Rapot 52%, Sekolah 22%, Keketatan 14%, Alumni 12%
        $rawScore = ($rapotAverage * 0.52)
            + ($schoolScore * 0.22)
            + ((100 - $competition) * 0.14)
            + ($alumni * 0.12);

        $prediction = (int) min(99, max(12, round($rawScore)));
        $level = $this->getLevel($prediction);
        $angle = round(($prediction / 100) * 360, 1);

        $scoreDisplay = round($prediction * 7.8, 2);
        $keteranganValue = round(max(1.68, ((100 - $prediction) * 0.18 + 1.68)), 2);
        $peminatValue = (int) min(99999, max(1000, round($competition * 34.6)));
        $categoryLabel = $prediction >= 75 ? 'SANGAT KETAT' : ($prediction >= 50 ? 'KETAT' : 'TERBATAS');

        $quota = $prodiItem['quota'] ?? ($prodiItem['kuota'] ?? 0);
        $description = ($prodiItem['description'] ?? '')
            . " Siswa dengan rata-rata rapot " . number_format($rapotAverage, 1)
            . " dan akreditasi sekolah {$akreditasi} memiliki tingkat rasionalitas "
            . strtolower($level['label']) . " untuk jurusan ini.";

        return [
            'mode' => 'single_prediction',
            'universitas' => $prodiItem['universitas'],
            'prodi' => $prodiItem['prodi'],
            'rata_rata_rapot' => $rapotAverage,
            'skor_prediksi' => $prediction,
            'skor_aman' => $scoreDisplay,
            'keterangan_persen' => "{$keteranganValue}%",
            'kuota' => $quota,
            'peminat' => $peminatValue,
            'peminat_formatted' => number_format($peminatValue, 0, ',', '.'),
            'kategori' => $categoryLabel,
            'peluang' => $level['label'],
            'badge_color' => $level['color'],
            'angle_deg' => "{$angle}deg",
            'chance_percent' => "{$prediction}%",
            'deskripsi' => $description,
        ];
    }

    /**
     * Hitung rata-rata rapot dari semester 1-5 atau nilai langsung.
     */
    private function hitungRataRata(array $payload): float
    {
        if (!empty($payload['nilai_semester']) && is_array($payload['nilai_semester'])) {
            $validValues = array_filter($payload['nilai_semester'], fn($val) => is_numeric($val));
            if (!empty($validValues)) {
                return round(array_sum($validValues) / count($validValues), 1);
            }
        }

        if (isset($payload['rata_rata']) && is_numeric($payload['rata_rata'])) {
            return round((float) $payload['rata_rata'], 1);
        }

        if (isset($payload['nilai']) && is_numeric($payload['nilai'])) {
            return round((float) $payload['nilai'], 1);
        }

        return 0.0;
    }

    /**
     * Peta bobot skor akreditasi sekolah.
     */
    private function getSchoolScore(string $akreditasi): int
    {
        $map = [
            'A' => 96,
            'B' => 82,
            'C' => 68,
            'Belum Terakreditasi' => 56,
        ];

        return $map[$akreditasi] ?? 82;
    }

    /**
     * Tentukan level kategori peluang.
     */
    private function getLevel(int $prediction): array
    {
        if ($prediction >= 75) {
            return [
                'label' => 'Tinggi',
                'color' => 'bg-emerald-100 text-emerald-700',
            ];
        }

        if ($prediction >= 50) {
            return [
                'label' => 'Sedang',
                'color' => 'bg-amber-100 text-amber-700',
            ];
        }

        return [
            'label' => 'Kecil',
            'color' => 'bg-rose-100 text-rose-700',
        ];
    }

    /**
     * Evaluasi selisih passing grade.
     */
    private function tentukanPeluangSelisih(float $selisih): array
    {
        if ($selisih >= 2.0) {
            return ['Peluang Sangat Besar (Aman)', 'success'];
        }

        if ($selisih >= -1.5) {
            return ['Peluang Sedang (Kompetitif)', 'warning'];
        }

        return ['Peluang Kecil (Berisiko)', 'danger'];
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

        // 3. Fallback ke Database Eloquent
        try {
            $fromDb = Prodi::all()->toArray();
            if (!empty($fromDb)) {
                return $fromDb;
            }
        } catch (\Throwable $e) {
            // Abaikan jika tabel belum siap
        }

        throw new RuntimeException('Data program studi tidak ditemukan.');
    }
}
