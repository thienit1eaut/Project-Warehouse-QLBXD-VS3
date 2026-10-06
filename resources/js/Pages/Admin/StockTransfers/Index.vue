<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Badge from '@/Components/UI/Badge.vue';
import { usePermission } from '@/Composables/usePermission';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    pageTitle: { type: String, default: '' },
    transfers: { type: Object, required: true },
    filters: { type: Object, default: () => ({ search: '', status: '' }) },
});

const { can } = usePermission();
const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');

function applyFilters() {
    router.get('/admin/stock-transfer', { search: search.value, status: status.value }, {
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
                    placeholder="Tìm theo mã chuyển kho..."
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
                    <option value="posted">Đã chuyển kho</option>
                </select>
            </div>
            <Link
                v-if="can('stock-transfer.create')"
                href="/admin/stock-transfer/create"
                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700"
            >
                + Tạo chứng từ chuyển kho
            </Link>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50 text-left">
                            <th class="px-4 py-3 font-medium text-slate-600">Mã chứng từ</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Kho nguồn</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Kho đích</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Ngày</th>
                            <th class="px-4 py-3 text-right font-medium text-slate-600">Số dòng</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Trạng thái</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-if="transfers.data.length === 0">
                            <td colspan="7" class="px-4 py-10 text-center text-slate-400">Chưa có chứng từ chuyển kho.</td>
                        </tr>
                        <tr v-for="t in transfers.data" :key="t.id" class="transition-colors hover:bg-slate-50">
                            <td class="px-4 py-3 font-mono text-slate-700">{{ t.transfer_code }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ t.from_warehouse?.name }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ t.to_warehouse?.name }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ t.transfer_date }}</td>
                            <td class="px-4 py-3 text-right text-slate-700">{{ t.items_count }}</td>
                            <td class="px-4 py-3">
                                <Badge :variant="t.status === 'posted' ? 'green' : 'gray'">
                                    {{ t.status === 'posted' ? 'Đã chuyển kho' : 'Nháp' }}
                                </Badge>
                            </td>
                            <td class="space-x-3 px-4 py-3 text-right">
                                <Link :href="`/admin/stock-transfer/${t.id}`" class="font-medium text-indigo-600 hover:text-indigo-800">Xem</Link>
                                <Link
                                    v-if="t.status === 'draft' && can('stock-transfer.update')"
                                    :href="`/admin/stock-transfer/${t.id}/edit`"
                                    class="font-medium text-slate-600 hover:text-slate-800"
                                >Sửa</Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="transfers.last_page > 1"
                class="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-sm"
            >
                <span class="text-slate-500">
                    Hiển thị {{ transfers.from }}–{{ transfers.to }} / {{ transfers.total }} chứng từ
                </span>
                <div class="flex gap-1">
                    <Link
                        v-for="link in transfers.links"
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