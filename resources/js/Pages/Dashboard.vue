<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { computed } from 'vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    pageTitle: { type: String, default: 'Dashboard' },
    stats: {
        type: Object,
        default: () => ({ totalUsers: 0, totalBikes: 0, totalParts: 0, lowStock: 0 }),
    },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);

const cards = computed(() => [
    { label: 'Người dùng',      value: props.stats.totalUsers, bg: 'bg-blue-50',   text: 'text-blue-600',
      icon: 'M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z',
      href: '/admin/users' },
    { label: 'Sản phẩm trong kho', value: props.stats.totalBikes, bg: 'bg-indigo-50', text: 'text-indigo-600',
      icon: 'M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z',
      href: '/admin/products' },
    { label: 'Danh mục', value: props.stats.totalParts, bg: 'bg-purple-50', text: 'text-purple-600',
      icon: 'M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3zM6 6h.008v.008H6V6z',
      href: '/admin/categories' },
    { label: 'Sắp hết hàng', value: props.stats.lowStock, bg: 'bg-red-50', text: 'text-red-600',
      icon: 'M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z',
      href: '/admin/inventory' },
]);
</script>

<template>
    <Head :title="pageTitle" />

    <div class="space-y-6">

        <!-- Welcome -->
        <div class="rounded-xl border border-slate-200 bg-white p-6">
            <h2 class="text-xl font-semibold text-slate-800">
                Xin chào, {{ user?.name }} 👋
            </h2>
            <p class="mt-1 text-sm text-slate-500">
                Đây là tổng quan hệ thống quản lý kho hàng hôm nay.
            </p>
        </div>

        <!-- Stat cards -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <a
                v-for="card in cards"
                :key="card.label"
                :href="card.href"
                class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-5
                       transition-shadow hover:shadow-sm"
            >
                <div :class="['flex h-12 w-12 shrink-0 items-center justify-center rounded-xl', card.bg]">
                    <svg class="h-6 w-6" :class="card.text" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="card.icon" />
                    </svg>
                </div>
                <div>
                    <p :class="['text-2xl font-bold', card.text]">{{ card.value }}</p>
                    <p class="mt-0.5 text-sm text-slate-500">{{ card.label }}</p>
                </div>
            </a>
        </div>

        <!-- Stack info -->
        <div class="rounded-xl border border-slate-200 bg-white p-6">
            <p class="mb-3 text-xs font-medium uppercase tracking-wider text-slate-400">Tech Stack</p>
            <div class="flex flex-wrap gap-2">
                <span
                    v-for="tag in ['Laravel 12', 'Inertia.js', 'Vue 3', 'Tailwind CSS 4', 'Vite', 'Sanctum']"
                    :key="tag"
                    class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-medium text-indigo-700"
                >
                    {{ tag }}
                </span>
            </div>
        </div>

    </div>
</template>
