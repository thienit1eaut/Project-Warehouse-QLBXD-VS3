<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputField from '@/Components/UI/InputField.vue';
import MediaPicker from '@/Components/Media/MediaPicker.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    pageTitle:     { type: String, default: '' },
    category:      { type: Object, default: null }, // null = tạo mới
    parentOptions: { type: Array,  default: () => [] },
});

const isEditing = computed(() => !!props.category);

// img là media_id (int, nullable) — cùng contract với Brand.
const form = useForm({
    name:        props.category?.name        ?? '',
    parent_id:   props.category?.parent_id    ?? '',
    description: props.category?.description ?? '',
    img:         props.category?.img          ?? null,
    is_active:   props.category?.is_active    ?? true,
});

function submit() {
    // parent_id rỗng ('') -> gửi null để backend hiểu là danh mục gốc
    const payload = { ...form.data(), parent_id: form.parent_id || null };

    if (isEditing.value) {
        form.transform(() => payload).put(`/admin/categories/${props.category.id}`);
    } else {
        form.transform(() => payload).post('/admin/categories');
    }
}
</script>

<template>
    <Head :title="pageTitle" />

    <div class="max-w-xl">
        <div class="rounded-xl border border-slate-200 bg-white p-6">
            <form @submit.prevent="submit" class="space-y-5">

                <InputField label="Tên danh mục" id="name" :error="form.errors.name" required>
                    <input
                        id="name"
                        v-model="form.name"
                        type="text"
                        placeholder="Ví dụ: Xe đạp địa hình"
                        class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm
                               transition focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        :class="{ 'border-red-400': form.errors.name }"
                    />
                </InputField>

                <InputField label="Danh mục cha" id="parent_id" :error="form.errors.parent_id">
                    <select
                        id="parent_id"
                        v-model="form.parent_id"
                        class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm
                               transition focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        <option value="">— Danh mục gốc (không có cha) —</option>
                        <option v-for="p in parentOptions" :key="p.id" :value="p.id">
                            <!-- indent nhẹ theo cấp để gợi ý phân cấp trong dropdown -->
                            {{ p.parent_id ? '— ' : '' }}{{ p.name }}
                        </option>
                    </select>
                </InputField>

                <!-- Ảnh đại diện — chọn từ Media Library, gửi media_id (form.img) -->
                <InputField label="Ảnh đại diện" id="img" :error="form.errors.img">
                    <MediaPicker
                        v-model="form.img"
                        :initial-media="category?.media ?? null"
                    />
                </InputField>

                <InputField label="Mô tả" id="description" :error="form.errors.description">
                    <textarea
                        id="description"
                        v-model="form.description"
                        rows="3"
                        placeholder="Mô tả ngắn về danh mục (không bắt buộc)"
                        class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm
                               transition focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </InputField>

                <!-- Toggle trạng thái — cùng pattern với Users/Form.vue -->
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
                        {{ form.is_active ? 'Danh mục hoạt động' : 'Danh mục ngừng hoạt động' }}
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
                        <span v-else>{{ isEditing ? 'Cập nhật' : 'Tạo danh mục' }}</span>
                    </button>
                    <Link
                        href="/admin/categories"
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