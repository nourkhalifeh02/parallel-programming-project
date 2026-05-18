<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class AdaptiveResourceManager
{
    private string $limitKey   = 'capacity:current_limit';
    private string $counterKey = 'capacity:active_count';
    private string $metricsKey = 'capacity:metrics';

    private int $minLimit     = 5;
    private int $maxLimit     = 100;
    private int $defaultLimit = 20;

    public function executeWithCapacityControl(callable $operation): mixed
    {
        $limit   = $this->getCurrentLimit();
        $current = Redis::incr($this->counterKey);

        if ($current > $limit) {
            Redis::decr($this->counterKey);
            $this->recordRejection(); 
            throw new CapacityExceededException($current, $limit);
        }

        $start = microtime(true);

        try {
            $result = $operation();
            $this->recordSuccess(microtime(true) - $start); 
            return $result;
        } catch (\Throwable $e) {
            $this->recordFailure(); 
            throw $e;
        } finally {
            Redis::decr($this->counterKey);
        }
    }

    private function recordSuccess(float $responseTime): void
    {
        Redis::lpush($this->metricsKey, json_encode([
            'status' => 'success',
            'time'   => $responseTime,
            'ts'     => now()->timestamp,
        ]));
        Redis::ltrim($this->metricsKey, 0, 99); // keep last 100 records

        // Slow increase: +1 when things are going well
        if ($responseTime < 1.0) {
            $newLimit = min($this->maxLimit, $this->getCurrentLimit() + 1);
            Redis::set($this->limitKey, $newLimit);
        }
    }

    private function recordRejection(): void
    {
        // Fast decrease: cut by 25% when at capacity
        $newLimit = max($this->minLimit, (int)($this->getCurrentLimit() * 0.75));
        Redis::set($this->limitKey, $newLimit);
    }

    private function recordFailure(): void
    {
        // Moderate decrease: cut by 10% on errors
        $newLimit = max($this->minLimit, (int)($this->getCurrentLimit() * 0.90));
        Redis::set($this->limitKey, $newLimit);
    }

    public function getCurrentLimit(): int
    {
        return (int)(Redis::get($this->limitKey) ?? $this->defaultLimit);
    }
}