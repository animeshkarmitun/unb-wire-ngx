import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            colors: {
                // ===== UNB newsroom palette (app-data README §2) =====
                navy: {
                    900: '#0f1730', // sidebar deep
                    800: '#16204a', // sidebar base
                    600: '#2a3560', // sidebar hover
                    text: '#aab3cf', // sidebar text
                    label: '#667099', // sidebar section labels
                },
                crimson: {
                    DEFAULT: '#e5484d', // primary accent — wire/breaking red
                    dark: '#d13438',
                    soft: '#fdecec',
                },
                amber: '#f0a832', // secondary accent
                paper: '#faf9f6', // warm page background
                panel: '#ffffff',
                border: '#eceae5',
                ink: '#1c1f2e',
                muted: '#7c7f8c',
                'muted-2': '#b0b2bc',
                green: {
                    DEFAULT: '#16a34a',
                    bg: '#e5f6ec',
                },
                red: '#dc2626',
                blue: {
                    DEFAULT: '#3b6fe0',
                    bg: '#e9f0fd',
                },
                pink: {
                    DEFAULT: '#db2777',
                    bg: '#fdeef5',
                },
                lime: {
                    DEFAULT: '#65a30d',
                    bg: '#f2f9e6',
                },
                purple: {
                    DEFAULT: '#7c3aed', // reserved for AI-related UI
                    bg: '#f1eafe',
                },
                // KPI tints
                tint: {
                    blue: { DEFAULT: '#eef3fe', border: '#dce7fb' },
                    mint: { DEFAULT: '#e9f8f0', border: '#d4efdf' },
                    peach: { DEFAULT: '#fff3e8', border: '#fae3cf' },
                    rose: { DEFAULT: '#fdeef4', border: '#f7d9e6' },
                },
            },
            fontFamily: {
                sans: ['Inter', 'system-ui', '-apple-system', 'Segoe UI', 'Roboto', ...defaultTheme.fontFamily.sans],
                serif: ['Fraunces', 'Georgia', ...defaultTheme.fontFamily.serif],
                'serif-body': ['"Source Serif 4"', 'Georgia', 'Times New Roman', ...defaultTheme.fontFamily.serif],
            },
        },
    },

    plugins: [forms],
};
