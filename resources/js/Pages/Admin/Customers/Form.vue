<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputField from '@/Components/UI/InputField.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    pageTitle: { type: String, default: '' },
    customer: { type: Object, default: null },
});

const isEdit = !!props.customer;

const form = useForm({
    customer_code: props.customer?.customer_code ?? '',
    name: props.customer?.name ?? '',
    phone: props.customer?.phone ?? '',
    email: props.customer?.email ?? '',
    customer_type: props.customer?.customer_type ?? 'individual',
    address: props.customer?.address ?? '',
    note: props.customer?.note ?? '',
});

const inputClass = `block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm
    transition focus:outline-none focus:ring-2 focus:ring-indigo-500`;

function submit() {
    if (isEdit) form.put(`/admin/customers/${props.customer.id}`);
    else form.post('/admin/customers');
}
</script>

<template>
    <Head :title="pageTitle" />

    <div class="max-w-3xl space-y-4">
        <div class="flex items-center justify-between">
            <h1 class="text-lg font-semibold text-slate-800">{{ pageTitle }}</h1>
            <Link href="/admin/customers" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">← Quay lại</Link>
        </div>

        <form @submit.prevent="submit" class="grid grid-cols-1 gap-4 rounded-xl border border-slate-200 bg-white p-6 sm:grid-cols-2">
            <InputField label="Mã khách hàng" id="customer_code" :error="form.errors.customer_code" required>
                <input id="customer_code" v-model="form.customer_code" type="text" :class="[inputClass, 'mt-1']" />
            </InputField>

            <InputField label="Tên khách hàng" id="name" :error="form.errors.name" required>
                <input id="name" v-model="form.name" type="text" :class="[inputClass, 'mt-1']" />
            </InputField>

            <InputField label="Số điện thoại" id="phone" :error="form.errors.phone">
                <input id="phone" v-model="form.phone" type="text" :class="[inputClass, 'mt-1']" />
            </InputField>

            <InputField label="Email liên hệ" id="email" :error="form.errors.email">
                <input id="email" v-model="form.email" type="email" :class="[inputClass, 'mt-1']" />
            </InputField>

            <InputField label="Loại khách hàng" id="customer_type" :error="form.errors.customer_type" required>
                <select id="customer_type" v-model="form.customer_type" :class="[inputClass, 'mt-1']">
                    <option value="individual">Cá nhân</option>
                    <option value="business">Doanh nghiệp</option>
                    <option value="other">Khác</option>
                </select>
            </InputField>

            <InputField label="Địa chỉ" id="address" :error="form.errors.address">
                <input id="address" v-model="form.address" type="text" :class="[inputClass, 'mt-1']" />
            </InputField>

            <div class="sm:col-span-2">
                <InputField label="Ghi chú" id="note" :error="form.errors.note">
                    <textarea id="note" v-model="form.note" rows="3" :class="[inputClass, 'mt-1']"></textarea>
                </InputField>
            </div>

            <div class="flex items-center gap-3 sm:col-span-2">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-medium text-white
                           transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <span v-if="form.processing">Đang lưu...</span>
                    <span v-else>{{ isEdit ? 'Cập nhật' : 'Tạo khách hàng' }}</span>
                </button>
                <Link
                    href="/admin/customers"
                    class="rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                >Huỷ</Link>
            </div>
        </form>
    </div>
</template>