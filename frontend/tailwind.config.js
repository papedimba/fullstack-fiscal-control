/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{js,ts,jsx,tsx}'],
  theme: {
    extend: {
      colors: {
        primary: {
          50: '#EEF2F8',
          100: '#D8E2F0',
          200: '#B0C5E0',
          300: '#89A8D1',
          400: '#628BC2',
          500: '#3B6EB3',
          600: '#2F5890',
          700: '#23426C',
          800: '#1E3A5F',
          900: '#162B47',
        },
      },
    },
  },
  plugins: [],
};
