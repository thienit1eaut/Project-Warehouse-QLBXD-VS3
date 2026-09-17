<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Badge from '@/Components/UI/Badge.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    pageTitle:     { type: String, default: '' },
    categories:    { type: Object, required: true }, // Laravel paginator
    filters:       { type: Object, default: () => ({ search: '', is_active: '', parent_id: '' }) },
    parentOptions: { type: Array, default: () => [] }, // chỉ danh mục gốc, dùng để lọc
});

const search   = ref(props.filters.search    ?? '');
const isActive = ref(props.filters.is_active ?? '');
const parentId = ref(props.filters.parent_id ?? '');

let debounceTimer;
watch([search, isActive, parentId], () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        router.get('/admin/categories',
            { search: search.value, is_active: isActive.value, parent_id: parentId.value },
            { preserveState: true, replace: true }
        );
    }, 400);
});

function deleteCategory(category) {
    if (!confirm(`Xoá danh mục "${category.name}"? Hành động này không thể hoàn tác.`)) return;
    router.delete(`/admin/categories/${category.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head :title="pageTitle" />

    <div class="space-y-4">

        <!-- Toolbar -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap gap-2">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Tìm theo tên danh mục..."
                    class="w-60 rounded-lg border border-slate-300 px-3 py-2 text-sm
                           focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />

                <select
                    v-model="isActive"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm
                           focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
                    <option value="">Tất cả trạng thái</option>
                    <option value="1">Hoạt động</option>
                    <option value="0">Ngừng hoạt động</option>
                </select>

                <select
                    v-model="parentId"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm
                           focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
                    <option value="">Tất cả danh mục cha</option>
                    <option value="root">Chỉ danh mục gốc</option>
                    <option v-for="p in parentOptions" :key="p.id" :value="p.id">
                        {{ p.name }}
                    </option>
                </select>
            </div>

            <Link
                href="/admin/categories/create"
                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2
                       text-sm font-medium text-white transition hover:bg-indigo-700"
            >
                + Thêm danh mục
            </Link>
        </div>

        <!-- Table -->
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50 text-left">
                            <th class="px-4 py-3 font-medium text-slate-600">Tên danh mục</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Danh mục cha</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Danh mục con</th>
                            <th class="px-4 py-3 font-medium text-slate-600">Trạng thái</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <!-- Empty state -->
                        <tr v-if="categories.data.length === 0">
                            <td colspan="5" class="px-4 py-10 text-center text-slate-400">
                                Không tìm thấy danh mục nào.
                            </td>
                        </tr>

                        <tr
                            v-for="c in categories.data"
                            :key="c.id"
                            class="transition-colors hover:bg-slate-50"
                        >
                            <!-- Tên — indent nếu là danh mục con, thể hiện phân cấp cha/con -->
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2" :class="c.parent_id && 'pl-6'">
                                    <span v-if="c.parent_id" class="text-slate-300">└</span>
                                    <span class="font-medium text-slate-800">{{ c.name }}</span>
                                </div>
                            </td>

                            <td class="px-4 py-3 text-slate-500">
                                {{ c.parent?.name ?? '—' }}
                            </td>

                            <td class="px-4 py-3 text-slate-500">
                                {{ c.children_count }}
                            </td>

                            <td class="px-4 py-3">
                                <Badge :variant="c.is_active ? 'green' : 'gray'">
                                    {{ c.is_active ? 'Hoạt động' : 'Ngừng hoạt động' }}
                                </Badge>
                            </td>

                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <Link
                                        :href="`/admin/categories/${c.id}/edit`"
                                        class="font-medium text-indigo-600 hover:text-indigo-800"
                                    >
                                        Sửa
                                    </Link>
                                    <button
                                        class="font-medium text-red-500 hover:text-red-700"
                                        @click="deleteCategory(c)"
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
                v-if="categories.last_page > 1"
                class="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-sm"
            >
                <span class="text-slate-500">
                    Hiển thị {{ categories.from }}–{{ categories.to }} / {{ categories.total }} danh mục
                </span>
                <div class="flex gap-1">
                    <Link
                        v-for="link in categories.links"
                        :key="link.label"
                        :href="link.url ?? '#'"
                        :class="[
                            'rounded-lg border px-3 py-1 text-xs transition',
                            link.active
                                ? 'border-indigo-600 bg-indigo-600 text-white'
                                : 'border-slate-200 text-slate-600 hover:border-indigo-300',
                            !link.url && 'pointer-events-none opacity-40',
                        ]"
                        v-html="link.label"
                        preserve-scroll
                    />
                </div>
            </div>
        </div>

    </div>
</template>
