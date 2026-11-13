# 去水印解析手机版

手机版是独立的移动端界面，不复用网页端或电脑版的导航布局：采用单手操作、底部导航、分步状态和移动下载队列。

```bash
npm install
npm run dev
```

移动端解析完全在本地完成：界面使用 `packages/parsers` 共享 JS parser，通过 CapacitorHttp 访问平台页面和接口，不依赖根目录 PHP 服务或远程解析 API。媒体预览和下载使用本地原生媒体代理，由原生下载桥接交给系统下载器处理。

仓库已包含 Android 工程，日常开发直接同步并打开：

```bash
npm run mobile:android
```

## Android 本地验证

Windows 开发需要 JDK 21、Android SDK Platform 35、Build Tools 35（构建兼容补充 34）和已接受的 SDK licenses。同步前端并执行 Debug 构建与 JVM 单元测试：

```powershell
npm run mobile:sync
Push-Location android
.\gradlew.bat :app:assembleDebug :app:testDebugUnitTest
Pop-Location
```

APK 输出在 `android/app/build/outputs/apk/debug/app-debug.apk`。项目已处理中文 Windows 路径下 Gradle 测试 worker 的编码问题，源码仍按 UTF-8 编译。

## 在线更新

手机版启动时会检查 GitHub 最新 Release，也可以在“关于 → 在线更新”手动检查。默认直接通过 GitHub Release API 读取最新版本；如需使用自定义更新 API，可在 `apps/mobile/.env` 设置 `VITE_UPDATE_API_URL`。该地址必须是 HTTPS 协议，否则会在发起请求前拒绝。发现更新后，Android 会把 APK 放入系统下载；正式签名 APK 下载完成后按系统提示安装，应用不会静默绕过 Android 的安装确认。

## 图集与本机记录

Android 原生端的「下载全部图片」将原图逐张交给系统下载器；浏览器预览环境提供图集 ZIP（总大小最多 128 MiB，单次请求最多 60 秒）。两者都显示实际任务创建错误，不能把「已加入下载」视为下载已经完成。

最近记录保留 20 条。损坏或无效条目在读取时跳过，不清除原始存储；本机存储不可用时仍可解析，但记录仅在本次会话保留，凭据保存失败会明确提示。

## 回归验证

从仓库根目录执行 `node --test tests/mobile-workbench.test.mjs`。浏览器回归需 Python Playwright 和 Edge，先在本目录启动 `npm run dev -- --host 127.0.0.1 --port 18182 --strictPort`，再从根目录执行 `python tests/mobile_browser.py`。脚本使用明确夹具验证界面和下载桥参数，不代表真实平台或真机验收。
