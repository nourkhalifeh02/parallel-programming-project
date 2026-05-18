<?php

namespace App\Jobs;

use App\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class OrderCompletedNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $userId;

    public float $orderTotal;

    public function __construct(int $userId, float $orderTotal)
    {
        $this->userId = $userId;
        $this->orderTotal = $orderTotal;
    }

    public function handle(): void
    {
        Notification::create([
            'user_id' => $this->userId,
            'type' => 'order_completed',
            'message' => 'Your order was completed successfully! Total: $'.number_format($this->orderTotal, 2),
        ]);
    }
}
