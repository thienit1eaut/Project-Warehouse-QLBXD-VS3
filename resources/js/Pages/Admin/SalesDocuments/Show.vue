<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Badge from '@/Components/UI/Badge.vue';
import { usePermission } from '@/Composables/usePermission';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    pageTitle: { type: String, default: '' },
    document: { type: Object, required: true },
});

const { can } = usePermission();
const page = usePage();
const processing = ref(false);

const isDraft = computed(() => props.document.status === 'draft');
const firstError = computed(() => Object.values(page.props.errors ?? {})[0] ?? null);
const money = (v) => Number(v ?? 0).toLocaleString('vi-VN');

function postDocument() {
    if (!window.confirm('Xác nhận xuất kho? Sau khi POST sẽ không thể sửa, xoá hoặc POST lại.')) return;

    processing.value = true;
    router.post(`/admin/sales/${props.document.id}/post`, {}, {
        preserveScroll: true,
        onFinish: () => (processing.value = false),
    });
}

function deleteDocument() {
    if (!window.confirm('Xoá chứng từ nháp này?')) return;
    router.delete(`/admin/sales/${props.document.id}`);
}
</script>

<template>
    <Head :title="pageTitle" />

    <div class="max-w-4xl space-y-4">
        <div class="flex items-center justify-between">
            <h1 class="text-lg font-semibold text-slate-800">Chứng từ bán {{ document.document_code }}</h1>
            <Link href="/admin/sales" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
                ← Quay lại danh sách
            </Link>
        </div>

        <p v-if="firstError" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ firstError }}
        </p>

        <div class="grid grid-cols-1 gap-4 rounded-xl border border-slate-200 bg-white p-4 text-sm sm:grid-cols-4">
            <div>
                <span class="text-slate-400">Khách hàng:</span>
                <p class="text-slate-700">
                    {{ document.customer ? `${document.customer.customer_code} - ${document.customer.name}` : 'Khách vãng lai' }}
                </p>
            </div>
            <div>
                <span class="text-slate-400">Kho xuất:</span>
                <p class="text-slate-700">{{ document.warehouse?.code }} - {{ document.warehouse?.name }}</p>
            </div>
            <div>
                <span class="text-slate-400">Ngày chứng từ:</span>
                <p class="text-slate-700">{{ document.document_date }}</p>
            </div>
            <div>
                <span class="text-slate-400">Trạng thái:</span>
                <p>
                    <Badge :variant="isDraft ? 'gray' : 'green'">{{ isDraft ? 'Nháp' : 'Đã xuất kho' }}</Badge>
                </p>
            </div>
            <div>
                <span class="text-slate-400">Người tạo:</span>
                <p class="text-slate-700">{{ document.creator?.name ?? '—' }}</p>
            </div>
            <div v-if="!isDraft">
                <span class="text-slate-400">Thời điểm POST:</span>
                <p class="text-slate-700">{{ document.posted_at }}</p>
            </div>
            <div class="sm:col-span-4">
                <span class="text-slate-400">Ghi chú:</span>
                <p class="text-slate-700">{{ document.note ?? '—' }}</p>
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
                        <th class="px-4 py-3 text-right font-medium text-slate-600">Thành tiền</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr v-for="item in document.items" :key="item.id">
                        <td class="px-4 py-3 font-mono text-slate-600">{{ item.product?.sku }}</td>
                        <td class="px-4 py-3 font-medium text-slate-800">{{ item.product?.name }}</td>
                        <td class="px-4 py-3 text-right text-slate-700">{{ item.quantity }}</td>
                        <td class="px-4 py-3 text-right text-slate-700">{{ money(item.unit_price) }}</td>
                        <td class="px-4 py-3 text-right text-slate-700">{{ money(item.line_total) }}</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="border-t border-slate-200 bg-slate-50">
                        <td colspan="4" class="px-4 py-3 text-right font-medium text-slate-600">Tổng tiền</td>
                        <td class="px-4 py-3 text-right font-semibold text-slate-800">{{ money(document.total_amount) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div v-if="isDraft" class="flex flex-wrap items-center gap-3">
            <button
                v-if="can('sales-document.post')"
                @click="postDocument"
                :disabled="processing"
                class="rounded-lg bg-green-600 px-5 py-2.5 text-sm font-medium text-white
                       transition hover:bg-green-700 disabled:cursor-not-allowed disabled:opacity-60"
            >
                <span v-if="processing">Đang xuất kho...</span>
                <span v-else>POST - Xuất kho</span>
            </button>
            <Link
                v-if="can('sales-document.update')"
                :href="`/admin/sales/${document.id}/edit`"
                class="rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
            >Sửa</Link>
            <button
                v-if="can('sales-document.delete')"
                @click="deleteDocument"
                class="rounded-lg border border-red-300 px-5 py-2.5 text-sm font-medium text-red-700 hover:bg-red-50"
            >Xoá nháp</button>
        </div>
        <p v-else class="text-sm font-medium text-green-700">Đã xuất kho. Chứng từ chỉ đọc.</p>
    </div>
</template>