<?php

namespace App\Http\Controllers;

use DOMXPath;
use DOMDocument;
use App\Models\Manga;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MangaController extends Controller
{
    public function fetchAllManga()
    {
        try {
            // Fetch the webpage
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ])->get('https://manhwaindo.one/series/list-mode/');

            if ($response->successful()) {
                Log::info('Successfully fetched the webpage');

                // Create DOM document
                $dom = new DOMDocument();
                @$dom->loadHTML($response->body(), LIBXML_NOERROR | LIBXML_NOWARNING);

                // Create XPath object
                $xpath = new DOMXPath($dom);

                // Find all manga links within blix divs
                $mangaLinks = $xpath->query("//div[@class='soralist']//div[@class='blix']//li/a");

                DB::beginTransaction();
                try {
                    $count = 0;
                    $newCount = 0;

                    foreach ($mangaLinks as $link) {
                        $title = trim($link->textContent);
                        $url = $link->getAttribute('href');

                        // Extract slug from URL
                        $slug = str_replace(['https://manhwaindo.one/series/', '/'], '', $url);

                        // Create or update manga entry
                        $manga = Manga::updateOrCreate(
                            ['slug' => $slug],
                            [
                                'title' => $title,
                                'slug' => $slug
                            ]
                        );

                        if ($manga->wasRecentlyCreated) {
                            Log::info("New manga added: $title");
                            $newCount++;
                        }

                        $count++;
                    }

                    DB::commit();

                    Log::info("Processed $count manga titles");
                    Log::info("Added $newCount new manga");
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error('Error: ' . $e->getMessage());
                }
            } else {
                Log::error('Failed to fetch the webpage: ' . $response->status());
            }
        } catch (\Exception $e) {
            Log::error('Error: ' . $e->getMessage());
        }
    }
}
