# 手机版发布与签名说明

去水印解析手机版使用 Capacitor 构建 Android 工程，release 包需要签名才能安装和上架。移动端解析使用共享 JS parser 和 CapacitorHttp，媒体预览使用本地原生媒体代理，不依赖根目录 PHP 服务或远程解析 API。

## 关键文件

- `android/keystore/release.keystore`：RSA 签名密钥，必须单独备份并保密。
- `android/keystore.properties`：签名参数（`storeFile` / `storePassword` / `keyAlias` / `keyPassword`）。
- `android/app/build.gradle`：已接入 `signingConfigs.release`，release 构建会自动读取上述文件。

这些文件都在被 `.gitignore` 忽略的 `apps/mobile/android/` 目录下，不会进入版本库。

## 打 release APK

```powershell
cd apps/mobile
# 1. 构建共享 JS parser 和移动端界面
npm run build
# 2. 首次需要生成 Android 工程
npx cap add android
# 3. 同步 Web 资源和 Capacitor 原生插件
npx cap sync android
# 4. 构建签名版
cd android
.\gradlew.bat assembleRelease
```

产物：`apps/mobile/android/app/build/outputs/apk/release/app-release.apk`

## 校验签名

```powershell
& "$env:ANDROID_HOME\build-tools\35.0.0\apksigner.bat" verify --print-certs app\build\outputs\apk\release\app-release.apk
```

## 生成自己的 keystore（正式发布必须换掉本地测试密钥）

```powershell
keytool -genkeypair -v -keystore keystore/release.keystore -alias qushuiyin -keyalg RSA -keysize 2048 -validity 10000 `
  -storepass 你的强密码 -keypass 你的强密码 `
  -dname "CN=你的名称, OU=部门, O=组织, L=城市, ST=省, C=CN"
```

然后把 `android/keystore.properties` 里的 `storePassword` / `keyPassword` 改成你自己的，`keyAlias` 与 `storeFile` 保持一致。

## 在线更新配置

默认检查 `gitee.com` 的项目 Release。只有在需要自定义更新 API 时，才在 `apps/mobile/.env` 设置 `VITE_UPDATE_API_URL`；地址必须使用 HTTP(S) 协议，并且主机必须是 `gitee.com` 或其合法子域名（例如 `api.gitee.com`），不能携带用户凭据。

## 重要提醒

1. 移动端解析和媒体下载在设备本地完成；发布前不需要配置远程解析服务。
2. keystore 与密码务必保密并备份；同一应用后续升级必须使用同一 keystore 签名，否则 Android 会拒绝覆盖安装。
3. 上架 Google Play 使用 AAB：`.\gradlew.bat bundleRelease`，产物在 `android/app/build/outputs/bundle/release/`。
4. 桌面端（`apps/app-shell`）打包见其 README：`npm run dist:win`。
