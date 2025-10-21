<!-- File: frontend/src/views/Inventory.vue -->
<script setup>
import { ref, onMounted } from 'vue';
import { useAuthStore } from '@/stores/auth'; 
import apiClient from '@/lib/apiClient'; 
import { useRouter } from 'vue-router'; 

const router = useRouter();
const inventoryData = ref([]);
const loading = ref(true);
const authStore = useAuthStore();
const paginationMeta = ref({});
const currentPage = ref(1);
const errorMessage = ref('');

// Idempotency State & Data
const isAdjusting = ref(false); 
const adjustSku = ref('SKU-A001');
const adjustQuantity = ref(1);
const adjustLocation = ref('A1-01-01');

// 產生 UUID (用於 X-Idempotency-Key)
const generateUUID = () => {
    // 簡單的 UUID v4 模擬
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
        const r = Math.random() * 16 | 0, v = c === 'x' ? r : (r & 0x3 | 0x8);
        return v.toString(16);
    });
};

// =================================================================
// 庫存查詢 (已包含分頁邏輯)
// =================================================================
async function fetchInventory(page = 1) {
    loading.value = true;
    errorMessage.value = '';
    
    try {
        // 使用 apiClient 實例，該實例會自動處理 Bearer Token 和 401 Refresh
        const response = await apiClient.get('/inventory', {
            params: {
                page: page
            }
        }); 
        
        inventoryData.value = response.data.data || [];
        // 假設後端返回的 Laravel 分頁 meta 結構
        paginationMeta.value = response.data.meta || { current_page: 1, last_page: 1, total: 0 }; 
        currentPage.value = page;

    } catch (error) {
        // 401 錯誤會被攔截器處理，這裡只需處理其他錯誤
        console.error("Failed to fetch inventory:", error);
        if (error.response?.status !== 401) {
            errorMessage.value = `載入庫存失敗: ${error.response?.data?.message || error.message || '連線錯誤'}`;
        }
    } finally {
        loading.value = false;
    }
}

// =================================================================
// 庫存調整 (加入防重複提交與 Idempotency Key)
// =================================================================
async function handleInventoryAdjust() {
    if (isAdjusting.value) return; 

    // 1. 設定鎖定狀態 (防重複提交)
    isAdjusting.value = true;
    errorMessage.value = '';

    // 2. 產生 Idempotency Key (唯一請求識別碼)
    const idempotencyKey = generateUUID();

    try {
        // 3. 執行寫入 API 呼叫 (假設後端 /inventory/adjust 路由存在)
        await apiClient.post('/inventory/adjust', {
            sku: adjustSku.value,
            location_code: adjustLocation.value,
            quantity: adjustQuantity.value
        }, {
            // 4. 在 Header 中夾帶 X-Idempotency-Key
            headers: {
                'X-Idempotency-Key': idempotencyKey
            }
        });

        // 調整成功後刷新當前頁面
        await fetchInventory(currentPage.value);

    } catch (error) {
        console.error("Inventory adjustment failed:", error);
        
        const errorMsg = error.response?.data?.message || error.message || '未知錯誤';
        
        // 假設 409 是後端針對重複 Idempotency Key 的標準響應
        errorMessage.value = error.response?.status === 409 
                             ? `調整失敗: 請求重複 (Key: ${idempotencyKey.substring(0, 8)}...)`
                             : `調整失敗: ${errorMsg}`;
    } finally {
        // 5. 解除鎖定狀態
        isAdjusting.value = false;
    }
}


// 分頁控制
const goToNextPage = () => {
    if (paginationMeta.value.current_page < paginationMeta.value.last_page) {
        fetchInventory(currentPage.value + 1);
    }
};

const goToPrevPage = () => {
    if (paginationMeta.value.current_page > 1) {
        fetchInventory(currentPage.value - 1);
    }
};

onMounted(() => {
    fetchInventory();
});
</script>

