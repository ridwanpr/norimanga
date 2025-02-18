<?php

namespace App\Http\Controllers;

use DOMXPath;
use DOMDocument;
use App\Models\Manga;
use App\Jobs\FetchAllMangaJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class MangaController extends Controller
{
    public function fetchAllManga()
    {
        FetchAllMangaJob::dispatch();
        
        return response()->json(['message' => 'Manga fetch job has been dispatched']);
    }
}
