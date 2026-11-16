# 项目自主进化交付报告

交付日期：2026-11-16
仓库：`yuchen0x1/Short-Video-Analysis`
基线：`main` / `7a46d70` / `v1.1.10`；发布目标：`v1.1.11`

## A. 竞品研究

本轮研究了 15 个 GitHub 开源项目：yt-dlp、MeTube、Seal、Streamlink、gallery-dl、lux、you-get、cobalt、Douyin_TikTok_Download_API、MediaGo、Media Downloader、OmniGet、aria2、Electron、electron-builder。

研究覆盖仓库主页、README、许可证、最近提交、Release、最新公开 Issues/PR；对 yt-dlp、MeTube、Streamlink、gallery-dl 还读取了核心源码文件。研究结论和链接见 [COMPETITOR_ANALYSIS.md](COMPETITOR_ANALYSIS.md)。

最值得借鉴的方向：

1. yt-dlp/gallery-dl 的持续站点修复和 extractor/fixture 思路；
2. MeTube 的队列状态、并发配置、目录隔离和近期安全修复；
3. Streamlink 的直播插件与播放器交接；
4. Cobalt 的极简、无广告、本地隐私体验；
5. electron-builder 近期对 Windows 更新 manifest、publisherName 和安装器版本绑定的加强。

本项目仍然领先或具有独特性的地方：中文短视频平台专用解析、Web + Windows 双端、HLS 防盗链代理、抖音主页/合集/发现/直播组合能力，以及不把 Cookie 放入资料备份的本地优先设计。

## B. 升级数量与完成状态

- 发现并建立：36 个独立升级点；
- 本轮实施完成：9 个（EV-001 至 EV-009）；
- 部分完成：0 个；
- 未完成：27 个；
- 阻塞：EV-024（真实代码签名/证书）以及依赖真实平台、发布服务或产品决策的后续项。

本轮完成项：

| EV | 实施内容 | 证据 |
| --- | --- | --- |
| EV-001 | GET-only 自动重试，POST 不自动重发 | `app/Utils/HttpClient.php`、PHP 回归测试 |
| EV-002 | 429 返回 `Retry-After` | `app/Utils/Response.php`、`public/index.php`、`public/api/v1.php` |
| EV-003 | JS 缓存 256 项 LRU/TTL 上限和清理 | `packages/parsers/src/cache.js`、`test/cache.mjs` |
| EV-004 | HLS 使用查询相对代理地址，支持子路径 | `app/Services/MediaProxy.php`、`DiscoveryBehaviorTest.php` |
| EV-005 | 日志文件遵循 `logging.file` 配置 | `app/Utils/Logger.php` |
| EV-006 | Electron CSP | `apps/app-shell/index.html` |
| EV-007 | Electron IPC sender/来源校验 | `apps/app-shell/electron/main.cjs` |
| EV-008 | 生产环境仅允许 dist 内 file 导航 | `apps/app-shell/electron/main.cjs` |
| EV-009 | Web 加载状态和结果区可访问性标记 | `public/index.php`、`public/app.php` |

未完成项的原因：

- EV-010/014/016/017/018 需要跨 PHP/JS 契约设计与脱敏 fixture，属于下一阶段 API 兼容工作；
- EV-019/020/022/023/025/032/035 需要较大的产品和 UI 变更，当前先保留现有行为；
- EV-024/028/029/036 依赖证书、GitHub 权限或新增 CI 服务，不能以本地文件存在冒充完成；
- EV-026/027/031/033/034 需要真实 HTTP/反向代理/浏览器或平台环境验证。

## C. 代码修改

新增文件：

- `PROJECT_AUDIT.md`
- `COMPETITOR_ANALYSIS.md`
- `UPGRADE_ROADMAP.md`
- `UPGRADE_REPORT.md`
- `packages/parsers/test/cache.mjs`

修改文件：

- `.github/workflows/quality.yml`
- `app/Services/MediaProxy.php`
- `app/Utils/HttpClient.php`
- `app/Utils/Logger.php`
- `app/Utils/Response.php`
- `apps/app-shell/electron/main.cjs`
- `apps/app-shell/index.html`
- `packages/parsers/package.json`
- `packages/parsers/src/cache.js`
- `public/api/v1.php`
- `public/app.php`
- `public/index.php`
- `tests/Unit/DiscoveryBehaviorTest.php`
- `tests/Unit/RegressionFixesTest.php`

没有删除用户数据、配置或产品模块；没有引入新的运行时依赖；没有新增 Android/iOS 工程。

## D. 测试结果

| 检查 | 结果 | 实际证据 |
| --- | --- | --- |
| PHP 语法/lint | PASS | `composer lint` |
| PHP 单元测试 | PASS | `composer test`：100 tests / 361 assertions |
| PHP smoke | PASS | `composer smoke` |
| JS parser contract | PASS | `npm --prefix packages/parsers test`：56/56 |
| JS cache tests | PASS | `npm --prefix packages/parsers run test:cache`：2/2 |
| a_bogus sidecar tests | PASS | `node --test scripts/a_bogus_server.test.mjs`：6/6 |
| Web workbench tests | PASS | `node --test tests/workbench.test.cjs`：11/11 |
| Desktop renderer build | PASS | `npm --prefix apps/app-shell run build` |
| Electron syntax | PASS | `node --check apps/app-shell/electron/main.cjs` |
| Repository hygiene | PASS | `node scripts/check-repository-hygiene.mjs` |
| Whitespace | PASS | `git diff --check` |

未完成验证：

- 未执行真实平台分享链接、直播流、微博登录态或 TikTok 网络冒烟；
- 未执行 Docker daemon 构建、真实 Windows 安装包签名和旧客户端升级安装；
- 未执行反向代理子路径的真实浏览器回归；本轮只用离线 HLS 改写测试覆盖逻辑。

## E. 项目变化

升级前：项目已经具备较完整的 12 平台解析、批量、发现、直播、PWA、媒体代理和 GitHub 发布基础，但存在 POST 重试风险、Electron IPC 信任边界偏宽、无 CSP、JS 缓存无上限、HLS 子路径不稳、日志配置不生效以及限流响应缺少等待提示等问题。

升级后：上述 9 个问题已在代码中修复并有自动化检查；PHP 端测试从 99 个增加到 100 个，JS 端增加独立缓存行为测试；Electron 构建和所有离线回归仍保持通过。文档现在明确区分了已完成、计划、阻塞和需要真实外部环境的项目。

仍然不足：代码签名和更新安装 E2E、统一错误码、跨语言 fixture、API 集成测试、真实平台长期健康度、任务持久化、可观测性和前端模块化仍未完成。

下一阶段建议按这个顺序：

1. EV-024：配置 Windows 代码签名并在 GitHub Actions 验证更新安装；
2. EV-010/014/018：统一错误码、跨语言 fixture 和可重试分类；
3. EV-026/027/028：API/子路径集成测试与 CodeQL/SCA；
4. EV-013/030：缓存单飞、结构化日志和解析指标；
5. EV-019/020/022：在不改变当前产品范围的情况下完善下载队列。

## 发布边界

本报告对应 `v1.1.11` 发布内容。远程发布证据以 GitHub 的 `v1.1.11` 标签、Actions 运行结果和公开 Release 资产为准；PHP 网页部署、代码签名和旧客户端升级安装仍需独立验收。按照仓库规则，远程操作只允许目标 GitHub。
