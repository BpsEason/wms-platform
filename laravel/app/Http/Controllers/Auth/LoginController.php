<?php
// Controller: app/Http/Controllers/Auth/LoginController.php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /**
     * 處理用戶登入並發行 Sanctum Token
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'required',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['提供的憑證與我們的記錄不符。'],
            ]);
        }
        
        $user = Auth::user();
        
        // 獲取用戶的 abilities (來自 User Model 的 casts 或資料庫欄位)
        $abilities = $user->abilities ?? [];
        
        // 確保至少包含一個基本能力，以便 Sanctum 檢查
        if (empty($abilities)) {
             $abilities[] = 'default-access';
        }
        
        // 創建 Token，並帶上用戶的 abilities
        $token = $user->createToken($request->device_name, $abilities)->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'abilities' => $abilities,
            ]
        ]);
    }

    /**
     * 處理用戶登出 (撤銷當前 Token)
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => '登出成功。'], 200);
    }
}
