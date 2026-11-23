# 项目进化路线图

建立日期：2026-11-16
状态约定：`已完成` 表示本轮有代码和测试证据；`计划` 表示已确认价值但尚未实施；`阻塞` 表示需要证书、真实平台、产品决策或外部服务。

## 升级清单

| ID | 升级名称 | 模块 | 当前问题与依据 | 对标参考 | 实施方案与预期效果 | 文件/模块 | 难度 | 优先级 | 验收标准 | 状态 |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| EV-001 | 禁止 POST 自动重试 | HTTP | `HttpClient` 原先按网络/5xx 重发所有请求 | yt-dlp/MeTube 的失败边界 | 仅无 body 的 GET 重试，避免副作用重复请求 | `app/Utils/HttpClient.php` | 低 | P0 | POST 失败只执行一次；GET 仍可退避 | 已完成 |
| EV-002 | 429 Retry-After | API | 限流响应没有机器可读等待时间 | MeTube/HTTP 客户端惯例 | 统一从限流 reset 计算 `Retry-After` | `Response.php`、`public/*` | 低 | P1 | 所有 429 含合法秒数头 | 已完成 |
| EV-003 | JS 缓存上限与清理 | 桌面解析 | 模块级 Map 可随新 URL 无界增长 | aria2/下载器的有界状态原则 | 256 项 LRU、TTL 清理、`cacheClear` | `packages/parsers/src/cache.js` | 低 | P1 | 超过容量淘汰旧项；过期项不可读 | 已完成 |
| EV-004 | HLS 子路径改写 | 媒体代理 | `/index.php` 根绝对路径在反向代理子路径失效 | MeTube URL_PREFIX、Workbox 部署实践 | 改为查询相对地址，继承当前部署目录 | `app/Services/MediaProxy.php` | 低 | P1 | `/subdir/` 播放列表的分片仍命中 `/subdir/index.php` | 已完成 |
| EV-005 | 日志路径遵循配置 | 可观测性 | `config/app.php` 暴露 logging.file，Logger 却硬编码 | Symfony 配置分层 | 动态读取 logging.file，保留轮转 | `app/Utils/Logger.php` | 低 | P1 | 改配置后日志写入新路径且轮转正常 | 已完成 |
| EV-006 | Electron CSP | 桌面安全 | `index.html` 没有 CSP | Electron 官方安全基线 | 限制脚本、连接、媒体和 frame 来源 | `apps/app-shell/index.html` | 低 | P0 | 构建页面无 CSP 违规；外部脚本被拒绝 | 已完成 |
| EV-007 | Electron IPC sender 校验 | 桌面安全 | 关键 IPC 只依赖 preload 暴露面 | Electron 安全实践 | 校验 sender webContents、来源和主窗口 | `apps/app-shell/electron/main.cjs` | 中 | P0 | 非信任 frame 调 IPC 被拒绝 | 已完成 |
| EV-008 | 文件导航限制到 dist | 桌面安全 | 生产环境 `file:` 过宽 | Electron 安全实践 | 仅允许打包 `dist` 目录下文件 | `main.cjs` | 中 | P0 | dist 外 file URL 导航被阻止 | 已完成 |
| EV-009 | 网页结果区可访问性 | Web UX | 异步结果缺少统一 live region/焦点目标 | Cobalt/成熟 Web UI | 结果标题可聚焦，加载状态 role=status | `public/app.php` | 低 | P1 | 屏幕阅读器可感知加载和结果 | 已完成 |
| EV-010 | 统一错误码 | API 契约 | 客户端只能解析中文 message | Douyin_TikTok_Download_API REST | 增加稳定 code、retryable、字段错误 | `Response.php`、`public/api/v1.php`、JS | 中 | P1 | 同一错误跨 Web/桌面返回同 code | 已完成（v1.3.0） |
| EV-011 | 客户端消费 Retry-After | Web/桌面 | 服务端给出等待值，客户端仍立即重试 | yt-dlp/MeTube 重试策略 | 读取 429 头并做上限退避 | `public/app.php`、`packages/parsers/src/http.js` | 中 | P1 | 429 后不立即打满限额，提示剩余等待 | 已完成（网页版，v1.3.0） |
| EV-012 | 解析总时间预算 | 稳定性 | 多级重定向/回退可能占满 worker | yt-dlp 网络超时分层 | 传播 request deadline，统一剩余预算 | `HttpClient.php`、`BaseParser.php` | 中 | P1 | 单次解析超过预算进入受控失败 | 计划 |
| EV-013 | ParseCache 单飞 | 性能 | 同 URL 并发 miss 会同时回源 | MeTube 队列/缓存思想 | 文件/Redis 锁 + 短等待，防 cache stampede | `ParseCache.php`、`VideoParser.php` | 中 | P1 | 并发同 key 只有一个上游请求 | 计划 |
| EV-014 | PHP/JS 跨语言 fixture | 架构 | 双解析器结果一致性靠手写断言 | yt-dlp extractor tests | 为 12 平台保存脱敏 fixture，双端对比字段 | `tests/fixtures`、JS/PHP tests | 中 | P1 | fixture 在 PHP/JS 两端输出等价契约 | 计划 |
| EV-015 | 平台能力矩阵 | 文档/产品 | README 分散描述单条/主页/直播能力 | Streamlink/gallery-dl 文档组织 | 生成平台 × 能力矩阵，标注 Cookie/sidecar | `README.md`、`docs/CAPABILITY_MATRIX.md` | 低 | P1 | 新平台变更只需更新一处矩阵 | 计划 |
| EV-016 | 抖音搜索桌面对齐 | 解析/发现 | PHP 支持，JS 明确拒绝 | Douyin_TikTok_Download_API | 复用签名传输或在 UI 明确 Web-only | `packages/parsers/src/discovery.js`、桌面 UI | 中 | P1 | 能力矩阵和 UI 与实际行为一致 | 计划 |
| EV-017 | B站音视频字段 | API/下载 | DASH 视频轨与音乐轨分开，下载文案不够明确 | yt-dlp format selection | 增加 `audio_url`/轨道信息和合并提示 | `BilibiliParser.php`、JS、OpenAPI | 中 | P1 | B站结果显式标注视频轨/音频轨 | 计划 |
| EV-018 | 失败分类与可重试标记 | 队列 | 所有失败都显示同一字符串 | MeTube completed/failed 状态 | 增加 invalid/auth/rate-limit/upstream/timeout 分类 | Web/桌面队列 | 中 | P1 | 仅 retryable 项显示重试；提示可行动 | 计划 |
| EV-019 | 批量队列持久化 | Web | 刷新后批量队列丢失 | MeTube 状态目录 | 本地版本化保存待处理项，不保存 Cookie | `workbench.js`、`public/app.php` | 中 | P2 | 刷新可恢复未完成任务，容量有界 | 已完成（v1.3.0） |
| EV-020 | 可配置并发数 | Web/桌面 | 并发固定 3 路 | MeTube `MAX_CONCURRENT_DOWNLOADS` | 设置页提供 1-6 上限并按平台限流 | Web/桌面设置 | 中 | P2 | 修改后队列按配置执行并不突破硬上限 | 已完成（网页版，v1.3.0） |
| EV-021 | 下载文件名模板 | 下载体验 | 当前主要使用标题 + 固定扩展名 | gallery-dl/MeTube 模板 | 支持安全的标题、作者、日期模板 | Web/桌面 ZIP/下载 | 中 | P2 | 路径穿越和控制字符被清理 | 计划 |
| EV-022 | 断点/校验下载任务 | 下载器 | 代理支持 Range，但没有任务状态与校验 | aria2 | 先做分片状态、SHA256、失败续传 | Electron 本地下载器 | 高 | P2 | 中断后可恢复，完成后校验一致 | 计划 |
| EV-023 | 外部播放器打开流 | 直播 | 当前只复制地址和手工设置 Referer | Streamlink | Windows 注册 VLC/PotPlayer 命令或复制配置 | `apps/app-shell` | 中 | P2 | 选择播放器能打开正确流和 Referer 说明 | 计划 |
| EV-024 | 更新 manifest 签名 | 发布 | `signExecutable=false`，SHA256 不等于认证 | electron-builder 近期 publisherName/版本绑定 | 配置证书或签名服务，保留 CI 证据 | `package.json`、release workflow | 高 | P0 | 旧客户端验证签名并拒绝篡改包 | 阻塞 |
| EV-025 | 更新安装回滚 | 发布 | 未做旧版本安装失败回滚演练 | electron-updater | 记录 pending/update 状态并提供回滚路径 | Electron main/update channel | 高 | P1 | 模拟安装失败后仍能启动旧版本 | 计划 |
| EV-026 | API 路由集成测试 | 测试 | 当前主要是源码断言和离线单测 | Douyin API/MeTube CI | 启动内置服务器，对 health/platforms/invalid input 真实请求 | `tests/Integration`、CI | 中 | P1 | CI 自动覆盖状态码、头和 JSON 契约 | 计划 |
| EV-027 | 子路径部署测试 | 测试/部署 | HLS、PWA、静态资源在子路径缺真实回归 | MeTube URL_PREFIX、Workbox | 用 PHP 内置/反代 fixture 验证 `/subdir` | `tests/evolution_browser.py`、CI | 中 | P1 | 页面、API、媒体、manifest 均不丢前缀 | 计划 |
| EV-028 | CodeQL/SCA 门禁 | 安全/CI | 当前有 hygiene，无依赖漏洞扫描 | Douyin API CodeQL、Electron 生态 | 加 CodeQL、Composer audit、npm audit 允许列表 | `.github/workflows/` | 中 | P1 | 高危依赖/注入问题阻断 PR | 计划 |
| EV-029 | 依赖更新矩阵 | 工程化 | PHP/Node 依赖更新分散手工处理 | yt-dlp/Streamlink Dependabot | 配置 Dependabot/Renovate 并按周汇总 | `.github/dependabot.yml` | 低 | P2 | 更新 PR 自动带测试结果 | 计划 |
| EV-030 | 结构化日志与指标 | 可观测性 | 日志为文本，缺解析耗时/命中率/上游状态 | Streamlink/成熟 API | JSON 行日志、request_id、耗时、平台、错误码 | `Logger.php`、解析服务 | 中 | P2 | 不含 Cookie/Token，能聚合成功率 | 计划 |
| EV-031 | 更新检查客户端缓存 | Web/PWA | Service Worker 仍靠手工版本字符串 | Workbox | 构建注入版本并显示“新版本可用” | `service-worker.js`、build script | 中 | P2 | 新资源可回源，旧页面提示更新 | 计划 |
| EV-032 | Web UI 模块化 | 架构 | `public/app.php` 内嵌约 2000 行脚本 | Vite/Cobalt Web monorepo | 保持功能不变，拆 API/storage/renderers | `src/web`、构建配置 | 高 | P2 | 单模块可测试，构建产物可缓存 | 计划 |
| EV-033 | 统一安全 URL 工具 | 安全/维护 | PHP/Electron/JS 各自解析 URL | Electron/MeTube SSRF 修复思路 | 生成共享域名/协议/文件名规则 | `config`、JS、Electron | 中 | P1 | 白名单和校验规则跨运行时一致 | 计划 |
| EV-034 | Cookie 加密存储 | 隐私 | Web Cookie 在 localStorage；桌面依赖 partition | Electron safeStorage/本地优先产品 | 桌面使用 safeStorage；Web 明确浏览器边界 | Web/桌面设置 | 中 | P1 | Cookie 不进入日志、备份、URL | 部分完成：API Key 已改为 HttpOnly 媒体 Cookie（v1.3.0） |
| EV-035 | 国际化基础层 | UX/生态 | UI 硬编码简体中文，TikTok 面向国际平台 | Seal/Media Downloader 多语言 | 抽 `t()` 与 zh-CN 字典，先不改默认语言 | Web/桌面 | 高 | P3 | 文案集中管理，默认界面不变 | 计划 |
| EV-036 | 发布脚本与证据包 | DevOps | 版本、构建、校验和、Release 仍多步手工 | electron-builder/yt-dlp release cadence | 统一检查版本、构建、manifest、notes、GitHub Release | `scripts/release.mjs` | 中 | P1 | 一个命令生成可审计发布目录 | 计划 |

## 计划配额核对

- 核心正确性/功能：EV-001、010-018、023，共 12 项。
- 新增实用能力：EV-019-023，共 5 项。
- UI/UX/可访问性：EV-009、015、020、021、023、035，共 6 项。
- 架构/性能：EV-003、004、005、013、014、019、020、022、030、032、033，共 11 项。
- 安全/发布/工程：EV-002、006-008、024-029、031、034、036，共 13 项。

合计：36 个独立升级点，满足不少于 30 项的要求。路线图不把同一个视觉改色拆成多项；每项都有单独的代码边界和验收条件。
