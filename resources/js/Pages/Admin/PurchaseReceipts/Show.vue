<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Badge from '@/Components/UI/Badge.vue';
import { usePermission } from '@/Composables/usePermission';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    pageTitle: { type: String, default: '' },
    receipt: { type: Object, required: true },
});

const { can } = usePermission();
const page = usePage();
const processing = ref(false);

const isDraft = computed(() => props.receipt.status === 'draft');
const receiptError = computed(() => page.props.errors?.receipt ?? null);

function postReceipt() {
    if (!window.confirm('Xác nhận nhập kho? Sau khi POST sẽ không thể sửa hoặc POST lại.')) return;

    processing.value = true;
    router.post(`/admin/purchase-receipts/${props.receipt.id}/post`, {}, {
        preserveScroll: true,
        onFinish: () => (processing.value = false),
    });
}
</script>

<template>
    <Head :title="pageTitle" />

    <div class="max-w-4xl space-y-4">
        <div class="flex items-center justify-between">
            <h1 class="text-lg font-semibold text-slate-800">Phiếu nhập {{ receipt.receipt_code }}</h1>
            <Link href="/admin/purchase-receipts" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
                ← Quay lại danh sách
            </Link>
        </div>

        <p v-if="receiptError" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ receiptError }}
        </p>

        <div class="grid grid-cols-1 gap-4 rounded-xl border border-slate-200 bg-white p-4 text-sm sm:grid-cols-4">
            <div>
                <span class="text-slate-400">Nhà cung cấp:</span>
                <p class="text-slate-700">{{ receipt.supplier?.code }} - {{ receipt.supplier?.name }}</p>
            </div>
            <div>
                <span class="text-slate-400">Kho nhập:</span>
                <p class="text-slate-700">{{ receipt.warehouse?.code }} - {{ receipt.warehouse?.name }}</p>
            </div>
            <div>
                <span class="text-slate-400">Ngày nhập:</span>
                <p class="text-slate-700">{{ receipt.receipt_date }}</p>
            </div>
            <div>
                <span class="text-slate-400">Trạng thái:</span>
                <p>
                    <Badge :variant="isDraft ? 'gray' : 'green'">{{ isDraft ? 'Nháp' : 'Đã nhập kho' }}</Badge>
                </p>
            </div>
            <div class="sm:col-span-4">
                <span class="text-slate-400">Ghi chú:</span>
                <p class="text-slate-700">{{ receipt.note ?? '—' }}</p>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50 text-left">
                        <th class="px-4 py-3 font-medium text-slate-600">SKU</th>
                        <th class="px-4 py-3 font-medium text-slate-600">Sản phẩm</th>
                        <th class="px-4 py-3 text-right font-medium text-slate-600">Số lượng</th>
                        <th class="px-4 py-3 text-right font-medium text-slate-600">Đơn giá</th>
                        <th class="px-4 py-3 font-medium text-slate-600">Hạn sử dụng</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr v-for="item in receipt.items" :key="item.id">
                        <td class="px-4 py-3 font-mono text-slate-600">{{ item.product?.sku }}</td>
                        <td class="px-4 py-3 font-medium text-slate-800">{{ item.product?.name }}</td>
                        <td class="px-4 py-3 text-right text-slate-700">{{ item.quantity }}</td>
                        <td class="px-4 py-3 text-right text-slate-700">{{ item.unit_price ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ item.expiry_date ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="isDraft && can('purchase-receipt.post')">
            <button
                @click="postReceipt"
                :disabled="processing"
                class="rounded-lg bg-green-600 px-5 py-2.5 text-sm font-medium text-white
                       transition hover:bg-green-700 disabled:cursor-not-allowed disabled:opacity-60"
            >
                <span v-if="processing">Đang nhập kho...</span>
                <span v-else>POST RECEIPT</span>
            </button>
        </div>
        <p v-else-if="!isDraft" class="text-sm font-medium text-green-700">Đã nhập kho.</p>
    </div>
</template>