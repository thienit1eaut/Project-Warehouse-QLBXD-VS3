<script setup>
import { useForm, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import InputField from '@/Components/InputField.vue'
import FlashMessage from '@/Components/FlashMessage.vue'

const props = defineProps({
  warehouse: {
    type: Object,
    default: null,
  },
})

const isEdit = !!props.warehouse

const form = useForm({
  code: props.warehouse?.code ?? '',
  name: props.warehouse?.name ?? '',
  address: props.warehouse?.address ?? '',
  description: props.warehouse?.description ?? '',
  is_active: props.warehouse?.is_active ?? true,
})

function submit() {
  if (isEdit) {
    form.put(route('admin.warehouses.update', props.warehouse.id))
  } else {
    form.post(route('admin.warehouses.store'))
  }
}
</script>

<template>
  <AdminLayout :title="isEdit ? 'Sửa kho hàng' : 'Thêm kho hàng'">
    <FlashMessage />

    <h1 class="text-xl font-semibold mb-4">
      {{ isEdit ? `Sửa kho hàng: ${warehouse.name}` : 'Thêm kho hàng mới' }}
    </h1>

    <form @submit.prevent="submit" class="bg-white rounded-lg shadow p-6 max-w-xl space-y-4">
      <div>
        <label class="block text-sm font-medium mb-1">Mã kho <span class="text-red-500">*</span></label>
        <InputField v-model="form.code" :error="form.errors.code" placeholder="VD: WH-01" />
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Tên kho <span class="text-red-500">*</span></label>
        <InputField v-model="form.name" :error="form.errors.name" placeholder="VD: Kho chính" />
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Địa chỉ</label>
        <textarea
          v-model="form.address"
          rows="2"
          class="w-full rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500"
        ></textarea>
        <p v-if="form.errors.address" class="text-sm text-red-500 mt-1">{{ form.errors.address }}</p>
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Mô tả</label>
        <textarea
          v-model="form.description"
          rows="3"
          class="w-full rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500"
        ></textarea>
        <p v-if="form.errors.description" class="text-sm text-red-500 mt-1">{{ form.errors.description }}</p>
      </div>

      <div class="flex items-center gap-2">
        <input id="is_active" type="checkbox" v-model="form.is_active" class="rounded border-gray-300" />
        <label for="is_active" class="text-sm font-medium">Đang hoạt động</label>
      </div>

      <div class="flex gap-3 pt-2">
        <button
          type="submit"
          :disabled="form.processing"
          class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
        >
          {{ isEdit ? 'Lưu thay đổi' : 'Tạo kho hàng' }}
        </button>
        <Link :href="route('admin.warehouses.index')" class="px-4 py-2 rounded-md border hover:bg-gray-50">
          Huỷ
        </Link>
      </div>
    </form>
  </AdminLayout>
</template>
