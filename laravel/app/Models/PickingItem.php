<?php
// Model: laravel/app/Models/PickingItem.php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class PickingItem extends Model
{
    use HasFactory;
    protected $table = 'picking_items';
    protected $fillable = ['picking_order_id', 'product_id', 'source_location_id', 'qty_to_pick', 'qty_picked'];
    public function order() { return $this->belongsTo(PickingOrder::class); }
}
