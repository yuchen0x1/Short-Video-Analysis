# 项目现状档案

审计日期：2026-11-16
审计范围：当前 `main` 分支（HEAD `7a46d70`，版本 `v1.1.10`）

## 1. 项目定位

本项目是一个本地优先的短视频媒体解析工作台，面向需要从公开分享链接取得视频、图集、封面、音乐或直播流的个人用户与自托管部署者。产品范围由仓库 `AGENTS.md` 约束为：

- Windows Electron 电脑版：`apps/app-shell`；
- PHP 网页版、PWA 与 HTTP API：`public`、`app`；
- 共享 Node.js 解析器：`packages/parsers`；
- 不新增独立 Android/iOS 客户端。

## 2. 技术架构

| 层 | 实现 | 证据 |
| --- | --- | --- |
| Web 入口 | PHP 内置服务器路由、Apache rewrite 兼容入口 | `public/router.php`、`public/index.php` |
| Web UI | PHP 模板 + 原生 HTML/CSS/JavaScript | `public/app.php`、`public/workbench.js` |
| API | `/api/v1` 版本化 REST API | `public/api/v1.php`、`public/openapi.yaml` |
| PHP 解析 | PSR-4 `App\\Parsers`，按平台拆分 | `app/Parsers/*` |
| JS 解析 | 原生 ES Modules、无运行时第三方依赖 | `packages/parsers/src/*` |
| Windows | Electron 44 + Vite 7 + electron-updater | `apps/app-shell/package.json` |
| 缓存 | Symfony Cache，文件/Redis/数组驱动 | `app/Services/ParseCache.php`、`config/cache.php` |
| 限流 | Symfony RateLimiter，按 IP 固定窗口 | `app/Services/RateLimiter.php` |
| 媒体代理 | PHP 与 Electron 双代理，Range/HLS/域名白名单/SSRF 校验 | `app/Services/MediaProxy.php`、`apps/app-shell/electron/main.cjs` |
| 签名 sidecar | Node.js `a_bogus` HTTP 服务 | `scripts/a_bogus_server.js` |
| 发布 | GitHub Actions，Windows 安装版/便携版、校验清单、GitHub Release | `.github/workflows/release.yml` |

## 3. 已实现能力

- 12 个平台的单条解析，统一返回 `platform`、`media_type`、`images`、`music` 等字段。
- 视频、图集、封面、音乐地址、作者和基础统计信息展示。
- 网页批量解析（最多 20 条、3 路并发）、失败重试、停止、CSV 与 ZIP 导出。
- 抖音主页和合集分页、加载全部、选择下载和 CSV 导出。
- 抖音、B 站、虎牙、斗鱼直播解析，返回多画质 FLV/HLS 地址。
- B 站热门、抖音热榜、B 站/抖音搜索以及 B 站公开评论查看。
- 本地历史、收藏、JSON 备份导入导出、Cookie 不进入备份。
- 服务端和 Electron 媒体代理，Range、HLS 播放列表改写、域名白名单、私网和重定向逐跳校验。
- API Key、限流、请求 ID、健康检查、GitHub Release 更新检查。
- PWA manifest、Service Worker、深浅色主题、键盘快捷键。

## 4. 当前优势

1. PHP Web/API 与 Windows 客户端都能离线运行核心界面，部署门槛低。
2. 解析结果契约已有 56 项离线 JS 场景和 PHP 单测覆盖，平台边界清晰。
3. 媒体代理采用白名单、DNS 解析、私网拒绝、逐跳重定向和大小限制，安全基线明显高于普通直通代理。
4. 更新发布已限定到 GitHub，包含安装包、便携版、`latest.yml` 和 SHA256 清单生成流程。
5. 资料管理不把 Cookie 写入备份，并对 URL、公式注入和容量做了校验。

## 5. 已核验的质量基线

| 检查 | 结果 |
| --- | --- |
| `composer lint` | PASS |
| `composer test` | PASS，100 tests / 361 assertions |
| `composer smoke` | PASS |
| `npm --prefix packages/parsers test` | PASS，56/56 |
| `npm --prefix packages/parsers run test:cache` | PASS，2/2 |
| `node --test scripts/a_bogus_server.test.mjs` | PASS，6/6 |
| `node --test tests/workbench.test.cjs` | PASS，11/11 |
| `npm --prefix apps/app-shell run build` | PASS，Vite 7.3.7 |
| `node scripts/check-repository-hygiene.mjs` | PASS |
| `git diff --check` | PASS |

## 6. 当前缺陷与技术债务

### P0/P1 风险

- Windows 安装包配置 `signExecutable: false`，更新流程缺少已验证的 Authenticode/发布签名证据。
- PHP 与 JS 各维护一套平台解析器；同一平台修复需要双份回归，长期维护成本高。
- PHP 更新检查、解析、媒体代理虽然已有白名单和限流，但还没有统一的结构化错误码契约。
- POST 请求现在已停止自动重试；429 `Retry-After` 已返回，但客户端尚未统一消费该值。
- 开启 `API_KEY_ENABLED=true` 后，网页外壳没有用户输入 API Key 的配置流程，外部 API 与同源网页的运行边界需要文档化或补齐。

### P2/P3 技术债务

- `packages/parsers` 的缓存此前为无界 `Map`，本轮已加 256 项上限和 `cacheClear`，仍缺少可观测命中率。
- HLS 改写此前固定根路径，本轮已改为查询相对地址，但反向代理矩阵仍缺少真实部署验收。
- 网页版仍是大型 PHP 模板文件，UI 逻辑可测试性与模块边界弱于构建型前端。
- 发现、主页、合集、评论结果的异步焦点管理和 `aria-live` 仍不完整。
- 真实平台链接冒烟、跨版本更新安装、代码签名、Docker daemon 构建尚未在本机完成。

## 7. 成熟度判断

当前为“可发布的个人/自托管工具”水平：功能覆盖广，测试和发布门禁已具备，安全代理基线较好；距离生产级公共服务仍缺少真实平台长期监控、统一错误模型、签名更新、跨部署集成测试和可观测性。

## 8. 下一步方向

优先顺序为：

1. 把更新签名、安装回滚和发布证据补齐。
2. 建立 PHP/JS 共享结果 fixture 与统一错误码。
3. 把限流、重试、缓存命中和解析耗时纳入结构化指标。
4. 增加 API 路由集成测试、子路径部署测试和真实平台定时冒烟。
5. 在保持当前 Web + Windows 范围的前提下，逐步收敛双解析器维护税。
