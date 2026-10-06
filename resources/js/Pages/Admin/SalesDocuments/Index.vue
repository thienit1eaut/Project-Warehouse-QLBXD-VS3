<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Badge from '@/Components/UI/Badge.vue';
import { usePermission } from '@/Composables/usePermission';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    pageTitle: { type: String, default: '' },
    documents: { type: Object, required: true },
    filters: { type: Object, default: () => ({ search: '', status: '' }) },
});

const { can } = usePermission();
const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');

const money = (v) => Number(v ?? 0).toLocaleString('vi-VN');

function applyFilters() {
    router.get('/admin/sales', { search: search.value, status: status.value }, {
        preserveState: true,
        replace: true,
    });
}

let debounceTimer;
watch(search, () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(applyFilters, 400);
});
watch(status, applyFilters);
</script>

<template>
    <Head :title="pageTitle" />

    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-3">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Tìm theo mã chứng từ..."
                    class="w-60 rounded-lg border border-slate-300 px-3 py-2 text-sm
                           focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
                <select
                    v-model="status"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm
                           focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
                    <option value="">Tất cả trạng thái</option>
                    <option value="draft">Nháp</option>
                    <option value="posted">Đã xuất kho</option>
                </select>
            </div>
            <Link
                v-if="can('sales-document.create')"
                href="/admin/sales/create"
                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700"
            >
                + Tạo chứng từ bán
            </Link>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50 text-left">
                            <th class="px-4 py-3 font-medium text-slate-600">Mã chứng từ</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Khách hàng</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Kho xuất</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Ngày</th>
                            <th class="px-4 py-3 text-right font-medium text-slate-600">Tổng tiền</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Trạng thái</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-if="documents.data.length === 0">
                            <td colspan="7" class="px-4 py-10 text-center text-slate-400">Chưa có chứng từ bán hàng.</td>
                        </tr>
                        <tr v-for="d in documents.data" :key="d.id" class="transition-colors hover:bg-slate-50">
                            <td class="px-4 py-3 font-mono text-slate-700">{{ d.document_code }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ d.customer ? d.customer.name : 'Khách vãng lai' }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ d.warehouse?.name }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ d.document_date }}</td>
                            <td class="px-4 py-3 text-right text-slate-700">{{ money(d.total_amount) }}</td>
                            <td class="px-4 py-3">
                                <Badge :variant="d.status === 'posted' ? 'green' : 'gray'">
                                    {{ d.status === 'posted' ? 'Đã xuất kho' : 'Nháp' }}
                                </Badge>
                            </td>
                            <td class="space-x-3 px-4 py-3 text-right">
                                <Link :href="`/admin/sales/${d.id}`" class="font-medium text-indigo-600 hover:text-indigo-800">Xem</Link>
                                <Link
                                    v-if="d.status === 'draft' && can('sales-document.update')"
                                    :href="`/admin/sales/${d.id}/edit`"
                                    class="font-medium text-slate-600 hover:text-slate-800"
                                >Sửa</Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="documents.last_page > 1"
                class="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-sm"
            >
                <span class="text-slate-500">
                    Hiển thị {{ documents.from }}–{{ documents.to }} / {{ documents.total }} chứng từ
                </span>
                <div class="flex gap-1">
                    <Link
                        v-for="link in documents.links"
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