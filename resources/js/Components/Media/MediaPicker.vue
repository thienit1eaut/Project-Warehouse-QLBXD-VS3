<script setup>
import { ref, computed, watch } from 'vue';

// Field component chọn 1 ảnh từ Media Library — dùng chung cho mọi module
// cần gắn ảnh (Brand, sau này Category/Product...). Chỉ gửi media_id ra
// ngoài qua v-model, không tự upload file, không tự query "medias" table
// trực tiếp từ Vue — luôn qua endpoint GET /admin/media/picker (JSON) đã
// có sẵn ở backend.
const props = defineProps({
    modelValue: { type: [Number, String, null], default: null }, // media_id
    initialMedia: { type: Object, default: null }, // Media object đầy đủ, dùng để preview ban đầu khi edit
});

const emit = defineEmits(['update:modelValue']);

// Ảnh đang được chọn để preview — ưu tiên initialMedia lúc mới mount (khi
// edit Brand đã có sẵn ảnh), sau đó tự cập nhật khi user chọn ảnh mới từ
// picker (không cần round-trip fetch lại, item trong danh sách đã đủ dữ liệu).
const selectedMedia = ref(props.initialMedia);

// Đồng bộ nếu initialMedia đổi sau khi mount (hiếm khi xảy ra, nhưng để an toàn
// nếu component được tái sử dụng với props thay đổi từ ngoài).
watch(() => props.initialMedia, (val) => {
    selectedMedia.value = val;
});

const showPicker = ref(false);
const search = ref('');
const items = ref([]);
const loading = ref(false);
const pagination = ref(null); // { current_page, last_page, links... } từ Laravel paginator

const uploading = ref(false);
const uploadError = ref('');
const fileInput = ref(null);

let debounceTimer = null;

function openPicker() {
    showPicker.value = true;
    if (items.value.length === 0) {
        fetchImages();
    }
}

function closePicker() {
    showPicker.value = false;
}

async function fetchImages(page = 1) {
    loading.value = true;
    try {
        const params = new URLSearchParams({ page });
        if (search.value) params.set('search', search.value);

        const res = await fetch(`/admin/media/picker?${params}`, {
            headers: { Accept: 'application/json' },
        });
        const data = await res.json();

        items.value = data.data;
        pagination.value = data;
    } finally {
        loading.value = false;
    }
}

watch(search, () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchImages(1), 400);
});

/**
 * Đọc XSRF-TOKEN từ cookie (Laravel session-based CSRF) — cần thiết vì đây
 * là request POST qua fetch() thuần, không đi qua Inertia router (Inertia
 * tự đính X-XSRF-TOKEN cho request của chính nó, nhưng fetch() thủ công thì
 * không). Cookie này Laravel tự set sẵn cho mọi authenticated session.
 */
function getXsrfToken() {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : null;
}

function triggerFileInput() {
    fileInput.value?.click();
}

/**
 * Upload ảnh mới ngay trong picker — gọi thẳng POST /admin/media (route +
 * validation + Service upload() đã có sẵn từ Phase 6, không tạo endpoint
 * mới). Backend trả JSON khi nhận Accept: application/json thay vì redirect.
 * Sau khi upload xong: thêm vào đầu danh sách đang hiển thị + tự động chọn
 * luôn ảnh đó (đúng yêu cầu "chọn ảnh mới vừa up hiện ngay vào form").
 */
async function onFileSelected(e) {
    const file = e.target.files?.[0];
    if (!file) return;

    uploading.value = true;
    uploadError.value = '';

    try {
        const formData = new FormData();
        formData.append('file', file);

        const res = await fetch('/admin/media', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': getXsrfToken(),
            },
            credentials: 'same-origin',
            body: formData,
        });

        if (!res.ok) {
            const errData = await res.json().catch(() => ({}));
            uploadError.value = errData.message
                || errData.errors?.file?.[0]
                || 'Upload thất bại, vui lòng thử lại.';
            return;
        }

        const newMedia = await res.json();

        // Media trùng SHA-256 với file đã có (Service tự phát hiện) sẽ trả
        // về Media CŨ — nếu đã có trong danh sách đang hiển thị thì không
        // thêm trùng, chỉ chọn nó.
        if (!items.value.some((i) => i.id === newMedia.id)) {
            items.value = [newMedia, ...items.value];
        }

        selectImage(newMedia);
    } catch (err) {
        uploadError.value = 'Không thể kết nối tới server, vui lòng thử lại.';
    } finally {
        uploading.value = false;
        e.target.value = ''; // reset input để chọn lại cùng 1 file nếu cần
    }
}

function selectImage(item) {
    selectedMedia.value = item;
    emit('update:modelValue', item.id);
    closePicker();
}

