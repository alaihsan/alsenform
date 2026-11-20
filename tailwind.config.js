import defaultTheme from 'tailwindcss/defaultTheme';
import { looksPlugin, surfaceColors, textColors } from './resources/js/theme/palette.js';

/** The shadcn tokens, set for each look by resources/js/theme/palette.js. */
const tokens = {
    background: 'hsl(var(--background))',
    foreground: 'hsl(var(--foreground))',
    card: {
        DEFAULT: 'hsl(var(--card))',
        foreground: 'hsl(var(--card-foreground))',
    },
    popover: {
        DEFAULT: 'hsl(var(--popover))',
        foreground: 'hsl(var(--popover-foreground))',
    },
    primary: {
        DEFAULT: 'hsl(var(--primary))',
        foreground: 'hsl(var(--primary-foreground))',
    },
    secondary: {
        DEFAULT: 'hsl(var(--secondary))',
        foreground: 'hsl(var(--secondary-foreground))',
    },
    muted: {
        DEFAULT: 'hsl(var(--muted))',
        foreground: 'hsl(var(--muted-foreground))',
    },
    accent: {
        DEFAULT: 'hsl(var(--accent))',
        foreground: 'hsl(var(--accent-foreground))',
    },
    destructive: {
        DEFAULT: 'hsl(var(--destructive))',
        foreground: 'hsl(var(--destructive-foreground))',
    },
    border: 'hsl(var(--border))',
    input: 'hsl(var(--input))',
    ring: 'hsl(var(--ring))',
    chart: {
        1: 'hsl(var(--chart-1))',
        2: 'hsl(var(--chart-2))',
        3: 'hsl(var(--chart-3))',
        4: 'hsl(var(--chart-4))',
        5: 'hsl(var(--chart-5))',
    },
    sidebar: {
        DEFAULT: 'hsl(var(--sidebar-background))',
        foreground: 'hsl(var(--sidebar-foreground))',
        primary: 'hsl(var(--sidebar-primary))',
        'primary-foreground': 'hsl(var(--sidebar-primary-foreground))',
        accent: 'hsl(var(--sidebar-accent))',
        'accent-foreground': 'hsl(var(--sidebar-accent-foreground))',
        border: 'hsl(var(--sidebar-border))',
        ring: 'hsl(var(--sidebar-ring))',
    },
};

/** Text, icons, placeholders and carets use the text colours of the looks. */
const textPalette = { ...textColors, ...tokens };

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: ['class'],
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{vue,js,ts,jsx,tsx}',
    ],
    theme: {
        // Every colour follows the chosen look (light, dark, warm): see resources/js/theme/palette.js.
        colors: { ...surfaceColors, ...tokens },
        textColor: textPalette,
        placeholderColor: textPalette,
        caretColor: textPalette,
        textDecorationColor: textPalette,
        fill: textPalette,
        stroke: textPalette,
        extend: {
            fontFamily: {
                sans: ['Instrument Sans', ...defaultTheme.fontFamily.sans],
            },
            borderRadius: {
                lg: 'var(--radius)',
                md: 'calc(var(--radius) - 2px)',
                sm: 'calc(var(--radius) - 4px)',
            },
        },
    },
    plugins: [require('tailwindcss-animate'), looksPlugin],
};
