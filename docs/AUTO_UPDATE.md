# 自动更新与 GitHub 发布

版本统一使用 `vMAJOR.MINOR.PATCH`。Windows 安装版、便携版、PHP 网页压缩包及对应源码均由同一 Git 标签分发。生产默认来源为 [GitHub Releases](https://github.com/yuchen0x1/Short-Video-Analysis/releases/latest)。

## Windows

- 关于页显示当前版本并提供手动检查。启动检查可关闭。发现新版本时显示版本、发布时间、包大小和发布说明，选择稍后后同一版本保留在关于页。
- 普通发行和便携版通过官方发布页下载。安装版具备构建时配置的可信发布者时，才提供应用内下载。下载可见百分比、速度和预计剩余时间。NSIS 更新器校验 SHA-512 和 Authenticode，重启执行需再次确认。
- 正式发行未配置 Windows 代码签名时，可使用 GitHub Release attestation 和 SHA-256 核对来源与文件完整性。Windows 系统对发布者的信任由 Authenticode 证书决定。
- 网络或校验失败后可重新检查，现有应用仍可使用。保留历史发行可帮助人工恢复。在同一 Windows 用户下重装前，建议备份 Electron 用户数据并确认旧版本的数据兼容性。当前未提供自动回滚。

## PHP 网页和 PWA

- 发布页的 `short-video-analysis-web-<version>.zip` 含 PHP、签名服务、Docker 配置和 Composer 锁文件。生产部署方自行备份配置和数据，按新目录安装依赖，经 `/api/v1/health` 确认后切换流量。恢复时切回旧目录与对应配置。
- `/api/v1/update` 从 GitHub 获取公开版本，返回当前版本、最新版、日期和资产。发现 GitHub 有新版时可看发行说明。生产站点是否更新由部署状态决定。
- PWA 在 HTTPS 或 localhost 启用。已部署的新 Service Worker 缓存完整后等待，用户点击“应用页面更新”才刷新该页，保留输入草稿。解析期间可继续使用旧页面。收藏与历史继续由浏览器存储。

## 维护者发版流程

1. 升版本并更新 README、CHANGELOG 和发布说明。将 PR 合并到通过检查的 `main`。
2. 从 `main` 提交创建正式标签，通过 GitHub 远程推送。标签启动同一套质量检查，通过后构建 Windows 与 PHP。
3. 打包后 Windows 实际运行检查。校验 PHP 包结构、SHA-256、Windows 更新 SHA-512 与版本。
4. 只有发布 job 有仓库写权限，且生产环境只接受版本标签。它先上传并核验草稿中的 GitHub 资产摘要，随后公开发行。GitHub 不可变发行为已发布标签与附件生成证明。
5. 检查 [维护工作流](https://github.com/yuchen0x1/Short-Video-Analysis/actions/workflows/maintenance.yml)。维护检查获取公开元数据，核对资产摘要和更新清单，再验证 Release attestation。

```powershell
node scripts/check-release-version.mjs v1.2.0
git remote -v
git tag -a v1.2.0 -m "Release v1.2.0"
git push origin refs/tags/v1.2.0
gh release verify v1.2.0 --repo yuchen0x1/Short-Video-Analysis
```

真实签名安装、旧版迁移、磁盘空间不足、下载安装中断和卸载需要在隔离 Windows 环境中验收。目前 GitHub 发行证明与已下载资产的哈希可独立核对。
