<?php

namespace App\Http\Controllers;

use DOMXPath;
use DOMDocument;
use App\Models\Genre;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class GenreController extends Controller
{
    public function fetchGenres()
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
            ])->get('https://manhwaindo.one/bookmark/');

            if ($response->successful()) {
                $html = $response->body();

                // Create a new DOMDocument
                $dom = new DOMDocument();
                @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);

                // Create XPath object
                $xpath = new DOMXPath($dom);

                // Find all genre links
                $genreLinks = $xpath->query("//ul[contains(@class, 'genre')]/li/a");

                $count = 0;
                DB::beginTransaction();
                try {
                    foreach ($genreLinks as $link) {
                        $name = trim($link->textContent);
                        if (!empty($name)) {
                            // Get the slug from href
                            $href = $link->getAttribute('href');
                            $slug = str_replace(['https://manhwaindo.one/genres/', '/'], '', $href);

                            // Create or update genre
                            Genre::updateOrCreate(
                                ['slug' => $slug],
                                [
                                    'name' => $name,
                                    'slug' => $slug
                                ]
                            );

                            $count++;
                        }
                    }
                    DB::commit();
                    Log::info("Successfully processed {$count} genres");
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error('Error: ' . $e->getMessage());
                }
            } else {
                Log::error('Failed to fetch the webpage');
            }
        } catch (\Exception $e) {
            Log::error('Error: ' . $e->getMessage());
        }
    }
}
