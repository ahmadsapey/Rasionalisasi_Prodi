<?php

use App\Http\Controllers\RasionalisasiController;
use Illuminate\Support\Facades\Route;

Route::post('/rasionalisasi', [RasionalisasiController::class, 'hitung']);

