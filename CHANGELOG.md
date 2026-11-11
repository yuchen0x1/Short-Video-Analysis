# 更新日志

## 1.1.7

### 修复

- 补齐媒体代理默认白名单：新增小红书 `xhscdn.com`、快手 `kwaicdn.com`/`kwimgs.com`/`yximgs.com`、微博 `weibocdn.com`/`sinaimg.cn`、视频号 `finder.video.qq.com`/`qpic.cn`、最右 `izuiyou.com`/`xiaochuankeji.cn` 及 `tiktok.com`/`douyin.com`。此前这些平台解析成功但网页端播放/下载会被代理 403。白名单收敛到 `config/media_domains.php` 单一来源，并新增 PHPUnit 用例强制与 Electron、Android 三处保持同步。
- 单条解析结果统一归一化：PHP 侧现在与 JS 解析器一致，`platform`、`media_type`、`images`、`music` 字段必定存在，协议相对地址自动补全，图集缺少主地址时回落到首图。
- `/api/v1/parse` 新增 `force=1`（或 `refresh=true`）跳过解析缓存；网页播放失败后的自动重取改为强制刷新，不再拿回同一个已失效地址。
- 修复主题「跟随系统」失效：主题持久化只在用户手动切换时写入，系统明暗变化重新生效；`<head>` 内联预置主题，消除深色用户首屏白色闪烁；`theme-color` 随主题更新。
- 修复 `DiscoveryBehaviorTest` 针对抖音搜索的过时断言（v1.1.6 已支持抖音搜索），并把 `.phpunit.result.cache` 从版本库移除。
- `a_bogus_server.test.mjs` 的 2 个失败用例：测试硬编码 64KB 上限、服务端实际为 320KB。改为由 `/health` 上报真实 `max_body_bytes`，测试据此断言。
- Service Worker 缓存名升级到 `v2`；静态资源改为 stale-while-revalidate，导航失败时同时回落到 `./` 与当前请求的缓存。

### 新增

- `/api/v1/update` 增加 900 秒服务端缓存（`UPDATE_CACHE_TTL`），避免公共实例反复消耗 GitHub 匿名 API 配额；传 `force=1` 可绕过。
- `/api/v1/health` 增加 `mbstring`、`dom` 检查与 `a_bogus` sidecar 探活（`sidecar.reachable`、`warnings`）；sidecar 未启动时返回 `degraded` 但不阻塞整体就绪。
- 新增 GitHub Actions 质量门禁 `.github/workflows/quality.yml`：PHP lint + PHPUnit + smoke、两个 JS 契约测试、a_bogus sidecar 测试、桌面/移动端构建、仓库卫生检查。
- 移动端 toast 补充 `role="status"` 与 `aria-live="polite"`。

### 部署与文档

- Docker 镜像补齐 PHP `curl`、`dom`、`mbstring`、`zip` 扩展，Composer 平台要求与运行环境保持一致。
- README、移动端构建说明和 OpenAPI 文档同步到 `1.1.7`，补充 Android 本地验证与解析强制刷新参数。

## 1.1.6

### 解析与发现

- 新增抖音关键词搜索：`/api/v1/search` 支持 `platform=douyin`，走网页公开搜索接口并复用 a_bogus 签名会话，风控时返回受控错误；发现页抖音搜索结果可一键解析。

### 界面与体验

- 新增本地收藏夹：解析结果、批量结果与历史记录均可星标收藏，支持一键重新解析、取消收藏与导出 CSV。
- 批量结果新增「全部打包 ZIP」一键下载全部成功项，新增「重试失败项」断点重跑，无需整批重来。
- 新增全局快捷键：`Ctrl/Cmd+Enter` 触发当前模式解析，`Ctrl/Cmd+K` 聚焦输入框。
- 主题支持跟随系统明暗切换（手动切换后以手动选择为准）。
- 新增 PWA 安装支持：Web App Manifest、Service Worker 离线页面壳、192/512 应用图标与顶部「安装应用」入口。

### 修复

