<?php

/**
 * Serverless Entrypoint for Vercel.
 * Serves fast standalone JSON responses from real prodi data,
 * or delegates to Laravel if vendor is present.
 * Handles /api/prodi and /api/rasionalisasi with zero-dependency high performance.
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$route = $_GET['route'] ?? '';
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

// 1. If Laravel vendor exists, delegate to Laravel front controller
$laravelBootstrap = __DIR__ . '/../backend/public/index.php';
$vendorAutoload = __DIR__ . '/../backend/vendor/autoload.php';

if (file_exists($vendorAutoload) && file_exists($laravelBootstrap)) {
    require $laravelBootstrap;
    exit;
}

// 2. Standalone fallback: Load prodi.json dataset
$prodiList = [];
$candidatePaths = [
    __DIR__ . '/prodi.json',
    __DIR__ . '/../backend/database/data/prodi.json',
];

foreach ($candidatePaths as $path) {
    if (file_exists($path)) {
        $content = file_get_contents($path);
        if ($content) {
            $prodiList = json_decode($content, true) ?: [];
            break;
        }
    }
}

// Endpoint: GET /api/prodi
if (str_contains($uri, '/api/prodi') || str_contains($route, 'prodi')) {
    $tree = [];
    foreach ($prodiList as $item) {
        $univ = $item['universitas'];
        $prodi = $item['prodi'];
        if (!isset($tree[$univ])) {
            $tree[$univ] = [];
        }
        $tree[$univ][$prodi] = [
            'quota' => $item['quota'] ?? ($item['kuota'] ?? 100),
            'peminatan' => $item['peminatan'] ?? 'Umum',
            'status' => $item['status'] ?? 'Kompetitif',
            'competition' => $item['competition'] ?? 80,
            'alumni' => $item['alumni'] ?? 80,
            'passing_grade' => $item['passing_grade'] ?? 80.0,
            'description' => $item['description'] ?? "Program studi {$prodi} di {$univ}.",
        ];
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'success',
        'message' => 'Daftar universitas dan program studi berhasil diambil.',
        'data' => $tree,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Endpoint: POST /api/rasionalisasi
if (str_contains($uri, '/api/rasionalisasi') || str_contains($route, 'rasionalisasi')) {
    $rawInput = file_get_contents('php://input');
    $payload = json_decode($rawInput, true) ?: $_POST;

    $scores = $payload['nilai_semester'] ?? [];
    $avg = 0.0;
    if (is_array($scores) && !empty($scores)) {
        $valid = array_filter($scores, fn($v) => is_numeric($v));
        if (!empty($valid)) {
            $avg = round(array_sum($valid) / count($valid), 1);
        }
    } elseif (isset($payload['rata_rata']) && is_numeric($payload['rata_rata'])) {
        $avg = round((float) $payload['rata_rata'], 1);
    }

    $akreditasi = $payload['akreditasi'] ?? 'B';
    $schoolScore = match ($akreditasi) {
        'A' => 96,
        'B' => 82,
        'C' => 68,
        default => 56,
    };

    $univName = $payload['universitas'] ?? '';
    $prodiName = $payload['prodi'] ?? '';

    // Cari jurusan yang cocok
    $matched = null;
    foreach ($prodiList as $item) {
        $univMatch = strcasecmp($item['universitas'], $univName) === 0
            || stripos($item['universitas'], $univName) !== false;
        if ($univMatch) {
            $cleanItem = trim(preg_replace('/\s*\([^\)]*\)\s*$/i', '', $item['prodi']));
            $cleanInput = trim(preg_replace('/\s*\([^\)]*\)\s*$/i', '', $prodiName));
            if (strcasecmp($item['prodi'], $prodiName) === 0 || strcasecmp($cleanItem, $cleanInput) === 0) {
                $matched = $item;
                break;
            }
        }
    }

    $competition = (int) ($matched['competition'] ?? 80);
    $alumni = (int) ($matched['alumni'] ?? 80);
    $quota = $matched['quota'] ?? ($matched['kuota'] ?? 100);

    // Formula SNBP: Rapot 52%, Sekolah 22%, Keketatan 14%, Alumni 12%
    $rawScore = ($avg * 0.52)
        + ($schoolScore * 0.22)
        + ((100 - $competition) * 0.14)
        + ($alumni * 0.12);

    $prediction = (int) min(99, max(12, round($rawScore)));
    $level = match (true) {
        $prediction >= 75 => ['label' => 'Tinggi', 'color' => 'bg-emerald-100 text-emerald-700'],
        $prediction >= 50 => ['label' => 'Sedang', 'color' => 'bg-amber-100 text-amber-700'],
        default => ['label' => 'Kecil', 'color' => 'bg-rose-100 text-rose-700'],
    };

    $scoreDisplay = round($prediction * 7.8, 2);
    $keteranganValue = round(max(1.68, ((100 - $prediction) * 0.18 + 1.68)), 2);
    $peminatValue = (int) min(99999, max(1000, round($competition * 34.6)));
    $categoryLabel = $prediction >= 75 ? 'SANGAT KETAT' : ($prediction >= 50 ? 'KETAT' : 'TERBATAS');

    $description = ($matched['description'] ?? "Program studi {$prodiName} di {$univName}.")
        . " Siswa dengan rata-rata rapot " . number_format($avg, 1)
        . " dan akreditasi sekolah {$akreditasi} memiliki tingkat rasionalitas "
        . strtolower($level['label']) . " untuk jurusan ini.";

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'success',
        'message' => 'Kalkulasi rasionalisasi prodi berhasil diproses.',
        'data' => [
            'mode' => 'single_prediction',
            'universitas' => $univName,
            'prodi' => $prodiName,
            'rata_rata_rapot' => $avg,
            'skor_prediksi' => $prediction,
            'skor_aman' => $scoreDisplay,
            'keterangan_persen' => "{$keteranganValue}%",
            'kuota' => $quota,
            'peminat' => $peminatValue,
            'peminat_formatted' => number_format($peminatValue, 0, ',', '.'),
            'kategori' => $categoryLabel,
            'peluang' => $level['label'],
            'badge_color' => $level['color'],
            'angle_deg' => round(($prediction / 100) * 360, 1) . 'deg',
            'chance_percent' => "{$prediction}%",
            'deskripsi' => $description,
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Fallback jika rute tidak ditemukan
http_response_code(404);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['status' => 'error', 'message' => 'Not Found']);
