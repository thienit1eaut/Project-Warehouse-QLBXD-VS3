<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    pageTitle:        { type: String, default: '' },
    stocks:           { type: Object, required: true }, // Laravel paginator
    filters:          { type: Object, default: () => ({ search: '', warehouse_id: '' }) },
    warehouseOptions: { type: Array, default: () => [] },
});

const search = ref(props.filters.search ?? '');
const warehouseId = ref(props.filters.warehouse_id ?? '');

function applyFilters() {
    router.get('/admin/stock', {
        search: search.value,
        warehouse_id: warehouseId.value,
    }, {
        preserveState: true,
        replace: true,
    });
}

let debounceTimer;
watch(search, () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(applyFilters, 400);
});

watch(warehouseId, applyFilters);
</script>

<template>
    <Head :title="pageTitle" />

    <div class="space-y-4">

        <div class="flex flex-wrap items-center gap-3">
            <input
                v-model="search"
                type="text"
                placeholder="Tìm theo tên hoặc SKU sản phẩm..."
                class="w-60 rounded-lg border border-slate-300 px-3 py-2 text-sm
                       focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
            <select
                v-model="warehouseId"
                class="rounded-lg border border-slate-300 px-3 py-2 text-sm
                       focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
                <option value="">Tất cả kho</option>
                <option v-for="wh in warehouseOptions" :key="wh.id" :value="wh.id">
                    {{ wh.name }}
                </option>
            </select>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50 text-left">
                            <th class="px-4 py-3 font-medium text-slate-600">Kho</th>
                            <th class="px-4 py-3 font-medium text-slate-600">SKU</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Sản phẩm</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Đơn vị</th>
                            <th class="px-4 py-3 text-right font-medium text-slate-600">Tồn hiện tại</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-if="stocks.data.length === 0">
                            <td colspan="6" class="px-4 py-10 text-center text-slate-400">
                                Không có dữ liệu tồn kho.
                            </td>
                        </tr>

                        <tr v-for="stock in stocks.data" :key="stock.id" class="transition-colors hover:bg-slate-50">
                            <td class="px-4 py-3 text-slate-700">{{ stock.warehouse.name }}</td>
                            <td class="px-4 py-3 font-mono text-slate-600">{{ stock.product.sku }}</td>
                            <td class="px-4 py-3 font-medium text-slate-800">{{ stock.product.name }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ stock.product.unit?.name ?? '—' }}</td>
                            <td class="px-4 py-3 text-right text-slate-700">{{ stock.quantity_on_hand }}</td>
                            <td class="px-4 py-3 text-right">
                                <Link :href="`/admin/stock/${stock.id}`" class="font-medium text-indigo-600 hover:text-indigo-800">
                                    Xem
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="stocks.last_page > 1"
                class="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-sm"
            >
                <span class="text-slate-500">
                    Hiển thị {{ stocks.from }}–{{ stocks.to }} / {{ stocks.total }} dòng
                </span>
                <div class="flex gap-1">
                    <Link
                        v-for="link in stocks.links"
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
