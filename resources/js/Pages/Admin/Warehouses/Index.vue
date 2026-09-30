<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Badge from '@/Components/UI/Badge.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    pageTitle:   { type: String, default: '' },
    warehouses:  { type: Object, required: true }, // Laravel paginator
    filters:     { type: Object, default: () => ({ search: '' }) },
});

const search = ref(props.filters.search ?? '');

let debounceTimer;
watch(search, () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        router.get('/admin/warehouses',
            { search: search.value },
            { preserveState: true, replace: true }
        );
    }, 400);
});

function destroyWarehouse(warehouse) {
    if (!confirm(`Xoá kho "${warehouse.name}"? Hành động này không thể hoàn tác.`)) return;
    router.delete(`/admin/warehouses/${warehouse.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head :title="pageTitle" />

    <div class="space-y-4">

        <!-- Toolbar -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <input
                v-model="search"
                type="text"
                placeholder="Tìm theo mã hoặc tên kho..."
                class="w-60 rounded-lg border border-slate-300 px-3 py-2 text-sm
                       focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />

            <Link
                href="/admin/warehouses/create"
                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2
                       text-sm font-medium text-white transition hover:bg-indigo-700"
            >
                + Thêm kho hàng
            </Link>
        </div>

        <!-- Table -->
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50 text-left">
                            <th class="px-4 py-3 font-medium text-slate-600">Mã kho</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Tên kho</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Địa chỉ</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Số SP đang tồn</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Trạng thái</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-if="warehouses.data.length === 0">
                            <td colspan="6" class="px-4 py-10 text-center text-slate-400">
                                Chưa có kho hàng nào.
                            </td>
                        </tr>

                        <tr
                            v-for="warehouse in warehouses.data"
                            :key="warehouse.id"
                            class="transition-colors hover:bg-slate-50"
                        >
                            <td class="px-4 py-3 font-mono text-slate-600">{{ warehouse.code }}</td>
                            <td class="px-4 py-3 font-medium text-slate-800">{{ warehouse.name }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ warehouse.address ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ warehouse.stocks_count ?? 0 }}</td>
                            <td class="px-4 py-3">
                                <Badge :variant="warehouse.is_active ? 'green' : 'gray'">
                                    {{ warehouse.is_active ? 'Hoạt động' : 'Ngừng hoạt động' }}
                                </Badge>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <Link
                                        :href="`/admin/warehouses/${warehouse.id}`"
                                        class="font-medium text-slate-600 hover:text-slate-800"
                                    >
                                        Xem
                                    </Link>
                                    <Link
                                        :href="`/admin/warehouses/${warehouse.id}/edit`"
                                        class="font-medium text-indigo-600 hover:text-indigo-800"
                                    >
                                        Sửa
                                    </Link>
                                    <button
                                        class="font-medium text-red-500 hover:text-red-700"
                                        @click="destroyWarehouse(warehouse)"
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
                v-if="warehouses.last_page > 1"
                class="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-sm"
            >
                <span class="text-slate-500">
                    Hiển thị {{ warehouses.from }}–{{ warehouses.to }} / {{ warehouses.total }} kho hàng
                </span>
                <div class="flex gap-1">
                    <Link
                        v-for="link in warehouses.links"
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
