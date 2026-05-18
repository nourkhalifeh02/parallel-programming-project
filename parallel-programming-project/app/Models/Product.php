<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
        'inventory',
        'image',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function carts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'carts')->withPivot('quantity');
    }
}
