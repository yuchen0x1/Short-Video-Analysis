# v1.2 生态升级基线

审计日期为 2026-11-18，源码基线为 `9af2065`，最近正式版为 v1.1.13。产品名、仓库和 AGPL-3.0-only 许可沿用现有约定。GitHub 是唯一代码和正式发布渠道。

## 架构和功能清单

| 边界 | 入口 | 现有能力与数据 |
| --- | --- | --- |
| PHP 网页和 API | `public/index.php`、`public/app.php`、`public/api/v1.php` | 响应式界面、单条与批量解析、主页/合集、直播、搜索热榜评论、播放、下载、收藏和备份。API 含版本化契约、可选令牌、限流、媒体代理和健康检查。 |
| PHP 业务层 | `app/Services`、`app/Parsers`、`app/Utils` | 站点识别与解析、结果缓存、短链、HTTP 超时、日志和媒体 Range。Composer 锁定 Symfony 与 phpdotenv，抖音和斗鱼按业务使用本地 Node 签名服务。 |
| Windows | `apps/app-shell`、`packages/parsers` | Electron 隔离渲染层、JS 解析器、IPC 和本地媒体代理。支持主页/合集分页、直播地址、发现、评论、历史、图集 ZIP、微博会话。本地记录在 Electron profile，安装版和便携版共用版本规则。 |
| 持久化 | `public/workbench.js`、Electron WebStorage、`storage` | 网页收藏/历史保存在浏览器，已有备份合并和旧数据迁移测试。PHP 文件缓存与日志，无业务数据库。账号数据与分享链接按各入口处理。 |
| 分发与验证 | `.github/workflows`、Dockerfile、Composer、Vite、Electron Builder | 版本同时校验 PHP、JS、Electron、npm lock、OpenAPI。NSIS x64 安装、便携和更新清单，PHP 由用户部署。第三方声明见 [OPEN_SOURCE_NOTICES](../../OPEN_SOURCE_NOTICES.md)。 |

基线实跑通过 PHP 100 项测试（361 断言）、JS 56 项契约、Node 19 项回归及 PHP lint/smoke。BrowserSkill 打开 v1.1.13 网页，健康接口、签名服务和 PHP 8.3 依赖正常。上一轮 Windows 打包运行证据来自同一提交，真实 B 站短链和抖音解析均通过。该基线的 GitHub 质量、发布和 README 同步均有成功记录。

## 平台取舍

| 候选 | 结论 | 核心能力、系统权限与维护判断 |
| --- | --- | --- |
| Windows 11 x64 | A | 现有原生窗口、文件下载、NSIS 分发路径可直接迭代。普通用户操作，安装目录和代码签名需单独验证。 |
| Windows 10 x64 | B | 与 Win11 共用构建，需独立系统验收和运行时版本跟踪。无本机 Win10 设备，兼容性记录保留待验状态。 |
| Windows ARM64 | C | Electron 有对应架构，缺少真实设备以及稳定的同渠道资产和升级验收，维护成本高于当前覆盖价值。 |
| Linux、Ubuntu、Debian、Fedora、Arch Linux | C | PHP 网页可在满足依赖的服务器运行，Linux 用户可用浏览器访问。原生桌面包需要验证不同依赖、桌面集成、包格式和更新，超出现有产品范围。 |
| macOS Intel、macOS Apple Silicon | C | 共享解析架构具备迁移基础，桌面发行需额外构建原生架构、签名公证、安装升级验证和维护。现有 Web 覆盖访问。 |
| Android、Android 平板、折叠屏 | C | 用移动 Web 与 PWA 复用核心下载和解析。独立 APK 的后台任务、存储权限、设备验证、签名与分发不在维护范围。 |
| iOS、iPadOS | C | 浏览器可用。原生端需沙盒文件交接、证书、店审与独立设备测试，产品范围不包含独立客户端。 |
| 桌面 Web、移动 Web、响应式 Web、PWA | A | 已有 HTML/PHP、浏览器持久化及离线壳。桌面用鼠标键盘，窄屏保留触控路径。安装能力取决于浏览器，真实解析需要在线。 |
| Chrome、Edge、Firefox 扩展 | C | 分享入口可扩展，跨站权限和商店发布要单独维护，当前网页粘贴流程覆盖主要需求。三个商店的审查和更新未纳入交付。 |
| Docker、NAS、私有化部署、云服务器 | A/D | Docker 和常规 PHP 私有化部署为 A。NAS 支持标准容器时可沿用，专用 NAS 套件为 C。GitHub Pages 只能托管静态文档，直接运行 PHP 的路径为 D。生产主机、域名和凭证由部署方提供。 |
| CLI | C | Composer 脚本是开发工具，API 可供命令行用户访问。新增独立 CLI 会增加打包支持面与发布成本。 |

## 初始发现

- `main` 未保护且无规则集，正式 Release 可更改。仓库公开，开启 Issues、Wiki、密钥扫描及推送保护，默认 Actions 权限为只读。发布和质量任务并发，发布没有依赖完整质量检查。
- Windows 安装包没有 Authenticode 证书。v1.1.13 会自动下载并可能在退出时安装。校验和只能确认文件完整性，需要建立发布来源证明和有可信发布者时的安装边界。
- 客户端代码和 AGPL 对应源码公开。扫描看到生产归档含依赖 source map，以及 Electron 自带符号路径。优先收敛归档、运行开关和发布权限。凭据仍由浏览器或桌面 profile 持有，账号授权最终由平台服务处理。
- 仓库 Topics 含并未采用的 Laravel/Vue。已有发布说明、API 文档和 36 项路线图可沿用；需要增加问题模板、贡献规范、依赖更新、维护状态及实际发版门禁。
- 网页 PWA 更新立即接管，缺少用户确认。页面更新应保留链接输入和本地资料。
