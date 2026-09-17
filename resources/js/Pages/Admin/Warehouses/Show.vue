<script setup>
import { ref, watch } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import InputField from '@/Components/InputField.vue'
import Badge from '@/Components/Badge.vue'
import FlashMessage from '@/Components/FlashMessage.vue'

const props = defineProps({
  warehouse: Object,
  stocks: Object,
  filters: Object,
})

const search = ref(props.filters?.search ?? '')

let debounceTimer = null
watch(search, (value) => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    router.get(route('admin.warehouses.show', props.warehouse.id), { search: value }, {
      preserveState: true,
      replace: true,
    })
  }, 400)
})
</script>

<template>
  <AdminLayout :title="`Kho: ${warehouse.name}`">
    <FlashMessage />

    <div class="flex items-center justify-between mb-4">
      <h1 class="text-xl font-semibold">{{ warehouse.name }} ({{ warehouse.code }})</h1>
      <Link :href="route('admin.warehouses.index')" class="text-blue-600 hover:underline">
        ← Quay lại danh sách kho
      </Link>
    </div>

    <div class="bg-white rounded-lg shadow p-4 mb-6 grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
      <div>
        <span class="text-gray-500">Địa chỉ:</span>
        <p>{{ warehouse.address ?? '—' }}</p>
      </div>
      <div>
        <span class="text-gray-500">Trạng thái:</span>
        <p>
          <Badge :variant="warehouse.is_active ? 'success' : 'default'">
            {{ warehouse.is_active ? 'Hoạt động' : 'Ngừng hoạt động' }}
          </Badge>
        </p>
      </div>
      <div>
        <span class="text-gray-500">Mô tả:</span>
        <p>{{ warehouse.description ?? '—' }}</p>
      </div>
    </div>

    <h2 class="text-lg font-medium mb-2">Tồn kho hiện tại</h2>

    <div class="mb-4 max-w-sm">
      <InputField v-model="search" placeholder="Tìm theo tên hoặc SKU sản phẩm..." />
    </div>

    <div class="bg-white rounded-lg shadow overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">SKU</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sản phẩm</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Đơn vị</th>
            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Tồn hiện tại</th>
            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Thao tác</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="stock in stocks.data" :key="stock.id">
            <td class="px-4 py-3 font-mono text-sm">{{ stock.product.sku }}</td>
            <td class="px-4 py-3">{{ stock.product.name }}</td>
            <td class="px-4 py-3 text-sm text-gray-500">{{ stock.product.unit?.name ?? '—' }}</td>
            <td class="px-4 py-3 text-right">{{ stock.quantity_on_hand }}</td>
            <td class="px-4 py-3 text-right">
              <Link :href="route('admin.stock.show', stock.id)" class="text-blue-600 hover:underline">
                Xem lịch sử
              </Link>
            </td>
          </tr>
          <tr v-if="stocks.data.length === 0">
            <td colspan="5" class="px-4 py-6 text-center text-gray-400">Kho này chưa có sản phẩm tồn kho.</td>
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
