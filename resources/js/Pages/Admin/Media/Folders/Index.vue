<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Badge from '@/Components/UI/Badge.vue';
import FolderTree from '@/Components/Media/FolderTree.vue';
import { usePermission } from '@/Composables/usePermission';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    pageTitle:   { type: String, default: '' },
    rootFolders: { type: Array,  required: true },
});

const { can } = usePermission();

function confirmDelete(folder) {
    if (!confirm(`Chuyển thư mục "${folder.name}" vào thùng rác?`)) return;
    router.delete(`/admin/media-folders/${folder.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head :title="pageTitle" />

    <div class="space-y-4">

        <!-- Toolbar -->
        <div class="flex items-center justify-between">
            <div class="flex gap-2">
                <Link
                    v-if="can('media-folder.restore')"
                    href="/admin/media-folders/trash"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50"
                >
                    🗑 Thùng rác
                </Link>
            </div>
            <Link
                v-if="can('media-folder.create')"
                href="/admin/media-folders/create"
                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
            >
                + Tạo thư mục
            </Link>
        </div>

        <!-- Cây thư mục -->
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <p v-if="rootFolders.length === 0" class="text-center text-sm text-slate-400 py-8">
                Chưa có thư mục nào.
            </p>

            <div v-else class="space-y-1">
                <!-- Mỗi root folder render inline dạng row + expand tree con -->
                <div
                    v-for="folder in rootFolders"
                    :key="folder.id"
                    class="rounded-lg border border-slate-100 p-3"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2 text-sm font-medium text-slate-800">
                            <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                            </svg>
                            {{ folder.name }}
                            <Badge v-if="folder.children_count" variant="blue">
                                {{ folder.children_count }} thư mục con
                            </Badge>
                        </div>

                        <div class="flex items-center gap-3 text-sm">
                            <Link
                                v-if="can('media-folder.update')"
                                :href="`/admin/media-folders/${folder.id}/edit`"
                                class="font-medium text-indigo-600 hover:text-indigo-800"
                            >
                                Sửa
                            </Link>
                            <button
                                v-if="can('media-folder.delete')"
                                class="font-medium text-red-500 hover:text-red-700"
                                @click="confirmDelete(folder)"
                            >
                                Xoá
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</template>