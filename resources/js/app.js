import './bootstrap';
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
 
createInertiaApp({
    // Tên tab trình duyệt: "Dashboard – Warehouse System"
    title: (title) => `${title} – Warehouse System`,
 
    // Tự động tìm file Vue trong resources/js/Pages/
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
 
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
 
    // Hiện thanh loading khi chuyển trang (built-in của Inertia)
    progress: {
        color: '#4f46e5', // indigo-600 — đổi màu tuỳ ý
    },
});