import './styles.css';
import * as parsers from '@parsers';
import { applyLocale, readLocaleSetting, resolveLocale, t, writeLocaleSetting } from './i18n.js';

const HISTORY_STORAGE = 'qushuiyin-app-history-v1';
const THEME_STORAGE = 'qushuiyin-app-theme-v1';
const WEIBO_COOKIE_STORAGE = 'qushuiyin-app-weibo-cookie-v1';
const UPDATE_AUTOCHECK_STORAGE = 'qushuiyin-update-autocheck-v1';
const UPDATE_DEFERRED_STORAGE = 'qushuiyin-update-deferred-v1';
const RELEASES_URL = 'https://github.com/yuchen0x1/Short-Video-Analysis/releases';
const URL_PATTERN = /https?:\/\/[^\s"'<>，。；：！？【】（）《》]+/i;

const shell = window.appShell || {};
const localeState = {
  setting: readLocaleSetting(),
  locale: resolveLocale(readLocaleSetting(), typeof navigator === 'undefined' ? [] : navigator.languages || [navigator.language]),
};

// 本地解析：传输层经 IPC 走主进程，使用独立的共享 JS 解析器包
const ipcTransport = {
  async request({ url, method, headers, body, timeoutMs = 15000, credentialMode }) {
    const request = credentialMode === 'weibo-session' && shell.weiboRequest
      ? shell.weiboRequest
      : shell.httpRequest;
    const response = await request({ url, method, headers, body, timeoutMs });
    return {
      status: response.status,
      data: response.data,
      headers: response.headers || {},
      url: response.url || url,
    };
  },
};
parsers.setTransport(ipcTransport);
parsers.setWeiboCookie(localStorage.getItem(WEIBO_COOKIE_STORAGE) || '');

const state = {
  theme: localStorage.getItem(THEME_STORAGE) || 'dark',
  view: 'single',
  mode: 'single',
  busy: null,
  profile: null,
  history: loadHistory(),
  activeMediaRefresh: false,
  currentSourceUrl: '',
  discover: { tab: 'hot', platform: 'bilibili', page: 1, items: [], hasMore: false, total: 0, keyword: '', loading: false },
};

const app = document.querySelector('#app');

app.innerHTML = `
  <div class="app-frame">
    <header class="app-header">
      <div class="brand-lockup">
        <span class="brand-mark" aria-hidden="true">去</span>
        <div class="brand-text">
          <span class="brand-name" data-i18n="brandName">去水印解析</span>
          <span class="brand-sub">DESKTOP WORKSPACE · v${__APP_VERSION__}</span>
        </div>
      </div>
      <div class="header-actions">
        <span id="service-state" class="service-state"><i></i><span data-i18n="service">检查服务</span></span>
        <label class="locale-control"><span class="sr-only" data-i18n="language">语言 / Language</span><select id="locale-select" aria-label="语言 / Language"><option value="auto">自动 / Auto</option><option value="zh-CN">简体中文</option><option value="en-US">English</option></select></label>
        <button id="theme-button" class="icon-button" type="button" data-i18n-title="theme">◐</button>
      </div>
    </header>

    <div class="app-body">
      <nav class="side-nav" aria-label="主导航">
        <div class="side-nav-label" data-i18n="workspace">工作区</div>
        <button class="nav-button active" type="button" data-view="single"><span class="nav-name" data-i18n="parse">解析</span><small>01</small></button>
        <button class="nav-button" type="button" data-view="profile"><span class="nav-name" data-i18n="profile">主页</span><small>02</small></button>
        <button class="nav-button" type="button" data-view="discover"><span class="nav-name" data-i18n="discover">发现</span><small>03</small></button>
        <button class="nav-button" type="button" data-view="history"><span class="nav-name" data-i18n="history">记录</span><small>04</small></button>
        <button class="nav-button" type="button" data-view="about"><span class="nav-name" data-i18n="about">关于</span><small>05</small></button>
        <div class="side-nav-note"><span data-i18n="noWatermark">无水印</span><strong data-i18n="sideNote">把分享链接变成可用媒体</strong></div>
      </nav>

      <main class="content-column">
        <section id="parse-panel" class="tool-panel parse-input-panel">
          <div class="panel-heading">
            <div class="panel-title">
              <span class="panel-kicker" data-i18n="inputKicker">解析输入</span>
              <h2 data-i18n="inputTitle">解析分享链接</h2>
              <p class="panel-note" data-i18n="inputNote">粘贴分享文案，自动提取真正的链接和无水印媒体。</p>
            </div>
            <div class="segmented" role="group" aria-label="解析模式">
              <button class="segment active" type="button" data-mode="single" data-i18n="single">单条</button>
              <button class="segment" type="button" data-mode="profile" data-i18n="profile">主页</button>
              <button class="segment" type="button" data-mode="collection" data-i18n="collection">合集</button>
              <button class="segment" type="button" data-mode="live" data-i18n="live">直播</button>
            </div>
          </div>
          <div class="input-row">
            <input id="link-input" type="text" autocomplete="off" spellcheck="false" data-i18n-placeholder="placeholder" placeholder="粘贴视频链接或整段分享文案" />
            <button id="paste-input" class="secondary-button" type="button" data-i18n="paste" title="从剪贴板粘贴">粘贴</button>
            <button id="clear-input" class="secondary-button" type="button" data-i18n="clear">清除</button>
            <button id="parse-button" class="primary-button" type="button">开始解析</button>
          </div>
          <p id="input-hint" class="field-hint">粘贴时会自动提取真正的链接。</p>
          <div class="input-context"><span><i></i><span data-i18n="local">本地优先解析</span></span><span><i></i><span data-i18n="videoImages">支持视频与图集</span></span><span><i></i><span data-i18n="noUpload">无后台上传</span></span></div>
          <div class="quick-platforms" aria-label="支持平台"><span>抖音</span><span>快手</span><span>B站</span><span>小红书</span><span>微博</span><span>更多平台</span></div>
        </section>

        <section class="view active" data-view-panel="single">
          <section id="single-result" class="result-panel">
            <div class="workspace-empty">
              <span class="workspace-empty-mark" aria-hidden="true">→</span>
              <div>
                <strong data-i18n="waiting">等待链接</strong>
                <span data-i18n="waitingNote">粘贴分享链接，解析结果会显示在这里。</span>
              </div>
            </div>
          </section>
        </section>

        <section class="view" data-view-panel="profile" hidden>
          <div class="page-intro compact-intro">
            <span class="eyebrow">作品列表</span>
            <h1>按页整理公开作品。</h1>
            <p>只在点击下一页时请求新内容，返回上一页直接恢复缓存。</p>
          </div>
          <section id="profile-panel" class="result-panel profile-panel"></section>
        </section>

        <section class="view" data-view-panel="discover" hidden>
          <div class="page-intro compact-intro">
            <span class="eyebrow">发现内容</span>
            <h1>公开热榜与搜索。</h1>
            <p>仅查看平台公开内容，按需加载，不做自动采集。</p>
          </div>
          <section class="tool-panel">
            <div class="panel-heading">
              <div class="panel-title">
                <span class="panel-kicker">发现</span>
                <h2 id="discover-summary">B站综合热门</h2>
                <p class="panel-note" id="discover-note">用户主动查看，按需加载下一页。</p>
              </div>
              <div class="segmented" role="group" aria-label="发现类型">
                <button class="segment active" type="button" data-discover-tab="hot">热榜</button>
                <button class="segment" type="button" data-discover-tab="search">搜索</button>
                <button class="segment active" type="button" data-discover-platform="bilibili">B站</button>
                <button class="segment" type="button" data-discover-platform="douyin">抖音</button>
              </div>
            </div>
            <div class="input-row">
              <input id="discover-input" type="text" autocomplete="off" spellcheck="false" placeholder="热榜无需关键词；搜索时输入关键词" />
              <button id="discover-search" class="primary-button" type="button">获取</button>
            </div>
          </section>
          <section id="discover-panel" class="result-panel">
            <div class="workspace-empty">
              <div>
                <strong>尚未加载内容</strong>
                <span>点击「获取」查看热榜或搜索结果。</span>
              </div>
            </div>
          </section>
        </section>

        <section class="view" data-view-panel="history" hidden>
          <div class="page-intro compact-intro">
            <span class="eyebrow">本地记录</span>
            <h1>最近处理。</h1>
            <p>历史记录仅保存在当前设备。</p>
          </div>
          <section id="history-panel" class="result-panel"></section>
        </section>

        <section class="view" data-view-panel="about" hidden>
          <div class="page-intro compact-intro">
            <span class="eyebrow">版本信息</span>
            <h1>关于应用。</h1>
            <p>查看当前版本、检查更新和更新日志。</p>
          </div>
          <section id="about-panel" class="about-panel"></section>
        </section>

      </main>
    </div>
    <footer class="app-footer"><span><span data-i18n="brandName">去水印解析</span> · v${__APP_VERSION__}</span><span id="platform-label">客户端</span></footer>
    <div id="toast" class="toast" role="status" aria-live="polite" hidden></div>
    <dialog id="update-dialog" class="about-card update-dialog" aria-labelledby="update-dialog-title">
      <h2 id="update-dialog-title">去水印解析有新版本</h2>
      <p id="update-dialog-details" class="panel-note"></p>
      <div class="about-actions">
        <button id="update-dialog-action" class="primary-button" type="button">查看并下载</button>
        <a class="secondary-button" href="${RELEASES_URL}/latest" id="update-dialog-release" target="_blank" rel="noopener">查看详情</a>
        <button id="update-dialog-later" class="secondary-button" type="button">稍后</button>
      </div>
    </dialog>
  </div>
`;

const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
const localeSelect = $('#locale-select');
const parsePanel = $('#parse-panel');
const linkInput = $('#link-input');
const parseButton = $('#parse-button');
const pasteInputButton = $('#paste-input');
const clearInputButton = $('#clear-input');
const inputHint = $('#input-hint');
const singleResult = $('#single-result');
const profilePanel = $('#profile-panel');
const historyPanel = $('#history-panel');
const toast = $('#toast');

function applyAppLocale() {
  document.documentElement.lang = localeState.locale;
  document.title = t(localeState.locale, 'brandName');
  applyLocale(app, localeState.locale);
  localeSelect.value = localeState.setting;
  $('#platform-label').textContent = t(localeState.locale, shell.platform === 'desktop' ? 'desktop' : 'web');
  syncParseControls();
  if (state.view === 'profile') renderProfile();
  checkHealth();
}

localeSelect?.addEventListener('change', (event) => {
  localeState.setting = writeLocaleSetting(event.target.value);
  localeState.locale = resolveLocale(localeState.setting, typeof navigator === 'undefined' ? [] : navigator.languages || [navigator.language]);
  applyAppLocale();
});

function loadHistory() {
  try {
    const value = JSON.parse(localStorage.getItem(HISTORY_STORAGE) || '[]');
    return Array.isArray(value) ? value : [];
  } catch {
    return [];
  }
}

function saveHistory() {
  localStorage.setItem(HISTORY_STORAGE, JSON.stringify(state.history.slice(0, 100)));
}

function cleanUrl(value) {
  const match = String(value || '').match(URL_PATTERN);
  return (match ? match[0] : String(value || '').trim()).replace(/[),.。]+$/, '');
}

