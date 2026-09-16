<?php

use App\Http\Controllers\RasionalisasiController;
use Illuminate\Support\Facades\Route;

Route::get('/prodi', [RasionalisasiController::class, 'prodi']);
Route::post('/rasionalisasi', [RasionalisasiController::class, 'hitung']);
