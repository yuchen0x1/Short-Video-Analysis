import './styles.css';
import { Capacitor, registerPlugin } from '@capacitor/core';
import * as parsers from '@parsers';
import { capacitorHttpTransport, initMediaProxy, ensureMediaProxy, mediaUrl, isNative } from './native.js';
import { checkForUpdate, UPDATE_REPOSITORY } from '@update-checker';

const HISTORY_KEY = 'qushuiyin-mobile-history-v1';
const WEIBO_COOKIE_KEY = 'qushuiyin-weibo-cookie-v1';
const URL_PATTERN = /https?:\/\/[^\s"'<>，。；：！？【】（）《》]+/i;
// 原生壳内通过系统 DownloadManager 下载；浏览器环境退回 <a download>
const DownloadBridge = registerPlugin('DownloadBridge');

// 解析完全在本地完成：注入原生网络传输层 + 恢复微博 Cookie 设置
parsers.setTransport(capacitorHttpTransport);
parsers.setWeiboCookie(localStorage.getItem(WEIBO_COOKIE_KEY) || '');
const mediaProxyReady = isNative ? initMediaProxy() : Promise.resolve(true);

const state = { view: 'parse', mode: 'single', profile: null, history: readHistory(), busy: false, update: { status: 'idle' } };
const app = document.querySelector('#app');

app.innerHTML = `<div class="mobile-app"><header class="mobile-header"><div><span class="mobile-eyebrow">NO-WATERMARK / MOBILE</span><h1>去水印解析</h1></div><span id="mobile-status" class="mobile-status">本地解析</span></header><main class="mobile-main"><section class="mobile-view active" data-view="parse"><div class="welcome-line"><span>今天整理什么？</span><strong id="mobile-mode-label">单条解析</strong></div><div class="mobile-input-panel"><div class="mobile-switch"><button class="mobile-switch-button active" data-mode="single" type="button">视频</button><button class="mobile-switch-button" data-mode="profile" type="button">主页</button></div><label for="mobile-input">链接</label><div class="mobile-input-wrap"><textarea id="mobile-input" rows="3" placeholder="粘贴分享链接或整段文案"></textarea><button id="mobile-clear" type="button" aria-label="清除">清除</button></div><button id="mobile-parse" class="mobile-primary" type="button">开始解析</button><p id="mobile-hint">粘贴后会自动保留链接。</p></div><section id="mobile-result" class="mobile-result" hidden></section></section><section class="mobile-view" data-view="profile" hidden><div class="mobile-section-title"><span>PUBLIC PROFILE</span><h2>主页作品</h2><p>下一页才解析，上一页直接恢复。</p></div><section id="mobile-profile" class="mobile-profile"></section></section><section class="mobile-view" data-view="history" hidden><div class="mobile-section-title"><span>LOCAL HISTORY</span><h2>最近处理</h2><p>只保存在当前手机。</p></div><section id="mobile-history" class="mobile-history"></section></section><section class="mobile-view" data-view="me" hidden><div class="mobile-section-title"><span>ABOUT APP</span><h2>关于</h2><p>解析在手机本地完成，无需任何服务器。</p></div><section id="mobile-settings" class="mobile-settings"><strong>去水印解析 · v${__APP_VERSION__}</strong><p>支持 ${parsers.getSupportedPlatforms().length} 个平台，本地解析、离线历史。</p><div id="mobile-update" class="mobile-update" aria-live="polite"><strong>在线更新</strong><p id="mobile-update-status">正在准备版本检查…</p><button id="mobile-update-check" type="button">检查更新</button><button id="mobile-update-download" class="mobile-primary-small" type="button" hidden>下载最新版本</button></div><label for="mobile-weibo-cookie">微博凭据</label><p id="mobile-weibo-status">${localStorage.getItem(WEIBO_COOKIE_KEY) ? '微博凭据已配置' : '微博凭据未配置'}</p><input id="mobile-weibo-cookie" type="password" placeholder="微博网页版登录后复制 Cookie 粘贴到这里" autocomplete="off" spellcheck="false"><p>目前需要登录态的平台只有微博；其他平台使用公开分享链接解析。</p><button id="mobile-weibo-save" type="button">保存凭据</button><button id="mobile-weibo-clear" type="button">清除凭据</button></section></section></main><nav class="mobile-tabbar"><button class="mobile-tab active" data-view="parse" type="button"><span>解析</span></button><button class="mobile-tab" data-view="profile" type="button"><span>主页</span></button><button class="mobile-tab" data-view="history" type="button"><span>记录</span></button><button class="mobile-tab" data-view="me" type="button"><span>关于</span></button></nav><div id="mobile-toast" class="mobile-toast" hidden></div></div>`;

const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
const input = $('#mobile-input');
const parseButton = $('#mobile-parse');
const result = $('#mobile-result');
const profileRoot = $('#mobile-profile');
const toast = $('#mobile-toast');

const mobileHeader = document.querySelector('.mobile-header > div');
if (mobileHeader) mobileHeader.insertAdjacentHTML('beforeend', `<span class="mobile-version">v${__APP_VERSION__}</span>`);
const mobileQuickStats = document.createElement('div');
mobileQuickStats.className = 'mobile-quick-stats';
mobileQuickStats.setAttribute('aria-label', '工作台能力');
mobileQuickStats.innerHTML = `<div><strong>${parsers.getSupportedPlatforms().length}</strong><span>支持平台</span></div><div><strong>本地</strong><span>优先解析</span></div><div><strong>20</strong><span>批量上限</span></div>`;
document.querySelector('.welcome-line')?.after(mobileQuickStats);
const mobileInputHint = $('#mobile-hint');
if (mobileInputHint) mobileInputHint.insertAdjacentHTML('afterend', '<div class="mobile-input-context"><span><i></i>支持视频与图集</span><span><i></i>链接仅在本机处理</span></div>');

function readHistory() { try { const value = JSON.parse(localStorage.getItem(HISTORY_KEY) || '[]'); return Array.isArray(value) ? value : []; } catch { return []; } }
function saveHistory() { localStorage.setItem(HISTORY_KEY, JSON.stringify(state.history.slice(0, 20))); }
function cleanUrl(text) { const match = String(text || '').match(URL_PATTERN); return (match ? match[0] : String(text || '').trim()).replace(/[),.。]+$/, ''); }
function typeOf(item) { return item?.media_type === 'image' ? 'image' : 'video'; }
function urlsOf(item) { return Array.isArray(item?.images) && item.images.length ? item.images : item?.url ? [item.url] : []; }
function esc(value) { return String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;'); }
function dateOf(value) { if (!value) return '-'; const date = new Date(Number(value) * 1000); return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleDateString('zh-CN'); }
function notify(message) { toast.textContent = message; toast.hidden = false; clearTimeout(notify.timer); notify.timer = setTimeout(() => { toast.hidden = true; }, 2200); }
function setBusy(on, text = '解析中…') { state.busy = on; parseButton.disabled = on; parseButton.textContent = on ? text : state.mode === 'profile' ? '解析主页' : '开始解析'; }

function updateWeiboStatus() { const value = localStorage.getItem(WEIBO_COOKIE_KEY) || ''; const inputEl = $('#mobile-weibo-cookie'); const statusEl = $('#mobile-weibo-status'); if (inputEl) inputEl.value = value; if (statusEl) statusEl.textContent = value ? '微博凭据已配置' : '微博凭据未配置'; }
function renderUpdateStatus() { const status = $('#mobile-update-status'); const download = $('#mobile-update-download'); if (!status) return; const update = state.update; if (update.status === 'checking') status.textContent = '正在检查最新版本…'; else if (update.status === 'available') status.textContent = `发现新版本 v${update.version}`; else if (update.status === 'none') status.textContent = `当前已是最新版本 v${update.version || __APP_VERSION__}`; else if (update.status === 'error') status.textContent = update.message || '版本检查失败，请稍后重试'; else status.textContent = '尚未检查最新版本'; if (download) download.hidden = update.status !== 'available'; }
async function checkMobileUpdate({ silent = false } = {}) { state.update = { status: 'checking' }; renderUpdateStatus(); try { const repository = __UPDATE_API_URL__ ? { ...UPDATE_REPOSITORY, apiUrl: __UPDATE_API_URL__ } : UPDATE_REPOSITORY; const result = await checkForUpdate({ currentVersion: __APP_VERSION__, repository, request: isNative ? capacitorHttpTransport.request : undefined }); state.update = { status: result.updateAvailable ? 'available' : 'none', version: result.latestVersion, info: result }; renderUpdateStatus(); if (!silent) notify(result.updateAvailable ? `发现新版本 v${result.latestVersion}` : '当前已是最新版本'); } catch (error) { state.update = { status: 'error', message: error.message || '版本检查失败，请稍后重试' }; renderUpdateStatus(); if (!silent) notify(state.update.message); } }
async function downloadMobileUpdate() { const info = state.update.info; const asset = info?.androidAsset; if (!asset) return notify('最新 Release 没有 Android APK'); if (isNative) { try { await DownloadBridge.download({ url: asset.url, name: asset.name }); notify('更新包已加入系统下载'); } catch (error) { notify(error.message || '更新包下载失败'); } } else { window.open(asset.url, '_blank', 'noopener'); } }
function setView(view) { state.view = view; $$('.mobile-tab').forEach((tab) => tab.classList.toggle('active', tab.dataset.view === view)); $$('.mobile-view').forEach((panel) => { panel.hidden = panel.dataset.view !== view; }); if (view === 'history') renderHistory(); if (view === 'profile' && state.profile) renderProfile(); if (view === 'me') updateWeiboStatus(); }
function setMode(mode) { state.mode = mode; $$('.mobile-switch-button').forEach((button) => button.classList.toggle('active', button.dataset.mode === mode)); $('#mobile-mode-label').textContent = mode === 'profile' ? '主页解析' : '单条解析'; $('#mobile-hint').textContent = mode === 'profile' ? '只在点击下一页时请求下一页。' : '粘贴后会自动保留链接。'; parseButton.textContent = mode === 'profile' ? '解析主页' : '开始解析'; if (mode === 'profile') setView('profile'); else setView('parse'); }
function addHistory(url, data) { state.history = [{ url, title: data.title || '未命名内容', author: data.author || '未知作者', time: data.time || 0, type: typeOf(data) }, ...state.history.filter((item) => item.url !== url)].slice(0, 20); saveHistory(); }

async function requireMediaProxy() {
  try {
    await mediaProxyReady;
    await ensureMediaProxy();
    return true;
  } catch {
    notify('本地媒体代理启动失败，请重启应用');
    return false;
  }
}

function renderResult(data, rawUrl) {
 const type = typeOf(data); const urls = urlsOf(data); const first = urls[0] || data.url; const baseName = (data.title || 'media').replace(/[\\/:*?"<>|]/g, '_'); const ext = type === 'image' ? 'jpg' : 'mp4'; // 抖音等平台带背景音乐时给出下载入口
 const musicUrl = /^https?:\/\//i.test(data.music?.url || '') ? data.music.url : ''; const musicName = `${baseName}-music.mp3`; result.hidden = false; result.innerHTML = `<div class="mobile-result-top"><span>${type === 'image' ? '图集完成' : '视频完成'}</span><button type="button" data-mobile-copy="${esc(first)}">复制</button></div><h2>${esc(data.title || '未命名内容')}</h2><p class="mobile-result-meta">${esc(data.author || '未知作者')} · ${dateOf(data.time)}</p><div class="mobile-media">${type === 'image' ? urls.map((url, index) => `<figure><img src="${esc(mediaUrl(url))}" alt="图片 ${index + 1}"><a href="${esc(mediaUrl(url, `${baseName}-${index + 1}.jpg`))}" download="${esc(`${baseName}-${index + 1}.jpg`)}">下载</a></figure>`).join('') : `<video controls playsinline src="${esc(mediaUrl(first))}" poster="${esc(data.cover ? mediaUrl(data.cover) : '')}"></video>`}</div><a class="mobile-primary mobile-download" href="${esc(mediaUrl(first, `${baseName}.${ext}`))}" download="${esc(`${baseName}.${ext}`)}">下载${type === 'image' ? '首图' : '视频'}</a>${musicUrl ? `<a class="mobile-primary mobile-download" href="${esc(mediaUrl(musicUrl, musicName))}" download="${esc(musicName)}">下载音乐</a>` : ''}`; addHistory(rawUrl, data); }
async function parseSingle() { const url = cleanUrl(input.value); if (!/^https?:\/\//i.test(url)) return notify('请粘贴有效链接'); if (!(await requireMediaProxy())) return; input.value = url; setBusy(true); try { const data = await parsers.parse(url); renderResult(data, url); notify('解析完成'); } catch (error) { notify(error.message || '解析失败'); } finally { setBusy(false); } }
function idOf(item, index, page) { return String(item?.aweme_id || item?.source_url || `${state.profile?.url}-${page}-${index}`); }
function renderProfile() { const page = state.profile?.pages[state.profile.current]; const items = page?.items || []; profileRoot.innerHTML = `<div class="mobile-profile-head"><div><span>${esc(page?.profile?.author || '公开主页')}</span><strong>第 ${state.profile.current + 1} 页</strong></div><div><button id="mobile-select-all" type="button">全选</button><button id="mobile-download-all" class="mobile-primary-small" type="button">全部下载</button></div></div><div class="mobile-profile-list">${items.length ? items.map((item, index) => { const urls = urlsOf(item); const selected = state.profile.selected.has(idOf(item, index, state.profile.current)); return `<article class="mobile-profile-item ${selected ? 'selected' : ''}" data-index="${index}"><input type="checkbox" class="mobile-check" ${selected ? 'checked' : ''}><img src="${esc(mediaUrl(item.cover || urls[0] || ''))}" alt=""><div><h3>${esc(item.title || '未命名内容')}</h3><p>${typeOf(item) === 'image' ? `图集 ${urls.length} 张` : '视频'}</p></div><button type="button" data-mobile-download="${index}">下载</button></article>`; }).join('') : '<div class="mobile-empty">这一页没有公开作品。</div>'}</div><div class="mobile-pagination"><button id="mobile-prev" type="button" ${state.profile.current === 0 ? 'disabled' : ''}>上一页</button><span>第 ${state.profile.current + 1} 页</span><button id="mobile-next" type="button" ${page?.has_more ? '' : 'disabled'}>下一页</button></div>`; bindProfile(); }
async function parseProfile() { const url = cleanUrl(input.value); if (!/^https?:\/\//i.test(url)) return notify('请粘贴有效主页链接'); if (!(await requireMediaProxy())) return; setBusy(true, '读取第 1 页…'); try { const data = await parsers.parseProfile(url, 0); state.profile = { url, pages: [data], current: 0, selected: new Set() }; setView('profile'); renderProfile(); notify('第 1 页已完成'); } catch (error) { notify(error.message || '主页解析失败'); } finally { setBusy(false); } }
async function nextProfile() { const page = state.profile?.pages[state.profile.current]; if (!state.profile || !page?.has_more) return; const next = state.profile.current + 1; if (state.profile.pages[next]) { state.profile.current = next; renderProfile(); return; } setBusy(true, `读取第 ${next + 1} 页…`); try { state.profile.pages[next] = await parsers.parseProfile(state.profile.url, page.next_cursor); state.profile.current = next; renderProfile(); } catch (error) { notify(error.message || '下一页解析失败'); } finally { setBusy(false); } }
function previousProfile() { if (state.profile?.current > 0) { state.profile.current -= 1; renderProfile(); } }
async function saveFile(url, name) { if (isNative) { if (!(await requireMediaProxy())) return; await DownloadBridge.download({ url, name }); } else { const anchor = document.createElement('a'); anchor.href = url; anchor.download = name; anchor.click(); } }
async function downloadItem(item, index) { const extension = typeOf(item) === 'image' ? 'jpg' : 'mp4'; for (let part = 0; part < urlsOf(item).length; part += 1) { const name = `${(item.title || `media-${index + 1}`).replace(/[\\/:*?"<>|]/g, '_')}${part ? `-${part + 1}` : ''}.${extension}`; try { await saveFile(mediaUrl(urlsOf(item)[part], name), name); } catch (error) { notify(error.message || '下载任务创建失败'); } await new Promise((resolve) => setTimeout(resolve, 260)); } }
function bindProfile() { const page = state.profile.pages[state.profile.current]; $('#mobile-prev')?.addEventListener('click', previousProfile); $('#mobile-next')?.addEventListener('click', nextProfile); $('#mobile-download-all')?.addEventListener('click', () => downloadItems(state.profile.pages.flatMap((item) => item.items || []))); $('#mobile-select-all')?.addEventListener('click', () => { (page.items || []).forEach((item, index) => state.profile.selected.add(idOf(item, index, state.profile.current))); renderProfile(); }); $$('.mobile-check').forEach((check) => check.addEventListener('change', (event) => { const itemIndex = Number(event.target.closest('.mobile-profile-item').dataset.index); const item = page.items[itemIndex]; const id = idOf(item, itemIndex, state.profile.current); if (event.target.checked) state.profile.selected.add(id); else state.profile.selected.delete(id); event.target.closest('.mobile-profile-item').classList.toggle('selected', event.target.checked); })); $$('[data-mobile-download]').forEach((button) => button.addEventListener('click', () => downloadItem(page.items[Number(button.dataset.mobileDownload)], Number(button.dataset.mobileDownload)))); }
async function downloadItems(items) { if (!items.length) return notify('请先选择作品'); for (let index = 0; index < items.length; index += 1) await downloadItem(items[index], index); notify('下载任务已发送'); }
function renderHistory() { $('#mobile-history').innerHTML = state.history.length ? `${state.history.map((item) => `<button class="mobile-history-item" type="button" data-mobile-history="${esc(item.url)}"><span>${esc(item.type === 'image' ? '图集' : '视频')}</span><div><strong>${esc(item.title)}</strong><small>${esc(item.author)} · ${dateOf(item.time)}</small></div><b>重试</b></button>`).join('')}` : '<div class="mobile-empty">还没有记录。</div>'; $$('[data-mobile-history]').forEach((button) => button.addEventListener('click', () => { setView('parse'); setMode('single'); input.value = button.dataset.mobileHistory; parseSingle(); })); }

$$('[data-view]').forEach((button) => button.addEventListener('click', () => setView(button.dataset.view)));
$$('[data-mode]').forEach((button) => button.addEventListener('click', () => setMode(button.dataset.mode)));
parseButton.addEventListener('click', () => state.mode === 'profile' ? parseProfile() : parseSingle());
$('#mobile-clear').addEventListener('click', () => { input.value = ''; input.focus(); });
input.addEventListener('paste', (event) => { const value = event.clipboardData?.getData('text') || ''; if (value) { event.preventDefault(); input.value = cleanUrl(value); } });
input.addEventListener('keydown', (event) => { if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); parseButton.click(); } });
$('#mobile-weibo-save')?.addEventListener('click', () => { const value = $('#mobile-weibo-cookie').value.trim(); if (value) localStorage.setItem(WEIBO_COOKIE_KEY, value); else localStorage.removeItem(WEIBO_COOKIE_KEY); parsers.setWeiboCookie(value); updateWeiboStatus(); notify(value ? '微博凭据已保存' : '微博凭据已清空'); });
$('#mobile-weibo-clear')?.addEventListener('click', () => { localStorage.removeItem(WEIBO_COOKIE_KEY); parsers.setWeiboCookie(''); updateWeiboStatus(); notify('微博凭据已清空'); });
$('#mobile-update-check')?.addEventListener('click', () => checkMobileUpdate());
$('#mobile-update-download')?.addEventListener('click', downloadMobileUpdate);
document.addEventListener('click', (event) => { const copy = event.target.closest('[data-mobile-copy]'); if (copy) { navigator.clipboard?.writeText(copy.dataset.mobileCopy); notify('链接已复制'); } const anchor = event.target.closest('a[download]'); if (anchor && isNative) { event.preventDefault(); const name = anchor.download || 'download'; DownloadBridge.download({ url: anchor.href, name }).then(() => notify(`已加入下载：${name}`)).catch((error) => notify(error.message || '下载任务创建失败')); } });
$('#mobile-status').textContent = `本地解析 · ${parsers.getSupportedPlatforms().length} 平台`;
checkMobileUpdate({ silent: true });
