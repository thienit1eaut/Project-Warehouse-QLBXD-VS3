<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputField from '@/Components/UI/InputField.vue';
import MediaPicker from '@/Components/Media/MediaPicker.vue';
import RichTextEditor from '@/Components/UI/RichTextEditor.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    pageTitle:        { type: String, default: '' },
    product:          { type: Object, default: null }, // null = tạo mới
    categoryOptions:  { type: Array,  default: () => [] },
    brandOptions:     { type: Array,  default: () => [] },
    supplierOptions:  { type: Array,  default: () => [] },
    unitOptions:      { type: Array,  default: () => [] },
});

const isEditing = computed(() => !!props.product);

// img là media_id (int, nullable) — cùng contract Brand/Category.
const form = useForm({
    sku:                props.product?.sku                ?? '',
    name:               props.product?.name               ?? '',
    short_description:  props.product?.short_description  ?? '',
    category_id:        props.product?.category_id        ?? '',
    brand_id:           props.product?.brand_id            ?? '',
    supplier_id:        props.product?.supplier_id        ?? '',
    unit_id:            props.product?.unit_id            ?? '',
    img:                props.product?.img                ?? null,
    description:        props.product?.description        ?? '',
    selling_price:      props.product?.selling_price      ?? 0,
    is_active:          props.product?.is_active          ?? true,
});

function submit() {
    const payload = {
        ...form.data(),
        category_id: form.category_id || null,
        brand_id:    form.brand_id    || null,
        supplier_id: form.supplier_id || null,
        unit_id:     form.unit_id     || null,
    };

    const options = { onError: jumpToErrorTab };

    if (isEditing.value) {
        form.transform(() => payload).put(`/admin/products/${props.product.id}`, options);
    } else {
        form.transform(() => payload).post('/admin/products', options);
    }
}

// ── Tabs ─────────────────────────────────────────────────────────────────
// Native Tailwind tab UI, không dùng thêm thư viện. "Thông số kỹ thuật"
// hiện chưa có field nào được chốt trong schema Phase 1 — để trống kèm
// ghi chú, KHÔNG tự bịa field (color/size/weight...) khi chưa có yêu cầu.
const tabs = [
    { key: 'basic', label: 'Thông tin cơ bản' },
    { key: 'description', label: 'Mô tả' },
    { key: 'specs', label: 'Thông số kỹ thuật' },
];
const activeTab = ref('basic');

// Field nào lỗi thì tự nhảy về đúng tab chứa field đó — tránh user submit
// xong tưởng form "im lặng" vì lỗi nằm ở tab đang ẩn.
function jumpToErrorTab() {
    const basicFields = ['sku', 'name', 'short_description', 'category_id', 'brand_id', 'supplier_id', 'unit_id', 'img', 'selling_price'];
    const hasBasicError = basicFields.some((f) => form.errors[f]);
    if (hasBasicError) {
        activeTab.value = 'basic';
    } else if (form.errors.description) {
        activeTab.value = 'description';
    }
}
</script>

