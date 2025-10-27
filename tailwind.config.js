import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/filament/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/Filament/**/*.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                supernova: {
                    'primary-dark': '#0A1A1A',
                    'primary-mid1': '#1A4A4A', 
                    'primary-mid2': '#2A6A6A',
                    'primary-bright': '#00BFBF',
                    'secondary-aqua': '#00FFFF',
                    'secondary-turquoise': '#40E0D0',
                    'accent-teal': '#20B2AA',
                    'accent-medium': '#48D1CC',
                    'text-white': '#FFFFFF',
                    'text-light-cyan': '#E0FFFF',
                    'background-dark': '#0A1A1A',
                }
            }
        },
    },

    plugins: [forms],
};