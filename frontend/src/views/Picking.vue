<!-- File: frontend/src/views/Picking.vue -->
<script setup>
import { ref } from 'vue';

const message = ref('揀貨作業頁面內容待實作...');
</script>

<template>
    <div class="picking-view p-6 bg-yellow-50 min-h-full">
        <h1 class="text-3xl font-bold mb-6 text-yellow-800">🚚 揀貨作業 (Picking)</h1>
        
        <div class="bg-white p-6 rounded-lg shadow-lg">
            <p class="text-lg text-gray-700 mb-4">WMS 出庫/揀貨流程：</p>
            <ol class="list-decimal list-inside space-y-2 text-gray-600">
                <li>創建出庫單 (Sales Order/Outbound Shipment)。</li>
                <li>系統分配揀貨任務 (Picking Task) - 指定儲位與數量。</li>
                <li>執行揀貨 - 掃描儲位並確認揀貨數量。</li>
                <li>執行包裝與出庫 (Packing & Shipping)。</li>
                <li>發送出貨通知 (Queue Job)。</li>
            </ol>
            <p class="mt-6 p-4 bg-yellow-100 border-l-4 border-yellow-500">{{ message }}</p>
        </div>
    </div>
</template>
