# 仓库发布公告（所有 AI 会话必须遵守）

## 产品范围

- 本项目只维护 Windows 电脑版（`apps/app-shell`）和 PHP 网页版（`public`、`app`）。
- 不制作独立 Android、iOS 或其他移动客户端，不新增移动工程及其构建、发布任务。
- 网页响应式布局和 PWA 是网页版能力，应保留；API 与 Docker 是网页版的接口和部署方式。
- 旧日志中的 Android 内容仅作为历史记录，不作为当前功能或后续维护范围。

**【永久规则】从现在开始，本项目的后续代码更新、提交和发版一律只推送到 GitHub 仓库。**

- GitHub 主页：https://github.com/yuchen0x1 （对应仓库：https://github.com/yuchen0x1/<仓库名>）
- 任何 git push、发版、同步任务：目标只能是 GitHub，绝不要再向 Gitee（gitee.com/yuchen0x1）推送任何内容。
- 推送前先 `git remote -v` 检查远程：如没有 GitHub 远程，先添加指向本项目 GitHub 仓库的 remote（建议名称 `github`），推送时只使用该远程。
