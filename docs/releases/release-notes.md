# 发布说明

## v1.1.8 · 2026-11-12

### 用户可见改进

- Windows 更新下载页现在显示真实下载速度和可计算时的预计剩余时间。
- 更新来源统一使用 HTTPS；Android 不会把 debug 或 unsigned APK 作为正式升级包。

### 发布与运维

- 新增 GitHub 标签发布流水线，自动构建 NSIS、portable、`latest.yml`、blockmap 和 SHA-256 清单。
- Android 签名发布任务支持通过 GitHub Secrets 启用，并在上传前验证 APK 签名。
- 完整配置与限制见 [`docs/AUTO_UPDATE.md`](../AUTO_UPDATE.md)。

## v1.1.7 · 2026-11-12

### 用户可见改进

- 媒体代理补齐小红书、快手、微博、视频号、最右和 TikTok 等平台的常用 CDN，解析成功后播放与下载更稳定。
- PHP、Windows 和 Android 三端的解析结果字段保持一致；图集、音乐和协议相对地址的处理更加统一。
- 解析接口支持强制刷新。播放失败后可以重新获取上游地址，不再反复使用同一个已失效链接。
- 更新检查增加缓存和手动强制刷新，公共实例更不容易受到 GitHub 请求频率限制影响。
- 健康检查会报告关键 PHP 扩展和签名 sidecar 状态；sidecar 暂时不可用时会明确标记为降级状态。
- 主题跟随系统时重新生效，深色模式首屏闪白和主题色不同步问题得到修复。
- Service Worker 缓存策略升级，网络暂时不可用时仍可打开已缓存的页面壳。

### 开发与部署

- 新增 GitHub Actions 质量门禁，覆盖 PHP、共享解析器、签名 sidecar、桌面端、移动端和仓库卫生检查。
- Docker 镜像补齐运行所需的 PHP `curl`、`dom`、`mbstring` 和 `zip` 扩展。
- Android 本地构建说明补充 JDK 21、Android SDK Platform 35、Build Tools 35 与中文 Windows 路径兼容设置。
- OpenAPI 文档同步到 `1.1.7`，并补充解析强制刷新参数说明。

## v1.1.6 · 2026-11-07

- 新增抖音关键词搜索、收藏夹、批量 ZIP 打包、失败项重试和全局快捷键。
- 新增 PWA 安装支持，主题可跟随系统明暗变化。
- 修复收藏媒体地址、批量快捷键、PWA MIME、搜索分页和批量重试等问题。

## 历史版本

更早版本（`v1.1.5` 及以前）的完整记录保留在 [CHANGELOG.md](../../CHANGELOG.md)。
