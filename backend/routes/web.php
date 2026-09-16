<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Menampilkan halaman frontend index.html jika diakses lewat browser
| pada http://127.0.0.1:8000
|
*/

Route::get('/', function () {
    $indexPath = base_path('../index.html');

    if (file_exists($indexPath)) {
        return response()->file($indexPath, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    return view('welcome');
});