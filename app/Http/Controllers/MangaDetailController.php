<?php

namespace App\Http\Controllers;

use DOMXPath;
use DOMDocument;
use App\Models\Manga;
use App\Models\MangaDetail;
use App\Models\Genre;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class MangaDetailController extends Controller
{
    public function fetchAllMangaDetails()
    {
        // Get all manga slugs from the database
        $mangaList = Manga::take(2)->get();

        $count = 0;
        DB::beginTransaction();
        try {
            foreach ($mangaList as $manga) {
                $slug = $manga->slug;
                $url = "https://manhwaindo.one/series/{$slug}/";

                Log::info("Fetching: {$url}");

                // Fetch the webpage
                $response = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
                ])->get($url);

                if (!$response->successful()) {
                    Log::error("Failed to fetch: {$url}");
                    continue;
                }

                $html = $response->body();

                // Parse HTML
                $dom = new DOMDocument();
                @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
                $xpath = new DOMXPath($dom);

                // Extract data
                $title = $xpath->evaluate('string(//h1[@class="entry-title"])') ?: $manga->title;
                $status = $xpath->evaluate('string(//div[contains(@class, "imptdt")][contains(text(), "Status")]/i)') ?: 'Unknown';
                $type = $xpath->evaluate('string(//div[contains(@class, "imptdt")][contains(text(), "Type")]/a)') ?: 'Unknown';
                $releaseYear = $xpath->evaluate('string(//div[contains(@class, "imptdt")][contains(text(), "Released")]/i)') ?: 'Unknown';
                $author = $xpath->evaluate('string(//div[contains(@class, "imptdt")][contains(text(), "Author")]/i)') ?: 'Unknown';
                $artist = $xpath->evaluate('string(//div[contains(@class, "imptdt")][contains(text(), "Artist")]/i)') ?: 'Unknown';
                $viewsText = $xpath->evaluate('string(//div[contains(@class, "imptdt")][contains(text(), "Views")]/i)');
                $views = preg_replace('/[^0-9]/', '', $viewsText) ?: 0; // Remove non-numeric characters and ensure integer value
                $synopsis = $xpath->evaluate('string(//div[@class="entry-content entry-content-single"]/p)') ?: 'No synopsis available';

                // Extract genres
                $genreElements = $xpath->query('//span[@class="mgen"]/a');
                $genres = [];
                foreach ($genreElements as $genre) {
                    $genres[] = trim($genre->textContent);
                }

                // Extract cover image
                $coverImage = $xpath->evaluate('string(//div[@class="thumb"]//img/@src)') ?: '';

                // Save to database
                $mangaDetail = MangaDetail::updateOrCreate(
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
                        $genre = Genre::firstOrCreate(
                            ['name' => $genreName],
                            ['slug' => strtolower(str_replace(' ', '-', $genreName))]
                        );
                        $genreIds[] = $genre->id;
                    }
                    $manga->genres()->sync($genreIds); // ✅ Correct: Attach genres to Manga
                }


                $count++;
            }
            DB::commit();
            Log::info("Successfully processed {$count} manga details.");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error: " . $e->getMessage());
            throw $e;
        }
    }
}
