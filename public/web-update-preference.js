(function (root) {
  const key = 'short-video-web-update-autocheck-v1';

  function isEnabled() {
    try {
      return root.localStorage.getItem(key) !== 'off';
    } catch {
      return true;
    }
  }

  function setEnabled(enabled) {
    try {
      root.localStorage.setItem(key, enabled ? 'on' : 'off');
    } catch {
      // Manual checks remain available when browser storage is blocked.
    }
  }

  root.WebUpdatePreference = { isEnabled, setEnabled };
})(window);
