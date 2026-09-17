<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputField from '@/Components/UI/InputField.vue';
import { buildFolderOptions } from '@/Composables/useFolderOptions';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    pageTitle:  { type: String, default: '' },
    allFolders: { type: Array,  default: () => [] }, // toàn bộ folder mọi cấp
});

// Dropdown lồng cấp — hiển thị được cả folder cấp 2 trở lên làm parent,
// không chỉ folder gốc.
const folderOptions = computed(() => buildFolderOptions(props.allFolders));

const form = useForm({
    name: '',
    parent_id: '',
});

function submit() {
    form.post('/admin/media-folders', {
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <Head :title="pageTitle" />

    <div class="max-w-xl">
        <div class="rounded-xl border border-slate-200 bg-white p-6">
            <div class="flex items-center justify-between mb-4">
                <h1 class="text-lg font-semibold text-slate-800">{{ pageTitle }}</h1>
                <Link
                    href="/admin/media"
                    class="text-sm text-slate-500 hover:text-indigo-600"
                >
                    ← Quay lại Media
                </Link>
            </div>

            <form @submit.prevent="submit" class="space-y-4">
                <InputField label="Tên thư mục" id="name" :error="form.errors.name" required>
                    <input
                        id="name" v-model="form.name" type="text"
                        placeholder="Ví dụ: Products, Banners, Avatars"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                        autofocus
                    />
                </InputField>

                <InputField label="Thư mục cha" id="parent_id" :error="form.errors.parent_id">
                    <select
                        id="parent_id" v-model="form.parent_id"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    >
                        <option value="">— Thư mục gốc —</option>
                        <option v-for="opt in folderOptions" :key="opt.id" :value="opt.id">
                            {{ opt.label }}
                        </option>
                    </select>
                </InputField>

                <div class="flex items-center justify-between gap-2 pt-2 border-t border-slate-100">
                    <Link
                        href="/admin/media"
                        class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50"
                    >
                        Huỷ
                    </Link>
                    <button
                        type="submit" :disabled="form.processing"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm text-white hover:bg-indigo-700 disabled:opacity-50"
                    >
                        <span v-if="form.processing">Đang tạo...</span>
                        <span v-else>Tạo thư mục</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>