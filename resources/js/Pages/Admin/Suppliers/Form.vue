<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputField from '@/Components/UI/InputField.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    pageTitle: { type: String, default: '' },
    supplier:  { type: Object, default: null }, // null = tạo mới
});

const isEditing = computed(() => !!props.supplier);

const form = useForm({
    code:           props.supplier?.code           ?? '',
    name:           props.supplier?.name           ?? '',
    contact_person: props.supplier?.contact_person  ?? '',
    phone:          props.supplier?.phone           ?? '',
    email:          props.supplier?.email           ?? '',
    address:        props.supplier?.address         ?? '',
    tax_code:       props.supplier?.tax_code         ?? '',
    website:        props.supplier?.website          ?? '',
    description:    props.supplier?.description      ?? '',
    is_active:      props.supplier?.is_active         ?? true,
});

function submit() {
    if (isEditing.value) {
        form.put(`/admin/suppliers/${props.supplier.id}`);
    } else {
        form.post('/admin/suppliers');
    }
}
</script>

<template>
    <Head :title="pageTitle" />

    <div class="max-w-2xl">
        <div class="rounded-xl border border-slate-200 bg-white p-6">
            <form @submit.prevent="submit" class="space-y-5">

                <!-- Mã + Tên -->
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <InputField label="Mã nhà cung cấp" id="code" :error="form.errors.code" required>
                        <input
                            id="code"
                            v-model="form.code"
                            type="text"
                            placeholder="Ví dụ: NCC001"
                            class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm
                                   transition focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            :class="{ 'border-red-400': form.errors.code }"
                        />
                    </InputField>

                    <InputField label="Tên nhà cung cấp" id="name" :error="form.errors.name" required>
                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            placeholder="Ví dụ: Công ty TNHH Xe đạp ABC"
                            class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm
                                   transition focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            :class="{ 'border-red-400': form.errors.name }"
                        />
                    </InputField>
                </div>

                <InputField label="Người liên hệ" id="contact_person" :error="form.errors.contact_person">
                    <input
                        id="contact_person"
                        v-model="form.contact_person"
                        type="text"
                        placeholder="Tên người phụ trách liên hệ"
                        class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm
                               transition focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </InputField>

                <!-- SĐT + Email -->
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <InputField label="Số điện thoại" id="phone" :error="form.errors.phone">
                        <input
                            id="phone"
                            v-model="form.phone"
                            type="text"
                            placeholder="09xxxxxxxx"
                            class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm
                                   transition focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            :class="{ 'border-red-400': form.errors.phone }"
                        />
                    </InputField>

                    <InputField label="Email" id="email" :error="form.errors.email">
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            placeholder="contact@supplier.com"
                            class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm
                                   transition focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            :class="{ 'border-red-400': form.errors.email }"
                        />
                    </InputField>
                </div>

                <InputField label="Địa chỉ" id="address" :error="form.errors.address">
                    <textarea
                        id="address"
                        v-model="form.address"
                        rows="2"
                        placeholder="Địa chỉ nhà cung cấp"
                        class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm
                               transition focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </InputField>

                <!-- MST + Website -->
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <InputField label="Mã số thuế" id="tax_code" :error="form.errors.tax_code">
                        <input
                            id="tax_code"
                            v-model="form.tax_code"
                            type="text"
                            placeholder="Mã số thuế doanh nghiệp"
                            class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm
                                   transition focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        />
                    </InputField>

                    <InputField label="Website" id="website" :error="form.errors.website">
                        <input
                            id="website"
                            v-model="form.website"
                            type="text"
                            placeholder="https://example.com"
                            class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm
                                   transition focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            :class="{ 'border-red-400': form.errors.website }"
                        />
                    </InputField>
                </div>

                <InputField label="Mô tả" id="description" :error="form.errors.description">
                    <textarea
                        id="description"
                        v-model="form.description"
                        rows="3"
                        placeholder="Ghi chú thêm về nhà cung cấp (không bắt buộc)"
                        class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm
                               transition focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </InputField>

                <!-- Toggle trạng thái -->
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        role="switch"
                        :aria-checked="form.is_active"
                        :class="[
                            'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full',
                            'border-2 border-transparent transition-colors duration-200',
                            form.is_active ? 'bg-indigo-600' : 'bg-slate-200',
                        ]"
                        @click="form.is_active = !form.is_active"
                    >
                        <span
                            :class="[
                                'pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow',
                                'transition duration-200',
                                form.is_active ? 'translate-x-5' : 'translate-x-0',
                            ]"
                        />
                    </button>
                    <span class="text-sm text-slate-700">
                        {{ form.is_active ? 'Nhà cung cấp hoạt động' : 'Nhà cung cấp ngừng hoạt động' }}
                    </span>
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-3 border-t border-slate-100 pt-4">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-medium text-white
                               transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <span v-if="form.processing">Đang lưu...</span>
                        <span v-else>{{ isEditing ? 'Cập nhật' : 'Tạo nhà cung cấp' }}</span>
                    </button>
                    <Link
                        href="/admin/suppliers"
                        class="rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-medium
                               text-slate-700 transition hover:bg-slate-50"
                    >
                        Huỷ
                    </Link>
                </div>

            </form>
        </div>
    </div>
</template>
