<?php
// Model: app/Models/Inventory.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inventory extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'location_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
    ];

    // 關聯商品
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // 關聯儲位
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
