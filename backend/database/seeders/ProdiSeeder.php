<?php

namespace Database\Seeders;

use App\Models\Prodi;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class ProdiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $filePath = database_path('data/prodi.json');

        if (!File::exists($filePath)) {
            $filePath = storage_path('app/prodi.json');
        }

        if (!File::exists($filePath)) {
            $this->command?->error("File prodi.json tidak ditemukan.");
            return;
        }

        $dataProdi = json_decode(File::get($filePath), true);

        if (!is_array($dataProdi)) {
            $this->command?->error("Format JSON pada prodi.json tidak valid.");
            return;
        }

        foreach ($dataProdi as $item) {
            Prodi::updateOrCreate(
                [
                    'universitas' => $item['universitas'],
                    'prodi'       => $item['prodi'],
                ],
                [
                    'passing_grade' => $item['passing_grade'],
                    'kuota'         => $item['kuota'] ?? null,
                ]
            );
        }
    }
}
