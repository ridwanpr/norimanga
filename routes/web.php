<?php

use App\Jobs\UpdateBucketUsageJob;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GenreController;
use App\Http\Controllers\MangaController;
use App\Http\Controllers\MangaDetailController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('fetch-genres', [GenreController::class, 'fetchGenres']);
Route::get('fetch-all-manga', [MangaController::class, 'fetchAllManga']);
Route::get('fetch-all-manga-details', [MangaDetailController::class, 'fetchAllMangaDetails']);

Route::get('fetch-all-chapter', [MangaDetailController::class, 'fetchChapter']);

Route::get('/dispatch-bucket-job', function () {
    dispatch(new UpdateBucketUsageJob());
    return response()->json(['message' => 'Bucket usage job dispatched successfully.']);
});