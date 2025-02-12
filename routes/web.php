<?php

use App\Http\Controllers\GenreController;
use App\Http\Controllers\MangaController;
use App\Http\Controllers\MangaDetailController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('fetch-genres', [GenreController::class, 'fetchGenres']);
Route::get('fetch-all-manga', [MangaController::class, 'fetchAllManga']);
Route::get('fetch-all-manga-details', [MangaDetailController::class, 'fetchAllMangaDetails']);