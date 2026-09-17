<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputField from '@/Components/UI/InputField.vue';

defineOptions({ layout: AdminLayout });

// MediaController::edit() truyền thẳng media object qua Inertia (đúng
// convention Category/Brand/Unit: Controller truyền props đầy đủ, không bắt
// frontend tự fetch lại). Edit.vue KHÔNG gọi fetch/show() nữa — đơn giản hơn,
// không cần onMounted async, không có loading state.
const props = defineProps({
    pageTitle: { type: String, default: '' },
    media:     { type: Object, required: true },
});

// Chỉ đúng field UpdateMediaRequest thực tế cho phép: alt, title, description.
// KHÔNG gửi path/disk/uuid/file_name/original_name/mime_type/file_hash/size/
// width/height/duration/status/created_by — đúng mục VI đã chốt.
const form = useForm({
    alt:         props.media.alt         ?? '',
    title:       props.media.title       ?? '',
    description: props.media.description ?? '',
});

function submit() {
    form.put(`/admin/media/${props.media.id}`);
}
</script>

<template>
    <Head :title="pageTitle" />

    <div class="max-w-xl">
        <div class="rounded-xl border border-slate-200 bg-white p-6">

            <!-- Preview ảnh nếu là image type và có url -->
            <div
                v-if="media.type === 'image' && media.url"
                class="mb-4 overflow-hidden rounded-lg border border-slate-200 max-w-xs"
            >
                <img :src="media.url" class="w-full h-auto" :alt="media.alt ?? media.original_name" />
            </div>

            <!-- Tên file gốc -->
            <p class="mb-4 text-xs text-slate-400">{{ media.original_name }}</p>

            <form @submit.prevent="submit" class="space-y-4">

                <InputField label="Tiêu đề" id="title" :error="form.errors.title">
                    <input
                        id="title" v-model="form.title" type="text"
                        placeholder="Tiêu đề SEO cho file này"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm
                               focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                        :class="{ 'border-red-400': form.errors.title }"
                    />
                </InputField>

                <InputField label="Alt text" id="alt" :error="form.errors.alt">
                    <input
                        id="alt" v-model="form.alt" type="text"
                        placeholder="Mô tả nội dung hình ảnh (accessibility + SEO)"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm
                               focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                        :class="{ 'border-red-400': form.errors.alt }"
                    />
                </InputField>

                <InputField label="Mô tả" id="description" :error="form.errors.description">
                    <textarea
                        id="description" v-model="form.description" rows="3"
                        placeholder="Mô tả thêm về nội dung file này"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm
                               focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                        :class="{ 'border-red-400': form.errors.description }"
                    ></textarea>
                </InputField>

                <div class="flex items-center justify-between border-t border-slate-100 pt-4">
                    <Link
                        href="/admin/media"
                        class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50"
                    >
                        ← Quay lại
                    </Link>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-medium text-white
                               hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <span v-if="form.processing">Đang lưu...</span>
                        <span v-else>Lưu thay đổi</span>
                    </button>
                </div>

            </form>
        </div>
    </div>
</template>