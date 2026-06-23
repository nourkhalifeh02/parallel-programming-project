<?php

use App\Jobs\GenerateDailyReport;
use App\Jobs\SyncProductInventoryToDatabase;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    dispatch(new GenerateDailyReport);
})->dailyAt('00:00');

Schedule::job(new SyncProductInventoryToDatabase)->everyMinute();
