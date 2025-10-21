<!-- File: frontend/src/views/Login.vue -->
<script setup>
import { ref } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useRouter } from 'vue-router'; 

const authStore = useAuthStore();
const router = useRouter();

// 預設帳密從環境變數讀取 (用於開發測試)
const devCredentialsString = import.meta.env.VITE_DEV_TEST_CREDENTIALS;
let devCredentials = {};
try {
    if (devCredentialsString) {
        // VITE_DEV_TEST_CREDENTIALS 格式應為 JSON 字串: '{"email": "test@wms.com", "password": "password"}'
        devCredentials = JSON.parse(devCredentialsString);
    }
} catch (e) {
    console.error("VITE_DEV_TEST_CREDENTIALS is not valid JSON:", e);
}

const username = ref(devCredentials.email || ''); 
const password = ref(devCredentials.password || ''); 

const loading = ref(false);
const error = ref('');

async function handleLogin() {
    error.value = '';

    if (!username.value || !password.value) {
        error.value = '請輸入帳號和密碼。';
        return;
    }

    loading.value = true;
    
    const success = await authStore.login(username.value, password.value);
    
    loading.value = false;

    if (success) {
        // 路由導向由 main.js 的路由守衛處理
    } else {
        error.value = '登入失敗，請檢查帳號密碼。';
    }
}
</script>

<template>
    <div class="login-container">
        <div class="login-card">
            <h1 class="text-2xl font-bold mb-6">WMS 系統登入</h1>
            <form @submit.prevent="handleLogin">
                <div class="form-group">
                    <label for="username">帳號 (Email)</label>
                    <input id="username" type="email" v-model="username" required :disabled="loading" />
                </div>
                <div class="form-group">
                    <label for="password">密碼</label>
                    <input id="password" type="password" v-model="password" required :disabled="loading" />
                </div>
                
                <p v-if="error" class="error-message p-2 bg-red-100 border border-red-400 rounded">{{ error }}</p>

                <button type="submit" :disabled="loading" class="mt-4">
                    <span v-if="loading">登入中...</span>
                    <span v-else>登入</span>
                </button>
            </form>
            <p v-if="devCredentials.email" class="test-hint mt-4 text-sm text-green-600">已使用環境變數載入測試帳密。</p>
            <p v-else class="test-hint mt-4 text-sm text-gray-500">請透過 VITE_DEV_TEST_CREDENTIALS 設定測試帳密。</p>
        </div>
    </div>
</template>

<style scoped>
.login-container { display: flex; justify-content: center; align-items: center; min-height: calc(100vh - 80px); background-color: #f4f7f9; }
.login-card { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); width: 100%; max-width: 400px; }
.form-group { margin-bottom: 16px; }
input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
button { width: 100%; padding: 12px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 1rem; transition: background-color 0.3s; }
button:disabled { background-color: #a0c4ff; cursor: not-allowed; }
.error-message { color: #dc3545; margin-bottom: 15px; text-align: center; border-color: #f5c6cb !important; }
</style>
