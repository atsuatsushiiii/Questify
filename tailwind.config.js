// Code © 2026 Kevin Cyril Maraño, MIT license.

/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './app/Views/**/*.php',
    './resources/js/**/*.js',
    './public/**/*.{html,php}',
  ],
  theme: {
    extend: {
      colors: {
        /*
         * Expedition Ledger palette. The names are unchanged from the earlier
         * themes so every view inherits the new ink-and-parchment world without
         * having to rewrite utility classes.
         *
         * Warm near-black paper stock, parchment type, brass ("brand"),
         * vermilion ("ember"), ochre ("gold"), and a copper verdigris ("aqua").
         */
        void: '#0B0907',
        ink: '#100D09',
        surface: '#15110C',
        panel: '#1A150F',
        line: '#332B21',
        muted: '#B6AB98',
        'muted-dim': '#9C9180',
        brand: {
          50: '#FAF3E4',
          100: '#F3E6C8',
          200: '#E7CE97',
          300: '#D9B266',
          400: '#C9993F',
          500: '#B47F2A',
          600: '#96651F',
          700: '#75501D',
          800: '#5C3F1B',
          900: '#46321A',
        },
        aqua: {
          50: '#EAF5F1',
          100: '#CFE8DF',
          200: '#A3D2C3',
          300: '#72B7A3',
          400: '#4A9A85',
          500: '#367D6B',
          600: '#2A6456',
          700: '#235046',
          800: '#1E413A',
          900: '#1A3731',
        },
        ember: {
          50: '#FDF1ED',
          100: '#FADFD5',
          200: '#F4BDA9',
          300: '#EC9377',
          400: '#E06A46',
          500: '#C94C2A',
          600: '#A93A1F',
          700: '#882E1B',
          800: '#6E2819',
          900: '#5B2418',
        },
        gold: {
          50: '#FBF6E3',
          100: '#F5EABF',
          200: '#EBD68B',
          300: '#DEBE54',
          400: '#C9A230',
          500: '#AA8420',
          600: '#8A6819',
          700: '#6F5318',
          800: '#5B4518',
          900: '#4C3A17',
        },
        /*
         * Warm the stock Tailwind slate scale so incidental slate utilities read
         * as aged paper rather than cool dashboard grey.
         */
        slate: {
          50: '#F7F2E7',
          100: '#EDE6D6',
          200: '#DBD0BB',
          300: '#C6B9A1',
          400: '#A99C86',
          500: '#8C806C',
          600: '#6B6152',
          700: '#514A3F',
          800: '#3A352E',
          900: '#26231F',
          950: '#171512',
        },
      },
      fontFamily: {
        display: ['Fraunces', '"Iowan Old Style"', 'Georgia', 'serif'],
        sans: ['"IBM Plex Sans"', 'ui-sans-serif', 'system-ui', '-apple-system', 'sans-serif'],
        mono: ['"IBM Plex Mono"', 'ui-monospace', 'SFMono-Regular', 'Menlo', 'Consolas', 'monospace'],
      },
      /*
       * Expedition Ledger surfaces are cut paper, not soft glass. Shrinking the
       * radius scale ripples through every existing card and control.
       */
      borderRadius: {
        DEFAULT: '0.125rem',
        md: '0.1875rem',
        lg: '0.25rem',
        xl: '0.375rem',
        '2xl': '0.5rem',
        '3xl': '0.75rem',
      },
      boxShadow: {
        'glow-brand': '0 0 0 1px rgba(201, 153, 63, 0.28)',
        'glow-aqua': '0 0 0 1px rgba(74, 154, 133, 0.26)',
        'glow-ember': '0 0 0 1px rgba(201, 76, 42, 0.28)',
        'glow-gold': '0 0 0 1px rgba(201, 162, 48, 0.26)',
        offset: '3px 3px 0 0 rgba(0, 0, 0, 0.55)',
        'offset-lg': '5px 5px 0 0 rgba(0, 0, 0, 0.5)',
        stamp: 'inset 0 0 0 1px rgba(217, 178, 102, 0.32)',
      },
      backgroundImage: {
        'hero-radial':
          'linear-gradient(180deg, rgba(201, 153, 63, 0.06) 0%, rgba(11, 9, 7, 0) 60%)',
      },
      keyframes: {
        'float-slow': {
          '0%, 100%': { transform: 'translateY(0px)' },
          '50%': { transform: 'translateY(-10px)' },
        },
        'pulse-soft': {
          '0%, 100%': { opacity: '0.5' },
          '50%': { opacity: '0.9' },
        },
      },
      animation: {
        'float-slow': 'float-slow 7s ease-in-out infinite',
        'pulse-soft': 'pulse-soft 5s ease-in-out infinite',
      },
    },
  },
  plugins: [],
};
