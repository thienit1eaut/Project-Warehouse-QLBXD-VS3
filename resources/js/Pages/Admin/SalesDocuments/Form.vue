<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputField from '@/Components/UI/InputField.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    pageTitle: { type: String, default: '' },
    document: { type: Object, default: null },
    customerOptions: { type: Array, default: () => [] },
    warehouseOptions: { type: Array, default: () => [] },
    productOptions: { type: Array, default: () => [] },
    today: { type: String, default: '' },
});

const isEdit = !!props.document;
const page = usePage();
const documentError = computed(() => page.props.errors?.document ?? null);

const priceByProduct = computed(
    () => Object.fromEntries(props.productOptions.map((p) => [p.id, p.selling_price])),
);

const emptyItem = () => ({ product_id: '', quantity: '', unit_price: '' });

const form = useForm({
    customer_id: props.document?.customer_id ?? '',
    warehouse_id: props.document?.warehouse_id ?? '',
    document_date: props.document?.document_date ?? props.today,
    note: props.document?.note ?? '',
    items: props.document
        ? props.document.items.map((i) => ({
            product_id: i.product_id,
            quantity: i.quantity,
            unit_price: i.unit_price,
        }))
        : [emptyItem()],
});

const inputClass = `block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm
    transition focus:outline-none focus:ring-2 focus:ring-indigo-500`;

// Giá mặc định = Product.selling_price; người dùng có thể chỉnh. Server vẫn snapshot nếu để trống.
function onProductChange(item) {
    const price = priceByProduct.value[item.product_id];
    if (price !== undefined && price !== null) item.unit_price = price;
}

function addItem() {
    form.items.push(emptyItem());
}

function removeItem(index) {
    if (form.items.length > 1) form.items.splice(index, 1);
}

const lineTotal = (item) => (Number(item.quantity) || 0) * (Number(item.unit_price) || 0);
const total = computed(() => form.items.reduce((sum, i) => sum + lineTotal(i), 0));
const money = (v) => Number(v ?? 0).toLocaleString('vi-VN');

function submit() {
    if (isEdit) form.put(`/admin/sales/${props.document.id}`);
    else form.post('/admin/sales');
}
</script>

<template>
    <Head :title="pageTitle" />

    <div class="max-w-5xl space-y-4">
        <div class="flex items-center justify-between">
            <h1 class="text-lg font-semibold text-slate-800">{{ pageTitle }}</h1>
            <Link href="/admin/sales" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">← Quay lại</Link>
        </div>

        <p v-if="documentError" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ documentError }}
        </p>

        <form @submit.prevent="submit" class="space-y-5">
            <div class="grid grid-cols-1 gap-4 rounded-xl border border-slate-200 bg-white p-6 sm:grid-cols-2">
                <InputField label="Khách hàng" id="customer_id" :error="form.errors.customer_id">
                    <select id="customer_id" v-model="form.customer_id" :class="[inputClass, 'mt-1']">
                        <option value="">Khách vãng lai</option>
                        <option v-for="c in customerOptions" :key="c.id" :value="c.id">{{ c.customer_code }} - {{ c.name }}</option>
                    </select>
                </InputField>

                <InputField label="Kho xuất" id="warehouse_id" :error="form.errors.warehouse_id" required>
                    <select id="warehouse_id" v-model="form.warehouse_id" :class="[inputClass, 'mt-1']">
                        <option value="" disabled>-- Chọn kho --</option>
                        <option v-for="wh in warehouseOptions" :key="wh.id" :value="wh.id">{{ wh.code }} - {{ wh.name }}</option>
                    </select>
                </InputField>

                <InputField label="Ngày chứng từ" id="document_date" :error="form.errors.document_date" required>
                    <input id="document_date" v-model="form.document_date" type="date" :class="[inputClass, 'mt-1']" />
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
                    <div v-for="(item, index) in form.items" :key="index" class="grid grid-cols-12 items-start gap-2">
                        <div class="col-span-12 sm:col-span-4">
                            <select v-model="item.product_id" :class="inputClass" @change="onProductChange(item)">
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

                        <div class="col-span-4 px-1 py-2 text-right text-sm text-slate-600 sm:col-span-3">
                            {{ money(lineTotal(item)) }}
                        </div>

                        <div class="col-span-12 sm:col-span-1">
                            <button
                                type="button"
                                @click="removeItem(index)"
                                :disabled="form.items.length === 1"
                                class="rounded-lg px-2 py-2 text-sm text-red-600 hover:bg-red-50 disabled:opacity-30"
                            >Xoá</button>
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex justify-end border-t border-slate-100 pt-3 text-sm">
                    <span class="mr-3 text-slate-500">Tổng tiền:</span>
                    <span class="font-semibold text-slate-800">{{ money(total) }}</span>
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
                    <span v-else>{{ isEdit ? 'Cập nhật nháp' : 'Lưu chứng từ nháp' }}</span>
                </button>
                <Link
                    href="/admin/sales"
                    class="rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                >Huỷ</Link>
            </div>
        </form>
    </div>
</template>