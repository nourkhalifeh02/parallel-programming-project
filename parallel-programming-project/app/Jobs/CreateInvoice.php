<?php

namespace App\Jobs;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CreateInvoice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $userId;

    public float $totalCost;

    public function __construct(int $userId, float $totalCost)
    {
        $this->userId = $userId;
        $this->totalCost = $totalCost;
    }

    public function handle(): void
    {
        Invoice::create([
            'user_id' => $this->userId,
            'total_cost' => $this->totalCost,
        ]);
    }
}
