<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputField from '@/Components/UI/InputField.vue';
import { usePermission } from '@/Composables/usePermission';
import { buildFolderOptions } from '@/Composables/useFolderOptions';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    pageTitle:  { type: String, default: '' },
    folder:     { type: Object, required: true },
    allFolders: { type: Array,  required: true }, // toàn bộ folder mọi cấp
});

const { can } = usePermission();

// Loại chính folder đang sửa (và toàn bộ nhánh con của nó) khỏi option —
// không cho chọn chính nó hoặc con/cháu của nó làm cha (circular).
// Server (assertMoveValid) vẫn là lớp chặn cuối cùng, đây chỉ là UX.
const folderOptions = computed(() => buildFolderOptions(props.allFolders, props.folder.id));

const form = useForm({
    name:      props.folder.name      ?? '',
    parent_id: props.folder.parent_id ?? null,
});

function submit() {
    form.put(`/admin/media-folders/${props.folder.id}`, {
        onSuccess: () => form.reset(),
    });
}

function confirmDelete() {
    if (!confirm(`Chuyển thư mục "${props.folder.name}" vào thùng rác?`)) return;
    router.delete(`/admin/media-folders/${props.folder.id}`, {
        onSuccess: () => router.visit('/admin/media-folders'),
    });
}

function confirmForceDelete() {
    if (!confirm(`Xoá vĩnh viễn thư mục "${props.folder.name}"?\nHành động này không thể hoàn tác.`)) return;
    router.delete(`/admin/media-folders/${props.folder.id}/force`, {
        onSuccess: () => router.visit('/admin/media-folders'),
    });
}
</script>

<template>
    <Head :title="pageTitle" />

    <div class="max-w-xl space-y-4">

        <!-- Form rename / move -->
        <div class="rounded-xl border border-slate-200 bg-white p-6">
            <h2 class="text-sm font-semibold text-slate-700 mb-4">Đổi tên / Di chuyển thư mục</h2>
            <form @submit.prevent="submit" class="space-y-5">

                <InputField label="Tên thư mục" id="name" :error="form.errors.name" required>
                    <input
                        id="name"
                        v-model="form.name"
                        type="text"
                        placeholder="Ví dụ: Products, Banners, Avatars"
                        class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm
                               transition focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        :class="{ 'border-red-400': form.errors.name }"
                        autofocus
                    />
                </InputField>

                <InputField label="Thư mục cha" id="parent_id" :error="form.errors.parent_id">
                    <select
                        id="parent_id"
                        v-model="form.parent_id"
                        class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm
                               transition focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        <option :value="null">— Thư mục gốc —</option>
                        <option
                            v-for="opt in folderOptions"
                            :key="opt.id"
                            :value="opt.id"
                        >
                            {{ opt.label }}
                        </option>
                    </select>
                    <p class="mt-1 text-xs text-slate-400">
                        Đổi thư mục cha sẽ di chuyển thư mục này sang vị trí mới.
                    </p>
                </InputField>

                <div class="flex items-center gap-3 border-t border-slate-100 pt-4">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-medium text-white
                               transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <span v-if="form.processing">Đang lưu...</span>
                        <span v-else>Cập nhật thư mục</span>
                    </button>
                    <Link
                        href="/admin/media-folders"
                        class="rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-medium
                               text-slate-700 transition hover:bg-slate-50"
                    >
                        Huỷ
                    </Link>
                </div>

            </form>
        </div>

        <!-- Vùng nguy hiểm — Delete / Force Delete -->
        <div
            v-if="can('media-folder.delete') || can('media-folder.force-delete')"
            class="rounded-xl border border-red-100 bg-white p-6"
        >
            <h2 class="text-sm font-semibold text-red-600 mb-4">Vùng nguy hiểm</h2>

            <div class="space-y-3">

                <!-- Soft delete -->
                <div
                    v-if="can('media-folder.delete')"
                    class="flex items-center justify-between gap-4"
                >
                    <div>
                        <p class="text-sm font-medium text-slate-700">Chuyển vào thùng rác</p>
                        <p class="text-xs text-slate-400 mt-0.5">
                            Thư mục sẽ bị ẩn, có thể khôi phục lại sau.
                            Không ảnh hưởng đến Media bên trong.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="shrink-0 rounded-lg border border-red-300 px-4 py-2 text-sm font-medium
                               text-red-600 transition hover:bg-red-50"
                        @click="confirmDelete"
                    >
                        Xoá thư mục
                    </button>
                </div>

                <!-- Force delete -->
                <div
                    v-if="can('media-folder.force-delete')"
                    class="flex items-center justify-between gap-4 pt-3 border-t border-red-50"
                >
                    <div>
                        <p class="text-sm font-medium text-slate-700">Xoá vĩnh viễn</p>
                        <p class="text-xs text-slate-400 mt-0.5">
                            Xoá thư mục khỏi hệ thống hoàn toàn, không thể hoàn tác.
                            Không xoá Media bên trong — chỉ xoá liên kết Folder.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="shrink-0 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium
                               text-white transition hover:bg-red-700"
                        @click="confirmForceDelete"
                    >
                        Xoá vĩnh viễn
                    </button>
                </div>

            </div>
        </div>

    </div>
</template>