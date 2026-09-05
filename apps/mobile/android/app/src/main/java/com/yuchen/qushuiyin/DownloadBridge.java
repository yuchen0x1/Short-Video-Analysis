package com.yuchen.qushuiyin;

import android.Manifest;
import android.app.DownloadManager;
import android.content.Context;
import android.net.Uri;
import android.os.Build;
import android.os.Environment;

import com.getcapacitor.JSObject;
import com.getcapacitor.PermissionState;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;
import com.getcapacitor.annotation.Permission;
import com.getcapacitor.annotation.PermissionCallback;

/**
 * 原生下载桥：WebView 不支持 <a download> 保存文件，
 * 这里转交系统 DownloadManager（通知栏进度、断点续传、存入公共 Downloads）。
 */
@CapacitorPlugin(
        name = "DownloadBridge",
        permissions = {
                @Permission(
                        alias = "storage",
                        strings = { Manifest.permission.WRITE_EXTERNAL_STORAGE }
                )
        }
)
public class DownloadBridge extends Plugin {

    static final String STORAGE_PERMISSION_ALIAS = "storage";

    @PluginMethod
    public void download(PluginCall call) {
        if (shouldRequestStoragePermission(Build.VERSION.SDK_INT, getPermissionState(STORAGE_PERMISSION_ALIAS))) {
            requestPermissionForAlias(STORAGE_PERMISSION_ALIAS, call, "storagePermissionCallback");
            return;
        }
        performDownload(call);
    }

    @PermissionCallback
    public void storagePermissionCallback(PluginCall call) {
        if (shouldRequestStoragePermission(Build.VERSION.SDK_INT, getPermissionState(STORAGE_PERMISSION_ALIAS))) {
            call.reject("没有存储权限，请在系统设置中授予（Android 9 及以下）");
            return;
        }
        performDownload(call);
    }

    static boolean shouldRequestStoragePermission(int sdkInt, PermissionState state) {
        return DownloadPermissionSecurity.shouldRequestStoragePermission(
                sdkInt, state == PermissionState.GRANTED);
    }

    private void performDownload(PluginCall call) {
        String url = call.getString("url");
        String name = call.getString("name");
        if (url == null || url.isEmpty()) {
            call.reject("缺少下载地址");
            return;
        }

        Uri uri = Uri.parse(url);
        String scheme = uri.getScheme() == null ? "" : uri.getScheme().toLowerCase();
        if (!"http".equals(scheme) && !"https".equals(scheme)) {
            call.reject("仅支持 http/https 下载地址");
            return;
        }

        if (name == null || name.isEmpty()) {
            name = "download";
        }
        // 去掉路径分隔符与非法字符，防止文件名逃逸出 Downloads 目录
        name = name.replaceAll("[\\\\/:*?\"<>|]", "_").replace("..", "_").trim();

        try {
            DownloadManager manager = (DownloadManager) getContext()
                    .getSystemService(Context.DOWNLOAD_SERVICE);
            DownloadManager.Request request = new DownloadManager.Request(uri);
            request.setTitle(name);
            request.setDescription("去水印解析");
            request.setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED);
            request.setDestinationInExternalPublicDir(Environment.DIRECTORY_DOWNLOADS, name);
            long id = manager.enqueue(request);
            // 立即回读一次状态，便于诊断（status/reason 见 DownloadManager.STATUS_* / ERROR_*）
            JSObject result = new JSObject();
            result.put("id", id);
            android.database.Cursor cursor = manager.query(
                    new DownloadManager.Query().setFilterById(id));
            if (cursor != null) {
                try {
                    if (cursor.moveToFirst()) {
                        int statusIdx = cursor.getColumnIndex(DownloadManager.COLUMN_STATUS);
                        int reasonIdx = cursor.getColumnIndex(DownloadManager.COLUMN_REASON);
                        result.put("status", statusIdx >= 0 ? cursor.getInt(statusIdx) : null);
                        result.put("reason", reasonIdx >= 0 ? cursor.getInt(reasonIdx) : null);
                    } else {
                        result.put("status", -1);
                    }
                } finally {
                    cursor.close();
                }
            }
            call.resolve(result);
        } catch (SecurityException e) {
            call.reject("没有存储权限，请在系统设置中授予（Android 9 及以下）");
        } catch (Exception e) {
            call.reject("下载任务创建失败: " + e.getMessage());
        }
    }
}

final class DownloadPermissionSecurity {

    private DownloadPermissionSecurity() {}

    static boolean shouldRequestStoragePermission(int sdkInt, boolean granted) {
        return sdkInt <= 28 && !granted;
    }
}
