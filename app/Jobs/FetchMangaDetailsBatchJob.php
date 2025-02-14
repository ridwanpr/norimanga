<?php

namespace App\Jobs;

use DOMXPath;
use DOMDocument;
use App\Models\Genre;
use App\Models\Manga;
use App\Models\MangaDetail;
use App\Models\MangaChapter;
use Illuminate\Bus\Queueable;
use App\Services\BucketManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class FetchMangaDetailsBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $mangaBatch;
    private $bucketManager;

    public function __construct($mangaBatch)
    {
        $this->mangaBatch = $mangaBatch;
        $this->bucketManager = new BucketManager();
    }

    public function handle()
    {
        foreach ($this->mangaBatch as $manga) {
            $this->fetchMangaDetails($manga);
            sleep(rand(5, 10)); // Avoid rate limits with random delay
        }

        Log::info("Batch of " . count($this->mangaBatch) . " manga processed successfully.");
    }

    private function fetchMangaDetails(Manga $manga)
    {
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

        DB::beginTransaction(); // ✅ Start Transaction

        try {
            // ✅ Extract manga details
            $status = $xpath->evaluate('string(//div[contains(@class, "imptdt")][contains(text(), "Status")]/i)') ?: '-';
            $type = $xpath->evaluate('string(//div[contains(@class, "imptdt")][contains(text(), "Type")]/a)') ?: '-';
            $releaseYear = $xpath->evaluate('string(//div[contains(@class, "imptdt")][contains(text(), "Released")]/i)') ?: '-';
            $author = $xpath->evaluate('string(//div[contains(@class, "imptdt")][contains(text(), "Author")]/i)') ?: '-';
            $artist = $xpath->evaluate('string(//div[contains(@class, "imptdt")][contains(text(), "Artist")]/i)') ?: '-';
            $viewsText = $xpath->evaluate('string(//div[contains(@class, "imptdt")][contains(text(), "Views")]/i)');
            $views = preg_replace('/[^0-9]/', '', $viewsText) ?: 0;
            $synopsis = $xpath->evaluate('string(//div[@class="entry-content entry-content-single"]/p)') ?: 'No synopsis available';

            // ✅ Extract cover image
            $coverImageUrl = $xpath->evaluate('string(//div[@class="thumb"]//img/@src)');
            $coverPath = null;
            $bucket = null;

            if (!empty($coverImageUrl)) {
                $imageResponse = Http::get($coverImageUrl);
                if ($imageResponse->successful()) {
                    $extension = pathinfo($coverImageUrl, PATHINFO_EXTENSION);
                    $fileName = 'covers/' . $manga->id . '.' . $extension;

                    try {
                        // Store file and get bucket info
                        $storageInfo = $this->bucketManager->storeFile(
                            $fileName,
                            $imageResponse->body(),
                            ['visibility' => 'public']
                        );

                        // Save both the URL and bucket information
                        $coverPath = $storageInfo['url'];
                        $bucket = $storageInfo['bucket'];
                    } catch (\Exception $e) {
                        Log::error("Failed to store cover image for manga {$manga->title}: " . $e->getMessage());
                        throw $e;
                    }
                }
            }

            // ✅ Save to database (single updateOrCreate operation)
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
                    'cover' => $coverPath,
                    'bucket' => $bucket,
                ]
            );

            // ✅ Attach genres
            $genreElements = $xpath->query('//span[@class="mgen"]/a');
            $genres = [];
            foreach ($genreElements as $genre) {
                $genres[] = trim($genre->textContent);
            }

            if (!empty($genres)) {
                $genreIds = [];
                foreach ($genres as $genreName) {
                    $genre = Genre::firstOrCreate(['name' => $genreName], ['slug' => strtolower(str_replace(' ', '-', $genreName))]);
                    $genreIds[] = $genre->id;
                }
                $manga->genres()->sync($genreIds);
            }

            DB::commit(); // ✅ Commit Transaction if everything is OK

            $this->fetchChapterList($manga, $xpath);

            Log::info("Successfully updated: {$manga->title}");
        } catch (\Exception $e) {
            DB::rollBack(); // ❌ Rollback Transaction on Failure

            // ❌ Delete uploaded image if exists
            if (!empty($coverPath) && !empty($mangaDetail->bucket)) {
                $pathParts = parse_url($coverPath);
                $relativePath = ltrim($pathParts['path'], '/');
                $this->bucketManager->deleteFile($mangaDetail->bucket, $relativePath);
                Log::warning("Deleted uploaded cover for {$manga->title} due to failure.");
            }

            Log::error("Error processing {$manga->title}: " . $e->getMessage());
        }
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

    private function fetchChapterList(Manga $manga, DOMXPath $xpath)
    {
        try {
            $chapterElements = $xpath->query('//div[@class="eplister"]//li');
            if (!$chapterElements || $chapterElements->length === 0) {
                Log::warning("No chapters found for manga: {$manga->title}");
                return;
            }

            $chapters = [];
            foreach ($chapterElements as $element) {
                // Extract chapter data
                $chapterNum = $element->getAttribute('data-num');
                $linkElement = $xpath->evaluate('.//div[@class="eph-num"]/a', $element)->item(0);

                if (!$linkElement) {
                    continue;
                }

                $chapterTitle = $xpath->evaluate('string(.//span[@class="chapternum"])', $element);
                $chapterDate = $xpath->evaluate('string(.//span[@class="chapterdate"])', $element);
                $chapterUrl = $linkElement->getAttribute('href');

                // Create chapter slug from URL
                $chapterSlug = basename(rtrim($chapterUrl, '/'));

                // Parse chapter number, ensuring it's properly formatted
                $chapterNumber = intval($chapterNum);
                if ($chapterNumber === 0) {
                    // Fallback to extracting number from title if data-num is invalid
                    preg_match('/Chapter (\d+)/', $chapterTitle, $matches);
                    $chapterNumber = isset($matches[1]) ? intval($matches[1]) : 0;
                }

                if ($chapterNumber === 0) {
                    Log::warning("Invalid chapter number for {$manga->title}: {$chapterTitle}");
                    continue;
                }

                // Store chapter data
                MangaChapter::updateOrCreate(
                    [
                        'manga_id' => $manga->id,
                        'chapter_number' => $chapterNumber
                    ],
                    [
                        'title' => $chapterTitle,
                        'slug' => $chapterSlug,
                        'image' => json_encode([]), // Initialize empty image array, to be populated later
                    ]
                );

                Log::info("Processed chapter {$chapterNumber} for manga: {$manga->title}");
            }
        } catch (\Exception $e) {
            Log::error("Error processing chapters for {$manga->title}: " . $e->getMessage());
            throw $e; // Re-throw to be caught by the parent transaction
        }
    }
}
