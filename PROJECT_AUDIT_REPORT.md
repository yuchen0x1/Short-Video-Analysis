# 项目全方位审计与升级报告

审计日期：2026-11-09  
项目目录：`C:\Users\lenmo\Desktop\Short Video Analysis`  
当前版本：`1.1.6`  
当前分支：`main`

## 1. 项目现状

这是一个 AGPL-3.0-only 的短视频解析与下载工具，包含：

- PHP 8.1+ 后端、网页版、媒体代理和 `/api/v1` API；
- `packages/parsers` 共享 JavaScript 解析器；
- Electron Windows 客户端；
- Capacitor Android 客户端；
- 文件缓存、限流、可选 API Key、GitHub Releases 更新检查。

扫描结果（排除 `.git`、`vendor`、`node_modules`、构建产物和运行时存储）：2565 个源文件，其中 PHP 47 个、JavaScript/Node 相关文件 94 个，PHPUnit 测试 6 个。工作区在审计开始时干净，远程仅配置 GitHub `origin`，本次没有执行提交、推送、发布或远程修改。

## 2. 清理记录

### 已执行

| 项目 | 处理 | 依据 |
| --- | --- | --- |
| `.phpunit.result.cache` | 删除并加入 `.gitignore` | PHPUnit 生成的本地缓存，不属于源码或用户数据 |

### 保留

`vendor/`、各端 `node_modules/`、Android 工程、`storage/certs/cacert.pem`、缓存配置、迁移/用户数据目录和所有不确定是否废弃的文件均保留。没有确认无用的 Composer/npm 依赖，因此没有修改锁文件或依赖集合。

## 3. GitHub 竞品研究

研究了以下公开仓库的 README、许可证和架构定位：

