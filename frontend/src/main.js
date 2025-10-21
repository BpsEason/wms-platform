// File: frontend/src/main.js
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import { createRouter, createWebHistory } from 'vue-router';
import App from '@/App.vue';
import { useAuthStore } from '@/stores/auth';
import { initApiClient } from '@/lib/apiClient'; // 匯入 apiClient 初始化函數

// Views
import Login from '@/views/Login.vue';
import Inventory from '@/views/Inventory.vue';
import Inbound from '@/views/Inbound.vue';
import Picking from '@/views/Picking.vue';

// 1. 定義路由
const routes = [
    { path: '/', redirect: '/inventory' },
    { path: '/login', name: 'Login', component: Login, meta: { requiresAuth: false } },
    { path: '/inventory', name: 'Inventory', component: Inventory, meta: { requiresAuth: true } },
    { path: '/inbound', name: 'Inbound', component: Inbound, meta: { requiresAuth: true } },
    { path: '/picking', name: 'Picking', component: Picking, meta: { requiresAuth: true } },
    { path: '/:pathMatch(.*)*', redirect: '/inventory' } 
];

// 2. 創建路由器
const router = createRouter({
    history: createWebHistory(),
    routes,
});

// 3. 註冊 Pinia 實例
const pinia = createPinia();

// 4. 創建 App 實例並註冊 Pinia 和 Router
const app = createApp(App);
app.use(pinia);
app.use(router);


// =================================================================
// 5. 認證與全域處理邏輯 (必須在 Pinia 註冊後)
// =================================================================
const authStore = useAuthStore();

/**
 * 完整的登出與導向處理函數 (被 apiClient 呼叫)
 */
const completeLogoutAndRedirect = () => {
    // 確保 Store 狀態被清理
    authStore.logout();
    
    // 執行路由導向，若當前不在 Login 頁面，則導向 Login
    if (router.currentRoute.value.name !== 'Login') {
        router.push({ name: 'Login' });
    }
    console.log("Logout triggered by API 401 handler or Sync.");
};

// 注入 Logout Handler 到 apiClient
initApiClient(completeLogoutAndRedirect);


// 6. 路由守衛 (Navigation Guard)
router.beforeEach((to, from, next) => {
    // 確保 Store 狀態已初始化 (在初次載入時很重要)
    if (!authStore.authReady && authStore.token) {
        // 如果有 Token 但 Store 尚未 ready，嘗試獲取用戶資訊
        authStore.fetchUser().finally(() => checkAuth(to, next));
    } else {
        checkAuth(to, next);
    }
});

function checkAuth(to, next) {
    const requiresAuth = to.matched.some(record => record.meta.requiresAuth);

    if (requiresAuth && !authStore.isLoggedIn) {
        next({ name: 'Login' });
    } else if (authStore.isLoggedIn && to.name === 'Login') {
        next({ name: 'Inventory' });
    } else {
        next();
    }
}


// 7. 跨 Tab/Window Storage 事件同步處理
window.addEventListener('storage', (event) => {
    if (event.key === 'auth_token') {
        const newAuthToken = event.newValue;

        if (newAuthToken && !authStore.isLoggedIn) {
            // 另一個 Tab 登入或 Token 刷新
            console.log("Storage Sync: Token received, fetching user.");
            authStore.fetchUser();
        } else if (!newAuthToken && authStore.isLoggedIn) {
            // 另一個 Tab 登出，強制本 Tab 登出並導向
            console.log("Storage Sync: Token removed, forcing logout.");
            completeLogoutAndRedirect(); 
        } 
    }
});


// 8. 掛載應用程式
app.mount('#app');
