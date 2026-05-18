<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'total_sales',
        'total_orders',
        'type',
    ];

    protected $casts = [
        'date' => 'date',
        'total_sales' => 'decimal:2',
    ];
}
