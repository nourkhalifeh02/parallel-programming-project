<?php

namespace App\Jobs;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class SyncProductInventoryToDatabase implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // Atomically get and clear the dirty product IDs from the set
        $productIds = Redis::connection('cache')->smembers('products:sync_set');
        if (empty($productIds)) {
            return;
        }

        // Clear the set for this batch
        Redis::connection('cache')->srem('products:sync_set', ...$productIds);

        foreach ($productIds as $productId) {
            $cacheKey = "product:{$productId}";
            $cached = Cache::get($cacheKey);

            if ($cached === null) {
                // Cache expired or missing; re-add to sync set to retry next minute
                // This prevents MySQL from remaining stale if the cache was evicted mid-flight
                Redis::connection('cache')->sadd('products:sync_set', $productId);
                Log::warning("Product {$productId} not found in cache during sync. Re-added to sync set.");

                continue;
            }

            try {
                Product::where('id', $productId)->update([
                    'inventory' => $cached['inventory'],
                    'version' => $cached['version'] ?? 0,
                    'request_counter' => $cached['request_counter'] ?? 0,
                ]);
            } catch (\Exception $e) {
                Log::error("Failed to sync product {$productId} to database: ".$e->getMessage());
                // Re-add to sync set to retry
                Redis::connection('cache')->sadd('products:sync_set', $productId);
            }
        }
    }
}
