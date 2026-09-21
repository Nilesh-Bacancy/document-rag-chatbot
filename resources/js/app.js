import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';

// If the session/CSRF token expired (e.g. the tab was left open for a long
// time), Inertia would otherwise dump the raw Laravel "419 | PAGE EXPIRED"
// page. Reload instead, so the user just lands back on a fresh page with a
// valid token rather than seeing a broken screen.
router.on('invalid', (event) => {
    if (event.detail.response?.status === 419) {
        event.preventDefault();
        window.location.reload();
    }
});

createInertiaApp({
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
        return pages[`./Pages/${name}.vue`];
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
});
