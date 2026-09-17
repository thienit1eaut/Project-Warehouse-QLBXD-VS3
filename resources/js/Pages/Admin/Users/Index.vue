<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Badge from '@/Components/UI/Badge.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    pageTitle: { type: String, default: '' },
    users:     { type: Object, required: true },
    filters:   { type: Object, default: () => ({ search: '', role: '' }) },
});

const search = ref(props.filters.search ?? '');
const role   = ref(props.filters.role   ?? '');

let debounceTimer;
watch([search, role], () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        router.get('/admin/users',
            { search: search.value, role: role.value },
            { preserveState: true, replace: true }
        );
    }, 400);
});

function deleteUser(user) {
    if (!confirm(`Xoá tài khoản "${user.name}"? Hành động này không thể hoàn tác.`)) return;
    router.delete(`/admin/users/${user.id}`, { preserveScroll: true });
}

const roleBadge = {
    admin:   { label: 'Quản trị viên', variant: 'purple' },
    manager: { label: 'Quản lý',       variant: 'blue'   },
    staff:   { label: 'Nhân viên',     variant: 'gray'   },
};
</script>

<template>
    <Head :title="pageTitle" />

    <div class="space-y-4">

        <!-- Toolbar -->
        <div class="flex flex-col sm:flex-row gap-3 justify-between">
            <div class="flex gap-2">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Tìm tên, email..."
                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm w-60
                           focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
                <select
                    v-model="role"
                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm
                           focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
                    <option value="">Tất cả vai trò</option>
                    <option value="admin">Quản trị viên</option>
                    <option value="manager">Quản lý</option>
                    <option value="staff">Nhân viên</option>
                </select>
            </div>
            <Link
                href="/admin/users/create"
                class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700
                       text-white text-sm font-medium rounded-lg transition"
            >
                + Thêm người dùng
            </Link>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50 text-left">
                            <th class="px-4 py-3 font-medium text-gray-600">Người dùng</th>
                            <th class="px-4 py-3 font-medium text-gray-600">Vai trò</th>
                            <th class="px-4 py-3 font-medium text-gray-600">Trạng thái</th>
                            <th class="px-4 py-3 font-medium text-gray-600">Ngày tạo</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-if="users.data.length === 0">
                            <td colspan="5" class="px-4 py-10 text-center text-gray-400">
                                Không tìm thấy người dùng nào.
                            </td>
                        </tr>
                        <tr
                            v-for="u in users.data"
                            :key="u.id"
                            class="hover:bg-gray-50 transition-colors"
                        >
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-600
                                                flex items-center justify-center font-semibold text-sm shrink-0">
                                        {{ u.name?.charAt(0)?.toUpperCase() }}
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-800">{{ u.name }}</p>
                                        <p class="text-xs text-gray-400">{{ u.email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <Badge :variant="roleBadge[u.role]?.variant ?? 'gray'">
                                    {{ roleBadge[u.role]?.label ?? u.role }}
                                </Badge>
                            </td>
                            <td class="px-4 py-3">
                                <Badge :variant="u.is_active ? 'green' : 'red'">
                                    {{ u.is_active ? 'Hoạt động' : 'Đã khoá' }}
                                </Badge>
                            </td>
                            <td class="px-4 py-3 text-gray-500">
                                {{ new Date(u.created_at).toLocaleDateString('vi-VN') }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <Link
                                        :href="`/admin/users/${u.id}/edit`"
                                        class="text-indigo-600 hover:text-indigo-800 font-medium"
                                    >
                                        Sửa
                                    </Link>
                                    <button
                                        class="text-red-500 hover:text-red-700 font-medium"
                                        @click="deleteUser(u)"
                                    >
                                        Xoá
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div
                v-if="users.last_page > 1"
                class="px-4 py-3 border-t border-gray-100 flex items-center justify-between text-sm"
            >
                <span class="text-gray-500">
                    Hiển thị {{ users.from }}–{{ users.to }} / {{ users.total }} người dùng
                </span>
                <div class="flex gap-1">
                    <Link
                        v-for="link in users.links"
                        :key="link.label"
                        :href="link.url ?? '#'"
                        :class="[
                            'px-3 py-1 rounded-lg border text-xs transition',
                            link.active
                                ? 'bg-indigo-600 text-white border-indigo-600'
                                : 'border-gray-200 text-gray-600 hover:border-indigo-300',
                            !link.url && 'opacity-40 pointer-events-none',
                        ]"
                        v-html="link.label"
                        preserve-scroll
                    />
                </div>
            </div>
        </div>

    </div>
</template>
