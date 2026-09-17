<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Badge from '@/Components/UI/Badge.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    pageTitle: { type: String, default: '' },
    units:     { type: Object, required: true },
    filters:   { type: Object, default: () => ({ search: '', is_active: '' }) },
});

const search   = ref(props.filters.search    ?? '');
const isActive = ref(props.filters.is_active ?? '');

let debounceTimer;
watch([search, isActive], () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        router.get('/admin/units',
            { search: search.value, is_active: isActive.value },
            { preserveState: true, replace: true }
        );
    }, 400);
});

function deleteUnit(unit) {
    if (!confirm(`Xoá đơn vị tính "${unit.name}"? Hành động này không thể hoàn tác.`)) return;
    router.delete(`/admin/units/${unit.id}`, { preserveScroll: true });
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
                    placeholder="Tìm theo mã hoặc tên..."
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
                href="/admin/units/create"
                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2
                       text-sm font-medium text-white transition hover:bg-indigo-700"
            >
                + Thêm đơn vị tính
            </Link>
        </div>

        <!-- Table -->
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50 text-left">
                            <th class="px-4 py-3 font-medium text-slate-600">Mã</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Tên đơn vị tính</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Mô tả</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Sản phẩm</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Trạng thái</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Ngày tạo</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-if="units.data.length === 0">
                            <td colspan="7" class="px-4 py-10 text-center text-slate-400">
                                Chưa có đơn vị tính nào.
                            </td>
                        </tr>

                        <tr
                            v-for="u in units.data"
                            :key="u.id"
                            class="transition-colors hover:bg-slate-50"
                        >
                            <td class="px-4 py-3">
                                <span class="rounded-md bg-slate-100 px-2 py-0.5 font-mono text-xs text-slate-600">
                                    {{ u.code }}
                                </span>
                            </td>
                            <td class="px-4 py-3 font-medium text-slate-800">{{ u.name }}</td>
                            <td class="px-4 py-3 max-w-xs truncate text-slate-500">{{ u.description ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ u.products_count ?? 0 }}</td>

                            <td class="px-4 py-3">
                                <Badge :variant="u.is_active ? 'green' : 'gray'">
                                    {{ u.is_active ? 'Hoạt động' : 'Ngừng hoạt động' }}
                                </Badge>
                            </td>

                            <td class="px-4 py-3 text-slate-500">
                                {{ new Date(u.created_at).toLocaleDateString('vi-VN') }}
                            </td>

                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <Link
                                        :href="`/admin/units/${u.id}/edit`"
                                        class="font-medium text-indigo-600 hover:text-indigo-800"
                                    >
                                        Sửa
                                    </Link>
                                    <button
                                        class="font-medium text-red-500 hover:text-red-700"
                                        @click="deleteUnit(u)"
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
                v-if="units.last_page > 1"
                class="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-sm"
            >
                <span class="text-slate-500">
                    Hiển thị {{ units.from }}–{{ units.to }} / {{ units.total }} đơn vị tính
                </span>
                <div class="flex gap-1">
                    <Link
                        v-for="link in units.links"
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
