# 项目分析、竞品研究与升级验收报告

本报告记录升级实现完成时的本地验收快照，随后用户授权将这些改动纳入 v1.1.9 发布；发布状态以 GitHub Releases 为准。

日期：2026-11-13（Asia/Shanghai）。基线：`main` / `a81616b`，工作区原本干净，唯一远程为 GitHub。全部改动在当前项目内；没有提交、推送、创建 Release 或部署。版本保留 1.1.8，发布记录标记为「未发布」。

## 执行与功能地图

- [x] 项目结构、入口、配置、解析服务、存储、Web 交互、测试与 CI 检查。
- [x] 至少五个相关项目的 README、目录、核心源代码和许可证核对。
- [x] 确认问题并选择兼容升级，完成代码与文档。
- [x] PHP、JS、HTTP、浏览器流程及两端前端构建验证。
- [x] 最终差异检查、风险与后续工作记录。

| 模块 | 原有真实能力 | 本次判断 |
| --- | --- | --- |
| PHP / `public/index.php` / `public/api/v1.php` | Web、旧版解析、v1 API、媒体代理、可选 Key、限流、缓存 | 旧版解析鉴权存在遗漏；缓存账户边界不足 |
| `app/Parsers` / `packages/parsers` | 12 平台注册，单条、图集、部分主页/合集/直播/发现 | 有实质实现；外部平台成功率不能由离线契约测试证明 |
| `public/app.php` | 播放、批量三路并发、历史、收藏、CSV、ZIP、主题、PWA | ZIP 存在确定的按钮错误；重试没有统一锁；记录没有可恢复备份 |
| Electron / Capacitor | 共享 JS 解析器、原生网络/下载桥和更新入口 | 保留架构；同步微博缓存修复并构建验证 |
| 数据 | 浏览器 localStorage、Symfony 文件/Redis 缓存 | 没有产品数据库迁移；文件缓存路径错误指向项目父目录 |
| 发布 | GitHub quality/release 工作流 | 本次只增加工作台回归检查，不执行远程工作流或发布 |

启动仍使用 README 的 PHP / Composer、Electron / Vite、Capacitor 流程。UI 保持原有青色、深色工作台布局；本轮新增侧栏资料管理区，修正浅色对比度、空状态、焦点和窄屏按钮。没有更换框架、引入产品运行依赖或增加服务端数据库。

## 开源研究：证据与应用

通过联网检索发现候选，再使用 GitHub 只读接口查看仓库元数据、完整目录树、README、许可证及以下源文件。维护时间是查询当时 GitHub `pushed_at`，不是对项目质量或线上可用性的保证。

