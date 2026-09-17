<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { usePermission } from '@/Composables/usePermission';

defineOptions({ name: 'FolderTreeNode' });

const props = defineProps({
    folder:           { type: Object,         required: true },
    selectedFolderId: { type: [Number, null], default: null  },
    depth:            { type: Number,         default: 0     },
});

const emit = defineEmits(['select']);

const { can } = usePermission();

const expanded = ref(false);
const children = ref(null); // null = chưa fetch, [] = đã fetch nhưng rỗng
const loading  = ref(false);

async function toggleExpand() {
    if (props.folder.children_count === 0) return;
    expanded.value = !expanded.value;
    if (expanded.value && children.value === null) {
        loading.value = true;
        try {
            const res = await fetch(`/admin/media-folders/${props.folder.id}/children`, {
                headers: { Accept: 'application/json' },
            });
            children.value = await res.json();
        } finally {
            loading.value = false;
        }
    }
}

function selectThis() {
    emit('select', props.folder.id);
}

function onChildSelect(folderId) {
    emit('select', folderId);
}
</script>

<template>
    <div>
        <!-- Row node — class "group" kích hoạt hover effect cho con cháu -->
        <div
            class="group flex items-center gap-1 rounded-lg px-2 py-1.5 text-sm cursor-pointer"
            :class="selectedFolderId === folder.id
                ? 'bg-indigo-50 text-indigo-700 font-medium'
                : 'text-slate-600 hover:bg-slate-100'"
            :style="{ paddingLeft: `${depth * 16 + 8}px` }"
        >
            <!-- Nút expand/collapse -->
            <button
                v-if="folder.children_count > 0"
                type="button"
                class="shrink-0 w-4 h-4 flex items-center justify-center text-slate-400 hover:text-slate-600"
                @click.stop="toggleExpand"
            >
                <svg
                    class="w-3 h-3 transition-transform"
                    :class="expanded ? 'rotate-90' : ''"
                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                </svg>
            </button>
            <span v-else class="w-4 shrink-0" />

            <!-- Folder icon -->
            <svg class="w-4 h-4 shrink-0 text-slate-400" viewBox="0 0 24 24"
                fill="none" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
            </svg>

            <!-- Tên folder — click chọn để filter Media -->
            <span class="truncate flex-1 select-none" @click.stop="selectThis">
                {{ folder.name }}
            </span>

            <!-- Badge số con: ẩn khi hover để nhường chỗ nút Edit -->
            <span
                v-if="folder.children_count > 0"
                class="text-xs text-slate-400 shrink-0 group-hover:hidden"
            >
                {{ folder.children_count }}
            </span>

            <!-- Nút Edit — chỉ hiện khi hover VÀ có quyền media-folder.update.
                 @click.stop ngăn event bubble lên row (tránh kích hoạt selectThis). -->
            <Link
                v-if="can('media-folder.update')"
                :href="`/admin/media-folders/${folder.id}/edit`"
                class="hidden group-hover:inline-flex items-center justify-center
                       w-5 h-5 rounded text-slate-400 hover:text-indigo-600 hover:bg-indigo-50
                       shrink-0 transition-colors"
                title="Sửa thư mục"
                @click.stop
            >
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" />
                </svg>
            </Link>
        </div>

        <!-- Children đệ quy -->
        <div v-if="expanded">
            <p v-if="loading"
                class="text-xs text-slate-400 py-1"
                :style="{ paddingLeft: `${(depth + 1) * 16 + 8}px` }"
            >
                Đang tải...
            </p>
            <FolderTreeNode
                v-for="child in children"
                :key="child.id"
                :folder="child"
                :selected-folder-id="selectedFolderId"
                :depth="depth + 1"
                @select="onChildSelect"
            />
        </div>
    </div>
</template>