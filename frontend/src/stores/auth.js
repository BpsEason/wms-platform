// File: frontend/src/stores/auth.js
import { defineStore } from 'pinia';
import apiClient, { setAuthHeader } from '@/lib/apiClient'; 

// 首次載入時檢查 localStorage 中是否有 token
const initialToken = localStorage.getItem('auth_token') || null;

// 在 Store 初始化時設置 Header
setAuthHeader(initialToken);

export const useAuthStore = defineStore('auth', {
    state: () => ({
        token: initialToken,
        user: null, 
        isLoggedIn: !!initialToken,
        authReady: !!initialToken // 用於判斷 Store 狀態是否準備就緒
    }),
    
    actions: {
        async login(username, password) {
            try {
                const response = await apiClient.post('/auth/login', {
                    email: username, 
                    password: password,
                    device_name: 'frontend_web',
                });

                const { token } = response.data;
                
                this.token = token;
                this.isLoggedIn = true;
                this.authReady = true;
                localStorage.setItem('auth_token', token);
                
                setAuthHeader(token);
                await this.fetchUser();

                return true;
            } catch (error) {
                console.error("Login failed:", error.response?.data || error.message);
                this.logout(); 
                return false;
            }
        },

        /**
         * 執行 Token 刷新 (供 apiClient 攔截器呼叫)
         * @returns {Promise<string>} 回傳新的 Access Token
         */
        async refreshToken() {
            try {
                // 呼叫 /auth/refresh，後端應檢查現有的 Sanctum Token 是否還能發出新 Token
                // 這裡必須使用靜態 API client 呼叫，確保不會被自己的攔截器循環觸發
                const response = await axios.post(`${apiClient.defaults.baseURL}/auth/refresh`, {}, {
                    headers: {
                        'Authorization': `Bearer ${this.token}`, // 帶上當前 Token
                    },
                }); 

                const newToken = response.data.token;
                if (newToken) {
                    this.token = newToken;
                    localStorage.setItem('auth_token', newToken);
                    setAuthHeader(newToken);
                    return newToken;
                } else {
                    throw new Error("Refresh token failed: API did not return a new token.");
                }
            } catch(e) {
                // 必須拋出錯誤，讓 apiClient 攔截器知道 Refresh 失敗並觸發登出
                throw e;
            }
        },

        /**
         * 獲取當前登入用戶資訊 (用於跨 tab 同步檢查)
         */
        async fetchUser() {
            const currentToken = localStorage.getItem('auth_token');
            if (!currentToken) {
                 this.logout();
                 return;
            }
            try {
                setAuthHeader(currentToken);
                const response = await apiClient.get('/auth/me');
                this.user = response.data;
                this.isLoggedIn = true;
            } catch (error) {
                // 401 錯誤會被 apiClient 攔截器處理
                console.error("Fetch user failed during sync:", error.message);
            } finally {
                this.authReady = true;
            }
        },
        
        /**
         * 執行登出 (只清理狀態和 localStorage，不進行路由導向)
         */
        logout() {
            this.token = null;
            this.user = null;
            this.isLoggedIn = false;
            this.authReady = false;
            localStorage.removeItem('auth_token');
            setAuthHeader(null);
        }
    }
});