function mediaUrl(url) {
  return shell.mediaProxyUrl ? shell.mediaProxyUrl(url) : url;
}

function safeHttpUrl(value) {
  try {
    const url = new URL(String(value || ''));
    return (url.protocol === 'http:' || url.protocol === 'https:') && Boolean(url.hostname);
  } catch {
    return false;
  }
}

function downloadUrl(url, filename) {
  return shell.mediaProxyUrl ? shell.mediaProxyUrl(url, filename) : url;
}

function escapeHtml(value) {
  return String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

function formatDate(value) {
  if (!value) return '-';
  const date = new Date(Number(value) * 1000);
  return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleDateString('zh-CN');
}

function formatCount(value) {
  if (value === undefined || value === null || value === '') return '-';
  const number = Number(value);
  return Number.isFinite(number) ? new Intl.NumberFormat('zh-CN', { notation: 'compact', maximumFractionDigits: 1 }).format(number) : String(value);
}

function mediaType(item) {
  return item?.media_type === 'image' ? 'image' : 'video';
}

function itemMedia(item) {
  return Array.isArray(item?.images) && item.images.length ? item.images : item?.url ? [item.url] : [];
}

function notify(message) {
  toast.textContent = message;
  toast.hidden = false;
  clearTimeout(notify.timer);
  notify.timer = setTimeout(() => { toast.hidden = true; }, 2400);
}

function setBusy(on, message = 'parsing', page = 1) {
  state.busy = on ? { message, page } : null;
  syncParseControls();
}

function setServiceState(label, status = 'ok') {
  const service = $('#service-state');
  service.className = 'service-state ' + status;
  $('span', service).textContent = label;
}

function renderSingleResult(data, rawUrl) {
  const type = mediaType(data);
  const urls = itemMedia(data);
  const first = urls[0] || data.url || '';
  // 抖音等平台带背景音乐时给出下载入口
  const musicUrl = /^https?:\/\//i.test(data.music?.url || '') ? data.music.url : '';
  const musicHref = musicUrl ? downloadUrl(musicUrl, (data.title || 'media').replace(/[\\/:*?"<>|]/g, '_') + '-music.mp3') : '';
  state.currentSourceUrl = rawUrl;
  singleResult.hidden = false;
  commentsPanel.hidden = true;
  const commentsButton = /bilibili\.com|b23\.tv/i.test(rawUrl)
    ? `<button class="secondary-button" type="button" data-comments-url="${escapeHtml(rawUrl)}">查看评论</button>`
    : '';
  singleResult.innerHTML = `
    <div class="result-heading"><div><span class="panel-kicker">解析结果</span><h2>${escapeHtml(data.title || '未命名内容')}</h2><p>${escapeHtml(data.author || '未知作者')} · ${type === 'image' ? '图集' : '视频'} · ${formatDate(data.time)}</p></div><span class="result-type">${type === 'image' ? '图集' : '视频'}</span></div>
    <div class="result-media ${type === 'image' ? 'is-gallery' : ''}">${type === 'image' ? urls.map((url, index) => `<figure class="image-tile"><img src="${escapeHtml(mediaUrl(url))}" alt="${escapeHtml(data.title || '图片')} ${index + 1}" loading="lazy"><a class="tile-download" href="${escapeHtml(downloadUrl(url, (data.title || 'image-' + (index + 1)) + '.jpg'))}" download>下载</a></figure>`).join('') : `<video controls playsinline preload="metadata" poster="${escapeHtml(data.cover ? mediaUrl(data.cover) : '')}" src="${escapeHtml(mediaUrl(first))}"></video>`}</div>
    <div class="result-details"><div><span>点赞</span><strong>${escapeHtml(formatCount(data.like))}</strong></div><div><span>平台</span><strong>${escapeHtml(platformName(rawUrl))}</strong></div><div><span>作者</span><strong>${escapeHtml(data.author || '未知作者')}</strong></div></div>
    <div class="result-actions"><a class="primary-button" href="${escapeHtml(downloadUrl(first, (data.title || 'media') + (type === 'image' ? '.jpg' : '.mp4')))}" download>下载${type === 'image' ? '首图' : '视频'}</a>${type === 'image' && urls.length > 1 ? `<button id="desktop-zip-btn" class="primary-button" type="button">打包下载图集 (${urls.length}张 ZIP)</button>` : ''}${musicHref ? `<a class="secondary-button" href="${escapeHtml(musicHref)}" download>下载音乐</a>` : ''}${commentsButton}<button class="secondary-button" type="button" data-copy-url="${escapeHtml(downloadUrl(first, (data.title || 'media') + (type === 'image' ? '.jpg' : '.mp4')))}">复制无水印链接</button><button class="secondary-button" type="button" data-open-url="${escapeHtml(rawUrl)}">打开原链接</button></div>
  `;
  addHistory({ rawUrl, data });
}

function platformName(url) {
  const host = (String(url).match(/^https?:\/\/([^/]+)/i) || [])[1] || '';
  if (host.includes('douyin')) return '抖音';
  if (host.includes('kuaishou')) return '快手';
  if (host.includes('bilibili') || host.includes('b23.tv')) return 'B站';
  if (host.includes('xiaohongshu') || host.includes('xhslink')) return '小红书';
  if (host.includes('weibo')) return '微博';
  if (host.includes('tiktok')) return 'TikTok';
  if (host.includes('huya')) return '虎牙';
  if (host.includes('douyu')) return '斗鱼';
  return '短视频';
}

async function parseSingle() {
  const url = cleanUrl(linkInput.value);
  if (!/^https?:\/\//i.test(url)) return notify(t(localeState.locale, 'invalidLink'));
  // 直播间链接自动切换到直播解析
  if (isLiveStreamUrl(url)) {
    setMode('live');
    await parseLive(url);
    return;
  }
  // 合集链接自动切换到合集解析
  if (/douyin\.com\/collection\//i.test(url) || /[?&]mix_id=\d+/i.test(url)) {
    setMode('collection');
    await parseCollection(url);
    return;
  }
  linkInput.value = url;
  state.activeMediaRefresh = false;
  state.currentSourceUrl = url;
  setBusy(true);
  try {
    const data = await parsers.parse(url);
    renderSingleResult(data, url);
    notify(t(localeState.locale, 'parsed'));
  } catch (error) {
    notify(error.message || t(localeState.locale, 'parseFailed'));
  } finally {
    setBusy(false);
  }
}

function profileItemId(item, index, pageIndex) {
  return String(item?.aweme_id || item?.source_url || `${state.profile?.url || 'profile'}-${pageIndex}-${index}`);
}

function renderProfile() {
  const profile = state.profile;
  if (profile?.kind !== state.mode) {
    const emptyLabel = t(localeState.locale, state.mode === 'collection' ? 'collectionEmpty' : 'profileEmpty');
    profilePanel.innerHTML = `<div class="workspace-empty"><strong>${escapeHtml(emptyLabel)}</strong></div>`;
    return;
  }
  const page = profile.pages[profile.current];
  const items = page?.items || [];
  const isCollection = profile.kind === 'collection';
  const head = isCollection && page?.collection
    ? `${page.collection.title || ''}${page.collection.author ? ` · ${page.collection.author}` : ''}`
    : (page?.profile?.author || '公开主页');
  const totalLabel = isCollection && page?.collection?.total ? ` · 共 ${page.collection.total} 集` : '';
  profilePanel.innerHTML = `
    <div class="panel-heading"><div><span class="panel-kicker">公开作品</span><h2>${escapeHtml(head)}</h2><p class="panel-note">第 ${profile.current + 1} 页 · ${items.length} 个作品${escapeHtml(totalLabel)}</p></div><div class="profile-tools"><label><input id="profile-select-all" type="checkbox"> 全选本页</label><button id="profile-selected-download" class="primary-button compact" type="button">下载选中</button><button id="profile-load-all" class="secondary-button compact" type="button">加载全部</button><button id="profile-all-download" class="secondary-button compact" type="button">全部下载</button></div></div>
    <div class="profile-list">${items.length ? items.map((item, index) => profileItemHtml(item, index, profile.current)).join('') : '<div class="empty-state">这一页没有可下载作品。</div>'}</div>
    <div class="pagination"><button id="profile-prev" class="secondary-button compact" type="button" ${profile.current === 0 ? 'disabled' : ''}>上一页</button><span>第 ${profile.current + 1} 页</span><button id="profile-next" class="primary-button compact" type="button" ${page?.has_more ? '' : 'disabled'}>下一页</button></div>
  `;
  bindProfileControls();
}

function profileItemHtml(item, index, pageIndex) {
  const type = mediaType(item);
  const urls = itemMedia(item);
  const thumb = item.cover || urls[0] || '';
  const checked = state.profile.selected.has(profileItemId(item, index, pageIndex));
  return `<article class="profile-item ${checked ? 'selected' : ''}" data-index="${index}"><input class="profile-checkbox" type="checkbox" ${checked ? 'checked' : ''} aria-label="选择作品"><img src="${escapeHtml(mediaUrl(thumb))}" alt="" loading="lazy"><div class="profile-copy"><h3>${escapeHtml(item.title || '未命名内容')}</h3><p>${type === 'image' ? `图集 ${urls.length} 张` : '视频'} · ${escapeHtml(item.author || '')}</p></div><button class="secondary-button compact" type="button" data-profile-download="${index}">下载</button></article>`;
}

async function parseProfile(url = cleanUrl(linkInput.value)) {
  if (!/^https?:\/\//i.test(url)) return notify(t(localeState.locale, 'invalidLink'));
  linkInput.value = url;
  setBusy(true, 'readingPage');
  try {
    const data = await parsers.parseProfile(url, 0);
    state.profile = { url, pages: [data], current: 0, selected: new Set(), kind: 'profile' };
    renderProfile();
    notify('主页第 1 页已完成');
  } catch (error) {
    notify(error.message || '主页解析失败');
  } finally {
    setBusy(false);
  }
}

async function parseCollection(url = cleanUrl(linkInput.value)) {
  if (!/^https?:\/\//i.test(url)) return notify(t(localeState.locale, 'invalidLink'));
  linkInput.value = url;
  setBusy(true, 'readingPage');
  try {
    const data = await parsers.parseCollection(url, 0);
    state.profile = { url, pages: [data], current: 0, selected: new Set(), kind: 'collection' };
    renderProfile();
    notify('合集第 1 页已完成');
  } catch (error) {
    notify(error.message || '合集解析失败');
  } finally {
    setBusy(false);
  }
}

function renderLive(data, rawUrl) {
  const live = data?.live || {};
  const streams = Array.isArray(data?.streams) ? data.streams : [];
  const isLive = live.status === 'live';
  const rows = streams.map((stream) => `<div class="live-stream"><span class="live-stream-format">${escapeHtml(String(stream.format || '').toUpperCase())}</span><div class="live-stream-copy"><strong>${escapeHtml(stream.quality || '默认画质')}</strong><span>${escapeHtml(String(stream.url || ''))}</span></div><button class="secondary-button compact" type="button" data-copy-url="${escapeHtml(String(stream.url || ''))}">复制地址</button><a class="secondary-button compact" href="${escapeHtml(String(stream.url || ''))}" target="_blank" rel="noopener">打开</a></div>`).join('');
  singleResult.hidden = false;
  singleResult.innerHTML = `
    <div class="result-heading"><div><span class="panel-kicker">直播间</span><h2>${escapeHtml(live.title || '未命名直播间')}</h2><p>${escapeHtml(live.anchor || '未知主播')} · ${isLive ? '直播中' : '未开播'}${live.viewers ? ` · 人气 ${escapeHtml(formatCount(live.viewers))}` : ''}</p></div><span class="result-type">${isLive ? 'LIVE' : 'OFFLINE'}</span></div>
    <div class="live-streams">${rows || '<div class="empty-state">当前未开播，开播后再来获取拉流地址。</div>'}</div>
    <div class="result-actions"><button class="secondary-button" type="button" data-open-url="${escapeHtml(rawUrl)}">打开原直播间</button></div>
  `;
}

async function parseLive(url = cleanUrl(linkInput.value)) {
  if (!/^https?:\/\//i.test(url)) return notify(t(localeState.locale, 'invalidLink'));
  linkInput.value = url;
  setBusy(true);
  try {
    const data = await parsers.parseLive(url);
    renderLive(data, url);
    notify('直播间解析完成');
  } catch (error) {
    notify(error.message || '直播解析失败');
  } finally {
    setBusy(false);
  }
}

async function requestListPage(profile, page) {
  return profile.kind === 'collection'
    ? parsers.parseCollection(profile.url, page.next_cursor)
    : parsers.parseProfile(profile.url, page.next_cursor);
}

async function loadAllProfilePages() {
  const profile = state.profile;
  if (!profile || state.busy) return;
  setBusy(true, 'loadingAll');
  try {
    while (profile.pages[profile.pages.length - 1]?.has_more) {
      const last = profile.pages[profile.pages.length - 1];
      profile.pages[profile.pages.length] = await requestListPage(profile, last);
      // 限速保护：页间稍作停顿，避免触发平台风控
      await new Promise((resolve) => setTimeout(resolve, 450));
    }
    const total = profile.pages.flatMap((page) => page.items || []).length;
    renderProfile();
    notify(`已加载全部 ${total} 个作品`);
  } catch (error) {
    notify(error.message || '加载失败');
  } finally {
    setBusy(false);
  }
}

async function loadNextProfilePage() {
  const profile = state.profile;
  const page = profile?.pages[profile.current];
  if (!profile || !page?.has_more || state.busy) return;
  const next = profile.current + 1;
  if (profile.pages[next]) {
    profile.current = next;
    renderProfile();
    return;
  }
  setBusy(true, 'readingPage', next + 1);
  try {
    profile.pages[next] = await requestListPage(profile, page);
    profile.current = next;
    renderProfile();
    notify(`第 ${next + 1} 页已完成`);
  } catch (error) {
    notify(error.message || '下一页解析失败');
  } finally {
    setBusy(false);
  }
}

function loadPreviousProfilePage() {
  if (!state.profile || state.profile.current === 0) return;
  state.profile.current -= 1;
  renderProfile();
}

function selectedProfileItems() {
  if (!state.profile) return [];
  return state.profile.pages.flatMap((page, pageIndex) => (page.items || []).filter((item, index) => state.profile.selected.has(profileItemId(item, index, pageIndex))));
}

async function downloadItem(item, index) {
  const extension = mediaType(item) === 'image' ? 'jpg' : 'mp4';
  const urls = itemMedia(item);
  for (let part = 0; part < urls.length; part += 1) {
    const name = `${(item.title || `media-${index + 1}`).replace(/[\\/:*?"<>|]/g, '_').slice(0, 80)}${part ? `-${part + 1}` : ''}.${extension}`;
    const anchor = document.createElement('a');
    anchor.href = downloadUrl(urls[part], name);
    anchor.download = name;
    anchor.target = '_blank';
    anchor.rel = 'noopener';
    document.body.append(anchor);
    anchor.click();
    anchor.remove();
    await new Promise((resolve) => setTimeout(resolve, 300));
  }
}

async function downloadProfileItems(items) {
  if (!items.length) return notify('请先选择作品');
  for (let index = 0; index < items.length; index += 1) await downloadItem(items[index], index);
  notify(`已发送 ${items.length} 个下载任务`);
}

function bindProfileControls() {
  const profile = state.profile;
  if (!profile) return;
  $('#profile-prev')?.addEventListener('click', loadPreviousProfilePage);
  $('#profile-next')?.addEventListener('click', loadNextProfilePage);
  $('#profile-selected-download')?.addEventListener('click', () => downloadProfileItems(selectedProfileItems()));
  $('#profile-load-all')?.addEventListener('click', loadAllProfilePages);
  $('#profile-all-download')?.addEventListener('click', async () => {
    const last = profile.pages[profile.pages.length - 1];
    if (last?.has_more) await loadAllProfilePages();
    await downloadProfileItems(profile.pages.flatMap((page) => page.items || []));
  });
  $('#profile-select-all')?.addEventListener('change', (event) => {
    const page = profile.pages[profile.current];
    (page.items || []).forEach((item, index) => {
      const id = profileItemId(item, index, profile.current);
      if (event.target.checked) profile.selected.add(id); else profile.selected.delete(id);
    });
    renderProfile();
  });
  $$('.profile-checkbox').forEach((checkbox) => checkbox.addEventListener('change', (event) => {
    const index = Number(event.target.closest('.profile-item').dataset.index);
    const item = profile.pages[profile.current].items[index];
    const id = profileItemId(item, index, profile.current);
    if (event.target.checked) profile.selected.add(id); else profile.selected.delete(id);
    event.target.closest('.profile-item').classList.toggle('selected', event.target.checked);
  }));
  $$('[data-profile-download]').forEach((button) => button.addEventListener('click', () => downloadItem(profile.pages[profile.current].items[Number(button.dataset.profileDownload)], Number(button.dataset.profileDownload))));
}

function addHistory(entry) {
  const item = { rawUrl: entry.rawUrl, title: entry.data.title || '', author: entry.data.author || '', time: entry.data.time || 0, cover: entry.data.cover || '', mediaType: mediaType(entry.data), savedAt: Date.now() };
  state.history = [item, ...state.history.filter((old) => old.rawUrl !== item.rawUrl)].slice(0, 100);
  saveHistory();
}

// ---- 发现：热榜与搜索（仅用户主动查询，按需分页，不落库） ----
const discoverPanel = $('#discover-panel');
const discoverInput = $('#discover-input');
const discoverSummary = $('#discover-summary');
const discoverNote = $('#discover-note');

discoverPanel.addEventListener('error', (event) => {
  if (!(event.target instanceof HTMLImageElement) || !event.target.matches('.discover-item img')) return;
  const fallback = event.target.dataset.fallback;
  if (fallback && !event.target.dataset.fallbackTried && safeHttpUrl(fallback)) {
    event.target.dataset.fallbackTried = 'true';
    event.target.src = fallback;
    return;
  }
  const placeholder = document.createElement('span');
  placeholder.className = 'discover-thumb-placeholder';
  placeholder.setAttribute('aria-hidden', 'true');
  event.target.replaceWith(placeholder);
}, true);

function renderDiscover() {
  const d = state.discover;
  discoverSummary.textContent = d.tab === 'hot'
    ? (d.platform === 'bilibili' ? 'B站综合热门' : '抖音热点榜')
    : `搜索「${d.keyword}」 · 共 ${d.total} 条`;
  discoverNote.textContent = d.tab === 'hot'
    ? (d.platform === 'douyin' ? '条目为热点词，可打开抖音搜索页。' : '用户主动查看，按需加载下一页。')
    : '点击「解析」直接解析无水印媒体。';
  if (!d.items.length) {
    discoverPanel.innerHTML = '<div class="workspace-empty"><div><strong>暂无内容</strong><span>换个关键词或平台再试试。</span></div></div>';
    return;
  }
  discoverPanel.innerHTML = `<div class="discover-list">${d.items.map((item) => {
    const parseable = /bilibili\.com|b23\.tv/i.test(item.url);
    const cover = safeHttpUrl(item.cover) ? mediaUrl(item.cover) : '';
    return `<article class="discover-item">${cover ? `<img src="${escapeHtml(cover)}" data-fallback="${escapeHtml(item.cover)}" alt="" loading="lazy">` : '<span class="discover-thumb-placeholder" aria-hidden="true"></span>'}<div class="profile-copy"><h3>${escapeHtml(item.title)}</h3><p>${escapeHtml(item.author || '')}${item.hot ? ` · 热度 ${escapeHtml(formatCount(item.hot))}` : ''}</p></div><div class="discover-actions">${parseable ? `<button class="primary-button compact" type="button" data-discover-parse="${escapeHtml(item.url)}">解析</button>` : ''}<a class="secondary-button compact" href="${escapeHtml(item.url)}" target="_blank" rel="noopener">打开</a></div></article>`;
  }).join('')}</div>${d.hasMore ? '<div class="discover-more"><button id="discover-more" class="secondary-button" type="button">加载更多</button></div>' : ''}`;
  $('#discover-more')?.addEventListener('click', () => {
    const d2 = state.discover;
    d2.page += 1;
    loadDiscover(false);
  });
}

async function loadDiscover(reset) {
  const d = state.discover;
  if (d.loading) return;
  if (d.tab === 'search' && !d.keyword) return notify('请输入搜索关键词');
  if (reset) {
    d.page = 1;
    d.items = [];
  }
  d.loading = true;
  discoverPanel.innerHTML = '<div class="workspace-empty"><div><strong>正在加载…</strong></div></div>';
  try {
    const data = d.tab === 'hot'
      ? await parsers.hotList(d.platform, d.page)
      : await parsers.search(d.platform, d.keyword, d.page);
    d.items = reset ? (data.items || []) : d.items.concat(data.items || []);
    d.hasMore = Boolean(data.has_more);
    d.total = data.total || d.items.length;
    renderDiscover();
  } catch (error) {
    notify(error.message || '获取失败');
    renderDiscover();
  } finally {
    d.loading = false;
  }
}

function runDiscover() {
  const d = state.discover;
  d.keyword = (discoverInput?.value || '').trim();
  if (d.tab === 'search' && !d.keyword) return notify('请输入搜索关键词');
  loadDiscover(true);
}

$$('[data-discover-tab]').forEach((button) => button.addEventListener('click', () => {
  state.discover.tab = button.dataset.discoverTab;
  $$('[data-discover-tab]').forEach((b) => b.classList.toggle('active', b === button));
  if (state.discover.tab === 'hot') loadDiscover(true);
}));
$$('[data-discover-platform]').forEach((button) => button.addEventListener('click', () => {
  state.discover.platform = button.dataset.discoverPlatform;
  $$('[data-discover-platform]').forEach((b) => b.classList.toggle('active', b === button));
  loadDiscover(true);
}));
$('#discover-search')?.addEventListener('click', runDiscover);
discoverInput?.addEventListener('keydown', (event) => { if (event.key === 'Enter') runDiscover(); });

// ---- B站公开评论（解析结果页按需查看） ----
const commentsPanel = document.createElement('section');
commentsPanel.className = 'result-panel';
commentsPanel.hidden = true;
if (singleResult) singleResult.after(commentsPanel);
let commentsLoading = false;
let commentsPage = 1;
let commentsUrl = '';

function renderComments(url, data, existing) {
  const comments = existing.concat(Array.isArray(data.comments) ? data.comments : []);
  const hasMore = Boolean(data.has_more);
  commentsPanel.innerHTML = `
    <div class="panel-heading"><div><span class="panel-kicker">公开评论</span><h2>共 ${escapeHtml(formatCount(data.total))} 条</h2><p class="panel-note">仅查看公开评论，不会保存。</p></div></div>
    <div class="comment-list">${comments.map((item) => `<article class="comment-item"><img src="${escapeHtml(item.avatar)}" alt="" loading="lazy"><div class="profile-copy"><h3>${escapeHtml(item.user || '匿名用户')} · 赞 ${escapeHtml(formatCount(item.likes))}</h3><p>${escapeHtml(item.content || '')}</p></div><span class="comment-time">${escapeHtml(formatDate(item.time))}</span></article>`).join('')}</div>
    ${hasMore ? '<div class="discover-more"><button id="comments-more" class="secondary-button" type="button">加载更多</button></div>' : ''}
  `;
  $('#comments-more')?.addEventListener('click', () => {
    commentsPage += 1;
    loadComments(url, comments);
  });
}

async function loadComments(url, existing = []) {
  if (commentsLoading || !url) return;
  commentsLoading = true;
  if (!existing.length) {
    commentsPage = 1;
    commentsPanel.hidden = false;
    commentsPanel.innerHTML = '<div class="workspace-empty"><div><strong>正在获取评论…</strong></div></div>';
  }
  try {
    const data = await parsers.comments(url, commentsPage);
    renderComments(url, data, existing);
  } catch (error) {
    notify(error.message || '评论获取失败');
  } finally {
    commentsLoading = false;
  }
}

document.addEventListener('click', (event) => {
  const parse = event.target.closest('[data-discover-parse]');
  if (parse) {
    setMode('single');
    linkInput.value = parse.dataset.discoverParse;
    parseSingle();
    return;
  }
  const commentsButton = event.target.closest('[data-comments-url]');
  if (commentsButton) {
    commentsUrl = commentsButton.dataset.commentsUrl;
    loadComments(commentsUrl, []);
  }

  const zipBtn = event.target.closest('#desktop-zip-btn');
  if (zipBtn && state.currentSourceUrl) {
    const urls = singleResult?.querySelectorAll('.image-tile img') ? Array.from(singleResult.querySelectorAll('.image-tile img')).map((img) => img.src) : [];
    if (!urls.length) return;
    const baseTitle = ($('#single-result h2')?.textContent || 'image-gallery').replace(/[\\/:*?"<>|]/g, '_').slice(0, 80);
    const origText = zipBtn.textContent;
    zipBtn.disabled = true;
    (async () => {
      try {
        const entries = [];
        for (let i = 0; i < urls.length; i += 1) {
          zipBtn.textContent = `打包中 (${i + 1}/${urls.length})…`;
          const res = await fetch(urls[i]);
          if (!res.ok) throw new Error(`第 ${i + 1} 张图片下载失败`);
          const buf = await res.arrayBuffer();
          entries.push({ name: `${baseTitle}-${String(i + 1).padStart(2, '0')}.jpg`, data: new Uint8Array(buf) });
        }
        zipBtn.textContent = '生成 ZIP…';
        const blob = parsers.createZipBlob(entries);
        const blobUrl = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = blobUrl;
        a.download = `${baseTitle}.zip`;
        document.body.append(a);
        a.click();
        a.remove();
        setTimeout(() => URL.revokeObjectURL(blobUrl), 30000);
        notify('图集打包下载成功');
      } catch (err) {
        notify('图集打包失败: ' + (err.message || err));
      } finally {
        zipBtn.textContent = origText;
        zipBtn.disabled = false;
      }
    })();
  }
});

function renderHistory() {
  historyPanel.innerHTML = state.history.length ? `<div class="panel-heading"><div><span class="panel-kicker">本地记录</span><h2>最近处理</h2></div><button id="clear-history" class="secondary-button compact" type="button">清空记录</button></div><div class="history-list">${state.history.map((item, index) => `<article class="history-item"><div><span class="history-index">${String(index + 1).padStart(2, '0')}</span><div><h3>${escapeHtml(item.title || '未命名内容')}</h3><p>${escapeHtml(item.author || '未知作者')} · ${escapeHtml(item.mediaType === 'image' ? '图集' : '视频')} · ${formatDate(item.time)}</p></div></div><button class="secondary-button compact" type="button" data-history-url="${escapeHtml(item.rawUrl)}">重新解析</button></article>`).join('')}</div>` : '<div class="empty-state">还没有解析记录。</div>';
  $('#clear-history')?.addEventListener('click', () => { state.history = []; saveHistory(); renderHistory(); });
  $$('[data-history-url]').forEach((button) => button.addEventListener('click', () => { setMode('single'); linkInput.value = button.dataset.historyUrl; parseButton.click(); }));
}

function checkHealth() {
  try {
    const count = parsers.getSupportedPlatforms().length;
    setServiceState(t(localeState.locale, 'localService').replace('{count}', count), 'ok');
  } catch {
    setServiceState(t(localeState.locale, 'serviceOffline'), 'offline');
  }
}

let updateState = { status: 'idle' };

function openUpdateRelease() {
  const tag = /^\d+\.\d+\.\d+$/.test(updateState.version) ? 'tag/v' + updateState.version : 'latest';
  shell.openExternal?.(`${RELEASES_URL}/${tag}`);
}

function deferUpdate() {
  if (updateState.version) localStorage.setItem(UPDATE_DEFERRED_STORAGE, updateState.version);
  $('#update-dialog').close();
}

$('#update-dialog-later').addEventListener('click', deferUpdate);
$('#update-dialog').addEventListener('cancel', deferUpdate);
$('#update-dialog-action').addEventListener('click', () => {
  $('#update-dialog').close();
  if (updateState.canDownload) {
    setView('about');
    shell.downloadUpdate?.();
  } else {
    openUpdateRelease();
  }
});

function setUpdateStatus(payload) {
  if (payload && payload.status) updateState = payload;
  const s = updateState.status;
  if (s === 'available' && payload && localStorage.getItem(UPDATE_DEFERRED_STORAGE) !== updateState.version) {
    const releaseDate = new Date(updateState.releaseDate);
    const date = Number.isNaN(releaseDate.getTime()) ? '' : releaseDate.toLocaleDateString(localeState.locale);
    const size = updateState.size ? `${(updateState.size / 1024 / 1024).toFixed(1)} MB` : '';
    $('#update-dialog-details').textContent = [
      `v${__APP_VERSION__} → v${updateState.version}`, date, size, updateState.releaseNotes,
    ].filter(Boolean).join('\n');
    $('#update-dialog-action').textContent = updateState.canDownload ? '立即下载' : '前往 GitHub 下载';
    if (/^\d+\.\d+\.\d+$/.test(updateState.version)) {
      $('#update-dialog-release').href = `${RELEASES_URL}/tag/v${updateState.version}`;
    }
    const updateDialog = $('#update-dialog');
    if (!updateDialog.open) updateDialog.showModal();
  }
  const el = $('#update-status');
  if (!el) return;
  const install = $('#update-install');
  const download = $('#update-download');
  const check = $('#check-update');
  install.hidden = s !== 'downloaded';
  download.hidden = s !== 'available';
  download.textContent = updateState.canDownload ? '立即下载' : '前往 GitHub 下载';
  check.disabled = ['checking', 'downloading', 'downloaded'].includes(s);
  if (s === 'checking') el.textContent = '正在检查更新…';
  else if (s === 'available') el.textContent = `发现新版本 v${updateState.version}`;
  else if (s === 'downloading') {
    const speed = formatTransferRate(updateState.bytesPerSecond);
    const remaining = updateState.total > 0 && updateState.bytesPerSecond > 0
      ? `，约剩余 ${formatDuration((updateState.total - updateState.transferred) / updateState.bytesPerSecond)}`
      : '';
    el.textContent = `正在下载更新 ${updateState.percent || 0}%${speed ? ` · ${speed}` : ''}${remaining}`;
  }
  else if (s === 'downloaded') el.textContent = `新版本 v${updateState.version} 已就绪`;
  else if (s === 'none') el.textContent = '当前已是最新版本。';
  else if (s === 'dev') el.textContent = '开发构建可通过正式发布页查看版本。';
  else if (s === 'error') el.textContent = updateState.message || '检查更新失败。';
  else el.textContent = '尚未检查更新。';
}

function formatTransferRate(value) {
  const bytes = Number(value) || 0;
  if (bytes <= 0) return '';
  if (bytes >= 1024 * 1024) return `${(bytes / 1024 / 1024).toFixed(1)} MB/s`;
  return `${Math.max(1, Math.round(bytes / 1024))} KB/s`;
}

function formatDuration(seconds) {
  const value = Math.max(0, Math.round(Number(seconds) || 0));
  if (value < 60) return `${value} 秒`;
  const minutes = Math.floor(value / 60);
  const remainder = value % 60;
  return `${minutes} 分 ${remainder} 秒`;
}

// 更新日志 Markdown 最小渲染：先整体转义，再支持标题/列表/引用/加粗/行内代码，防注入。
function renderChangelogMarkdown(text) {
  const escapeHtml = (value) => String(value).replace(/[&<>"']/g, (ch) => (
    { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]
  ));
  const inline = (value) => escapeHtml(value)
    .replace(/`([^`]+)`/g, '<code>$1</code>')
    .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
  const out = [];
  let listOpen = false;
  const closeList = () => { if (listOpen) { out.push('</ul>'); listOpen = false; } };
  for (const raw of String(text || '').split(/\r?\n/)) {
    const line = raw.trimEnd();
    if (!line.trim()) { closeList(); continue; }
    const heading = line.match(/^(#{1,6})\s+(.*)$/);
    if (heading) {
      closeList();
      // 顶层 H1 与面板标题重复，跳过；## → h3，### → h4
      if (heading[1].length === 1) continue;
      const level = Math.min(heading[1].length + 1, 6);
      out.push(`<h${level}>${inline(heading[2])}</h${level}>`);
      continue;
    }
    const bullet = line.match(/^\s*[-*]\s+(.*)$/);
    if (bullet) {
      if (!listOpen) { out.push('<ul>'); listOpen = true; }
      out.push(`<li>${inline(bullet[1])}</li>`);
      continue;
    }
    const quote = line.match(/^>\s?(.*)$/);
    if (quote) { closeList(); out.push(`<blockquote>${inline(quote[1])}</blockquote>`); continue; }
    closeList();
    out.push(`<p>${inline(line)}</p>`);
  }
  closeList();
  return out.join('') || '<p>暂无更新日志。</p>';
}

async function renderAbout() {
  const panel = $('#about-panel');
  if (!panel) return;
  panel.innerHTML = `
    <div class="about-card">
      <div class="panel-heading">
        <div class="panel-title">
          <span class="panel-kicker">版本信息</span>
          <h2>去水印解析</h2>
          <p class="panel-note" id="about-version">当前版本 —</p>
        </div>
        <button id="check-update" class="primary-button" type="button">检查更新</button>
      </div>
      <p id="update-status" class="update-status" role="status">尚未检查更新。</p>
      <label class="panel-note"><input id="check-update-on-start" type="checkbox"> 启动时检查更新</label>
      <button id="update-download" class="secondary-button" type="button" hidden>前往 GitHub 下载</button>
      <button id="update-install" class="secondary-button" type="button" hidden>立即重启安装</button>
    </div>
    <div class="about-card">
      <div class="panel-title">
        <span class="panel-kicker">账号设置</span>
        <h2>微博凭据</h2>
        <p class="panel-note">目前需要登录态的平台只有微博。先在微博网页版登录，再把 Cookie 粘贴到这里；凭据仅保存在本机。</p>
      </div>
      <div class="update-status" id="about-weibo-login-status">网页登录状态检查中…</div>
      <div class="about-actions"><button id="about-weibo-login" class="primary-button compact" type="button">登录微博网页</button><button id="about-weibo-refresh" class="secondary-button compact" type="button">刷新状态</button><button id="about-weibo-logout" class="secondary-button compact" type="button">退出网页登录</button></div>
      <div class="update-status" id="about-weibo-status">${localStorage.getItem(WEIBO_COOKIE_STORAGE) ? '手工凭据已配置' : '手工凭据未配置'}</div>
      <input id="about-weibo-cookie" type="password" placeholder="粘贴微博网页版 Cookie" value="${escapeHtml(localStorage.getItem(WEIBO_COOKIE_STORAGE) || '')}" autocomplete="off" spellcheck="false" />
      <p class="panel-note">优先使用网页登录；手工 Cookie 仅作为备用 fallback。</p>
      <div class="about-actions"><button id="about-weibo-save" class="secondary-button compact" type="button">保存手工凭据</button><button id="about-weibo-clear" class="secondary-button compact" type="button">清除手工凭据</button></div>
    </div>
    <div class="about-card changelog-card">
      <div class="panel-title">
        <span class="panel-kicker">更新日志</span>
        <h2>最近更新</h2>
      </div>
      <div id="about-changelog" class="changelog"></div>
    </div>
  `;
  const version = await (shell.getVersion ? shell.getVersion() : '—');
  const versionEl = $('#about-version');
  if (versionEl) versionEl.textContent = `当前版本 v${version}`;
  const changelog = await (shell.getChangelog ? shell.getChangelog() : '暂无更新日志。');
  const logEl = $('#about-changelog');
  if (logEl) logEl.innerHTML = renderChangelogMarkdown(changelog);
  const bufferedUpdate = await (shell.getUpdateStatus ? shell.getUpdateStatus() : null);
  if (bufferedUpdate?.status && bufferedUpdate.status !== 'idle') {
    updateState = bufferedUpdate;
    setUpdateStatus();
  }
  const autoCheck = $('#check-update-on-start');
  autoCheck.checked = localStorage.getItem(UPDATE_AUTOCHECK_STORAGE) !== 'off';
  autoCheck.addEventListener('change', () => localStorage.setItem(UPDATE_AUTOCHECK_STORAGE, autoCheck.checked ? 'on' : 'off'));
  $('#check-update')?.addEventListener('click', () => shell.checkForUpdates?.());
  $('#update-download')?.addEventListener('click', () => {
    if (updateState.canDownload) shell.downloadUpdate?.();
    else openUpdateRelease();
  });
  $('#update-install')?.addEventListener('click', () => shell.quitAndInstall?.());
  const refreshWeiboLoginStatus = async () => {
    const loginStatus = $('#about-weibo-login-status');
    if (!loginStatus || !shell.getWeiboLoginStatus) return;
    try {
      const status = await shell.getWeiboLoginStatus();
      loginStatus.textContent = status?.loggedIn ? `网页登录已登录（${status.cookieCount || 0} 个会话项）` : '网页登录未登录';
    } catch (error) {
      loginStatus.textContent = error.message || '网页登录状态检查失败';
    }
  };
  $('#about-weibo-login')?.addEventListener('click', async () => {
    await shell.openWeiboLogin?.();
    await refreshWeiboLoginStatus();
  });
  $('#about-weibo-refresh')?.addEventListener('click', refreshWeiboLoginStatus);
  $('#about-weibo-logout')?.addEventListener('click', async () => {
    await shell.logoutWeibo?.();
    await refreshWeiboLoginStatus();
    notify('微博网页登录已退出');
  });
  $('#about-weibo-save')?.addEventListener('click', () => {
    const value = $('#about-weibo-cookie').value.trim();
    if (value) localStorage.setItem(WEIBO_COOKIE_STORAGE, value); else localStorage.removeItem(WEIBO_COOKIE_STORAGE);
    parsers.setWeiboCookie(value);
    const status = $('#about-weibo-status');
    if (status) status.textContent = value ? '手工凭据已配置' : '手工凭据未配置';
    notify(value ? '微博手工凭据已保存' : '微博手工凭据已清空');
  });
  $('#about-weibo-clear')?.addEventListener('click', () => {
    localStorage.removeItem(WEIBO_COOKIE_STORAGE);
    parsers.setWeiboCookie('');
    $('#about-weibo-cookie').value = '';
    const status = $('#about-weibo-status');
    if (status) status.textContent = '手工凭据未配置';
    notify('微博手工凭据已清空');
  });
  shell.onWeiboLoginChanged?.(refreshWeiboLoginStatus);
  await refreshWeiboLoginStatus();
  setUpdateStatus();
}

function setView(view) {
  state.view = view;
  parsePanel.hidden = view !== 'single' && view !== 'profile';
  $$('.nav-button').forEach((button) => button.classList.toggle('active', button.dataset.view === view));
  $$('[data-view-panel]').forEach((panel) => { panel.hidden = panel.dataset.viewPanel !== view; panel.classList.toggle('active', panel.dataset.viewPanel === view); });
  if (view === 'profile') renderProfile();
  if (view === 'discover' && !state.discover.items.length) loadDiscover(true);
  if (view === 'history') renderHistory();
  if (view === 'about') renderAbout();
}

function isLiveStreamUrl(url) {
  let host = '';
  try { host = new URL(url).hostname; } catch { return false; }
  host = host.toLowerCase().replace(/^www\./, '');
  return host === 'live.douyin.com' || host === 'live.bilibili.com' || host === 'huya.com' || host === 'douyu.com'
    || host.endsWith('.live.douyin.com') || host.endsWith('.live.bilibili.com') || host.endsWith('.huya.com') || host.endsWith('.douyu.com');
}

const parseLabels = {
  single: { button: 'start', hint: 'inputHint' },
  profile: { button: 'parseProfile', hint: 'profileHint' },
  collection: { button: 'parseCollection', hint: 'collectionHint' },
  live: { button: 'parseLive', hint: 'liveHint' },
};

function syncParseControls() {
  const { locale } = localeState;
  const labels = parseLabels[state.mode];
  inputHint.textContent = t(locale, labels.hint);
  parseButton.disabled = Boolean(state.busy);
  parseButton.textContent = state.busy
    ? t(locale, state.busy.message).replace('{page}', state.busy.page)
    : t(locale, labels.button);
}

function setMode(mode) {
  state.mode = mode;
  $$('[data-mode]').forEach((button) => button.classList.toggle('active', button.dataset.mode === mode));
  syncParseControls();
  if (mode === 'profile' || mode === 'collection') setView('profile'); else setView('single');
}

$$('[data-view]').forEach((button) => button.addEventListener('click', () => {
  const view = button.dataset.view;
  if (view === 'single' || view === 'profile') setMode(view);
  else setView(view);
}));
$$('[data-mode]').forEach((button) => button.addEventListener('click', () => setMode(button.dataset.mode)));
parseButton.addEventListener('click', () => state.mode === 'profile' ? parseProfile() : state.mode === 'collection' ? parseCollection() : state.mode === 'live' ? parseLive() : parseSingle());
pasteInputButton?.addEventListener('click', async () => {
  try {
    const text = await navigator.clipboard?.readText();
    if (!text) return notify('剪贴板为空');
    linkInput.value = cleanUrl(text);
    linkInput.focus();
    notify('已从剪贴板粘贴');
  } catch {
    notify('无法访问剪贴板，请手动粘贴');
  }
});
clearInputButton.addEventListener('click', () => { linkInput.value = ''; linkInput.focus(); });
linkInput.addEventListener('paste', (event) => { const value = event.clipboardData?.getData('text') || ''; if (value) { event.preventDefault(); linkInput.value = cleanUrl(value); } });
linkInput.addEventListener('input', () => { const value = cleanUrl(linkInput.value); if (/^https?:\/\//i.test(value) && value !== linkInput.value) linkInput.value = value; });
linkInput.addEventListener('keydown', (event) => { if (event.key === 'Enter') parseButton.click(); });
document.addEventListener('keydown', (event) => {
  if (!event.ctrlKey || event.altKey || event.key.toLowerCase() !== 'l' || $('#update-dialog').open) return;
  event.preventDefault();
  if (parsePanel.hidden) setMode('single');
  linkInput.focus();
  linkInput.select();
});
parsePanel.addEventListener('dragover', (event) => event.preventDefault());
parsePanel.addEventListener('drop', async (event) => {
  event.preventDefault();
  const transfer = event.dataTransfer;
  if (!transfer) return;
  const file = transfer.files[0];
  let text = transfer.getData('text/uri-list') || transfer.getData('text/plain');
  if (file) {
    if (!/\.txt$/i.test(file.name) || file.size > 64 * 1024) return notify('请拖入链接或不超过 64 KiB 的文本文件');
    try { text = await file.text(); } catch { return notify('无法读取文本文件'); }
  }
  const url = cleanUrl(text);
  if (!safeHttpUrl(url)) return notify(t(localeState.locale, 'invalidLink'));
  linkInput.value = url;
  linkInput.focus();
});
document.addEventListener('click', async (event) => {
  const copy = event.target.closest('[data-copy-url]');
  const open = event.target.closest('[data-open-url]');
  if (copy) { await navigator.clipboard?.writeText(copy.dataset.copyUrl); notify('链接已复制'); }
  if (open) { if (window.appShell?.openExternal) window.appShell.openExternal(open.dataset.openUrl); else window.open(open.dataset.openUrl, '_blank', 'noopener'); }
});
document.addEventListener('error', async (event) => {
  if (!(event.target instanceof HTMLVideoElement) || !event.target.closest('#single-result')) return;
  if (!state.currentSourceUrl || state.activeMediaRefresh) {
    notify('视频播放失败，源站媒体地址可能已失效。');
    return;
  }
  state.activeMediaRefresh = true;
  notify('视频地址已失效，正在自动刷新…');
  try {
    const data = await parsers.parse(state.currentSourceUrl, { force: true });
    renderSingleResult(data, state.currentSourceUrl);
    notify('视频地址已刷新，请重新播放。');
  } catch (error) {
    notify(error.message || '无法刷新视频地址，请重新解析链接。');
  }
}, true);
$('#theme-button').addEventListener('click', () => { state.theme = state.theme === 'dark' ? 'light' : 'dark'; applyTheme(); });

function applyTheme() {
  document.documentElement.dataset.theme = state.theme;
  localStorage.setItem(THEME_STORAGE, state.theme);
}

applyAppLocale();
applyTheme();
renderHistory();
shell.onUpdateStatus?.(setUpdateStatus);
if (localStorage.getItem(UPDATE_AUTOCHECK_STORAGE) !== 'off') shell.checkForUpdates?.();
