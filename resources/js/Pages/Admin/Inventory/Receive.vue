<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputField from '@/Components/UI/InputField.vue';

defineOptions({ layout: AdminLayout });

defineProps({
    pageTitle: { type: String, default: '' },
    warehouseOptions: { type: Array, default: () => [] },
    productOptions: { type: Array, default: () => [] },
});

const form = useForm({
    warehouse_id: '',
    product_id: '',
    quantity: '',
    received_at: '',
    expiry_date: '',
    note: '',
});

function submit() {
    // Server (InventoryService::receiveStock) là source of truth - Vue không
    // tự tính Stock/StockLot sau khi nhập, chỉ gửi input rồi chờ redirect.
    form.post('/admin/inventory/receive');
}
</script>

<template>
    <Head :title="pageTitle" />

    <div class="max-w-xl">
        <div class="mb-4 flex items-center justify-between">
            <h1 class="text-lg font-semibold text-slate-800">Nhập kho</h1>
            <Link href="/admin/inventory" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
                ← Quay lại
            </Link>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6">
            <form @submit.prevent="submit" class="space-y-5">
                <InputField label="Kho hàng" id="warehouse_id" :error="form.errors.warehouse_id" required>
                    <select
                        id="warehouse_id"
                        v-model="form.warehouse_id"
                        class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm
                               transition focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        :class="{ 'border-red-400': form.errors.warehouse_id }"
                    >
                        <option value="" disabled>-- Chọn kho --</option>
                        <option v-for="wh in warehouseOptions" :key="wh.id" :value="wh.id">
                            {{ wh.code }} - {{ wh.name }}
                        </option>
                    </select>
                </InputField>

                <InputField label="Sản phẩm" id="product_id" :error="form.errors.product_id" required>
                    <select
                        id="product_id"
                        v-model="form.product_id"
                        class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm
                               transition focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        :class="{ 'border-red-400': form.errors.product_id }"
                    >
                        <option value="" disabled>-- Chọn sản phẩm --</option>
                        <option v-for="p in productOptions" :key="p.id" :value="p.id">
                            {{ p.sku }} - {{ p.name }}
                        </option>
                    </select>
                </InputField>

                <InputField label="Số lượng nhập" id="quantity" :error="form.errors.quantity" required>
                    <input
                        id="quantity"
                        v-model="form.quantity"
                        type="number"
                        step="0.001"
                        min="0.001"
                        placeholder="VD: 10"
                        class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm
                               transition focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        :class="{ 'border-red-400': form.errors.quantity }"
                    />
                </InputField>

                <div class="grid grid-cols-2 gap-4">
                    <InputField label="Ngày nhập" id="received_at" :error="form.errors.received_at">
                        <input
                            id="received_at"
                            v-model="form.received_at"
                            type="datetime-local"
                            class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm
                                   transition focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        />
                        <p class="mt-1 text-xs text-slate-400">Để trống = thời điểm hiện tại.</p>
                    </InputField>

                    <InputField label="Hạn sử dụng" id="expiry_date" :error="form.errors.expiry_date">
                        <input
                            id="expiry_date"
                            v-model="form.expiry_date"
                            type="date"
                            class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm
                                   transition focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        />
                        <p class="mt-1 text-xs text-slate-400">Không bắt buộc.</p>
                    </InputField>
                </div>

                <InputField label="Ghi chú" id="note" :error="form.errors.note">
                    <textarea
                        id="note"
                        v-model="form.note"
                        rows="2"
                        class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm
                               transition focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </InputField>

                <div class="flex items-center gap-3 border-t border-slate-100 pt-4">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-lg bg-green-600 px-5 py-2.5 text-sm font-medium text-white
                               transition hover:bg-green-700 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <span v-if="form.processing">Đang lưu...</span>
                        <span v-else>Nhập kho</span>
                    </button>
                    <Link
                        href="/admin/inventory"
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