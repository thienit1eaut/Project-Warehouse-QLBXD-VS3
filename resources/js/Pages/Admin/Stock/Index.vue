<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    pageTitle:        { type: String, default: '' },
    rows:             { type: Object, required: true }, // Laravel paginator
    filters:          { type: Object, default: () => ({}) },
    warehouseOptions: { type: Array, default: () => [] },
    categoryOptions:  { type: Array, default: () => [] },
    brandOptions:     { type: Array, default: () => [] },
});

const search = ref(props.filters.search ?? '');
const warehouseId = ref(props.filters.warehouse_id ?? '');
const categoryId = ref(props.filters.category_id ?? '');
const brandId = ref(props.filters.brand_id ?? '');
const status = ref(props.filters.status ?? '');

const statusMeta = {
    out_of_stock: { label: 'Hết hàng',   class: 'bg-red-50 text-red-700' },
    low:          { label: 'Tồn thấp',   class: 'bg-yellow-50 text-yellow-800' },
    normal:       { label: 'Bình thường', class: 'bg-green-50 text-green-700' },
};

const fmt = (v) => Number(v ?? 0).toLocaleString('vi-VN', { maximumFractionDigits: 3 });

function applyFilters() {
    const params = {
        search: search.value,
        warehouse_id: warehouseId.value,
        category_id: categoryId.value,
        brand_id: brandId.value,
        status: status.value,
    };
    // Bỏ tham số rỗng để URL gọn.
    Object.keys(params).forEach((k) => (params[k] === '' || params[k] === null) && delete params[k]);

    router.get('/admin/stock', params, { preserveState: true, replace: true });
}

let debounceTimer;
watch(search, () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(applyFilters, 400);
});
watch([warehouseId, categoryId, brandId, status], applyFilters);

const selectClass = `rounded-lg border border-slate-300 px-3 py-2 text-sm
    focus:outline-none focus:ring-2 focus:ring-indigo-500`;
</script>

<template>
    <Head :title="pageTitle" />

    <div class="space-y-4">
        <div class="flex flex-wrap items-center gap-3">
            <input
                v-model="search"
                type="text"
                placeholder="Tìm theo SKU hoặc tên sản phẩm..."
                class="w-64 rounded-lg border border-slate-300 px-3 py-2 text-sm
                       focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
            <select v-model="warehouseId" :class="selectClass">
                <option value="">Tất cả kho</option>
                <option v-for="wh in warehouseOptions" :key="wh.id" :value="wh.id">{{ wh.name }}</option>
            </select>
            <select v-model="categoryId" :class="selectClass">
                <option value="">Tất cả danh mục</option>
                <option v-for="c in categoryOptions" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
            <select v-model="brandId" :class="selectClass">
                <option value="">Tất cả thương hiệu</option>
                <option v-for="b in brandOptions" :key="b.id" :value="b.id">{{ b.name }}</option>
            </select>
            <select v-model="status" :class="selectClass">
                <option value="">Mọi trạng thái</option>
                <option value="normal">Bình thường</option>
                <option value="low">Tồn thấp</option>
                <option value="out_of_stock">Hết hàng</option>
            </select>
        </div>

        <p class="text-xs text-slate-400">
            Trạng thái được tính theo từng kho (tồn của kho so với mức tồn tối thiểu của sản phẩm).
            Cột "Tổng tồn SP" là tổng trên mọi kho.
        </p>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50 text-left">
                            <th class="px-4 py-3 font-medium text-slate-600">Kho</th>
                            <th class="px-4 py-3 font-medium text-slate-600">SKU</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Sản phẩm</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Danh mục</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Thương hiệu</th>
                            <th class="px-4 py-3 text-right font-medium text-slate-600">Tồn kho</th>
                            <th class="px-4 py-3 text-right font-medium text-slate-600">Tổng tồn SP</th>
                            <th class="px-4 py-3 text-right font-medium text-slate-600">Lô</th>
                            <th class="px-4 py-3 text-right font-medium text-slate-600">Tối thiểu</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Trạng thái</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-if="rows.data.length === 0">
                            <td colspan="11" class="px-4 py-10 text-center text-slate-400">
                                Không có dữ liệu tồn kho.
                            </td>
                        </tr>

                        <tr
                            v-for="row in rows.data"
                            :key="`${row.product_id}-${row.warehouse?.id ?? 0}`"
                            class="transition-colors hover:bg-slate-50"
                        >
                            <td class="px-4 py-3 text-slate-700">{{ row.warehouse?.name ?? '—' }}</td>
                            <td class="px-4 py-3 font-mono text-slate-600">{{ row.sku }}</td>
                            <td class="px-4 py-3 font-medium text-slate-800">{{ row.name }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ row.category ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ row.brand ?? '—' }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-slate-800">
                                {{ fmt(row.on_hand) }}
                                <span class="text-xs font-normal text-slate-400">{{ row.unit }}</span>
                            </td>
                            <td class="px-4 py-3 text-right text-slate-600">{{ fmt(row.product_total) }}</td>
                            <td class="px-4 py-3 text-right text-slate-600">{{ row.lots_count }}</td>
                            <td class="px-4 py-3 text-right text-slate-500">{{ fmt(row.minimum_stock) }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    :class="statusMeta[row.status]?.class"
                                >
                                    {{ statusMeta[row.status]?.label ?? row.status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Link
                                    v-if="row.stock_id"
                                    :href="`/admin/stock/${row.stock_id}`"
                                    class="font-medium text-indigo-600 hover:text-indigo-800"
                                >
                                    Xem
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="rows.last_page > 1"
                class="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-sm"
            >
                <span class="text-slate-500">
                    Hiển thị {{ rows.from }}–{{ rows.to }} / {{ rows.total }} dòng
                </span>
                <div class="flex gap-1">
                    <Link
                        v-for="link in rows.links"
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