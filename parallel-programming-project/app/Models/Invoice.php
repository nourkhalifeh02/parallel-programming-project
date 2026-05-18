<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected $fillable = ['user_id', 'total_cost'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
