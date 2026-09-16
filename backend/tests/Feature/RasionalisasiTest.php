<?php

namespace Tests\Feature;

use Tests\TestCase;

class RasionalisasiTest extends TestCase
{
    /**
     * Test mendapatkan daftar universitas dan program studi.
     */
    public function test_dapat_mengambil_daftar_prodi(): void
    {
        $response = $this->getJson('/api/prodi');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ])
            ->assertJsonStructure([
                'status',
                'message',
                'data',
            ]);
    }

    /**
     * Test validasi jika parameter nilai/semester tidak dikirim.
     */
    public function test_validasi_gagal_jika_nilai_kosong(): void
    {
        $response = $this->postJson('/api/rasionalisasi', []);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Validasi gagal.',
            ])
            ->assertJsonValidationErrors(['nilai']);
    }

    /**
     * Test kalkulasi detail dengan universitas, prodi, dan nilai rapot semester 1-5.
     */
    public function test_kalkulasi_detail_berhasil(): void
    {
        $response = $this->postJson('/api/rasionalisasi', [
            'universitas' => 'Universitas Negeri Semarang',
            'prodi' => 'Teknik Informatika',
            'nilai_semester' => [88, 90, 91, 92, 93],
            'akreditasi' => 'B',
            'nama' => 'Alya Putri',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'mode' => 'single_prediction',
                    'universitas' => 'Universitas Negeri Semarang',
                    'prodi' => 'Teknik Informatika (S1)',
                ],
            ])
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'mode',
                    'universitas',
                    'prodi',
                    'rata_rata_rapot',
                    'skor_prediksi',
                    'skor_aman',
                    'keterangan_persen',
                    'kuota',
                    'peminat',
                    'kategori',
                    'peluang',
                    'deskripsi',
                ],
            ]);
    }
}