- 修正收藏时媒体链接取值错误（历史记录收藏丢失媒体地址、结果区收藏误存分享链接）。
- 修正批量模式下 `Ctrl+Enter` 触发单条解析的问题，现正确触发批量处理。
- 修正 PHP 内置服务器下 `.webmanifest` MIME 缺失导致 PWA 安装失败的问题。
- 修正抖音搜索分页 `has_more` 改用接口返回字段，末页不再误报受限。
- 修正 Service Worker 导航缓存未校验响应状态，错误页不再被缓存。
- 修正批量重试在批量运行中可被并发触发的问题，并补齐完成提示。

### 文档

- README 与 OpenAPI 同步抖音搜索支持、收藏夹、批量打包与 PWA 能力说明。

## 1.1.5

### 解析与媒体

- 修复抖音 Argus 网关参数与同源指纹 Cookie，提升详情接口兼容性。
- 修复抖音媒体代理的域名白名单、双栈 DNS 选择和 3xx 转发问题，恢复视频与 Range 播放。
- 兼容小红书 EF4/EF5/EF6/EF7 视频分档，优先选择最高分辨率与码率。
- 兼容快手 `/f/` 分享短链、二次跳转及新式视频 ID。
- 兼容 TikTok `statsV2` 数据结构，正确读取点赞数。

### 更新与安全

- GitHub Releases 更新检查增加 Atom 与 expanded assets 回退，降低 API 限流导致的更新检测失败。
- 媒体代理继续执行域名白名单、公网 DNS 校验、逐跳重定向校验和独立限流。

### 界面与构建

- 完成网页版、Windows 桌面端和 Android 端科技感 UI、布局与主题升级。
- 同步全端版本号至 1.1.5，Android `versionCode` 升至 6。

## 1.1.4

### 界面

- 全端更换应用图标：新版为视频播放与数据分析组合图案（蓝紫渐变 + 橙色播放键），桌面端 ico/png、Android 传统与自适应启动器图标（圆形裁切 + 安全区前景 + 白色背景层）全部同步更新。

## 1.1.3

### 修复与改进

- 在线更新渠道由 Gitee 切换到 GitHub Releases（配合仓库迁移）：电脑版更新检查改读 GitHub API，并保留 DNS 安全校验、超时与响应大小限制；检查失败时给出可读的中文错误提示，不再出现底层网络错误原文。
- 移动端「检查更新」同步切换到 GitHub Release（`api.github.com`），更新包下载链接指向 GitHub Release 附件；自定义站点 API（`VITE_UPDATE_API_URL`）仍可复用。
- 修复电脑版「关于」页更新日志面板直接显示 Markdown 原文的问题：现按标题、列表、引用渲染排版，并保持整体转义防注入。

## 1.1.2

### 修复与发布

- 修复发现页缩略图加载：补充 B 站与抖音图片 CDN 代理域名，并在代理失败时提供一次原图回退及占位图。
- GitHub Release 附带 Windows NSIS 安装包、portable 便携版和 Android 测试签名 APK。
- Android 测试签名 APK 不能覆盖安装旧版；继续使用需先卸载旧版。应用内自动更新渠道仍为 Gitee。

## 1.1.0

### 新增平台与直播

- 新增 TikTok 国际版解析（第 12 平台）：单条视频 / 图集 / 背景音乐，三级回退（embed 页 → 页面水合数据 → 移动端 feed 接口）全程无需签名；`vm./vt.tiktok.com` 短链自动跟随。部署环境需可访问 TikTok。
- 新增直播解析 `/api/v1/live` 与三端「直播解析」模式，覆盖四平台：**抖音直播**（webcast enter 接口）、**B站直播**（getRoomPlayInfo v2 多画质）、**虎牙直播**（页面直取，FLV 按 CDN 线去重）、**斗鱼直播**（ub98484234 签名 + FLV 五档画质）。返回主播、标题、人气与 flv/hls 拉流地址，支持播放器直开。
- 签名边车（`scripts/a_bogus_server.js`）新增斗鱼 `/douyu` 签名端点，内置 Node crypto 等价的 CryptoJS.MD5 实现；共享 JS 解析器内嵌纯 JS MD5，桌面/移动端离线完成斗鱼签名。

### 新增能力

