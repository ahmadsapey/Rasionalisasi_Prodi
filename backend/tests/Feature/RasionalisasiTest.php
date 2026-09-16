<?php

namespace Tests\Feature;

use Tests\TestCase;

class RasionalisasiTest extends TestCase
{
    /**
     * Test validasi jika parameter nilai tidak dikirim.
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
     * Test validasi jika nilai melebihi 100 atau bernilai negatif.
     */
    public function test_validasi_gagal_jika_nilai_di_luar_rentang(): void
    {
        // Nilai > 100
        $responseMax = $this->postJson('/api/rasionalisasi', ['nilai' => 105]);
        $responseMax->assertStatus(422)
            ->assertJsonValidationErrors(['nilai']);

        // Nilai < 0
        $responseMin = $this->postJson('/api/rasionalisasi', ['nilai' => -10]);
        $responseMin->assertStatus(422)
            ->assertJsonValidationErrors(['nilai']);

        // Nilai bukan angka
        $responseType = $this->postJson('/api/rasionalisasi', ['nilai' => 'bukan_angka']);
        $responseType->assertStatus(422)
            ->assertJsonValidationErrors(['nilai']);
    }

    /**
     * Test kalkulasi rasionalisasi berhasil dan menghasilkan urutan rekomendasi yang tepat.
     */
    public function test_kalkulasi_rasionalisasi_berhasil(): void
    {
        $response = $this->postJson('/api/rasionalisasi', [
            'nilai' => 85.0,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'nilai_input',
                'total_rekomendasi',
                'data' => [
                    '*' => [
                        'universitas',
                        'prodi',
                        'passing_grade',
                        'kuota',
                        'selisih',
                        'status',
                        'badge',
                    ],
                ],
            ])
            ->assertJson([
                'status'      => 'success',
                'nilai_input' => 85.0,
            ]);

        // Pastikan hasil terurut descending berdasarkan selisih
        $data = $response->json('data');
        $this->assertNotEmpty($data);

        for ($i = 0; $i < count($data) - 1; $i++) {
            $this->assertGreaterThanOrEqual(
                $data[$i + 1]['selisih'],
                $data[$i]['selisih'],
                'Rekomendasi prodi harus terurut menurun berdasarkan selisih'
            );
        }
    }
}

