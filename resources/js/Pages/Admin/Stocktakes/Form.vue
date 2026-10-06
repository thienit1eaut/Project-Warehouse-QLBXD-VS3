<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputField from '@/Components/UI/InputField.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    pageTitle: { type: String, default: '' },
    stocktake: { type: Object, default: null },
    warehouseOptions: { type: Array, default: () => [] },
    productOptions: { type: Array, default: () => [] },
    today: { type: String, default: '' },
});

const isEdit = !!props.stocktake;
const page = usePage();
const documentError = computed(() => page.props.errors?.document ?? null);

const emptyItem = () => ({ product_id: '', actual_quantity: '' });

const form = useForm({
    warehouse_id: props.stocktake?.warehouse_id ?? '',
    stocktake_date: props.stocktake?.stocktake_date ?? props.today,
    note: props.stocktake?.note ?? '',
    items: props.stocktake
        ? props.stocktake.items.map((i) => ({ product_id: i.product_id, actual_quantity: i.actual_quantity }))
        : [emptyItem()],
});

const inputClass = `block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm
    transition focus:outline-none focus:ring-2 focus:ring-indigo-500`;

function addItem() {
    form.items.push(emptyItem());
}

function removeItem(index) {
    if (form.items.length > 1) form.items.splice(index, 1);
}

function submit() {
    if (isEdit) form.put(`/admin/stocktake/${props.stocktake.id}`);
    else form.post('/admin/stocktake');
}
</script>

<template>
    <Head :title="pageTitle" />

    <div class="max-w-4xl space-y-4">
        <div class="flex items-center justify-between">
            <h1 class="text-lg font-semibold text-slate-800">{{ pageTitle }}</h1>
            <Link href="/admin/stocktake" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">← Quay lại</Link>
        </div>

        <p v-if="documentError" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ documentError }}
        </p>

        <p class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-700">
            Tồn hệ thống được chụp lại (snapshot) mỗi khi bạn lưu phiếu. Nhập số lượng bạn đếm thực tế;
            chênh lệch sẽ hiển thị ở trang chi tiết sau khi lưu.
        </p>

        <form @submit.prevent="submit" class="space-y-5">
            <div class="grid grid-cols-1 gap-4 rounded-xl border border-slate-200 bg-white p-6 sm:grid-cols-3">
                <InputField label="Kho kiểm kê" id="warehouse_id" :error="form.errors.warehouse_id" required>
                    <select id="warehouse_id" v-model="form.warehouse_id" :class="[inputClass, 'mt-1']">
                        <option value="" disabled>-- Chọn kho --</option>
                        <option v-for="wh in warehouseOptions" :key="wh.id" :value="wh.id">{{ wh.code }} - {{ wh.name }}</option>
                    </select>
                </InputField>

                <InputField label="Ngày kiểm kê" id="stocktake_date" :error="form.errors.stocktake_date" required>
                    <input id="stocktake_date" v-model="form.stocktake_date" type="date" :class="[inputClass, 'mt-1']" />
                </InputField>

                <InputField label="Ghi chú" id="note" :error="form.errors.note">
                    <input id="note" v-model="form.note" type="text" :class="[inputClass, 'mt-1']" />
                </InputField>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-6">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-slate-700">Sản phẩm kiểm kê</h2>
                    <button type="button" @click="addItem" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
                        + Thêm sản phẩm
                    </button>
                </div>

                <p v-if="form.errors.items" class="mb-2 text-sm text-red-600">{{ form.errors.items }}</p>

                <div class="space-y-3">
                    <div v-for="(item, index) in form.items" :key="index" class="grid grid-cols-12 items-start gap-2">
                        <div class="col-span-12 sm:col-span-7">
                            <select v-model="item.product_id" :class="inputClass">
                                <option value="" disabled>-- Sản phẩm --</option>
                                <option v-for="p in productOptions" :key="p.id" :value="p.id">{{ p.sku }} - {{ p.name }}</option>
                            </select>
                            <p v-if="form.errors[`items.${index}.product_id`]" class="mt-1 text-xs text-red-600">
                                {{ form.errors[`items.${index}.product_id`] }}
                            </p>
                        </div>

                        <div class="col-span-8 sm:col-span-4">
                            <input
                                v-model="item.actual_quantity"
                                type="number"
                                step="0.001"
                                min="0"
                                placeholder="Số lượng thực tế"
                                :class="inputClass"
                            />
                            <p v-if="form.errors[`items.${index}.actual_quantity`]" class="mt-1 text-xs text-red-600">
                                {{ form.errors[`items.${index}.actual_quantity`] }}
                            </p>
                        </div>

                        <div class="col-span-4 sm:col-span-1">
                            <button
                                type="button"
                                @click="removeItem(index)"
                                :disabled="form.items.length === 1"
                                class="rounded-lg px-2 py-2 text-sm text-red-600 hover:bg-red-50 disabled:opacity-30"
                            >Xoá</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-medium text-white
                           transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <span v-if="form.processing">Đang lưu...</span>
                    <span v-else>{{ isEdit ? 'Lưu và chụp lại tồn hệ thống' : 'Lưu phiếu nháp' }}</span>
                </button>
                <Link
                    href="/admin/stocktake"
                    class="rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                >Huỷ</Link>
            </div>
        </form>
    </div>
</template>