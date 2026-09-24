import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        // أصناف ألوان الحالات تُعرَّف داخل التعدادات (badgeClasses) لا في القوالب.
        './app/**/*.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['"Thmanyah Sans"', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // هوية وصال — نفس ألوان المنصة العامة (متغيرات :root في index.html).
                brand: {
                    DEFAULT: '#282692',
                    50: '#f4f3fd',
                    100: '#e8e6fa',
                    200: '#d2cef5',
                    300: '#b0a8ec',
                    400: '#8a7de0',
                    500: '#6655d2',
                    600: '#5039a8',
                    700: '#3a2d9e',
                    800: '#282692',
                    900: '#20134f',
                },
                accent: '#814fc3',
                surface: '#f8f6fd',
                ink: '#1a1632',
            },
        },
    },

    plugins: [forms],
};
