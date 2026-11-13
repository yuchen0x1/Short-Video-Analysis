<?php
$webAppVersion = class_exists(\Composer\InstalledVersions::class)
    ? (string) (\Composer\InstalledVersions::getPrettyVersion('yuchen0x1/short-video-watermark-remover') ?? 'dev')
    : 'dev';
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<meta name="theme-color" content="#071114">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="去水印解析">
<link rel="manifest" href="manifest.webmanifest">
<link rel="apple-touch-icon" sizes="192x192" href="icon-192.png">
<title>去水印解析 · 短视频工具</title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='7' fill='%231b4a49'/%3E%3Cpath d='M9 9h6v6H9zM17 17h6v6h-6zM17 9h6v6h-6zM9 17h6v6H9z' fill='%23f5c85d'/%3E%3C/svg%3E">
<script src="workbench.js"></script>
<script>
// 首屏前确定主题，避免深色用户先看到浅色闪烁；只读取不写入，持久化交给显式切换。
(function(){try{var saved=localStorage.getItem('watermark-parse-theme-v2');var dark=saved==='dark'||(saved!=='light'&&(!window.matchMedia||!window.matchMedia('(prefers-color-scheme: light)').matches));if(dark)document.documentElement.classList.add('dark')}catch(e){document.documentElement.classList.add('dark')}})();
</script>
<style>
/* ==========================================================================
   CYBER TECH HUD DESIGN SYSTEM - 短视频无水印工作台 极客科技视觉引擎
   ========================================================================== */
:root {
  --bg-base: #03080c;
  --bg-gradient: radial-gradient(circle at 18% 12%, rgba(0, 245, 212, 0.08) 0%, transparent 45%),
                 radial-gradient(circle at 82% 8%, rgba(157, 78, 221, 0.08) 0%, transparent 40%),
                 radial-gradient(circle at 50% 90%, rgba(0, 187, 249, 0.06) 0%, transparent 50%),
                 linear-gradient(180deg, #03070b 0%, #061017 48%, #04090e 100%);
  --grid-line: rgba(0, 245, 212, 0.035);
  --paper: rgba(8, 19, 27, 0.78);
  --paper-solid: #09151e;
  --paper-hover: rgba(12, 27, 38, 0.88);
  --soft: rgba(0, 245, 212, 0.03);
  --warm: rgba(255, 255, 255, 0.02);
  --ink: #eaf6f4;
  --muted: #8ea8a2;
  --faint: #56726c;
  --line: rgba(0, 245, 212, 0.14);
  --line-strong: rgba(0, 245, 212, 0.32);
  --neon-cyan: #00f5d4;
  --neon-cyan-soft: rgba(0, 245, 212, 0.14);
  --neon-cyan-glow: 0 0 24px rgba(0, 245, 212, 0.35);
  --neon-blue: #00bbf9;
  --neon-blue-glow: 0 0 24px rgba(0, 187, 249, 0.35);
  --neon-purple: #9d4edd;
  --coral: #ff4d6d;
  --coral-dark: #e02447;
  --coral-soft: rgba(255, 77, 109, 0.15);
  --coral-glow: 0 0 28px rgba(255, 77, 109, 0.36);
  --amber: #ffb703;
  --amber-soft: rgba(255, 183, 3, 0.14);
  --danger: #ff5c5c;
  --danger-soft: rgba(255, 92, 92, 0.12);
  --shadow: 0 20px 60px rgba(0, 0, 0, 0.55), 0 0 1px rgba(0, 245, 212, 0.25);
  --small-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
  --r: 10px;
  --font-mono: "Cascadia Code", "Fira Code", "Consolas", monospace;
  --motion: .22s cubic-bezier(0.16, 1, 0.3, 1);
}

:root:not(.dark) {
  --bg-base: #f1f6f8;
  --bg-gradient: radial-gradient(circle at 18% 12%, rgba(0, 180, 160, 0.07) 0%, transparent 45%),
                 radial-gradient(circle at 82% 8%, rgba(114, 50, 180, 0.05) 0%, transparent 40%),
                 linear-gradient(180deg, #edf3f5 0%, #f7fafb 50%, #e8f0f3 100%);
  --grid-line: rgba(0, 140, 130, 0.04);
  --paper: rgba(255, 255, 255, 0.88);
  --paper-solid: #ffffff;
  --paper-hover: #ffffff;
  --soft: rgba(0, 140, 130, 0.035);
  --warm: rgba(255, 255, 255, 0.6);
  --ink: #112024;
  --muted: #566c72;
  --faint: #81989e;
  --line: rgba(0, 140, 130, 0.16);
  --line-strong: rgba(0, 140, 130, 0.35);
  --neon-cyan: #008f7a;
  --neon-cyan-soft: rgba(0, 143, 122, 0.12);
  --neon-cyan-glow: 0 0 18px rgba(0, 143, 122, 0.2);
  --neon-blue: #0284c7;
  --neon-blue-glow: 0 0 18px rgba(2, 132, 199, 0.2);
  --coral: #e63946;
  --coral-dark: #c92230;
  --coral-soft: rgba(230, 57, 70, 0.1);
  --coral-glow: 0 0 20px rgba(230, 57, 70, 0.2);
  --amber: #d97706;
  --amber-soft: rgba(217, 119, 6, 0.12);
  --danger: #dc2626;
  --danger-soft: rgba(220, 38, 38, 0.1);
  --shadow: 0 18px 45px rgba(20, 45, 55, 0.09);
  --small-shadow: 0 6px 18px rgba(20, 45, 55, 0.06);
}

@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    animation-duration: .01ms !important;
    transition-duration: .01ms !important;
  }
}

* { box-sizing: border-box; }
[hidden] { display: none !important; }

html, body {
  margin: 0;
  min-height: 100%;
}

html {
  background: var(--bg-base);
}

body {
  background: var(--bg-gradient);
  color: var(--ink);
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "PingFang SC", "Microsoft YaHei", sans-serif;
  font-size: 14px;
  line-height: 1.6;
  position: relative;
  overflow-x: hidden;
  transition: background var(--motion), color var(--motion);
}

/* 赛博空间背景网格与粒子暗纹 */
body::before {
  content: "";
  position: fixed;
  inset: 0;
  z-index: -2;
  pointer-events: none;
  background-image:
    linear-gradient(var(--grid-line) 1px, transparent 1px),
    linear-gradient(90deg, var(--grid-line) 1px, transparent 1px);
  background-size: 38px 38px;
  -webkit-mask-image: radial-gradient(ellipse at 50% 30%, rgba(0,0,0,0.95), transparent 85%);
  mask-image: radial-gradient(ellipse at 50% 30%, rgba(0,0,0,0.95), transparent 85%);
}

body::after {
  content: "";
  position: fixed;
  inset: 0;
  z-index: -1;
  pointer-events: none;
  background: radial-gradient(circle at 50% 0%, rgba(0, 245, 212, 0.04), transparent 60%);
}

button, input, textarea, a { font: inherit; }
button { cursor: pointer; }
a { color: inherit; }
:focus-visible {
  outline: 2px solid var(--neon-cyan);
  outline-offset: 3px;
  box-shadow: 0 0 12px var(--neon-cyan-soft);
}

/* 霓虹滚动条 */
::-webkit-scrollbar { width: 8px; height: 8px; }
::-webkit-scrollbar-track { background: rgba(0, 0, 0, 0.2); }
::-webkit-scrollbar-thumb {
  background: rgba(0, 245, 212, 0.22);
  border-radius: 999px;
  border: 2px solid transparent;
  background-clip: padding-box;
}
::-webkit-scrollbar-thumb:hover {
  background: rgba(0, 245, 212, 0.45);
}

/* 顶栏 Header HUD 风格 */
.topbar {
  position: sticky;
  top: 0;
  z-index: 100;
  background: rgba(4, 12, 17, 0.82);
  backdrop-filter: blur(20px) saturate(180%);
  -webkit-backdrop-filter: blur(20px) saturate(180%);
  border-bottom: 1px solid var(--line);
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.4), inset 0 -1px 0 rgba(0, 245, 212, 0.1);
}
:root:not(.dark) .topbar {
  background: rgba(255, 255, 255, 0.86);
  border-bottom-color: rgba(0, 140, 130, 0.2);
}

.topbar-inner {
  width: min(1340px, calc(100% - 40px));
  min-height: 66px;
  margin: auto;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
}

.brand {
  display: flex;
  align-items: center;
  gap: 13px;
  min-width: 0;
}

.brand-mark {
  width: 38px;
  height: 38px;
  flex: 0 0 38px;
  display: grid;
  place-items: center;
  border-radius: 8px;
  background: linear-gradient(135deg, var(--neon-cyan) 0%, #00bbf9 100%);
  color: #031518;
  font-size: 19px;
  font-weight: 900;
  box-shadow: var(--neon-cyan-glow);
  position: relative;
}
.brand-mark::after {
  content: "";
  position: absolute;
  inset: -2px;
  border-radius: 10px;
  border: 1px solid rgba(0, 245, 212, 0.5);
  pointer-events: none;
}

.brand-name {
  margin: 0;
  font-size: 17px;
  line-height: 1.2;
  font-weight: 900;
  letter-spacing: 0.04em;
  background: linear-gradient(90deg, var(--ink) 0%, var(--neon-cyan) 160%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}
:root:not(.dark) .brand-name {
  background: none;
  -webkit-text-fill-color: initial;
  color: var(--ink);
}

.brand-sub {
  margin: 3px 0 0;
  color: var(--muted);
  font-size: 11px;
  font-family: var(--font-mono);
  letter-spacing: 0.08em;
}

.topbar-actions {
  display: flex;
  align-items: center;
  gap: 16px;
}

.online {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  color: var(--muted);
  font-size: 12px;
  font-family: var(--font-mono);
  padding: 5px 12px;
  border-radius: 999px;
  background: rgba(0, 245, 212, 0.05);
  border: 1px solid var(--line);
}
.online-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: var(--neon-cyan);
  box-shadow: 0 0 8px var(--neon-cyan);
  animation: cyber-beacon 2.2s infinite;
}
@keyframes cyber-beacon {
  0%, 100% { opacity: 1; transform: scale(1); box-shadow: 0 0 8px var(--neon-cyan); }
  50% { opacity: 0.5; transform: scale(0.85); box-shadow: 0 0 2px var(--neon-cyan); }
}

.theme-toggle {
  width: 36px;
  height: 36px;
  padding: 0;
  display: grid;
  place-items: center;
  border: 1px solid var(--line);
  border-radius: 8px;
  background: rgba(255, 255, 255, 0.04);
  color: var(--ink);
  transition: all var(--motion);
}
.theme-toggle:hover {
  background: var(--neon-cyan-soft);
  border-color: var(--neon-cyan);
  color: var(--neon-cyan);
  transform: scale(1.05);
  box-shadow: 0 0 14px var(--neon-cyan-soft);
}

/* 工作区 Workspace 布局 */
.workspace {
  width: min(1340px, calc(100% - 40px));
  margin: auto;
  padding: 40px 0 60px;
  display: grid;
  grid-template-columns: minmax(0, 1fr) 350px;
  gap: 26px;
  align-items: start;
}
.main-column { min-width: 0; }

/* 头部 Intro 炫酷科技排版 */
.intro {
  margin: 0 0 26px;
  position: relative;
}
.eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  color: var(--neon-cyan);
  font-size: 11px;
  font-weight: 800;
  font-family: var(--font-mono);
  letter-spacing: 0.16em;
  text-transform: uppercase;
}
.eyebrow::before {
  content: "//";
  color: var(--coral);
  font-weight: 900;
}
.intro h1 {
  margin: 10px 0 10px;
  max-width: 760px;
  font-size: clamp(32px, 3.8vw, 50px);
  line-height: 1.15;
  letter-spacing: -0.02em;
  font-weight: 900;
}
.intro h1 em {
  font-style: normal;
  background: linear-gradient(135deg, var(--neon-cyan) 0%, #00bbf9 50%, var(--coral) 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  text-shadow: 0 0 30px rgba(0, 245, 212, 0.25);
}
:root:not(.dark) .intro h1 em {
  background: none;
  -webkit-text-fill-color: initial;
  color: var(--neon-cyan);
}
.intro p {
  max-width: 620px;
  margin: 0;
  color: var(--muted);
  font-size: 15px;
}

.intro-metrics {
  display: flex;
  gap: 12px;
  margin-top: 20px;
}
.intro-metrics > div {
  display: flex;
  align-items: baseline;
  gap: 8px;
  padding: 7px 14px;
  border: 1px solid var(--line);
  border-radius: 8px;
  background: rgba(0, 245, 212, 0.035);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.05);
  backdrop-filter: blur(10px);
}
.intro-metrics strong {
  color: var(--neon-cyan);
  font-size: 16px;
  font-family: var(--font-mono);
  font-weight: 800;
}
.intro-metrics span {
  color: var(--muted);
  font-size: 11px;
}

/* 核心 HUD 晶体面板 */
.panel {
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: var(--r);
  box-shadow: var(--shadow);
  backdrop-filter: blur(24px) saturate(160%);
  -webkit-backdrop-filter: blur(24px) saturate(160%);
  position: relative;
  isolation: isolate;
  transition: border-color var(--motion), box-shadow var(--motion), transform var(--motion);
}
.panel::before {
  content: "";
  position: absolute;
  top: 0;
  left: 0;
  width: 24px;
  height: 2px;
  background: linear-gradient(90deg, var(--neon-cyan), transparent);
  pointer-events: none;
}
.panel:hover {
  border-color: var(--line-strong);
  box-shadow: var(--shadow), 0 0 35px rgba(0, 245, 212, 0.08);
}

.ingest-panel {
  padding: 24px;
  border-color: rgba(0, 245, 212, 0.24);
  background: linear-gradient(135deg, rgba(0, 245, 212, 0.04) 0%, rgba(8, 19, 27, 0.82) 40%, rgba(157, 78, 221, 0.03) 100%);
}
.ingest-panel::after {
  content: "";
  position: absolute;
  top: -1px;
  right: -1px;
  width: 14px;
  height: 14px;
  border-top: 2px solid var(--neon-cyan);
  border-right: 2px solid var(--neon-cyan);
  pointer-events: none;
}

.panel-heading {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 18px;
  margin-bottom: 22px;
}
.panel-kicker {
  margin: 0 0 4px;
  color: var(--neon-cyan);
  font-size: 11px;
  font-weight: 800;
  font-family: var(--font-mono);
  letter-spacing: 0.14em;
  text-transform: uppercase;
}
.panel-title {
  margin: 0;
  font-size: 20px;
  line-height: 1.25;
  font-weight: 900;
  color: var(--ink);
}
.panel-note {
  margin: 5px 0 0;
  color: var(--muted);
  font-size: 13px;
}

/* 科技控制台模式切换器 */
.mode-switch {
  display: inline-flex;
  gap: 3px;
  padding: 4px;
  background: rgba(0, 0, 0, 0.35);
  border: 1px solid var(--line);
  border-radius: 8px;
  flex-shrink: 0;
}
:root:not(.dark) .mode-switch {
  background: rgba(0, 140, 130, 0.06);
}