| 项目 / 最近 push 日期 | 阅读的具体实现 | 许可证核对 | 本项目应用 |
| --- | --- | --- | --- |
| [yt-dlp](https://github.com/yt-dlp/yt-dlp) / 2026-11-04 | [downloader/common.py](https://github.com/yt-dlp/yt-dlp/blob/412e7f0a4b0588fe884a02b344ac66a06538475b/yt_dlp/downloader/common.py)：重试、文件大小边界、进度接口 | 根 LICENSE 为 Unlicense；依赖和分发产物需另行核对 | 打包体积边界、请求超时、明确进度；未引入 yt-dlp 或 FFmpeg |
| [cobalt](https://github.com/imputnet/cobalt) / 2026-04-06 | [queue.ts](https://github.com/imputnet/cobalt/blob/a636575b09de1fc55d9b8cd98cac88f5f2f16b42/web/src/lib/task-manager/queue.ts)：waiting 状态、任务 ID、流水线、失败反馈 | 根 AGPL-3.0；**Web 子目录为 CC-BY-NC-SA-4.0**，见 [Web README](https://github.com/imputnet/cobalt/blob/main/web/README.md)；不能当作普通 AGPL Web 代码移植 | 仅借鉴任务状态与恢复思路，独立编写队列逻辑，没有复制代码或视觉资产 |
| [Douyin_TikTok_Download_API](https://github.com/Evil0ctal/Douyin_TikTok_Download_API) / 2026-11-09 | [download_requests.py](https://github.com/Evil0ctal/Douyin_TikTok_Download_API/blob/3c045e11aecd58ab076562f1246931b9dd5d909b/src/dtk/services/download_requests.py)：REST/MCP 共用入口校验、目标识别、重复任务信息 | Apache-2.0 | 统一旧版/v1 鉴权边界；没有引入其 PostgreSQL、下载 sidecar 或身份池 |
| [DouK / TikTokDownloader](https://github.com/JoeanAmier/TikTokDownloader) / 2026-11-04 | [download.py](https://github.com/JoeanAmier/TikTokDownloader/blob/f766a6eff25b76fe4d737f10c27258e4da65df7d/src/downloader/download.py)：Semaphore、下载进度、任务分类 | GPL-3.0；README 明确提示签名算法不再维护 | 有界并发和进度；不复制签名实现，不因其功能列表扩大本项目宣称 |
| [Seal](https://github.com/JunkFood02/Seal) / 2026-09-25 | [BackupUtil.kt](https://github.com/JunkFood02/Seal/blob/7677f61a20fda4210e84215fef9e9b51d25daa47/app/src/main/java/com/junkfood/seal/database/backup/BackupUtil.kt)：结构化 JSON、记录导出、解码失败返回 Result | GPL-3.0 | 版本化本地备份、导入校验、明确失败反馈，独立实现浏览器存储适配 |

本次没有移植外部代码，不新增第三方代码归属声明。未来移植需按具体文件及其依赖重新核对，不能只看仓库根许可证。

| 对比维度 | 当前原有情况 | 优秀实践 / 差距 | 本轮升级 |
| --- | --- | --- | --- |
| 核心功能 | 已有批量、ZIP | 出错后入口不可用 | 修复 ZIP、停止项恢复、失败项重试 |
| UI | 深浅主题与侧栏 | 浅色面板仍覆盖深色渐变、空状态无样式 | 主题一致、搜索计数、空态与操作间距 |
| 用户体验 | 历史/收藏只读导出 | 缺少可恢复数据交换 | JSON 备份、校验、合并去重 |
| 架构 | 内联 JS 混合状态与视图 | 重试与主任务各自执行 | 提取无依赖队列、资料和安全导出模块 |
| 自动化 | 批量三路处理 | 重试可与新任务竞争 | 所有批量入口共用锁；有限并发 |
| 扩展 | 本地键无模式版本 | 数据恢复没有稳定格式 | v2 备份/存储、明确允许字段与大小限制 |

## 问题、优先级与实施结果

| 优先级 | 问题与收益 | 难度 / 风险 | 结果 |
| --- | --- | --- | --- |
| P0 | 旧版解析绕过可选 API Key | 低；开启鉴权时旧无 Key 调用现在返回 401 | 已修复，真实 HTTP GET/POST 回归 |
| P0 | 微博解析缓存可能返回另一凭据的结果 | 中；增加微博请求次数 | PHP/共享 JS 禁止微博解析缓存；公共缓存换命名空间，不读取旧可能受凭据影响的结果 |
| P0 | 缓存配置越出项目目录 | 低；缓存/限流状态会重新开始 | 改到项目 `storage/cache`；保留旧目录文件，不扫描、不搬迁、不删除 |
| P1 | ZIP 按钮读取不存在的 span，直接抛错 | 低 | 修复文字目标；真实 ZIP 文件 CRC 检查；同时修复单条 Ctrl+Enter 重复触发导致自我取消 |
| P1 | 批量单项/整批重试与新任务竞争，停止项被排除 | 中；仍保留三路并发和原解析接口 | 统一运行锁、AbortController 与输入状态；成功项保留，重试强制刷新 |
| P1 | 收藏/历史无恢复入口、存储失败可能打断解析 | 中；新增本地数据版本 | 备份恢复、搜索、旧键兼容、原子写入和明确错误 |
| P1 | CSV 对外部标题只做引号转义 | 低 | 公式/控制前缀加文本标记，保留 BOM/CSV 引号转义 |
| P2 | ZIP 大量并发且无总大小边界，同名冲突 | 中；串行可能更慢，超限需逐个下载 | 累计媒体 128 MiB、每文件 60 秒、流式读取上限和唯一序号文件名 |
| P2 | PWA 激活删除同源所有缓存 | 低 | 只清理本应用缓存前缀，新脚本加入 v3 外壳缓存 |
| P2 | Composer lock 与 manifest 不同步 | 低；无版本更新 | 官方 `composer update --lock` 刷新 hash/元数据，依赖版本与 commit 未变 |

CSV 处理依据 [OWASP CSV Injection](https://community.owasp.org/attacks/CSV_Injection)：引号不能独立阻止表格公式。这里防止直接导出时的公式前缀解释，不承诺所有表格软件二次保存/重开后的行为。

## 数据兼容与边界

- 新键 `watermark-library-v2` 将历史和收藏放在一次 `localStorage.setItem` 中写入，避免导入时只更新一个集合。读取旧 v1 键，首次写入才迁移；旧键不删除。
- 导入只接受本工具 v2 JSON，最多 4 MiB、每类最多 2000 条，写入同样检查可恢复备份大小；校验分享 URL、字段类型，丢弃未知字段，禁止凭据 URL。相同 `rawUrl` 保留已有记录。Cookie、API Key、其他浏览器配置不进入备份。
- 历史默认仍保留 20 条；导入更多历史后保留对应容量，上限 2000。收藏不自动淘汰。导入不是后台同步；多个标签页同时写入仍可能发生后写覆盖，应避免同时编辑。
- 损坏存储和配额失败会提示并阻止覆盖；解析成功不因此被改成解析失败。损坏原文不自动修复，保留用户排查/恢复机会。
- 批量停止仅停止前端后续调度和 fetch，已到达 PHP/上游的请求不保证立刻取消。刷新不保存批量队列，不宣称跨重启续传。
- ZIP 仍在浏览器内存中封装；128 MiB 是媒体数据上限，不是整个浏览器内存上限。图片扩展名仍沿用现有 jpg 约定；未新增转码。
- 微博 PHP 缓存完全关闭；其他带运行期 Cookie 的 PHP 请求也跳过公共缓存。共享 JS 微博包含宿主登录态，也关闭缓存。

## 重要文件清单

| 文件 | 变化 |
| --- | --- |
| `public/index.php` | 旧解析入口统一鉴权 |
| `app/Services/VideoParser.php` | 凭据缓存隔离、公共缓存命名空间 |
| `packages/parsers/src/index.js` | 桌面/移动端微博缓存隔离 |
| `config/cache.php`, `config/app.php` | 修正存储路径 |
| `public/workbench.js`（新增） | 资料模型、验证、搜索、队列、CSV 与限额读取 |
| `public/app.php` | 完整 UI 接入、ZIP、批量状态、存储错误与布局 |
| `public/service-worker.js` | 应用缓存作用域与新增脚本 |
| `tests/workbench.test.cjs`（新增） | 11 项离线行为回归 |
| `tests/Unit/CredentialCacheTest.php`（新增） | 账号隔离与公共缓存/强制刷新回归 |
| `tests/Unit/CoreBehaviorTest.php`, `tests/Unit/RegressionFixesTest.php` | 路径边界和缓存条件检查 |
| `packages/parsers/test/contract.mjs` | 切换微博凭据后重新请求 |
| `tests/evolution_browser.py`（新增） | 隔离 PHP 服务、真实 HTTP、Edge UI 和下载文件检查 |
| `.github/workflows/quality.yml` | 加入工作台 Node 测试 |
| `composer.lock` | hash、上游 phpdotenv 仓库重定向元数据、Composer plugin API 元数据 |
| README、CHANGELOG、发布说明、本报告 | 如实描述本地未发布能力 |

无删除文件。Playwright 仅安装在忽略目录 `.evolution/python`，未进入产品依赖；尝试过的 Node 浏览器工具也位于 `.evolution/browser`。测试使用独立浏览器上下文，不读取用户浏览器资料。

## 测试结果

| 检查 / 命令 | 状态 | 证据与限制 |
| --- | --- | --- |
| `composer install --no-interaction --no-progress --no-scripts` | PASS | 现有锁依赖可安装，无新增/更新包，生成 autoload；非空机器全新安装 |
| `composer validate --no-check-publish` | PASS | lock 同步；保留已有显式 version 的建议级警告 |
| `composer check-platform-reqs` | PASS | PHP 8.3.36；部分扩展要求由已装 Symfony polyfill 提供 |
| `php bin/lint.php` | PASS | app/config/public PHP 语法 |
| `php vendor/phpunit/phpunit/phpunit` | PASS | 98 tests / 356 assertions；基线为 95 / 340 |
| `php bin/smoke.php` | PASS | 注册、配置、TLS 与媒体地址策略等 |
| `node packages/parsers/test/contract.mjs` | PASS | 56 项离线契约，基线 55；使用夹具 |
| `node packages/update-checker/test/contract.mjs` | PASS | 更新检查器契约，非真实升级安装 |
| `node --test scripts/a_bogus_server.test.mjs` | PASS | 6 项签名服务边界测试 |
| `node --test tests/workbench.test.cjs` | PASS | 11 项：迁移、去重、损坏/恶意备份、配额、搜索、CSV、并发、停止、大小边界 |
| 两端 `npm exec -- vite build`（桌面加 `--base=./`） | PASS | Electron/Android Web renderer 均构建成功；移动端原有静态/动态 import 混用警告仍在 |
| `tests/evolution_browser.py` | PASS | 真实旧版 GET/POST、v1、媒体 401；正确 Key、首页/健康；Web 导入、搜索、收藏、刷新持久化；ZIP CRC 与 CSV；1440/768/390 宽度无溢出、主题切换、零未捕获 JS 错误 |
| Git diff whitespace | PASS | `git diff --check` |
| Composer 安全公告 | PASS | lock 刷新当时 Composer 返回无已知漏洞公告；不代表全面安全审计 |
| 12 平台真实联网解析、媒体完整下载 | PARTIAL | 本轮验证本地 API 边界和离线契约；浏览器批量/媒体用明确测试夹具，不据此宣称真实平台成功 |
| Windows 安装包、Android APK/真机、真实更新 | BLOCKED | 本轮未配置签名/真机发布验收，不执行发版；只验证前端构建 |
| Docker 全新环境、长期压力测试、Redis 实例 | PARTIAL | 未执行；不能由现有环境测试推断 |

浏览器插件缺失内部运行文件，初次旧 Python pip 出现 TLS 下载失败；最终使用已安装 Python 3.14 和项目内 Playwright、系统 Edge 完成测试。首次 UI 脚本对主题初始状态作了错误假设，修正为点击实际主题控制后全流程通过。Composer 初检 lock 不同步已修复。上述失败没有冒充通过。

复跑浏览器检查（本机 PowerShell）：

```powershell
$env:PYTHONPATH=(Join-Path (Get-Location) '.evolution\python')
$env:PYTHONIOENCODING='utf-8'
py -3.14 -u tests/evolution_browser.py
```

脚本自动选本地空闲端口、启动并清理 PHP 进程。测试凭据是临时夹具；日志、下载和截图位于 `.evolution/verification/`。文档截图见 [桌面工作台](screenshots/evolution-web-desktop.png)；截图取自实际运行页面，在线更新被明确测试响应替代，不作为真实更新成功证据。

## 后续工作与成熟度

1. **P1**：逐个平台用公开样本执行联网解析、完整媒体下载和失效刷新验收；登录态平台使用用户授权的测试账号。
2. **P1**：普通解析 HTTP 客户端的逐跳公网 DNS/响应大小防护需进一步统一；当前媒体代理已有较严格边界，但不能代表所有解析 HTTP 请求具有同等保证。
3. **P1**：开启 API Key 后，网页缺少可用的会话/媒体鉴权交互；API 客户端可用 Header 调用。需要专门设计短期媒体授权或服务器会话，避免把 Key 放进 URL。
4. **P2**：队列跨刷新持久化、IndexedDB 大资料库及多标签页并发事务；目前保留有界本地模型。
5. **P2**：图片真实 MIME/扩展名、超大 ZIP 流式落盘、PHP 单进程开发服务器与生产部署并发性能基准。
6. **P3**：按需插件化、原生端同步资料备份、下载任务持久化。语音转写/AI 分析不是当前去水印核心流程，不强行增加外部服务与成本。

使用证据等级评分，避免伪造产品百分比：1 = 仅有设计；2 = 已实现但缺少针对验证；3 = 本地自动验证；4 = 浏览器/跨层验证；5 = 真实生产和长期验收。本表只评价证据成熟度，不等于安全认证或功能覆盖率。

| 维度 | 等级 / 5 | 依据与上限 |
| --- | --- | --- |
| 功能完整度 | 4 | 本轮备份、搜索、批量、ZIP 跨层通过；全平台线上链路未验收 |
| UI/UX | 4 | 三档宽度、两种主题、真实交互与截图；原生端未真机验收 |
| 代码质量 | 3 | 回归和构建通过；主 Web 模板仍大，尚未完整模块化 |
| 性能 | 3 | 队列并发/流读取限制有测试；无生产压力与内存基准 |
| 安全性 | 3 | 鉴权/凭据缓存/CSV/路径边界有回归；解析 HTTP 防护仍需补齐 |
| 稳定性 | 4 | 错误恢复、存储失败、导入失败、下载 ZIP 验证；缺少真实源站与长时间运行 |
| 可维护性 | 3 | 抽出纯逻辑、CI、文档；未建立全端共用资料层 |
| 扩展性 | 3 | v2 数据模式、独立存储与队列逻辑；暂无持久任务/插件验收 |

本轮可审阅的本地升级完成；上述未验证项和长期工作没有标记为已经实现或生产就绪。
