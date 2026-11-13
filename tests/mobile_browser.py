"""Mobile UI regression fixtures; not an upstream service or Android device test.

Start Vite in apps/mobile on port 18182, then run with Python Playwright + Edge.
"""
import base64
import json
import os
from pathlib import Path
import zipfile
from playwright.sync_api import sync_playwright, expect

ROOT = Path(__file__).resolve().parents[1]
ARTIFACTS = ROOT / '.evolution' / 'verification'
ARTIFACTS.mkdir(parents=True, exist_ok=True)
BASE = os.environ.get('MOBILE_TEST_URL', 'http://127.0.0.1:18182/')
IMAGE = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=')
UTILS = '/@fs/' + (ROOT / 'packages/parsers/src/utils.js').as_posix()

PARSERS = """
export { createZipBlob } from %s;
export function setTransport() {}
export function setWeiboCookie() {}
export const getSupportedPlatforms = () => Array.from({length:12}, () => ({}));
export async function parse() {
  window.__parseCalls = (window.__parseCalls || 0) + 1;
  await new Promise(resolve => setTimeout(resolve, 100));
  return {title:'fixture gallery',author:'fixture author',media_type:'image',images:[
    'https://v26.douyinvod.com/fixture-1.jpg','https://v26.douyinvod.com/fixture-2.jpg']};
}
export async function hotList(platform, page) {
  window.__pages = [...(window.__pages || []), page];
  if (page === 2 && !window.__failed) { window.__failed = true; throw Error('fixture page failure'); }
  return {items:[{title:'fixture page '+page,url:'https://www.bilibili.com/video/BV1234567890'}],has_more:page===1};
}
""" % json.dumps(UTILS)


def fixtures(context):
    def route(request):
        url = request.request.url
        if '/packages/parsers/src/index.js' in url:
            return request.fulfill(content_type='text/javascript', body=PARSERS)
        if '/packages/update-checker/src/index.js' in url:
            return request.fulfill(content_type='text/javascript', body="export const UPDATE_REPOSITORY={}; export async function checkForUpdate(){return {updateAvailable:false,latestVersion:'1.1.9'}}")
        if '/src/native.js' in url:
            return request.fulfill(content_type='text/javascript', body="export const isNative=window.__native; export const capacitorHttpTransport={}; export const initMediaProxy=async()=>true; export const ensureMediaProxy=async()=>true; export const mediaUrl=url=>url;")
        if '/node_modules/.vite/deps/@capacitor_core.js' in url:
            return request.fulfill(content_type='text/javascript', body="export const registerPlugin=()=>({download:async task=>{window.__queued=[...(window.__queued||[]),task];}});")
        if '/fixture-' in url and url.endswith('.jpg'):
            return request.fulfill(content_type='image/png', body=IMAGE)
        return request.continue_()
    context.route('**/*', route)


with sync_playwright() as p:
    browser = p.chromium.launch(channel='msedge', headless=True)
    try:
        for native in [False, True]:
            context = browser.new_context(viewport={'width': 390, 'height': 844}, accept_downloads=True)
            fixtures(context)
            context.add_init_script('window.__native=' + json.dumps(native) + ';localStorage.setItem("qushuiyin-mobile-history-v1", JSON.stringify([null,{}, {url:"javascript:bad"},{url:"https://www.douyin.com/video/1",title:"saved history"}]));')
            page = context.new_page()
            errors = []
            page.on('pageerror', lambda error: errors.append(str(error)))
            page.goto(BASE)
            page.wait_for_load_state('networkidle')
            page.locator('.mobile-tab[data-view=history]').click()
            expect(page.locator('.mobile-history-item')).to_have_count(1)
            page.locator('.mobile-tab[data-view=parse]').click()
            page.locator('#mobile-input').fill('https://www.douyin.com/video/123')
            page.locator('#mobile-parse').dblclick()
            expect(page.locator('#mobile-result')).to_be_visible()
            assert page.evaluate('window.__parseCalls') == 1
            if native:
                expect(page.locator('#mobile-zip-download')).to_contain_text('下载全部图片')
                page.locator('#mobile-zip-download').click()
                page.wait_for_function('(window.__queued || []).length === 2')
                queued = page.evaluate('window.__queued')
                assert all(item['url'].startswith('https://') for item in queued), queued
                assert [item['name'] for item in queued] == ['fixture gallery-01.jpg', 'fixture gallery-02.jpg']
            else:
                with page.expect_download() as download:
                    page.locator('#mobile-zip-download').click()
                target = ARTIFACTS / 'mobile-gallery.zip'
                download.value.save_as(target)
                with zipfile.ZipFile(target) as archive:
                    assert archive.testzip() is None
                    assert archive.namelist() == ['fixture gallery-01.jpg', 'fixture gallery-02.jpg']
            page.locator('.mobile-tab[data-view=discover]').click()
            expect(page.locator('#mobile-discover-more')).to_be_visible()
            page.locator('#mobile-discover-more').click()
            expect(page.locator('#mobile-toast')).to_contain_text('fixture page failure')
            page.locator('#mobile-discover-more').click()
            expect(page.locator('.mobile-discover-item')).to_have_count(2)
            assert page.evaluate('window.__pages') == [1, 2, 2]
            assert page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1')
            page.screenshot(path=str(ARTIFACTS / ('mobile-native-fixture.png' if native else 'mobile-browser.png')), full_page=True)
            assert not errors, errors
            print('PASS mobile UI:', 'native download bridge fixture' if native else 'browser ZIP CRC', 'history validation, parse lock, paging retry, 390px layout')
            context.close()

        context = browser.new_context()
        fixtures(context)
        context.add_init_script("window.__native=false;Storage.prototype.getItem=()=>{throw Error('fixture storage unavailable')};")
        page = context.new_page()
        errors = []
        page.on('pageerror', lambda error: errors.append(str(error)))
        page.goto(BASE)
        page.wait_for_load_state('networkidle')
        expect(page.locator('#mobile-parse')).to_be_visible()
        assert not errors, errors
        print('PASS mobile UI: unavailable browser storage does not prevent startup')
        context.close()
    finally:
        browser.close()
