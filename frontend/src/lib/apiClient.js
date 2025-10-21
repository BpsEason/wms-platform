// File: frontend/src/lib/apiClient.js
import axios from 'axios';

const BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost/api';

const apiClient = axios.create({
    baseURL: BASE_URL,
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
    },
    timeout: 10000,
});

// 刷新 Token 狀態管理 (防止併發請求重複刷新)
let isRefreshing = false;
let failedQueue = [];
let logoutHandler = null; 

/**
 * 外部呼叫此函數來注入完整的登出處理邏輯 (包含 Pinia store 清理和 Router 導向)
 * @param {Function} handler 
 */
export function initApiClient(handler) {
    logoutHandler = handler;
    console.log("ApiClient: Logout handler injected.");
}

/**
 * 設定 API 客戶端的授權 Header
 * @param {string | null} token 
 */
export function setAuthHeader(token) {
    if (token) {
        apiClient.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    } else {
        delete apiClient.defaults.headers.common['Authorization'];
    }
}

// 處理等待中的請求隊列
const processQueue = (error, token = null) => {
    failedQueue.forEach(prom => {
        if (error) {
            prom.reject(error);
        } else {
            // 成功刷新後，重試請求
            prom.resolve(token);
        }
    });
    failedQueue = [];
};

// =================================================================
// 🚨 Response 攔截器：處理 401 Unauthorized, Token Refresh, 同步鎖定
// =================================================================
apiClient.interceptors.response.use(
    response => response,
    async (error) => {
        const originalRequest = error.config;
        
        // 檢查是否為 401 錯誤，且尚未標記為重試請求
        if (error.response?.status === 401 && !originalRequest._retry) {
            
            // 1. 如果是登入或登出請求本身失敗，或沒有 logoutHandler，直接返回錯誤
            if (originalRequest.url.endsWith('/auth/login') || originalRequest.url.endsWith('/auth/logout') || !logoutHandler) {
                if (originalRequest.url.endsWith('/auth/logout') && logoutHandler) logoutHandler();
                return Promise.reject(error);
            }

            // 2. 如果正在刷新 Token，將當前請求加入隊列
            if (isRefreshing) {
                return new Promise((resolve, reject) => {
                    failedQueue.push({ 
                        resolve: async () => {
                            const currentToken = localStorage.getItem('auth_token'); // 從 localStorage 獲取最新 Token
                            originalRequest.headers['Authorization'] = `Bearer ${currentToken}`; 
                            try {
                                resolve(await apiClient(originalRequest));
                            } catch(e) {
                                reject(e);
                            }
                        }, 
                        reject 
                    });
                });
            }

            // 3. 開始刷新 Token 流程 (同步鎖定)
            originalRequest._retry = true; // 標記為已重試
            isRefreshing = true;

            try {
                // 動態引入 authStore，避免循環依賴
                const { useAuthStore } = await import('@/stores/auth'); 
                const authStore = useAuthStore();
                
                const newAccessToken = await authStore.refreshToken(); 

                // Refresh 成功
                isRefreshing = false;
                processQueue(null, newAccessToken);
                
                // 重試原始請求
                originalRequest.headers['Authorization'] = `Bearer ${newAccessToken}`;
                return apiClient(originalRequest);

            } catch (refreshError) {
                // Refresh 失敗，強制登出
                isRefreshing = false;
                console.error("Token Refresh Failed, force logout.", refreshError);
                if (logoutHandler) {
                    logoutHandler(); 
                }
                processQueue(refreshError, null);
                return Promise.reject(refreshError);
            }
        }

        return Promise.reject(error);
    }
);

export default apiClient;
