<?php

namespace App\Http\Controllers;

use App\Models\Manga;
use App\Models\MangaChapter;
use App\Jobs\FetchChapterImagesJob;
use App\Jobs\FetchMangaDetailsBatchJob;

class MangaDetailController extends Controller
{
    public function fetchAllMangaDetails()
    {
        $totalManga = Manga::count();

        if ($totalManga === 0) {
            return response()->json(['success' => true, 'message' => "No manga found to queue."]);
        }

        Manga::chunk(100, function ($mangaBatch) {
            FetchMangaDetailsBatchJob::dispatch($mangaBatch)->delay(rand(2, 5));
        });

        return response()->json(['success' => true, 'message' => "$totalManga manga queued in batches."]);
    }

    public function fetchChapter()
    {
        $chapters = MangaChapter::whereNull('image')
            ->orWhere('image', '[]')
            ->orderBy('slug', 'asc')
            ->chunk(100)
            ->each(function ($chapters) {
                foreach ($chapters as $index => $chapter) {
                    $delayInSeconds = rand(60, 300) + ($index * 30);

                    FetchChapterImagesJob::dispatch($chapter)
                        ->onQueue('chapter-images')
                        ->delay(now()->addSeconds($delayInSeconds));
                }
            });

        return response()->json(['success' => true, 'message' => "Queued " . count($chapters) . " chapters for processing."]);
    }
}
