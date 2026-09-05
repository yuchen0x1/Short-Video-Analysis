// 与 PHP AbstractParser 的 fixUrl 行为一致：补全协议相对地址 //xxx -> https://xxx
export function fixUrl(url) {
  const value = String(url ?? '');
  if (value === '') return '';
  return value.startsWith('http') ? value : 'https:' + value;
}

export function firstUrl(urlList) {
  if (!Array.isArray(urlList) || typeof urlList[0] !== 'string' || urlList[0] === '') {
    return null;
  }
  return fixUrl(urlList[0]);
}

// 微博 Cookie 等运行期设置，由宿主 App 注入（localStorage / 设置页）
const runtimeSettings = { weiboCookie: '' };

export function setWeiboCookie(cookie) {
  runtimeSettings.weiboCookie = String(cookie || '').trim();
}

export function getWeiboCookie() {
  return runtimeSettings.weiboCookie;
}
