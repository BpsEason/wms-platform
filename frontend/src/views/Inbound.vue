<!-- File: frontend/src/views/Inbound.vue -->
<script setup>
import { ref } from 'vue';

const message = ref('入庫作業頁面內容待實作...');
</script>

<template>
    <div class="inbound-view p-6 bg-green-50 min-h-full">
        <h1 class="text-3xl font-bold mb-6 text-green-800">📥 入庫作業 (Inbound)</h1>
        
        <div class="bg-white p-6 rounded-lg shadow-lg">
            <p class="text-lg text-gray-700 mb-4">WMS 入庫流程：</p>
            <ol class="list-decimal list-inside space-y-2 text-gray-600">
                <li>創建入庫單 (Purchase Order/Inbound Receipt)。</li>
                <li>執行收貨 (Receiving) - 掃描 SKU 與數量。</li>
                <li>執行上架 (Putaway) - 掃描 SKU 與目標儲位。</li>
                <li>確認入庫完成，更新庫存。</li>
            </ol>
            <p class="mt-6 p-4 bg-green-100 border-l-4 border-green-500">{{ message }}</p>
        </div>
    </div>
</template>
