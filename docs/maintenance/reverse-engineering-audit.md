# v1.2.0 逆向暴露面审计

审计日期为 2026-11-18，目标为本项目自有的 Windows x64 打包目录和渲染器产物。报告使用发布前构建的 `apps/app-shell/release/win-unpacked` 与 `apps/app-shell/dist`，不代表服务端权限或第三方平台安全性。

## 结果

| 目标 | 工具结果 | 结论 |
| --- | --- | --- |
| `apps/app-shell/dist` | 评分 100，等级 A，无发现 | Vite 产物没有随包 source map、凭证或二进制文件。 |
| `apps/app-shell/release/win-unpacked` | 评分 38，等级 D | 评分被 Electron 的 ASAR 可读取性、第三方 DLL 调试信息和未签名原生文件拉低。ASAR 是归档格式，不是加密容器。 |
| `去水印解析.exe` | x64 PE，未签名 | 当前 `package.json` 明确关闭 `signExecutable`，发布资产不能提供 Authenticode 身份证明。 |

## 已实施的加固

- Electron Fuses 已实际读回 `RunAsNode`、Node Options 环境变量和 Node Inspector 参数关闭。
- `EnableEmbeddedAsarIntegrityValidation` 和 `OnlyLoadAppFromAsar` 已启用。
- 生产包排除 `*.map`，渲染器扫描没有发现 source map。
- 真实业务凭据、微博 Cookie 和第三方授权判断没有放进桌面包。需要长期保护的逻辑继续留在 PHP 服务端或短期会话中。

## 扫描命中及边界

- `PE-PDB`、`PE-SIGN` 和 `CLR-ASM` 的命中来自 Electron 分发的 `d3dcompiler_47.dll`、`dxcompiler.dll`、`dxil.dll`、`ffmpeg.dll`、`vk_swiftshader.dll` 等第三方组件，不能归因于本项目业务源码。
- `WEB-SOURCEMAPREF` 命中来自 Electron 依赖内部的 `sourceMappingURL` 字符串。构建目录没有对应的业务 source map 文件，属于启发式扫描命中。
- `http://127.0.0.1` 是桌面本地媒体代理和 DevTools 运行时地址。它不是外部内网服务地址，但说明本地代理端口和协议可以被分析工具看到。
- `PE-SIGN` 仍是当前可见的发布缺口。没有可用的 Windows 代码签名证书与时间戳服务，不能把未签名资产描述为已验证的完整性发布。

扫描器自检为 58/60，两个失败项是技能自带合成样本对托管 BSJB 和 Android v2 签名的识别回归，与本项目 Windows/Web 目标无关。该结果作为工具限制记录，不能当作项目测试通过。

## 后续顺序

1. P0 为配置 Windows Authenticode 证书、时间戳和发布者校验，再开启 `signExecutable`。
2. P1 继续保持 Fuses、无 source map 和服务端凭据边界，每次 Electron 大版本升级后重新读回 Fuses。
3. P2 评估是否需要为第三方 DLL 做供应链来源审计。仅为提高逆向成本而混淆公开的解析器，收益低于维护成本。
