<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Badge from '@/Components/UI/Badge.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    pageTitle:        { type: String, default: '' },
    products:         { type: Object, required: true }, // Laravel paginator
    filters:          { type: Object, default: () => ({}) },
    categoryOptions:  { type: Array,  default: () => [] },
    brandOptions:     { type: Array,  default: () => [] },
});

const search     = ref(props.filters.search      ?? '');
const categoryId = ref(props.filters.category_id ?? '');
const brandId    = ref(props.filters.brand_id    ?? '');
const isActive   = ref(props.filters.is_active   ?? '');

let debounceTimer;
watch([search, categoryId, brandId, isActive], () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        router.get('/admin/products', {
            search: search.value,
            category_id: categoryId.value,
            brand_id: brandId.value,
            is_active: isActive.value,
        }, { preserveState: true, replace: true });
    }, 400);
});

function deleteProduct(product) {
    if (!confirm(`Xoá sản phẩm "${product.name}"? Hành động này không thể hoàn tác.`)) return;
    router.delete(`/admin/products/${product.id}`, { preserveScroll: true });
}

function thumbUrl(product) {
    if (!product.media) return null;
    const thumb = product.media.variants?.find((v) => v.name === 'thumbnail');
    return thumb?.url ?? product.media.url;
}

function formatPrice(value) {
    return new Intl.NumberFormat('vi-VN').format(value) + ' đ';
}
</script>

<template>
    <Head :title="pageTitle" />

    <div class="space-y-4">

        <!-- Toolbar -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap gap-2">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Tìm theo tên hoặc SKU..."
                    class="w-56 rounded-lg border border-slate-300 px-3 py-2 text-sm
                           focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
                <select
                    v-model="categoryId"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm
                           focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
                    <option value="">Tất cả danh mục</option>
                    <option v-for="c in categoryOptions" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
                <select
                    v-model="brandId"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm
                           focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
                    <option value="">Tất cả thương hiệu</option>
                    <option v-for="b in brandOptions" :key="b.id" :value="b.id">{{ b.name }}</option>
                </select>
                <select
                    v-model="isActive"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm
                           focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
                    <option value="">Tất cả trạng thái</option>
                    <option value="1">Hoạt động</option>
                    <option value="0">Ngừng hoạt động</option>
                </select>
            </div>

            <Link
                href="/admin/products/create"
                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2
                       text-sm font-medium text-white transition hover:bg-indigo-700"
            >
                + Thêm sản phẩm
            </Link>
        </div>

        <!-- Table -->
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50 text-left">
                            <th class="px-4 py-3 font-medium text-slate-600">Ảnh</th>
                            <th class="px-4 py-3 font-medium text-slate-600">SKU</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Tên sản phẩm</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Danh mục</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Thương hiệu</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Đơn vị</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Giá bán</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Trạng thái</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-if="products.data.length === 0">
                            <td colspan="9" class="px-4 py-10 text-center text-slate-400">
                                Chưa có sản phẩm nào.
                            </td>
                        </tr>

                        <tr v-for="p in products.data" :key="p.id" class="transition-colors hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <img
                                    v-if="thumbUrl(p)"
                                    :src="thumbUrl(p)"
                                    :alt="p.name"
                                    class="h-9 w-9 rounded-lg border border-slate-100 object-contain"
                                />
                                <div
                                    v-else
                                    class="flex h-9 w-9 items-center justify-center rounded-lg
                                           bg-slate-100 text-xs font-semibold text-slate-400"
                                >
                                    {{ p.name?.charAt(0)?.toUpperCase() }}
                                </div>
                            </td>

                            <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ p.sku }}</td>

                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-800">{{ p.name }}</p>
                                <p class="text-xs text-slate-400">{{ p.slug }}</p>
                            </td>

                            <td class="px-4 py-3 text-slate-600">{{ p.category?.name ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ p.brand?.name ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ p.unit?.name ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ formatPrice(p.selling_price) }}</td>

                            <td class="px-4 py-3">
                                <Badge :variant="p.is_active ? 'green' : 'gray'">
                                    {{ p.is_active ? 'Hoạt động' : 'Ngừng hoạt động' }}
                                </Badge>
                            </td>

                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <Link :href="`/admin/products/${p.id}`" class="font-medium text-slate-500 hover:text-slate-700">
                                        Xem
                                    </Link>
                                    <Link :href="`/admin/products/${p.id}/edit`" class="font-medium text-indigo-600 hover:text-indigo-800">
                                        Sửa
                                    </Link>
                                    <button class="font-medium text-red-500 hover:text-red-700" @click="deleteProduct(p)">
                                        Xoá
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="products.last_page > 1" class="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-sm">
                <span class="text-slate-500">
                    Hiển thị {{ products.from }}–{{ products.to }} / {{ products.total }} sản phẩm
                </span>
                <div class="flex gap-1">
                    <Link
                        v-for="link in products.links"
                        :key="link.label"
                        :href="link.url ?? '#'"
                        :class="[
                            'rounded-lg border px-3 py-1 text-xs transition',
                            link.active ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-slate-200 text-slate-600 hover:border-indigo-300',
                            !link.url && 'pointer-events-none opacity-40',
                        ]"
                        v-html="link.label"
                        preserve-scroll
                    />
                </div>
            </div>
        </div>

    </div>
</template>