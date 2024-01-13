import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy/dist/vue.m';

const appName = import.meta.env.VITE_APP_NAME || 'Defrag Racing';

createInertiaApp({
    title: (title) => `${title} - Defrag Racing`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue);

        app.config.globalProperties.q3tohtml = (name) => {
            let colored_name = '';
            let color = 'white';

            for (let i = 0; i < name.length; i++) {
                if (name[i] == '^') {
                    if (name[i + 1] == '^') {
                        colored_name += '^';
                        i++;
                    } else {
                        color = name[i + 1];
                        i++;
                    }
                } else {
                    colored_name += `<span class="q3c-${color}">${name[i]}</span>`;
                }
            }
    
            return colored_name;
        }

        app.mount(el);

        return app;
    },
    progress: {
        color: '#2d85ff',
    },
});
