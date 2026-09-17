<script setup>
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { usePermission } from '@/Composables/usePermission';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    pageTitle:      { type: String, default: '' },
    trashedFolders: { type: Array,  required: true },
});

const { can } = usePermission();

function confirmRestore(folder) {
    if (!confirm(`Khôi phục thư mục "${folder.name}"?`)) return;
    router.post(`/admin/media-folders/${folder.id}/restore`, {}, { preserveScroll: true });
}

function confirmForceDelete(folder) {
    if (!confirm(`Xoá vĩnh viễn thư mục "${folder.name}"? Hành động này không thể hoàn tác.`)) return;
    router.delete(`/admin/media-folders/${folder.id}/force`, { preserveScroll: true });
}

function formatDate(dateStr) {
    return new Date(dateStr).toLocaleString('vi-VN', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
}
</script>

<template>
    <Head :title="pageTitle" />

    <div class="space-y-4">

        <div class="flex items-center justify-between">
            <p class="text-sm text-slate-500">
                {{ trashedFolders.length }} thư mục trong thùng rác
            </p>
            <a
                href="/admin/media-folders"
                class="text-sm text-indigo-600 hover:underline"
            >
                ← Quay lại danh sách
            </a>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50 text-left">
                            <th class="px-4 py-3 font-medium text-slate-600">Tên thư mục</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Thư mục cha (ID)</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Ngày xoá</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-if="trashedFolders.length === 0">
                            <td colspan="4" class="px-4 py-10 text-center text-slate-400">
                                Thùng rác trống.
                            </td>
                        </tr>

                        <tr
                            v-for="folder in trashedFolders"
                            :key="folder.id"
                            class="hover:bg-slate-50"
                        >
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                                    </svg>
                                    <span class="font-medium text-slate-700">{{ folder.name }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-slate-500">
                                {{ folder.parent_id ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-slate-500">
                                {{ formatDate(folder.deleted_at) }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <button
                                        v-if="can('media-folder.restore')"
                                        class="font-medium text-indigo-600 hover:text-indigo-800"
                                        @click="confirmRestore(folder)"
                                    >
                                        Khôi phục
                                    </button>
                                    <button
                                        v-if="can('media-folder.force-delete')"
                                        class="font-medium text-red-500 hover:text-red-700"
                                        @click="confirmForceDelete(folder)"
                                    >
                                        Xoá vĩnh viễn
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</template>