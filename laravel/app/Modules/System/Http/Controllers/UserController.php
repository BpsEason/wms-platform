<?php
// Controller: laravel/app/Modules/System/Http/Controllers/UserController.php (V2 - 權限列表更新)

namespace App\Modules\System\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    // V2 修正: 完整的可用權限列表，新增 WMS 核心操作權限
    const AVAILABLE_ABILITIES = [
        'system-admin',         // 系統管理員：完整後台訪問權限
        'inventory-query',      // 庫存：查詢權限
        'inventory-adjust',     // 庫存：調整權限 (僅供管理員或特殊用戶)

        // 核心 WMS 流程權限
        'inbound-create',       // 入庫：創建入庫單
        'inbound-putaway',      // 入庫：執行上架操作 (V2 修正: 新增)
        
        'picking-create',       // 揀貨：創建揀貨單 (通常由系統觸發)
        'picking-scan',         // 揀貨：執行揀貨掃描/確認 (V2 修正: 新增)
        
        'outbound-create',      // 出庫：創建出庫單
        'outbound-ship',        // 出庫：執行出貨操作 (V2 修正: 新增)
    ];

    /**
     * 取得所有可用的權限列表。
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllAvailableAbilities()
    {
        return response()->json(self::AVAILABLE_ABILITIES);
    }
    
    /**
     * 取得所有用戶列表 (Admin 權限)
     */
    public function index(Request $request)
    {
        return response()->json(User::paginate(20));
    }

    /**
     * 創建新用戶 (Admin 權限)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'abilities' => 'nullable|array',
            'abilities.*' => [Rule::in(self::AVAILABLE_ABILITIES)],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => \Hash::make($validated['password']),
        ]);
        
        if (!empty($validated['abilities'])) {
            $user->abilities()->attach($validated['abilities']);
        }

        return response()->json(['message' => 'User created successfully', 'user' => $user], 201);
    }
    
    /**
     * 更新用戶資訊 (Admin 權限)
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => [
                'sometimes', 
                'required', 
                'string', 
                'email', 
                'max:255', 
                Rule::unique('users')->ignore($user->id)
            ],
            'password' => 'nullable|string|min:6',
        ]);

        $user->name = $validated['name'] ?? $user->name;
        $user->email = $validated['email'] ?? $user->email;
        if (isset($validated['password']) && !empty($validated['password'])) {
            $user->password = \Hash::make($validated['password']);
        }
        $user->save();

        return response()->json(['message' => 'User updated successfully', 'user' => $user]);
    }

    /**
     * 同步用戶權限 (Admin 權限)
     */
    public function syncAbilities(Request $request, User $user)
    {
        $validated = $request->validate([
            'abilities' => 'required|array',
            'abilities.*' => [Rule::in(self::AVAILABLE_ABILITIES)],
        ]);

        // 由於我們使用 abilities table，這裡的 detach/attach 邏輯取決於您的實現，這裡假設您有 User->abilities() 關聯
        // $user->abilities()->sync($validated['abilities']);
        // 假設 user Model 有 setAbilities 函式來處理 Sanctum 的 abilities 欄位
        $user->abilities = implode(',', $validated['abilities']);
        $user->save();

        return response()->json(['message' => 'User abilities synchronized successfully']);
    }

    /**
     * 刪除用戶 (Admin 權限)
     */
    public function destroy(User $user)
    {
        // 避免刪除自己
        if ($user->id === auth()->id()) {
            return response()->json(['message' => '無法刪除當前登入的使用者'], 403);
        }
        $user->delete();
        return response()->json(['message' => 'User deleted successfully']);
    }

}
