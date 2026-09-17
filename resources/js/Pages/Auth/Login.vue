<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputField from '@/Components/UI/InputField.vue';

// useForm của Inertia: tự xử lý loading state, lỗi validate, reset sau submit
const form = useForm({
    email:    '',
    password: '',
    remember: false,
});

function submit() {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Đăng nhập" />

    <div class="min-h-screen flex items-center justify-center bg-gray-50 px-4">
        <div class="w-full max-w-md">

            <!-- Logo / Brand -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-14 h-14
                            bg-indigo-600 rounded-2xl text-3xl mb-4">
                    🚲
                </div>
                <h1 class="text-2xl font-bold text-gray-900">Warehouse System</h1>
                <p class="mt-1 text-sm text-gray-500">Đăng nhập để tiếp tục</p>
            </div>

            <!-- Card -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
                <form @submit.prevent="submit" class="space-y-5">

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
                            autocomplete="current-password"
                            placeholder="••••••••"
                            class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5
                                   text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500
                                   focus:border-transparent transition"
                            :class="{ 'border-red-400': form.errors.password }"
                        />
                    </InputField>

                    <!-- Remember me -->
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input
                            v-model="form.remember"
                            type="checkbox"
                            class="w-4 h-4 rounded border-gray-300 text-indigo-600
                                   focus:ring-indigo-500"
                        />
                        <span class="text-sm text-gray-600">Ghi nhớ đăng nhập</span>
                    </label>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full py-2.5 px-4 bg-indigo-600 hover:bg-indigo-700
                               text-white text-sm font-medium rounded-lg transition
                               disabled:opacity-60 disabled:cursor-not-allowed"
                    >
                        <span v-if="form.processing">Đang xử lý...</span>
                        <span v-else>Đăng nhập</span>
                    </button>

                </form>
            </div>

            <!-- Link đăng ký -->
            <p class="mt-6 text-center text-sm text-gray-500">
                Chưa có tài khoản?
                <Link href="/register" class="text-indigo-600 hover:text-indigo-700 font-medium">
                    Đăng ký ngay
                </Link>
            </p>

        </div>
    </div>
</template>
