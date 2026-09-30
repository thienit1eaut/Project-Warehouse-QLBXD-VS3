<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Badge from '@/Components/UI/Badge.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    pageTitle: { type: String, default: '' },
    warehouse: { type: Object, required: true },
    stocks:    { type: Object, required: true }, // Laravel paginator
    filters:   { type: Object, default: () => ({ search: '' }) },
});

const search = ref(props.filters.search ?? '');

let debounceTimer;
watch(search, () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        router.get(`/admin/warehouses/${props.warehouse.id}`,
            { search: search.value },
            { preserveState: true, replace: true }
        );
    }, 400);
});
</script>

<template>
    <Head :title="pageTitle" />

    <div class="space-y-4">

        <div class="flex items-center justify-between">
            <h1 class="text-lg font-semibold text-slate-800">
                {{ warehouse.name }} ({{ warehouse.code }})
            </h1>
            <Link href="/admin/warehouses" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
                ← Quay lại danh sách kho
            </Link>
        </div>

        <div class="grid grid-cols-1 gap-4 rounded-xl border border-slate-200 bg-white p-4 text-sm sm:grid-cols-3">
            <div>
                <span class="text-slate-400">Địa chỉ:</span>
                <p class="text-slate-700">{{ warehouse.address ?? '—' }}</p>
            </div>
            <div>
                <span class="text-slate-400">Trạng thái:</span>
                <p>
                    <Badge :variant="warehouse.is_active ? 'green' : 'gray'">
                        {{ warehouse.is_active ? 'Hoạt động' : 'Ngừng hoạt động' }}
                    </Badge>
                </p>
            </div>
            <div>
                <span class="text-slate-400">Mô tả:</span>
                <p class="text-slate-700">{{ warehouse.description ?? '—' }}</p>
            </div>
        </div>

        <h2 class="text-base font-medium text-slate-800">Tồn kho hiện tại</h2>

        <input
            v-model="search"
            type="text"
            placeholder="Tìm theo tên hoặc SKU sản phẩm..."
            class="w-60 rounded-lg border border-slate-300 px-3 py-2 text-sm
                   focus:outline-none focus:ring-2 focus:ring-indigo-500"
        />

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50 text-left">
                            <th class="px-4 py-3 font-medium text-slate-600">SKU</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Sản phẩm</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Đơn vị</th>
                            <th class="px-4 py-3 text-right font-medium text-slate-600">Tồn hiện tại</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-if="stocks.data.length === 0">
                            <td colspan="5" class="px-4 py-10 text-center text-slate-400">
                                Kho này chưa có sản phẩm tồn kho.
                            </td>
                        </tr>

                        <tr v-for="stock in stocks.data" :key="stock.id" class="transition-colors hover:bg-slate-50">
                            <td class="px-4 py-3 font-mono text-slate-600">{{ stock.product.sku }}</td>
                            <td class="px-4 py-3 font-medium text-slate-800">{{ stock.product.name }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ stock.product.unit?.name ?? '—' }}</td>
                            <td class="px-4 py-3 text-right text-slate-700">{{ stock.quantity_on_hand }}</td>
                            <td class="px-4 py-3 text-right">
                                <Link :href="`/admin/stock/${stock.id}`" class="font-medium text-indigo-600 hover:text-indigo-800">
                                    Xem lịch sử
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
                    Hiển thị {{ stocks.from }}–{{ stocks.to }} / {{ stocks.total }} sản phẩm
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