.mode-btn {
  min-height: 32px;
  padding: 0 13px;
  border: 0;
  border-radius: 6px;
  background: transparent;
  color: var(--muted);
  font-size: 12px;
  font-weight: 800;
  font-family: var(--font-mono);
  transition: all var(--motion);
}
.mode-btn:hover {
  color: var(--ink);
  background: rgba(255, 255, 255, 0.05);
}
.mode-btn.active {
  background: linear-gradient(135deg, var(--neon-cyan) 0%, #00bbf9 100%);
  color: #03171a;
  box-shadow: 0 0 16px rgba(0, 245, 212, 0.45);
}

/* 科技输入框与按钮 */
.field-label {
  display: block;
  margin-bottom: 8px;
  color: var(--neon-cyan);
  font-size: 11px;
  font-weight: 800;
  font-family: var(--font-mono);
  letter-spacing: 0.1em;
  text-transform: uppercase;
}
.input-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 46px 124px;
  gap: 10px;
}
.input-wrap { min-width: 0; }
.input-wrap input, .batch-input {
  width: 100%;
  border: 1px solid var(--line-strong);
  border-radius: 8px;
  background: rgba(3, 11, 16, 0.6);
  color: var(--ink);
  outline: none;
  font-family: inherit;
  font-size: 14px;
  box-shadow: inset 0 2px 6px rgba(0, 0, 0, 0.4);
  transition: all var(--motion);
}
:root:not(.dark) .input-wrap input, :root:not(.dark) .batch-input {
  background: #ffffff;
  box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.05);
}
.input-wrap input {
  height: 50px;
  padding: 0 16px;
}
.input-wrap input::placeholder, .batch-input::placeholder {
  color: var(--faint);
}
.input-wrap input:focus, .batch-input:focus {
  border-color: var(--neon-cyan);
  box-shadow: 0 0 0 3px var(--neon-cyan-soft), 0 0 24px rgba(0, 245, 212, 0.18);
  background: rgba(5, 16, 23, 0.85);
}
.batch-input {
  min-height: 146px;
  padding: 14px 16px;
  resize: vertical;
  line-height: 1.65;
  font-family: var(--font-mono);
}

/* 按钮系 */
.icon-btn, .btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  border-radius: 8px;
  transition: all var(--motion);
  font-weight: 800;
}
.icon-btn {
  width: 46px;
  height: 50px;
  padding: 0;
  border: 1px solid var(--line-strong);
  background: rgba(0, 245, 212, 0.05);
  color: var(--neon-cyan);
}
.icon-btn:hover {
  background: var(--neon-cyan-soft);
  border-color: var(--neon-cyan);
  box-shadow: 0 0 18px var(--neon-cyan-soft);
  transform: translateY(-1px);
}

.btn {
  min-height: 42px;
  padding: 0 16px;
  border: 1px solid var(--line);
  background: rgba(255, 255, 255, 0.04);
  color: var(--ink);
  font-size: 13px;
}
.btn:hover:not(:disabled) {
  border-color: var(--line-strong);
  background: rgba(255, 255, 255, 0.08);
  transform: translateY(-1px);
}
.btn:disabled, .icon-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
  transform: none !important;
}

.btn-primary {
  border-color: transparent;
  background: linear-gradient(135deg, var(--coral) 0%, #ff758f 100%);
  color: #ffffff;
  box-shadow: var(--coral-glow);
  position: relative;
  overflow: hidden;
}
.btn-primary::after {
  content: "";
  position: absolute;
  top: -50%;
  left: -50%;
  width: 200%;
  height: 200%;
  background: linear-gradient(45deg, transparent 40%, rgba(255,255,255,0.2) 50%, transparent 60%);
  transform: rotate(25deg);
  animation: cyber-sweep 4.5s infinite;
}
@keyframes cyber-sweep {
  0% { transform: translateX(-120%) rotate(25deg); }
  25%, 100% { transform: translateX(120%) rotate(25deg); }
}
.btn-primary:hover:not(:disabled) {
  background: linear-gradient(135deg, #ff6b87 0%, #ff8fa3 100%);
  box-shadow: 0 0 32px rgba(255, 77, 109, 0.55);
  transform: translateY(-1px);
}

.btn-teal {
  border-color: rgba(0, 245, 212, 0.4);
  background: linear-gradient(135deg, var(--neon-cyan) 0%, #00bbf9 100%);
  color: #031518;
  box-shadow: var(--neon-cyan-glow);
}
.btn-teal:hover:not(:disabled) {
  box-shadow: 0 0 28px rgba(0, 245, 212, 0.6);
  transform: translateY(-1px);
}

/* 收藏激活态：琥珀星标高亮 */
.btn.is-fav {
  border-color: rgba(255, 183, 3, 0.5);
  color: var(--amber);
  background: var(--amber-soft);
  box-shadow: 0 0 16px var(--amber-soft);
}
.history-actions button.is-fav {
  color: var(--amber);
}
.history-actions button.is-fav:hover {
  background: var(--amber-soft);
  color: var(--amber);
}

.btn-quiet {
  background: transparent;
  border-color: transparent;
  color: var(--muted);
}
.btn-quiet:hover {
  color: var(--ink);
  background: rgba(255, 255, 255, 0.05);
}

.input-hint {
  margin: 10px 0 0;
  color: var(--faint);
  font-size: 12px;
}
.input-context {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  margin-top: 14px;
  color: var(--muted);
  font-size: 11px;
  font-family: var(--font-mono);
}
.input-context span {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.input-context i {
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background: var(--neon-cyan);
  box-shadow: 0 0 8px var(--neon-cyan);
}

/* 平台微光标签 Platform Chips */
.platform-strip {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 20px;
  padding-top: 16px;
  border-top: 1px dashed var(--line);
}
.platform-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 26px;
  padding: 0 11px;
  border: 1px solid var(--line);
  border-radius: 999px;
  background: rgba(0, 245, 212, 0.03);
  color: var(--muted);
  font-size: 12px;
  transition: all var(--motion);
}
.platform-chip:hover {
  background: rgba(0, 245, 212, 0.1);
  border-color: var(--neon-cyan);
  color: var(--ink);
  transform: translateY(-1px);
  box-shadow: 0 0 12px var(--neon-cyan-soft);
}
.platform-chip i {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--chip);
  box-shadow: 0 0 6px var(--chip);
}

/* 状态指示与雷达旋转器 */
.state-row {
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 42px;
  margin-top: 14px;
  padding: 0 14px;
  border-radius: 8px;
  background: rgba(0, 245, 212, 0.05);
  border: 1px solid var(--line);
  color: var(--neon-cyan);
  font-size: 13px;
  font-family: var(--font-mono);
}
.spinner {
  width: 16px;
  height: 16px;
  border: 2px solid rgba(0, 245, 212, 0.2);
  border-top-color: var(--neon-cyan);
  border-radius: 50%;
  animation: cyber-spin 0.7s linear infinite;
  box-shadow: 0 0 8px var(--neon-cyan-soft);
}
@keyframes cyber-spin {
  to { transform: rotate(360deg); }
}

.error-alert {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  margin-top: 14px;
  padding: 12px 14px;
  border: 1px solid rgba(255, 92, 92, 0.35);
  border-radius: 8px;
  background: var(--danger-soft);
  color: #ff8585;
  font-size: 13px;
  box-shadow: 0 0 20px rgba(255, 92, 92, 0.15);
}

/* 核心解析结果卡片 Result Panel */
.result-panel, .batch-panel, .profile-panel {
  margin-top: 26px;
}
.result-layout {
  display: grid;
  grid-template-columns: minmax(0, 1.05fr) minmax(290px, .95fr);
  min-height: 340px;
}
.result-video-wrap {
  min-height: 340px;
  padding: 20px;
  background: #02070a;
  border-right: 1px solid var(--line);
  display: grid;
  place-items: center;
  position: relative;
}
.result-video-wrap::before {
  content: "HUD // MONITOR";
  position: absolute;
  top: 10px;
  left: 14px;
  font-family: var(--font-mono);
  font-size: 9px;
  color: var(--faint);
  letter-spacing: 0.16em;
}
.result-video-wrap video {
  display: block;
  width: 100%;
  max-height: 480px;
  aspect-ratio: 16/10;
  object-fit: contain;
  border-radius: 8px;
  background: #000000;
  border: 1px solid var(--line);
  box-shadow: 0 0 30px rgba(0, 0, 0, 0.8), 0 0 15px rgba(0, 245, 212, 0.08);
}

.result-info {
  padding: 26px;
  display: flex;
  flex-direction: column;
  min-width: 0;
  background: rgba(0, 245, 212, 0.015);
}
.result-tag {
  display: inline-flex;
  align-self: flex-start;
  padding: 4px 10px;
  background: var(--neon-cyan-soft);
  color: var(--neon-cyan);
  border: 1px solid rgba(0, 245, 212, 0.3);
  border-radius: 4px;
  font-size: 11px;
  font-weight: 800;
  font-family: var(--font-mono);
  letter-spacing: 0.06em;
}
.result-title {
  margin: 14px 0 8px;
  font-size: 21px;
  line-height: 1.35;
  font-weight: 900;
  overflow-wrap: anywhere;
  color: var(--ink);
}
.result-meta {
  margin: 0;
  color: var(--muted);
  font-size: 13px;
  font-family: var(--font-mono);
}

.creator-row {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-top: 20px;
  padding: 10px 12px;
  border-radius: 8px;
  background: rgba(255, 255, 255, 0.025);
  border: 1px solid var(--line);
}
.avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  border: 1px solid var(--neon-cyan);
  object-fit: cover;
  box-shadow: 0 0 12px var(--neon-cyan-soft);
}
.creator-label {
  color: var(--faint);
  font-size: 10px;
  font-family: var(--font-mono);
  letter-spacing: 0.08em;
  text-transform: uppercase;
}
.creator-name {
  margin-top: 1px;
  font-size: 14px;
  font-weight: 800;
  color: var(--ink);
}

.result-stats {
  display: flex;
  gap: 10px;
  margin-top: 20px;
}
.result-stat {
  flex: 1;
  padding: 10px 14px;
  border: 1px solid var(--line);
  border-top: 2px solid var(--amber);
  border-radius: 6px;
  background: rgba(255, 183, 3, 0.03);
}
.result-stat dt {
  color: var(--faint);
  font-size: 11px;
  font-family: var(--font-mono);
}
.result-stat dd {
  margin: 3px 0 0;
  font-size: 16px;
  font-weight: 900;
  font-family: var(--font-mono);
  color: var(--ink);
}

.result-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 9px;
  margin-top: auto;
  padding-top: 24px;
}

/* 图集网格展示 Gallery Grid */
.result-images {
  width: 100%;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(135px, 1fr));
  gap: 10px;
  align-items: start;
}
.result-image-card {
  position: relative;
  overflow: hidden;
  border-radius: 8px;
  border: 1px solid var(--line);
  background: #02070a;
  transition: all var(--motion);
}
.result-image-card:hover {
  border-color: var(--neon-cyan);
  box-shadow: 0 0 20px var(--neon-cyan-soft);
  transform: translateY(-2px);
}
.result-image-card img {
  display: block;
  width: 100%;
  height: auto;
  max-height: 460px;
  object-fit: contain;
}
.result-image-download {
  position: absolute;
  right: 8px;
  bottom: 8px;
  width: 32px;
  height: 32px;
  display: grid;
  place-items: center;
  border-radius: 6px;
  background: rgba(0, 0, 0, 0.75);
  border: 1px solid var(--neon-cyan);
  color: var(--neon-cyan);
  text-decoration: none;
  backdrop-filter: blur(8px);
  transition: all var(--motion);
}
.result-image-download:hover {
  background: var(--neon-cyan);
  color: #031518;
  box-shadow: var(--neon-cyan-glow);
}

/* 侧边栏 Sidebar */
.sidebar {
  display: grid;
  gap: 16px;
  position: sticky;
  top: 86px;
}
.side-panel {
  padding: 20px;
}
.side-heading {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 16px;
}
.side-heading h2 {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
  color: var(--ink);
}
.side-heading p {
  margin: 3px 0 0;
  color: var(--muted);
  font-size: 12px;
}
.count-badge {
  min-width: 28px;
  height: 24px;
  padding: 0 8px;
  display: inline-grid;
  place-items: center;
  border-radius: 999px;
  background: linear-gradient(135deg, var(--amber) 0%, #fb8500 100%);
  color: #031215;
  font-size: 11px;
  font-family: var(--font-mono);
  font-weight: 900;
  box-shadow: 0 0 12px var(--amber-soft);
}

.history-list { display: grid; gap: 8px; }
.history-entry {
  display: grid;
  grid-template-columns: 62px minmax(0, 1fr) auto;
  gap: 10px;
  align-items: center;
  padding: 8px 10px;
  border: 1px solid var(--line);
  border-radius: 8px;
  background: rgba(0, 245, 212, 0.02);
  transition: all var(--motion);
}
.history-entry:hover {
  background: rgba(0, 245, 212, 0.06);
  border-color: var(--neon-cyan);
  box-shadow: 0 0 14px var(--neon-cyan-soft);
}
.history-thumb {
  width: 62px;
  height: 42px;
  object-fit: cover;
  border-radius: 5px;
  background: #02070a;
  border: 1px solid var(--line);
}
.history-title {
  margin: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 13px;
  font-weight: 800;
  color: var(--ink);
}
.history-meta {
  margin: 2px 0 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: var(--muted);
  font-size: 11px;
  font-family: var(--font-mono);
}
.history-actions button {
  width: 28px;
  height: 28px;
  padding: 0;
  display: grid;
  place-items: center;
  border: 0;
  border-radius: 5px;
  background: transparent;
  color: var(--muted);
  transition: all var(--motion);
}
.history-actions button:hover {
  background: var(--neon-cyan-soft);
  color: var(--neon-cyan);
}

.side-divider {
  height: 1px;
  margin: 18px 0;
  background: var(--line);
}
.clear-btn {
  min-height: 28px;
  padding: 0 10px;
  border: 0;
  border-radius: 5px;
  background: transparent;
  color: var(--muted);
  font-size: 12px;
  transition: all var(--motion);
}
.clear-btn:hover {
  background: var(--coral-soft);
  color: var(--coral);
}

/* 账号与信息表单 */
.account-form { display: grid; gap: 10px; }
.account-form input {
  width: 100%;
  height: 40px;
  padding: 0 12px;
  border: 1px solid var(--line);
  border-radius: 6px;
  background: rgba(0, 0, 0, 0.35);
  color: var(--ink);
  outline: none;
  font-family: var(--font-mono);
  font-size: 13px;
}
.account-form input:focus {
  border-color: var(--neon-cyan);
  box-shadow: 0 0 0 3px var(--neon-cyan-soft);
}
.account-status {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: var(--muted);
  font-size: 11px;
  font-family: var(--font-mono);
  font-weight: 700;
}
.account-status i {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--faint);
}
.account-status.is-ready i {
  background: var(--neon-cyan);
  box-shadow: 0 0 8px var(--neon-cyan);
}

.quick-list { display: grid; gap: 10px; }
.quick-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  color: var(--muted);
  font-size: 12px;
  font-family: var(--font-mono);
}
.quick-value {
  color: var(--neon-cyan);
  font-weight: 800;
}

/* 更新卡片 */
.update-status {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  color: var(--muted);
  font-size: 12px;
  font-family: var(--font-mono);
}
.update-status i {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: var(--amber);
  box-shadow: 0 0 8px var(--amber);
}
.update-status.is-ready i {
  background: var(--neon-cyan);
  box-shadow: 0 0 8px var(--neon-cyan);
}
.update-status.is-error i {
  background: var(--danger);
  box-shadow: 0 0 8px var(--danger);
}
.update-card p {
  margin: 4px 0 12px;
  color: var(--muted);
  font-size: 12px;
}
.update-card .update-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

