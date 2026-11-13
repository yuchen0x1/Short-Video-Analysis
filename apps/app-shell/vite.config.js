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
      // 电脑版 JS 解析器包（与 PHP 网页版保持结果契约一致）
      '@parsers': fileURLToPath(new URL('../../packages/parsers/src/', import.meta.url)),
    },
  },
});
