<?php
// Model: laravel/app/Models/PickingOrder.php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class PickingOrder extends Model
{
    use HasFactory;
    protected $fillable = ['outbound_id', 'user_id', 'status'];
    public function items() { return $this->hasMany(PickingItem::class); }
    public function outbound() { return $this->belongsTo(OutboundOrder::class, 'outbound_id'); }
}
