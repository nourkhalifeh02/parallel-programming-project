<?php

namespace App\Http\Middleware;

use App\Models\Benchmark;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BenchmarkMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        $startPeakMemory = memory_get_peak_usage(true);

        $response = $next($request);

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        $endPeakMemory = memory_get_peak_usage(true);

        $totalTime = ($endTime - $startTime) * 1000;
        $ramUsage = ($endMemory - $startMemory) / 1024 / 1024;
        $peakRamUsage = $endPeakMemory / 1024 / 1024;

        Benchmark::create([
            'type' => 'http',
            'name' => $request->path(),
            'method' => $request->method(),
            'uri' => $request->fullUrl(),
            'cpu_time' => $totalTime,
            'ram_usage' => $ramUsage,
            'peak_ram_usage' => $peakRamUsage,
            'connection_time' => 0,
            'response_time' => $totalTime,
            'total_time' => $totalTime,
        ]);

        return $response;
    }
}
