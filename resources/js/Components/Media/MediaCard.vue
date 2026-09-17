<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import Badge from '@/Components/UI/Badge.vue';

const props = defineProps({
    media: { type: Object, required: true },
    canUpdate: { type: Boolean, default: false },
    canDelete: { type: Boolean, default: false },
    selectable: { type: Boolean, default: false },
    selected: { type: Boolean, default: false },
});

const emit = defineEmits(['delete', 'toggle-select']);

// Ưu tiên variant 'thumbnail' nếu có, fallback URL gốc — đúng yêu cầu mục IV.
const thumbnailUrl = computed(() => {
    const thumb = props.media.variants?.find((v) => v.name === 'thumbnail');
    return thumb?.url ?? props.media.url;
});

const isImage = computed(() => props.media.type === 'image');

const typeBadge = {
    image: { label: 'Ảnh', variant: 'blue' },
    video: { label: 'Video', variant: 'purple' },
    document: { label: 'Tài liệu', variant: 'yellow' },
    audio: { label: 'Audio', variant: 'gray' },
    file: { label: 'File', variant: 'gray' },
};

function formatSize(bytes) {
    if (!bytes) return '';
    const kb = bytes / 1024;
    return kb < 1024 ? `${kb.toFixed(0)} KB` : `${(kb / 1024).toFixed(1)} MB`;
}
</script>

<template>
    <div
        class="group relative rounded-lg border bg-white overflow-hidden hover:shadow-md transition-shadow"
        :class="selected ? 'border-indigo-400 ring-2 ring-indigo-100' : 'border-slate-200'"
    >
        <!-- Checkbox chọn — luôn hiện nếu selected, chỉ hiện khi hover nếu chưa chọn -->
        <label
            v-if="selectable"
            class="absolute top-1.5 left-1.5 z-10 flex items-center justify-center w-6 h-6
                   rounded-md bg-white/90 shadow-sm cursor-pointer transition-opacity"
            :class="selected ? 'opacity-100' : 'opacity-0 group-hover:opacity-100'"
        >
            <input
                type="checkbox"
                :checked="selected"
                class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4"
                @change="emit('toggle-select', media.id)"
                @click.stop
            />
        </label>

        <!-- Thumbnail — chỉ hiển thị ảnh thật cho type image, các type khác dùng icon -->
        <div class="aspect-square bg-slate-50 flex items-center justify-center overflow-hidden">
            <img
                v-if="isImage"
                :src="thumbnailUrl"
                :alt="media.alt || media.original_name"
                class="w-full h-full object-cover"
                loading="lazy"
            />
            <svg v-else class="w-10 h-10 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
            </svg>
        </div>

        <div class="p-2 space-y-1">
            <p class="text-xs font-medium text-slate-700 truncate" :title="media.original_name">
                {{ media.title || media.original_name }}
            </p>
            <div class="flex items-center gap-1 flex-wrap">
                <Badge :variant="typeBadge[media.type]?.variant ?? 'gray'">
                    {{ typeBadge[media.type]?.label ?? media.type }}
                </Badge>
                <span class="text-[11px] text-slate-400">{{ formatSize(media.size) }}</span>
            </div>
        </div>

        <!-- Actions overlay — chỉ hiện khi hover, chỉ hiện nếu có quyền -->
        <div class="absolute top-1.5 right-1.5 flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
            <Link
                v-if="canUpdate"
                :href="`/admin/media/${media.id}/edit`"
                class="w-7 h-7 flex items-center justify-center rounded-md bg-white/90 text-slate-600 hover:text-indigo-600 shadow-sm"
                title="Sửa"
            >
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" />
                </svg>
            </Link>
            <button
                v-if="canDelete"
                type="button"
                class="w-7 h-7 flex items-center justify-center rounded-md bg-white/90 text-slate-600 hover:text-red-600 shadow-sm"
                title="Xoá"
                @click="emit('delete', media)"
            >
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                </svg>
            </button>
        </div>
    </div>
</template>