function removeImage() {
    selectedMedia.value = null;
    emit('update:modelValue', null);
}

// Ưu tiên variant 'thumbnail' cho preview nhỏ gọn, fallback url gốc.
const previewUrl = computed(() => {
    if (!selectedMedia.value) return null;
    const thumb = selectedMedia.value.variants?.find((v) => v.name === 'thumbnail');
    return thumb?.url ?? selectedMedia.value.url;
});
</script>

<template>
    <div>
        <!-- Preview + actions -->
        <div class="flex items-center gap-4">
            <div
                class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden
                       rounded-lg border border-dashed border-slate-300 bg-slate-50"
            >
                <img
                    v-if="previewUrl"
                    :src="previewUrl"
                    :alt="selectedMedia?.alt || selectedMedia?.original_name"
                    class="h-full w-full object-contain"
                />
                <span v-else class="text-xs text-slate-400">Chưa có</span>
            </div>

            <div class="flex flex-col gap-1.5">
                <button
                    type="button"
                    class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium
                           text-slate-600 hover:bg-slate-50"
                    @click="openPicker"
                >
                    Chọn ảnh
                </button>
                <button
                    v-if="selectedMedia"
                    type="button"
                    class="text-xs text-red-500 hover:underline"
                    @click="removeImage"
                >
                    Xoá ảnh
                </button>
            </div>
        </div>

        <!-- Overlay picker — markup thuần Tailwind, không dùng modal framework -->
        <div
            v-if="showPicker"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            @click.self="closePicker"
        >
            <div class="flex max-h-[85vh] w-full max-w-2xl flex-col rounded-xl bg-white shadow-xl">

                <!-- Header -->
                <div class="flex items-center justify-between border-b border-slate-100 p-4">
                    <h3 class="text-sm font-semibold text-slate-800">Chọn ảnh từ Media Library</h3>
                    <div class="flex items-center gap-2">
                        <input
                            ref="fileInput"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="hidden"
                            @change="onFileSelected"
                        />
                        <button
                            type="button"
                            :disabled="uploading"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300
                                   px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50
                                   disabled:cursor-not-allowed disabled:opacity-50"
                            @click="triggerFileInput"
                        >
                            <svg v-if="!uploading" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                            </svg>
                            <svg v-else class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                            </svg>
                            {{ uploading ? 'Đang tải lên...' : 'Tải ảnh lên' }}
                        </button>
                        <button type="button" class="text-slate-400 hover:text-slate-600" @click="closePicker">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Upload error -->
                <div v-if="uploadError" class="border-b border-red-100 bg-red-50 px-4 py-2 text-xs text-red-600">
                    {{ uploadError }}
                </div>

                <!-- Search -->
                <div class="border-b border-slate-100 p-3">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Tìm ảnh theo tên..."
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm
                               focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                    />
                </div>

                <!-- Grid ảnh -->
                <div class="flex-1 overflow-y-auto p-3">
                    <p v-if="loading" class="py-10 text-center text-sm text-slate-400">Đang tải...</p>

                    <p v-else-if="items.length === 0" class="py-10 text-center text-sm text-slate-400">
                        Không tìm thấy ảnh nào.
                    </p>

                    <div v-else class="grid grid-cols-4 gap-2 sm:grid-cols-5">
                        <button
                            v-for="item in items"
                            :key="item.id"
                            type="button"
                            class="aspect-square overflow-hidden rounded-lg border-2 border-transparent
                                   bg-slate-50 hover:border-indigo-400 transition-colors"
                            :title="item.original_name"
                            @click="selectImage(item)"
                        >
                            <img
                                :src="item.variants?.find(v => v.name === 'thumbnail')?.url ?? item.url"
                                :alt="item.alt || item.original_name"
                                class="h-full w-full object-cover"
                                loading="lazy"
                            />
                        </button>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="pagination && pagination.last_page > 1" class="flex items-center justify-center gap-2 border-t border-slate-100 p-3">
                    <button
                        type="button"
                        :disabled="pagination.current_page <= 1"
                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs
                               disabled:opacity-40 disabled:cursor-not-allowed hover:bg-slate-50"
                        @click="fetchImages(pagination.current_page - 1)"
                    >
                        Trước
                    </button>
                    <span class="text-xs text-slate-500">
                        {{ pagination.current_page }} / {{ pagination.last_page }}
                    </span>
                    <button
                        type="button"
                        :disabled="pagination.current_page >= pagination.last_page"
                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs
                               disabled:opacity-40 disabled:cursor-not-allowed hover:bg-slate-50"
                        @click="fetchImages(pagination.current_page + 1)"
                    >
                        Sau
                    </button>
                </div>

            </div>
        </div>
    </div>
</template>