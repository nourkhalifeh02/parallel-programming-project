<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Benchmark extends Model
{
    protected $fillable = [
        'type',
        'name',
        'method',
        'uri',
        'job_class',
        'cpu_time',
        'ram_usage',
        'peak_ram_usage',
        'connection_time',
        'response_time',
        'total_time',
    ];
}