/* 批量任务与主页列表 */
.section-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 18px 22px 14px;
  border-bottom: 1px solid var(--line);
}
.section-heading h2 { margin: 0; font-size: 17px; font-weight: 900; }
.batch-list, .profile-list { display: grid; }
.batch-item, .profile-item {
  display: grid;
  grid-template-columns: 108px minmax(0, 1fr) auto;
  gap: 14px;
  align-items: center;
  padding: 14px 22px;
  border-bottom: 1px solid var(--line);
  transition: background var(--motion);
}
.batch-item:hover, .profile-item:hover {
  background: rgba(0, 245, 212, 0.025);
}
.batch-thumb, .profile-thumb {
  width: 108px;
  height: 64px;
  object-fit: cover;
  border-radius: 6px;
  border: 1px solid var(--line);
  background: #02070a;
}
.batch-title, .profile-title {
  margin: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 14px;
  font-weight: 800;
  color: var(--ink);
}
.batch-meta, .profile-meta {
  margin: 3px 0 0;
  color: var(--muted);
  font-size: 12px;
  font-family: var(--font-mono);
}
.profile-toolbar {
  display: flex;
  align-items: center;
  gap: 8px;
}
.profile-pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 16px 22px;
  border-top: 1px solid var(--line);
  font-family: var(--font-mono);
  font-weight: 700;
}

/* 直播间 Stream HUD 卡片 */
.live-stream {
  display: grid;
  grid-template-columns: 66px minmax(0, 1fr) auto;
  gap: 14px;
  align-items: center;
  padding: 14px 22px;
  border-bottom: 1px solid var(--line);
}
.live-stream-format {
  display: inline-grid;
  place-items: center;
  height: 32px;
  border: 1px solid rgba(0, 245, 212, 0.35);
  border-radius: 6px;
  font-family: var(--font-mono);
  font-size: 11px;
  font-weight: 900;
  letter-spacing: 0.08em;
  color: var(--neon-cyan);
  background: var(--neon-cyan-soft);
}
.live-stream-url {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: var(--muted);
  font-size: 12px;
  font-family: var(--font-mono);
}
.live-status-badge {
  display: inline-flex;
  align-items: center;
  min-height: 24px;
  padding: 0 10px;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 800;
  font-family: var(--font-mono);
}
.live-status-badge.is-live {
  background: var(--neon-cyan-soft);
  color: var(--neon-cyan);
  border: 1px solid rgba(0, 245, 212, 0.3);
}
.live-status-badge.is-offline {
  background: var(--amber-soft);
  color: var(--amber);
  border: 1px solid rgba(255, 183, 3, 0.3);
}

footer {
  width: min(1340px, calc(100% - 40px));
  margin: 0 auto;
  padding: 0 0 36px;
  text-align: center;
  color: var(--faint);
  font-size: 12px;
  font-family: var(--font-mono);
}

/* 动效与自适应响应 */
.intro, .ingest-panel, .sidebar .panel, .result-panel, .batch-panel {
  animation: cyber-rise .45s cubic-bezier(0.16, 1, 0.3, 1) backwards;
}
@keyframes cyber-rise {
  from { opacity: 0; transform: translateY(16px); }
  to { opacity: 1; transform: none; }
}

