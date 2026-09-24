import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import plugin from 'tailwindcss/plugin';

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
            // هوية وصال — نفس رموز المنصة العامة (متغيرات :root في index.html):
            // درجات brand-50…900 للمحتوى، والأسماء (primary, sky…) لهيكل الصفحات.
            colors: {
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
                    primary: '#282692',
                    secondary: '#814fc3',
                    tertiary: '#5039a8',
                    navy: '#20134f',
                    sky: '#52bdf9',
                    bg: '#f8f6fd',
                    surface: '#f1eef8',
                    border: '#e4ddf0',
                    muted: '#6b6088',
                    text: '#3d3558',
                    ink: '#1a1632',
                },
                accent: '#814fc3',
                surface: '#f8f6fd',
                ink: '#1a1632',
            },
            backgroundImage: {
                'brand-gradient': 'linear-gradient(135deg,#814fc3 0%,#5039a8 40%,#282692 100%)',
                'brand-hero': 'linear-gradient(160deg,#12103a 0%,#1a1560 20%,#282692 45%,#4a35a8 70%,#6b42bc 85%,#814fc3 100%)',
            },
        },
    },

    plugins: [
        forms,
        // القائمة الجانبية المصغّرة: الصنف يوضع على <html> قبل أول رسم حتى لا
        // تومض القائمة موسّعة ثم تنكمش عند التحميل
        plugin(({ addVariant }) => {
            addVariant('sb-collapsed', 'html.sb-collapsed &');
        }),
    ],
};
