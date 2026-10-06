<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Badge from '@/Components/UI/Badge.vue';
import { usePermission } from '@/Composables/usePermission';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    pageTitle: { type: String, default: '' },
    customer: { type: Object, required: true },
});

const { can } = usePermission();
const page = usePage();

const typeLabel = { individual: 'Cá nhân', business: 'Doanh nghiệp', other: 'Khác' };
const account = computed(() => props.customer.account);
const accountError = computed(() => page.props.errors?.account ?? null);

const form = useForm({
    email: props.customer.email ?? '',
    password: '',
    password_confirmation: '',
    status: 'active',
});

const inputClass = `block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm
    focus:outline-none focus:ring-2 focus:ring-indigo-500`;

function createAccount() {
    form.post(`/admin/customers/${props.customer.id}/account`, {
        preserveScroll: true,
        onSuccess: () => form.reset('password', 'password_confirmation'),
    });
}

function setStatus(status) {
    const msg = status === 'inactive'
        ? 'Chuyển tài khoản sang inactive?'
        : 'Chuyển tài khoản sang active?';
    if (!window.confirm(msg)) return;
    router.patch(`/admin/customers/${props.customer.id}/account/status`, { status }, { preserveScroll: true });
}
</script>

<template>
    <Head :title="pageTitle" />

    <div class="max-w-4xl space-y-4">
        <div class="flex items-center justify-between">
            <h1 class="text-lg font-semibold text-slate-800">{{ customer.customer_code }} - {{ customer.name }}</h1>
            <div class="flex items-center gap-4">
                <Link
                    v-if="can('customer.update')"
                    :href="`/admin/customers/${customer.id}/edit`"
                    class="text-sm font-medium text-slate-600 hover:text-slate-800"
                >Sửa</Link>
                <Link href="/admin/customers" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
                    ← Quay lại danh sách
                </Link>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 rounded-xl border border-slate-200 bg-white p-4 text-sm sm:grid-cols-3">
            <div><span class="text-slate-400">Mã khách hàng:</span><p class="text-slate-700">{{ customer.customer_code }}</p></div>
            <div><span class="text-slate-400">Tên:</span><p class="text-slate-700">{{ customer.name }}</p></div>
            <div><span class="text-slate-400">Loại:</span><p class="text-slate-700">{{ typeLabel[customer.customer_type] }}</p></div>
            <div><span class="text-slate-400">Số điện thoại:</span><p class="text-slate-700">{{ customer.phone ?? '—' }}</p></div>
            <div><span class="text-slate-400">Email liên hệ:</span><p class="text-slate-700">{{ customer.email ?? '—' }}</p></div>
            <div><span class="text-slate-400">Địa chỉ:</span><p class="text-slate-700">{{ customer.address ?? '—' }}</p></div>
            <div class="sm:col-span-3"><span class="text-slate-400">Ghi chú:</span><p class="text-slate-700">{{ customer.note ?? '—' }}</p></div>
        </div>

        <div class="space-y-3 rounded-xl border border-slate-200 bg-white p-4">
            <h2 class="text-sm font-semibold text-slate-700">Tài khoản khách hàng (nền tảng cho website tương lai)</h2>

            <p v-if="accountError" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ accountError }}
            </p>

            <!-- Đã có account -->
            <div v-if="account" class="space-y-3 text-sm">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div><span class="text-slate-400">Email tài khoản:</span><p class="text-slate-700">{{ account.email }}</p></div>
                    <div>
                        <span class="text-slate-400">Trạng thái:</span>
                        <p>
                            <Badge :variant="account.status === 'active' ? 'green' : 'gray'">
                                {{ account.status === 'active' ? 'Active' : 'Inactive' }}
                            </Badge>
                        </p>
                    </div>
                    <div>
                        <span class="text-slate-400">Xác minh email:</span>
                        <p class="text-slate-700">{{ account.email_verified_at ?? 'Chưa xác minh' }}</p>
                    </div>
                </div>

                <div v-if="can('customer.update')">
                    <button
                        v-if="account.status === 'active'"
                        type="button"
                        class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        @click="setStatus('inactive')"
                    >Chuyển sang Inactive</button>
                    <button
                        v-else
                        type="button"
                        class="rounded-lg border border-green-300 px-4 py-2 text-sm font-medium text-green-700 hover:bg-green-50"
                        @click="setStatus('active')"
                    >Chuyển sang Active</button>
                </div>
            </div>

            <!-- Chưa có account -->
            <div v-else>
                <p class="mb-3 text-sm text-slate-500">Chưa có tài khoản.</p>

                <form v-if="can('customer.update')" @submit.prevent="createAccount" class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                    <div>
                        <input v-model="form.email" type="email" placeholder="Email tài khoản" :class="inputClass" autocomplete="off" />
                        <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
                    </div>
                    <div>
                        <input v-model="form.password" type="password" placeholder="Mật khẩu" :class="inputClass" autocomplete="new-password" />
                        <p v-if="form.errors.password" class="mt-1 text-xs text-red-600">{{ form.errors.password }}</p>
                    </div>
                    <div>
                        <input v-model="form.password_confirmation" type="password" placeholder="Nhập lại mật khẩu" :class="inputClass" autocomplete="new-password" />
                    </div>
                    <div>
                        <select v-model="form.status" :class="inputClass">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                        <p v-if="form.errors.status" class="mt-1 text-xs text-red-600">{{ form.errors.status }}</p>
                    </div>
                    <div class="sm:col-span-4">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-medium text-white
                                   transition hover:bg-indigo-700 disabled:opacity-60"
                        >Tạo tài khoản</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>