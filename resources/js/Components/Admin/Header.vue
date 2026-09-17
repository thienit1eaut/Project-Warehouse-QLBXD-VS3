<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import Breadcrumb from '@/Components/Admin/Breadcrumb.vue';

const emit = defineEmits(['toggle-sidebar', 'toggle-mobile-sidebar']);

const page = usePage();
const user = computed(() => page.props.auth?.user);

const roleLabel = { admin: 'Quản trị viên', manager: 'Quản lý', staff: 'Nhân viên' };

// Dropdown user — đóng khi click ra ngoài
const userMenuOpen = ref(false);
const userMenuRef = ref(null);

function handleClickOutside(e) {
    if (userMenuRef.value && !userMenuRef.value.contains(e.target)) {
        userMenuOpen.value = false;
    }
}
onMounted(() => document.addEventListener('click', handleClickOutside));
onBeforeUnmount(() => document.removeEventListener('click', handleClickOutside));

function logout() {
    router.post('/logout');
}

// Icon buttons bên phải header — badge số lượng tạm thời để 0,
// sau này nối API thông báo/đơn hàng/tin nhắn thực tế thì thay bằng props hoặc composable riêng.
const iconButtons = [
    {
        label: 'Thông báo',
        badge: 3,
        icon: 'M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0',
    },
    {
        label: 'Đơn hàng',
        badge: 5,
        icon: 'M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z',
    },
    {
        label: 'Tin nhắn',
        badge: 0,
        icon: 'M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z',
    },
];
</script>

<template>
    <header class="sticky top-0 z-20 flex h-16 shrink-0 items-center gap-4 border-b border-slate-200 bg-white px-4 lg:px-6">

        <!-- Mobile: hamburger mở drawer -->
        <button
            class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:hidden"
            @click="emit('toggle-mobile-sidebar')"
        >
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" />
            </svg>
        </button>

        <!-- Desktop: nút collapse sidebar -->
        <button
            class="hidden rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:inline-flex"
            @click="emit('toggle-sidebar')"
        >
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9h16.5m-16.5 6.75h16.5" />
            </svg>
        </button>

        <!-- Breadcrumb -->
        <div class="min-w-0 flex-1">
            <Breadcrumb />
        </div>

        <!-- Icon actions -->
        <div class="flex items-center gap-1">
            <button
                v-for="btn in iconButtons"
                :key="btn.label"
                :title="btn.label"
                class="relative rounded-lg p-2 text-slate-500 hover:bg-slate-100 transition-colors"
            >
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="btn.icon" />
                </svg>
                <span
                    v-if="btn.badge > 0"
                    class="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center
                           rounded-full bg-red-500 px-1 text-[10px] font-semibold text-white"
                >
                    {{ btn.badge > 9 ? '9+' : btn.badge }}
                </span>
            </button>
        </div>

        <!-- Divider -->
        <div class="hidden h-8 w-px bg-slate-200 sm:block" />

        <!-- User dropdown -->
        <div ref="userMenuRef" class="relative">
            <button
                class="flex items-center gap-2.5 rounded-lg px-2 py-1.5 hover:bg-slate-100 transition-colors"
                @click="userMenuOpen = !userMenuOpen"
            >
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-sm font-semibold text-indigo-600">
                    {{ user?.name?.charAt(0)?.toUpperCase() }}
                </div>
                <div class="hidden text-left sm:block">
                    <p class="text-sm font-medium leading-tight text-slate-800">{{ user?.name }}</p>
                    <p class="text-xs leading-tight text-slate-400">{{ roleLabel[user?.role] }}</p>
                </div>
                <svg class="hidden h-4 w-4 text-slate-400 sm:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                </svg>
            </button>

            <!-- Dropdown panel -->
            <transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="opacity-0 -translate-y-1"
                leave-active-class="transition duration-100 ease-in"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="userMenuOpen"
                    class="absolute right-0 mt-2 w-56 origin-top-right rounded-xl border border-slate-200
                           bg-white py-1.5 shadow-lg"
                >
                    <div class="px-3.5 py-2 border-b border-slate-100">
                        <p class="text-sm font-medium text-slate-800 truncate">{{ user?.name }}</p>
                        <p class="text-xs text-slate-400 truncate">{{ user?.email }}</p>
                    </div>

                    <Link
                        href="/admin/profile"
                        class="flex items-center gap-2.5 px-3.5 py-2 text-sm text-slate-600 hover:bg-slate-50"
                        @click="userMenuOpen = false"
                    >
                        <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                        </svg>
                        Hồ sơ cá nhân
                    </Link>

                    <Link
                        href="/admin/settings"
                        class="flex items-center gap-2.5 px-3.5 py-2 text-sm text-slate-600 hover:bg-slate-50"
                        @click="userMenuOpen = false"
                    >
                        <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Cài đặt
                    </Link>

                    <div class="my-1 border-t border-slate-100" />

                    <button
                        class="flex w-full items-center gap-2.5 px-3.5 py-2 text-sm text-red-500 hover:bg-red-50"
                        @click="logout"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0110.5 3h6a2.25 2.25 0 012.25 2.25v13.5A2.25 2.25 0 0116.5 21h-6a2.25 2.25 0 01-2.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3H15" />
                        </svg>
                        Đăng xuất
                    </button>
                </div>
            </transition>
        </div>
    </header>
</template>
