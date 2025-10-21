<?php
// Model: laravel/app/Models/InboundOrder.php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class InboundOrder extends Model
{
    use HasFactory;
    protected $fillable = ['reference_no', 'supplier_id', 'user_id', 'status', 'expected_arrival_date'];
    public function items() { return $this->hasMany(InboundItem::class); }
    public function supplier() { return $this->belongsTo(Supplier::class); }
}
