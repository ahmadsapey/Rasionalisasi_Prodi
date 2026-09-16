<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class RasionalisasiRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'universitas' => ['nullable', 'string', 'max:255'],
            'prodi' => ['nullable', 'string', 'max:255'],
            'nilai_semester' => ['nullable', 'array'],
            'nilai_semester.*' => ['numeric', 'min:0', 'max:100'],
            'nilai' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'rata_rata' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'akreditasi' => ['nullable', 'string', 'max:50'],
            'nama' => ['nullable', 'string', 'max:255'],
            'npsn' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $hasSemester = $this->has('nilai_semester') && !empty($this->input('nilai_semester'));
            $hasNilai = $this->has('nilai') && $this->input('nilai') !== null;
            $hasRataRata = $this->has('rata_rata') && $this->input('rata_rata') !== null;

            if (!$hasSemester && !$hasNilai && !$hasRataRata) {
                $validator->errors()->add('nilai', 'Harap masukkan nilai rapot semester atau nilai rata-rata.');
            }
        });
    }

    /**
     * Custom error messages for validation.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nilai_semester.array' => 'Format nilai semester harus berupa daftar angka.',
            'nilai_semester.*.numeric' => 'Setiap nilai semester harus berupa angka numerik.',
            'nilai_semester.*.min' => 'Nilai semester minimal adalah 0.',
            'nilai_semester.*.max' => 'Nilai semester maksimal adalah 100.',
            'nilai.numeric' => 'Nilai harus berupa angka numerik.',
            'nilai.min' => 'Nilai minimal adalah 0.',
            'nilai.max' => 'Nilai maksimal adalah 100.',
            'rata_rata.numeric' => 'Nilai rata-rata harus berupa angka numerik.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'status' => 'error',
            'message' => 'Validasi gagal.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
