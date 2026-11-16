# 开源竞品与技术对标

研究日期：2026-11-16
研究方式：GitHub MCP 查询仓库主页、README、许可证、近 90 天 commits/releases、Issues/PR；重点项目读取 README 和公开核心源码文件。没有把 README 宣传语当作本项目已验证能力，也没有复制外部代码。

## 1. 研究项目清单

本轮实际研究 15 个开源项目，覆盖下载器、直播流、图集、Web UI、桌面壳、更新与下载基础设施：

| 项目 | 许可证 | 最近可见活动 | 可借鉴方向 |
| --- | --- | --- | --- |
| [yt-dlp](https://github.com/yt-dlp/yt-dlp) | Unlicense | 2026-11-15 commits；2026.10.26 release | 大规模站点适配、持续修复、格式选择 |
| [MeTube](https://github.com/alexta69/metube) | AGPL-3.0 | 2026-11-14 release；安全修复 commit | 自托管 Web UI、队列、订阅、目录隔离 |
| [Seal](https://github.com/JunkFood02/Seal) | GPL-3.0 | 2026-09 有 commit；最近稳定 release 较早 | 移动端下载 UX、格式/队列交互（仅作历史参考） |
| [Streamlink](https://github.com/streamlink/streamlink) | BSD-2-Clause | 2026-11-14 仍在更新；8.6.3 release | 插件式直播源、播放器交接 |
| [gallery-dl](https://github.com/mikf/gallery-dl) | GPL-2.0 | 2026-11-14 v1.32.20 | 图集/目录下载、配置化命名 |
| [lux](https://github.com/iawia002/lux) | MIT | 2024-05 后 release 较少 | 多站点 CLI 结构、Go 性能取舍 |
| [you-get](https://github.com/soimort/you-get) | MIT | 2025-01 release；近期活动较少 | 轻量多媒体解析边界 |
| [cobalt](https://github.com/imputnet/cobalt) | AGPL-3.0 | 公开仓库提供 API/Web monorepo | 极简粘贴即下载、隐私和无广告 UX |
| [Douyin_TikTok_Download_API](https://github.com/Evil0ctal/Douyin_TikTok_Download_API) | Apache-2.0 | 2026-11-09 commit；v5.1.11 | 抖音/TikTok/B站数据 API、MCP、归档和异步任务 |
| [MediaGo](https://github.com/mediago-dev/mediago) | MIT | 2026-11-13 UI/发布修复；3.6 beta | 桌面 + Docker、HLS 抓取、安装体验 |
| [Media Downloader](https://github.com/mhogomchungu/media-downloader) | GPL-2.0 | 2026-11-11 commits；5.6.8 release | 扩展式下载后端、预设、并发、国际化 |
| [OmniGet](https://github.com/tonhowtf/omniget) | GPL-3.0 | 2026-11-13 签名工作流与文档 | 本地服务、敏感操作确认、桌面工具箱 |
| [aria2](https://github.com/aria2/aria2) | GPL-2.0 | release 较旧但实现成熟 | 分段、多源、校验和恢复 |
| [Electron](https://github.com/electron/electron) | MIT | 2026-11-13 Chromium/Node 更新 | 桌面安全边界与运行时升级 |
| [electron-builder](https://github.com/electron-userland/electron-builder) | MIT | 2026-11-11 更新 Windows 更新校验 | 安装包、更新 manifest、签名与版本绑定 |

## 2. 关键对标结论

| 维度 | 对方证据 | 本项目现状 | 差距与决定 |
| --- | --- | --- | --- |
| 站点覆盖与持续修复 | yt-dlp 近 90 天持续修复 YouTube 片段、客户端策略和依赖；gallery-dl 近月连续发版 | 本项目固定 12 平台，依赖公开接口与页面结构 | 不盲目扩站；建立平台适配健康度和 fixture，优先提高已有 12 平台稳定性 |
| Web 队列 | MeTube 明确支持并发队列、播放列表/频道、订阅、预设和目录 | 本项目批量 20 条、3 路并发、重试和 ZIP，刷新后不持久化队列 | 借鉴队列持久化、失败分类、并发配置；不引入频道订阅到当前范围，除非产品需求明确 |
| 极简体验 | cobalt 强调无广告、无跟踪、粘贴后直接得到文件 | 本项目已有粘贴、自动提链、播放、下载、复制、收藏 | 保持本地优先和无广告，减少不必要设置层级；可补更清晰的单步反馈 |
| 图集 | gallery-dl 通过 extractor/config 支持目录级图集和命名模板 | 本项目支持图集数组、逐张下载和 ZIP | 借鉴命名模板、下载状态与失败重试；保持当前浏览器端容量上限 |
| 直播 | Streamlink 以插件和播放器交接为核心 | 本项目支持 4 个直播平台并返回多画质流 | 借鉴流格式能力矩阵、播放器打开协议和直播状态缓存 |
| 领域 API | Douyin_TikTok_Download_API 将 REST、MCP、归档、异步任务、评论/直播/热榜统一 | 本项目已有 REST、发现、评论、直播，但无持久归档与 MCP | 优先统一错误码和分页契约；MCP/数据库属于后续可选方向，不直接引入重基础设施 |
| 桌面体验 | MediaGo、Media Downloader 提供桌面批量、预设、HLS/外部后端扩展 | Electron 客户端已有本地解析、微博登录、更新和媒体代理 | 借鉴下载任务中心、预设和外部播放器；不改为多后端框架 |
| 更新安全 | electron-builder 近期加强 NSIS manifest 版本绑定、publisherName 与安装器校验 | 本项目生成 latest.yml、校验清单，但 `signExecutable=false` | P0：加入签名配置和发布证据；在无证书环境不宣称完成 |
| 下载可靠性 | aria2 具备分段、多源、校验和、恢复；MeTube 有状态恢复与目录隔离安全修复 | 本项目媒体代理支持 Range 和大小限制，但无断点任务状态 | P1：先做任务状态/失败原因，再评估分段下载，避免把短直链解析器变成重型下载器 |
| 安全 | MeTube 2026-11-14 修复 STATE_DIR 越界与恢复记录选项绕过；OmniGet 关注敏感操作确认 | 本项目已有 SSRF、白名单、Cookie 不入备份、API Key、IPC sandbox | 借鉴“恢复数据也要重新授权/校验”的原则；补 IPC sender 校验和更新签名，本轮已完成前者 |
| 工程化 | yt-dlp、Streamlink、Electron 生态持续 CI/依赖更新；MediaGo 有签名集成修复 | 本项目 GitHub Actions 已有 PHP/JS/桌面构建与卫生检查 | 加 CodeQL/SCA、API 集成矩阵、真实 Release 安装回归 |

## 3. Issues/PR 与核心源码抽样

查询时间为 2026-11-16（UTC）。每个项目均查询了最新公开 Issue 和 PR；下表保留一条最新记录作为活动度证据，数量来自 GitHub 返回的公开计数。Issue/PR 标题只用于判断维护重点，不代表本项目采用其实现。

| 项目 | Open Issue 总数 | 最新 Issue（更新时间） | 最新 PR（更新时间） | 核心源码抽样 |
| --- | ---: | --- | --- | --- |
| yt-dlp | 2,091 | #17932，扩展名安全校验（2026-11-16） | #15525，release timestamp（2026-11-16） | `yt_dlp/YoutubeDL.py`：`YoutubeDL`、下载器和 post-process 主流程 |
| MeTube | 4 | #914，多格式下载（2026-10-10） | #1121，保留原始音频（2026-11-06） | `app/ytdl.py`：线程池、队列持久化、重试重新校验 |
| Seal | 714 | #2690，TikTok（2026-11-16） | #2519，伪 Premium APK 安全提醒（2026-11-16） | README/发布信息；仅作移动端历史参考 |
| Streamlink | 62 | #6940，YouTube 登录校验（2026-11-16） | #7204，拆分 changelog（2026-11-15） | `src/streamlink/plugin/plugin.py`：插件、流解析和 URL 解析 |
| gallery-dl | 1,004 | #9888，Redgifs（2026-11-16） | #8963，新增站点支持（2026-11-13） | `gallery_dl/extractor/common.py`：Extractor、items、config |
| lux | 529 | #1462，芒果TV下载失败（2026-11-14） | #1460，Homebrew 文档（2026-10-18） | README/发布信息；活动度较低 |
| you-get | 0 | 无公开 Issue | #498，移除全局变量（2026-11-16） | README/发布信息；活动度较低 |
| cobalt | 238 | #1566，Instagram 视频扩展名（2026-11-11） | #1625，解析 flashvars metadata（2026-11-16） | `api/src/cobalt.js` 目录；关注 API 分层 |
| Douyin_TikTok_Download_API | 2 | #805，视频地址 403（2026-11-02） | #828，依赖升级（2026-11-14） | README/公开 API 结构；关注异步任务和错误契约 |
| MediaGo | 6 | #823，列表选择框可辨识度（2026-11-15） | #824，繁体中文翻译（2026-11-15） | README/桌面与 HLS 入口说明 |
| Media Downloader | 13 | #821，UI 显示（2026-10-07） | #837，CMake 更新（2026-11-09） | README/后端适配说明 |
| OmniGet | 3 | #380，账号劫持安全事件（2026-11-15） | #374，移除插件和 Tools 区域（2026-09-27） | README/本地服务与敏感操作说明 |
| aria2 | 1,121 | #2411，Torrent poisoning（2026-11-11） | #2412，DHT 元数据防护（2026-11-12） | README/下载器设计；关注恢复、校验与安全 |
| Electron | 622 | #55399，文本框崩溃（2026-11-16） | #53807，offscreen HTML 拖放（2026-11-16） | README/运行时边界；本项目已抽样 Electron 主进程 |
| electron-builder | 16 | #6183，依赖面板（2026-11-16） | #10619，NSIS 意大利语（2026-11-16） | README/发布链路；NSIS 源码路径在当前 ref 下不可直接读取 |

核心源码抽样的目的，是确认竞品的实际扩展点和状态边界：yt-dlp/MeTube 偏下载生命周期，Streamlink/gallery-dl 偏插件/Extractor 契约；本项目因此选择缓存、队列语义、错误契约和 IPC 边界等可落地升级，没有复制外部实现。

## 4. 项目自身优势

- 对中文短视频平台（抖音、快手、B站、小红书、微博、视频号等）有明确的平台专用解析逻辑。
- Web 与 Windows 共用字段契约，支持 PWA 和本地桌面，不强依赖远端账号。
- 媒体代理在播放链路中同时处理 Referer、Range、HLS 和逐跳 SSRF 校验。
- 当前许可证为 AGPL-3.0-only，仓库已有 `OPEN_SOURCE_NOTICES.md`，没有直接移植 GPL/AGPL 代码。

## 5. 不采用的方案

- 不复制 yt-dlp、gallery-dl、MeTube 或 Cobalt 的实现代码；许可证和架构均不适合直接嵌入现有 PHP/原生 JS 双栈。
- 不恢复独立 Android/iOS 工程；这与当前项目范围冲突。
- 不为了数量引入 PostgreSQL、MCP 或转码集群。先把现有解析、代理、更新和测试链路做稳定。

## 6. 研究限制

- 本轮重点项目通过 GitHub API/MCP 读取公开仓库信息，并对 yt-dlp、MeTube、Streamlink、gallery-dl 读取了核心源码文件；没有克隆 15 个仓库逐文件阅读，深度实现结论仅用于方向判断，不声称已验证每个项目所有功能。
- 未对真实账号、付费服务、受限平台或真人登录态做操作。
- 竞品近期时间以 2026-11-16 查询结果为准；项目状态会继续变化。
