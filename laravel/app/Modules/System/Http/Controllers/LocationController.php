<?php
// Controller: app/Modules/System/Http/Controllers/LocationController.php

namespace App\Modules\System\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocationController extends Controller
{
    /**
     * 獲取儲位列表
     */
    public function index()
    {
        $locations = Location::orderBy('id', 'desc')->paginate(15);
        return response()->json($locations);
    }

    /**
     * 創建新儲位
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:locations,code'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $location = Location::create($validated);

        return response()->json([
            'message' => '儲位創建成功',
            'location' => $location
        ], 201);
    }

    /**
     * 獲取單個儲位詳情
     */
    public function show(Location $location)
    {
        return response()->json($location);
    }

    /**
     * 更新儲位
     */
    public function update(Request $request, Location $location)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('locations', 'code')->ignore($location->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $location->update($validated);

        return response()->json([
            'message' => '儲位更新成功',
            'location' => $location
        ]);
    }

    /**
     * 刪除儲位
     */
    public function destroy(Location $location)
    {
        // 考慮是否應檢查是否有庫存紀錄再刪除
        $location->delete();

        return response()->json(['message' => '儲位刪除成功'], 204);
    }

    /**
     * 啟用/停用儲位
     */
    public function toggleActive(Location $location)
    {
        $location->is_active = !$location->is_active;
        $location->save();

        $status = $location->is_active ? '啟用' : '停用';

        return response()->json([
            'message' => "儲位 {$location->code} 已切換為 {$status}",
            'location' => $location
        ]);
    }
}
