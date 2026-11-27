import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// In development the React app talks to the Laravel API on port 8000 through this proxy.
// For production the Dockerfile builds with VITE_BASE=/app/ and copies dist/ into backend/public/app,
// so Laravel serves the app and the API from the same domain.
export default defineConfig({
  base: process.env.VITE_BASE ?? '/',
  plugins: [react()],
  server: {
    port: 5173,
    proxy: {
      '/api': 'http://127.0.0.1:8000',
      '/storage': 'http://127.0.0.1:8000',
    },
  },
});
