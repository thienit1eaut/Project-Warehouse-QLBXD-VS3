<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { usePermission } from '@/Composables/usePermission';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    pageTitle: { type: String, default: 'Dashboard' },
    inventory: { type: Object, default: null }, // null khi không có quyền stock.view
});

const page = usePage();
const user = computed(() => page.props.auth?.user);
const { can } = usePermission();

const fmt = (v) => Number(v ?? 0).toLocaleString('vi-VN', { maximumFractionDigits: 3 });

const cards = computed(() => {
    const k = props.inventory?.kpis;
    if (!k) return [];

    return [
        { label: 'Sản phẩm (đang bán)', value: fmt(k.total_products), text: 'text-indigo-600', href: null },
        { label: 'Kho hoạt động', value: fmt(k.total_warehouses), text: 'text-blue-600', href: null },
        { label: 'Tổng số lượng tồn', value: fmt(k.total_on_hand), text: 'text-slate-800', href: '/admin/stock' },
        { label: 'Sản phẩm hết hàng', value: fmt(k.out_of_stock_products), text: 'text-red-600', href: '/admin/stock?status=out_of_stock' },
        { label: 'Sản phẩm tồn thấp', value: fmt(k.low_stock_products), text: 'text-yellow-700', href: '/admin/stock?status=low' },
    ];
});

const draftRows = computed(() => {
    const d = props.inventory?.drafts;
    if (!d) return [];

    return [
        { label: 'Phiếu nhập kho', value: d.purchase_receipts, href: '/admin/purchase-receipts?status=draft', permission: 'purchase-receipt.view' },
        { label: 'Chứng từ bán', value: d.sales_documents, href: '/admin/sales?status=draft', permission: 'sales-document.view' },
        { label: 'Chuyển kho', value: d.stock_transfers, href: '/admin/stock-transfer?status=draft', permission: 'stock-transfer.view' },
        { label: 'Phiếu kiểm kê', value: d.stocktakes, href: '/admin/stocktake?status=draft', permission: 'stocktake.view' },
    ];
});

function quantityClass(m) {
    if (m.quantity > 0) return 'text-green-700';
    if (m.quantity < 0) return 'text-red-600';
    return 'text-slate-500';
}

const signed = (v) => (v > 0 ? `+${fmt(v)}` : fmt(v));
</script>

<template>
    <Head :title="pageTitle" />

    <div class="space-y-6">
        <div class="rounded-xl border border-slate-200 bg-white p-6">
            <h2 class="text-xl font-semibold text-slate-800">Xin chào, {{ user?.name }} 👋</h2>
            <p class="mt-1 text-sm text-slate-500">Tổng quan tình trạng kho hàng hôm nay.</p>
        </div>

        <p
            v-if="!inventory"
            class="rounded-xl border border-slate-200 bg-white p-6 text-sm text-slate-500"
        >
            Bạn chưa được cấp quyền xem số liệu tồn kho.
        </p>

        <template v-else>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <component
                    :is="card.href ? Link : 'div'"
                    v-for="card in cards"
                    :key="card.label"
                    :href="card.href ?? undefined"
                    class="rounded-xl border border-slate-200 bg-white p-5 transition-shadow"
                    :class="card.href ? 'hover:shadow-sm' : ''"
                >
                    <p :class="['text-2xl font-bold', card.text]">{{ card.value }}</p>
                    <p class="mt-0.5 text-sm text-slate-500">{{ card.label }}</p>
                </component>
            </div>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <div class="rounded-xl border border-slate-200 bg-white p-5">
                    <h3 class="mb-3 text-sm font-semibold text-slate-700">
                        Chứng từ nháp ({{ inventory.drafts.total }})
                    </h3>
                    <ul class="space-y-2 text-sm">
                        <li v-for="d in draftRows" :key="d.label" class="flex items-center justify-between">
                            <component
                                :is="can(d.permission) ? Link : 'span'"
                                :href="can(d.permission) ? d.href : undefined"
                                :class="can(d.permission) ? 'text-indigo-600 hover:text-indigo-800' : 'text-slate-600'"
                            >
                                {{ d.label }}
                            </component>
                            <span class="font-semibold text-slate-800">{{ d.value }}</span>
                        </li>
                    </ul>
                </div>

                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white xl:col-span-2">
                    <h3 class="border-b border-slate-100 px-5 py-3 text-sm font-semibold text-slate-700">
                        Hoạt động kho gần đây
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-slate-100 bg-slate-50 text-left">
                                    <th class="px-4 py-2 font-medium text-slate-600">Thời gian</th>
                                    <th class="px-4 py-2 font-medium text-slate-600">Loại</th>
                                    <th class="px-4 py-2 text-right font-medium text-slate-600">Số lượng</th>
                                    <th class="px-4 py-2 font-medium text-slate-600">Sản phẩm</th>
                                    <th class="px-4 py-2 font-medium text-slate-600">Kho</th>
                                    <th class="px-4 py-2 font-medium text-slate-600">Chứng từ</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <tr v-if="inventory.recent_movements.length === 0">
                                    <td colspan="6" class="px-4 py-8 text-center text-slate-400">Chưa có hoạt động kho.</td>
                                </tr>
                                <tr v-for="m in inventory.recent_movements" :key="m.id">
                                    <td class="px-4 py-2 text-slate-500">{{ m.created_at }}</td>
                                    <td class="px-4 py-2 text-slate-700">{{ m.type_label }}</td>
                                    <td class="px-4 py-2 text-right font-semibold" :class="quantityClass(m)">{{ signed(m.quantity) }}</td>
                                    <td class="px-4 py-2 text-slate-700">{{ m.product?.sku }} - {{ m.product?.name }}</td>
                                    <td class="px-4 py-2 text-slate-500">{{ m.warehouse?.name }}</td>
                                    <td class="px-4 py-2">
                                        <template v-if="m.reference">
                                            <Link
                                                v-if="can(m.reference.permission)"
                                                :href="m.reference.url"
                                                class="text-indigo-600 hover:text-indigo-800"
                                            >{{ m.reference.code }}</Link>
                                            <span v-else class="text-slate-500">{{ m.reference.code }}</span>
                                        </template>
                                        <span v-else class="text-slate-300">—</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>