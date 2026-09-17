<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputField from '@/Components/UI/InputField.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    pageTitle: { type: String, default: '' },
    user:      { type: Object, default: null },
});

const isEditing = computed(() => !!props.user);

const form = useForm({
    name:      props.user?.name      ?? '',
    email:     props.user?.email     ?? '',
    password:  '',
    role:      props.user?.role      ?? 'staff',
    is_active: props.user?.is_active ?? true,
});

function submit() {
    if (isEditing.value) {
        form.put(`/admin/users/${props.user.id}`, {
            onFinish: () => form.reset('password'),
        });
    } else {
        form.post('/admin/users');
    }
}
</script>

<template>
    <Head :title="pageTitle" />

    <div class="max-w-xl">
        <div class="bg-white rounded-xl border border-gray-100 p-6">
            <form @submit.prevent="submit" class="space-y-5">

                <InputField label="Họ tên" id="name" :error="form.errors.name" required>
                    <input
                        id="name"
                        v-model="form.name"
                        type="text"
                        placeholder="Nguyễn Văn A"
                        class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm
                               focus:outline-none focus:ring-2 focus:ring-indigo-500 transition"
                        :class="{ 'border-red-400': form.errors.name }"
                    />
                </InputField>

                <InputField label="Email" id="email" :error="form.errors.email" required>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        placeholder="you@example.com"
                        class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm
                               focus:outline-none focus:ring-2 focus:ring-indigo-500 transition"
                        :class="{ 'border-red-400': form.errors.email }"
                    />
                </InputField>

                <InputField
                    label="Mật khẩu"
                    id="password"
                    :error="form.errors.password"
                    :required="!isEditing"
                >
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        :placeholder="isEditing
                            ? 'Để trống nếu không đổi mật khẩu'
                            : 'Tối thiểu 8 ký tự, có chữ hoa và số'"
                        class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm
                               focus:outline-none focus:ring-2 focus:ring-indigo-500 transition"
                        :class="{ 'border-red-400': form.errors.password }"
                    />
                </InputField>

                <InputField label="Vai trò" id="role" :error="form.errors.role" required>
                    <select
                        id="role"
                        v-model="form.role"
                        class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm
                               focus:outline-none focus:ring-2 focus:ring-indigo-500 transition"
                    >
                        <option value="staff">Nhân viên</option>
                        <option value="manager">Quản lý</option>
                        <option value="admin">Quản trị viên</option>
                    </select>
                </InputField>

                <!-- Toggle is_active -->
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        role="switch"
                        :aria-checked="form.is_active"
                        :class="[
                            'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full',
                            'border-2 border-transparent transition-colors duration-200',
                            form.is_active ? 'bg-indigo-600' : 'bg-gray-200',
                        ]"
                        @click="form.is_active = !form.is_active"
                    >
                        <span
                            :class="[
                                'pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow',
                                'transition duration-200',
                                form.is_active ? 'translate-x-5' : 'translate-x-0',
                            ]"
                        />
                    </button>
                    <span class="text-sm text-gray-700">
                        {{ form.is_active ? 'Tài khoản hoạt động' : 'Tài khoản bị khoá' }}
                    </span>
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm
                               font-medium rounded-lg transition disabled:opacity-60 disabled:cursor-not-allowed"
                    >
                        <span v-if="form.processing">Đang lưu...</span>
                        <span v-else>{{ isEditing ? 'Cập nhật' : 'Tạo người dùng' }}</span>
                    </button>
                    <Link
                        href="/admin/users"
                        class="px-5 py-2.5 border border-gray-300 text-gray-700 text-sm
                               font-medium rounded-lg hover:bg-gray-50 transition"
                    >
                        Huỷ
                    </Link>
                </div>

            </form>
        </div>
    </div>
</template>
