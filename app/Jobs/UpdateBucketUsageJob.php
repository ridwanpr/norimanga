<?php

namespace App\Jobs;

use App\Models\BucketUsage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class UpdateBucketUsageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        foreach (range(3, 5) as $i) {
            $bucket = "s{$i}";
            try {
                $files = Storage::disk($bucket)->allFiles();
                $totalBytes = array_sum(array_map(fn($file) => Storage::disk($bucket)->size($file), $files));

                BucketUsage::updateOrCreate(
                    ['bucket_name' => $bucket],
                    ['total_bytes' => $totalBytes]
                );
            } catch (\Exception $e) {
                Log::error("Failed to update bucket usage for {$bucket}: " . $e->getMessage());
            }
        }
    }
}
