<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import FolderTree from '@/Components/Media/FolderTree.vue';
import MediaCard from '@/Components/Media/MediaCard.vue';
import { usePermission } from '@/Composables/usePermission';
import { buildFolderOptions } from '@/Composables/useFolderOptions';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    pageTitle: { type: String, default: '' },
    media: { type: Object, required: true },       // Laravel paginator
    rootFolders: { type: Array, required: true },
    allFolders: { type: Array, default: () => [] }, // toàn bộ folder mọi cấp — cho bulk picker
    filters: { type: Object, default: () => ({}) },
});

const { can } = usePermission();

const search = ref(props.filters.search ?? '');
const type = ref(props.filters.type ?? '');
const status = ref(props.filters.status ?? '');
const selectedFolderId = ref(props.filters.folder_id ? Number(props.filters.folder_id) : null);

// Search debounce 400ms — đúng convention Brands/Categories hiện có,
// KHÔNG filter client-side, luôn gọi lại backend qua router.get().
let debounceTimer = null;
watch(search, () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(applyFilters, 400);
});

watch([type, status], applyFilters);

function applyFilters() {
    router.get('/admin/media', {
        search: search.value || undefined,
        type: type.value || undefined,
        status: status.value || undefined,
        folder_id: selectedFolderId.value || undefined,
    }, { preserveState: true, replace: true });
}

function onSelectFolder(folderId) {
    selectedFolderId.value = folderId;
    applyFilters();
}

function confirmDelete(media) {
    if (confirm(`Chuyển "${media.title || media.original_name}" vào thùng rác?`)) {
        router.delete(`/admin/media/${media.id}`, { preserveScroll: true });
    }
}

// ── Bulk selection ──────────────────────────────────────────────────────────
// Chỉ áp dụng cho Media đang hiển thị trên page hiện tại — không "chọn toàn bộ
// mọi trang", đúng phạm vi đã chốt.
const selectedIds = ref(new Set());

const selectedCount = computed(() => selectedIds.value.size);

const allOnPageSelected = computed(() => {
    return props.media.data.length > 0
        && props.media.data.every((item) => selectedIds.value.has(item.id));
});

function toggleSelect(id) {
    const next = new Set(selectedIds.value);
    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }
    selectedIds.value = next;
}

function toggleSelectAll() {
    if (allOnPageSelected.value) {
        selectedIds.value = new Set();
    } else {
        selectedIds.value = new Set(props.media.data.map((item) => item.id));
    }
}

function clearSelection() {
    selectedIds.value = new Set();
}

// Đổi trang/paginate/filter -> selection của trang cũ không còn ý nghĩa.
watch(() => props.media.data, () => {
    selectedIds.value = new Set();
});

// ── Bulk actions ─────────────────────────────────────────────────────────────
const bulkAction = ref('');
const showFolderPicker = ref(false);
const bulkFolderId = ref('');

// Dropdown lồng cấp cho bulk add-to-folder — dùng allFolders (mọi cấp),
// không chỉ folder gốc.
const folderOptions = computed(() => buildFolderOptions(props.allFolders));

function onBulkActionChange() {
    if (bulkAction.value === 'add-to-folder') {
        showFolderPicker.value = true;
        return;
    }

    if (bulkAction.value === 'trash') {
        confirmBulkDelete();
    }

    bulkAction.value = '';
}

function confirmBulkDelete() {
    const count = selectedCount.value;
    if (!confirm(`Bạn có chắc muốn chuyển ${count} media vào thùng rác?`)) {
        return;
    }

    router.delete('/admin/media/bulk', {
        data: { ids: Array.from(selectedIds.value) },
        preserveScroll: true,
        onSuccess: () => clearSelection(),
    });
}

function confirmBulkAddToFolder() {
    if (!bulkFolderId.value) return;

    router.post('/admin/media/bulk/add-to-folder', {
        ids: Array.from(selectedIds.value),
        folder_id: bulkFolderId.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            clearSelection();
            showFolderPicker.value = false;
            bulkFolderId.value = '';
            bulkAction.value = '';
        },
    });
}

function cancelFolderPicker() {
    showFolderPicker.value = false;
    bulkFolderId.value = '';
    bulkAction.value = '';
}
</script>

