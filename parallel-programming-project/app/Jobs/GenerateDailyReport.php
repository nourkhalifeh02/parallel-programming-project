<?php

namespace App\Jobs;

use App\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class GenerateDailyReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const BATCH_SIZE = 10000;

    public ?string $date;

    public function __construct(?string $date = null)
    {
        $this->date = $date ?? now()->toDateString();
    }

    public function handle(): void
    {
        $date = $this->date;

        $totalOrders = DB::table('orders')
            ->whereDate('created_at', $date)
            ->where('status', 'completed')
            ->count();

        $totalSales = 0;
        $offset = 0;

        while ($offset < $totalOrders) {
            $batch = DB::table('orders')
                ->whereDate('created_at', $date)
                ->where('status', 'completed')
                ->select('total')
                ->offset($offset)
                ->limit(self::BATCH_SIZE)
                ->get();

            foreach ($batch as $order) {
                $totalSales += $order->total;
            }

            $offset += self::BATCH_SIZE;
        }

        Report::updateOrCreate(
            ['date' => $date, 'type' => 'daily_sales_batch'],
            [
                'total_sales' => $totalSales,
                'total_orders' => $totalOrders,
            ]
        );
    }
}