@media (max-width: 980px) {
  .workspace { grid-template-columns: 1fr; }
  .sidebar { position: static; grid-template-columns: minmax(0, 1.2fr) minmax(260px, .8fr); }
}
@media (max-width: 720px) {
  .topbar-inner, .workspace, footer { width: min(100% - 24px, 1340px); }
  .workspace { padding: 24px 0 46px; gap: 20px; }
  .intro h1 { font-size: 30px; }
  .ingest-panel { padding: 16px; }
  .panel-heading { flex-direction: column; gap: 12px; }
  .mode-switch { width: 100%; }
  .mode-btn { flex: 1; padding: 0 6px; }
  .result-layout { grid-template-columns: 1fr; }
  .result-video-wrap { min-height: 0; padding: 14px; border-right: 0; border-bottom: 1px solid var(--line); }
  .result-info { padding: 18px; }
  .sidebar { grid-template-columns: 1fr; }
  .result-stats { flex-wrap: wrap; }
  .result-stat { flex: 1 1 45%; }
}
@media (max-width: 520px) {
  .input-row { grid-template-columns: minmax(0, 1fr) 46px; }
  .input-row .btn-primary { grid-column: 1 / -1; }
  .batch-item, .profile-item { grid-template-columns: 76px minmax(0, 1fr); padding: 12px 14px; }
  .batch-thumb, .profile-thumb { width: 76px; height: 50px; }
  .batch-actions, .profile-actions { grid-column: 1 / -1; justify-content: flex-start; }
  .result-actions .btn { flex: 1 1 calc(50% - 8px); }
}
.library-search { width:100%; min-height:42px; border:1px solid var(--line); border-radius:8px; padding:10px 12px; background:transparent; color:inherit; font:inherit; margin-bottom:12px; }
:root:not(.dark) .ingest-panel { background:var(--paper); }
:root:not(.dark) .account-form input { background:#fff; }
.account-actions { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
.history-empty { display:grid; justify-items:center; gap:8px; padding:22px 10px; color:var(--muted); text-align:center; font-size:13px; }
.history-empty[hidden] { display:none; }
.history-empty strong { color:var(--ink); }
.history-empty-icon { color:var(--neon-cyan); }
.library-search:focus-visible, button:focus-visible, a:focus-visible { outline:2px solid #22d3ee; outline-offset:3px; }
#batch-summary { font-variant-numeric:tabular-nums; }
@media (max-width:520px) { .mode-switch { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); } .mode-btn { white-space:nowrap; } }
@media (prefers-reduced-motion: reduce) { *, *::before, *::after { scroll-behavior:auto !important; animation:none !important; transition:none !important; } }
</style>
</head>
<body>
<header class="topbar"><div class="topbar-inner"><div class="brand"><span class="brand-mark" aria-hidden="true">去</span><div><p class="brand-name">去水印解析</p><p class="brand-sub">短视频无水印工作台 · v<?= htmlspecialchars($webAppVersion, ENT_QUOTES, 'UTF-8') ?></p></div></div><div class="topbar-actions"><span id="service-status" class="online"><i class="online-dot"></i><span>检查服务</span></span><span id="update-status" class="update-status" aria-live="polite"><i></i><span>检查更新</span></span><button id="install-app" class="theme-toggle" type="button" title="安装应用" aria-label="安装应用" hidden><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12"></path><path d="m7 10 5 5 5-5"></path><path d="M5 21h14"></path></svg></button><button id="theme-toggle" class="theme-toggle" type="button" title="切换主题" aria-label="切换主题"></button></div></div></header>
<main class="workspace">
  <div class="main-column">
    <section class="intro" aria-labelledby="page-title"><span class="eyebrow">VIDEO WORKSPACE / 01</span><h1 id="page-title">分享链接，<em>直接变成</em>可用视频。</h1><p>粘贴短视频分享链接，快速整理出无水印视频。解析结果、下载和最近记录都在同一个工作区完成。</p><div class="intro-metrics" aria-label="工作台能力"><div><strong>12</strong><span>支持平台</span></div><div><strong>本地</strong><span>优先解析</span></div><div><strong>20</strong><span>批量上限</span></div></div></section>
    <section class="panel ingest-panel" aria-label="视频解析入口">
      <div class="panel-heading"><div><p class="panel-kicker">Import link</p><h2 class="panel-title">从一个链接开始</h2><p class="panel-note">支持公开分享链接，不需要登录第三方平台。</p></div><div class="mode-switch" role="group" aria-label="解析模式"><button id="single-mode-btn" class="mode-btn active" type="button" aria-pressed="true">单个解析</button><button id="batch-mode-btn" class="mode-btn" type="button" aria-pressed="false">批量处理</button><button id="profile-mode-btn" class="mode-btn" type="button" aria-pressed="false">主页解析</button><button id="collection-mode-btn" class="mode-btn" type="button" aria-pressed="false">合集解析</button><button id="live-mode-btn" class="mode-btn" type="button" aria-pressed="false">直播解析</button><button id="discover-mode-btn" class="mode-btn" type="button" aria-pressed="false">发现</button></div></div>
      <div id="single-panel"><label id="mode-label" class="field-label" for="video-url">视频分享链接</label><div class="input-row"><div class="input-wrap"><input id="video-url" type="url" placeholder="粘贴抖音、快手、B站、小红书等链接" autocomplete="off" spellcheck="false"></div><button id="paste-btn" class="icon-btn" type="button" title="从剪贴板粘贴" aria-label="从剪贴板粘贴"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="8" y="3" width="8" height="4" rx="1"></rect><path d="M16 5h2a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h2"></path></svg></button><button id="parse-btn" class="btn btn-primary" type="button"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"></path><path d="m13 6 6 6-6 6"></path></svg><span>开始解析</span></button></div><p class="input-hint">小提示：也可以直接粘贴包含文字的整段分享文案，系统会自动提取链接。</p></div>
      <div class="input-context"><span><i></i>自动识别平台</span><span><i></i>视频与图集均支持</span><span><i></i>链接不会保存到服务器</span></div>
      <div id="batch-panel" hidden><label class="field-label" for="batch-input">每行放一个视频链接</label><textarea id="batch-input" class="batch-input" rows="5" placeholder="https://...&#10;https://...&#10;https://..." autocomplete="off" spellcheck="false" aria-label="批量视频链接"></textarea><p class="input-hint">最多处理 20 条链接，同时进行 3 路解析。</p><div class="batch-actions"><button id="batch-parse-btn" class="btn btn-primary" type="button"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 6h13"></path><path d="M8 12h13"></path><path d="M8 18h13"></path><path d="M3 6h.01"></path><path d="M3 12h.01"></path><path d="M3 18h.01"></path></svg><span>开始批量处理</span></button><button id="batch-stop-btn" class="btn btn-quiet" type="button" hidden>停止处理</button></div></div>
      <div class="platform-strip" aria-label="支持平台"><span class="platform-chip" style="--chip:#2d6cdf"><i></i>抖音</span><span class="platform-chip" style="--chip:#10a77a"><i></i>快手</span><span class="platform-chip" style="--chip:#e86d94"><i></i>B站</span><span class="platform-chip" style="--chip:#e64555"><i></i>小红书</span><span class="platform-chip" style="--chip:#f19a42"><i></i>皮皮虾</span><span class="platform-chip" style="--chip:#5e83d7"><i></i>微博</span><span class="platform-chip" style="--chip:#4d9d91"><i></i>视频号</span><span class="platform-chip" style="--chip:#25f4ee"><i></i>TikTok</span><span class="platform-chip" style="--chip:#8d6fc1"><i></i>更多平台</span></div>
      <div id="loading" class="state-row" aria-live="polite" hidden><span class="spinner" aria-hidden="true"></span><span id="loading-text">正在读取视频信息…</span></div><div id="batch-progress" class="state-row" aria-live="polite" hidden><span class="spinner" aria-hidden="true"></span><span id="batch-progress-text"></span></div>
      <div id="error" class="error-alert" role="alert" aria-live="assertive" hidden><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 8v5"></path><path d="M12 16h.01"></path></svg><span id="error-text"></span></div>
    </section>
    <section id="result" class="panel result-panel" hidden aria-label="解析结果"><div class="result-layout"><div class="result-video-wrap"><video id="result-video" controls playsinline preload="metadata"></video></div><div class="result-info"><span id="result-platform" class="result-tag">解析完成</span><h2 id="result-title" class="result-title"></h2><p id="result-meta" class="result-meta"></p><div class="creator-row"><img id="result-avatar" class="avatar" alt="" hidden><div><div class="creator-label">发布作者</div><div id="result-author" class="creator-name"></div></div></div><dl class="result-stats"><div class="result-stat"><dt>点赞</dt><dd id="result-like">-</dd></div><div class="result-stat"><dt>发布时间</dt><dd id="result-time">-</dd></div></dl><div class="result-actions"><a id="download-btn" class="btn btn-primary" download target="_blank" rel="noopener"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12"></path><path d="m7 10 5 5 5-5"></path><path d="M5 21h14"></path></svg><span>下载视频</span></a><button id="zip-btn" class="btn btn-primary" type="button" hidden><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg><span>打包下载图集 (ZIP)</span></button><a id="music-btn" class="btn" download target="_blank" rel="noopener" hidden><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18V5l12-2v13"></path><circle cx="6" cy="18" r="3"></circle><circle cx="18" cy="16" r="3"></circle></svg><span>下载音乐</span></a><button id="comments-btn" class="btn" type="button" hidden><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg><span>查看评论</span></button><button id="copy-btn" class="btn" type="button"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v1"></path></svg><span>复制链接</span></button><button id="copy-text-btn" class="btn" type="button"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><path d="M14 2v6h6"></path><path d="M8 13h8"></path><path d="M8 17h5"></path></svg><span>复制文案</span></button><button id="fav-btn" class="btn" type="button"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg><span>收藏</span></button><a id="open-btn" class="btn btn-quiet" target="_blank" rel="noopener"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3h7v7"></path><path d="M10 14 21 3"></path><path d="M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5"></path></svg><span>打开视频</span></a></div></div></div></section>
    <section id="batch-results" class="panel batch-panel" hidden aria-label="批量解析结果"><div class="section-heading"><div><h2>批量处理结果</h2><p>完成的项目可以直接播放、下载或复制文案。</p></div><div class="profile-toolbar"><button id="batch-export" class="btn" type="button" hidden>导出 CSV</button><button id="batch-zip" class="btn btn-teal" type="button" hidden>全部打包 ZIP</button><button id="batch-retry" class="btn btn-quiet" type="button" hidden>重试失败 / 已停止项</button><span id="batch-summary" class="batch-summary"></span></div></div><div id="batch-list" class="batch-list"></div><div id="batch-empty" class="batch-empty" hidden>还没有批量任务。</div></section>
  </div>
  <aside class="sidebar"><section class="panel side-panel" aria-label="资料管理"><div class="side-heading"><div><h2>我的资料</h2><p>搜索记录，或将收藏带到另一台设备</p></div></div><label class="field-label" for="library-search">搜索标题、作者或链接</label><input id="library-search" class="library-search" type="search" placeholder="在收藏和历史中查找" autocomplete="off"><div class="account-actions"><button id="library-backup" class="btn" type="button">备份 JSON</button><button id="library-import" class="btn" type="button">导入备份</button><input id="library-file" type="file" accept=".json,application/json" hidden></div><p id="library-status" class="input-hint" role="status" aria-live="polite">导入会合并去重，保留已有记录；备份不含登录凭据。</p></section><section id="favorites" class="panel side-panel" aria-label="我的收藏"><div class="side-heading"><div><h2>我的收藏</h2><p>手动星标的内容，只保存在当前浏览器</p></div><span id="favorites-count" class="count-badge">0</span></div><div class="account-actions favorites-actions"><button id="favorites-export" class="btn" type="button">导出 CSV</button></div><div id="favorites-list" class="history-list"></div><div id="favorites-empty" class="history-empty"><div class="history-empty-icon"><svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg></div><strong>这里还没有收藏</strong><span>在结果、批量或历史记录里点星标收藏</span></div></section><section id="history" class="panel side-panel" aria-label="最近解析"><div class="side-heading"><div><h2>最近处理</h2><p>只保存在当前浏览器</p></div><span id="history-count" class="count-badge">0</span></div><div id="history-list" class="history-list"></div><div id="history-empty" class="history-empty"><div class="history-empty-icon"><svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 5h16v14H4z"></path><path d="M8 9h8"></path><path d="M8 13h5"></path></svg></div><strong>这里还没有记录</strong><span>解析完成后会自动出现在这里</span></div><div class="side-divider"></div><div class="account-actions"><button id="history-export" class="btn" type="button">导出 CSV</button><button id="clear-history-btn" class="clear-btn" type="button"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2-2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg> 清空记录</button></div></section><section class="panel side-panel" aria-label="账号设置"><div class="side-heading"><div><h2>账号</h2><p>当前需要登录态的平台：微博</p></div><span id="weibo-cookie-status" class="account-status"><i></i><span>未配置</span></span></div><div class="account-form"><label class="field-label" for="weibo-cookie-input">微博凭据</label><input id="weibo-cookie-input" type="password" autocomplete="off" spellcheck="false" placeholder="粘贴微博网页版 Cookie"><p class="input-hint">在微博网页版登录后复制 Cookie；解析微博链接时仅随本次 POST 请求发送，不会写入 URL。</p><div class="account-actions"><button id="weibo-cookie-save" class="btn" type="button">保存到本浏览器</button><button id="weibo-cookie-clear" class="btn btn-quiet" type="button">清除</button></div></div></section><section class="panel side-panel" aria-label="工作台信息"><div class="side-heading"><div><h2>工作台</h2><p>让每次整理更顺手</p></div></div><div class="quick-list"><div class="quick-row"><span class="quick-label"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path></svg>单个解析</span><strong class="quick-value">即时</strong></div><div class="quick-row"><span class="quick-label"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 6h13"></path><path d="M8 12h13"></path><path d="M8 18h13"></path><path d="M3 6h.01"></path><path d="M3 12h.01"></path><path d="M3 18h.01"></path></svg>批量处理</span><strong class="quick-value">最多 3 路</strong></div><div class="quick-row"><span class="quick-label"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v18"></path><path d="M3 12h18"></path></svg>记录方式</span><strong class="quick-value">本地保存</strong></div></div></section></aside>
</main>
<footer>去水印解析 · 仅用于个人学习与研究</footer>
<script>
// 在线更新：网站启动时读取服务端代理的 GitHub 最新 Release。
const WEB_APP_VERSION=<?= json_encode($webAppVersion, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
(function(){
  const headerStatus=document.querySelector('#update-status');
  const sidebar=document.querySelector('.sidebar');
  if(!sidebar)return;
  const card=document.createElement('section');
  card.className='panel side-panel update-card';
  card.setAttribute('aria-label','在线更新');
  card.innerHTML='<div class="side-heading"><div><h2>在线更新</h2><p id="web-update-detail">正在检查最新版本…</p></div></div><div class="update-actions"><button id="web-update-check" class="btn btn-teal" type="button">检查更新</button><button id="web-update-refresh" class="btn" type="button" hidden>刷新网站</button><a id="web-update-download" class="btn btn-primary" href="#" target="_blank" rel="noopener" hidden>查看最新发布</a></div>';
  sidebar.insertBefore(card,sidebar.children[1]||null);
  const detail=document.querySelector('#web-update-detail'),check=document.querySelector('#web-update-check'),refresh=document.querySelector('#web-update-refresh'),release=document.querySelector('#web-update-download');
  function status(text,kind){if(detail)detail.textContent=text;if(headerStatus){headerStatus.classList.toggle('is-ready',kind==='ready');headerStatus.classList.toggle('is-error',kind==='error');const label=headerStatus.querySelector('span');if(label)label.textContent=text}};
  async function checkUpdate(){
    check.disabled=true;status('正在检查最新版本…','checking');
    try{
      const response=await fetch('api/v1/update',{cache:'no-store'}),json=await response.json().catch(()=>null);
      if(!response.ok||!json||!json.success||!json.data)throw new Error(json&&json.message||'版本服务暂时不可用');
      const data=json.data;
      if(data.update_available){
        status(`发现新版本 v${data.latest_version}`,'ready');
        detail.textContent=`当前 v${data.current_version} · 最新 v${data.latest_version}`;
        refresh.hidden=false;release.hidden=false;release.href=data.release_url;
      }else{
        status(`已是最新 v${data.latest_version}`,'ready');
        detail.textContent=`网站版本 v${WEB_APP_VERSION} 已是最新`;
        refresh.hidden=true;release.hidden=true;
      }
    }catch(error){status(error.message||'版本检查失败','error');detail.textContent='无法连接版本服务，请稍后重试';}
    finally{check.disabled=false;}
  }
  check.addEventListener('click',checkUpdate);
  refresh.addEventListener('click',()=>{const url=new URL(window.location.href);url.searchParams.set('update',Date.now());window.location.replace(url.href)});
  checkUpdate();
})();
</script>
<script>
// 主应用：单条 / 批量 / 抖音主页解析、图集展示、历史与主题。
// 单一状态机；此前通过捕获阶段拦截事件和覆写 window.fetch 叠加的补丁逻辑已合并至此。
const $=s=>document.querySelector(s);
const installAppBtn=$('#install-app');
let deferredInstallPrompt=null;
window.addEventListener('beforeinstallprompt',event=>{event.preventDefault();deferredInstallPrompt=event;if(installAppBtn)installAppBtn.hidden=false});
window.addEventListener('appinstalled',()=>{deferredInstallPrompt=null;if(installAppBtn)installAppBtn.hidden=true});
installAppBtn?.addEventListener('click',async()=>{if(!deferredInstallPrompt)return;deferredInstallPrompt.prompt();await deferredInstallPrompt.userChoice;deferredInstallPrompt=null;installAppBtn.hidden=true});
const input=$('#video-url'),parseBtn=$('#parse-btn'),pasteBtn=$('#paste-btn'),singleModeBtn=$('#single-mode-btn'),batchModeBtn=$('#batch-mode-btn'),profileModeBtn=$('#profile-mode-btn'),collectionModeBtn=$('#collection-mode-btn'),liveModeBtn=$('#live-mode-btn'),discoverModeBtn=$('#discover-mode-btn'),singlePanel=$('#single-panel'),batchPanel=$('#batch-panel'),batchInput=$('#batch-input'),batchParseBtn=$('#batch-parse-btn'),batchStopBtn=$('#batch-stop-btn'),loading=$('#loading'),loadingText=$('#loading-text'),batchProgress=$('#batch-progress'),batchProgressText=$('#batch-progress-text'),errorBox=$('#error'),errorText=$('#error-text'),result=$('#result'),video=$('#result-video'),avatar=$('#result-avatar'),titleEl=$('#result-title'),metaEl=$('#result-meta'),authorEl=$('#result-author'),likeEl=$('#result-like'),timeEl=$('#result-time'),platformEl=$('#result-platform'),downloadBtn=$('#download-btn'),zipBtn=$('#zip-btn'),musicBtn=$('#music-btn'),copyBtn=$('#copy-btn'),copyTextBtn=$('#copy-text-btn'),openBtn=$('#open-btn'),favBtn=$('#fav-btn'),historyList=$('#history-list'),historyEmpty=$('#history-empty'),historyCount=$('#history-count'),clearHistoryBtn=$('#clear-history-btn'),batchSection=$('#batch-results'),batchList=$('#batch-list'),batchEmpty=$('#batch-empty'),batchSummary=$('#batch-summary'),batchExportBtn=$('#batch-export'),batchZipBtn=$('#batch-zip'),batchRetryBtn=$('#batch-retry'),themeToggle=$('#theme-toggle'),modeLabel=$('#mode-label'),serviceStatus=$('#service-status'),weiboCookieInput=$('#weibo-cookie-input'),weiboCookieSave=$('#weibo-cookie-save'),weiboCookieClear=$('#weibo-cookie-clear'),weiboCookieStatus=$('#weibo-cookie-status'),favoritesList=$('#favorites-list'),favoritesEmpty=$('#favorites-empty'),favoritesCount=$('#favorites-count'),favoritesExportBtn=$('#favorites-export'),inputHint=document.querySelector('#single-panel .input-hint');
const HISTORY_KEY='watermark-parse-history-v1',WEIBO_COOKIE_KEY='watermark-weibo-cookie-v1',HISTORY_LIMIT=20,BATCH_LIMIT=20,FAVORITE_KEY='watermark-parse-favorites-v1',URL_PATTERN=/https?:\/\/[^\s"'<>，。；：！？【】（）《》]+/i;
let ignoreMediaError=false,currentResult=null,batchItems=[],batchRunning=false,batchCancelRequested=false,singleAbortController=null,batchAbortController=null,mediaRefreshKey='';
const state={mode:'single',profile:null,discover:{tab:'hot',platform:'bilibili',page:1,items:[],hasMore:false,total:0,keyword:''},comments:{url:'',page:1,total:0,comments:[],hasMore:false}};
const PLAY_ICON='<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="6 3 20 12 6 21 6 3"></polygon></svg>',DOWNLOAD_ICON='<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12"></path><path d="m7 10 5 5 5-5"></path><path d="M5 21h14"></path></svg>',COPY_ICON='<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v1"></path></svg>',TRASH_ICON='<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>',STAR_ICON='<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>',STAR_FILLED_ICON='<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>';

// 图集展示容器（挂在播放器区域旁）
const resultImages=document.createElement('div');
resultImages.id='result-images';
resultImages.className='result-images';
resultImages.hidden=true;
const videoWrap=document.querySelector('.result-video-wrap');
if(videoWrap&&!document.querySelector('#result-images'))videoWrap.append(resultImages);

function extractUrl(text){const m=String(text).match(URL_PATTERN);return m?m[0].replace(/[),.。]+$/,''):String(text).trim()}
function platformName(url){const h=(String(url).match(/^https?:\/\/([^/]+)/i)||[])[1]||'';if(h.includes('douyin.com'))return'抖音';if(h.includes('kuaishou.com')||h.includes('kuaishou.cn'))return'快手';if(h.includes('bilibili.com')||h.includes('b23.tv'))return'B站';if(h.includes('xiaohongshu.com')||h.includes('xhslink.com'))return'小红书';if(h.includes('pipix.com'))return'皮皮虾';if(h.includes('weishi.qq.com'))return'微视';if(h.includes('weibo.com'))return'微博';if(h.includes('izuiyou.com')||h.includes('xiaochuankeji.cn'))return'最右';if(h.includes('ippzone.com')||h.includes('pipigx.com'))return'皮皮搞笑';if(h.includes('channels.weixin.qq.com'))return'视频号';if(h.includes('ixigua.com'))return'西瓜视频';if(h.includes('tiktok.com'))return'TikTok';if(h.includes('huya.com'))return'虎牙';if(h.includes('douyu.com'))return'斗鱼';return'短视频'}
function formatTime(v){if(!v&&v!==0)return'';if(typeof v==='number'||/^\d{10}$/.test(String(v))){const s=typeof v==='number'?v:parseInt(v,10);if(s<1000000000)return'';const d=new Date(s*1000);if(Number.isNaN(d.getTime()))return'';return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0')}return String(v)}
function formatCount(v){if(v===undefined||v===null||v==='')return'-';const n=Number(v);return Number.isFinite(n)?new Intl.NumberFormat('zh-CN',{notation:'compact',maximumFractionDigits:1}).format(n):String(v)}
function esc(v){return String(v??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;')}
function mediaUrl(url){return'index.php?action=media&url='+encodeURIComponent(url)}
function validMediaUrl(url){try{const parsed=new URL(String(url||''),location.href);return/^https?:$/.test(parsed.protocol)&&Boolean(parsed.hostname)}catch{return false}}
function downloadUrl(url,name,extension){return mediaUrl(url)+'&download=1&filename='+encodeURIComponent((name||'media')+'.'+(extension||'mp4'))}
function buildDownloadUrl(url,title){return downloadUrl(url,title,'mp4')}
function isWeiboUrl(url){return /(^|\.)weibo\.com$/i.test((String(url).match(/^https?:\/\/([^/]+)/i)||[])[1]||'')}
function getWeiboCookie(){return localStorage.getItem(WEIBO_COOKIE_KEY)||''}
function updateWeiboCookieStatus(message){const ready=getWeiboCookie().trim()!=='';if(weiboCookieInput)weiboCookieInput.value=getWeiboCookie();if(weiboCookieStatus){weiboCookieStatus.classList.toggle('is-ready',ready);const label=weiboCookieStatus.querySelector('span');if(label)label.textContent=message||(ready?'已配置':'未配置')}}
function isProfileUrl(value){return/douyin\.com\/user\//i.test(String(value))||/[?&](?:sec_user_id|sec_uid)=/i.test(String(value))}
function isCollectionUrl(value){return/douyin\.com\/collection\//i.test(String(value))||/[?&]mix_id=\d+/i.test(String(value))}
function isLiveUrl(value){const host=(String(value).match(/^https?:\/\/([^/]+)/i)||[])[1]||'';return/(^|\.)live\.douyin\.com$/i.test(host)||/(^|\.)live\.bilibili\.com$/i.test(host)||/(^|\.)huya\.com$/i.test(host)||/(^|\.)douyu\.com$/i.test(host)}
function mediaType(item){return item&&item.media_type==='image'?'image':'video'}
function mediaUrls(item){return Array.isArray(item&&item.images)&&item.images.length?item.images:(item&&item.url?[item.url]:[])}
function safeName(item,index){return((item&&item.title)||'media-'+(index+1)).replace(/[\\/:*?"<>|]/g,'_').slice(0,80)}

function setMode(mode){
  state.mode=mode;
  const single=mode==='single',batch=mode==='batch',profile=mode==='profile',collection=mode==='collection',live=mode==='live',discover=mode==='discover';
  singlePanel.hidden=batch;
  batchPanel.hidden=!batch;
  singleModeBtn.classList.toggle('active',single);
  batchModeBtn.classList.toggle('active',batch);
  if(profileModeBtn){
    profileModeBtn.classList.toggle('active',profile);
    profileModeBtn.setAttribute('aria-pressed',String(profile));
  }
  if(collectionModeBtn){
    collectionModeBtn.classList.toggle('active',collection);
    collectionModeBtn.setAttribute('aria-pressed',String(collection));
  }
  if(liveModeBtn){
    liveModeBtn.classList.toggle('active',live);
    liveModeBtn.setAttribute('aria-pressed',String(live));
  }
  if(discoverModeBtn){
    discoverModeBtn.classList.toggle('active',discover);
    discoverModeBtn.setAttribute('aria-pressed',String(discover));
  }
  singleModeBtn.setAttribute('aria-pressed',String(single));
  batchModeBtn.setAttribute('aria-pressed',String(batch));
  modeLabel.textContent=profile?'抖音个人主页链接':(collection?'抖音合集链接':(live?'直播间链接':(discover?'搜索关键词':(batch?'每行放一个视频链接':'视频分享链接'))));
  input.placeholder=profile?'粘贴抖音个人主页链接':(collection?'粘贴抖音合集链接':(live?'粘贴抖音直播或B站直播间链接':(discover?'输入关键词，配合上方「热榜 / 搜索」标签使用':'粘贴抖音、快手、B站、小红书等链接')));
  if(inputHint)inputHint.textContent=profile||collection
    ?'将按页读取公开作品；支持加载全部、选择下载或全部下载'
    :(live?'解析直播间返回 flv/hls 拉流地址，可用 VLC、PotPlayer 等播放器打开'
    :(discover?'热榜无需输入关键词；搜索请输入关键词后点「搜索」'
    :'小提示：也可以直接粘贴包含文字的整段分享文案，系统会自动提取链接'));
  (batch?batchInput:input).focus();
  if(discover&&!state.discover.items.length)loadDiscover(true);
}

function baseParseLabel(){return state.mode==='profile'?'解析主页':(state.mode==='collection'?'解析合集':(state.mode==='live'?'解析直播间':(state.mode==='discover'?'搜索':'开始解析')))}
function setLoading(on,text){
  loading.hidden=!on;
  parseBtn.disabled=false;
  parseBtn.classList.toggle('is-cancel',on);
  parseBtn.querySelector('span').textContent=on?'取消解析':baseParseLabel();
  parseBtn.setAttribute('aria-label',on?'取消当前解析':baseParseLabel());
  loadingText.textContent=on?(text||'正在读取视频信息…'):'';
  input.setAttribute('aria-busy',String(on));
}
function showError(m){errorText.textContent=m||'解析失败，请稍后再试';errorBox.hidden=false}
function hideError(){errorBox.hidden=true;errorText.textContent=''}
function hideResult(){
  result.hidden=true;
  ignoreMediaError=true;
  currentResult=null;
  video.hidden=false;
  resultImages.hidden=true;
  video.removeAttribute('src');
  video.removeAttribute('poster');
  video.load();
  if(typeof commentsBtn!=='undefined'&&commentsBtn)commentsBtn.hidden=true;
  if(typeof commentsSection!=='undefined'&&commentsSection)commentsSection.hidden=true;
}

async function requestParse(url,signal,force){const payload={url};if(force)payload.force=true;if(isWeiboUrl(url)){const weiboCookie=getWeiboCookie().trim();if(weiboCookie)payload.weiboCookie=weiboCookie}const options={method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)};if(signal)options.signal=signal;const r=await fetch('api/v1/parse',options),j=await r.json().catch(()=>null);if(!r.ok||!j||!j.success||!j.data||!j.data.url)throw new Error(j&&j.message||'解析失败，请稍后再试');return{url,data:j.data}}
async function parseUrlIntoResult(url){
  hideError();hideResult();
  singleAbortController=new AbortController();
  setLoading(true);
  try{
    const item=await requestParse(url,singleAbortController.signal);
    renderResult(item.data,item.url);
    addHistory(item);renderHistory();
    result.scrollIntoView({behavior:'smooth',block:'start'});
  }catch(e){
    showError(e.name==='AbortError'?'已取消解析':(e.message||'解析失败，请稍后再试'));
  }finally{
    singleAbortController=null;setLoading(false);
  }
}
async function parseVideo(){
  if(singleAbortController){singleAbortController.abort();return}
  // 发现模式：输入框承载搜索关键词；空输入=刷新当前标签（热榜）
  if(state.mode==='discover'){
    const keyword=input.value.trim();
    if(!keyword&&state.discover.tab==='search'){showError('请输入搜索关键词');input.focus();return}
    state.discover.keyword=keyword;
    await loadDiscover(true);
    return;
  }
  const raw=input.value.trim();
  if(!raw){showError('请先粘贴一个视频分享链接');input.focus();return}
  const url=extractUrl(raw);
  if(!/^https?:\/\//i.test(url)){
    showError(state.mode==='profile'?'请输入有效的抖音个人主页链接':(state.mode==='collection'?'请输入有效的抖音合集链接':(state.mode==='live'?'请输入有效的直播间链接':'请输入有效的视频链接')));
    return;
  }
  // 合集优先：合集模式下或输入本身是合集链接时走合集分页解析
  if(state.mode==='collection'||isCollectionUrl(url)){input.value=url;await parseCollection(url);return}
  // 直播间链接走直播解析入口
  if(state.mode==='live'||isLiveUrl(url)){input.value=url;await parseLive(url);return}
  // 主页模式下或输入本身就是抖音主页链接时走主页分页解析
  if(state.mode==='profile'||isProfileUrl(url)){input.value=url;await parseProfile(url);return}
  await parseUrlIntoResult(url);
}

function renderResult(data,raw){
  ignoreMediaError=false;mediaRefreshKey='';
  currentResult={url:raw,data};
  titleEl.textContent=data.title||'未命名视频';
  authorEl.textContent=data.author||'未知作者';
  metaEl.textContent=[platformName(raw),formatTime(data.time)].filter(Boolean).join(' · ');
  likeEl.textContent=formatCount(data.like);
  timeEl.textContent=formatTime(data.time)||'-';
  if(data.avatar){avatar.src=mediaUrl(data.avatar);avatar.hidden=false}else{avatar.removeAttribute('src');avatar.hidden=true}
  openBtn.href=mediaUrl(data.url);
  // 抖音等平台带背景音乐时给出下载入口；无音频地址则隐藏
  const music=/^https?:\/\//i.test(data.music&&data.music.url||'')?data.music:null;
  if(music){musicBtn.hidden=false;musicBtn.href=downloadUrl(music.url,safeName(data,0)+'-music','mp3');musicBtn.setAttribute('download',safeName(data,0)+'-music.mp3')}
  else{musicBtn.hidden=true;musicBtn.removeAttribute('href')}
  // B站视频提供公开评论查看入口
  if(commentsBtn)commentsBtn.hidden=platformName(raw)!=='B站';

  if(mediaType(data)==='image'){
    // 图集：隐藏播放器，逐张展示并支持单张下载
    platformEl.textContent='图集解析完成';
    video.hidden=true;resultImages.hidden=true;
    video.removeAttribute('src');video.removeAttribute('poster');video.load();
    const urls=mediaUrls(data),name=safeName(data,0);
    resultImages.replaceChildren();
    urls.forEach((url,index)=>{
      const card=document.createElement('div');card.className='result-image-card';
      const img=document.createElement('img');
      img.src=mediaUrl(url);img.alt=(data.title||'图片')+' '+(index+1);img.loading='lazy';
      const link=document.createElement('a');
      link.className='result-image-download';
      link.href=downloadUrl(url,name+'-'+(index+1),'jpg');
      link.download=name+'-'+(index+1)+'.jpg';
      link.target='_blank';link.rel='noopener';link.title='下载图片';
      link.innerHTML=DOWNLOAD_ICON;
      card.append(img,link);
      resultImages.append(card);
    });
    resultImages.hidden=false;
    downloadBtn.href=downloadUrl(urls[0]||data.url,data.title||'image','jpg');
    downloadBtn.setAttribute('download',(data.title||'image')+'.jpg');
    downloadBtn.querySelector('span').textContent='下载首图';
    if(zipBtn){
      zipBtn.hidden=urls.length<=1;
      zipBtn.disabled=false;
      zipBtn.querySelector('span').textContent='打包下载图集 ('+urls.length+'张 ZIP)';
    }
  }else{
    platformEl.textContent=platformName(raw)+' · 解析完成';
    video.hidden=false;resultImages.hidden=true;
    if(zipBtn)zipBtn.hidden=true;
    downloadBtn.querySelector('span').textContent='下载视频';
    video.src=mediaUrl(data.url);
    const cover=data.cover||'';
    if(cover)video.poster=mediaUrl(cover);else video.removeAttribute('poster');
    downloadBtn.href=buildDownloadUrl(data.url,data.title);
    downloadBtn.setAttribute('download',(data.title||'video')+'.mp4');
  }
  result.hidden=false;
  updateFavBtn(raw);
}

function updateFavBtn(raw){if(!favBtn||!raw)return;const fav=isFavorite(raw);favBtn.classList.toggle('is-fav',fav);const span=favBtn.querySelector('span');if(span)span.textContent=fav?'已收藏':'收藏';favBtn.querySelector('svg').outerHTML=fav?STAR_FILLED_ICON.replace('width="14"','width="16"').replace('height="14"','height="16"'):'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>'}
const library=Workbench.createLibrary({getItem:key=>localStorage.getItem(key),setItem:(key,value)=>localStorage.setItem(key,value)});
function libraryStatus(message){$('#library-status').textContent=message}
function readLibrary(kind){try{return library.read()[kind]}catch(error){libraryStatus('无法读取本地资料：'+error.message+'。原数据保持不变。');return[]}}
function saveLibrary(kind,items){try{library.save(kind,items);return true}catch(error){libraryStatus('保存失败：'+error.message+'。请检查浏览器存储空间或导出备份。');return false}}
function loadHistory(){return readLibrary('history')}function saveHistory(items){return saveLibrary('history',items)}function addHistory(item){const list=loadHistory().filter(x=>x.rawUrl!==item.url);list.unshift({rawUrl:item.url,title:item.data.title||'',author:item.data.author||'',like:item.data.like??0,time:item.data.time??0,cover:item.data.cover||'',videoUrl:item.data.url||'',savedAt:Date.now()});saveHistory(list.slice(0,Math.max(HISTORY_LIMIT,loadHistory().length)))}function removeHistory(i){const list=loadHistory();list.splice(i,1);saveHistory(list);renderHistory()}
function loadFavorites(){return readLibrary('favorites')}function saveFavorites(items){return saveLibrary('favorites',items)}function isFavorite(rawUrl){return loadFavorites().some(f=>f.rawUrl===rawUrl)}function toggleFavorite(item){const rawUrl=item.url||item.rawUrl||'';if(!rawUrl)return;const list=loadFavorites(),idx=list.findIndex(f=>f.rawUrl===rawUrl);if(idx>=0){list.splice(idx,1)}else{list.unshift({rawUrl:rawUrl,title:item.title||item.data?.title||'',author:item.author||item.data?.author||'',like:item.like??item.data?.like??0,time:item.time??item.data?.time??0,cover:item.cover||item.data?.cover||'',videoUrl:item.data?.url||item.videoUrl||'',savedAt:Date.now()})}if(!saveFavorites(list))return false;renderFavorites();return idx<0}function renderFavorites(){const list=loadFavorites(),query=$('#library-search').value,visible=list.map((item,i)=>({item,i})).filter(({item})=>Workbench.matches(item,query));favoritesCount.textContent=String(visible.length)+' / '+list.length;favoritesEmpty.hidden=visible.length>0;favoritesEmpty.querySelector('strong').textContent=list.length?'没有匹配的收藏':'这里还没有收藏';favoritesList.innerHTML=visible.map(({item,i})=>'<article class="history-entry" data-index="'+i+'">'+historyThumb(item.cover)+'<div class="history-copy"><h3 class="history-title">'+esc(item.title||'未命名视频')+'</h3><p class="history-meta">'+esc([platformName(item.rawUrl),formatTime(item.time)].filter(Boolean).join(' · '))+'</p></div><div class="history-actions"><button type="button" data-favaction="reparse" title="重新解析" aria-label="重新解析">'+PLAY_ICON+'</button><button type="button" data-favaction="copy" title="复制链接" aria-label="复制链接">'+COPY_ICON+'</button><button type="button" data-favaction="unfav" title="取消收藏" aria-label="取消收藏">'+STAR_FILLED_ICON+'</button></div></article>').join('')}
function historyThumb(cover){return cover?'<img class="history-thumb" src="'+esc(mediaUrl(cover))+'" alt="" loading="lazy">':'<span class="history-thumb" aria-hidden="true"></span>'}
function historyItemHtml(item,i){const t=item.cover?mediaUrl(item.cover):'';const starFav=isFavorite(item.rawUrl)?' is-fav':'';return'<article class="history-entry" data-index="'+i+'">'+historyThumb(item.cover)+'<div class="history-copy"><h3 class="history-title">'+esc(item.title||'未命名视频')+'</h3><p class="history-meta">'+esc([platformName(item.rawUrl),formatTime(item.time)].filter(Boolean).join(' · '))+'</p></div><div class="history-actions"><button type="button" data-action="reparse" title="重新解析" aria-label="重新解析">'+PLAY_ICON+'</button><button type="button" data-action="copy" title="复制链接" aria-label="复制链接">'+COPY_ICON+'</button><button type="button" data-action="delete" title="删除记录" aria-label="删除记录">'+TRASH_ICON+'</button><button type="button" data-action="favorite" title="收藏" aria-label="收藏" class="'+starFav.trim()+'">'+(isFavorite(item.rawUrl)?STAR_FILLED_ICON:STAR_ICON)+'</button></div></article>'}
function renderHistory(){const list=loadHistory(),visible=list.map((item,i)=>({item,i})).filter(({item})=>Workbench.matches(item,$('#library-search').value));historyCount.textContent=String(visible.length)+' / '+list.length;historyEmpty.hidden=visible.length>0;historyEmpty.querySelector('strong').textContent=list.length?'没有匹配的记录':'这里还没有记录';historyList.innerHTML=visible.map(({item,i})=>historyItemHtml(item,i)).join('')}
function setBatchRunning(on){batchRunning=on;batchParseBtn.disabled=on;batchInput.disabled=on;batchRetryBtn.disabled=on;batchStopBtn.hidden=!on;batchProgress.hidden=!on;batchParseBtn.querySelector('span').textContent=on?'处理中…':'开始批量处理'}function updateBatchSummary(){const ok=batchItems.filter(x=>x&&x.data).length,fail=batchItems.filter(x=>x&&x.error).length;batchSummary.textContent=[ok?'成功 '+ok:'',fail?'失败 '+fail:''].filter(Boolean).join(' · ');if(batchExportBtn)batchExportBtn.hidden=ok===0;if(batchZipBtn)batchZipBtn.hidden=ok===0;if(batchRetryBtn)batchRetryBtn.hidden=fail===0}
function batchItemHtml(item,i){if(item.loading)return'<article class="batch-item is-loading" data-index="'+i+'"><div class="batch-thumb"></div><div class="batch-content"><h3 class="batch-title">处理中</h3><p class="batch-meta">正在读取视频信息…</p></div><div class="batch-actions"><span class="batch-meta">请稍候</span></div></article>';if(item.error)return'<article class="batch-item is-error" data-index="'+i+'"><div class="batch-content"><h3 class="batch-title">'+esc(item.url)+'</h3><p class="batch-meta">'+esc(item.error)+'</p></div><div class="batch-actions"><button class="btn" type="button" data-action="retry">重试</button></div></article>';const d=item.data,t=d.cover?mediaUrl(d.cover):'';const favIcon=isFavorite(item.url)?STAR_FILLED_ICON:STAR_ICON;return'<article class="batch-item" data-index="'+i+'"><img class="batch-thumb" src="'+esc(t)+'" alt="" loading="lazy"><div class="batch-content"><h3 class="batch-title">'+esc(d.title||'未命名视频')+'</h3><p class="batch-meta">'+esc(platformName(item.url))+' · '+esc(d.author||'未知作者')+' · 点赞 '+esc(formatCount(d.like))+'</p></div><div class="batch-actions"><button class="btn" type="button" data-action="play">'+PLAY_ICON+'播放</button><a class="btn" href="'+esc(buildDownloadUrl(d.url,d.title))+'" download="'+esc((d.title||'video')+'.mp4')+'" target="_blank" rel="noopener">'+DOWNLOAD_ICON+'下载</a><button class="btn" type="button" data-action="copy">'+COPY_ICON+'复制</button><button class="btn" type="button" data-action="favorite" title="收藏" aria-label="收藏">'+favIcon+'收藏</button></div></article>'}
function renderBatchList(){batchEmpty.hidden=batchItems.length>0;batchList.innerHTML=batchItems.map(batchItemHtml).join('');updateBatchSummary()}
async function runBatchIndices(indices,force=false){
  if(batchRunning||!indices.length)return;
  hideError();batchCancelRequested=false;batchAbortController=new AbortController();
  const items=batchItems,controller=batchAbortController;
  indices.forEach(i=>{items[i]={url:items[i].url,loading:true}});
  setBatchRunning(true);renderBatchList();
  try{
    await Workbench.runQueue(items,indices,(url,signal)=>requestParse(url,signal,force),{signal:controller.signal,onChange:i=>{
      if(items[i].data){addHistory(items[i]);renderHistory()}
      batchProgressText.textContent='已处理 '+indices.filter(n=>!items[n].loading).length+'/'+indices.length;
      renderBatchList();
    }});
  }finally{batchAbortController=null;setBatchRunning(false);renderBatchList();renderHistory()}
}
async function parseBatch(){
  if(batchRunning)return;
  const urls=batchInput.value.split(/\n/).map(extractUrl).map(x=>x.trim()).filter(x=>/^https?:\/\//i.test(x)).filter((x,i,a)=>a.indexOf(x)===i);
  if(!urls.length){showError('请先粘贴至少一个有效的视频链接');return}
  if(urls.length>BATCH_LIMIT){showError('批量任务最多支持 '+BATCH_LIMIT+' 条链接，请分批处理');return}
  batchItems=urls.map(url=>({url,loading:true}));batchSection.hidden=false;
  await runBatchIndices(batchItems.map((_,i)=>i));
  batchSection.scrollIntoView({behavior:'smooth',block:'start'});
}
async function retryBatchItem(i){if(batchItems[i]?.error)await runBatchIndices([i],true)}
async function copyText(text){try{await navigator.clipboard.writeText(text)}catch(e){const t=document.createElement('textarea');t.value=text;t.style.position='fixed';t.style.opacity='0';document.body.appendChild(t);t.select();document.execCommand('copy');t.remove()}}
function flashButton(b,text){const s=b.querySelector('span'),target=s||b;if(!target)return;const old=target.textContent;target.textContent=text;setTimeout(()=>target.textContent=old,1200)}
function buildShareText(x){return[x.data.title||'未命名视频',platformName(x.url),'无水印链接：'+buildDownloadUrl(x.data.url,x.data.title)].join('\n')}

// ---- 抖音主页分页解析 ----
function itemId(item,index,page){return String((item&&item.aweme_id)||(item&&item.source_url)||(((state.profile&&state.profile.url)||'profile')+'-'+page+'-'+index))}
const profileSection=document.createElement('section');
profileSection.id='profile-results';profileSection.className='panel profile-panel';profileSection.hidden=true;profileSection.setAttribute('aria-label','作品列表');
profileSection.innerHTML='<div class="section-heading"><div><h2 id="profile-heading">作品列表</h2><p id="profile-summary">正在整理作品</p></div><div class="profile-toolbar"><label class="select-all-control"><input id="profile-select-all" type="checkbox"><span>全选本页</span></label><button id="profile-download-selected" class="btn btn-primary" type="button">下载选中</button><button id="profile-load-all" class="btn" type="button">加载全部</button><button id="profile-download-all" class="btn btn-teal" type="button">全部下载</button><button id="profile-export" class="btn" type="button">导出 CSV</button></div></div><div id="profile-list" class="profile-list"></div><div id="profile-empty" class="batch-empty" hidden>暂时没有可下载作品。</div><div class="profile-pagination"><button id="profile-prev" class="btn" type="button">上一页</button><span id="profile-page-label">第 1 页</span><button id="profile-next" class="btn btn-teal" type="button">下一页</button></div>';
if(batchSection)batchSection.after(profileSection);else result.after(profileSection);
const profileList=profileSection.querySelector('#profile-list'),profileSummary=profileSection.querySelector('#profile-summary'),profileEmpty=profileSection.querySelector('#profile-empty'),profileSelectAll=profileSection.querySelector('#profile-select-all'),profileSelectedButton=profileSection.querySelector('#profile-download-selected'),profileAllButton=profileSection.querySelector('#profile-download-all'),profileLoadAll=profileSection.querySelector('#profile-load-all'),profileHeading=profileSection.querySelector('#profile-heading'),profilePrev=profileSection.querySelector('#profile-prev'),profileNext=profileSection.querySelector('#profile-next'),profilePageLabel=profileSection.querySelector('#profile-page-label');

function renderProfilePage(pageIndex){
  if(!state.profile||!state.profile.pages[pageIndex])return;
  state.profile.current=pageIndex;
  const page=state.profile.pages[pageIndex],items=Array.isArray(page.items)?page.items:[];
  const isCollection=state.profile.kind==='collection';
  profileSection.hidden=false;
  profileEmpty.hidden=items.length>0;
  profileHeading.textContent=isCollection?'合集作品':'个人主页作品';
  const head=isCollection&&page.collection
    ?((page.collection.title||'')+(page.collection.author?' · '+page.collection.author:''))
    :((page.profile&&page.profile.author)?page.profile.author:'');
  const totalLabel=isCollection&&page.collection&&page.collection.total?(' · 共 '+page.collection.total+' 集'):'';
  profileSummary.textContent=(head?head+' · ':'')+'第 '+(pageIndex+1)+' 页 · 本页 '+items.length+' 个公开作品'+totalLabel;
  profilePageLabel.textContent='第 '+(pageIndex+1)+' 页';
  profileList.innerHTML=items.map((item,index)=>{
    const urls=mediaUrls(item),thumb=item.cover||urls[0]||'',type=mediaType(item);
    return'<article class="profile-item" data-index="'+index+'"><input class="profile-check" type="checkbox" data-action="select" aria-label="选择 '+esc(item.title||'第 '+(index+1)+' 个作品')+'"><img class="profile-thumb" src="'+esc(thumb?mediaUrl(thumb):'')+'" alt="" loading="lazy"><div class="profile-content"><h3 class="profile-title">'+esc(item.title||'未命名内容')+'</h3><p class="profile-meta">'+esc(type==='image'?'图集 '+urls.length+' 张':'视频')+' · '+esc(item.author||'')+'</p></div><div class="profile-actions"><button class="btn" type="button" data-action="download">'+DOWNLOAD_ICON+'下载</button></div></article>';
  }).join('');
  items.forEach((item,index)=>{
    if(state.profile.selected.has(itemId(item,index,pageIndex))){
      const check=profileList.querySelector('.profile-item[data-index="'+index+'"] .profile-check');
      if(check){check.checked=true;check.closest('.profile-item').classList.add('is-selected')}
    }
  });
  profileSelectAll.checked=items.length>0&&items.every((item,index)=>state.profile.selected.has(itemId(item,index,pageIndex)));
  profilePrev.disabled=pageIndex===0;
  profileNext.disabled=!page.has_more;
}
async function requestProfile(url,cursor){const response=await fetch('api/v1/profile',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({url,cursor})}),json=await response.json().catch(()=>null);if(!response.ok||!json||!json.success||!json.data||!Array.isArray(json.data.items))throw new Error(json&&json.message||'主页解析失败，请稍后再试');return json.data}
async function requestCollection(url,cursor){const response=await fetch('api/v1/collection',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({url,cursor})}),json=await response.json().catch(()=>null);if(!response.ok||!json||!json.success||!json.data||!Array.isArray(json.data.items))throw new Error(json&&json.message||'合集解析失败，请稍后再试');return json.data}
async function parseProfile(url){
  hideError();setMode('profile');
  profileSection.hidden=false;profileList.innerHTML='';
  profileSummary.textContent='正在读取第 1 页作品…';
  state.profile={url,pages:[],current:0,selected:new Set(),kind:'profile'};
  setLoading(true,'正在读取第 1 页作品…');
  try{
    state.profile.pages[0]=await requestProfile(url,0);
    renderProfilePage(0);
    profileSection.scrollIntoView({behavior:'smooth',block:'start'});
  }catch(error){
    profileSummary.textContent='';
    showError(error.message||'主页解析失败，请稍后再试');
  }finally{
    setLoading(false);
  }
}
async function parseCollection(url){
  hideError();setMode('collection');
  profileSection.hidden=false;profileList.innerHTML='';
  profileSummary.textContent='正在读取第 1 页作品…';
  state.profile={url,pages:[],current:0,selected:new Set(),kind:'collection'};
  setLoading(true,'正在读取第 1 页作品…');
  try{
    state.profile.pages[0]=await requestCollection(url,0);
    renderProfilePage(0);
    profileSection.scrollIntoView({behavior:'smooth',block:'start'});
  }catch(error){
    profileSummary.textContent='';
    showError(error.message||'合集解析失败，请稍后再试');
  }finally{
    setLoading(false);
  }
}
async function loadAllPages(){
  if(!state.profile)return;
  profileLoadAll.disabled=true;profileNext.disabled=true;profilePrev.disabled=true;
  try{
    while(state.profile.pages[state.profile.pages.length-1]&&state.profile.pages[state.profile.pages.length-1].has_more){
      const last=state.profile.pages[state.profile.pages.length-1];
      const pageIndex=state.profile.pages.length;
      profileSummary.textContent='正在加载第 '+(pageIndex+1)+' 页作品…';
      state.profile.pages[pageIndex]=state.profile.kind==='collection'
        ?await requestCollection(state.profile.url,last.next_cursor)
        :await requestProfile(state.profile.url,last.next_cursor);
      // 限速保护：页间稍作停顿，避免触发平台风控
      await new Promise(resolve=>setTimeout(resolve,450));
    }
    const loaded=state.profile.pages.flatMap(p=>p.items||[]).length;
    profileSummary.textContent='已加载全部 '+loaded+' 个作品，可以开始下载';
  }catch(error){
    const loaded=state.profile.pages.flatMap(p=>p.items||[]).length;
    showError((error.message||'加载失败')+'（已加载 '+loaded+' 个作品）');
  }finally{
    profileLoadAll.disabled=false;
    if(state.profile&&state.profile.pages[state.profile.current]){
      profileNext.disabled=!state.profile.pages[state.profile.current].has_more;
      profilePrev.disabled=state.profile.current===0;
    }
  }
}

// ---- 直播间解析 ----
const liveSection=document.createElement('section');
liveSection.id='live-results';liveSection.className='panel profile-panel';liveSection.hidden=true;liveSection.setAttribute('aria-label','直播间解析');
liveSection.innerHTML='<div class="section-heading"><div><h2>直播间信息</h2><p id="live-summary">正在解析直播间</p></div><span id="live-status-badge" class="live-status-badge">检查中</span></div><div id="live-stream-list" class="profile-list"></div><div id="live-empty" class="batch-empty" hidden>当前未开播，开播后再来获取拉流地址。</div>';
if(typeof profileSection!=='undefined'&&profileSection)profileSection.after(liveSection);else if(batchSection)batchSection.after(liveSection);
const liveList=liveSection.querySelector('#live-stream-list'),liveSummary=liveSection.querySelector('#live-summary'),liveEmpty=liveSection.querySelector('#live-empty'),liveStatusBadge=liveSection.querySelector('#live-status-badge');

async function requestLive(url){const response=await fetch('api/v1/live',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({url})}),json=await response.json().catch(()=>null);if(!response.ok||!json||!json.success||!json.data)throw new Error(json&&json.message||'直播解析失败，请稍后再试');return json.data}
function renderLive(data){
  const live=data.live||{};
  const isLive=live.status==='live';
  liveSection.hidden=false;
  liveStatusBadge.textContent=isLive?'直播中':'未开播';
  liveStatusBadge.classList.toggle('is-live',isLive);
  liveStatusBadge.classList.toggle('is-offline',!isLive);
  liveSummary.textContent=[live.title||'未命名直播间',live.anchor,live.viewers?'人气 '+formatCount(live.viewers):''].filter(Boolean).join(' · ');
  const streams=Array.isArray(data.streams)?data.streams:[];
  liveEmpty.hidden=streams.length>0;
  // 「打开」走媒体代理（服务端注入 Referer，douyin/bilibili 实测可用）；
  // 虎牙拉流 302 跳边缘裸 IP，代理按 SSRF 策略不放行，故虎牙直接给原始地址；
  // 复制出去的原始地址在 PotPlayer/VLC 中播放时需自行设置 Referer
  const proxiedOpen=data.platform==='douyin'||data.platform==='bilibili'||data.platform==='douyu';
  liveList.innerHTML=streams.map(stream=>{
    const url=String(stream.url||'');
    const openHref=proxiedOpen?mediaUrl(url):url;
    return'<div class="live-stream"><span class="live-stream-format">'+esc(String(stream.format||'').toUpperCase())+'</span><div class="profile-content"><h3 class="profile-title">'+esc(stream.quality||'默认画质')+'</h3><p class="live-stream-url">'+esc(url)+'</p></div><div class="profile-actions"><button class="btn" type="button" data-live-copy="'+esc(url)+'">'+COPY_ICON+'复制地址</button><a class="btn" href="'+esc(openHref)+'" target="_blank" rel="noopener">'+PLAY_ICON+'打开</a></div></div>';
  }).join('')+'<p class="batch-empty" style="border-top:1px solid var(--ag-border)">复制到播放器播放时需设置 Referer：B站为 https://live.bilibili.com/ ，抖音为 https://live.douyin.com/ ，虎牙为 https://huya.com/ ，斗鱼为 https://www.douyu.com/ 。</p>';
}
async function parseLive(url){
  hideError();setMode('live');
  liveSection.hidden=false;liveList.innerHTML='';
  liveSummary.textContent='正在解析直播间…';
  setLoading(true,'正在解析直播间…');
  try{
    const data=await requestLive(url);
    renderLive(data);
    liveSection.scrollIntoView({behavior:'smooth',block:'start'});
  }catch(error){
    liveSummary.textContent='';
    showError(error.message||'直播解析失败，请稍后再试');
  }finally{
    setLoading(false);
  }
}
liveList.addEventListener('click',async e=>{
  const button=e.target.closest('[data-live-copy]');
  if(!button)return;
  await copyText(button.getAttribute('data-live-copy'));
  button.innerHTML='✓ 已复制';
  setTimeout(()=>{button.innerHTML=COPY_ICON+'复制地址'},1200);
});

// ---- 发现：热榜与搜索（合规边界：仅用户主动查询，按需分页，不落库） ----
const discoverSection=document.createElement('section');
discoverSection.id='discover-results';discoverSection.className='panel profile-panel';discoverSection.hidden=true;discoverSection.setAttribute('aria-label','发现内容');
discoverSection.innerHTML='<div class="section-heading"><div><h2>发现内容</h2><p id="discover-summary">查看平台公开热榜，或按关键词搜索</p></div><div class="profile-toolbar"><button id="discover-tab-hot" class="mode-btn active" type="button">热榜</button><button id="discover-tab-search" class="mode-btn" type="button">搜索</button><button id="discover-platform-bilibili" class="mode-btn active" type="button">B站</button><button id="discover-platform-douyin" class="mode-btn" type="button">抖音</button><button id="discover-refresh" class="btn btn-teal" type="button">刷新</button></div></div><div id="discover-list" class="batch-list"></div><div id="discover-empty" class="batch-empty" hidden>暂无内容。</div><div class="profile-pagination"><button id="discover-more" class="btn btn-teal" type="button" hidden>加载更多</button></div>';
if(typeof liveSection!=='undefined'&&liveSection)liveSection.after(discoverSection);else if(batchSection)batchSection.after(discoverSection);
const discoverList=discoverSection.querySelector('#discover-list'),discoverSummary=discoverSection.querySelector('#discover-summary'),discoverEmpty=discoverSection.querySelector('#discover-empty'),discoverMore=discoverSection.querySelector('#discover-more'),discoverRefresh=discoverSection.querySelector('#discover-refresh');
discoverList.addEventListener('error',event=>{const image=event.target.closest('img.batch-thumb');if(!image)return;const fallback=image.dataset.fallback;if(fallback&&!image.dataset.fallbackTried&&validMediaUrl(fallback)){image.dataset.fallbackTried='true';image.src=fallback;return}image.replaceWith(Object.assign(document.createElement('span'),{className:'batch-thumb-placeholder','aria-hidden':'true'}))},true);
const discoverTabHot=discoverSection.querySelector('#discover-tab-hot'),discoverTabSearch=discoverSection.querySelector('#discover-tab-search'),discoverPlatformBili=discoverSection.querySelector('#discover-platform-bilibili'),discoverPlatformDouyin=discoverSection.querySelector('#discover-platform-douyin');

async function fetchDiscoverJson(url){const r=await fetch(url),j=await r.json().catch(()=>null);if(!r.ok||!j||!j.success||!j.data)throw new Error(j&&j.message||'请求失败，请稍后再试');return j.data}
function isParseableVideoUrl(url){return/^https?:\/\/(www\.)?(bilibili\.com|b23\.tv)\//i.test(String(url))||/^https?:\/\/(www\.)?(douyin\.com)\/(video|note|share\/video)\//i.test(String(url))}
function renderDiscover(){
  const d=state.discover;
  discoverSummary.textContent=d.tab==='hot'
    ?(d.platform==='bilibili'?'B站综合热门 · 用户主动查看，按需加载':'抖音热点榜 · 条目为热点词，可打开原页')
    :'搜索「'+d.keyword+'」 · 共 '+d.total+' 条结果';
  discoverEmpty.hidden=d.items.length>0;
  discoverList.innerHTML=d.items.map(item=>{
    const parseable=isParseableVideoUrl(item.url);
    const cover=validMediaUrl(item.cover)?mediaUrl(item.cover):'';
    return'<article class="batch-item"><div class="batch-thumb-wrap">'+(cover?'<img class="batch-thumb" src="'+esc(cover)+'" data-fallback="'+esc(item.cover)+'" alt="" loading="lazy">':'<span class="batch-thumb-placeholder" aria-hidden="true"></span>')+'</div><div class="batch-content"><h3 class="batch-title">'+esc(item.title||'未命名内容')+'</h3><p class="batch-meta">'+esc(item.author||'')+(item.hot?' · 热度 '+esc(formatCount(item.hot)):'')+'</p></div><div class="batch-actions">'+(parseable?'<button class="btn" type="button" data-discover-parse="'+esc(item.url)+'">'+PLAY_ICON+'解析</button>':'')+'<a class="btn" href="'+esc(item.url)+'" target="_blank" rel="noopener">打开</a></div></article>';
  }).join('');
  discoverMore.hidden=!d.hasMore;
}
async function loadDiscover(reset){
  const d=state.discover;
  if(d.tab==='search'&&!d.keyword){showError('请先在上方输入搜索关键词');return}
  if(reset){d.page=1;d.items=[];discoverSection.hidden=false;discoverList.innerHTML=''}
  setLoading(true,d.tab==='hot'?'正在获取热榜…':'正在搜索…');
  try{
    const data=d.tab==='hot'
      ?await fetchDiscoverJson('api/v1/hot?platform='+d.platform+'&page='+d.page)
      :await fetchDiscoverJson('api/v1/search?platform='+d.platform+'&keyword='+encodeURIComponent(d.keyword)+'&page='+d.page);
    d.items=reset?(data.items||[]):d.items.concat(data.items||[]);
    d.hasMore=Boolean(data.has_more);
    d.total=data.total||d.items.length;
    renderDiscover();
    discoverSection.scrollIntoView({behavior:'smooth',block:'start'});
  }catch(error){
    showError(error.message||'获取失败，请稍后再试');
  }finally{
    setLoading(false);
  }
}
function setDiscoverTab(tab){
  state.discover.tab=tab;
  discoverTabHot.classList.toggle('active',tab==='hot');
  discoverTabSearch.classList.toggle('active',tab==='search');
}
function setDiscoverPlatform(platform){
  state.discover.platform=platform;
  discoverPlatformBili.classList.toggle('active',platform==='bilibili');
  discoverPlatformDouyin.classList.toggle('active',platform==='douyin');
}
discoverTabHot.addEventListener('click',()=>{setDiscoverTab('hot');loadDiscover(true)});
discoverTabSearch.addEventListener('click',()=>{setDiscoverTab('search');if(state.discover.keyword)loadDiscover(true);else{showError('请先在上方输入搜索关键词');input.focus()}});
discoverPlatformBili.addEventListener('click',()=>{setDiscoverPlatform('bilibili');loadDiscover(true)});
discoverPlatformDouyin.addEventListener('click',()=>{setDiscoverPlatform('douyin');loadDiscover(true)});
discoverRefresh.addEventListener('click',()=>loadDiscover(true));
discoverMore.addEventListener('click',()=>{state.discover.page+=1;loadDiscover(false)});
discoverList.addEventListener('click',async e=>{
  const button=e.target.closest('[data-discover-parse]');
  if(!button)return;
  await parseUrlIntoResult(button.getAttribute('data-discover-parse'));
});

// ---- B站公开评论（用户主动查看，按需分页） ----
const commentsSection=document.createElement('section');
commentsSection.id='comments-results';commentsSection.className='panel profile-panel';commentsSection.hidden=true;commentsSection.setAttribute('aria-label','视频评论');
commentsSection.innerHTML='<div class="section-heading"><div><h2>公开评论</h2><p id="comments-summary">仅查看公开评论，不会保存</p></div></div><div id="comments-list" class="batch-list"></div><div id="comments-empty" class="batch-empty" hidden>暂无公开评论。</div><div class="profile-pagination"><button id="comments-more" class="btn btn-teal" type="button" hidden>加载更多</button></div>';
discoverSection.after(commentsSection);
const commentsList=commentsSection.querySelector('#comments-list'),commentsSummary=commentsSection.querySelector('#comments-summary'),commentsEmpty=commentsSection.querySelector('#comments-empty'),commentsMore=commentsSection.querySelector('#comments-more'),commentsBtn=$('#comments-btn');

function renderComments(){
  const c=state.comments;
  commentsSummary.textContent='共 '+formatCount(c.total)+' 条公开评论 · 仅查看，不会保存';
  commentsEmpty.hidden=c.comments.length>0;
  commentsList.innerHTML=c.comments.map(item=>'<article class="batch-item"><img class="batch-thumb" style="border-radius:50%;object-fit:cover" src="'+esc(item.avatar?mediaUrl(item.avatar):'')+'" alt="" loading="lazy"><div class="batch-content"><h3 class="batch-title">'+esc(item.user||'匿名用户')+' · 赞 '+esc(formatCount(item.likes))+'</h3><p class="batch-meta">'+esc(item.content||'')+'</p></div><div class="batch-actions"><span class="batch-meta">'+esc(formatTime(item.time))+'</span></div></article>').join('');
  commentsMore.hidden=!c.hasMore;
}
async function loadComments(reset){
  const c=state.comments;
  if(!c.url)return;
  if(reset){c.page=1;c.comments=[];commentsSection.hidden=false;commentsList.innerHTML=''}
  setLoading(true,'正在获取评论…');
  try{
    const data=await fetchDiscoverJson('api/v1/comments?url='+encodeURIComponent(c.url)+'&page='+c.page);
    c.comments=reset?(data.comments||[]):c.comments.concat(data.comments||[]);
    c.total=data.total||0;
    c.hasMore=Boolean(data.has_more);
    if(data.has_more)c.page=data.next_page||c.page+1;
    renderComments();
    commentsSection.scrollIntoView({behavior:'smooth',block:'start'});
  }catch(error){
    showError(error.message||'评论获取失败，请稍后再试');
  }finally{
    setLoading(false);
  }
}
commentsBtn?.addEventListener('click',()=>{
  if(!currentResult||!currentResult.url)return;
  state.comments={url:currentResult.url,page:1,total:0,comments:[],hasMore:false};
  loadComments(true);
});
commentsMore.addEventListener('click',()=>{state.comments.page+=1;loadComments(false)});

// ---- CSV 导出（仅导出本机已有数据） ----
function exportCsv(filename,rows){
  const csv=rows.map(row=>row.map(Workbench.csvCell).join(',')).join('\r\n');
  const blob=new Blob(['\uFEFF'+csv],{type:'text/csv;charset=utf-8'});
  const link=document.createElement('a');
  link.href=URL.createObjectURL(blob);
  link.download=filename;
  document.body.append(link);link.click();link.remove();
  setTimeout(()=>URL.revokeObjectURL(link.href),1000);
}
$('#history-export')?.addEventListener('click',()=>{
  const rows=loadHistory().map(item=>[item.title||'',item.author||'',platformName(item.rawUrl),item.rawUrl,formatTime(item.time)]);
  if(!rows.length){showError('还没有可导出的记录');return}
  exportCsv('parse-history.csv',[['标题','作者','平台','链接','时间'],...rows]);
});
favoritesExportBtn?.addEventListener('click',()=>{
  const rows=loadFavorites().map(item=>[item.title||'',item.author||'',platformName(item.rawUrl),item.rawUrl,item.videoUrl||'',formatTime(item.time)]);
  if(!rows.length){showError('还没有可导出的收藏');return}
  exportCsv('parse-favorites.csv',[['标题','作者','平台','原始链接','媒体链接','时间'],...rows]);
});
$('#batch-export')?.addEventListener('click',()=>{
  const rows=batchItems.filter(x=>x&&x.data).map(x=>[x.data.title||'',x.data.author||'',platformName(x.url),buildDownloadUrl(x.data.url,x.data.title)]);
  if(!rows.length){showError('还没有可导出的结果');return}
  exportCsv('batch-results.csv',[['标题','作者','平台','无水印链接'],...rows]);
});
$('#batch-zip')?.addEventListener('click',async()=>{
  const items=batchItems.filter(x=>x&&x.data);
  if(!items.length){showError('还没有可打包的结果');return}
  const btn=batchZipBtn,span=btn.querySelector('span')||btn,origText=span.textContent;
  btn.disabled=true;
  try{
    const entries=[];let done=0,totalBytes=0;
    const total=items.reduce((n,x)=>n+mediaUrls(x.data).length,0);
    for(const [index,x] of items.entries()){
      const urls=mediaUrls(x.data),extension=mediaType(x.data)==='image'?'jpg':'mp4',base=safeName(x.data,index);
      for(const [i,url] of urls.entries()){
        span.textContent=`正在下载打包 (${done+1}/${total})…`;
        const res=await fetch(mediaUrl(url),{signal:AbortSignal.timeout(60000)});
        const bytes=await Workbench.readLimited(res,128*1024*1024-totalBytes);
        totalBytes+=bytes.length;
        entries.push({name:`${String(index+1).padStart(2,'0')}-${base}${urls.length>1?'-'+String(i+1).padStart(2,'0'):''}.${extension}`,data:bytes});
        done++;
      }
    }
    span.textContent='生成 ZIP…';
    const blob=createZipBlob(entries);
    const blobUrl=URL.createObjectURL(blob);
    const a=document.createElement('a');a.href=blobUrl;a.download=`batch-${Date.now()}.zip`;
    document.body.appendChild(a);a.click();a.remove();
    setTimeout(()=>URL.revokeObjectURL(blobUrl),30000);
    span.textContent='打包完成！';
    setTimeout(()=>{span.textContent=origText;btn.disabled=false},1800);
  }catch(err){
    showError('批量打包下载失败: '+(err.message||err));
    span.textContent=origText;btn.disabled=false;
  }
});
$('#batch-retry')?.addEventListener('click',()=>runBatchIndices(batchItems.map((item,i)=>item.error?i:-1).filter(i=>i>=0),true));
const profileExportButton=profileSection.querySelector('#profile-export');
profileExportButton?.addEventListener('click',()=>{
  if(!state.profile){showError('请先解析主页或合集');return}
  const rows=state.profile.pages.flatMap(p=>p.items||[]).map(item=>[item.title||'',item.author||'',mediaType(item)==='image'?'图集':'视频',item.source_url||'']);
  if(!rows.length){showError('还没有可导出的作品');return}
  exportCsv((state.profile.kind==='collection'?'collection':'profile')+'-works.csv',[['标题','作者','类型','链接'],...rows]);
});
async function downloadItem(item,index){
  const urls=mediaUrls(item),extension=mediaType(item)==='image'?'jpg':'mp4';
  for(let i=0;i<urls.length;i++){
    const link=document.createElement('a');
    link.href=downloadUrl(urls[i],safeName(item,index+(i?'-'+i:'')),extension);
    link.download=safeName(item,index+(i?'-'+i:''))+'.'+extension;
    link.target='_blank';link.rel='noopener';
    document.body.append(link);link.click();link.remove();
    await new Promise(resolve=>setTimeout(resolve,350));
  }
}
function selectedItems(){
  const selected=[];
  for(let page=0;page<state.profile.pages.length;page++){
    for(let index=0;index<(state.profile.pages[page].items||[]).length;index++){
      const item=state.profile.pages[page].items[index];
      if(state.profile.selected.has(itemId(item,index,page)))selected.push(item);
    }
  }
  return selected;
}
async function downloadItems(items){
  if(!items.length){showError('请先选择至少一个作品');return}
  profileSelectedButton.disabled=true;profileAllButton.disabled=true;profileLoadAll.disabled=true;
  try{
    // 3 路并发下载队列：作品级并发，单个作品内的图集仍按序下载
    const total=items.length;
    let done=0,next=0;
    async function worker(){
      while(next<total){
        const index=next++;
        await downloadItem(items[index],index);
        done++;
        profileSummary.textContent='正在下载 '+done+'/'+total+' 个作品…';
      }
    }
    await Promise.all(Array.from({length:Math.min(3,total)},()=>worker()));
    profileSummary.textContent='已完成 '+total+' 个作品下载';
  }finally{
    profileSelectedButton.disabled=false;profileAllButton.disabled=false;profileLoadAll.disabled=false;
  }
}

// ---- 事件绑定 ----
profileList.addEventListener('change',event=>{
  if(!event.target.matches('.profile-check')||!state.profile)return;
  const card=event.target.closest('.profile-item'),index=Number(card.dataset.index),page=state.profile.current;
  const id=itemId(state.profile.pages[page].items[index],index,page);
  if(event.target.checked)state.profile.selected.add(id);else state.profile.selected.delete(id);
  card.classList.toggle('is-selected',event.target.checked);
  profileSelectAll.checked=state.profile.pages[page].items.every((item,itemIndex)=>state.profile.selected.has(itemId(item,itemIndex,page)));
});
profileList.addEventListener('click',event=>{
  const button=event.target.closest('[data-action="download"]');
  if(!button||!state.profile)return;
  const index=Number(button.closest('.profile-item').dataset.index);
  downloadItem(state.profile.pages[state.profile.current].items[index],index);
});
profileSelectAll.addEventListener('change',()=>{
  if(!state.profile)return;
  const page=state.profile.current;
  state.profile.pages[page].items.forEach((item,index)=>{
    const id=itemId(item,index,page);
    if(profileSelectAll.checked)state.profile.selected.add(id);else state.profile.selected.delete(id);
  });
  renderProfilePage(page);
});
profilePrev.addEventListener('click',()=>{if(state.profile&&state.profile.current>0)renderProfilePage(state.profile.current-1)});
profileNext.addEventListener('click',async()=>{
  if(!state.profile)return;
  const page=state.profile.pages[state.profile.current];
  if(!page||!page.has_more)return;
  const nextPage=state.profile.current+1;
  if(state.profile.pages[nextPage]){renderProfilePage(nextPage);return}
  profileNext.disabled=true;
  setLoading(true,'正在读取第 '+(nextPage+1)+' 页作品…');
  try{
    state.profile.pages[nextPage]=await requestProfile(state.profile.url,page.next_cursor);
    renderProfilePage(nextPage);
  }catch(error){
    showError(error.message||'下一页解析失败，请稍后再试');
  }finally{
    setLoading(false);
    if(state.profile&&state.profile.pages[state.profile.current])profileNext.disabled=!state.profile.pages[state.profile.current].has_more;
  }
});
profileSelectedButton.addEventListener('click',()=>downloadItems(selectedItems()));
profileAllButton.addEventListener('click',async()=>{
  if(!state.profile)return;
  // 全部下载：先把剩余分页全部加载出来，再整体下载
  const last=state.profile.pages[state.profile.pages.length-1];
  if(last&&last.has_more){await loadAllPages()}
  await downloadItems(state.profile.pages.flatMap(page=>page.items||[]));
});
profileLoadAll.addEventListener('click',()=>loadAllPages());

historyList.addEventListener('click',async e=>{const card=e.target.closest('.history-entry'),a=e.target.closest('[data-action]');if(!card||!a)return;const item=loadHistory()[Number(card.dataset.index)];if(!item)return;const action=a.dataset.action;if(action==='reparse'){setMode('single');input.value=item.rawUrl;await parseUrlIntoResult(item.rawUrl)}if(action==='copy'){await copyText(item.rawUrl);a.innerHTML='✓';setTimeout(()=>a.innerHTML=COPY_ICON,1000)}if(action==='delete')removeHistory(Number(card.dataset.index));if(action==='favorite'){toggleFavorite(item);renderHistory()}if(action==='unfavorite'){toggleFavorite(item);renderHistory()}});clearHistoryBtn.addEventListener('click',()=>{saveHistory([]);renderHistory()});
weiboCookieSave?.addEventListener('click',()=>{const value=weiboCookieInput?.value.trim()||'';if(value)localStorage.setItem(WEIBO_COOKIE_KEY,value);else localStorage.removeItem(WEIBO_COOKIE_KEY);updateWeiboCookieStatus(value?'已保存':'已清空');setTimeout(()=>updateWeiboCookieStatus(),1200)});
weiboCookieClear?.addEventListener('click',()=>{localStorage.removeItem(WEIBO_COOKIE_KEY);if(weiboCookieInput)weiboCookieInput.value='';updateWeiboCookieStatus('已清空');setTimeout(()=>updateWeiboCookieStatus(),1200)});
batchList.addEventListener('click',async e=>{const card=e.target.closest('.batch-item'),a=e.target.closest('[data-action]');if(!card||!a)return;const item=batchItems[Number(card.dataset.index)];if(!item)return;if(a.dataset.action==='play'&&item.data){setMode('single');input.value=item.url;await parseUrlIntoResult(item.url)}if(a.dataset.action==='copy'&&item.data){await copyText(buildShareText(item));a.textContent='已复制';setTimeout(()=>{a.innerHTML=COPY_ICON+'复制'},1000)}if(a.dataset.action==='retry')await retryBatchItem(Number(card.dataset.index));if(a.dataset.action==='favorite'){toggleFavorite({url:item.url,title:item.data?.title,author:item.data?.author,like:item.data?.like,time:item.data?.time,cover:item.data?.cover,data:item.data});renderBatchList()}});

singleModeBtn.addEventListener('click',()=>setMode('single'));
batchModeBtn.addEventListener('click',()=>setMode('batch'));
profileModeBtn?.addEventListener('click',()=>setMode('profile'));
collectionModeBtn?.addEventListener('click',()=>setMode('collection'));
liveModeBtn?.addEventListener('click',()=>setMode('live'));
discoverModeBtn?.addEventListener('click',()=>setMode('discover'));

// 粘贴分享文案时自动提取链接；输入过程中同步归一化
pasteBtn.addEventListener('click',async()=>{try{const text=await navigator.clipboard.readText(),target=singlePanel.hidden?batchInput:input;target.value=extractUrl(text);target.focus()}catch(e){showError('无法读取剪贴板，请手动粘贴')}});
input.addEventListener('paste',event=>{const text=event.clipboardData&&event.clipboardData.getData('text');if(text){event.preventDefault();input.value=extractUrl(text);input.dispatchEvent(new Event('input',{bubbles:true}))}});
input.addEventListener('input',()=>{const url=extractUrl(input.value);if(url!==input.value&&/^https?:\/\//i.test(url))input.value=url});

parseBtn.addEventListener('click',parseVideo);
input.addEventListener('keydown',e=>{if(e.key==='Enter'&&!e.ctrlKey&&!e.metaKey){e.preventDefault();parseVideo()}});
batchParseBtn.addEventListener('click',parseBatch);
batchStopBtn.addEventListener('click',()=>{if(!batchRunning)return;batchCancelRequested=true;if(batchAbortController)batchAbortController.abort();batchProgressText.textContent='正在停止…'});

copyBtn.addEventListener('click',async()=>{if(currentResult&&downloadBtn.href){await copyText(downloadBtn.href);flashButton(copyBtn,'已复制')}});
copyTextBtn.addEventListener('click',async()=>{if(currentResult){await copyText(buildShareText(currentResult));flashButton(copyTextBtn,'已复制')}});
favBtn?.addEventListener('click',()=>{if(!currentResult)return;toggleFavorite(currentResult);updateFavBtn(currentResult.url)});

// 图集 ZIP 在客户端封装，媒体经服务端代理获取；限制累计内存与请求时间。
const CRC_TABLE=new Uint32Array(256);
for(let i=0;i<256;i++){let c=i;for(let k=0;k<8;k++)c=((c&1)?(0xEDB88320^(c>>>1)):(c>>>1));CRC_TABLE[i]=c>>>0}
function computeCrc32(buf){let crc=0xFFFFFFFF;for(let i=0;i<buf.length;i++)crc=CRC_TABLE[(crc^buf[i])&0xFF]^(crc>>>8);return(crc^0xFFFFFFFF)>>>0}
function createZipBlob(entries){
  const enc=new TextEncoder(),fileParts=[],centralParts=[];let offset=0;
  const now=new Date(),dosTime=(now.getHours()<<11)|(now.getMinutes()<<5)|(now.getSeconds()>>1),dosDate=((now.getFullYear()-1980)<<9)|((now.getMonth()+1)<<5)|now.getDate();
  for(const entry of entries){
    const bytes=entry.data instanceof Uint8Array?entry.data:new Uint8Array(entry.data),nameBytes=enc.encode(entry.name||'file'),crc=computeCrc32(bytes),size=bytes.length;
    const localHeader=new Uint8Array(30+nameBytes.length),lv=new DataView(localHeader.buffer);
    lv.setUint32(0,0x04034b50,true);lv.setUint16(4,20,true);lv.setUint16(6,0x0800,true);lv.setUint16(8,0,true);lv.setUint16(10,dosTime,true);lv.setUint16(12,dosDate,true);lv.setUint32(14,crc,true);lv.setUint32(18,size,true);lv.setUint32(22,size,true);lv.setUint16(26,nameBytes.length,true);lv.setUint16(28,0,true);
    localHeader.set(nameBytes,30);fileParts.push(localHeader,bytes);
    const centralHeader=new Uint8Array(46+nameBytes.length),cv=new DataView(centralHeader.buffer);
    cv.setUint32(0,0x02014b50,true);cv.setUint16(4,20,true);cv.setUint16(6,20,true);cv.setUint16(8,0x0800,true);cv.setUint16(10,0,true);cv.setUint16(12,dosTime,true);cv.setUint16(14,dosDate,true);cv.setUint32(16,crc,true);cv.setUint32(20,size,true);cv.setUint32(24,size,true);cv.setUint16(28,nameBytes.length,true);cv.setUint16(30,0,true);cv.setUint16(32,0,true);cv.setUint16(34,0,true);cv.setUint16(36,0,true);cv.setUint32(38,0,true);cv.setUint32(42,offset,true);
    centralHeader.set(nameBytes,46);centralParts.push(centralHeader);offset+=localHeader.length+size;
  }
  const centralOffset=offset;let centralSize=0;for(const p of centralParts)centralSize+=p.length;
  const eocd=new Uint8Array(22),ev=new DataView(eocd.buffer);
  ev.setUint32(0,0x06054b50,true);ev.setUint16(4,0,true);ev.setUint16(6,0,true);ev.setUint16(8,entries.length,true);ev.setUint16(10,entries.length,true);ev.setUint32(12,centralSize,true);ev.setUint32(16,centralOffset,true);ev.setUint16(20,0,true);
  return new Blob([...fileParts,...centralParts,eocd],{type:'application/zip'});
}

zipBtn?.addEventListener('click',async()=>{
  if(!currentResult||mediaType(currentResult.data)!=='image')return;
  const urls=mediaUrls(currentResult.data);
  if(!urls.length)return;
  const baseTitle=safeName(currentResult.data,0);
  zipBtn.disabled=true;
  const span=zipBtn.querySelector('span');
  const origText=span.textContent;
  try{
    let totalBytes=0;const entries=[];
    for(let i=0;i<urls.length;i++){
      span.textContent=`正在打包 (${i+1}/${urls.length})…`;
      const res=await fetch(mediaUrl(urls[i]),{signal:AbortSignal.timeout(60000)});
      if(!res.ok)throw new Error(`第 ${i+1} 张图片下载失败`);
      const buf=await Workbench.readLimited(res,128*1024*1024-totalBytes);totalBytes+=buf.length;
      entries.push({name:`${baseTitle}-${String(i+1).padStart(2,'0')}.jpg`,data:new Uint8Array(buf)});
    }
    span.textContent='生成 ZIP…';
    const blob=createZipBlob(entries);
    const blobUrl=URL.createObjectURL(blob);
    const a=document.createElement('a');a.href=blobUrl;a.download=`${baseTitle}.zip`;
    document.body.appendChild(a);a.click();a.remove();
    setTimeout(()=>URL.revokeObjectURL(blobUrl),30000);
    span.textContent='打包下载完成！';
    setTimeout(()=>{span.textContent=origText;zipBtn.disabled=false},1800);
  }catch(err){
    showError('图集打包下载失败: '+(err.message||err));
    span.textContent=origText;
    zipBtn.disabled=false;
  }
});

// 播放失败时自动重新解析一次刷新失效的媒体地址
video.addEventListener('error',async()=>{
  if(ignoreMediaError)return;
  if(currentResult&&currentResult.url&&mediaRefreshKey!==currentResult.url&&mediaType(currentResult.data)!=='image'){
    mediaRefreshKey=currentResult.url;
    ignoreMediaError=true;
    try{
      const item=await requestParse(currentResult.url,null,true);
      if(item&&item.data&&item.data.url){video.src=mediaUrl(item.data.url);video.load();ignoreMediaError=false;return}
    }catch(e){}
    ignoreMediaError=false;
    showError('视频源地址已失效，已尝试重新获取，请稍后再试');
    return;
  }
  showError('视频播放失败，源站链接可能已失效或暂时禁止访问。');
});

function applyTheme(theme,persist){document.documentElement.classList.toggle('dark',theme==='dark');themeToggle.innerHTML=theme==='dark'?'<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"></circle><path d="M12 2v2"></path><path d="M12 20v2"></path><path d="m4.93 4.93 1.41 1.41"></path><path d="m17.66 17.66 1.41 1.41"></path><path d="M2 12h2"></path><path d="M20 12h2"></path></svg>':'<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.5 14.7A8.5 8.5 0 0 1 9.3 3.5 8.5 8.5 0 1 0 20.5 14.7z"></path></svg>';themeToggle.title=theme==='dark'?'切换浅色主题':'切换深色主题';themeToggle.setAttribute('aria-label',themeToggle.title);const meta=document.querySelector('meta[name="theme-color"]');if(meta)meta.setAttribute('content',theme==='dark'?'#03080c':'#f1f6f8');if(persist)localStorage.setItem('watermark-parse-theme-v2',theme)}
function preferredTheme(){const saved=localStorage.getItem('watermark-parse-theme-v2');if(saved==='dark'||saved==='light')return saved;return window.matchMedia&&window.matchMedia('(prefers-color-scheme: light)').matches?'light':'dark'}
// 仅在用户显式切换时持久化，否则系统主题变化永远无法生效
themeToggle.addEventListener('click',()=>applyTheme(document.documentElement.classList.contains('dark')?'light':'dark',true));applyTheme(preferredTheme(),false);renderHistory();renderFavorites();updateWeiboCookieStatus();
// 未手动指定主题时，跟随系统明暗切换
const systemThemeQuery=window.matchMedia?window.matchMedia('(prefers-color-scheme: light)'):null;
if(systemThemeQuery&&systemThemeQuery.addEventListener)systemThemeQuery.addEventListener('change',e=>{if(!localStorage.getItem('watermark-parse-theme-v2'))applyTheme(e.matches?'light':'dark',false)});
if('serviceWorker' in navigator){window.addEventListener('load',()=>navigator.serviceWorker.register('service-worker.js').catch(()=>{}))}
favoritesList?.addEventListener('click',async e=>{const card=e.target.closest('.history-entry'),a=e.target.closest('[data-favaction]');if(!card||!a)return;const item=loadFavorites()[Number(card.dataset.index)];if(!item)return;const action=a.dataset.favaction;if(action==='reparse'){setMode('single');input.value=item.rawUrl;await parseUrlIntoResult(item.rawUrl)}if(action==='copy'){await copyText(item.rawUrl);a.innerHTML='✓';setTimeout(()=>a.innerHTML=COPY_ICON,1000)}if(action==='unfav'){toggleFavorite(item);renderFavorites()}});
$('#library-search').addEventListener('input',()=>{renderHistory();renderFavorites()});
$('#library-backup').addEventListener('click',()=>{try{
  const blob=new Blob([library.export()],{type:'application/json'}),url=URL.createObjectURL(blob),a=document.createElement('a');
  a.href=url;a.download='short-video-library-'+new Date().toISOString().slice(0,10)+'.json';a.click();setTimeout(()=>URL.revokeObjectURL(url),30000);
  libraryStatus('备份已生成，包含历史和收藏，不含登录凭据。');
}catch(error){libraryStatus('备份失败：'+error.message)}});
$('#library-import').addEventListener('click',()=>$('#library-file').click());
$('#library-file').addEventListener('change',async event=>{
  const file=event.target.files[0];if(!file)return;
  const button=$('#library-import');button.disabled=true;libraryStatus('正在校验并合并备份…');
  try{
    if(file.size>Workbench.MAX_BACKUP_BYTES)throw new Error('备份文件不能超过 4 MiB');
    const data=library.import(await file.text());renderHistory();renderFavorites();
    libraryStatus('导入完成：历史 '+data.history.length+' 条，收藏 '+data.favorites.length+' 条。已有同链接记录保持不变。');
  }catch(error){libraryStatus('导入失败：'+error.message+'。原数据保持不变。')}
  finally{button.disabled=false;event.target.value=''}
});
window.addEventListener('storage',event=>{if(event.key==='watermark-library-v2'){renderHistory();renderFavorites()}});
// 全局快捷键：Ctrl/Cmd+Enter 触发解析，Ctrl/Cmd+K 聚焦输入框
document.addEventListener('keydown',event=>{
  const mod=event.ctrlKey||event.metaKey;
  if(!mod)return;
  const key=event.key.toLowerCase();
  if(key==='enter'){event.preventDefault();if(state.mode==='batch'){if(!batchRunning)parseBatch()}else if(!parseBtn.disabled)parseVideo();}
  else if(key==='k'){event.preventDefault();(batchPanel.hidden?input:batchInput).focus();(batchPanel.hidden?input:batchInput).select();}
});
</script>
<script>
(function(){
  const strip=document.querySelector('.platform-strip');
  const labels={douyin:'抖音',kuaishou:'快手',bilibili:'B站',pipixia:'皮皮虾',weibo:'微博',ixigua:'西瓜视频',shipinhao:'视频号',izuiyou:'最右',pipigx:'皮皮搞笑',weishi:'微视',xiaohongshu:'小红书',tiktok:'TikTok'};
  const colors=['#55dfbd','#ff866b','#f2cc72','#8db8ff','#d39bff','#7ce2d4'];
  function renderPlatforms(keys){
    if(!strip)return;
    const list=Array.isArray(keys)&&keys.length?keys:Object.keys(labels);
    strip.innerHTML=list.map((key,i)=>'<span class="platform-chip" style="--chip:'+colors[i%colors.length]+'"><i></i>'+esc(labels[key]||key)+'</span>').join('');
  }
  fetch('api/v1/platforms')
    .then(r=>r.json())
    .then(j=>{if(j&&j.success)renderPlatforms(j.data)})
    .catch(()=>renderPlatforms());
})();
</script>
<script>
(function(){
  const status=document.querySelector('#service-status');
  if(!status)return;
  const label=status.querySelector('span');
  fetch('api/v1/health',{cache:'no-store'})
    .then(async r=>({ok:r.ok,data:await r.json().catch(()=>null)}))
    .then(({ok,data})=>{
      const ready=Boolean(data&&data.data&&data.data.ready);
      status.classList.toggle('is-offline',!ok&&!ready);
      status.classList.toggle('is-degraded',ok&&!ready);
      label.textContent=ready?'服务在线':(ok?'服务不完整':'服务离线');
    })
    .catch(()=>{
      status.classList.add('is-offline');
      label.textContent='服务离线';
    });
})();
</script>
</body>
</html>
