<?php

namespace App\Jobs;

use DOMXPath;
use DOMDocument;
use Illuminate\Bus\Queueable;
use App\Services\BucketManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class FetchChapterImagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes timeout
    public $tries = 3; // Number of retries
    private $chapter;
    private $bucketManager;

    public function __construct($chapter)
    {
        $this->chapter = $chapter;
        $this->bucketManager = new BucketManager();
    }

    public function handle()
    {
        $url = "https://manhwaindo.one/{$this->chapter->slug}";
        Log::info("Fetching images for chapter: {$this->chapter->title} from {$url}");

        try {
            $response = Http::withHeaders([
                'User-Agent' => $this->getRandomUserAgent()
            ])->get($url);

            if (!$response->successful()) {
                Log::error("Failed to fetch chapter page: {$url}");
                return;
            }

            $html = $response->body();
            $dom = new DOMDocument();
            @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
            $xpath = new DOMXPath($dom);

            // Find all images within #readerarea > p > img
            $imageNodes = $xpath->query('//div[@id="readerarea"]/p/img');

            if (!$imageNodes || $imageNodes->length === 0) {
                Log::warning("No images found for chapter: {$this->chapter->title}");
                return;
            }

            DB::beginTransaction();
            try {
                $imageUrls = [];

                foreach ($imageNodes as $index => $imageNode) {
                    $imageUrl = $imageNode->getAttribute('src');
                    if (empty($imageUrl)) {
                        continue;
                    }

                    // Download image
                    $imageResponse = Http::timeout(30)->get($imageUrl);
                    if (!$imageResponse->successful()) {
                        Log::warning("Failed to download image {$index} for chapter {$this->chapter->title}");
                        continue;
                    }

                    $extension = pathinfo($imageUrl, PATHINFO_EXTENSION) ?: 'jpg';
                    $fileName = "chapters/{$this->chapter->manga_id}/{$this->chapter->id}/{$index}.{$extension}";

                    try {
                        // Store file using BucketManager
                        $storageInfo = $this->bucketManager->storeFile(
                            $fileName,
                            $imageResponse->body(),
                            ['visibility' => 'public']
                        );

                        $imageUrls[] = $storageInfo['url'];

                        // Update chapter with the latest bucket used
                        $this->chapter->bucket = $storageInfo['bucket'];

                        Log::info("Stored image {$index} for chapter {$this->chapter->title}");

                        // Add small delay between image downloads
                        usleep(500000); // 0.5 second delay
                    } catch (\Exception $e) {
                        Log::error("Failed to store image {$index} for chapter {$this->chapter->title}: " . $e->getMessage());
                        throw $e;
                    }
                }

                // Update chapter with stored images
                $this->chapter->image = json_encode($imageUrls);
                $this->chapter->save();

                DB::commit();
                Log::info("Successfully processed {$this->chapter->title} with " . count($imageUrls) . " images");
            } catch (\Exception $e) {
                DB::rollBack();

                // Clean up any stored images on failure
                if (!empty($imageUrls) && !empty($this->chapter->bucket)) {
                    foreach ($imageUrls as $url) {
                        $pathParts = parse_url($url);
                        $relativePath = ltrim($pathParts['path'], '/');
                        $this->bucketManager->deleteFile($this->chapter->bucket, $relativePath);
                    }
                }

                throw $e;
            }
        } catch (\Exception $e) {
            Log::error("Error processing chapter {$this->chapter->title}: " . $e->getMessage());
            throw $e;
        }
    }

    private function getRandomUserAgent()
    {
        $userAgents = [
            "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36",
            "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36",
            "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0",
            "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36"
        ];
        return $userAgents[array_rand($userAgents)];
    }

    public function failed(\Throwable $exception)
    {
        Log::error("FetchChapterImagesJob failed for chapter {$this->chapter->title}: " . $exception->getMessage());
    }
}