- 新增「发现」能力：`/api/v1/hot`（B站综合热门 + 抖音热点榜）、`/api/v1/search`（B站搜索）。网页版、电脑版、移动端均提供「发现」入口，条目可一键解析。合规边界：仅响应用户主动查询、按需分页、不落库。
- 新增 B站公开评论查看：`/api/v1/comments` 与三端评论区（按需分页，仅查看不保存）。
- 新增抖音合集解析 `/api/v1/collection` 与三端「合集解析」模式；主页/合集支持「加载全部」与全部下载队列（3 路并发 + 进度）。
- 新增 CSV 导出：最近记录、批量结果、主页/合集作品列表（UTF-8 BOM，Excel 可直接打开）。
- 媒体代理支持 m3u8 播放列表改写：分片、子列表与密钥 URI 全部经由代理逐跳校验转发，带防盗链的 HLS 流可直接播放（B站直播实测）。

### 修复与改进

- 修复主页解析入口在 PHP 8.2 下恒被误判的存量 bug（`#` 定界符正则被字符类内 `#` 截断）；修复网页版主页流程 `clearError` 未定义的 ReferenceError。
- 修复抖音直播间/合集链接被误当单条视频 ID 解析的问题。
- `HttpClient::request` 支持单次超时覆盖；TikTok 多路径在不可达网络下约 7 秒返回受控错误。
- 媒体代理白名单补充 `huya.com`、`msstatic.com`、`douyucdn2.cn`、`douyucdn.cn`。
- 测试规模扩充：PHPUnit 83 例、共享解析器离线契约 51 例（新增直播 7 例、发现 6 例、合集 4 例、TikTok 5 例）。

### 已知限制

- TikTok 真实数据验证与主页批量、抖音搜索/评论（需登录态）、快手直播（需 did Cookie）暂未支持；详见 `upgrade-report/08-同类项目功能差距分析.md`。

## 1.0.0（上一版本）

- 版本体系归一化：项目重新以 `1.0.0` 作为初始版本发布，网页版、电脑版（Electron）、Android 端与 API 的版本号全部统一。
- 本版本整合了此前 2.x 系列的全部有效成果，包括：11 平台共享本地解析器、背景音乐下载、小红书图文图集解析、本地媒体代理、国内 Gitee 在线更新通道、安全加固与三端界面升级。
- 在线更新源仅保留国内 Gitee Release；CI/CD 迁移为 Gitee Go 流水线。
- **升级注意**：版本重置后 1.0.0 低于旧版 2.4.0，旧客户端不会收到自动更新；已安装 2.x 的用户请卸载后手动安装本版本（Android 需先卸载，`versionCode` 已重置为 1）。

### 历史版本（2.x 系列，成果已全部整合进 1.0.0）

## 2.4.0

- 新增背景音乐下载：抖音解析结果带出音乐播放地址，网页版、电脑版与手机版在解析完成后展示「下载音乐」入口。
- 补齐小红书图文图集解析：无视频流的图文笔记返回 `media_type=image` 与完整图片列表，网页/桌面/移动端按图集逐张下载；PHP 与共享 JS 解析器同步支持。
- 修复小红书 `xhslink.com` 分享短链跳转的 `/discovery/item/` 页面解析失败：兼容该页面的状态结构、`nickName`/`masterUrl` 驼峰字段和按编码分组的视频流，短链主路径恢复可用（真实链接实测通过）。
- 媒体代理域名白名单补充 `douyinstatic.com`，修复抖音背景音乐（`*.douyinstatic.com/obj/ies-music/*.mp3`）经代理下载被拒绝的问题。
- 电脑版 Electron 从 37 升级到 44（37 已超出官方支持窗口）。
- 修复限流器单测因文件缓存跨运行残留导致的偶发失败。
- 网站、Windows 和 Android 在线更新源统一为国内 Gitee，移除 GitHub API、GitHub updater provider 和自动回退路径。
- 修复西瓜视频当前公开页面的加密媒体地址解析，并保留旧 API、ROUTER_DATA、SSR_HYDRATED_DATA 多级后备。
- 修复微视旧 JSON 接口 404 后无法解析的问题，新增公开分享页 `window.Vise.initState` 后备。
- 验证皮皮搞笑当前 `h5.pipigx.com` 分享链接仍可通过公开接口解析。
- Windows 打包改用本地 Electron 发行目录，避免因国外下载链路超时而失败。
- PHP 健康检查新增 OpenSSL 运行依赖。

