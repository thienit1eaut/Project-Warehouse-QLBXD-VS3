// resources/js/Composables/usePermission.js
//
// Đọc permission của user hiện tại từ shared props Inertia
// (HandleInertiaRequests đã share auth.user.permissions từ Phase 5).
// Không hard-code role slug — mọi quyết định hiển thị UI đều qua permission key.

import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function usePermission() {
    const page = usePage();

    const permissions = computed(() => page.props.auth?.user?.permissions ?? []);

    function can(permission) {
        return permissions.value.includes(permission);
    }

    return { can, permissions };
}