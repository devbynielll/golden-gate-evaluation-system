import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.jsx',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Brand green — anchored on the official #08890c at the
                // 700 shade, since that's what the header/footer/buttons
                // use throughout the app.
                green: {
                    50: '#eafbea',
                    100: '#d0f5d1',
                    200: '#9fe5a2',
                    300: '#6fd873',
                    400: '#3ec944',
                    500: '#18ac1e',
                    600: '#0c9910',
                    700: '#08890c',
                    800: '#076f0a',
                    900: '#065607',
                },
            },
        },
    },

    plugins: [forms],
};
