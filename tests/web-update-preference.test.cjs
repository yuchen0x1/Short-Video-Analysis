const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const { test } = require('node:test');
const { join } = require('node:path');

const source = fs.readFileSync(join(__dirname, '..', 'public', 'web-update-preference.js'), 'utf8');

function loadPreference({ storageAvailable = true } = {}) {
  const values = new Map();
  const storage = {
    getItem(key) {
      if (!storageAvailable) throw new Error('storage unavailable');
      return values.get(key) ?? null;
    },
    setItem(key, value) {
      if (!storageAvailable) throw new Error('storage unavailable');
      values.set(key, value);
    },
  };
  const context = { window: { localStorage: storage } };
  vm.runInNewContext(source, context);
  return { preference: context.window.WebUpdatePreference, storage };
}

test('startup update checks default on and retain the user selection', () => {
  const { preference } = loadPreference();

  assert.equal(preference.isEnabled(), true);
  preference.setEnabled(false);
  assert.equal(preference.isEnabled(), false);
  preference.setEnabled(true);
  assert.equal(preference.isEnabled(), true);
});

test('unavailable browser storage leaves manual update checks usable', () => {
  const { preference } = loadPreference({ storageAvailable: false });

  assert.equal(preference.isEnabled(), true);
  assert.doesNotThrow(() => preference.setEnabled(false));
  assert.equal(preference.isEnabled(), true);
});

test('the web updater explains when startup checks are disabled', () => {
  const app = fs.readFileSync(join(__dirname, '..', 'public', 'app.php'), 'utf8');
  assert.match(app, /启动时检查更新已关闭/);
  assert.match(app, /点击[“"]检查更新[”"]手动获取版本/);
});
