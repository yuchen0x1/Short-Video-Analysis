import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';

const pkg = JSON.parse(readFileSync(new URL('./package.json', import.meta.url), 'utf8'));

export default defineConfig({
  base: './',
  define: {
    __APP_VERSION__: JSON.stringify(pkg.version),
  },
  resolve: {
    alias: {
      // 共享解析器包（手机版与电脑版共用同一套 JS 解析代码）
      '@parsers': fileURLToPath(new URL('../../packages/parsers/src/', import.meta.url)),
    },
  },
});
