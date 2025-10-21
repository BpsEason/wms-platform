<?php
// Model: laravel/app/Models/OutboundOrder.php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class OutboundOrder extends Model
{
    use HasFactory;
    protected $fillable = ['reference_no', 'user_id', 'status'];
    public function items() { return $this->hasMany(OutboundItem::class); }
    public function pickingOrder() { return $this->hasOne(PickingOrder::class, 'outbound_id'); }
}
