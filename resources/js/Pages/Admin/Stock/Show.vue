<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Badge from '@/Components/UI/Badge.vue';

defineOptions({ layout: AdminLayout });

defineProps({
    pageTitle: { type: String, default: '' },
    stock:     { type: Object, required: true },
    movements: { type: Object, required: true }, // Laravel paginator
});

// Badge.vue chỉ nhận: gray | green | red | blue | yellow | purple
const movementBadgeVariant = {
    in: 'green',
    out: 'red',
    adjustment: 'yellow',
};

const movementLabel = {
    in: 'Nhập kho',
    out: 'Xuất kho',
    adjustment: 'Điều chỉnh',
};
</script>

<template>
    <Head :title="pageTitle" />

    <div class="space-y-4">

        <div class="flex items-center justify-between">
            <h1 class="text-lg font-semibold text-slate-800">
                {{ stock.product.name }} — {{ stock.warehouse.name }}
            </h1>
            <Link href="/admin/stock" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
                ← Quay lại danh sách tồn kho
            </Link>
        </div>

        <div class="grid grid-cols-1 gap-4 rounded-xl border border-slate-200 bg-white p-4 text-sm sm:grid-cols-4">
            <div>
                <span class="text-slate-400">Kho:</span>
                <p class="text-slate-700">{{ stock.warehouse.name }} ({{ stock.warehouse.code }})</p>
            </div>
            <div>
                <span class="text-slate-400">SKU:</span>
                <p class="font-mono text-slate-700">{{ stock.product.sku }}</p>
            </div>
            <div>
                <span class="text-slate-400">Đơn vị:</span>
                <p class="text-slate-700">{{ stock.product.unit?.name ?? '—' }}</p>
            </div>
            <div>
                <span class="text-slate-400">Tồn hiện tại:</span>
                <p class="text-lg font-semibold text-slate-800">{{ stock.quantity_on_hand }}</p>
            </div>
        </div>

        <div>
            <h2 class="text-base font-medium text-slate-800">Lịch sử giao dịch</h2>
            <p class="text-xs text-slate-400">
                Đây là sổ giao dịch (ledger) bất biến, không thể sửa hoặc xoá.
            </p>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50 text-left">
                            <th class="px-4 py-3 font-medium text-slate-600">Ngày</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Loại</th>
                            <th class="px-4 py-3 text-right font-medium text-slate-600">Số lượng</th>
                            <th class="px-4 py-3 text-right font-medium text-slate-600">Trước</th>
                            <th class="px-4 py-3 text-right font-medium text-slate-600">Sau</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Người thực hiện</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Tham chiếu</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Ghi chú</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-if="movements.data.length === 0">
                            <td colspan="8" class="px-4 py-10 text-center text-slate-400">
                                Chưa có giao dịch nào.
                            </td>
                        </tr>

                        <tr v-for="movement in movements.data" :key="movement.id" class="transition-colors hover:bg-slate-50">
                            <td class="px-4 py-3 whitespace-nowrap text-slate-500">{{ movement.created_at }}</td>
                            <td class="px-4 py-3">
                                <Badge :variant="movementBadgeVariant[movement.movement_type] ?? 'gray'">
                                    {{ movementLabel[movement.movement_type] ?? movement.movement_type }}
                                </Badge>
                            </td>
                            <td
                                class="px-4 py-3 text-right font-medium"
                                :class="movement.quantity < 0 ? 'text-red-600' : 'text-green-700'"
                            >
                                {{ movement.quantity > 0 ? '+' : '' }}{{ movement.quantity }}
                            </td>
                            <td class="px-4 py-3 text-right text-slate-500">{{ movement.quantity_before }}</td>
                            <td class="px-4 py-3 text-right text-slate-500">{{ movement.quantity_after }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ movement.user?.name ?? 'Hệ thống' }}</td>
                            <td class="px-4 py-3 text-slate-500">
                                <span v-if="movement.reference_type">
                                    {{ movement.reference_type }} #{{ movement.reference_id }}
                                </span>
                                <span v-else>—</span>
                            </td>
                            <td class="px-4 py-3 text-slate-500">{{ movement.note ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="movements.last_page > 1"
                class="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-sm"
            >
                <span class="text-slate-500">
                    Hiển thị {{ movements.from }}–{{ movements.to }} / {{ movements.total }} giao dịch
                </span>
                <div class="flex gap-1">
                    <Link
                        v-for="link in movements.links"
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
