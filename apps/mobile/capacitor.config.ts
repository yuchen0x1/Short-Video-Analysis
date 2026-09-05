import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'com.yuchen.qushuiyin',
  appName: '去水印解析',
  webDir: 'dist',
  bundledWebRuntime: false,
  // http scheme：页面源为 http://localhost，本地媒体代理(127.0.0.1)的地址不构成混合内容
  server: { androidScheme: 'http' },
};

export default config;
