<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Badge from '@/Components/UI/Badge.vue';
import { usePermission } from '@/Composables/usePermission';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    pageTitle: { type: String, default: '' },
    stocktakes: { type: Object, required: true },
    filters: { type: Object, default: () => ({ search: '', status: '' }) },
});

const { can } = usePermission();
const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');

function applyFilters() {
    router.get('/admin/stocktake', { search: search.value, status: status.value }, {
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
                    placeholder="Tìm theo mã phiếu kiểm kê..."
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
                    <option value="posted">Đã chốt</option>
                </select>
            </div>
            <Link
                v-if="can('stocktake.create')"
                href="/admin/stocktake/create"
                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700"
            >
                + Tạo phiếu kiểm kê
            </Link>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50 text-left">
                            <th class="px-4 py-3 font-medium text-slate-600">Mã phiếu</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Kho</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Ngày kiểm kê</th>
                            <th class="px-4 py-3 text-right font-medium text-slate-600">Số dòng</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Trạng thái</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-if="stocktakes.data.length === 0">
                            <td colspan="6" class="px-4 py-10 text-center text-slate-400">Chưa có phiếu kiểm kê.</td>
                        </tr>
                        <tr v-for="s in stocktakes.data" :key="s.id" class="transition-colors hover:bg-slate-50">
                            <td class="px-4 py-3 font-mono text-slate-700">{{ s.stocktake_code }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ s.warehouse?.name }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ s.stocktake_date }}</td>
                            <td class="px-4 py-3 text-right text-slate-700">{{ s.items_count }}</td>
                            <td class="px-4 py-3">
                                <Badge :variant="s.status === 'posted' ? 'green' : 'gray'">
                                    {{ s.status === 'posted' ? 'Đã chốt' : 'Nháp' }}
                                </Badge>
                            </td>
                            <td class="space-x-3 px-4 py-3 text-right">
                                <Link :href="`/admin/stocktake/${s.id}`" class="font-medium text-indigo-600 hover:text-indigo-800">Xem</Link>
                                <Link
                                    v-if="s.status === 'draft' && can('stocktake.update')"
                                    :href="`/admin/stocktake/${s.id}/edit`"
                                    class="font-medium text-slate-600 hover:text-slate-800"
                                >Sửa</Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="stocktakes.last_page > 1"
                class="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-sm"
            >
                <span class="text-slate-500">
                    Hiển thị {{ stocktakes.from }}–{{ stocktakes.to }} / {{ stocktakes.total }} phiếu
                </span>
                <div class="flex gap-1">
                    <Link
                        v-for="link in stocktakes.links"
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