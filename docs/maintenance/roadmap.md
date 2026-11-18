# 维护路线图与验收

标注 A 为发布或正确性核心，B 为直接提升体验，C 为待评估，D 为当前取舍。具体功能与研究依据见 [研究记录](competitive-research.md)。历史迭代可查阅 [升级路线图](../../UPGRADE_ROADMAP.md)。

| 序号 | 升级项 | 参考依据 | 优先级 | 难度 | 决策 |
| ---: | --- | --- | --- | --- | --- |
| 1 | 发布前完整 CI | electron-builder | A | 中 | 本轮 |
| 2 | 只读构建与发布隔离 | GitHub Actions | A | 中 | 本轮 |
| 3 | 不可变发布与来源证明 | Sigstore/GitHub | A | 低 | 本轮 |
| 4 | 正式包校验与损坏阻断 | aria2/electron-builder | A | 中 | 本轮 |
| 5 | Windows 打包启动用例 | MediaGo | A | 中 | 本轮 |
| 6 | PHP 独立分发 ZIP | MeTube/Docker Compose | A | 低 | 本轮 |
| 7 | PHP 8.1/8.3 矩阵 | Symfony | A | 低 | 本轮 |
| 8 | 依赖更新和安全审查 | Dependabot | A | 低 | 本轮 |
| 9 | 私密漏洞报告与反馈表单 | Gitleaks/GitHub | A | 低 | 本轮 |
| 10 | 主分支规则和 CI 门禁 | GitHub | A | 低 | 本轮 |
| 11 | 更新下载与安装显式确认 | electron-builder | A | 中 | 本轮 |
| 12 | 有可信发布者时启用原生下载 | electron-builder | A | 中 | 本轮，证书待配置 |
| 13 | 按版本稍后提醒 | PowerToys | B | 低 | 本轮 |
| 14 | 启动时检查可关闭 | PowerToys | B | 低 | 本轮 |
| 15 | PWA 等待激活与草稿恢复 | Workbox | A | 中 | 本轮 |
| 16 | 禁用生产 Node 调试入口 | Electron Fuses | A | 中 | 本轮 |
| 17 | 构建 ASAR 完整性校验 | Electron Fuses | A | 中 | 本轮 |
| 18 | 发布包缩减 source map | Electron | B | 低 | 本轮 |
| 19 | 电脑版输入快捷键 | Parabolic | B | 低 | 本轮 |
| 20 | 拖放链接与文本文件 | Parabolic | B | 低 | 本轮 |
| 21 | 计划性验证发布资产 | changedetection.io | A | 中 | 本轮 |
| 22 | 竞品长期快照 | GitHub API | B | 低 | 本轮 |
| 23 | Windows 10 实机安装回归 | Open Video Downloader | A | 中 | 待环境 |
| 24 | 可信签名及证书轮换 | electron-builder | A | 高 | 待发布者证书 |
| 25 | 更新失败回滚和数据恢复演练 | PowerToys | A | 高 | 待隔离签名环境 |
| 26 | 图集命名模板 | gallery-dl | B | 中 | 评估跨端一致性 |
| 27 | 桌面历史备份与合并 | MeTube | B | 中 | 与网页备份契约统一后实施 |
| 28 | 定点实链存活样本 | yt-dlp | B | 中 | 按公开稳定样本加入 |
| 29 | 直播 HLS 错误和外部交接 | Streamlink | B | 中 | 需实测播放器 |
| 30 | 桌面下载任务恢复 | aria2 | B | 高 | 需持久化队列 |
| 31 | PWA 子路径浏览器矩阵 | Workbox | B | 中 | 安排自动化 |
| 32 | 多来源转码 | FFmpeg | C | 高 | 需许可证和体积决策 |
| 33 | 独立移动、桌面扩展与插件 | Seal/Parabolic | D | 高 | 现有产品范围之外 |
| 34 | 服务端永久存储和账号系统 | MeTube | D | 高 | 本地优先产品保持无库 |

每次发版以 [GitHub Actions](https://github.com/yuchen0x1/Short-Video-Analysis/actions) 和 [正式 Release](https://github.com/yuchen0x1/Short-Video-Analysis/releases/latest) 为状态来源。以测试结果区分已构建、已运行、待证书、待设备和生产部署。提供真实日志前，安装、卸载、跨版本迁移及回滚单独记录为待验证。
