# 去水印解析电脑版

这个目录是独立的电脑版客户端。它不复用网页端的布局，使用桌面侧栏、多栏结果区和快捷工作区。

## 本地开发

```bash
npm install
npm run dev
```

浏览器打开 Vite 输出的地址。开发环境默认通过 Electron 主进程访问平台网络；解析器使用 `packages/parsers` 共享 JS parser，不需要在客户端界面填写解析服务地址。

## 电脑版

```bash
npm run build
npm run desktop
```

开发时可以先启动 `npm run dev`，再使用：

```powershell
$env:APP_DEV_SERVER_URL="http://127.0.0.1:5173"
npm run desktop:dev
```

桌面壳使用 Electron。渲染进程通过 IPC 使用主进程网络传输，解析完全复用 `packages/parsers` 的共享 JS parser；本地媒体代理由 Electron 主进程启动并负责 Referer、UA、Range 和下载转发。应用不携带或启动 PHP runtime，也不依赖根目录 PHP API。

## Windows 安装包

```powershell
npm run dist:win
```

产物写入 `release`，包括 NSIS 安装程序和 portable 便携版。打包机只需要 Node.js、npm 依赖和 Windows 构建环境。

项目只提供本目录的 Windows 电脑版和根目录 PHP 网页版；手机浏览器访问属于网页版。
