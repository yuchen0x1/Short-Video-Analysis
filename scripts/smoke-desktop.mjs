import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { mkdir, mkdtemp, readFile, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { setTimeout as delay } from 'node:timers/promises';

const [executable, version, screenshotDir] = process.argv.slice(2);
assert(executable && version, 'Usage: smoke-desktop.mjs <executable> <version> [screenshot-dir]');

async function waitFor(probe, name) {
  const deadline = Date.now() + 30_000;
  while (Date.now() < deadline) {
    const value = await probe().catch(() => null);
    if (value) return value;
    await delay(200);
  }
  throw new Error(`Timed out waiting for ${name}`);
}

const profile = await mkdtemp(join(tmpdir(), 'sv-desktop-smoke-'));
const child = spawn(resolve(executable), [
  `--user-data-dir=${profile}`, '--remote-debugging-port=0', '--disable-gpu',
], { stdio: ['ignore', 'ignore', 'inherit'], windowsHide: true });
let spawnError;
child.once('error', (error) => { spawnError = error; });
let socket;

try {
  const port = await waitFor(async () => {
    if (spawnError || child.exitCode !== null) return 'exited';
    const body = await readFile(join(profile, 'DevToolsActivePort'), 'utf8');
    return Number(body.split('\n')[0]);
  }, 'packaged desktop');
  if (port === 'exited') throw spawnError || new Error(`Desktop exited: ${child.exitCode}`);
  const debugUrl = await waitFor(async () => {
    const response = await fetch(`http://127.0.0.1:${port}/json/list`, { signal: AbortSignal.timeout(2_000) });
    return (await response.json()).find((target) => target.type === 'page' && target.url.startsWith('file:'))?.webSocketDebuggerUrl;
  }, 'desktop renderer');

  socket = new WebSocket(debugUrl);
  let nextCommandId = 0;
  const pendingCommands = new Map();
  socket.addEventListener('message', (event) => {
    const response = JSON.parse(event.data);
    const pending = pendingCommands.get(response.id);
    if (!pending) return;
    clearTimeout(pending.timeout);
    pendingCommands.delete(response.id);
    const details = response.result?.exceptionDetails;
    if (response.error || details) {
      pending.reject(new Error(response.error?.message || details.exception?.description || details.text));
      return;
    }
    pending.resolve(response.result || {});
  });
  await new Promise((resolveOpen, rejectOpen) => {
    socket.addEventListener('open', resolveOpen, { once: true });
    socket.addEventListener('error', () => rejectOpen(new Error('Could not connect to packaged desktop renderer')), { once: true });
  });

  const sendCdp = (method, params = {}) => {
    const id = ++nextCommandId;
    return new Promise((resolveCommand, rejectCommand) => {
      const timeout = setTimeout(() => {
        pendingCommands.delete(id);
        rejectCommand(new Error(`Timed out waiting for ${method}`));
      }, 15_000);
      pendingCommands.set(id, { resolve: resolveCommand, reject: rejectCommand, timeout });
      socket.send(JSON.stringify({ id, method, params }));
    });
  };
  const evaluate = async (expression) => {
    const response = await sendCdp('Runtime.evaluate', {
      expression,
      returnByValue: true,
      awaitPromise: true,
    });
    return response.result?.value;
  };

  const result = await evaluate(`(async () => {
    for (let attempt = 0; attempt < 80 && !document.querySelector('#parse-button'); attempt++) {
      await new Promise(resolve => setTimeout(resolve, 100));
    }
    document.querySelector('[data-view="profile"]').click();
    const profileInput = Boolean(document.querySelector('#link-input').getClientRects().length);
    document.querySelector('[data-view="about"]').click();
    for (let attempt = 0; attempt < 80 && !document.querySelector('#about-version'); attempt++) {
      await new Promise(resolve => setTimeout(resolve, 100));
    }
    return {
      version: await window.appShell.getVersion(),
      aboutVersion: document.querySelector('#about-version')?.textContent,
      profileInput,
      history: Boolean(document.querySelector('[data-view="history"]')),
      favorites: Boolean(document.querySelector('[data-view="favorites"]')),
      supportedPlatforms: document.querySelectorAll('#supported-platforms span').length,
    };
  })()`);
  assert.deepEqual(result, {
    version,
    aboutVersion: `当前版本 v${version}`,
    profileInput: true,
    history: true,
    favorites: true,
    supportedPlatforms: 12,
  });

  if (screenshotDir) {
    await mkdir(resolve(screenshotDir), { recursive: true });
    const updateStatus = await evaluate(`(async () => {
      const deadline = Date.now() + 15000;
      while (Date.now() < deadline) {
        const text = document.querySelector('#update-status')?.textContent || '';
        if (text && !/正在检查更新|checking for updates/i.test(text)) return text;
        await new Promise(resolve => setTimeout(resolve, 100));
      }
      return document.querySelector('#update-status')?.textContent || 'no update status';
    })()`);
    console.log(`Update status: ${updateStatus}`);
    const setTheme = async (theme) => {
      for (let attempt = 0; attempt < 3; attempt += 1) {
        if (await evaluate('document.documentElement.dataset.theme') === theme) break;
        await evaluate(`document.querySelector('#theme-button').click(); new Promise(resolve => setTimeout(resolve, 220))`);
      }
      assert.equal(await evaluate('document.documentElement.dataset.theme'), theme);
    };
    const capture = async (name, width, height) => {
      await sendCdp('Emulation.setDeviceMetricsOverride', {
        width,
        height,
        deviceScaleFactor: 1,
        mobile: false,
      });
      await evaluate(`(() => {
        window.scrollTo(0, 0);
        document.documentElement.scrollTop = 0;
        document.body.scrollTop = 0;
        document.querySelector('.side-nav')?.scrollTo(0, 0);
        document.querySelector('.content-column')?.scrollTo(0, 0);
      })()`);
      await sendCdp('Input.dispatchMouseEvent', {
        type: 'mouseMoved',
        x: Math.round(width / 2),
        y: 32,
      });
      await delay(220);
      const dimensions = await evaluate(`({
        viewport: window.innerWidth,
        document: document.documentElement.scrollWidth,
        body: document.body.scrollWidth,
      })`);
      assert.ok(
        dimensions.document <= dimensions.viewport && dimensions.body <= dimensions.viewport,
        `Horizontal overflow at ${width}x${height}: ${JSON.stringify(dimensions)}`,
      );
      const image = await sendCdp('Page.captureScreenshot', {
        format: 'png',
        fromSurface: true,
        captureBeyondViewport: false,
      });
      await writeFile(join(resolve(screenshotDir), name), Buffer.from(image.data, 'base64'));
    };

    await setTheme('dark');
    await capture('ui-v1.3.4-about-dark.png', 1366, 768);
    await setTheme('light');
    await capture('ui-v1.3.4-about-light.png', 1366, 768);
    await evaluate(`document.querySelector('[data-view="single"]').click()`);
    await setTheme('dark');
    await capture('ui-v1.3.4-1366x768-dark.png', 1366, 768);
    await setTheme('light');
    await capture('ui-v1.3.4-1366x768-light.png', 1366, 768);
    await capture('ui-v1.3.4-1920x1080-light.png', 1920, 1080);
    await setTheme('dark');
    await capture('ui-v1.3.4-1920x1080-dark.png', 1920, 1080);

    await evaluate(`document.querySelector('#link-input').value = 'https://www.bilibili.com/video/BV1xx411c7mD'`);
    await setTheme('dark');
    await capture('ui-v1.3.4-input-dark.png', 1366, 768);
    await setTheme('light');
    await capture('ui-v1.3.4-input-light.png', 1366, 768);
    await evaluate(`document.querySelector('#link-input').value = ''`);
    await evaluate(`document.querySelector('[data-view="profile"]').click()`);
    const capturePageThemes = async (name) => {
      for (const theme of ['dark', 'light']) {
        await setTheme(theme);
        await capture(`ui-v1.3.4-${name}-${theme}.png`, 1366, 768);
      }
    };
    await capturePageThemes('profile');
    await evaluate(`document.querySelector('[data-mode="collection"]').click()`);
    await capturePageThemes('collection');
    await evaluate(`document.querySelector('[data-mode="live"]').click()`);
    await capturePageThemes('live');
    await evaluate(`document.querySelector('[data-view="history"]').click()`);
    await capturePageThemes('history');
    await evaluate(`document.querySelector('[data-view="favorites"]').click()`);
    await capturePageThemes('favorites');
    const favoritesUi = await evaluate(`(() => {
      const key = 'qushuiyin-app-favorites-v1';
      const fixture = {
        rawUrl: 'https://www.douyin.com/video/smoke-test',
        title: 'Smoke favorite',
        author: 'Smoke test',
        time: 0,
        cover: '',
        mediaType: 'video',
        savedAt: Date.now(),
      };
      localStorage.setItem(key, JSON.stringify([fixture]));
      document.querySelector('[data-view="favorites"]').click();
      const shown = document.querySelector('#favorites-panel .library-copy h3')?.textContent;
      window.confirm = () => false;
      document.querySelector('#clear-favorites').click();
      const retained = JSON.parse(localStorage.getItem(key) || '[]').length;
      document.querySelector('[data-favorite-remove]').click();
      const remaining = JSON.parse(localStorage.getItem(key) || '[]').length;
      return { shown, retained, remaining };
    })()`);
    assert.deepEqual(favoritesUi, { shown: 'Smoke favorite', retained: 1, remaining: 0 });
    await evaluate(`document.querySelector('[data-view="discover"]').click()`);
    const discoverItems = await evaluate(`(async () => {
      const deadline = Date.now() + 12000;
      while (Date.now() < deadline) {
        const panel = document.querySelector('#discover-panel');
        if (panel?.querySelector('.discover-item')) return panel.querySelectorAll('.discover-item').length;
        if (panel?.querySelector('.workspace-empty') && !panel.textContent.includes('正在加载')) return 0;
        await new Promise(resolve => setTimeout(resolve, 100));
      }
      return -1;
    })()`);
    assert.ok(discoverItems > 0, 'Discover did not return live public items; refusing a content-empty acceptance screenshot');
    const loadedThumbnails = await evaluate(`(async () => {
      const deadline = Date.now() + 8000;
      while (Date.now() < deadline) {
        const images = [...document.querySelectorAll('#discover-panel .discover-item img')];
        const loaded = images.filter(image => image.complete && image.naturalWidth > 0).length;
        if (loaded > 0 || !images.length) return { count: images.length, loaded };
        await new Promise(resolve => setTimeout(resolve, 100));
      }
      const images = [...document.querySelectorAll('#discover-panel .discover-item img')];
      return { count: images.length, loaded: images.filter(image => image.complete && image.naturalWidth > 0).length };
    })()`);
    console.log(`Discover thumbnails loaded: ${loadedThumbnails.loaded}/${loadedThumbnails.count}`);
    await capturePageThemes('discover');
    console.log(`Discover screenshot contains ${discoverItems < 0 ? 'a pending request' : discoverItems + ' live items'}`);
    await evaluate(`document.querySelector('[data-view="single"]').click()`);
    await setTheme('dark');
    for (const [width, height] of [[1280, 720], [1366, 768], [1600, 900], [1920, 1080], [2560, 1440], [768, 1024], [390, 844]]) {
      await sendCdp('Emulation.setDeviceMetricsOverride', {
        width,
        height,
        deviceScaleFactor: 1,
        mobile: false,
      });
      await delay(160);
      const dimensions = await evaluate(`({
        viewport: window.innerWidth,
        document: document.documentElement.scrollWidth,
        body: document.body.scrollWidth,
      })`);
      assert.ok(
        dimensions.document <= dimensions.viewport && dimensions.body <= dimensions.viewport,
        `Horizontal overflow at ${width}x${height}: ${JSON.stringify(dimensions)}`,
      );
    }
    console.log('Layout fits 1280x720, 1366x768, 1600x900, 1920x1080, 2560x1440, 768x1024 and 390x844');
    await sendCdp('Emulation.clearDeviceMetricsOverride');
    console.log(`Captured packaged desktop screenshots in ${resolve(screenshotDir)}`);
  }

  console.log(`Packaged desktop smoke passed for v${version}`);
} finally {
  socket?.close();
  child.kill();
}
