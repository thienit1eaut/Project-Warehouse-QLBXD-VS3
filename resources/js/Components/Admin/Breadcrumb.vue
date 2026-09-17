<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { findActiveMenuItem } from '@/Composables/useAdminMenu';

const page = usePage();

// Breadcrumb 2 cấp: Trang chủ > [Tên menu đang active]
// Tên menu lấy tự động từ config, không cần khai báo lại ở từng trang.
// Nếu trang có pageTitle riêng (vd: "Sửa người dùng: Nguyễn Văn A") thì ưu tiên hiển thị pageTitle.
const activeItem = computed(() => findActiveMenuItem(page.url));
const currentLabel = computed(() => page.props.pageTitle || activeItem.value?.label || '');
</script>

<template>
    <nav class="flex items-center gap-1.5 text-sm" aria-label="Breadcrumb">
        <Link href="/admin/dashboard" class="text-slate-400 hover:text-slate-600 transition-colors">
            Trang chủ
        </Link>

        <template v-if="currentLabel">
            <svg class="h-3.5 w-3.5 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
            </svg>
            <span class="font-medium text-slate-700">{{ currentLabel }}</span>
        </template>
    </nav>
</template>
