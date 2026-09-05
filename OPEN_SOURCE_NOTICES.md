# 开源参考与归属

本文件记录本项目在诊断、设计和兼容性研究中参考过的公开开源项目。除项目已有明确声明的上游代码外，本轮没有直接复制受限制许可证项目的代码。

## 已有上游

- `5ime/video_spider`：本项目早期解析逻辑的上游参考，项目 README 已声明二次开发关系。
- `zouyi741599086/douyinlive`：`packages/parsers/src/a-bogus.js` 文件内已有原始来源注释。

## 本轮参考

- `wujunwei928/parse-video`：MIT License。参考其分享文案直接提取 URL、统一解析入口、媒体字段和公开平台兼容策略；没有复制其 Go 源文件。
  - https://github.com/wujunwei928/parse-video
- `wujunwei928/parse-video-py`：MIT License。参考其西瓜视频分享链接与错误边界说明；没有复制其 Python 源文件。
  - https://github.com/wujunwei928/parse-video-py
- `jiuhunwl/short_videos`：MIT License。参考其 PHP 多平台接口组织方式和备用媒体字段设计；没有复制其实现。
  - https://github.com/jiuhunwl/short_videos
- `jiuhunwl/shortvideo-html`：MIT License。参考其批量下载、结果区和错误反馈的产品组织；没有复制其前端代码。
  - https://github.com/jiuhunwl/shortvideo-html
- `yt-dlp/yt-dlp`：Unlicense。评估过将其作为可选广泛站点后备引擎的可行性；本轮没有引入其运行时或代码，避免增加 Python/FFmpeg 依赖和跨端打包复杂度。
  - https://github.com/yt-dlp/yt-dlp
- `LuckyPuppy514/Play-With-MPV`：MIT。参考其西瓜视频 SSR_HYDRATED_DATA 与 Base64 媒体地址处理思路；没有复制脚本代码。
  - https://github.com/LuckyPuppy514/Play-With-MPV
- `NanmiCoder/MediaCrawler`：当前许可证为非商业学习许可。本项目仅参考其公开接口变化线索，不复制代码、不合并其实现。
  - https://github.com/NanmiCoder/MediaCrawler

## 整合原则

1. 只合并许可证与本项目 AGPL-3.0 兼容、且经过本地测试的代码或依赖。
2. 对非商业学习许可、许可证不清晰或要求额外授权的项目，仅做资料参考。
3. 所有平台解析结果必须经过项目自身的 URL、媒体类型和安全白名单校验。
4. 不绕过验证码、登录、付费、私密权限或 DRM；平台限制应作为明确的外部条件返回。
