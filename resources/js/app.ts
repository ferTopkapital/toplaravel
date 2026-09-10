import '../css/app.css';

import { createApp, h, type DefineComponent } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import AppLayout from './Layouts/AppLayout.vue';

const appName = import.meta.env.VITE_APP_NAME || 'Top Kapital';

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),

    resolve: (name) => {
        const page = resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./Pages/**/*.vue'),
        );

        // Layout por defecto para todas las paginas. Una pagina puede
        // sobreescribirlo exportando su propio `layout` (p. ej. las de auth,
        // que no llevan sidebar).
        return Promise.resolve(page).then((module) => {
            module.default.layout = module.default.layout ?? AppLayout;
            return module;
        });
    },

    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },

    // Barra de progreso de las navegaciones SPA. El color sale del token de
    // marca; 250ms de retraso evita que parpadee en respuestas rapidas.
    progress: {
        color: '#DF591D',
        delay: 250,
    },
});