<template>
    <Head :title="pageTitle" />

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-semibold text-slate-800">{{ pageTitle }}</h1>
        <div class="flex gap-2">
            <Link
                v-if="can('media.restore')"
                href="/admin/media/trash"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-600 hover:bg-slate-50"
            >
                Thùng rác
            </Link>
            <Link
                v-if="can('media.create')"
                href="/admin/media/upload"
                class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-sm text-white hover:bg-indigo-700"
            >
                Upload Media
            </Link>
        </div>
    </div>

    <div class="flex gap-4">
        <!-- Sidebar cây Folder -->
        <aside class="w-64 shrink-0 self-start rounded-xl border border-slate-200 bg-white p-3">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">
                Thư mục
            </p>
            <FolderTree
                :root-folders="rootFolders"
                :selected-folder-id="selectedFolderId"
                @select="onSelectFolder"
            />
        </aside>

        <!-- Nội dung chính -->
        <div class="flex-1 min-w-0">
            <!-- Filter bar -->
            <div class="flex flex-wrap gap-2 mb-3">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Tìm theo tên, tiêu đề..."
                    class="flex-1 min-w-[200px] rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                />
                <select v-model="type" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Mọi loại</option>
                    <option value="image">Ảnh</option>
                    <option value="video">Video</option>
                    <option value="document">Tài liệu</option>
                </select>
                <select v-model="status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Mọi trạng thái</option>
                    <option value="ready">Sẵn sàng</option>
                    <option value="pending">Đang xử lý</option>
                    <option value="failed">Lỗi</option>
                </select>
            </div>

            <!-- Bulk action toolbar — chỉ hiện khi có ít nhất 1 Media trên page -->
            <div
                v-if="media.data.length > 0"
                class="flex items-center gap-3 mb-3 px-3 py-2 rounded-lg border border-slate-200 bg-slate-50"
            >
                <label class="flex items-center gap-2 text-sm text-slate-600 cursor-pointer select-none">
                    <input
                        type="checkbox"
                        :checked="allOnPageSelected"
                        class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                        @change="toggleSelectAll"
                    />
                    Chọn tất cả
                </label>

                <span v-if="selectedCount > 0" class="text-sm text-slate-500">
                    {{ selectedCount }} media đã chọn
                </span>

                <div class="ml-auto flex items-center gap-2">
                    <select
                        v-model="bulkAction"
                        :disabled="selectedCount === 0"
                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm
                               disabled:opacity-50 disabled:cursor-not-allowed"
                        @change="onBulkActionChange"
                    >
                        <option value="">Thao tác hàng loạt</option>
                        <option v-if="can('media.manage-folder')" value="add-to-folder">
                            Thêm vào thư mục
                        </option>
                        <option v-if="can('media.delete')" value="trash">
                            Chuyển vào thùng rác
                        </option>
                    </select>

                    <button
                        v-if="selectedCount > 0"
                        type="button"
                        class="text-sm text-slate-400 hover:text-slate-600"
                        @click="clearSelection"
                    >
                        Bỏ chọn
                    </button>
                </div>
            </div>

            <!-- Folder picker — hiện khi chọn "Thêm vào thư mục" -->
            <div
                v-if="showFolderPicker"
                class="flex items-center gap-2 mb-3 px-3 py-2 rounded-lg border border-indigo-200 bg-indigo-50"
            >
                <span class="text-sm text-slate-600">Chọn thư mục đích:</span>
                <select
                    v-model="bulkFolderId"
                    class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm flex-1 max-w-xs"
                >
                    <option value="">— Chọn thư mục —</option>
                    <option v-for="opt in folderOptions" :key="opt.id" :value="opt.id">
                        {{ opt.label }}
                    </option>
                </select>
                <button
                    type="button"
                    :disabled="!bulkFolderId"
                    class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm text-white hover:bg-indigo-700
                           disabled:opacity-50 disabled:cursor-not-allowed"
                    @click="confirmBulkAddToFolder"
                >
                    Thêm
                </button>
                <button
                    type="button"
                    class="text-sm text-slate-400 hover:text-slate-600"
                    @click="cancelFolderPicker"
                >
                    Huỷ
                </button>
            </div>

            <!-- Grid Media -->
            <div v-if="media.data.length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
                <MediaCard
                    v-for="item in media.data"
                    :key="item.id"
                    :media="item"
                    :can-update="can('media.update')"
                    :can-delete="can('media.delete')"
                    :selectable="true"
                    :selected="selectedIds.has(item.id)"
                    @delete="confirmDelete"
                    @toggle-select="toggleSelect"
                />
            </div>
            <div v-else class="rounded-xl border border-dashed border-slate-300 py-16 text-center text-sm text-slate-400">
                Không có media nào.
            </div>

            <!-- Pagination — đúng convention Laravel paginator hiện có -->
            <div v-if="media.links.length > 3" class="flex justify-center gap-1 mt-6">
                <Link
                    v-for="(link, idx) in media.links"
                    :key="idx"
                    :href="link.url || ''"
                    v-html="link.label"
                    class="px-3 py-1.5 rounded-lg text-sm"
                    :class="[
                        link.active ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100',
                        !link.url && 'pointer-events-none text-slate-300',
                    ]"
                    preserve-state
                />
            </div>
        </div>
    </div>
</template>