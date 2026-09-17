<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputField from '@/Components/UI/InputField.vue';

const form = useForm({
    name:                  '',
    email:                 '',
    password:              '',
    password_confirmation: '',
});

function submit() {
    form.post('/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head title="Đăng ký" />

    <div class="min-h-screen flex items-center justify-center bg-gray-50 px-4">
        <div class="w-full max-w-md">

            <!-- Logo -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-14 h-14
                            bg-indigo-600 rounded-2xl text-3xl mb-4">
                    🚲
                </div>
                <h1 class="text-2xl font-bold text-gray-900">Tạo tài khoản</h1>
                <p class="mt-1 text-sm text-gray-500">Đăng ký để sử dụng hệ thống</p>
            </div>

            <!-- Card -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
                <form @submit.prevent="submit" class="space-y-5">

                    <InputField label="Họ tên" id="name" :error="form.errors.name" required>
                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            autocomplete="name"
                            placeholder="Nguyễn Văn A"
                            class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5
                                   text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500
                                   focus:border-transparent transition"
                            :class="{ 'border-red-400': form.errors.name }"
                        />
                    </InputField>

                    <InputField label="Email" id="email" :error="form.errors.email" required>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            autocomplete="email"
                            placeholder="you@example.com"
                            class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5
                                   text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500
                                   focus:border-transparent transition"
                            :class="{ 'border-red-400': form.errors.email }"
                        />
                    </InputField>

                    <InputField label="Mật khẩu" id="password" :error="form.errors.password" required>
                        <input
                            id="password"
                            v-model="form.password"
                            type="password"
                            autocomplete="new-password"
                            placeholder="Tối thiểu 8 ký tự, có chữ hoa và số"
                            class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5
                                   text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500
                                   focus:border-transparent transition"
                            :class="{ 'border-red-400': form.errors.password }"
                        />
                    </InputField>

                    <InputField
                        label="Xác nhận mật khẩu"
                        id="password_confirmation"
                        :error="form.errors.password_confirmation"
                        required
                    >
                        <input
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            placeholder="Nhập lại mật khẩu"
                            class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5
                                   text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500
                                   focus:border-transparent transition"
                        />
                    </InputField>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full py-2.5 px-4 bg-indigo-600 hover:bg-indigo-700
                               text-white text-sm font-medium rounded-lg transition
                               disabled:opacity-60 disabled:cursor-not-allowed"
                    >
                        <span v-if="form.processing">Đang xử lý...</span>
                        <span v-else>Đăng ký</span>
                    </button>

                </form>
            </div>

            <p class="mt-6 text-center text-sm text-gray-500">
                Đã có tài khoản?
                <Link href="/login" class="text-indigo-600 hover:text-indigo-700 font-medium">
                    Đăng nhập
                </Link>
            </p>

        </div>
    </div>
</template>
