<!-- File: frontend/src/App.vue -->
<script setup>
import { useAuthStore } from '@/stores/auth';
import { useRouter } from 'vue-router';
import { computed } from 'vue';

const authStore = useAuthStore();
const router = useRouter();

const currentPath = computed(() => router.currentRoute.value.name);

// 註冊在 main.js 的 logoutHandler 已經處理了路由導向
const handleLogout = () => {
    authStore.logout(); 
};
</script>

<template>
    <div id="app-wrapper" class="min-h-screen bg-gray-100 font-sans antialiased">
        
        <!-- 導航欄 (僅在登入後顯示) -->
        <header v-if="authStore.isLoggedIn" class="bg-white shadow-md p-4 mb-4 sticky top-0 z-10">
            <nav class="flex justify-between items-center max-w-7xl mx-auto">
                <h1 class="text-2xl font-semibold text-blue-600">WMS Frontend</h1>
                <div class="space-x-4 flex items-center">
                    <router-link to="/inventory" :class="{ 'router-link-active': currentPath === 'Inventory' }" class="nav-link">庫存</router-link>
                    <router-link to="/inbound" :class="{ 'router-link-active': currentPath === 'Inbound' }" class="nav-link">入庫</router-link>
                    <router-link to="/picking" :class="{ 'router-link-active': currentPath === 'Picking' }" class="nav-link">揀貨</router-link>
                    <button @click="handleLogout" class="bg-red-500 hover:bg-red-600 text-white font-bold py-1 px-3 text-sm rounded transition duration-150 ml-6">
                        登出
                    </button>
                </div>
            </nav>
        </header>

        <main class="max-w-7xl mx-auto p-4">
            <!-- 路由視圖將在此處渲染 -->
            <router-view v-slot="{ Component }">
                <!-- 路由轉換動畫 (可選) -->
                <transition name="fade" mode="out-in">
                    <component :is="Component" />
                </transition>
            </router-view>
        </main>
    </div>
</template>

<style>
/* 這裡是全域樣式，假設您已配置 Tailwind CSS */
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');

#app-wrapper {
    font-family: 'Inter', Avenir, Helvetica, Arial, sans-serif;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}

.nav-link {
    @apply text-gray-600 hover:text-blue-500 transition duration-150 pb-1;
}

.router-link-active {
    @apply font-bold text-blue-600 border-b-2 border-blue-600;
}

/* 簡單的路由動畫 */
.fade-enter-active, .fade-leave-active {
  transition: opacity 0.3s ease;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}
</style>
