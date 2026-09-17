<script setup>
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Badge from '@/Components/Badge.vue'

const props = defineProps({
  stock: Object,
  movements: Object,
})

const movementBadgeVariant = {
  in: 'success',
  out: 'danger',
  adjustment: 'warning',
}

const movementLabel = {
  in: 'Nhập kho',
  out: 'Xuất kho',
  adjustment: 'Điều chỉnh',
}
</script>

<template>
  <AdminLayout title="Chi tiết tồn kho">
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-xl font-semibold">
        {{ stock.product.name }} — {{ stock.warehouse.name }}
      </h1>
      <Link :href="route('admin.stock.index')" class="text-blue-600 hover:underline">
        ← Quay lại danh sách tồn kho
      </Link>
    </div>

    <div class="bg-white rounded-lg shadow p-4 mb-6 grid grid-cols-1 sm:grid-cols-4 gap-4 text-sm">
      <div>
        <span class="text-gray-500">Kho:</span>
        <p>{{ stock.warehouse.name }} ({{ stock.warehouse.code }})</p>
      </div>
      <div>
        <span class="text-gray-500">SKU:</span>
        <p class="font-mono">{{ stock.product.sku }}</p>
      </div>
      <div>
        <span class="text-gray-500">Đơn vị:</span>
        <p>{{ stock.product.unit?.name ?? '—' }}</p>
      </div>
      <div>
        <span class="text-gray-500">Tồn hiện tại:</span>
        <p class="text-lg font-semibold">{{ stock.quantity_on_hand }}</p>
      </div>
    </div>

    <h2 class="text-lg font-medium mb-2">Lịch sử giao dịch</h2>
    <p class="text-sm text-gray-400 mb-3">
      Đây là sổ giao dịch (ledger) bất biến, không thể sửa hoặc xoá.
    </p>

    <div class="bg-white rounded-lg shadow overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ngày</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Loại</th>
            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Số lượng</th>
            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Trước</th>
            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Sau</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Người thực hiện</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tham chiếu</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ghi chú</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="movement in movements.data" :key="movement.id">
            <td class="px-4 py-3 text-sm whitespace-nowrap">{{ movement.created_at }}</td>
            <td class="px-4 py-3">
              <Badge :variant="movementBadgeVariant[movement.movement_type] ?? 'default'">
                {{ movementLabel[movement.movement_type] ?? movement.movement_type }}
              </Badge>
            </td>
            <td
              class="px-4 py-3 text-right font-medium"
              :class="movement.quantity < 0 ? 'text-red-600' : 'text-green-700'"
            >
              {{ movement.quantity > 0 ? '+' : '' }}{{ movement.quantity }}
            </td>
            <td class="px-4 py-3 text-right text-sm text-gray-500">{{ movement.quantity_before }}</td>
            <td class="px-4 py-3 text-right text-sm text-gray-500">{{ movement.quantity_after }}</td>
            <td class="px-4 py-3 text-sm">{{ movement.user?.name ?? 'Hệ thống' }}</td>
            <td class="px-4 py-3 text-sm text-gray-500">
              <span v-if="movement.reference_type">
                {{ movement.reference_type }} #{{ movement.reference_id }}
              </span>
              <span v-else>—</span>
            </td>
            <td class="px-4 py-3 text-sm text-gray-500">{{ movement.note ?? '—' }}</td>
          </tr>
          <tr v-if="movements.data.length === 0">
            <td colspan="8" class="px-4 py-6 text-center text-gray-400">Chưa có giao dịch nào.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="mt-4 flex justify-center gap-1">
      <template v-for="link in movements.links" :key="link.label">
        <Link
          v-if="link.url"
          :href="link.url"
          v-html="link.label"
          class="px-3 py-1 rounded"
          :class="link.active ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-100'"
        />
        <span v-else v-html="link.label" class="px-3 py-1 text-gray-300" />
      </template>
    </div>
  </AdminLayout>
</template>
