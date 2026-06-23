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
        'peak_ram_usage',
    ];
}
