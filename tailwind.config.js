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
        void: '#06060D',
        ink: '#0A0A13',
        surface: '#0F0F1A',
        panel: '#14141F',
        line: '#252538',
        muted: '#9898B3',
        brand: {
          50: '#F4F0FF',
          100: '#E9E1FF',
          200: '#D4C4FF',
          300: '#B79BFF',
          400: '#9D78FF',
          500: '#8B5CF6',
          600: '#7C3AED',
          700: '#6D28D9',
          800: '#5B21B6',
          900: '#4C1D95',
        },
        aqua: {
          50: '#ECFEFF',
          100: '#CFFAFE',
          200: '#A5F3FC',
          300: '#67E8F9',
          400: '#22D3EE',
          500: '#06B6D4',
          600: '#0891B2',
          700: '#0E7490',
          800: '#155E75',
          900: '#164E63',
        },
      },
      fontFamily: {
        display: ['"Space Grotesk"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
        sans: ['Inter', 'ui-sans-serif', 'system-ui', '-apple-system', 'sans-serif'],
      },
      boxShadow: {
        'glow-brand': '0 20px 70px -30px rgba(139, 92, 246, 0.65)',
        'glow-aqua': '0 20px 70px -30px rgba(34, 211, 238, 0.5)',
      },
      backgroundImage: {
        'hero-radial':
          'radial-gradient(60% 60% at 50% 0%, rgba(139, 92, 246, 0.18) 0%, rgba(6, 6, 13, 0) 70%)',
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
