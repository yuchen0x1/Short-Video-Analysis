# 贡献指南

项目维护 Windows 电脑版、PHP 网页、PWA、HTTP API 与 Docker。开始开发前请通过 Issue 说明用户场景、受影响端和验收方法。 [创建 Issue](https://github.com/yuchen0x1/Short-Video-Analysis/issues/new/choose)

从 `main` 创建短期分支，经 Pull Request 合并。功能、修复与紧急修复都走相同质量检查。同步最新 `main` 后解决冲突并重新运行受影响的测试。解析字段变化请同步检查 PHP、JS 契约以及 OpenAPI。

| 内容 | 本地检查 |
| --- | --- |
| PHP | `composer install`、`composer lint`、`composer test`、`composer smoke` |
| JS 解析与工作台 | `npm --prefix packages/parsers test`、`node --test tests/*.test.cjs scripts/a_bogus_server.test.mjs` |
| Windows 电脑版 | `npm --prefix apps/app-shell ci`、`npm --prefix apps/app-shell run build` |
| 仓库和版本 | `git diff --check`、`node scripts/check-repository-hygiene.mjs`、`node scripts/check-release-version.mjs vX.Y.Z` |

正式标签由维护者从通过检查的 `main` 提交创建。发布工作流构建、启动检查并校验产物，通过后上传草稿和正式发布。修复正式版时递增版本，保留历史发行。 [发版流程](docs/AUTO_UPDATE.md)
