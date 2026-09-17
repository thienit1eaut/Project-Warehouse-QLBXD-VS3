<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputField from '@/Components/UI/InputField.vue';
import MediaPicker from '@/Components/Media/MediaPicker.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    pageTitle: { type: String, default: '' },
    brand:     { type: Object, default: null }, // null = tạo mới
});

const isEditing = computed(() => !!props.brand);

// img giờ là media_id (int, nullable) — không còn File object/remove_logo.
// Không có file upload trực tiếp trong form này nữa nên không cần
// forceFormData/transform _method spoofing như trước.
const form = useForm({
    name:        props.brand?.name        ?? '',
    description: props.brand?.description ?? '',
    img:         props.brand?.img         ?? null,
    website:     props.brand?.website     ?? '',
    is_active:   props.brand?.is_active   ?? true,
});

function submit() {
    if (isEditing.value) {
        form.put(`/admin/brands/${props.brand.id}`);
    } else {
        form.post('/admin/brands');
    }
}
</script>

<template>
    <Head :title="pageTitle" />

    <div class="max-w-xl">
        <div class="rounded-xl border border-slate-200 bg-white p-6">
            <form @submit.prevent="submit" class="space-y-5">

                <InputField label="Tên thương hiệu" id="name" :error="form.errors.name" required>
                    <input
                        id="name"
                        v-model="form.name"
                        type="text"
                        placeholder="Ví dụ: Giant, Trek, Shimano"
                        class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm
                               transition focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        :class="{ 'border-red-400': form.errors.name }"
                    />
                </InputField>

                <!-- Ảnh đại diện — chọn từ Media Library, gửi media_id (form.img) -->
                <InputField label="Ảnh đại diện" id="img" :error="form.errors.img">
                    <MediaPicker
                        v-model="form.img"
                        :initial-media="brand?.media ?? null"
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

                <InputField label="Mô tả" id="description" :error="form.errors.description">
                    <textarea
                        id="description"
                        v-model="form.description"
                        rows="3"
                        placeholder="Mô tả ngắn về thương hiệu (không bắt buộc)"
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
                        {{ form.is_active ? 'Thương hiệu hoạt động' : 'Thương hiệu ngừng hoạt động' }}
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
                        <span v-else>{{ isEditing ? 'Cập nhật' : 'Tạo thương hiệu' }}</span>
                    </button>
                    <Link
                        href="/admin/brands"
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