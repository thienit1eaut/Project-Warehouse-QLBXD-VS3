<script setup>
import { ref, watch } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import InputField from '@/Components/InputField.vue'
import Badge from '@/Components/Badge.vue'
import FlashMessage from '@/Components/FlashMessage.vue'

const props = defineProps({
  warehouses: Object,
  filters: Object,
})

const search = ref(props.filters?.search ?? '')

let debounceTimer = null
watch(search, (value) => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    router.get(route('admin.warehouses.index'), { search: value }, {
      preserveState: true,
      replace: true,
    })
  }, 400)
})

function destroyWarehouse(warehouse) {
  if (!confirm(`Xoá kho "${warehouse.name}"? Hành động này không thể hoàn tác.`)) {
    return
  }

  router.delete(route('admin.warehouses.destroy', warehouse.id))
}
</script>

<template>
  <AdminLayout title="Kho hàng">
    <FlashMessage />

    <div class="flex items-center justify-between mb-4">
      <h1 class="text-xl font-semibold">Danh sách kho hàng</h1>
      <Link
        :href="route('admin.warehouses.create')"
        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
      >
        + Thêm kho hàng
      </Link>
    </div>

    <div class="mb-4 max-w-sm">
      <InputField v-model="search" placeholder="Tìm theo mã hoặc tên kho..." />
    </div>

    <div class="bg-white rounded-lg shadow overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Mã kho</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tên kho</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Địa chỉ</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Số SP đang tồn</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Trạng thái</th>
            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Thao tác</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="warehouse in warehouses.data" :key="warehouse.id">
            <td class="px-4 py-3 font-mono text-sm">{{ warehouse.code }}</td>
            <td class="px-4 py-3">{{ warehouse.name }}</td>
            <td class="px-4 py-3 text-sm text-gray-500">{{ warehouse.address ?? '—' }}</td>
            <td class="px-4 py-3">{{ warehouse.stocks_count ?? 0 }}</td>
            <td class="px-4 py-3">
              <Badge :variant="warehouse.is_active ? 'success' : 'default'">
                {{ warehouse.is_active ? 'Hoạt động' : 'Ngừng hoạt động' }}
              </Badge>
            </td>
            <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
              <Link :href="route('admin.warehouses.show', warehouse.id)" class="text-blue-600 hover:underline">Xem</Link>
              <Link :href="route('admin.warehouses.edit', warehouse.id)" class="text-amber-600 hover:underline">Sửa</Link>
              <button type="button" class="text-red-600 hover:underline" @click="destroyWarehouse(warehouse)">
                Xoá
              </button>
            </td>
          </tr>
          <tr v-if="warehouses.data.length === 0">
            <td colspan="6" class="px-4 py-6 text-center text-gray-400">Chưa có kho hàng nào.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="mt-4 flex justify-center gap-1">
      <template v-for="link in warehouses.links" :key="link.label">
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
