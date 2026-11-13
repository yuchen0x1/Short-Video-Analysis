# 网页版、Android 维护与项目清理

日期：2026-11-13。范围为 PHP/网页、Android 与本地/仓库文件维护，改进记入待发布区；当前正式版本仍为 v1.1.9。

## 完成的维护

| 范围 | 问题与结果 |
| --- | --- |
| Android 图集 | 原来的 ZIP blob 地址无法交给 DownloadManager；原生端改为按顺序加入原图下载任务，浏览器保留 ZIP 并限制 128 MiB / 单文件请求 60 秒 |
| 网页/移动发现页 | 修复请求失败后跳过页码；保留已加载内容，禁止重复请求，丢弃不再匹配当前查询的迟到结果 |
| PHP 抖音搜索 | 兼容顶层数组与嵌套响应；合法空列表按末页处理，异常/受限响应仍返回可读错误 |
| 移动端本地数据 | 过滤 null、损坏对象、非法链接与重复历史；存储受限不再阻止启动，写入失败不假报保存成功 |
| 移动端任务 | 代理启动前即加锁；解析/翻页互斥；代理启动失败可以重试；复制和下载任务创建失败正确提示 |
| Android 测试 | 移除重复 ExampleUnitTest，独有的重定向状态测试合并到 MediaProxySecurityTest；移除只验证包名的模板测试和未引用布局 |
| 文档 | 修正 Android 工程误称整目录被忽略、重复 cap add 与更新源允许 HTTP 等过时说明 |

这些是边界与失败恢复修复，没有据此宣称所有外部平台长期可用或所有端功能完全相同。

## 本地实际清理

以下 8 个目录经过绝对路径、Git 跟踪状态、链接/目录联接检查后删除，共 677 个文件、1,125,493,277 字节（约 1.05 GiB / 1.13 GB）：

| 路径 | 字节 | 原因 |
| --- | ---: | --- |
| `apps/app-shell/release/` | 208607371 | v1.1.8 旧安装包与清单 |
| `apps/app-shell/release-v1.1.9-portable-check/` | 434245840 | 重复便携版验证与解包目录 |
| `apps/app-shell/release-v1.1.9/win-unpacked/` | 340626157 | 已生成安装包后可重建的解包目录 |
| `.evolution/published-v1.1.9/` | 104335969 | 已完成哈希核验的重复下载 |
| `.evolution/remote-v1.1.9/` | 2152 | 重复元数据下载 |
| `.evolution/browser/` | 19037808 | 未使用的浏览器工具试装目录 |
| `.evolution/composer-cache/` | 2454728 | 临时 Composer 下载缓存 |
| `.evolution/electron-builder-cache/` | 16183252 | 临时打包工具缓存 |

保留最新本地 Windows 安装包、便携版与更新文件；保留本轮 Debug APK 以便设备验收。保留实际使用的 vendor/node_modules、浏览器测试运行库、Git 数据、CA 信任库、品牌图标、被文档引用的截图，以及任何用户配置和签名密钥。上述清理不触及工作区外的 SDK、Gradle 缓存或同步盘其他项目。

## 仓库清理与上传约束

- 检查了清理前 GitHub main 的目录树与本地对应提交：源码库约 7.67 MB，没有跟踪安装包、node_modules、vendor 或本机 keystore。不能把这一结果视作完整秘密审计。
- 删除 `.workflow/quality-pipeline.yml`，现行质量/发布流程只使用 GitHub Actions。
- 删除 5 份已由后续分析与实现取代的旧计划：原 upgrade-report 的 01、02、03、05、06。历史内容仍可通过 Git 提交历史查看。
- 保留比较分析、实施记录和品牌资源；早期根目录审计报告移至 `docs/archive/AUDIT-2026-11-09.md`，历史报告补充快照标识，避免旧环境结论被当作当前状态。
- 强化 `.gitignore`：本机 env 覆盖、签名容器/密钥、本机 SDK 配置、编辑器目录、Python 缓存与 coverage；保留 `.env.example`。Android 原生源码、Gradle wrapper、图标和 CA 文件继续跟踪。
- 新增 `node scripts/check-repository-hygiene.mjs` 并纳入 CI，检查实际 Git 跟踪列表，阻止生成包、本机配置、已知私钥/token 模式进入仓库。只报告文件路径，不输出命中的凭据。
- CI 增加 Android Debug 编译与 JVM 测试，后续维护同时验证原生 Android 工程；无须正式签名密钥，也不会发布测试 APK。
- 对现有 Git 历史对象执行相同私钥/GitHub token 模式扫描，未命中。这是启发式检查，不覆盖所有类型的凭据。
- 没有重写 Git 历史、移动版本标签或删除旧 Releases；旧安装包可能仍服务于用户回退和版本溯源。

## 验证与限制

| 检查 | 结果 |
| --- | --- |
| PHP lint / smoke | PASS |
| PHPUnit | PASS：99 tests / 363 assertions |
| Web / 移动工作流 Node 测试 | PASS：11 + 7 |
| 共享解析器离线契约 | PASS：56 |
| 更新检查器、签名服务 | PASS；签名服务 6 项 |
| 桌面、移动 Vite 构建与 Capacitor sync | PASS |
| Android `assembleDebug` / `testDebugUnitTest` | PASS：生成 Debug APK，8 项 JVM 测试，无失败 |
| Web 浏览器 | PASS：鉴权、导入恢复、批量、真实 ZIP CRC、CSV、快捷键、分页失败重试、1440/768/390 布局 |
| 移动浏览器 | PASS：损坏历史、解析互斥、ZIP CRC、原生下载桥夹具参数、分页失败重试、390 布局、存储受限启动 |
| 真机保存 / 正式 APK 签名 / 实际升级 | 未验证：adb 没有连接设备；未发布新版本或更改签名身份 |

浏览器平台内容使用明确夹具，不能代替真实上游平台成功率测试；「已加入系统下载」仅表示成功创建任务。最新代码待下一次正式发版后才会进入用户安装包。
