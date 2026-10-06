<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Badge from '@/Components/UI/Badge.vue';
import { usePermission } from '@/Composables/usePermission';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    pageTitle: { type: String, default: '' },
    customers: { type: Object, required: true },
    filters: { type: Object, default: () => ({ search: '', customer_type: '' }) },
});

const { can } = usePermission();
const search = ref(props.filters.search ?? '');
const type = ref(props.filters.customer_type ?? '');

const typeLabel = { individual: 'Cá nhân', business: 'Doanh nghiệp', other: 'Khác' };

function applyFilters() {
    router.get('/admin/customers', { search: search.value, customer_type: type.value }, {
        preserveState: true,
        replace: true,
    });
}

let debounceTimer;
watch(search, () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(applyFilters, 400);
});
watch(type, applyFilters);

function destroyCustomer(c) {
    if (!window.confirm(`Xoá khách hàng "${c.name}"?`)) return;
    router.delete(`/admin/customers/${c.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head :title="pageTitle" />

    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-3">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Tìm theo mã, tên, SĐT, email..."
                    class="w-72 rounded-lg border border-slate-300 px-3 py-2 text-sm
                           focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
                <select
                    v-model="type"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm
                           focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
                    <option value="">Tất cả loại</option>
                    <option value="individual">Cá nhân</option>
                    <option value="business">Doanh nghiệp</option>
                    <option value="other">Khác</option>
                </select>
            </div>
            <Link
                v-if="can('customer.create')"
                href="/admin/customers/create"
                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700"
            >
                + Thêm khách hàng
            </Link>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50 text-left">
                            <th class="px-4 py-3 font-medium text-slate-600">Mã</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Tên</th>
                            <th class="px-4 py-3 font-medium text-slate-600">SĐT</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Email</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Loại</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Tài khoản</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-if="customers.data.length === 0">
                            <td colspan="7" class="px-4 py-10 text-center text-slate-400">Chưa có khách hàng.</td>
                        </tr>
                        <tr v-for="c in customers.data" :key="c.id" class="transition-colors hover:bg-slate-50">
                            <td class="px-4 py-3 font-mono text-slate-700">{{ c.customer_code }}</td>
                            <td class="px-4 py-3 font-medium text-slate-800">{{ c.name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ c.phone ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ c.email ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ typeLabel[c.customer_type] ?? c.customer_type }}</td>
                            <td class="px-4 py-3">
                                <Badge v-if="!c.account" variant="gray">Chưa có</Badge>
                                <Badge v-else :variant="c.account.status === 'active' ? 'green' : 'gray'">
                                    {{ c.account.status === 'active' ? 'Active' : 'Inactive' }}
                                </Badge>
                            </td>
                            <td class="space-x-3 px-4 py-3 text-right">
                                <Link :href="`/admin/customers/${c.id}`" class="font-medium text-indigo-600 hover:text-indigo-800">Xem</Link>
                                <Link
                                    v-if="can('customer.update')"
                                    :href="`/admin/customers/${c.id}/edit`"
                                    class="font-medium text-slate-600 hover:text-slate-800"
                                >Sửa</Link>
                                <button
                                    v-if="can('customer.delete')"
                                    type="button"
                                    class="font-medium text-red-600 hover:text-red-800"
                                    @click="destroyCustomer(c)"
                                >Xoá</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="customers.last_page > 1"
                class="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-sm"
            >
                <span class="text-slate-500">
                    Hiển thị {{ customers.from }}–{{ customers.to }} / {{ customers.total }} khách hàng
                </span>
                <div class="flex gap-1">
                    <Link
                        v-for="link in customers.links"
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