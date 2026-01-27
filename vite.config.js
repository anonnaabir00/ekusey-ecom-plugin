import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react()],
  build: {
    minify: true,
    rollupOptions: {
      input: 'src/main.jsx',
      output: {
        dir: 'includes/assets/admin',
        entryFileNames: 'admin.js',
        assetFileNames: 'admin.[ext]',
      },
    },
  },
});
