# 自动更新与 GitHub 发布

本项目使用 GitHub Releases 作为唯一官方更新源，按客户端分别使用平台适配的更新机制：

| 客户端 | 更新实现 | 安装方式 |
| --- | --- | --- |
| Windows Electron | `electron-updater` + `latest.yml` | 下载完成后由 NSIS 更新器校验并重启安装 |
| Android Capacitor | `packages/update-checker` + GitHub Release APK | 交给 Android 系统下载器，用户确认安装 |
| 网页/API | `/api/v1/update` | 返回最新 Release、更新日志和资产信息；网页不执行本地安装 |

## 发布流程

正式发布由 GitHub 标签触发：

```text
git tag v1.2.0
git push origin v1.2.0
```

`.github/workflows/release.yml` 会执行以下步骤：

1. 校验根目录 Composer、Electron、Android、共享包、npm lock 与 OpenAPI 的版本号都等于标签版本。
2. 在 Windows runner 安装依赖并构建 NSIS 安装包、portable 包和 `latest.yml`。打包器自动获取对应 Electron runtime，显式 `--publish never` 防止标签触发隐式上传。
3. 生成 `SHA256SUMS.txt` 与 `update-manifest.json`。
4. 从 `docs/releases/release-notes.md` 提取当前版本说明，创建 GitHub Draft Release 并上传资产；失败重跑可复用草稿，禁止覆盖已经正式发布的同版本资产。
5. 资产上传成功后才将 Draft Release 发布为 Latest Release。
6. 当仓库变量 `ANDROID_RELEASE_ENABLED=true` 时，后置 Android job 使用受保护的 keystore Secrets 构建、验签并追加 APK 资产。

工作流只使用 GitHub Actions 内置 `GITHUB_TOKEN`，不需要提交或配置个人访问令牌。仓库远程必须保持在 GitHub；本项目不会向 Gitee 推送。

## Windows 更新安全边界

- 更新源固定为 `https://github.com/yuchen0x1/Short-Video-Analysis`。
- 桌面端使用构建时写入的 GitHub provider；网站/移动端更新检查器会拒绝凭据 URL、非 GitHub 更新源和非 HTTPS 地址，服务端路径还限制响应大小并回退到官方 Release 页面。
- `electron-updater` 根据 `latest.yml` 中的 SHA-512 和文件大小校验安装包，校验完成前不会安装。
- 下载进度由真实 `download-progress` 事件驱动，界面显示百分比、速度和可计算时的剩余时间。
- 没有代码签名证书时，Windows 仍会显示系统的未知发布者提示。当前仓库没有提交证书或私钥；需要签名时应通过受保护的 GitHub Secret 接入签名服务。

## Android 更新边界

Android 客户端只选择 Release 中非 `debug`、非 `unsigned` 的 APK。客户端通过系统 `DownloadManager` 下载，安装覆盖能否成功取决于 APK 是否使用同一发布密钥签名。

正式 Android 发布需要配置以下 GitHub Secrets，并设置仓库变量 `ANDROID_RELEASE_ENABLED=true`：`ANDROID_KEYSTORE_BASE64`、`ANDROID_STORE_PASSWORD`、`ANDROID_KEY_ALIAS`、`ANDROID_KEY_PASSWORD`。工作流会构建、验签并自动上传 APK；没有这些外部密钥时，更新检查仍可用，但客户端不会把 debug/unsigned APK 当作正式升级包。

## 更新渠道

当前仅启用 `stable` 正式渠道。桌面端关闭预发布版本，移动端读取 GitHub 最新正式 Release。若以后增加 Beta，需要单独的标签和 Release 选择策略，不能把预发布包混入稳定渠道。

## 本地验证

```powershell
node scripts/check-release-version.mjs v1.1.9
node scripts/generate-release-manifest.mjs apps/app-shell/release v1.1.9

Push-Location packages/update-checker
npm test
Pop-Location

Push-Location apps/app-shell
npm ci
npm run build
Pop-Location
```

要验证真实升级，还需要一台安装了旧版的 Windows 客户端，以及一个包含新版本安装包的 GitHub Release。没有这两项时只能完成本地构建和元数据校验，不能声称真实重启安装已通过。

## 失败处理

- GitHub API 不可用时，桌面端和共享更新检查器回退到官方 Release Atom/附件页。
- 网络错误只显示可读错误，不阻塞应用启动。
- 下载校验失败或安装失败时，旧版本仍保留；Electron 更新器不会先删除旧版本。
- Release 资产上传失败时 Draft Release 保留，便于查看 Actions 日志并重新执行；构建失败会直接保留 Actions 日志，不会覆盖已发布资产。
- `SHA256SUMS.txt` 可用于发布后人工或 CI 校验；`update-manifest.json` 提供版本、资产大小、SHA-256 和实际 GitHub 下载地址。

## 已知限制

- 当前仓库没有 Windows 代码签名证书，无法在本地或 CI 中声称签名验证通过。
- Android 正式签名依赖外部 keystore；未配置时 Android job 会跳过，工作流只发布 Windows 资产。
- 网页端只提供版本信息，不会尝试替换服务器文件或执行部署回滚。
