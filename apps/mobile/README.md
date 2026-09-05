# 去水印解析手机版

手机版是独立的移动端界面，不复用网页端或电脑版的导航布局：采用单手操作、底部导航、分步状态和移动下载队列。

```bash
npm install
npm run dev
```

移动端解析完全在本地完成：界面使用 `packages/parsers` 共享 JS parser，通过 CapacitorHttp 访问平台页面和接口，不依赖根目录 PHP 服务或远程解析 API。媒体预览和下载使用本地原生媒体代理，由原生下载桥接交给系统下载器处理。

生成 Android 工程：

```bash
npm run mobile:add:android
npm run mobile:android
```

生成 iOS 工程：

```bash
npm run mobile:add:ios
npm run mobile:ios
```

## 在线更新

手机版启动时会检查国内 Gitee 最新 Release，也可以在“关于 → 在线更新”手动检查。默认直接读取 Gitee Release 页面；如需使用自定义更新 API，可在 `apps/mobile/.env` 设置 `VITE_UPDATE_API_URL`。该地址必须是 HTTP(S) 的 `gitee.com` 精确域名或合法子域名（例如 `https://api.gitee.com/...`），否则会在发起请求前拒绝。发现更新后，Android 会把 APK 放入系统下载；正式签名 APK 下载完成后按系统提示安装，应用不会静默绕过 Android 的安装确认。
