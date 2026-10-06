<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputField from '@/Components/UI/InputField.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    pageTitle: { type: String, default: '' },
    transfer: { type: Object, default: null },
    warehouseOptions: { type: Array, default: () => [] },
    productOptions: { type: Array, default: () => [] },
    today: { type: String, default: '' },
});

const isEdit = !!props.transfer;
const page = usePage();
const documentError = computed(() => page.props.errors?.document ?? null);

const emptyItem = () => ({ product_id: '', quantity: '' });

const form = useForm({
    from_warehouse_id: props.transfer?.from_warehouse_id ?? '',
    to_warehouse_id: props.transfer?.to_warehouse_id ?? '',
    transfer_date: props.transfer?.transfer_date ?? props.today,
    note: props.transfer?.note ?? '',
    items: props.transfer
        ? props.transfer.items.map((i) => ({ product_id: i.product_id, quantity: i.quantity }))
        : [emptyItem()],
});

// Kho đích không được trùng kho nguồn (server vẫn kiểm tra lại).
const destinationOptions = computed(
    () => props.warehouseOptions.filter((w) => w.id !== form.from_warehouse_id),
);

const inputClass = `block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm
    transition focus:outline-none focus:ring-2 focus:ring-indigo-500`;

function onFromChange() {
    if (form.to_warehouse_id === form.from_warehouse_id) form.to_warehouse_id = '';
}

function addItem() {
    form.items.push(emptyItem());
}

function removeItem(index) {
    if (form.items.length > 1) form.items.splice(index, 1);
}

function submit() {
    if (isEdit) form.put(`/admin/stock-transfer/${props.transfer.id}`);
    else form.post('/admin/stock-transfer');
}
</script>

<template>
    <Head :title="pageTitle" />

    <div class="max-w-4xl space-y-4">
        <div class="flex items-center justify-between">
            <h1 class="text-lg font-semibold text-slate-800">{{ pageTitle }}</h1>
            <Link href="/admin/stock-transfer" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">← Quay lại</Link>
        </div>

        <p v-if="documentError" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ documentError }}
        </p>

        <form @submit.prevent="submit" class="space-y-5">
            <div class="grid grid-cols-1 gap-4 rounded-xl border border-slate-200 bg-white p-6 sm:grid-cols-2">
                <InputField label="Kho nguồn" id="from_warehouse_id" :error="form.errors.from_warehouse_id" required>
                    <select id="from_warehouse_id" v-model="form.from_warehouse_id" :class="[inputClass, 'mt-1']" @change="onFromChange">
                        <option value="" disabled>-- Chọn kho nguồn --</option>
                        <option v-for="wh in warehouseOptions" :key="wh.id" :value="wh.id">{{ wh.code }} - {{ wh.name }}</option>
                    </select>
                </InputField>

                <InputField label="Kho đích" id="to_warehouse_id" :error="form.errors.to_warehouse_id" required>
                    <select id="to_warehouse_id" v-model="form.to_warehouse_id" :class="[inputClass, 'mt-1']">
                        <option value="" disabled>-- Chọn kho đích --</option>
                        <option v-for="wh in destinationOptions" :key="wh.id" :value="wh.id">{{ wh.code }} - {{ wh.name }}</option>
                    </select>
                </InputField>

                <InputField label="Ngày chuyển" id="transfer_date" :error="form.errors.transfer_date" required>
                    <input id="transfer_date" v-model="form.transfer_date" type="date" :class="[inputClass, 'mt-1']" />
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
                            <input v-model="item.quantity" type="number" step="0.001" min="0.001" placeholder="Số lượng" :class="inputClass" />
                            <p v-if="form.errors[`items.${index}.quantity`]" class="mt-1 text-xs text-red-600">
                                {{ form.errors[`items.${index}.quantity`] }}
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
                    <span v-else>{{ isEdit ? 'Cập nhật nháp' : 'Lưu chứng từ nháp' }}</span>
                </button>
                <Link
                    href="/admin/stock-transfer"
                    class="rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                >Huỷ</Link>
            </div>
        </form>
    </div>
</template>