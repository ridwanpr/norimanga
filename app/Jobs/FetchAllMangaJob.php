<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Jobs\FetchMangaJob;
use DOMDocument;
use DOMXPath;

class FetchAllMangaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        //
    }

    public function handle(): void
    {
        try {
            $userAgents = [
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/88.0.4324.96 Safari/537.36',
            ];
            $randomUserAgent = $userAgents[array_rand($userAgents)];

            $response = Http::withHeaders([
                'User-Agent' => $randomUserAgent
            ])->get('https://manhwaindo.one/series/list-mode/');

            if ($response->successful()) {
                Log::info('Successfully fetched the webpage');
                Log::info('HTML: ' . $response->body());
                
                $dom = new DOMDocument();
                @$dom->loadHTML($response->body(), LIBXML_NOERROR | LIBXML_NOWARNING);
                $xpath = new DOMXPath($dom);
                $mangaLinks = $xpath->query("//div[@class='soralist']//div[@class='blix']//li/a");

                foreach ($mangaLinks as $link) {
                    $title = trim($link->textContent);
                    $url = $link->getAttribute('href');
                    $slug = str_replace(['https://manhwaindo.one/series/', '/'], '', $url);

                    FetchMangaJob::dispatch($title, $slug);
                }

                Log::info("Dispatched jobs for " . $mangaLinks->length . " manga titles.");
            } else {
                Log::error('Failed to fetch the webpage: ' . $response->status());
            }
        } catch (\Exception $e) {
            Log::error('Error: ' . $e->getMessage());
        }
    }
}
