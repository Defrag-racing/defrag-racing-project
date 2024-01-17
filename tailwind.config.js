import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            screens: {
                'hmd': '500px',
                'xxs': '370px'
            },
            width: {
                '100': '600px',
            },
            maxWidth: {
                '8xl': '90rem',
            },
            colors: {
                gray: {
                    '50': '#f9fafb',
                    '100': '#f4f5f7',
                    '200': '#e5e7eb',
                    '300': '#d2d6dc',
                    '400': '#9fa6b2',
                    '500': '#6b7280',
                    '600': '#4b5563',
                    '700': '#37415150',
                    '800': '#1f293780',
                    '900': '#11182760',
                    '1000': '#111827',
                },
                blackop: {
                    '20': '#00000020',
                    '30': '#00000030',
                    '50': '#00000050',
                    '80': '#00000080'
                },
                grayop: {
                    '30': '#28282890'
                }
            }
        },
    },

    plugins: [forms, typography],
};
