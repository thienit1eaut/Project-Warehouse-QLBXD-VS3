<script setup>
import { Link } from '@inertiajs/vue3';
import FolderTreeNode from './FolderTreeNode.vue';
import { usePermission } from '@/Composables/usePermission';

defineProps({
    rootFolders:      { type: Array,          required: true },
    selectedFolderId: { type: [Number, null], default: null  },
});

const emit = defineEmits(['select']);

const { can } = usePermission();

function onSelect(folderId) {
    emit('select', folderId);
}
</script>

<template>
    <div class="flex flex-col">

        <!-- Danh sách folder -->
        <div class="space-y-0.5">

            <!-- "Tất cả Media" — bỏ chọn folder, xem toàn bộ -->
            <div
                class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm cursor-pointer"
                :class="selectedFolderId === null
                    ? 'bg-indigo-50 text-indigo-700 font-medium'
                    : 'text-slate-600 hover:bg-slate-100'"
                @click="onSelect(null)"
            >
                <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75
                           m-19.5 0v6a2.25 2.25 0 002.25 2.25h15a2.25 2.25 0 002.25-2.25v-6
                           m-19.5 0h19.5" />
                </svg>
                Tất cả Media
            </div>

            <FolderTreeNode
                v-for="folder in rootFolders"
                :key="folder.id"
                :folder="folder"
                :selected-folder-id="selectedFolderId"
                :depth="0"
                @select="onSelect"
            />

            <p v-if="rootFolders.length === 0"
                class="px-2 py-1.5 text-xs text-slate-400">
                Chưa có thư mục nào.
            </p>
        </div>

        <!-- Footer: link quản lý folder + tạo mới -->
        <div class="mt-3 pt-3 border-t border-slate-100 space-y-1">
            <Link
                v-if="can('media-folder.view')"
                href="/admin/media-folders"
                class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-xs text-slate-500
                       hover:bg-slate-100 hover:text-slate-700 transition-colors"
            >
                <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5
                           m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5
                           m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75" />
                </svg>
                Quản lý thư mục
            </Link>

            <Link
                v-if="can('media-folder.create')"
                href="/admin/media-folders/create"
                class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-xs text-indigo-600
                       hover:bg-indigo-50 transition-colors"
            >
                <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Tạo thư mục mới
            </Link>
        </div>

    </div>
</template>