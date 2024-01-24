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
                '100': '600px'
            },
            maxWidth: {
                '8xl': '90rem',
            },
            colors: {
                grayop: {
                    '100': '#1B232F',
                    '200': '#37415130',
                    '300': '#37415160',
                    '400': '#37415190',
                    '500': '#374151B0',
                    '600': '#374151F0',
                    '700': '#37415150',
                    '800': '#1f293780',
                    '900': '#11182760',
                    '1000': '#111827'
                },
                blackop: {
                    '20': '#00000020',
                    '30': '#00000030',
                    '50': '#00000050',
                    '80': '#00000080'
                }
            }
        },
    },

    plugins: [
        forms,
        typography,
        function ({ addUtilities }) {
            const newUtilities = {
                '.bg-fit': {
                    'background-repeat': 'no-repeat',
                    'background-size': '100% 100%',
                },
            };
            addUtilities(newUtilities, ['responsive', 'hover']);
        }
    ],
};
