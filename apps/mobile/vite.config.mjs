import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';

const pkg = JSON.parse(readFileSync(new URL('./package.json', import.meta.url), 'utf8'));

export default defineConfig({
  // Capacitor 内以本地文件/自定义 scheme 加载，资源必须用相对路径
  base: './',
  // 版本号以 package.json 为唯一来源，关于页直接读取，避免多处手工同步
  define: {
    __APP_VERSION__: JSON.stringify(pkg.version),
    __UPDATE_API_URL__: JSON.stringify(process.env.VITE_UPDATE_API_URL || ''),
  },
  resolve: {
    alias: {
      // 共享解析器包（网页版、电脑版与手机版共用同一套 JS 解析代码）
      '@parsers': fileURLToPath(new URL('../../packages/parsers/src/', import.meta.url)),
      '@update-checker': fileURLToPath(new URL('../../packages/update-checker/src/', import.meta.url)),
    },
  },
});
