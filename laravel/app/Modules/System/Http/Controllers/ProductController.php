<?php
// Controller: app/Modules/System/Http/Controllers/ProductController.php

namespace App\Modules\System\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    /**
     * 獲取商品列表
     */
    public function index()
    {
        $products = Product::orderBy('id', 'desc')->paginate(15);
        return response()->json($products);
    }

    /**
     * 創建新商品
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:50', 'unique:products,sku'],
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $product = Product::create($validated);

        return response()->json([
            'message' => '商品創建成功',
            'product' => $product
        ], 201);
    }

    /**
     * 獲取單個商品詳情
     */
    public function show(Product $product)
    {
        return response()->json($product);
    }

    /**
     * 更新商品
     */
    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:50', Rule::unique('products', 'sku')->ignore($product->id)],
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $product->update($validated);

        return response()->json([
            'message' => '商品更新成功',
            'product' => $product
        ]);
    }

    /**
     * 刪除商品
     */
    public function destroy(Product $product)
    {
        // 考慮是否應檢查是否有庫存紀錄再刪除
        $product->delete();

        return response()->json(['message' => '商品刪除成功'], 204);
    }
}
