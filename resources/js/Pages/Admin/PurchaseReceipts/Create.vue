<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputField from '@/Components/UI/InputField.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    pageTitle: { type: String, default: '' },
    supplierOptions: { type: Array, default: () => [] },
    warehouseOptions: { type: Array, default: () => [] },
    productOptions: { type: Array, default: () => [] },
    today: { type: String, default: '' },
});

const emptyItem = () => ({ product_id: '', quantity: '', unit_price: '', expiry_date: '' });

const form = useForm({
    supplier_id: '',
    warehouse_id: '',
    receipt_date: props.today,
    note: '',
    items: [emptyItem()],
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
    // Server (PurchaseReceiptService) là source of truth; Vue chỉ gửi input.
    form.post('/admin/purchase-receipts');
}
</script>

<template>
    <Head :title="pageTitle" />

    <div class="max-w-4xl space-y-4">
        <div class="flex items-center justify-between">
            <h1 class="text-lg font-semibold text-slate-800">Tạo phiếu nhập kho</h1>
            <Link href="/admin/purchase-receipts" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
                ← Quay lại
            </Link>
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <div class="grid grid-cols-1 gap-4 rounded-xl border border-slate-200 bg-white p-6 sm:grid-cols-2">
                <InputField label="Nhà cung cấp" id="supplier_id" :error="form.errors.supplier_id" required>
                    <select id="supplier_id" v-model="form.supplier_id" :class="[inputClass, 'mt-1']">
                        <option value="" disabled>-- Chọn nhà cung cấp --</option>
                        <option v-for="s in supplierOptions" :key="s.id" :value="s.id">{{ s.code }} - {{ s.name }}</option>
                    </select>
                </InputField>

                <InputField label="Kho nhập" id="warehouse_id" :error="form.errors.warehouse_id" required>
                    <select id="warehouse_id" v-model="form.warehouse_id" :class="[inputClass, 'mt-1']">
                        <option value="" disabled>-- Chọn kho --</option>
                        <option v-for="wh in warehouseOptions" :key="wh.id" :value="wh.id">{{ wh.code }} - {{ wh.name }}</option>
                    </select>
                </InputField>

                <InputField label="Ngày nhập" id="receipt_date" :error="form.errors.receipt_date" required>
                    <input id="receipt_date" v-model="form.receipt_date" type="date" :class="[inputClass, 'mt-1']" />
                </InputField>

                <InputField label="Ghi chú" id="note" :error="form.errors.note">
                    <input id="note" v-model="form.note" type="text" :class="[inputClass, 'mt-1']" />
                </InputField>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-6">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-slate-700">Danh sách sản phẩm</h2>
                    <button type="button" @click="addItem" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
                        + Thêm sản phẩm
                    </button>
                </div>

                <p v-if="form.errors.items" class="mb-2 text-sm text-red-600">{{ form.errors.items }}</p>

                <div class="space-y-3">
                    <div
                        v-for="(item, index) in form.items"
                        :key="index"
                        class="grid grid-cols-12 items-start gap-2"
                    >
                        <div class="col-span-12 sm:col-span-4">
                            <select v-model="item.product_id" :class="inputClass">
                                <option value="" disabled>-- Sản phẩm --</option>
                                <option v-for="p in productOptions" :key="p.id" :value="p.id">{{ p.sku }} - {{ p.name }}</option>
                            </select>
                            <p v-if="form.errors[`items.${index}.product_id`]" class="mt-1 text-xs text-red-600">
                                {{ form.errors[`items.${index}.product_id`] }}
                            </p>
                        </div>

                        <div class="col-span-4 sm:col-span-2">
                            <input v-model="item.quantity" type="number" step="0.001" min="0.001" placeholder="Số lượng" :class="inputClass" />
                            <p v-if="form.errors[`items.${index}.quantity`]" class="mt-1 text-xs text-red-600">
                                {{ form.errors[`items.${index}.quantity`] }}
                            </p>
                        </div>

                        <div class="col-span-4 sm:col-span-2">
                            <input v-model="item.unit_price" type="number" step="0.01" min="0" placeholder="Đơn giá" :class="inputClass" />
                            <p v-if="form.errors[`items.${index}.unit_price`]" class="mt-1 text-xs text-red-600">
                                {{ form.errors[`items.${index}.unit_price`] }}
                            </p>
                        </div>

                        <div class="col-span-4 sm:col-span-3">
                            <input v-model="item.expiry_date" type="date" :class="inputClass" title="Hạn sử dụng (không bắt buộc)" />
                            <p v-if="form.errors[`items.${index}.expiry_date`]" class="mt-1 text-xs text-red-600">
                                {{ form.errors[`items.${index}.expiry_date`] }}
                            </p>
                        </div>

                        <div class="col-span-12 sm:col-span-1">
                            <button
                                type="button"
                                @click="removeItem(index)"
                                :disabled="form.items.length === 1"
                                class="rounded-lg px-2 py-2 text-sm text-red-600 hover:bg-red-50 disabled:opacity-30"
                            >
                                Xoá
                            </button>
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
                    <span v-else>Lưu phiếu nháp</span>
                </button>
                <Link
                    href="/admin/purchase-receipts"
                    class="rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                >
                    Huỷ
                </Link>
            </div>
        </form>
    </div>
</template>