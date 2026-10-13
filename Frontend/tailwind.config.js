/** @type {import('tailwindcss').Config} */
// Los colores salen de variables CSS (style.css) para que el modo claro/oscuro
// cambie todo el sistema sin duplicar clases. Formato "R G B" para soportar /opacidad.
const v = (name) => `rgb(var(--${name}) / <alpha-value>)`

export default {
    content: [
        "./index.html",
        "./src/**/*.{vue,js,ts,jsx,tsx}",
    ],
    darkMode: ['class', '[data-theme="dark"]'],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Manrope', 'system-ui', 'sans-serif'],
                serif: ['Fraunces', 'Georgia', 'serif'],
                mono: ['"JetBrains Mono"', 'ui-monospace', 'monospace'],
            },
            colors: {
                bg: v('bg'),
                surface: v('surface'),
                'surface-2': v('surface-2'),
                'surface-3': v('surface-3'),
                line: v('line'),
                ink: v('ink'),
                'ink-2': v('ink-2'),
                'ink-3': v('ink-3'),
                gold: v('gold'),
                'gold-2': v('gold-2'),
                'on-gold': v('on-gold'),
                sage: v('sage'),
                ok: v('ok'),
                warn: v('warn'),
                danger: v('danger'),
                muted: v('muted'),
                info: v('info'),
            },
            borderRadius: {
                xl: '14px',
                '2xl': '20px',
                '3xl': '28px',
            },
            boxShadow: {
                soft: '0 1px 2px rgb(0 0 0 / .18), 0 8px 24px -12px rgb(0 0 0 / .35)',
                lift: '0 2px 6px rgb(0 0 0 / .2), 0 24px 48px -20px rgb(0 0 0 / .55)',
                glow: '0 0 0 1px rgb(var(--gold) / .35), 0 10px 30px -10px rgb(var(--gold) / .45)',
            },
            keyframes: {
                'pulse-ring': {
                    '0%': { boxShadow: '0 0 0 0 rgb(var(--gold) / .55)' },
                    '70%': { boxShadow: '0 0 0 14px rgb(var(--gold) / 0)' },
                    '100%': { boxShadow: '0 0 0 0 rgb(var(--gold) / 0)' },
                },
                shimmer: { '100%': { transform: 'translateX(100%)' } },
            },
            animation: {
                'pulse-ring': 'pulse-ring 1.6s cubic-bezier(.4,0,.6,1) infinite',
                shimmer: 'shimmer 1.6s infinite',
            },
        },
    },
    plugins: [],
}