<template>
    <div class="inventory-view p-6 bg-gray-50 min-h-screen">
        <!-- 標題與用戶資訊由 App.vue 處理，這裡專注內容 -->
        
        <!-- 錯誤區塊 -->
        <div v-if="errorMessage" class="mb-4 p-4 bg-red-100 border-l-4 border-red-500 text-red-700 rounded shadow-md">
             錯誤: {{ errorMessage }}
        </div>

        <!-- 庫存調整區塊 (示範 Idempotency) -->
        <div class="bg-white p-6 rounded-lg shadow-lg mb-8">
            <h2 class="text-xl font-semibold mb-4 text-blue-700">庫存調整操作 (Idempotency Demo)</h2>
            <div class="grid grid-cols-4 gap-4 items-end">
                <div class="col-span-1">
                    <label class="block text-sm font-medium text-gray-700">SKU</label>
                    <input type="text" v-model="adjustSku" placeholder="SKU" class="p-2 border rounded w-full" :disabled="isAdjusting">
                </div>
                <div class="col-span-1">
                     <label class="block text-sm font-medium text-gray-700">數量 (+/-)</label>
                    <input type="number" v-model="adjustQuantity" placeholder="數量 (+/-)" class="p-2 border rounded w-full" :disabled="isAdjusting">
                </div>
                <div class="col-span-1">
                    <label class="block text-sm font-medium text-gray-700">儲位</label>
                    <input type="text" v-model="adjustLocation" placeholder="儲位" class="p-2 border rounded w-full" :disabled="isAdjusting">
                </div>
                <button 
                    @click="handleInventoryAdjust" 
                    :disabled="isAdjusting"
                    class="py-2 bg-green-500 hover:bg-green-600 text-white font-bold rounded transition duration-150 disabled:bg-gray-400 disabled:cursor-wait">
                    <span v-if="isAdjusting">調整中...</span>
                    <span v-else>執行調整 (防重複提交)</span>
                </button>
            </div>
            <p class="text-xs mt-2 text-center text-gray-500">
                此按鈕加入了防重複提交鎖定狀態，並在請求中附加 X-Idempotency-Key。
            </p>
        </div>


        <!-- 庫存列表區塊 -->
        <div class="bg-white p-4 rounded-lg shadow-md">
            <h2 class="text-xl font-semibold mb-4">庫存資料 (共 {{ paginationMeta.total || 0 }} 筆)</h2>
            
            <!-- 載入中狀態 (Loading State) -->
            <div v-if="loading" class="text-center mt-8 p-4 bg-blue-100 rounded-md shadow-md text-blue-800">
                <svg class="animate-spin h-5 w-5 mr-3 inline-block" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" class="opacity-25"></circle><path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" class="opacity-75"></path></svg>
                載入中... (第 {{ currentPage }} 頁)
            </div>
            
            <!-- 資料列表 -->
            <ul v-else class="space-y-3">
                <!-- 空狀態 (Empty State) -->
                <li v-if="inventoryData.length === 0" class="text-gray-500 p-4 border border-dashed rounded text-center">
                    目前沒有庫存資料。
                </li>
                <li v-for="item in inventoryData" :key="item.id" 
                    class="p-3 border border-gray-200 rounded-md hover:bg-indigo-50 transition duration-100 grid grid-cols-4 gap-4 text-sm">
                    <p><strong>SKU:</strong> {{ item.sku || 'N/A' }}</p>
                    <p><strong>儲位:</strong> {{ item.location_code || 'N/A' }}</p>
                    <p><strong>數量:</strong> <span :class="{'text-red-500': item.quantity <= 0, 'text-green-600': item.quantity > 0}" class="font-mono font-bold">{{ item.quantity }}</span></p>
                    <p class="text-sm text-gray-500"><strong>更新時間:</strong> {{ item.updated_at ? new Date(item.updated_at).toLocaleTimeString() : 'N/A' }}</p>
                </li>
            </ul>

            <!-- 分頁控制 -->
            <div v-if="paginationMeta.last_page > 1" class="flex justify-between items-center mt-6 pt-4 border-t border-gray-200">
                <p class="text-sm text-gray-600">
                    目前在第 {{ currentPage }} 頁，共 {{ paginationMeta.last_page }} 頁 (總計 {{ paginationMeta.total || 0 }} 筆)。
                </p>
                <div class="space-x-2">
                    <button 
                        @click="goToPrevPage" 
                        :disabled="currentPage === 1 || loading"
                        class="px-3 py-1 border rounded-md text-gray-700 bg-gray-100 hover:bg-gray-200 disabled:opacity-50 transition duration-150">
                        上一頁
                    </button>
                    <button 
                        @click="goToNextPage" 
                        :disabled="currentPage === paginationMeta.last_page || loading"
                        class="px-3 py-1 border rounded-md text-gray-700 bg-gray-100 hover:bg-gray-200 disabled:opacity-50 transition duration-150">
                        下一頁
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
<style>
/* ... (Tailwind styles assumed) ... */
</style>
