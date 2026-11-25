export const LOCALES = ['zh-CN', 'en-US'];

export function resolveLocale(setting = 'auto', candidates = []) {
  if (setting === 'zh-CN' || setting === 'en-US') return setting;
  for (const candidate of (candidates.length ? candidates : ['zh-CN'])) {
    const value = String(candidate).toLowerCase();
    if (value === 'en' || value.startsWith('en-')) return 'en-US';
    if (value === 'zh' || value.startsWith('zh-')) return 'zh-CN';
  }
  return 'zh-CN';
}

function storage() {
  try { return globalThis.localStorage; } catch { return undefined; }
}

export function readLocaleSetting(store = storage()) {
  try {
    const value = store?.getItem('qushuiyin-app-locale-v1');
    return value === 'zh-CN' || value === 'en-US' ? value : 'auto';
  } catch { return 'auto'; }
}

export function writeLocaleSetting(value, store = storage()) {
  const setting = value === 'zh-CN' || value === 'en-US' ? value : 'auto';
  try { store?.setItem('qushuiyin-app-locale-v1', setting); } catch { /* private mode */ }
  return setting;
}

export const messages = {
  'zh-CN': {
    brandName: '去水印解析', brandSub: 'DESKTOP WORKSPACE', service: '检查服务', theme: '切换主题',
    collapseNavigation: '收起导航', expandNavigation: '展开导航',
    workspace: '工作区', workbenchNav: '工作台', libraryNav: '我的资料', systemNav: '系统',
    profileCollectionNav: '主页与合集', historyFull: '解析记录', favoritesNav: '我的收藏',
    favoritesTitle: '收藏。', favoritesDescription: '保存于此设备的媒体链接与作品信息。',
    historyIntro: '历史记录仅保存在当前设备。', historyPanelTitle: '最近处理',
    localRecords: '本地记录', searchLibrary: '搜索标题、作者或链接',
    clearHistory: '清空记录', clearFavorites: '清空收藏', noHistory: '还没有解析记录。',
    noHistoryMatches: '没有匹配的记录。', noFavorites: '还没有收藏。解析作品后可在结果区加入收藏。',
    noFavoritesMatches: '没有匹配的收藏。', favoriteCount: '已收藏 {count} 项',
    reparse: '重新解析', favorite: '收藏', favorited: '已收藏', removeFavorite: '取消收藏',
    deleteRecord: '删除', confirmClearHistory: '确认清空全部解析记录？此操作无法撤销。',
    confirmClearFavorites: '确认清空全部收藏？此操作无法撤销。',
    savedAt: '收藏于', imageSet: '图集', video: '视频',
    parse: '解析', profile: '主页', discover: '发现', history: '记录', about: '关于',
    noWatermark: '无水印', sideNote: '把分享链接变成可用媒体', inputKicker: '解析输入', inputTitle: '解析分享链接',
    inputNote: '粘贴分享文案，自动提取真正的链接和无水印媒体。', single: '单条', collection: '合集', live: '直播',
    placeholder: '粘贴视频链接或整段分享文案', paste: '粘贴', clear: '清除', start: '开始解析',
    inputHint: '粘贴时会自动提取真正的链接。', local: '本地优先解析', videoImages: '支持视频与图集', noUpload: '无后台上传',
    waiting: '等待链接', waitingNote: '粘贴分享链接，解析结果会显示在这里。', language: '语言 / Language', auto: '自动 / Auto',
    simplified: '简体中文', english: 'English', client: '客户端', desktop: '电脑版', web: 'Web 客户端',
    parseProfile: '解析主页', parseCollection: '解析合集', parseLive: '解析直播间',
    profileHint: '粘贴抖音个人主页链接，按页整理公开作品。',
    collectionHint: '粘贴抖音合集链接，按页整理合集作品。',
    liveHint: '粘贴直播间链接，获取 FLV 或 HLS 拉流地址。',
    profileEmpty: '尚未加载主页', collectionEmpty: '尚未加载合集',
    parsing: '正在解析…', readingPage: '读取第 {page} 页…', loadingAll: '加载全部…',
    invalidLink: '请先粘贴有效链接', parsed: '解析完成', parseFailed: '解析失败',
    localService: '本地解析 · {count} 平台', serviceOffline: '解析器未就绪',
  },
  'en-US': {
    brandName: 'Watermark Remover', brandSub: 'DESKTOP WORKSPACE', service: 'Check service', theme: 'Toggle theme',
    collapseNavigation: 'Collapse navigation', expandNavigation: 'Expand navigation',
    workspace: 'Workspace', workbenchNav: 'Workbench', libraryNav: 'Library', systemNav: 'System',
    profileCollectionNav: 'Profiles & collections', historyFull: 'History', favoritesNav: 'Favorites',
    favoritesTitle: 'Favorites.', favoritesDescription: 'Media links and details saved on this device.',
    historyIntro: 'History is stored on this device only.', historyPanelTitle: 'Recently processed',
    localRecords: 'Local library', searchLibrary: 'Search titles, authors, or links',
    clearHistory: 'Clear history', clearFavorites: 'Clear favorites', noHistory: 'No parsed items yet.',
    noHistoryMatches: 'No matching history.', noFavorites: 'No favorites yet. Save an item from its result.',
    noFavoritesMatches: 'No matching favorites.', favoriteCount: '{count} favorites',
    reparse: 'Parse again', favorite: 'Favorite', favorited: 'Favorited', removeFavorite: 'Remove favorite',
    deleteRecord: 'Delete', confirmClearHistory: 'Clear all parsed history? This cannot be undone.',
    confirmClearFavorites: 'Clear all favorites? This cannot be undone.',
    savedAt: 'Saved', imageSet: 'Gallery', video: 'Video',
    parse: 'Parse', profile: 'Profile', discover: 'Discover', history: 'History', about: 'About',
    noWatermark: 'No watermark', sideNote: 'Turn shared links into usable media', inputKicker: 'Parse input', inputTitle: 'Parse a shared link',
    inputNote: 'Paste a share message to extract the real link and watermark-free media.', single: 'Single', collection: 'Collection', live: 'Live',
    placeholder: 'Paste a video link or a full share message', paste: 'Paste', clear: 'Clear', start: 'Start parsing',
    inputHint: 'The real link is extracted automatically when pasted.', local: 'Local-first parsing', videoImages: 'Video and image sets', noUpload: 'No background upload',
    waiting: 'Waiting for a link', waitingNote: 'Paste a shared link and the result will appear here.', language: 'Language / 语言', auto: 'Auto / 自动',
    simplified: '简体中文', english: 'English', client: 'Client', desktop: 'Desktop', web: 'Web client',
    parseProfile: 'Parse profile', parseCollection: 'Parse collection', parseLive: 'Parse live room',
    profileHint: 'Paste a Douyin profile link to browse its public posts page by page.',
    collectionHint: 'Paste a Douyin collection link to browse its posts page by page.',
    liveHint: 'Paste a live room link to get its FLV or HLS stream URL.',
    profileEmpty: 'No profile loaded', collectionEmpty: 'No collection loaded',
    parsing: 'Parsing…', readingPage: 'Loading page {page}…', loadingAll: 'Loading all pages…',
    invalidLink: 'Paste a valid link first', parsed: 'Parsing complete', parseFailed: 'Parsing failed',
    localService: 'Local parser · {count} platforms', serviceOffline: 'Parser unavailable',
  },
};

export function t(locale, key) { return messages[locale]?.[key] || messages['zh-CN'][key] || key; }

export function applyLocale(root, locale) {
  root.querySelectorAll('[data-i18n]').forEach((element) => {
    const key = element.dataset.i18n;
    if (key) element.textContent = t(locale, key);
  });
  root.querySelectorAll('[data-i18n-placeholder]').forEach((element) => {
    const key = element.dataset.i18nPlaceholder;
    if (key) element.placeholder = t(locale, key);
  });
  root.querySelectorAll('[data-i18n-title]').forEach((element) => {
    const key = element.dataset.i18nTitle;
    if (key) { element.title = t(locale, key); element.setAttribute('aria-label', t(locale, key)); }
  });
}
