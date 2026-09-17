<script setup>
import { ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Sidebar from '@/Components/Admin/Sidebar.vue';
import Header from '@/Components/Admin/Header.vue';
import FlashMessage from '@/Components/UI/FlashMessage.vue';

// Trạng thái sidebar:
//  - collapsed: desktop thu gọn còn icon (lưu vào localStorage để nhớ lựa chọn của user)
//  - mobileOpen: mobile drawer đang mở hay đóng
const collapsed  = ref(localStorage.getItem('sidebar_collapsed') === '1');
const mobileOpen = ref(false);

function toggleSidebar() {
    collapsed.value = !collapsed.value;
    localStorage.setItem('sidebar_collapsed', collapsed.value ? '1' : '0');
}

function toggleMobileSidebar() {
    mobileOpen.value = !mobileOpen.value;
}

// Đóng drawer mobile mỗi khi chuyển trang (điều hướng qua Inertia)
const page = usePage();
watch(() => page.url, () => { mobileOpen.value = false; });
</script>

<template>
    <div class="flex h-screen overflow-hidden bg-slate-50">

        <Sidebar
            :collapsed="collapsed"
            :mobile-open="mobileOpen"
            @close-mobile="mobileOpen = false"
        />

        <!-- Cột phải: Header cố định + nội dung tự scroll riêng -->
        <div class="flex min-w-0 flex-1 flex-col">
            <Header
                @toggle-sidebar="toggleSidebar"
                @toggle-mobile-sidebar="toggleMobileSidebar"
            />

            <!-- overflow-y-auto ở đây, KHÔNG ở body -> header luôn cố định khi cuộn nội dung dài -->
            <main class="flex-1 overflow-y-auto">
                <div class="mx-auto max-w-7xl p-4 lg:p-6">
                    <FlashMessage />
                    <slot />
                </div>
            </main>
        </div>

    </div>
</template>