## 2.3.2

### 界面升级

- 网页版首屏增加支持平台、优先解析方式和批量上限信息，输入区补充解析能力提示。
- 电脑版输入面板增加版本标识、能力提示和轻量视觉层次，保持原有解析入口与导航不变。
- 手机版增加版本标识、能力概览和本地处理提示，优化首屏信息密度与按钮反馈。
- 三端统一减动效支持，减少系统开启“减少动画”时的视觉干扰。

## 2.3.1

### 安全与部署

- 电脑版渲染进程 HTTP 桥增加平台域名白名单，重定向后的最终地址同样校验。
- Docker 镜像自动启动抖音 `a_bogus` 签名服务，修复容器部署下抖音解析必败。
- `php -S` 启动入口配置多 worker，避免慢解析阻塞全部请求。
- 媒体代理增加独立限流、API Key 校验和 500MB 单次转发上限；Android 端同步加上限并修正响应状态行。

### 解析与稳定性

- 修复解析缓存未命中时产生永不过期 null 文件的磁盘泄漏。
- RateLimiter 消除重复存储创建逻辑，自定义限额工厂复用连接。
- 删除重复解析基类与无消费者的 parser 配置。
- `/api/v1/health` 版本号改为从 Composer 安装清单读取。
- 解析结果序列化失败时返回规范 JSON 错误。

### 前端与测试

- 网页版主页解析、图集展示和视频源刷新合并到单一状态机，删除 `window.fetch` 覆写和事件拦截补丁。
- 清理约 107 行被覆盖的历史 CSS 主题层。
- 新增核心行为测试；CI 自动运行 PHP lint、PHPUnit、smoke 和共享解析包契约测试。
- 修正 README、环境变量示例和抖音签名服务启动说明。

## 2.3.0

- 手机版、电脑版统一切换到共享 JS 解析器包（`packages/parsers`）：11 个平台全部本地解析，不再依赖任何服务器，流量/任意 WiFi 随处可用。
- 电脑版移除内置 PHP 后端与运行时：安装包体积 112.7MB → 88.5MB，启动更快，关闭后无后台残留进程。
- 新增本地媒体代理：App 内 127.0.0.1 迷你代理注入 Referer/UA 绕过防盗链，支持 Range 边下边播（手机版原生实现、电脑版主进程实现）。
- 手机版新增微博 Cookie 设置（关于页），保留本地历史、主页分页与批量下载。
- 电脑版关于页新增微博 Cookie 设置与动态版本号。
- 抖音 a_bogus 签名在客户端本地计算（不再依赖服务器 Node.js）。

## 2.2.3

- 版本递增，用于验证在线更新链路：已安装上一版（2.2.2）的客户端可自动检测到 2.2.3，下载完成后重启即完成安装。

## 2.2.2

- 修复在线更新产物名不一致问题：安装包与 `latest.yml` 完全对应，客户端可正确拉取并在线更新。
- 客户端新增“关于 / 更新”页：查看版本、检查更新与更新日志（同步更新日志正文）。
- 清理旧命名安装包，发布时以 `qushuiyin-desktop-client-setup-{版本}.exe` 为准。

## 2.2.1

- 电脑端新增“关于 / 更新”页：查看版本、检查更新与更新日志。
- 接入自动更新（electron-updater）：仓库发布新版本后，客户端可在线下载并安装。
- 统一界面：去掉英文装饰标签，移除应用默认英文菜单栏。
- 修复：桌面端启动黑屏（改用相对资源路径）、安装版与便携版产物互相覆盖。
- 手机端新增 release 签名与打包说明（`apps/mobile/SIGNING.md`）。

> 后续每次发新版，请在本文件顶部追加新版本条目，并在仓库对应 Release 中附带安装包与 `latest.yml`，客户端即可自动检测到更新。
