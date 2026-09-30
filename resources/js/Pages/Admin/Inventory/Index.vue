<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

defineProps({
    pageTitle: { type: String, default: '' },
});

const actions = [
    {
        title: 'Nhập kho',
        description: 'Ghi nhận hàng nhập vào kho, tự tạo lô hàng (StockLot) mới.',
        href: '/admin/inventory/receive',
        color: 'text-green-700 bg-green-50 border-green-200',
    },
    {
        title: 'Xuất kho',
        description: 'Xuất hàng ra khỏi kho theo nguyên tắc FIFO (lô nhập trước xuất trước).',
        href: '/admin/inventory/issue',
        color: 'text-red-700 bg-red-50 border-red-200',
    },
    {
        title: 'Điều chỉnh tồn kho',
        description: 'Cập nhật lại số lượng tồn thực tế sau kiểm kê.',
        href: '/admin/inventory/adjustment',
        color: 'text-amber-700 bg-amber-50 border-amber-200',
    },
];
</script>

<template>
    <Head :title="pageTitle" />

    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h1 class="text-lg font-semibold text-slate-800">Nghiệp vụ tồn kho</h1>
            <Link href="/admin/stock" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
                Xem tồn kho hiện tại →
            </Link>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <Link
                v-for="action in actions"
                :key="action.href"
                :href="action.href"
                :class="['rounded-xl border p-5 transition hover:shadow-sm', action.color]"
            >
                <h2 class="text-base font-semibold">{{ action.title }}</h2>
                <p class="mt-1 text-sm opacity-80">{{ action.description }}</p>
            </Link>
        </div>
    </div>
</template>