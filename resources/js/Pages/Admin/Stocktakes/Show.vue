<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Badge from '@/Components/UI/Badge.vue';
import { usePermission } from '@/Composables/usePermission';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    pageTitle: { type: String, default: '' },
    stocktake: { type: Object, required: true },
});

const { can } = usePermission();
const page = usePage();
const processing = ref(false);

const isDraft = computed(() => props.stocktake.status === 'draft');
const firstError = computed(() => Object.values(page.props.errors ?? {})[0] ?? null);
const fmt = (v) => Number(v ?? 0).toLocaleString('vi-VN', { maximumFractionDigits: 3 });

function diffText(v) {
    const n = Number(v ?? 0);
    return n > 0 ? `+${fmt(n)}` : fmt(n);
}

function diffClass(v) {
    const n = Number(v ?? 0);
    if (n > 0) return 'text-green-700';
    if (n < 0) return 'text-red-600';
    return 'text-slate-500';
}

function postStocktake() {
    if (!window.confirm('Chốt phiếu kiểm kê? Tồn kho sẽ được điều chỉnh theo số thực tế. Sau khi chốt không thể sửa, xoá hoặc chốt lại.')) return;

    processing.value = true;
    router.post(`/admin/stocktake/${props.stocktake.id}/post`, {}, {
        preserveScroll: true,
        onFinish: () => (processing.value = false),
    });
}

function deleteStocktake() {
    if (!window.confirm('Xoá phiếu kiểm kê nháp này?')) return;
    router.delete(`/admin/stocktake/${props.stocktake.id}`);
}
</script>

<template>
    <Head :title="pageTitle" />

    <div class="max-w-4xl space-y-4">
        <div class="flex items-center justify-between">
            <h1 class="text-lg font-semibold text-slate-800">Phiếu kiểm kê {{ stocktake.stocktake_code }}</h1>
            <Link href="/admin/stocktake" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
                ← Quay lại danh sách
            </Link>
        </div>

        <p v-if="firstError" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ firstError }}
        </p>

        <p
            v-if="isDraft && stocktake.has_stale_items"
            class="rounded-lg border border-yellow-300 bg-yellow-50 px-4 py-3 text-sm text-yellow-800"
        >
            Tồn kho của một số sản phẩm đã thay đổi kể từ khi phiếu được tạo (xem cột "Tồn hiện tại").
            Hệ thống sẽ từ chối chốt phiếu cho tới khi bạn kiểm tra lại số đếm, bấm Sửa rồi Lưu để chụp lại tồn hệ thống.
        </p>

        <div class="grid grid-cols-1 gap-4 rounded-xl border border-slate-200 bg-white p-4 text-sm sm:grid-cols-4">
            <div>
                <span class="text-slate-400">Kho:</span>
                <p class="text-slate-700">{{ stocktake.warehouse?.code }} - {{ stocktake.warehouse?.name }}</p>
            </div>
            <div>
                <span class="text-slate-400">Ngày kiểm kê:</span>
                <p class="text-slate-700">{{ stocktake.stocktake_date }}</p>
            </div>
            <div>
                <span class="text-slate-400">Trạng thái:</span>
                <p>
                    <Badge :variant="isDraft ? 'gray' : 'green'">{{ isDraft ? 'Nháp' : 'Đã chốt' }}</Badge>
                </p>
            </div>
            <div>
                <span class="text-slate-400">Người tạo:</span>
                <p class="text-slate-700">{{ stocktake.creator?.name ?? '—' }}</p>
            </div>
            <div v-if="!isDraft">
                <span class="text-slate-400">Thời điểm chốt:</span>
                <p class="text-slate-700">{{ stocktake.posted_at }}</p>
            </div>
            <div class="sm:col-span-4">
                <span class="text-slate-400">Ghi chú:</span>
                <p class="text-slate-700">{{ stocktake.note ?? '—' }}</p>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50 text-left">
                        <th class="px-4 py-3 font-medium text-slate-600">SKU</th>
                        <th class="px-4 py-3 font-medium text-slate-600">Sản phẩm</th>
                        <th class="px-4 py-3 text-right font-medium text-slate-600">Tồn hệ thống (snapshot)</th>
                        <th class="px-4 py-3 text-right font-medium text-slate-600">Thực tế</th>
                        <th class="px-4 py-3 text-right font-medium text-slate-600">Chênh lệch</th>
                        <th v-if="isDraft" class="px-4 py-3 text-right font-medium text-slate-600">Tồn hiện tại</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr v-for="item in stocktake.items" :key="item.id" :class="item.is_stale ? 'bg-yellow-50' : ''">
                        <td class="px-4 py-3 font-mono text-slate-600">{{ item.product?.sku }}</td>
                        <td class="px-4 py-3 font-medium text-slate-800">{{ item.product?.name }}</td>
                        <td class="px-4 py-3 text-right text-slate-700">{{ fmt(item.system_quantity) }}</td>
                        <td class="px-4 py-3 text-right text-slate-700">{{ fmt(item.actual_quantity) }}</td>
                        <td class="px-4 py-3 text-right font-semibold" :class="diffClass(item.difference)">
                            {{ diffText(item.difference) }}
                        </td>
                        <td v-if="isDraft" class="px-4 py-3 text-right" :class="item.is_stale ? 'font-semibold text-yellow-800' : 'text-slate-500'">
                            {{ fmt(item.current_quantity) }}
                            <span v-if="item.is_stale">(đã đổi)</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="isDraft" class="flex flex-wrap items-center gap-3">
            <button
                v-if="can('stocktake.post')"
                @click="postStocktake"
                :disabled="processing"
                class="rounded-lg bg-green-600 px-5 py-2.5 text-sm font-medium text-white
                       transition hover:bg-green-700 disabled:cursor-not-allowed disabled:opacity-60"
            >
                <span v-if="processing">Đang chốt phiếu...</span>
                <span v-else>POST - Chốt kiểm kê</span>
            </button>
            <Link
                v-if="can('stocktake.update')"
                :href="`/admin/stocktake/${stocktake.id}/edit`"
                class="rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
            >Sửa</Link>
            <button
                v-if="can('stocktake.delete')"
                @click="deleteStocktake"
                class="rounded-lg border border-red-300 px-5 py-2.5 text-sm font-medium text-red-700 hover:bg-red-50"
            >Xoá nháp</button>
        </div>
        <p v-else class="text-sm font-medium text-green-700">Đã chốt kiểm kê. Phiếu chỉ đọc.</p>
    </div>
</template>