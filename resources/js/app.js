import './bootstrap';
import '../css/app.css';
import '../css/items.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy/dist/vue.m';
import Popper from "vue3-popper";
import moment from 'moment-timezone';

import MainLayout from "@/Layouts/MainLayout.vue" 

import { createVuetify } from 'vuetify'
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'

const appName = import.meta.env.VITE_APP_NAME || 'Defrag Racing';

const formatTime = (milliseconds) => {
    milliseconds = Math.max(0, milliseconds);
  
    const hours = Math.floor(milliseconds / 3600000);
    milliseconds %= 3600000;
    const minutes = Math.floor(milliseconds / 60000);
    milliseconds %= 60000;
    const seconds = Math.floor(milliseconds / 1000);
    milliseconds %= 1000;
  
    let formattedTime = '';
  
    if (hours > 0) {
      formattedTime += `${hours}:`;
    }
  
    if (minutes > 0 || hours > 0) {
      formattedTime += `${padZero(minutes)}:`;
    }
  
    formattedTime += `${padZero(seconds)}:${milliseconds.toString().padStart(3, '0')}`;
  
    return formattedTime;
};

const padZero = (num) => {
    return num.toString().padStart(2, '0');
};

const q3tohtml = (name) => {
    let colored_name = '';
    let color = 'white';

    for (let i = 0; i < name.length; i++) {
        if (name[i] == '^') {
            if (name[i + 1] == '^') {
                colored_name += '^';
            } else {
                color = name[i + 1];
                i++;
            }
        } else {
            colored_name += `<span class="q3c-${color}">${name[i]}</span>`;
        }
    }

    return colored_name;
};

const timeSince = (date) => {
    const currentDate = moment.tz();
    const inputDate = moment.tz(date, "Europe/Berlin");
    const duration = moment.duration(currentDate.diff(inputDate));

    if (duration.asDays() < 1) {
        if (duration.hours() == 0) {
            return `${duration.minutes()} minutes`;
        }
        
        return `${duration.hours()} hours, ${duration.minutes()} minutes`;
    } else if (duration.asDays() < 365) {
        const months = duration.months();
        const weeks = duration.weeks();
        const days = duration.days() % 7;

        let result = '';

        if (months > 0) {
            result += `${months} ${months === 1 ? 'month' : 'months'}, `;
        }

        if (weeks > 0) {
            result += `${weeks} ${weeks === 1 ? 'week' : 'weeks'}, `;
        }

        if (days > 0) {
            result += `${days} ${days === 1 ? 'day' : 'days'}`;
        }

        return result;
    } else {
        const years = duration.years();
        return `${years} ${years === 1 ? 'year' : 'years'}`;
    }
}

const vuetify = createVuetify({
    components,
    directives,
})

createInertiaApp({
    title: (title) => `${title} - Defrag Racing`,
    resolve: async (name) => {
        const page = await resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue'))

        page.default.layout = page.default.layout || MainLayout
        
        return page
    },
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(vuetify);

        app.component("Popper", Popper);

        app.config.globalProperties.formatTime = formatTime

        app.config.globalProperties.q3tohtml = q3tohtml

        app.config.globalProperties.timeSince = timeSince

        app.mount(el);

        return app;
    },
    progress: {
        color: '#2d85ff'
    },
});
