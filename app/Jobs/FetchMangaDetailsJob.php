<?php

namespace App\Jobs;

use App\Models\Manga;
use App\Models\MangaDetail;
use App\Models\Genre;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use DOMXPath;
use DOMDocument;

class FetchMangaDetailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $manga;

    public function __construct(Manga $manga)
    {
        $this->manga = $manga;
    }

    public function handle()
    {
        $manga = $this->manga;
        $url = "https://manhwaindo.one/series/{$manga->slug}/";

        Log::info("Fetching: {$url}");

        $response = Http::withHeaders([
            'User-Agent' => $this->getRandomUserAgent()
        ])->get($url);

        if (!$response->successful()) {
            Log::error("Failed to fetch: {$url}");
            return;
        }

        $html = $response->body();
        $dom = new DOMDocument();
        @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($dom);

        // Extract manga details
        $status = $xpath->evaluate('string(//div[contains(@class, "imptdt")][contains(text(), "Status")]/i)') ?: 'Unknown';
        $type = $xpath->evaluate('string(//div[contains(@class, "imptdt")][contains(text(), "Type")]/a)') ?: 'Unknown';
        $releaseYear = $xpath->evaluate('string(//div[contains(@class, "imptdt")][contains(text(), "Released")]/i)') ?: 'Unknown';
        $author = $xpath->evaluate('string(//div[contains(@class, "imptdt")][contains(text(), "Author")]/i)') ?: 'Unknown';
        $artist = $xpath->evaluate('string(//div[contains(@class, "imptdt")][contains(text(), "Artist")]/i)') ?: 'Unknown';
        $viewsText = $xpath->evaluate('string(//div[contains(@class, "imptdt")][contains(text(), "Views")]/i)');
        $views = preg_replace('/[^0-9]/', '', $viewsText) ?: 0;
        $synopsis = $xpath->evaluate('string(//div[@class="entry-content entry-content-single"]/p)') ?: 'No synopsis available';

        // Extract genres
        $genreElements = $xpath->query('//span[@class="mgen"]/a');
        $genres = [];
        foreach ($genreElements as $genre) {
            $genres[] = trim($genre->textContent);
        }

        // Save to database
        MangaDetail::updateOrCreate(
            ['manga_id' => $manga->id],
            [
                'status' => $status,
                'type' => $type,
                'release_year' => $releaseYear,
                'author' => $author,
                'artist' => $artist,
                'views' => $views,
                'synopsis' => $synopsis,
            ]
        );

        // Attach genres
        if (!empty($genres)) {
            $genreIds = [];
            foreach ($genres as $genreName) {
                $genre = Genre::firstOrCreate(['name' => $genreName], ['slug' => strtolower(str_replace(' ', '-', $genreName))]);
                $genreIds[] = $genre->id;
            }
            $manga->genres()->sync($genreIds);
        }

        Log::info("Successfully updated: {$manga->title}");
    }

    private function getRandomUserAgent()
    {
        $userAgents = [
            "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36",
            "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/88.0.4324.96 Safari/537.36",
            "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/87.0.4280.66 Safari/537.36"
        ];
        return $userAgents[array_rand($userAgents)];
    }
}
