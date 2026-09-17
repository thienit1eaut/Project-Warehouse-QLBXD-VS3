<script setup>
import { ref, watch } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import InputField from '@/Components/InputField.vue'

const props = defineProps({
  stocks: Object,
  filters: Object,
  warehouseOptions: Array,
})

const search = ref(props.filters?.search ?? '')
const warehouseId = ref(props.filters?.warehouse_id ?? '')

function applyFilters() {
  router.get(route('admin.stock.index'), {
    search: search.value,
    warehouse_id: warehouseId.value,
  }, {
    preserveState: true,
    replace: true,
  })
}

let debounceTimer = null
watch(search, () => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(applyFilters, 400)
})

watch(warehouseId, () => {
  applyFilters()
})
</script>

<template>
  <AdminLayout title="Tồn kho">
    <h1 class="text-xl font-semibold mb-4">Tồn kho</h1>

    <div class="mb-4 flex flex-wrap gap-3 items-end">
      <div class="max-w-sm w-full sm:w-64">
        <InputField v-model="search" placeholder="Tìm theo tên hoặc SKU sản phẩm..." />
      </div>

      <div>
        <select
          v-model="warehouseId"
          class="rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500 text-sm"
        >
          <option value="">Tất cả kho</option>
          <option v-for="wh in warehouseOptions" :key="wh.id" :value="wh.id">
            {{ wh.name }}
          </option>
        </select>
      </div>
    </div>

    <div class="bg-white rounded-lg shadow overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kho</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">SKU</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sản phẩm</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Đơn vị</th>
            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Tồn hiện tại</th>
            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Thao tác</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="stock in stocks.data" :key="stock.id">
            <td class="px-4 py-3">{{ stock.warehouse.name }}</td>
            <td class="px-4 py-3 font-mono text-sm">{{ stock.product.sku }}</td>
            <td class="px-4 py-3">{{ stock.product.name }}</td>
            <td class="px-4 py-3 text-sm text-gray-500">{{ stock.product.unit?.name ?? '—' }}</td>
            <td class="px-4 py-3 text-right">{{ stock.quantity_on_hand }}</td>
            <td class="px-4 py-3 text-right">
              <Link :href="route('admin.stock.show', stock.id)" class="text-blue-600 hover:underline">
                Xem
              </Link>
            </td>
          </tr>
          <tr v-if="stocks.data.length === 0">
            <td colspan="6" class="px-4 py-6 text-center text-gray-400">Không có dữ liệu tồn kho.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="mt-4 flex justify-center gap-1">
      <template v-for="link in stocks.links" :key="link.label">
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
