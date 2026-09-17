<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputField from '@/Components/UI/InputField.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    pageTitle: { type: String, default: '' },
    unit:      { type: Object, default: null }, // null = tạo mới
});

const isEditing = computed(() => !!props.unit);

const form = useForm({
    code:        props.unit?.code        ?? '',
    name:        props.unit?.name        ?? '',
    description: props.unit?.description ?? '',
    is_active:   props.unit?.is_active   ?? true,
});

function submit() {
    if (isEditing.value) {
        form.put(`/admin/units/${props.unit.id}`);
    } else {
        form.post('/admin/units');
    }
}
</script>

<template>
    <Head :title="pageTitle" />

    <div class="max-w-xl">
        <div class="rounded-xl border border-slate-200 bg-white p-6">
            <form @submit.prevent="submit" class="space-y-5">

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <InputField label="Mã đơn vị tính" id="code" :error="form.errors.code" required>
                        <input
                            id="code"
                            v-model="form.code"
                            type="text"
                            placeholder="Ví dụ: PCS, SET, PAIR"
                            class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm
                                   transition focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            :class="{ 'border-red-400': form.errors.code }"
                        />
                    </InputField>

                    <InputField label="Tên đơn vị tính" id="name" :error="form.errors.name" required>
                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            placeholder="Ví dụ: Chiếc, Bộ, Đôi"
                            class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm
                                   transition focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            :class="{ 'border-red-400': form.errors.name }"
                        />
                    </InputField>
                </div>

                <InputField label="Mô tả" id="description" :error="form.errors.description">
                    <textarea
                        id="description"
                        v-model="form.description"
                        rows="3"
                        placeholder="Mô tả ngắn về đơn vị tính (không bắt buộc)"
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
                        {{ form.is_active ? 'Đơn vị tính hoạt động' : 'Đơn vị tính ngừng hoạt động' }}
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
                        <span v-else>{{ isEditing ? 'Cập nhật' : 'Tạo đơn vị tính' }}</span>
                    </button>
                    <Link
                        href="/admin/units"
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
