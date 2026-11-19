import tailwindcss from '@tailwindcss/vite';
import path from 'path';
import {defineConfig} from 'vite';

export default defineConfig(() => {
  return {
    plugins: [tailwindcss()],
    build: {
      outDir: path.resolve(__dirname, 'public'),
      emptyOutDir: false,
      rollupOptions: {
        input: {
          admin: path.resolve(__dirname, 'resources/css/admin.css'),
        },
        output: {
          assetFileNames: 'css/[name].css',
        },
      },
    },
    resolve: {
      alias: {
        '@': path.resolve(__dirname, '.'),
      },
    },
    server: {
      hmr: process.env.DISABLE_HMR !== 'true',
      watch: process.env.DISABLE_HMR === 'true' ? null : {},
    },
  };
});
