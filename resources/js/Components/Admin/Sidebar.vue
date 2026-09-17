<script setup>
import { Link } from '@inertiajs/vue3';
import SidebarMenu from '@/Components/Admin/SidebarMenu.vue';
import { adminMenu, adminMenuFooter } from '@/Composables/useAdminMenu';

const props = defineProps({
    // Desktop: sidebar thu gọn còn icon (width nhỏ)
    collapsed: {
        type: Boolean,
        default: false,
    },
    // Mobile: sidebar hiện dạng drawer (trượt từ trái)
    mobileOpen: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close-mobile']);
</script>

<template>
    <!-- Overlay tối nền khi mobile drawer mở -->
    <div
        v-if="mobileOpen"
        class="fixed inset-0 z-30 bg-slate-900/50 lg:hidden"
        @click="emit('close-mobile')"
    />

    <aside
        class="fixed inset-y-0 left-0 z-40 flex flex-col bg-white border-r border-slate-200
               transition-all duration-200 ease-in-out
               lg:static lg:translate-x-0"
        :class="[
            collapsed ? 'lg:w-20' : 'lg:w-64',
            'w-64', // mobile luôn full width khi mở
            mobileOpen ? 'translate-x-0' : '-translate-x-full',
        ]"
    >
        <!-- Logo -->
        <div
            class="flex h-16 items-center border-b border-slate-100 shrink-0"
            :class="collapsed ? 'lg:justify-center px-4' : 'px-5'"
        >
            <Link href="/admin/dashboard" class="flex items-center gap-2.5 min-w-0">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-indigo-600 text-white">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                    </svg>
                </div>
                <div v-if="!collapsed" class="min-w-0 leading-tight">
                    <p class="truncate text-sm font-bold text-slate-800">Warehouse</p>
                    <p class="truncate text-[11px] text-slate-400">Quản lý kho hàng</p>
                </div>
            </Link>
        </div>

        <!-- Menu chính -->
        <div class="flex-1 overflow-y-auto px-3 py-4">
            <SidebarMenu :groups="adminMenu" :collapsed="collapsed" @navigate="emit('close-mobile')" />
        </div>

        <!-- Cài đặt — ghim ở cuối, tách bằng border -->
        <div class="shrink-0 border-t border-slate-100 px-3 py-3">
            <SidebarMenu
                :groups="[{ group: null, items: adminMenuFooter }]"
                :collapsed="collapsed"
                @navigate="emit('close-mobile')"
            />
        </div>
    </aside>
</template>
