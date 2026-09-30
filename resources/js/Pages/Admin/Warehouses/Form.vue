<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputField from '@/Components/UI/InputField.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    pageTitle: { type: String, default: '' },
    warehouse: { type: Object, default: null }, // null = tạo mới
});

const isEditing = computed(() => !!props.warehouse);

const form = useForm({
    code:        props.warehouse?.code        ?? '',
    name:        props.warehouse?.name        ?? '',
    address:     props.warehouse?.address     ?? '',
    description: props.warehouse?.description ?? '',
    is_active:   props.warehouse?.is_active   ?? true,
});

function submit() {
    if (isEditing.value) {
        form.put(`/admin/warehouses/${props.warehouse.id}`);
    } else {
        form.post('/admin/warehouses');
    }
}
</script>

<template>
    <Head :title="pageTitle" />

    <div class="max-w-xl">
        <div class="rounded-xl border border-slate-200 bg-white p-6">
            <form @submit.prevent="submit" class="space-y-5">

                <InputField label="Mã kho" id="code" :error="form.errors.code" required>
                    <input
                        id="code"
                        v-model="form.code"
                        type="text"
                        placeholder="VD: WH-01"
                        class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm
                               transition focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        :class="{ 'border-red-400': form.errors.code }"
                    />
                </InputField>

                <InputField label="Tên kho" id="name" :error="form.errors.name" required>
                    <input
                        id="name"
                        v-model="form.name"
                        type="text"
                        placeholder="VD: Kho chính"
                        class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm
                               transition focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        :class="{ 'border-red-400': form.errors.name }"
                    />
                </InputField>

                <InputField label="Địa chỉ" id="address" :error="form.errors.address">
                    <textarea
                        id="address"
                        v-model="form.address"
                        rows="2"
                        class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm
                               transition focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </InputField>

                <InputField label="Mô tả" id="description" :error="form.errors.description">
                    <textarea
                        id="description"
                        v-model="form.description"
                        rows="3"
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
                        {{ form.is_active ? 'Kho đang hoạt động' : 'Kho ngừng hoạt động' }}
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
                        <span v-else>{{ isEditing ? 'Cập nhật' : 'Tạo kho hàng' }}</span>
                    </button>
                    <Link
                        href="/admin/warehouses"
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
