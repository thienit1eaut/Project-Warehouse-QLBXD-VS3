<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Badge from '@/Components/UI/Badge.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    pageTitle: { type: String, default: '' },
    product:   { type: Object, required: true },
});

const previewUrl = computed(() => {
    const thumb = props.product.media?.variants?.find((v) => v.name === 'medium');
    return thumb?.url ?? props.product.media?.url ?? null;
});

function formatPrice(value) {
    return new Intl.NumberFormat('vi-VN').format(value) + ' đ';
}

function deleteProduct() {
    if (!confirm(`Xoá sản phẩm "${props.product.name}"? Hành động này không thể hoàn tác.`)) return;
    router.delete(`/admin/products/${props.product.id}`, {
        onSuccess: () => router.visit('/admin/products'),
    });
}
</script>

<template>
    <Head :title="pageTitle" />

    <div class="max-w-3xl space-y-4">

        <div class="flex items-center justify-between">
            <Link href="/admin/products" class="text-sm text-slate-500 hover:text-indigo-600">
                ← Quay lại danh sách
            </Link>
            <div class="flex items-center gap-3">
                <Link
                    :href="`/admin/products/${product.id}/edit`"
                    class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                >
                    Sửa
                </Link>
                <button
                    class="rounded-lg border border-red-200 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50"
                    @click="deleteProduct"
                >
                    Xoá
                </button>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6">
            <div class="flex gap-6">

                <!-- Ảnh -->
                <div class="w-48 shrink-0">
                    <div class="aspect-square overflow-hidden rounded-lg border border-slate-100 bg-slate-50 flex items-center justify-center">
                        <img v-if="previewUrl" :src="previewUrl" :alt="product.name" class="h-full w-full object-contain" />
                        <span v-else class="text-xs text-slate-400">Chưa có ảnh</span>
                    </div>
                </div>

                <!-- Thông tin -->
                <div class="flex-1 space-y-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-lg font-semibold text-slate-800">{{ product.name }}</h1>
                            <Badge :variant="product.is_active ? 'green' : 'gray'">
                                {{ product.is_active ? 'Hoạt động' : 'Ngừng hoạt động' }}
                            </Badge>
                        </div>
                        <p class="mt-0.5 font-mono text-xs text-slate-400">SKU: {{ product.sku }}</p>
                        <p class="text-xs text-slate-400">Slug: {{ product.slug }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <p class="text-xs text-slate-400">Danh mục</p>
                            <p class="text-slate-700">{{ product.category?.name ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400">Thương hiệu</p>
                            <p class="text-slate-700">{{ product.brand?.name ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400">Nhà cung cấp</p>
                            <p class="text-slate-700">{{ product.supplier?.name ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400">Đơn vị tính</p>
                            <p class="text-slate-700">{{ product.unit?.name ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400">Giá bán</p>
                            <p class="text-base font-semibold text-slate-800">{{ formatPrice(product.selling_price) }}</p>
                        </div>
                    </div>

                    <div v-if="product.description">
                        <p class="text-xs text-slate-400">Mô tả</p>
                        <p class="whitespace-pre-line text-sm text-slate-600">{{ product.description }}</p>
                    </div>
                </div>

            </div>
        </div>

    </div>
</template>