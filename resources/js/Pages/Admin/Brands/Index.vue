<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Badge from '@/Components/UI/Badge.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    pageTitle: { type: String, default: '' },
    brands:    { type: Object, required: true }, // Laravel paginator
    filters:   { type: Object, default: () => ({ search: '', is_active: '' }) },
});

const search   = ref(props.filters.search    ?? '');
const isActive = ref(props.filters.is_active ?? '');

let debounceTimer;
watch([search, isActive], () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        router.get('/admin/brands',
            { search: search.value, is_active: isActive.value },
            { preserveState: true, replace: true }
        );
    }, 400);
});

function deleteBrand(brand) {
    if (!confirm(`Xoá thương hiệu "${brand.name}"? Hành động này không thể hoàn tác.`)) return;
    router.delete(`/admin/brands/${brand.id}`, { preserveScroll: true });
}

// Ưu tiên variant 'thumbnail' (ảnh nhỏ, tải nhanh cho bảng danh sách),
// fallback url gốc nếu Media chưa có variant (vd file không phải ảnh xử lý được).
function thumbUrl(brand) {
    if (!brand.media) return null;
    const thumb = brand.media.variants?.find((v) => v.name === 'thumbnail');
    return thumb?.url ?? brand.media.url;
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
                    placeholder="Tìm theo tên thương hiệu..."
                    class="w-60 rounded-lg border border-slate-300 px-3 py-2 text-sm
                           focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
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
                href="/admin/brands/create"
                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2
                       text-sm font-medium text-white transition hover:bg-indigo-700"
            >
                + Thêm thương hiệu
            </Link>
        </div>

        <!-- Table -->
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50 text-left">
                            <th class="px-4 py-3 font-medium text-slate-600">Ảnh</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Tên thương hiệu</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Website</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Số sản phẩm</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Trạng thái</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Ngày tạo</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-if="brands.data.length === 0">
                            <td colspan="7" class="px-4 py-10 text-center text-slate-400">
                                Chưa có thương hiệu nào.
                            </td>
                        </tr>

                        <tr
                            v-for="b in brands.data"
                            :key="b.id"
                            class="transition-colors hover:bg-slate-50"
                        >
                            <!-- Ảnh hoặc placeholder chữ cái đầu -->
                            <td class="px-4 py-3">
                                <img
                                    v-if="thumbUrl(b)"
                                    :src="thumbUrl(b)"
                                    :alt="b.name"
                                    class="h-9 w-9 rounded-lg border border-slate-100 object-contain"
                                />
                                <div
                                    v-else
                                    class="flex h-9 w-9 items-center justify-center rounded-lg
                                           bg-slate-100 text-xs font-semibold text-slate-400"
                                >
                                    {{ b.name?.charAt(0)?.toUpperCase() }}
                                </div>
                            </td>

                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-800">{{ b.name }}</p>
                                <p class="text-xs text-slate-400">{{ b.slug }}</p>
                            </td>

                            <td class="px-4 py-3">
                                <a
                                    v-if="b.website"
                                    :href="b.website"
                                    target="_blank"
                                    rel="noopener"
                                    class="text-indigo-600 hover:underline"
                                >
                                    {{ b.website.replace(/^https?:\/\//, '') }}
                                </a>
                                <span v-else class="text-slate-400">—</span>
                            </td>

                            <td class="px-4 py-3 text-slate-500">
                                {{ b.products_count ?? 0 }}
                            </td>

                            <td class="px-4 py-3">
                                <Badge :variant="b.is_active ? 'green' : 'gray'">
                                    {{ b.is_active ? 'Hoạt động' : 'Ngừng hoạt động' }}
                                </Badge>
                            </td>

                            <td class="px-4 py-3 text-slate-500">
                                {{ new Date(b.created_at).toLocaleDateString('vi-VN') }}
                            </td>

                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <Link
                                        :href="`/admin/brands/${b.id}/edit`"
                                        class="font-medium text-indigo-600 hover:text-indigo-800"
                                    >
                                        Sửa
                                    </Link>
                                    <button
                                        class="font-medium text-red-500 hover:text-red-700"
                                        @click="deleteBrand(b)"
                                    >
                                        Xoá
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div
                v-if="brands.last_page > 1"
                class="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-sm"
            >
                <span class="text-slate-500">
                    Hiển thị {{ brands.from }}–{{ brands.to }} / {{ brands.total }} thương hiệu
                </span>
                <div class="flex gap-1">
                    <Link
                        v-for="link in brands.links"
                        :key="link.label"
                        :href="link.url ?? '#'"
                        :class="[
                            'rounded-lg border px-3 py-1 text-xs transition',
                            link.active
                                ? 'border-indigo-600 bg-indigo-600 text-white'
                                : 'border-slate-200 text-slate-600 hover:border-indigo-300',
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