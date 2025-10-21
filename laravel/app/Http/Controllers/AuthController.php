<?php
// Controller: laravel/app/Http/Controllers/AuthController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * 用戶登入並創建 token
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'nullable|string|max:255', // 用於區分不同設備的 token
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['提供的憑證不符我們的紀錄。'],
            ]);
        }

        $user = Auth::user();
        
        // 從資料庫中讀取用戶的 abilities 陣列
        $abilities = $user->abilities ?? [];
        
        // 創建 Sanctum Token，並賦予用戶擁有的所有 abilities
        $token = $user->createToken($request->device_name ?? 'default-token', $abilities)->plainTextToken;

        return response()->json([
            'message' => '登入成功',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'abilities' => $abilities, // 回傳給前端用於權限判斷
            ],
            'token' => $token,
        ]);
    }

    /**
     * 獲取當前登入用戶的資訊 (用於驗證 token 狀態)
     */
    public function me(Request $request)
    {
        $user = $request->user();

        // 檢查用戶是否已登入 (透過 token)
        if (!$user) {
            return response()->json(['message' => '未認證或 Token 無效'], 401);
        }

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'abilities' => $user->abilities ?? [],
        ]);
    }

    /**
     * 登出 (撤銷當前 token)
     */
    public function logout(Request $request)
    {
        // 撤銷當前使用的 Token
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => '登出成功，Token 已撤銷']);
    }
}
