<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Badge from '@/Components/UI/Badge.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    pageTitle: { type: String, default: '' },
    suppliers: { type: Object, required: true },
    filters:   { type: Object, default: () => ({ search: '', is_active: '' }) },
});

const search   = ref(props.filters.search    ?? '');
const isActive = ref(props.filters.is_active ?? '');

let debounceTimer;
watch([search, isActive], () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        router.get('/admin/suppliers',
            { search: search.value, is_active: isActive.value },
            { preserveState: true, replace: true }
        );
    }, 400);
});

function deleteSupplier(supplier) {
    if (!confirm(`Xoá nhà cung cấp "${supplier.name}"? Hành động này không thể hoàn tác.`)) return;
    router.delete(`/admin/suppliers/${supplier.id}`, { preserveScroll: true });
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
                    placeholder="Tìm theo mã, tên, SĐT, email..."
                    class="w-72 rounded-lg border border-slate-300 px-3 py-2 text-sm
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
                href="/admin/suppliers/create"
                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2
                       text-sm font-medium text-white transition hover:bg-indigo-700"
            >
                + Thêm nhà cung cấp
            </Link>
        </div>

        <!-- Table -->
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50 text-left">
                            <th class="px-4 py-3 font-medium text-slate-600">Mã NCC</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Tên nhà cung cấp</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Người liên hệ</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Điện thoại</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Email</th>
                            <th class="px-4 py-3 font-medium text-slate-600">MST</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Sản phẩm</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Trạng thái</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-if="suppliers.data.length === 0">
                            <td colspan="9" class="px-4 py-10 text-center text-slate-400">
                                Chưa có nhà cung cấp nào.
                            </td>
                        </tr>

                        <tr
                            v-for="s in suppliers.data"
                            :key="s.id"
                            class="transition-colors hover:bg-slate-50"
                        >
                            <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ s.code }}</td>

                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-800">{{ s.name }}</p>
                                <p v-if="s.address" class="max-w-xs truncate text-xs text-slate-400">{{ s.address }}</p>
                            </td>

                            <td class="px-4 py-3 text-slate-600">{{ s.contact_person ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ s.phone ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ s.email ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ s.tax_code ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ s.products_count ?? 0 }}</td>

                            <td class="px-4 py-3">
                                <Badge :variant="s.is_active ? 'green' : 'gray'">
                                    {{ s.is_active ? 'Hoạt động' : 'Ngừng hoạt động' }}
                                </Badge>
                            </td>

                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <Link
                                        :href="`/admin/suppliers/${s.id}/edit`"
                                        class="font-medium text-indigo-600 hover:text-indigo-800"
                                    >
                                        Sửa
                                    </Link>
                                    <button
                                        class="font-medium text-red-500 hover:text-red-700"
                                        @click="deleteSupplier(s)"
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
                v-if="suppliers.last_page > 1"
                class="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-sm"
            >
                <span class="text-slate-500">
                    Hiển thị {{ suppliers.from }}–{{ suppliers.to }} / {{ suppliers.total }} nhà cung cấp
                </span>
                <div class="flex gap-1">
                    <Link
                        v-for="link in suppliers.links"
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
