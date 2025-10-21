<?php
// Model: app/Models/InventoryTransaction.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
    protected $casts = [
        "context" => "json",
    ];
        // V2 修正: 新增 context 欄位，用於記錄交易的額外資訊
        "context",
        'product_id',
        'location_id',
        'user_id',
        'quantity_change',
        'current_quantity',
        'type',
        'reference_type',
        'reference_id',
        'note',
    ];

    protected $casts = [
        'quantity_change' => 'decimal:2',
        'current_quantity' => 'decimal:2',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
