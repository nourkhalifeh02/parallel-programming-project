<?php

namespace App\Providers;

use App\Models\Benchmark;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class BenchmarkServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Event::listen(JobProcessing::class, function (JobProcessing $event) {
            $event->job->benchmarkStartTime = microtime(true);
            $event->job->benchmarkStartMemory = memory_get_usage(true);
            $event->job->benchmarkStartPeakMemory = memory_get_peak_usage(true);
        });

        Event::listen(JobProcessed::class, function (JobProcessed $event) {
            $endTime = microtime(true);
            $endMemory = memory_get_usage(true);
            $endPeakMemory = memory_get_peak_usage(true);

            $startTime = $event->job->benchmarkStartTime ?? $endTime;
            $startMemory = $event->job->benchmarkStartMemory ?? $endMemory;
            $startPeakMemory = $event->job->benchmarkStartPeakMemory ?? $endPeakMemory;

            $totalTime = ($endTime - $startTime) * 1000;
            $ramUsage = ($endMemory - $startMemory) / 1024 / 1024;
            $peakRamUsage = $endPeakMemory / 1024 / 1024;

            Benchmark::create([
                'type' => 'job',
                'name' => $event->job->resolveName(),
                'job_class' => $event->job->resolveName(),
                'cpu_time' => $totalTime,
                'ram_usage' => $ramUsage,
                'peak_ram_usage' => $peakRamUsage,
                'connection_time' => 0,
                'response_time' => $totalTime,
                'total_time' => $totalTime,
            ]);
        });
    }
}
