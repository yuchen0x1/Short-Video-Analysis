const { contextBridge, shell, ipcRenderer } = require('electron');

const initialMediaProxyPort = Number(
  process.argv.find((arg) => arg.startsWith('--media-proxy-port='))?.slice('--media-proxy-port='.length)
) || 0;
let mediaProxyPort = initialMediaProxyPort;
if (!mediaProxyPort) {
  ipcRenderer.invoke('app.mediaProxyPort').then((port) => { mediaProxyPort = Number(port) || 0; }).catch(() => {});
}

function localMediaProxyUrl(url, filename = '') {
  const value = String(url || '');
  if (!mediaProxyPort || !/^https?:\/\//i.test(value)) return value;
  const proxy = new URL(`http://127.0.0.1:${mediaProxyPort}/`);
  proxy.searchParams.set('url', value);
  if (filename) proxy.searchParams.set('filename', String(filename));
  return proxy.href;
}

contextBridge.exposeInMainWorld('appShell', {
  platform: 'desktop',
  isNative: true,
  openExternal: (url) => {
    if (/^https?:\/\//i.test(String(url))) shell.openExternal(String(url));
  },
  // 本地解析 HTTP 传输桥（主进程 net.fetch，无 CORS 限制）
  httpRequest: (payload) => ipcRenderer.invoke('app.httpRequest', payload),
  // 本地媒体代理地址（注入 Referer/UA 绕过防盗链）
  mediaProxyUrl: (url, filename) => localMediaProxyUrl(url, filename),
  // 微博网页登录：只暴露状态和受限请求，不暴露 Cookie 字符串
  openWeiboLogin: () => ipcRenderer.invoke('weibo.login.open'),
  getWeiboLoginStatus: () => ipcRenderer.invoke('weibo.login.status'),
  logoutWeibo: () => ipcRenderer.invoke('weibo.login.logout'),
  weiboRequest: (payload) => ipcRenderer.invoke('weibo.request', payload),
  onWeiboLoginChanged: (callback) => {
    const listener = () => callback();
    ipcRenderer.on('weibo.login.changed', listener);
    return () => ipcRenderer.removeListener('weibo.login.changed', listener);
  },
  getVersion: () => ipcRenderer.invoke('app.version'),
  getUpdateStatus: () => ipcRenderer.invoke('app.update.status'),
  getChangelog: () => ipcRenderer.invoke('app.changelog'),
  checkForUpdates: () => ipcRenderer.send('app.update.check'),
  quitAndInstall: () => ipcRenderer.send('app.update.install'),
  onUpdateStatus: (callback) => {
    const listener = (_event, payload) => callback(payload);
    ipcRenderer.on('app.update.status', listener);
    return () => ipcRenderer.removeListener('app.update.status', listener);
  },
});
