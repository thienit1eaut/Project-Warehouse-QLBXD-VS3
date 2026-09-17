// resources/js/Composables/useAdminMenu.js
//
// Nguồn dữ liệu DUY NHẤT cho toàn bộ menu sidebar.
// Sidebar.vue, SidebarMenu.vue và Breadcrumb.vue đều đọc từ đây,
// nên thêm/sửa/xoá menu chỉ cần sửa 1 chỗ.
//
// icon: object { viewBox, path } — path là "d" attribute của SVG (Heroicons outline style),
// không phụ thuộc thư viện icon ngoài nên không phải cài thêm package.
//
// permission (Phase 7): field tuỳ chọn — nếu có, SidebarMenu.vue chỉ hiển thị
// item khi user có permission đó (usePermission().can()). Item không khai
// báo permission luôn hiển thị (giữ nguyên hành vi cũ cho toàn bộ menu khác).

const icon = (path, viewBox = '0 0 24 24') => ({ viewBox, path });

export const adminMenu = [
    {
        group: null, // nhóm menu chính, không cần tiêu đề nhóm
        items: [
            {
                label: 'Dashboard',
                href: '/admin/dashboard',
                exact: true,
                icon: icon('M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'),
            },
            {
                label: 'Sản phẩm',
                href: '/admin/products',
                exact: false,
                icon: icon('M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z'),
            },
            {
                label: 'Danh mục',
                href: '/admin/categories',
                exact: false,
                icon: icon('M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3zM6 6h.008v.008H6V6z'),
            },
            {
                label: 'Thương hiệu',
                href: '/admin/brands',
                exact: false,
                icon: icon('M16.5 18.75h-1.5a2.25 2.25 0 01-2.25-2.25V15a3 3 0 00-3-3H9a3 3 0 00-3 3v1.5a2.25 2.25 0 01-2.25 2.25H2.25M3.75 6h16.5M4.5 6h15M5.25 6a2.25 2.25 0 004.5 0m5.5 0a2.25 2.25 0 004.5 0M9 6v.75A2.25 2.25 0 006.75 9H4.5m10.5-3v.75A2.25 2.25 0 0017.25 9H19.5'),
            },
            {
                label: 'Đơn vị tính',
                href: '/admin/units',
                exact: false,
                icon: icon('M9 7.5h6m-6 3h6m-6 3h3m-9-9h16.5c.621 0 1.125.504 1.125 1.125v13.5c0 .621-.504 1.125-1.125 1.125H4.125A1.125 1.125 0 013 19.125V5.625C3 5.004 3.504 4.5 4.5 4.5z'),
            },
            {
                label: 'Media',
                href: '/admin/media',
                exact: false,
                permission: 'media.view',
                icon: icon('M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M18 3.75h.008v.008H18V3.75zM6 21h12a2.25 2.25 0 002.25-2.25V5.25A2.25 2.25 0 0018 3H6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 006 21z'),
            },
            {
                label: 'Kho hàng',
                href: '/admin/inventory',
                exact: false,
                icon: icon('M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21'),
            },
            {
                label: 'Nhập kho',
                href: '/admin/stock-in',
                exact: false,
                icon: icon('M9 8.25H7.5a2.25 2.25 0 00-2.25 2.25v9a2.25 2.25 0 002.25 2.25h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25H15m-6 3.75l3 3m0 0l3-3m-3 3V1.5'),
            },
            {
                label: 'Xuất kho',
                href: '/admin/stock-out',
                exact: false,
                icon: icon('M9 8.25H7.5a2.25 2.25 0 00-2.25 2.25v9a2.25 2.25 0 002.25 2.25h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25H15M12 12.75V1.5m0 0l-3 3m3-3l3 3'),
            },
            {
                label: 'Chuyển kho',
                href: '/admin/stock-transfer',
                exact: false,
                icon: icon('M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5'),
            },
            {
                label: 'Kiểm kê',
                href: '/admin/stock-take',
                exact: false,
                icon: icon('M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z'),
            },
        ],
    },
    {
        group: 'Đối tác',
        items: [
            {
                label: 'Nhà cung cấp',
                href: '/admin/suppliers',
                exact: false,
                icon: icon('M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12'),
            },
            {
                label: 'Khách hàng',
                href: '/admin/customers',
                exact: false,
                icon: icon('M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z'),
            },
        ],
    },
    {
        group: 'Phân tích',
        items: [
            {
                label: 'Báo cáo',
                href: '/admin/reports',
                exact: false,
                icon: icon('M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z'),
            },
        ],
    },
];

// Menu riêng ở cuối sidebar (Cài đặt) — tách nhóm để style khác (có border-top)
export const adminMenuFooter = [
    {
        label: 'Cài đặt',
        href: '/admin/settings',
        exact: false,
        icon: icon('M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z M15 12a3 3 0 11-6 0 3 3 0 016 0z'),
    },
];

/**
 * Kiểm tra menu item có đang active không, dựa vào Inertia page.url hiện tại.
 * exact: true  -> chỉ active khi trùng chính xác (vd: Dashboard)
 * exact: false -> active khi url hiện tại bắt đầu bằng href (vd: /admin/products/5/edit vẫn active "Sản phẩm")
 */
export function isMenuItemActive(item, currentUrl) {
    return item.exact
        ? currentUrl === item.href
        : currentUrl.startsWith(item.href);
}

/** Tìm menu item khớp với url hiện tại — dùng cho Breadcrumb tự động */
export function findActiveMenuItem(currentUrl) {
    const all = [...adminMenu.flatMap((g) => g.items), ...adminMenuFooter];
    // ưu tiên href dài nhất khớp trước, tránh "/admin/products" match nhầm "/admin/products/categories"
    return all
        .filter((item) => isMenuItemActive(item, currentUrl))
        .sort((a, b) => b.href.length - a.href.length)[0];
}