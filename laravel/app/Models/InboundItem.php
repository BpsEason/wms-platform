<?php
// Model: laravel/app/Models/InboundItem.php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class InboundItem extends Model
{
    use HasFactory;
    protected $fillable = ['inbound_order_id', 'product_id', 'qty_expected', 'qty_received'];
    public function order() { return $this->belongsTo(InboundOrder::class); }
}
