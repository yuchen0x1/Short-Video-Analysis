"""Local HTTP + browser regression checks. Fixtures test UI plumbing, not upstream availability.

Requires Python Playwright and installed Microsoft Edge. Uses disposable browser contexts.
Run from the repository root: python tests/evolution_browser.py
"""
import base64
import json
import os
from pathlib import Path
import socket
import subprocess
import time
import urllib.error
import urllib.request
import zipfile

from playwright.sync_api import sync_playwright, expect

ROOT = Path(__file__).resolve().parents[1]
ARTIFACTS = ROOT / '.evolution' / 'verification'
ARTIFACTS.mkdir(parents=True, exist_ok=True)


def request(base, path, method='GET', headers=None):
    req = urllib.request.Request(base + path, method=method, headers=headers or {})
    try:
        with urllib.request.urlopen(req, timeout=10) as response:
            return response.status, response.read()
    except urllib.error.HTTPError as error:
        return error.code, error.read()


def server(auth):
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    env = dict(os.environ, API_KEY_ENABLED='true' if auth else 'false', API_KEY='local-regression-key', RATE_LIMIT_ENABLED='false', PARSE_CACHE_ENABLED='false')
    log = open(ARTIFACTS / ('auth-server.log' if auth else 'web-server.log'), 'w')
    process = subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', '-t', 'public', 'public/router.php'], cwd=ROOT, env=env, stdout=log, stderr=log, creationflags=getattr(subprocess, 'CREATE_NO_WINDOW', 0))
    base = f'http://127.0.0.1:{port}'
    for _ in range(100):
        try:
            if request(base, '/')[0] == 200:
                return process, log, base
        except (OSError, urllib.error.URLError):
            time.sleep(0.1)
    process.terminate()
    log.close()
    raise RuntimeError('PHP server did not start')


