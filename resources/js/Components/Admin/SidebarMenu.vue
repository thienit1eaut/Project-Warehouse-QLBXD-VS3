<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { isMenuItemActive } from '@/Composables/useAdminMenu';
import { usePermission } from '@/Composables/usePermission';

const props = defineProps({
    groups: {
        type: Array, // [{ group: 'Tên nhóm' | null, items: [...] }]
        required: true,
    },
    collapsed: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['navigate']); // để Sidebar mobile tự đóng drawer khi click menu

const page = usePage();
const currentUrl = computed(() => page.url);
const { can } = usePermission();

function active(item) {
    return isMenuItemActive(item, currentUrl.value);
}

/**
 * Ẩn/hiện menu item theo permission (Phase 7 VIII+IX) — chỉ filter khi item
 * có khai báo `item.permission`; item không khai báo permission (toàn bộ
 * menu cũ: Dashboard, Sản phẩm, Danh mục...) luôn hiển thị như trước,
 * không đổi hành vi hiện có.
 */
function visibleItems(items) {
    return items.filter((item) => !item.permission || can(item.permission));
}
</script>

<template>
    <nav class="space-y-6">
        <div v-for="(group, idx) in groups" :key="idx">
            <!-- Tiêu đề nhóm — ẩn khi collapsed để tránh chữ bị vỡ layout -->
            <p
                v-if="group.group && !collapsed"
                class="px-3 mb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-400"
            >
                {{ group.group }}
            </p>

            <div class="space-y-0.5">
                <Link
                    v-for="item in visibleItems(group.items)"
                    :key="item.href"
                    :href="item.href"
                    :title="collapsed ? item.label : undefined"
                    class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition-colors"
                    :class="[
                        active(item)
                            ? 'bg-indigo-50 text-indigo-700 font-medium'
                            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900',
                        collapsed && 'justify-center px-2',
                    ]"
                    @click="emit('navigate')"
                >
                    <!-- Icon SVG generic, dùng path từ config -->
                    <svg
                        class="h-5 w-5 shrink-0"
                        :class="active(item) ? 'text-indigo-600' : 'text-slate-400 group-hover:text-slate-600'"
                        :viewBox="item.icon.viewBox"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon.path" />
                    </svg>

                    <span v-if="!collapsed" class="truncate">{{ item.label }}</span>

                    <!-- Thanh active bên phải, chỉ hiện khi không collapse -->
                    <span
                        v-if="active(item) && !collapsed"
                        class="ml-auto h-1.5 w-1.5 rounded-full bg-indigo-600"
                    />
                </Link>
            </div>
        </div>
    </nav>
</template>