<template>
    <Head :title="pageTitle" />

    <div class="max-w-3xl">
        <div class="rounded-xl border border-slate-200 bg-white">

            <!-- Tab nav -->
            <div class="flex border-b border-slate-100 px-6 pt-4">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    class="border-b-2 px-4 pb-3 text-sm font-medium transition-colors"
                    :class="activeTab === tab.key
                        ? 'border-indigo-600 text-indigo-600'
                        : 'border-transparent text-slate-500 hover:text-slate-700'"
                    @click="activeTab = tab.key"
                >
                    {{ tab.label }}
                </button>
            </div>

            <form @submit.prevent="submit" class="p-6">

                <!-- Tab: Thông tin cơ bản -->
                <div v-show="activeTab === 'basic'" class="space-y-5">
                    <div class="grid grid-cols-2 gap-4">
                        <InputField label="Mã SKU" id="sku" :error="form.errors.sku" required>
                            <input
                                id="sku"
                                v-model="form.sku"
                                type="text"
                                placeholder="VD: SP-000123"
                                class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm
                                       transition focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                :class="{ 'border-red-400': form.errors.sku }"
                            />
                        </InputField>

                        <InputField label="Tên sản phẩm" id="name" :error="form.errors.name" required>
                            <input
                                id="name"
                                v-model="form.name"
                                type="text"
                                placeholder="VD: Xe đạp địa hình Giant ATX 2026"
                                class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm
                                       transition focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                :class="{ 'border-red-400': form.errors.name }"
                            />
                        </InputField>
                    </div>

                    <InputField label="Mô tả ngắn" id="short_description" :error="form.errors.short_description">
                        <input
                            id="short_description"
                            v-model="form.short_description"
                            type="text"
                            maxlength="500"
                            placeholder="Tóm tắt ngắn gọn, hiển thị ở danh sách/preview sản phẩm"
                            class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm
                                   transition focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            :class="{ 'border-red-400': form.errors.short_description }"
                        />
                        <p class="mt-1 text-xs text-slate-400">{{ (form.short_description || '').length }}/500 ký tự</p>
                    </InputField>

                    <div class="grid grid-cols-2 gap-4">
                        <InputField label="Danh mục" id="category_id" :error="form.errors.category_id" required>
                            <select
                                id="category_id"
                                v-model="form.category_id"
                                class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm
                                       transition focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                :class="{ 'border-red-400': form.errors.category_id }"
                            >
                                <option value="">— Chọn danh mục —</option>
                                <option v-for="c in categoryOptions" :key="c.id" :value="c.id">
                                    {{ c.parent_id ? '— ' : '' }}{{ c.name }}
                                </option>
                            </select>
                        </InputField>

                        <InputField label="Thương hiệu" id="brand_id" :error="form.errors.brand_id">
                            <select
                                id="brand_id"
                                v-model="form.brand_id"
                                class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm
                                       transition focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                                <option value="">— Không có —</option>
                                <option v-for="b in brandOptions" :key="b.id" :value="b.id">{{ b.name }}</option>
                            </select>
                        </InputField>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <InputField label="Nhà cung cấp" id="supplier_id" :error="form.errors.supplier_id">
                            <select
                                id="supplier_id"
                                v-model="form.supplier_id"
                                class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm
                                       transition focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                                <option value="">— Không có —</option>
                                <option v-for="s in supplierOptions" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                        </InputField>

                        <InputField label="Đơn vị tính" id="unit_id" :error="form.errors.unit_id" required>
                            <select
                                id="unit_id"
                                v-model="form.unit_id"
                                class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm
                                       transition focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                :class="{ 'border-red-400': form.errors.unit_id }"
                            >
                                <option value="">— Chọn đơn vị —</option>
                                <option v-for="u in unitOptions" :key="u.id" :value="u.id">{{ u.name }}</option>
                            </select>
                        </InputField>
                    </div>

                    <InputField label="Ảnh đại diện" id="img" :error="form.errors.img">
                        <MediaPicker
                            v-model="form.img"
                            :initial-media="product?.media ?? null"
                        />
                    </InputField>

                    <InputField label="Giá bán" id="selling_price" :error="form.errors.selling_price" required>
                        <div class="relative mt-1">
                            <input
                                id="selling_price"
                                v-model.number="form.selling_price"
                                type="number"
                                min="0"
                                step="1000"
                                class="block w-full rounded-lg border border-slate-300 px-3 py-2.5 pr-10 text-sm
                                       transition focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                :class="{ 'border-red-400': form.errors.selling_price }"
                            />
                            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-slate-400">đ</span>
                        </div>
                    </InputField>

                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            role="switch"
                            :aria-checked="form.is_active"
                            :class="[
                                'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full',
                                'border-2 border-transparent transition-colors duration-200',
                                form.is_active ? 'bg-indigo-600' : 'bg-slate-200',
                            ]"
                            @click="form.is_active = !form.is_active"
                        >
                            <span
                                :class="[
                                    'pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow',
                                    'transition duration-200',
                                    form.is_active ? 'translate-x-5' : 'translate-x-0',
                                ]"
                            />
                        </button>
                        <span class="text-sm text-slate-700">
                            {{ form.is_active ? 'Sản phẩm đang bán' : 'Sản phẩm ngừng bán' }}
                        </span>
                    </div>
                </div>

                <!-- Tab: Mô tả -->
                <div v-show="activeTab === 'description'" class="space-y-2">
                    <label class="block text-sm font-medium text-slate-700">Nội dung mô tả chi tiết</label>
                    <RichTextEditor v-model="form.description" placeholder="Nhập mô tả chi tiết sản phẩm..." />
                    <p v-if="form.errors.description" class="text-xs text-red-500">{{ form.errors.description }}</p>
                </div>

                <!-- Tab: Thông số kỹ thuật — chưa có field nào được chốt trong schema Phase 1 -->
                <div v-show="activeTab === 'specs'" class="flex flex-col items-center justify-center py-16 text-center">
                    <p class="text-sm text-slate-400">
                        Thông số kỹ thuật chưa được định nghĩa trong schema Phase 1.
                    </p>
                    <p class="mt-1 text-xs text-slate-400">
                        Sẽ bổ sung khi có yêu cầu field cụ thể (VD: kích thước khung, trọng lượng, chất liệu...).
                    </p>
                </div>

                <!-- Actions — luôn hiện, không phụ thuộc tab đang xem -->
                <div class="mt-6 flex items-center gap-3 border-t border-slate-100 pt-4">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-medium text-white
                               transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <span v-if="form.processing">Đang lưu...</span>
                        <span v-else>{{ isEditing ? 'Cập nhật' : 'Tạo sản phẩm' }}</span>
                    </button>
                    <Link
                        href="/admin/products"
                        class="rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-medium
                               text-slate-700 transition hover:bg-slate-50"
                    >
                        Huỷ
                    </Link>
                </div>

            </form>
        </div>
    </div>
</template>