servers = []
try:
    auth_process, auth_log, auth_base = server(True)
    servers.append((auth_process, auth_log))
    for path, method in [('/?url=https://example.com/a', 'GET'), ('/index.php?url=https://example.com/a', 'POST'), ('/api/v1/parse?url=https://example.com/a', 'GET'), ('/index.php?action=media&url=https://v26.douyinvod.com/a.mp4', 'GET')]:
        assert request(auth_base, path, method)[0] == 401, (path, method)
    assert request(auth_base, '/?url=https://example.com/a', headers={'X-API-Key': 'local-regression-key'})[0] == 400
    assert request(auth_base, '/api/v1/platforms', headers={'Authorization': 'Bearer local-regression-key'})[0] == 200
    assert request(auth_base, '/api/v1/platforms')[0] == 401
    assert request(auth_base, '/api/v1/health')[0] == 200
    print('PASS HTTP: legacy GET/POST, v1, media authentication; valid key; public home/health')

    web_process, web_log, base = server(False)
    servers.append((web_process, web_log))
    assert request(base, '/api/v1/parse?url=https://example.com/a')[0] == 400
    assert request(base, '/workbench.js')[0] == 200
    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(channel='msedge', headless=True)
        context = browser.new_context(viewport={'width': 1440, 'height': 1000}, service_workers='block', reduced_motion='reduce')
        # Avoid public update/platform calls in fixture-driven UI checks.
        context.route('**/api/v1/update*', lambda route: route.fulfill(json={'success': False, 'message': '测试环境不检查在线版本'}))
        page = context.new_page()
        errors = []
        page.on('pageerror', lambda error: errors.append(str(error)))
        page.goto(base)
        page.wait_for_load_state('networkidle')
        expect(page.get_by_label('搜索标题、作者或链接')).to_be_visible()
        page.screenshot(path=str(ARTIFACTS / 'web-desktop.png'), full_page=True)
        # An explicitly marked test fixture is imported through the actual file control.
        fixture = {'app': 'short-video-analysis', 'version': 2, 'history': [
            {'rawUrl': 'https://www.douyin.com/video/1', 'title': '测试记录甲', 'author': '作者一'},
            {'rawUrl': 'https://www.douyin.com/video/2', 'title': '测试记录乙', 'author': '作者二'}], 'favorites': []}
        page.locator('#library-file').set_input_files({'name': 'test-library.json', 'mimeType': 'application/json', 'buffer': json.dumps(fixture).encode()})
        expect(page.locator('#library-status')).to_contain_text('导入完成')
        expect(page.locator('#history-list .history-entry')).to_have_count(2)
        page.get_by_label('搜索标题、作者或链接').fill('作者二')
        expect(page.locator('#history-list .history-entry')).to_have_count(1)
        page.locator('#history-list').get_by_role('button', name='收藏', exact=True).click()
        expect(page.locator('#favorites-list')).to_contain_text('测试记录乙')
        page.get_by_label('搜索标题、作者或链接').fill('')
        with page.expect_download() as info:
            page.get_by_role('button', name='备份 JSON').click()
        downloaded = info.value
        downloaded.save_as(str(ARTIFACTS / 'test-library.json'))
        exported = json.loads((ARTIFACTS / 'test-library.json').read_text(encoding='utf-8'))
        assert len(exported['history']) == 2 and len(exported['favorites']) == 1
        page.locator('#library-file').set_input_files({'name': 'invalid.json', 'mimeType': 'application/json', 'buffer': b'{bad'})
        expect(page.locator('#library-status')).to_contain_text('导入失败')
        expect(page.locator('#history-list .history-entry')).to_have_count(2)
        page.reload()
        expect(page.locator('#favorites-list')).to_contain_text('测试记录乙')
        print('PASS browser: import/search/favorite/backup/corrupt-file/persistence')

        png = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aY1sAAAAASUVORK5CYII=')
        page.route('**/index.php?action=media*', lambda route: route.fulfill(body=png, content_type='image/png'))
        def parse_fixture(route):
            url = route.request.post_data_json['url']
            if url.endswith('/3'):
                route.fulfill(status=502, json={'success': False, 'message': '测试上游不可用'})
            else:
                route.fulfill(json={'success': True, 'data': {'title': '=测试图集', 'author': '测试作者', 'media_type': 'image', 'url': 'https://sns-img.xhscdn.com/test.jpg', 'images': ['https://sns-img.xhscdn.com/test.jpg']}})
        page.route('**/api/v1/parse', parse_fixture)
        page.get_by_role('button', name='批量处理', exact=True).click()
        page.get_by_label('批量视频链接').fill('https://www.douyin.com/video/1\nhttps://www.douyin.com/video/2\nhttps://www.douyin.com/video/3')
        page.get_by_role('button', name='开始批量处理').click()
        expect(page.locator('#batch-summary')).to_contain_text('成功 2')
        expect(page.locator('#batch-summary')).to_contain_text('失败 1')
        page.get_by_role('button', name='重试失败 / 已停止项').click()
        expect(page.locator('#batch-parse-btn')).to_be_enabled()
        expect(page.locator('#batch-summary')).to_contain_text('成功 2')
        with page.expect_download() as info:
            page.get_by_role('button', name='全部打包 ZIP').click()
        info.value.save_as(str(ARTIFACTS / 'test-batch.zip'))
        with zipfile.ZipFile(ARTIFACTS / 'test-batch.zip') as archive:
            assert len(archive.namelist()) == 2 and len(set(archive.namelist())) == 2
            assert archive.testzip() is None
        with page.expect_download() as info:
            page.locator('#batch-export').click()
        info.value.save_as(str(ARTIFACTS / 'test-batch.csv'))
        assert '"\'=测试图集"' in (ARTIFACTS / 'test-batch.csv').read_text(encoding='utf-8-sig')
        print('PASS browser fixtures: batch success/failure/retry, real ZIP structure/CRC, CSV protection')

        page.get_by_role('button', name='单个解析', exact=True).click()
        page.locator('#video-url').fill('https://www.douyin.com/video/2')
        page.locator('#video-url').press('Control+Enter')
        expect(page.locator('#result')).to_be_visible()
        expect(page.locator('#error')).to_be_hidden()
        print('PASS browser: Ctrl+Enter submits once without cancelling itself')

        for width in [768, 390]:
            page.set_viewport_size({'width': width, 'height': 844})
            assert page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'), f'Overflow at {width}'
        page.reload()
        page.wait_for_load_state('networkidle')
        page.screenshot(path=str(ARTIFACTS / 'web-mobile.png'), full_page=True)
        page.locator('#theme-toggle').click()
        page.screenshot(path=str(ARTIFACTS / 'web-mobile-light.png'), full_page=True)
        assert not errors, errors
        print('PASS browser: 1440/768/390 layout, themes, zero uncaught JavaScript errors')
        browser.close()
finally:
    for process, log in servers:
        process.terminate()
        try:
            process.wait(timeout=5)
        except subprocess.TimeoutExpired:
            process.kill()
        log.close()
