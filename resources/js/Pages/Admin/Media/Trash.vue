<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Badge from '@/Components/UI/Badge.vue';
import { usePermission } from '@/Composables/usePermission';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    pageTitle: { type: String, default: '' },
    media: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const { can } = usePermission();

const search = ref(props.filters.search ?? '');
const type = ref(props.filters.type ?? '');

let debounceTimer = null;
watch([search, type], () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        router.get('/admin/media/trash', {
            search: search.value || undefined,
            type: type.value || undefined,
        }, { preserveState: true, replace: true });
    }, 400);
});

function restoreMedia(item) {
    router.post(`/admin/media/${item.id}/restore`, {}, { preserveScroll: true });
}

function forceDeleteMedia(item) {
    if (confirm(`Xoá VĨNH VIỄN "${item.title || item.original_name}"? Hành động này không thể hoàn tác.`)) {
        router.delete(`/admin/media/${item.id}/force`, { preserveScroll: true });
    }
}
</script>

<template>
    <Head :title="pageTitle" />

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-semibold text-slate-800">{{ pageTitle }}</h1>
        <Link href="/admin/media" class="text-sm text-indigo-600 hover:underline">← Quay lại Media</Link>
    </div>

    <div class="flex flex-wrap gap-2 mb-4">
        <input
            v-model="search" type="text" placeholder="Tìm theo tên..."
            class="flex-1 min-w-[200px] rounded-lg border border-slate-300 px-3 py-2 text-sm"
        />
        <select v-model="type" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">Mọi loại</option>
            <option value="image">Ảnh</option>
            <option value="video">Video</option>
            <option value="document">Tài liệu</option>
        </select>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Tên file</th>
                    <th class="px-4 py-3">Loại</th>
                    <th class="px-4 py-3">Đã xoá lúc</th>
                    <th class="px-4 py-3 text-right">Hành động</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr v-for="item in media.data" :key="item.id">
                    <td class="px-4 py-3">{{ item.title || item.original_name }}</td>
                    <td class="px-4 py-3"><Badge variant="gray">{{ item.type }}</Badge></td>
                    <td class="px-4 py-3 text-slate-500">{{ item.deleted_at }}</td>
                    <td class="px-4 py-3 text-right space-x-2">
                        <button
                            v-if="can('media.restore')"
                            class="text-indigo-600 hover:underline"
                            @click="restoreMedia(item)"
                        >
                            Khôi phục
                        </button>
                        <button
                            v-if="can('media.force-delete')"
                            class="text-red-600 hover:underline"
                            @click="forceDeleteMedia(item)"
                        >
                            Xoá vĩnh viễn
                        </button>
                    </td>
                </tr>
                <tr v-if="media.data.length === 0">
                    <td colspan="4" class="px-4 py-8 text-center text-slate-400">Thùng rác trống.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div v-if="media.links.length > 3" class="flex justify-center gap-1 mt-6">
        <Link
            v-for="(link, idx) in media.links" :key="idx"
            :href="link.url || ''" v-html="link.label"
            class="px-3 py-1.5 rounded-lg text-sm"
            :class="[
                link.active ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100',
                !link.url && 'pointer-events-none text-slate-300',
            ]"
            preserve-state
        />
    </div>
</template>