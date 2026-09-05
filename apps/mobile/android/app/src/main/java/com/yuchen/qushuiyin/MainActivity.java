package com.yuchen.qushuiyin;

import android.os.Bundle;

import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {

    @Override
    public void onCreate(Bundle savedInstanceState) {
        // 插件必须在 super.onCreate 之前注册：Capacitor 7 的 BridgeActivity
        // 会在 super.onCreate 末尾的 load() 中构建 bridge，晚注册不会生效
        registerPlugin(DownloadBridge.class);
        registerPlugin(MediaProxyPlugin.class);
        super.onCreate(savedInstanceState);
    }
}
