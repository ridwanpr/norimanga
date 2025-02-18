<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Manga;

class FetchMangaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $title;
    protected string $slug;

    public function __construct(string $title, string $slug)
    {
        $this->title = $title;
        $this->slug = $slug;
    }

    public function handle(): void
    {
        try {
            DB::beginTransaction();
            $manga = Manga::updateOrCreate(
                ['slug' => $this->slug],
                ['title' => $this->title, 'slug' => $this->slug]
            );

            if ($manga->wasRecentlyCreated) {
                Log::info("New manga added: {$this->title}");
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error processing manga {$this->title}: " . $e->getMessage());
        }
    }
}
