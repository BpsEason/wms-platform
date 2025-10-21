<?php
// Model: app/Models/User.php - 已為 WMS ACL 調整

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'abilities', // 新增 abilities
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'abilities' => 'array', // 轉換為陣列
    ];
    
    /**
     * 檢查用戶是否擁有指定的權限字串
     */
    public function hasAbility(string $ability): bool
    {
        $abilities = $this->abilities ?? [];
        return in_array($ability, $abilities);
    }
    
    /**
     * 檢查用戶是否是系統管理員
     */
    public function isAdmin(): bool
    {
        return $this->hasAbility('system-admin');
    }
}
