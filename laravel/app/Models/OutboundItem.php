<?php
// Model: laravel/app/Models/OutboundItem.php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class OutboundItem extends Model
{
    use HasFactory;
    protected $fillable = ['outbound_order_id', 'product_id', 'qty_requested', 'qty_shipped'];
    public function order() { return $this->belongsTo(OutboundOrder::class); }
}
