<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputField from '@/Components/UI/InputField.vue';

defineOptions({ layout: AdminLayout });

defineProps({
    pageTitle: { type: String, default: '' },
});

// Chỉ đúng field MediaUploadRequest thực tế cho phép: file, folder_id, alt, title, description
const form = useForm({
    file: null,
    folder_id: '',
    alt: '',
    title: '',
    description: '',
});

const preview = ref(null);
const fileInputRef = ref(null);

function onFileChange(e) {
    const selected = e.target.files[0];
    if (!selected) return;

    form.file = selected;
    preview.value = selected.type.startsWith('image/') ? URL.createObjectURL(selected) : null;
}

function submit() {
    form.post('/admin/media', {
        forceFormData: true,
        onSuccess: () => {
            form.reset();
            preview.value = null;
            if (fileInputRef.value) fileInputRef.value.value = '';
        },
    });
}
</script>

<template>
    <Head :title="pageTitle" />

    <div class="max-w-xl">
        <div class="rounded-xl border border-slate-200 bg-white p-6">
            <h1 class="text-lg font-semibold text-slate-800 mb-4">{{ pageTitle }}</h1>

            <form @submit.prevent="submit" class="space-y-4">
                <InputField label="File" id="file" error="" required>
                    <input
                        id="file"
                        ref="fileInputRef"
                        type="file"
                        class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-indigo-700 hover:file:bg-indigo-100"
                        @change="onFileChange"
                    />
                    <p v-if="form.errors.file" class="text-xs text-red-600 mt-1">{{ form.errors.file }}</p>
                </InputField>

                <div v-if="preview" class="rounded-lg overflow-hidden border border-slate-200 max-w-xs">
                    <img :src="preview" class="w-full h-auto" alt="Xem trước" />
                </div>

                <InputField label="Tiêu đề" id="title" :error="form.errors.title">
                    <input
                        id="title" v-model="form.title" type="text"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                    />
                </InputField>

                <InputField label="Alt text" id="alt" :error="form.errors.alt">
                    <input
                        id="alt" v-model="form.alt" type="text"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                    />
                </InputField>

                <InputField label="Mô tả" id="description" :error="form.errors.description">
                    <textarea
                        id="description" v-model="form.description" rows="3"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                    ></textarea>
                </InputField>

                <div class="flex justify-end gap-2 pt-2">
                    <button
                        type="submit"
                        :disabled="form.processing || !form.file"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm text-white hover:bg-indigo-700 disabled:opacity-50"
                    >
                        {{ form.processing ? 'Đang upload...' : 'Upload' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>