| 仓库 | 许可证 | 可借鉴点 | 与本项目差异 |
| --- | --- | --- | --- |
| [imputnet/cobalt](https://github.com/imputnet/cobalt) | AGPL-3.0 | API、前端、包和文档分层；强调无广告、无追踪；提供实例保护文档 | 更偏通用媒体保存服务，本项目更聚焦中国平台解析、桌面端和 Android 端 |
| [yt-dlp/yt-dlp](https://github.com/yt-dlp/yt-dlp) | Unlicense | 大规模 extractor 体系、格式选择、重试和命令行可诊断性 | Python CLI 生态，不能直接复制到 PHP/浏览器端；本项目采用平台专用解析器和客户端共享包 |
| [iawia002/lux](https://github.com/iawia002/lux) | MIT | 多输入、断点/重试、代理和多线程下载体验 | Go 下载器，不承担本项目的 Web API、PWA 和 Electron/Capacitor 产品形态 |

GitHub 匿名 API 在本机查询时返回 403 rate limit，因此本报告不虚构 Star 数和最近更新时间；上述项目的许可证信息来自仓库 LICENSE，功能判断来自仓库 README 和目录说明。

可吸收的设计：统一错误模型、清晰的实例保护文档、可诊断的下载状态、解析器契约测试、独立更新检查模块。暂不整合完整下载引擎或第三方 UI，避免引入重复实现和额外依赖。

## 4. 发现的问题

### 已修复

1. JSON API 读取 `php://input` 没有大小上限，异常大请求可能消耗 PHP 内存。
2. 主页和合集 POST 路由绕过了统一 JSON body 解析，无法共享大小限制。
3. `/api/v1/update` 和 `/api/v1/platforms` 未执行限流，更新检查可反复触发外部 GitHub 请求。
4. API 和网页入口缺少基础响应安全头。
5. README 版本徽章仍显示 `v1.1.5`，与当前 `1.1.6` 不一致。
6. 生成的 `.phpunit.result.cache` 被 Git 跟踪。

### 更正的初始判断

初步检查曾怀疑 Dockerfile 缺少 PHP `ext-curl`，因此临时尝试补装。随后核对 `php:8.2-cli` 官方镜像构建配置，发现基础镜像已经以 `--with-curl` 编译 cURL 支持；重复加入 `docker-php-ext-install curl` 没有依据，也可能造成重复编译问题。已撤销 Dockerfile 修改，当前 Dockerfile 保持原样。PHP 基础镜像的 DOM/XML 与 mbstring 扩展仍需在可运行的镜像中通过 `php -m` 核实，本机 Docker daemon 未运行，因此不将它们列为已确认缺陷。

### 暂未解决

- 本机没有 PHP/Composer，Docker Desktop Linux 引擎当前未运行，因此本次无法执行 PHP lint、PHPUnit、smoke 或 Docker build。
- 抖音、快手、微博等平台依赖外部接口、登录态和风控，真实线上可用性仍需使用实际分享链接分平台冒烟。
- Android release 签名和 Windows 安装包发布流程未在本次审计中执行。

## 5. 已实施升级

### 后端与部署

- 新增 `MAX_JSON_BODY_BYTES` 配置，默认 1 MiB；读取时最多读取上限加 1 字节，超过上限返回 HTTP 413，避免无 `Content-Length` 时仍无界读取。
- 主页和合集路由统一调用受限的 `requestJsonBody()`。
- 更新检查与平台列表加入默认限流。
- API 和网页入口增加 `X-Content-Type-Options`、`X-Frame-Options`、`Referrer-Policy`、`Permissions-Policy`。

### 工程与文档

- 删除已确认的 PHPUnit 结果缓存并补充忽略规则。
- README 版本徽章更新为 `v1.1.6`。
- 本报告记录实际修改、验证和未完成项。

## 6. 架构评价

当前架构已经形成“PHP Web/API + 共享 JS 解析器 + Electron/Capacitor 客户端”的清晰边界，解析器契约测试覆盖 12 个平台和直播、发现、评论、主页/合集等扩展能力。主要维护成本来自平台接口变动、PHP 与 JS 双实现同步，以及 `public/api/v1.php` 路由文件偏长。下一阶段适合把重复的参数解析、限流和错误处理抽成 API middleware，但应先补齐 PHP 测试环境和路由集成测试。

## 7. UI/UX 评价

现有网页版、桌面端和移动端已共享工作台式交互，包含深浅色主题、历史、收藏、批量下载、图集 ZIP、发现、评论、主页/合集分页和 PWA 壳。前端构建通过；移动端构建仍有 Capacitor 动态/静态导入提示，但不阻断产物生成。

## 8. 测试报告

| 检查 | 结果 |
| --- | --- |
| `npm --prefix packages/parsers test` | 通过，55 项契约测试，0 失败 |
| `npm --prefix packages/update-checker test` | 通过 |
| `npm --prefix apps/app-shell run build` | 通过 |
| `npm --prefix apps/mobile run build` | 通过；有 Capacitor chunk 警告 |
| Node 语法检查 | 通过 |
| `git diff --check` | 通过 |
| PHP lint / PHPUnit / smoke | 未执行，本机无 PHP/Composer，Docker daemon 未运行 |
| Docker build | 未执行，Docker Desktop Linux 引擎未运行 |
| 真实平台网络冒烟 | 未执行，避免占用端口和触发外部平台风控 |

本次没有启动任何本地服务，没有修改或关闭其他项目进程，也没有占用现有端口。

## 9. 文件修改记录

### 新增

- `PROJECT_AUDIT_REPORT.md`

### 修改

- `.env.example`
- `.gitignore`
- `README.md`
- `config/app.php`
- `public/api/v1.php`
- `public/index.php`

### 删除

- `.phpunit.result.cache`

## 10. 最终质量评估

本次完成了有依据的部署修复、请求保护、限流补齐、基础安全头、生成物清理和文档校准；共享解析器、桌面端和移动端回归构建通过。PHP 后端运行验证仍受本机缺少 PHP/Composer 和 Docker daemon 未启动的环境限制，不能宣称后端测试已通过。

## 11. 建议的验收重点

1. 启动 Docker Desktop 后执行 `docker compose build`，再检查镜像中的 cURL、DOM/XML、mbstring 扩展。
2. 在 PHP 环境执行 `composer lint && composer test && composer smoke`。
3. 验证超 1 MiB JSON body 返回 413，主页/合集 POST 仍能正常读取 URL 和 cursor。
4. 连续请求 `/api/v1/update`、`/api/v1/platforms`，确认限流行为和健康检查公开策略符合预期。
5. 使用真实链接验收 B站、抖音、快手、微博、图集和直播路径。

