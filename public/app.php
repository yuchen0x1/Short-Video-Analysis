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
<title>去水印解析 · 短视频工具</title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='7' fill='%231b4a49'/%3E%3Cpath d='M9 9h6v6H9zM17 17h6v6h-6zM17 9h6v6h-6zM9 17h6v6H9z' fill='%23f5c85d'/%3E%3C/svg%3E">
<style>
:root{--bg:#eef0eb;--paper:#fff;--soft:#f7f9f4;--warm:#fbfaf5;--ink:#1b2630;--muted:#68736e;--faint:#96a19b;--line:#d9ded7;--strong:#c3cbc2;--teal:#1b4a49;--teal-dark:#123b3b;--teal-soft:#e8f1ee;--coral:#d86145;--coral-dark:#ae452f;--coral-soft:#fff0eb;--yellow:#f5c85d;--danger:#b8443e;--danger-soft:#fff0ee;--shadow:0 14px 36px rgba(24,39,34,.08);--small-shadow:0 5px 16px rgba(24,39,34,.06);--r:8px;--motion:.2s ease}
:root.dark{--bg:#152220;--paper:#1d2d2a;--soft:#20332f;--warm:#26352f;--ink:#f3f5ef;--muted:#b4c0b8;--faint:#82928a;--line:#38504a;--strong:#4b625b;--teal:#a8d3c6;--teal-dark:#d8eee4;--teal-soft:#29463f;--coral:#f28a6d;--coral-dark:#ffad95;--coral-soft:#4a302c;--yellow:#f5cf73;--danger:#ff9a8b;--danger-soft:#4d2e2b;--shadow:0 16px 38px rgba(0,0,0,.2);--small-shadow:0 5px 16px rgba(0,0,0,.16)}
@media(prefers-reduced-motion:reduce){*,*::before,*::after{animation-duration:.01ms!important;transition-duration:.01ms!important}}
*{box-sizing:border-box}[hidden]{display:none!important}html,body{margin:0;min-height:100%}body{background:var(--bg);color:var(--ink);font-family:"Microsoft YaHei","Segoe UI",system-ui,-apple-system,sans-serif;font-size:15px;line-height:1.55;transition:background var(--motion),color var(--motion)}button,input,textarea,a{font:inherit}button{cursor:pointer}a{color:inherit}:focus-visible{outline:3px solid rgba(216,97,69,.35);outline-offset:2px}
.topbar{background:var(--teal);color:#f7fbf6;border-bottom:4px solid var(--yellow)}.topbar-inner{width:min(1320px,calc(100% - 40px));min-height:72px;margin:auto;display:flex;align-items:center;justify-content:space-between;gap:20px}.brand{display:flex;align-items:center;gap:12px;min-width:0}.brand-mark{width:38px;height:38px;flex:0 0 38px;display:grid;place-items:center;background:var(--yellow);color:var(--teal-dark);border-radius:7px;font-size:20px;font-weight:900}.brand-name{margin:0;font-size:18px;line-height:1.1;font-weight:800;letter-spacing:.02em}.brand-sub{margin:3px 0 0;color:rgba(247,251,246,.72);font-size:12px}.topbar-actions{display:flex;align-items:center;gap:12px}.online{display:inline-flex;align-items:center;gap:7px;color:rgba(247,251,246,.86);font-size:13px}.online-dot{width:8px;height:8px;border-radius:50%;background:#a7df76;box-shadow:0 0 0 4px rgba(167,223,118,.14)}.theme-toggle{width:36px;height:36px;padding:0;display:grid;place-items:center;border:1px solid rgba(247,251,246,.24);border-radius:7px;background:transparent;color:#f7fbf6;transition:background var(--motion),border-color var(--motion)}.theme-toggle:hover{background:rgba(255,255,255,.12);border-color:rgba(255,255,255,.44)}
.workspace{width:min(1320px,calc(100% - 40px));margin:auto;padding:48px 0 64px;display:grid;grid-template-columns:minmax(0,1fr) 350px;gap:28px;align-items:start}.main-column{min-width:0}.intro{margin:0 0 28px;max-width:760px}.eyebrow{display:inline-flex;align-items:center;gap:8px;color:var(--coral);font-size:12px;font-weight:800;letter-spacing:.14em}.eyebrow:before{content:"";width:24px;height:2px;background:var(--coral)}.intro h1{margin:13px 0 10px;max-width:720px;font-size:clamp(34px,4.3vw,58px);line-height:1.1;letter-spacing:-.04em;font-weight:900}.intro h1 em{color:var(--coral);font-style:normal}.intro p{max-width:590px;margin:0;color:var(--muted);font-size:16px}
.panel{background:var(--paper);border:1px solid var(--line);border-radius:var(--r);box-shadow:var(--shadow);transition:background var(--motion),border-color var(--motion),box-shadow var(--motion)}.ingest-panel{padding:24px}.panel-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin-bottom:22px}.panel-kicker{margin:0 0 4px;color:var(--faint);font-size:11px;font-weight:800;letter-spacing:.12em;text-transform:uppercase}.panel-title{margin:0;font-size:20px;line-height:1.2;font-weight:800}.panel-note{margin:6px 0 0;color:var(--muted);font-size:13px}
.mode-switch{display:inline-flex;gap:3px;padding:3px;background:var(--soft);border:1px solid var(--line);border-radius:7px;flex-shrink:0}.mode-btn{min-height:32px;padding:0 13px;border:0;border-radius:5px;background:transparent;color:var(--muted);font-size:13px;font-weight:700;transition:background var(--motion),color var(--motion),box-shadow var(--motion)}.mode-btn.active{background:var(--teal);color:#fff;box-shadow:var(--small-shadow)}:root.dark .mode-btn.active,:root.dark .btn-teal{color:#173b37}
.field-label{display:block;margin-bottom:8px;color:var(--muted);font-size:13px;font-weight:700}.input-row{display:grid;grid-template-columns:minmax(0,1fr) 44px 116px;gap:9px}.input-wrap{min-width:0}.input-wrap input,.batch-input{width:100%;border:1px solid var(--strong);border-radius:6px;background:var(--soft);color:var(--ink);outline:none;transition:background var(--motion),border-color var(--motion),box-shadow var(--motion)}.input-wrap input{height:48px;padding:0 14px}.input-wrap input::placeholder,.batch-input::placeholder{color:var(--faint)}.input-wrap input:focus,.batch-input:focus{background:var(--paper);border-color:var(--coral);box-shadow:0 0 0 4px rgba(216,97,69,.12)}.batch-input{min-height:142px;padding:13px 14px;resize:vertical;line-height:1.6}
.icon-btn,.btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;border-radius:6px;transition:background var(--motion),color var(--motion),border-color var(--motion),transform var(--motion),opacity var(--motion)}.icon-btn{width:44px;height:48px;padding:0;border:1px solid var(--strong);background:var(--paper);color:var(--teal)}.icon-btn:hover{border-color:var(--teal);background:var(--teal-soft)}.btn{min-height:42px;padding:0 15px;border:1px solid var(--line);background:var(--paper);color:var(--ink);font-size:13px;font-weight:800}.btn:hover{border-color:var(--strong);background:var(--soft)}.btn:disabled,.icon-btn:disabled{opacity:.48;cursor:not-allowed}.btn-primary{border-color:var(--coral);background:var(--coral);color:#fff}.btn-primary:hover:not(:disabled){border-color:var(--coral-dark);background:var(--coral-dark);transform:translateY(-1px)}.btn-teal{border-color:var(--teal);background:var(--teal);color:#fff}.btn-teal:hover:not(:disabled){border-color:var(--teal-dark);background:var(--teal-dark);transform:translateY(-1px)}.btn-quiet{background:transparent;border-color:transparent;color:var(--muted)}.btn-quiet:hover{color:var(--ink);background:var(--soft)}.batch-actions{display:flex;gap:8px;margin-top:12px}.input-hint{margin:10px 0 0;color:var(--faint);font-size:12px}.platform-strip{display:flex;flex-wrap:wrap;gap:8px;margin-top:22px;padding-top:18px;border-top:1px solid var(--line)}.platform-chip{display:inline-flex;align-items:center;gap:6px;min-height:25px;padding:0 9px;border:1px solid var(--line);border-radius:999px;background:var(--warm);color:var(--muted);font-size:12px}.platform-chip i{width:6px;height:6px;border-radius:50%;background:var(--chip)}
.state-row{display:flex;align-items:center;gap:9px;min-height:38px;margin-top:14px;padding:0 2px;color:var(--muted);font-size:13px}.spinner{width:16px;height:16px;border:2px solid var(--line);border-top-color:var(--coral);border-radius:50%;animation:spin .7s linear infinite}@keyframes spin{to{transform:rotate(360deg)}}.error-alert{display:flex;align-items:flex-start;gap:9px;margin-top:14px;padding:12px 14px;border:1px solid #f2b0a5;border-radius:6px;background:var(--danger-soft);color:var(--danger);font-size:13px}.error-alert svg{flex:0 0 auto;margin-top:2px}
.result-panel,.batch-panel{margin-top:28px;overflow:hidden}.result-layout{display:grid;grid-template-columns:minmax(0,1.03fr) minmax(280px,.97fr);min-height:320px}.result-video-wrap{min-height:320px;padding:22px;background:#142322;display:grid;place-items:center}.result-video-wrap video{display:block;width:100%;max-height:470px;aspect-ratio:16/10;object-fit:contain;border-radius:5px;background:#0b1514}.result-info{padding:25px;display:flex;flex-direction:column;min-width:0}.result-tag{display:inline-flex;align-self:flex-start;padding:4px 8px;background:var(--teal-soft);color:var(--teal);border-radius:4px;font-size:11px;font-weight:800}.result-title{margin:15px 0 7px;font-size:22px;line-height:1.35;font-weight:850;overflow-wrap:anywhere}.result-meta{margin:0;color:var(--muted);font-size:13px}.creator-row{display:flex;align-items:center;gap:10px;margin-top:22px}.avatar{width:38px;height:38px;border-radius:50%;border:1px solid var(--line);object-fit:cover;background:var(--soft)}.creator-label{color:var(--faint);font-size:11px}.creator-name{margin-top:1px;font-size:14px;font-weight:800}.result-stats{display:flex;gap:9px;margin-top:24px}.result-stat{min-width:88px;padding:10px 12px;border-top:2px solid var(--yellow);background:var(--warm)}.result-stat dt{color:var(--faint);font-size:11px}.result-stat dd{margin:2px 0 0;font-size:15px;font-weight:800}.result-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:auto;padding-top:24px}.result-actions .btn{text-decoration:none}
.section-heading{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:20px 22px 16px;border-bottom:1px solid var(--line)}.section-heading h2{margin:0;font-size:17px;line-height:1.2}.section-heading p{margin:4px 0 0;color:var(--muted);font-size:12px}.batch-summary{color:var(--muted);font-size:12px}.batch-list{display:grid}.batch-empty{padding:28px 22px;color:var(--faint);font-size:13px}.batch-item{display:grid;grid-template-columns:108px minmax(0,1fr) auto;gap:13px;align-items:center;padding:14px 22px;border-bottom:1px solid var(--line)}.batch-item:last-child{border-bottom:0}.batch-thumb{width:108px;height:64px;object-fit:cover;border-radius:5px;background:var(--soft)}.batch-thumb-wrap{width:108px;height:64px}.batch-thumb-wrap .batch-thumb,.batch-thumb-placeholder{display:block;width:100%;height:100%;border-radius:5px;background:linear-gradient(135deg,rgba(150,212,197,.14),rgba(150,212,197,.04));border:1px solid var(--ag-border);box-sizing:border-box}.batch-content{min-width:0}.batch-title{margin:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:14px;font-weight:800}.batch-meta{margin:3px 0 0;color:var(--muted);font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.batch-actions{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:6px;margin:0}.batch-actions .btn{min-height:34px;padding:0 10px;font-size:12px}.batch-item.is-loading .batch-thumb{background:var(--line);animation:pulse 1.3s ease-in-out infinite alternate}.batch-item.is-loading .batch-title{width:58%;height:16px;background:var(--line);color:transparent;border-radius:3px}@keyframes pulse{to{opacity:.55}}.batch-item.is-error{background:var(--coral-soft)}.batch-item.is-error .batch-title{color:var(--danger)}
.sidebar{display:grid;gap:16px;position:sticky;top:20px}.side-panel{padding:18px}.side-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:15px}.side-heading h2{margin:0;font-size:17px}.side-heading p{margin:4px 0 0;color:var(--muted);font-size:12px}.count-badge{min-width:28px;height:24px;padding:0 7px;display:inline-grid;place-items:center;border-radius:4px;background:var(--yellow);color:var(--teal-dark);font-size:12px;font-weight:900}.history-list{display:grid;gap:8px}.history-empty{padding:28px 10px 22px;border-top:1px dashed var(--strong);text-align:center;color:var(--muted)}.history-empty-icon{width:40px;height:40px;margin:0 auto 10px;display:grid;place-items:center;border:1px solid var(--line);border-radius:6px;color:var(--coral);background:var(--warm)}.history-empty strong{display:block;color:var(--ink);font-size:13px}.history-empty span{display:block;margin-top:3px;font-size:12px}.history-entry{display:grid;grid-template-columns:62px minmax(0,1fr) auto;gap:9px;align-items:center;padding:8px;border:1px solid var(--line);border-radius:6px;background:var(--soft)}.history-thumb{width:62px;height:42px;object-fit:cover;border-radius:4px;background:var(--line)}.history-copy{min-width:0}.history-title{margin:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;font-weight:800}.history-meta{margin:2px 0 0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--muted);font-size:11px}.history-actions{display:flex;gap:2px}.history-actions button{width:28px;height:28px;padding:0;display:grid;place-items:center;border:0;border-radius:4px;background:transparent;color:var(--muted)}.history-actions button:hover{background:var(--teal-soft);color:var(--teal)}.side-divider{height:1px;margin:18px 0;background:var(--line)}.account-form{display:grid;gap:9px}.account-form input{width:100%;height:38px;padding:0 10px;border:1px solid var(--strong);border-radius:4px;background:var(--soft);color:var(--ink);outline:none}.account-form input:focus{border-color:var(--teal);box-shadow:0 0 0 3px rgba(83,223,189,.12)}.account-status{display:inline-flex;align-items:center;gap:6px;color:var(--muted);font-size:12px;font-weight:800}.account-status i{width:7px;height:7px;border-radius:50%;background:var(--faint)}.account-status.is-ready i{background:var(--teal)}.account-actions{display:flex;gap:8px;flex-wrap:wrap}.quick-list{display:grid;gap:10px}.quick-row{display:flex;align-items:center;justify-content:space-between;gap:10px;color:var(--muted);font-size:13px}.quick-label{display:inline-flex;align-items:center;gap:8px}.quick-label svg{color:var(--coral)}.quick-value{color:var(--ink);font-weight:800}.clear-btn{min-height:28px;padding:0 8px;border:0;border-radius:4px;background:transparent;color:var(--muted);font-size:12px}.clear-btn:hover{background:var(--coral-soft);color:var(--coral-dark)}footer{width:min(1320px,calc(100% - 40px));margin:auto;padding:0 0 28px;color:var(--faint);font-size:12px}
@media(max-width:980px){.workspace{grid-template-columns:1fr}.sidebar{position:static;grid-template-columns:minmax(0,1.2fr) minmax(260px,.8fr);align-items:start}}@media(max-width:720px){.topbar-inner,.workspace,footer{width:min(100% - 28px,1320px)}.workspace{padding:32px 0 46px;gap:20px}.intro h1{font-size:36px}.ingest-panel{padding:18px}.panel-heading{flex-direction:column;gap:13px}.result-layout{grid-template-columns:1fr}.result-video-wrap{min-height:0;padding:14px}.result-info{padding:20px}.sidebar{grid-template-columns:1fr}}@media(max-width:520px){.topbar-inner{min-height:64px}.brand-sub,.online{display:none}.input-row{grid-template-columns:minmax(0,1fr) 44px}.input-row .btn-primary{grid-column:1/-1}.batch-item{grid-template-columns:76px minmax(0,1fr);padding:12px 14px}.batch-thumb{width:76px;height:50px}.batch-thumb-wrap{width:76px;height:50px}.batch-actions{grid-column:1/-1;justify-content:flex-start}.result-actions .btn{flex:1 1 calc(50% - 8px)}}
</style>
<style>
/* Aurora glass: final polish layer */
:root{
  --ag-bg:#040b10;
  --ag-text:#edf7f4;
  --ag-muted:#a6bab4;
  --ag-faint:#738b83;
  --ag-teal:#5ce0bd;
  --ag-teal-2:#3bc3a5;
  --ag-coral:#ff8a6c;
  --ag-coral-2:#f2745f;
  --ag-amber:#f2c96f;
  --ag-violet:#a6a2ff;
  --ag-blue:#7fc1ff;
  --ag-surface:rgba(13,26,31,.62);
  --ag-surface-strong:rgba(16,33,39,.84);
  --ag-border:rgba(150,212,197,.16);
  --ag-border-strong:rgba(150,212,197,.32);
  --ag-shadow:0 24px 70px rgba(0,0,0,.34),0 4px 18px rgba(0,0,0,.18);
  --ag-glow-teal:rgba(92,224,189,.16);
  --ag-glow-coral:rgba(255,138,108,.14);
}
:root:not(.dark){
  --ag-bg:#eef3f4;
  --ag-text:#14242a;
  --ag-muted:#5b7078;
  --ag-faint:#8ba0a6;
  --ag-teal:#0d7d6c;
  --ag-teal-2:#0b9583;
  --ag-coral:#cf5a40;
  --ag-coral-2:#b94d37;
  --ag-amber:#a97b1c;
  --ag-violet:#6b63c9;
  --ag-blue:#2d70d1;
  --ag-surface:rgba(255,255,255,.66);
  --ag-surface-strong:rgba(255,255,255,.86);
  --ag-border:rgba(24,55,62,.14);
  --ag-border-strong:rgba(24,55,62,.3);
  --ag-shadow:0 22px 55px rgba(24,48,55,.13),0 4px 14px rgba(24,48,55,.06);
  --ag-glow-teal:rgba(13,125,108,.13);
  --ag-glow-coral:rgba(207,90,64,.12);
}
html{background:var(--ag-bg)}
body{
  background:
    linear-gradient(118deg,rgba(48,134,117,.22),rgba(48,134,117,0) 40%),
    linear-gradient(238deg,rgba(95,84,178,.17),rgba(95,84,178,0) 44%),
    linear-gradient(18deg,rgba(214,101,78,.13),rgba(214,101,78,0) 36%),
    linear-gradient(158deg,rgba(238,190,92,.09),rgba(238,190,92,0) 32%),
    linear-gradient(180deg,#040b10 0%,#07141a 52%,#060f15 100%);
  color:var(--ag-text);
}
:root:not(.dark) body{
  background:
    linear-gradient(118deg,rgba(18,128,110,.13),rgba(18,128,110,0) 42%),
    linear-gradient(238deg,rgba(107,99,201,.09),rgba(107,99,201,0) 46%),
    linear-gradient(18deg,rgba(207,90,64,.07),rgba(207,90,64,0) 38%),
    linear-gradient(180deg,#f1f6f5 0%,#f6f9f8 54%,#eaf0f0 100%);
}
body:before,body:after{
  display:block!important;
  content:"";
  position:fixed;
  left:0;
  right:0;
  top:0;
  bottom:0;
  pointer-events:none;
  animation:none;
}
body:before{
  z-index:-2;
  background-image:
    linear-gradient(rgba(150,212,197,.05) 1px,transparent 1px),
    linear-gradient(90deg,rgba(150,212,197,.05) 1px,transparent 1px);
  background-size:48px 48px;
  -webkit-mask-image:linear-gradient(180deg,rgba(0,0,0,.85),transparent 78%);
  mask-image:linear-gradient(180deg,rgba(0,0,0,.85),transparent 78%);
}
body:after{
  z-index:-1;
  background:linear-gradient(90deg,transparent,rgba(255,255,255,.028) 50%,transparent);
}
:root:not(.dark) body:before{background-image:linear-gradient(rgba(24,64,70,.06) 1px,transparent 1px),linear-gradient(90deg,rgba(24,64,70,.06) 1px,transparent 1px)}
:root:not(.dark) body:after{background:linear-gradient(90deg,transparent,rgba(255,255,255,.5) 50%,transparent)}
::selection{background:rgba(92,224,189,.26);color:inherit}
:focus-visible{outline:2px solid var(--ag-teal);outline-offset:2px;box-shadow:0 0 0 4px var(--ag-glow-teal)}
::-webkit-scrollbar{width:10px;height:10px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:rgba(150,212,197,.2);border:2px solid transparent;border-radius:8px;background-clip:padding-box}
::-webkit-scrollbar-thumb:hover{background:rgba(150,212,197,.34);border:2px solid transparent;background-clip:padding-box}
.topbar{
  position:sticky;
  top:0;
  z-index:50;
  background:rgba(6,15,19,.72);
  color:var(--ag-text);
  border-bottom:1px solid var(--ag-border);
  box-shadow:0 14px 40px rgba(0,0,0,.18),inset 0 -1px 0 rgba(255,255,255,.03);
  backdrop-filter:blur(26px) saturate(160%);
  -webkit-backdrop-filter:blur(26px) saturate(160%);
}
:root.dark .topbar{background:rgba(6,15,19,.76);color:var(--ag-text);border-bottom-color:var(--ag-border)}
:root:not(.dark) .topbar{background:rgba(240,246,245,.72);color:var(--ag-text);border-bottom-color:rgba(13,125,108,.2)}
.topbar-inner{min-height:68px}
.brand-mark{
  width:40px;
  height:40px;
  flex-basis:40px;
  border-radius:8px;
  background:linear-gradient(135deg,var(--ag-teal),var(--ag-teal-2));
  color:#03231d;
  font-size:18px;
  box-shadow:0 10px 26px var(--ag-glow-teal),inset 0 1px 0 rgba(255,255,255,.35),inset 0 -1px 0 rgba(0,0,0,.14);
}
.brand-name{color:var(--ag-text);font-size:17px;letter-spacing:0}
.brand-sub{color:var(--ag-muted);font-size:11px}
.online{color:var(--ag-muted)}
.online-dot{background:#7de3a7;box-shadow:0 0 0 4px rgba(125,227,167,.14);animation:ag-pulse 2.4s ease-in-out infinite}
.online.is-offline{color:#ffb6a3}.online.is-offline .online-dot{background:#ff8064;box-shadow:0 0 0 4px rgba(255,128,100,.14)}
.online.is-degraded{color:#f7d988}.online.is-degraded .online-dot{background:#f5cf73;box-shadow:0 0 0 4px rgba(245,207,115,.14)}
.theme-toggle{width:38px;height:38px;border-radius:8px;background:rgba(255,255,255,.055);border:1px solid var(--ag-border-strong);color:var(--ag-text);transition:background .2s ease,border-color .2s ease,transform .2s ease}
.theme-toggle:hover{background:rgba(255,255,255,.11);border-color:var(--ag-teal);transform:translateY(-1px)}
@keyframes ag-pulse{0%,100%{box-shadow:0 0 0 4px rgba(125,227,167,.14)}50%{box-shadow:0 0 0 7px rgba(125,227,167,.05)}}
.workspace{padding-top:38px}
.intro{margin-bottom:26px}
.eyebrow{color:var(--ag-teal);letter-spacing:0}
.intro h1{font-size:42px;line-height:1.14;letter-spacing:0;font-weight:900;max-width:720px}
.intro h1 em{color:var(--ag-coral)}
.intro p{font-size:15px;color:var(--ag-muted);max-width:560px}
.panel{
  background:var(--ag-surface);
  border:1px solid var(--ag-border);
  border-radius:8px;
  box-shadow:var(--ag-shadow),inset 0 1px 0 rgba(255,255,255,.07);
  backdrop-filter:blur(24px) saturate(150%);
  -webkit-backdrop-filter:blur(24px) saturate(150%);
  overflow:hidden;
  transition:transform .25s ease,box-shadow .25s ease,border-color .25s ease,background .25s ease;
}
:root.dark .panel{background:var(--ag-surface);border-color:var(--ag-border)}
:root:not(.dark) .panel{background:var(--ag-surface);border-color:var(--ag-border)}
.panel:hover,:root.dark .panel:hover,:root:not(.dark) .panel:hover{border-color:var(--ag-border-strong);transform:translateY(-1px);box-shadow:0 28px 78px rgba(0,0,0,.36),inset 0 1px 0 rgba(255,255,255,.09)}
:root:not(.dark) .panel:hover{box-shadow:0 26px 62px rgba(24,48,55,.16),inset 0 1px 0 rgba(255,255,255,.8)}
.panel:before{
  content:"";
  display:block;
  position:absolute;
  inset:0;
  pointer-events:none;
  border-radius:inherit;
  background:linear-gradient(145deg,rgba(255,255,255,.09),rgba(255,255,255,0) 38%,rgba(255,255,255,.02) 70%);
  opacity:1;
}
:root:not(.dark) .panel:before{background:linear-gradient(145deg,rgba(255,255,255,.55),rgba(255,255,255,0) 42%,rgba(255,255,255,.12) 74%)}
.ingest-panel,.result-panel,.batch-panel,.side-panel{position:relative;isolation:isolate}
.ingest-panel{
  border:1px solid transparent;
  background:
    linear-gradient(var(--ag-surface-strong),var(--ag-surface-strong)) padding-box,
    linear-gradient(135deg,rgba(92,224,189,.42),rgba(255,138,108,.3) 46%,rgba(242,201,111,.26)) border-box;
}
:root.dark .ingest-panel{background:linear-gradient(var(--ag-surface-strong),var(--ag-surface-strong)) padding-box,linear-gradient(135deg,rgba(92,224,189,.42),rgba(255,138,108,.3) 46%,rgba(242,201,111,.26)) border-box;box-shadow:var(--ag-shadow),inset 0 1px 0 rgba(255,255,255,.08)}
:root:not(.dark) .ingest-panel{background:linear-gradient(rgba(255,255,255,.88),rgba(255,255,255,.88)) padding-box,linear-gradient(135deg,rgba(13,125,108,.34),rgba(207,90,64,.22) 46%,rgba(169,123,28,.2)) border-box}
.panel-kicker,.panel-note,.field-label,.input-hint,.section-heading p,.side-heading p,.quick-row,.result-meta,.creator-label,.batch-meta,.batch-summary{color:var(--ag-muted)}
.panel-kicker{letter-spacing:0}
.panel-title,.section-heading h2,.side-heading h2,.result-title,.creator-name,.batch-title{color:var(--ag-text);letter-spacing:0}
.mode-switch{background:rgba(255,255,255,.055);border:1px solid var(--ag-border);border-radius:8px;box-shadow:inset 0 1px 0 rgba(255,255,255,.05)}
.mode-btn{min-height:34px;padding:0 15px;border-radius:6px;color:var(--ag-muted);font-weight:700}
.mode-btn:hover{color:var(--ag-text)}
.mode-btn.active,:root.dark .mode-btn.active{
  background:linear-gradient(135deg,var(--ag-teal),var(--ag-teal-2));
  color:#03231d;
  box-shadow:0 8px 20px var(--ag-glow-teal),inset 0 1px 0 rgba(255,255,255,.35);
}
:root:not(.dark) .mode-btn.active{background:linear-gradient(135deg,var(--ag-teal),var(--ag-teal-2));color:#ffffff;box-shadow:0 8px 20px var(--ag-glow-teal),inset 0 1px 0 rgba(255,255,255,.4)}
.input-wrap input,.batch-input{
  background:rgba(4,14,17,.42);
  border:1px solid var(--ag-border-strong);
  color:var(--ag-text);
  border-radius:6px;
  box-shadow:inset 0 2px 8px rgba(0,0,0,.18),inset 0 1px 0 rgba(255,255,255,.05);
}
.input-wrap input:focus,.batch-input:focus{
  background:rgba(6,19,22,.55);
  border-color:var(--ag-teal);
  box-shadow:0 0 0 4px var(--ag-glow-teal),0 0 30px rgba(92,224,189,.1),inset 0 1px 0 rgba(255,255,255,.06);
}
:root.dark .input-wrap input,:root.dark .batch-input{background:rgba(4,14,17,.46)}
:root:not(.dark) .input-wrap input,:root:not(.dark) .batch-input{background:rgba(255,255,255,.72);color:var(--ag-text);box-shadow:inset 0 1px 3px rgba(20,40,46,.08),inset 0 1px 0 rgba(255,255,255,.7)}
:root:not(.dark) .input-wrap input:focus,:root:not(.dark) .batch-input:focus{background:#ffffff;box-shadow:0 0 0 4px var(--ag-glow-teal),inset 0 1px 0 rgba(255,255,255,.8)}
.input-wrap input::placeholder,.batch-input::placeholder{color:var(--ag-faint)}
.icon-btn,.btn{
  border-radius:6px;
  border:1px solid var(--ag-border-strong);
  background:rgba(255,255,255,.055);
  color:var(--ag-text);
  transition:background .2s ease,border-color .2s ease,color .2s ease,transform .2s ease,box-shadow .2s ease;
}
.icon-btn:hover,.btn:hover{background:rgba(255,255,255,.11);border-color:var(--ag-teal);transform:translateY(-1px)}
.icon-btn:active,.btn:active{transform:translateY(0)}
:root:not(.dark) .icon-btn,:root:not(.dark) .btn{background:rgba(255,255,255,.68);color:var(--ag-text)}
:root:not(.dark) .icon-btn:hover,:root:not(.dark) .btn:hover{background:#ffffff}
.btn-primary{
  background:linear-gradient(135deg,var(--ag-coral),var(--ag-coral-2));
  border:1px solid rgba(255,190,170,.4);
  color:#ffffff;
  box-shadow:0 12px 28px var(--ag-glow-coral),inset 0 1px 0 rgba(255,255,255,.28);
  position:relative;
  overflow:hidden;
}
.btn-primary:hover:not(:disabled){background:linear-gradient(135deg,#ff9b82,var(--ag-coral));border-color:rgba(255,210,195,.55);box-shadow:0 16px 34px rgba(255,138,108,.24),inset 0 1px 0 rgba(255,255,255,.34)}
.btn-primary:after{content:"";position:absolute;inset:0;pointer-events:none;background:linear-gradient(110deg,transparent 22%,rgba(255,255,255,.26) 50%,transparent 78%);transform:translateX(-120%);animation:ag-shimmer 5s ease-in-out infinite}
.btn-primary.is-cancel:after{display:none}
.btn-teal,:root.dark .btn-teal{background:linear-gradient(135deg,var(--ag-teal),var(--ag-teal-2));border-color:rgba(92,224,189,.45);color:#03231d}
.btn-quiet{background:transparent;border-color:transparent;color:var(--ag-muted)}
:root:not(.dark) .btn-quiet{background:transparent}
.btn-quiet:hover{color:var(--ag-text);background:rgba(255,255,255,.07);border-color:transparent}
:root:not(.dark) .btn-quiet:hover{background:rgba(24,55,62,.06)}
@keyframes ag-shimmer{0%,55%{transform:translateX(-120%)}85%,100%{transform:translateX(120%)}}
.platform-strip{border-top-color:var(--ag-border)}
.platform-chip{
  background:rgba(255,255,255,.05);
  border-color:var(--ag-border);
  color:var(--ag-muted);
  border-radius:999px;
  box-shadow:inset 0 1px 0 rgba(255,255,255,.04);
}
.platform-chip:hover{background:rgba(255,255,255,.1);border-color:var(--ag-border-strong);color:var(--ag-text)}
:root:not(.dark) .platform-chip{background:rgba(255,255,255,.72);border-color:rgba(24,55,62,.18)}
:root:not(.dark) .platform-chip:hover{background:#ffffff}
.state-row{
  min-height:0;
  margin-top:14px;
  padding:10px 14px;
  background:rgba(255,255,255,.035);
  border:1px solid var(--ag-border);
  border-radius:6px;
}
:root:not(.dark) .state-row{background:rgba(255,255,255,.6)}
.spinner{width:17px;height:17px;border-color:var(--ag-border-strong);border-top-color:var(--ag-teal)}
.error-alert{background:rgba(150,45,36,.18);border-color:rgba(255,138,108,.38);color:#ffb6a3;border-radius:6px}
:root:not(.dark) .error-alert{background:rgba(255,240,235,.85);color:#a93f2c}
.result-panel,.batch-panel{margin-top:28px}
.result-video-wrap{
  background:linear-gradient(160deg,rgba(5,15,19,.94),rgba(9,22,27,.84));
  border-right:1px solid var(--ag-border);
}
:root.dark .result-video-wrap{background:linear-gradient(160deg,rgba(5,15,19,.94),rgba(9,22,27,.84))}
:root:not(.dark) .result-video-wrap{background:linear-gradient(160deg,rgba(230,240,238,.9),rgba(244,248,247,.82))}
.result-video-wrap video{border-radius:6px;background:#030a0d;box-shadow:0 18px 44px rgba(0,0,0,.4),0 0 0 1px rgba(255,255,255,.06)}
.result-info{background:rgba(255,255,255,.025)}
.result-tag{background:rgba(92,224,189,.14);color:var(--ag-teal);border:1px solid rgba(92,224,189,.24)}
.result-title{font-size:22px;font-weight:800}
.avatar{background:rgba(255,255,255,.08);border-color:var(--ag-border-strong);box-shadow:0 4px 14px rgba(0,0,0,.18)}
.result-stats{gap:9px}
.result-stat{background:rgba(255,255,255,.045);border-top:2px solid var(--ag-amber);border-radius:4px}
.result-stat dt{color:var(--ag-faint)}
:root:not(.dark) .result-stat{background:rgba(24,55,62,.05)}
.section-heading,.platform-strip{border-color:var(--ag-border)}
.batch-summary{color:var(--ag-muted)}
.batch-empty{color:var(--ag-faint)}
.batch-item{border-bottom-color:var(--ag-border)}
.batch-item:last-child{border-bottom:0}
.batch-thumb{border-radius:5px;background:linear-gradient(135deg,rgba(150,212,197,.12),rgba(150,212,197,.04));border:1px solid var(--ag-border)}
.batch-actions .btn{min-height:34px;padding:0 10px;font-size:12px;border-radius:6px}
.batch-item.is-loading .batch-thumb{background:linear-gradient(110deg,rgba(150,212,197,.08),rgba(150,212,197,.2),rgba(150,212,197,.08));background-size:200% 100%;animation:ag-load 1.2s ease infinite}
.batch-item.is-loading .batch-title{background:rgba(150,212,197,.14);border-radius:4px;color:transparent}
.batch-item.is-error{background:rgba(150,45,36,.14)}
.batch-item.is-error .batch-title{color:#ffb6a3}
:root:not(.dark) .batch-item.is-error{background:rgba(255,240,235,.7)}
:root:not(.dark) .batch-item.is-error .batch-title{color:#a93f2c}
@keyframes ag-load{to{background-position:-200% 0}}
.sidebar{top:86px}
.sidebar .panel,:root.dark .sidebar .panel{background:var(--ag-surface)}
:root:not(.dark) .sidebar .panel{background:var(--ag-surface)}
.history-entry{background:rgba(255,255,255,.045);border-color:var(--ag-border);border-radius:6px}
.history-entry:hover{background:rgba(255,255,255,.09);border-color:var(--ag-border-strong)}
:root:not(.dark) .history-entry{background:rgba(255,255,255,.62)}
:root:not(.dark) .history-entry:hover{background:#ffffff}
.history-thumb{border-radius:5px;background:linear-gradient(135deg,rgba(150,212,197,.12),rgba(150,212,197,.04));border:1px solid var(--ag-border)}
.history-actions button:hover{background:rgba(92,224,189,.14);color:var(--ag-teal)}
.history-empty{border-top-color:var(--ag-border)}
.history-empty-icon{background:rgba(255,255,255,.05);border-color:var(--ag-border);color:var(--ag-coral)}
:root:not(.dark) .history-empty-icon{background:rgba(255,255,255,.62)}
.side-divider{background:var(--ag-border)}
.count-badge{background:linear-gradient(135deg,var(--ag-amber),#e0b653);color:#211a08;border-radius:999px}
.quick-label svg{color:var(--ag-coral)}
.clear-btn{color:var(--ag-muted)}
.clear-btn:hover{background:rgba(255,138,108,.14);color:var(--ag-coral)}
footer{width:min(1320px,calc(100% - 40px));margin:0 auto;padding:0 0 34px;text-align:center;color:var(--ag-faint);opacity:.85}
.intro,.ingest-panel,.sidebar .panel{animation:ag-rise .55s cubic-bezier(.2,.7,.2,1) backwards}
.sidebar .panel:nth-child(2){animation-delay:.1s}
.result-panel,.batch-panel{animation:ag-rise .5s cubic-bezier(.2,.7,.2,1) backwards}
@keyframes ag-rise{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}
@media(max-width:980px){
  .workspace{grid-template-columns:1fr}
  .sidebar{position:static;top:auto;grid-template-columns:minmax(0,1.2fr) minmax(260px,.8fr);align-items:start}
}
@media(max-width:720px){
  .topbar-inner,.workspace,footer{width:min(100% - 28px,1320px)}
  .workspace{padding:26px 0 46px;gap:20px}
  .intro h1{font-size:34px}
  .intro p{font-size:14px}
  .ingest-panel{padding:18px}
  .panel-heading{flex-direction:column;gap:13px}
  .mode-switch{width:100%}
  .mode-btn{flex:1}
  .result-layout{grid-template-columns:1fr}
  .result-video-wrap{min-height:0;padding:14px;border-right:0;border-bottom:1px solid var(--ag-border)}
  .result-info{padding:20px}
  .sidebar{grid-template-columns:1fr}
  .result-stats{flex-wrap:wrap}
  .result-stat{flex:1 1 42%;min-width:0}
}
@media(max-width:520px){
  .topbar-inner{min-height:60px}
  .brand-sub,.online{display:none}
  .input-row{grid-template-columns:minmax(0,1fr) 44px}
  .input-row .btn-primary{grid-column:1/-1}
  .batch-item{grid-template-columns:76px minmax(0,1fr);padding:12px 14px}
  .batch-thumb{width:76px;height:50px}
  .batch-actions{grid-column:1/-1;justify-content:flex-start}
  .result-actions .btn{flex:1 1 calc(50% - 8px)}
}

/* 从早期主题层保留的排版特征：等宽标签、大写字段名与紧凑输入字号 */
.panel-kicker,.eyebrow,.batch-summary,.quick-value,.quick-row,.input-hint,.result-tag,footer,.side-heading h2,.section-heading h2,.field-label{font-family:"Cascadia Code","Consolas",monospace}
.field-label{font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:var(--ag-teal)}
.input-wrap input,.batch-input{font-size:14px}
.mode-btn{border-radius:5px}
.mode-btn.active{border-radius:5px}
.result-tag{border-radius:1px}
@media(max-width:1100px){.workspace{grid-template-columns:minmax(0,1fr) 300px}}
</style>
<style>
.result-images{width:100%;display:grid;grid-template-columns:repeat(auto-fit,minmax(145px,1fr));gap:10px;align-items:start}.result-image-card{position:relative;overflow:hidden;border-radius:6px;background:#0b1514}.result-image-card img{display:block;width:100%;height:auto;max-height:470px;object-fit:contain}.result-image-download{position:absolute;right:8px;bottom:8px;width:32px;height:32px;display:grid;place-items:center;border-radius:5px;background:rgba(0,0,0,.66);color:#fff;text-decoration:none}
.profile-toolbar{display:flex;align-items:center;justify-content:flex-end;flex-wrap:wrap;gap:8px}.select-all-control{display:inline-flex;align-items:center;gap:7px;color:var(--ag-muted);font-size:13px;font-weight:700;white-space:nowrap}.select-all-control input,.profile-check{width:17px;height:17px;accent-color:var(--ag-coral)}.profile-list{display:grid}.profile-item{display:grid;grid-template-columns:22px 112px minmax(0,1fr) auto;gap:13px;align-items:center;padding:14px 22px;border-bottom:1px solid var(--ag-border)}.profile-item:last-child{border-bottom:0}.profile-check{margin:0}.profile-thumb{width:112px;height:72px;object-fit:cover;border-radius:5px;background:var(--soft)}.profile-content{min-width:0}.profile-title{margin:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:14px;font-weight:800;color:var(--ag-text)}.profile-meta{margin:4px 0 0;color:var(--ag-muted);font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.profile-actions{display:flex;align-items:center;gap:7px}.profile-actions .btn{min-height:34px;padding:0 10px;font-size:12px}.profile-item.is-selected{background:rgba(85,223,189,.12)}.profile-pagination{display:flex;align-items:center;justify-content:center;gap:12px;padding:16px 22px;border-top:1px solid var(--ag-border)}.profile-pagination span{min-width:70px;text-align:center;color:var(--ag-muted);font-size:13px;font-weight:700}
.live-stream{display:grid;grid-template-columns:64px minmax(0,1fr) auto;gap:13px;align-items:center;padding:13px 22px;border-bottom:1px solid var(--ag-border)}.live-stream:last-child{border-bottom:0}.live-stream-format{display:inline-grid;place-items:center;height:34px;border:1px solid var(--ag-border-strong);border-radius:5px;font-family:"Cascadia Code","Consolas",monospace;font-size:11px;font-weight:800;letter-spacing:.06em;color:var(--ag-teal)}.live-stream-url{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--ag-muted);font-size:12px;font-family:"Cascadia Code","Consolas",monospace}.live-status-badge{display:inline-flex;align-items:center;min-height:26px;padding:0 10px;border-radius:4px;font-size:11px;font-weight:800}.live-status-badge.is-live{background:rgba(92,224,189,.14);color:var(--ag-teal)}.live-status-badge.is-offline{background:rgba(242,201,111,.16);color:var(--ag-amber)}
@media(max-width:720px){.profile-toolbar{justify-content:flex-start}.profile-item{grid-template-columns:22px 76px minmax(0,1fr);padding:12px 14px}.profile-thumb{width:76px;height:54px}.profile-actions{grid-column:2/-1;justify-content:flex-start}}
@media(max-width:520px){.mode-switch{width:100%}.mode-btn{flex:1;padding-left:8px;padding-right:8px}}
</style>
<style>
.update-status{display:inline-flex;align-items:center;gap:7px;color:rgba(247,251,246,.86);font-size:13px}.update-status i{width:8px;height:8px;border-radius:50%;background:#f5c85d;box-shadow:0 0 0 4px rgba(245,200,93,.14)}.update-status.is-ready i{background:#a7df76;box-shadow:0 0 0 4px rgba(167,223,118,.14)}.update-status.is-error i{background:#ff9a8b;box-shadow:0 0 0 4px rgba(255,154,139,.14)}.update-card{margin-top:16px}.update-card p{margin:4px 0 12px;color:var(--ag-muted);font-size:12px}.update-card .update-actions{display:flex;gap:8px;flex-wrap:wrap}.update-card .btn[hidden]{display:none}
.intro-metrics{display:flex;gap:10px;margin-top:22px}.intro-metrics>div{display:flex;align-items:baseline;gap:7px;padding:8px 11px;border:1px solid var(--ag-border);border-radius:6px;background:rgba(255,255,255,.04);box-shadow:inset 0 1px 0 rgba(255,255,255,.05)}.intro-metrics strong{color:var(--ag-text);font-size:15px}.intro-metrics span{color:var(--ag-muted);font-size:11px}.input-context{display:flex;flex-wrap:wrap;gap:14px;margin-top:14px;color:var(--ag-faint);font-size:11px}.input-context span{display:inline-flex;align-items:center;gap:6px}.input-context i{width:5px;height:5px;border-radius:50%;background:var(--ag-teal);box-shadow:0 0 0 3px var(--ag-glow-teal)}.platform-strip{margin-top:18px}.ingest-panel .panel-heading{margin-bottom:20px}.input-wrap input{height:52px}.icon-btn{height:52px}.btn{min-height:44px}.sidebar .side-panel:first-child{background:linear-gradient(145deg,rgba(16,33,39,.88),rgba(13,26,31,.58));}.sidebar .update-card{border-color:rgba(92,224,189,.24)}
@media(max-width:720px){.intro-metrics{gap:7px;overflow:auto;margin-top:18px}.intro-metrics>div{min-width:max-content;padding:7px 9px}.input-context{gap:9px 14px}.input-context span{font-size:10px}}
@media(prefers-reduced-motion:reduce){.panel,.btn,.theme-toggle{transition:none!important}.online-dot{animation:none!important}}
</style>
</head>
<body>
<header class="topbar"><div class="topbar-inner"><div class="brand"><span class="brand-mark" aria-hidden="true">去</span><div><p class="brand-name">去水印解析</p><p class="brand-sub">短视频无水印工作台 · v<?= htmlspecialchars($webAppVersion, ENT_QUOTES, 'UTF-8') ?></p></div></div><div class="topbar-actions"><span id="service-status" class="online"><i class="online-dot"></i><span>检查服务</span></span><span id="update-status" class="update-status" aria-live="polite"><i></i><span>检查更新</span></span><button id="theme-toggle" class="theme-toggle" type="button" title="切换主题" aria-label="切换主题"></button></div></div></header>
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
    <section id="result" class="panel result-panel" hidden aria-label="解析结果"><div class="result-layout"><div class="result-video-wrap"><video id="result-video" controls playsinline preload="metadata"></video></div><div class="result-info"><span id="result-platform" class="result-tag">解析完成</span><h2 id="result-title" class="result-title"></h2><p id="result-meta" class="result-meta"></p><div class="creator-row"><img id="result-avatar" class="avatar" alt="" hidden><div><div class="creator-label">发布作者</div><div id="result-author" class="creator-name"></div></div></div><dl class="result-stats"><div class="result-stat"><dt>点赞</dt><dd id="result-like">-</dd></div><div class="result-stat"><dt>发布时间</dt><dd id="result-time">-</dd></div></dl><div class="result-actions"><a id="download-btn" class="btn btn-primary" download target="_blank" rel="noopener"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12"></path><path d="m7 10 5 5 5-5"></path><path d="M5 21h14"></path></svg><span>下载视频</span></a><a id="music-btn" class="btn" download target="_blank" rel="noopener" hidden><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18V5l12-2v13"></path><circle cx="6" cy="18" r="3"></circle><circle cx="18" cy="16" r="3"></circle></svg><span>下载音乐</span></a><button id="comments-btn" class="btn" type="button" hidden><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg><span>查看评论</span></a><button id="copy-btn" class="btn" type="button"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v1"></path></svg><span>复制链接</span></button><button id="copy-text-btn" class="btn" type="button"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><path d="M14 2v6h6"></path><path d="M8 13h8"></path><path d="M8 17h5"></path></svg><span>复制文案</span></button><a id="open-btn" class="btn btn-quiet" target="_blank" rel="noopener"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3h7v7"></path><path d="M10 14 21 3"></path><path d="M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5"></path></svg><span>打开视频</span></a></div></div></div></section>
    <section id="batch-results" class="panel batch-panel" hidden aria-label="批量解析结果"><div class="section-heading"><div><h2>批量处理结果</h2><p>完成的项目可以直接播放、下载或复制文案。</p></div><div class="profile-toolbar"><button id="batch-export" class="btn" type="button" hidden>导出 CSV</button><span id="batch-summary" class="batch-summary"></span></div></div><div id="batch-list" class="batch-list"></div><div id="batch-empty" class="batch-empty" hidden>还没有批量任务。</div></section>
  </div>
  <aside class="sidebar"><section id="history" class="panel side-panel" aria-label="最近解析"><div class="side-heading"><div><h2>最近处理</h2><p>只保存在当前浏览器</p></div><span id="history-count" class="count-badge">0</span></div><div id="history-list" class="history-list"></div><div id="history-empty" class="history-empty"><div class="history-empty-icon"><svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 5h16v14H4z"></path><path d="M8 9h8"></path><path d="M8 13h5"></path></svg></div><strong>这里还没有记录</strong><span>解析完成后会自动出现在这里</span></div><div class="side-divider"></div><div class="account-actions"><button id="history-export" class="btn" type="button">导出 CSV</button><button id="clear-history-btn" class="clear-btn" type="button"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2-2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg> 清空记录</button></div></section><section class="panel side-panel" aria-label="账号设置"><div class="side-heading"><div><h2>账号</h2><p>当前需要登录态的平台：微博</p></div><span id="weibo-cookie-status" class="account-status"><i></i><span>未配置</span></span></div><div class="account-form"><label class="field-label" for="weibo-cookie-input">微博凭据</label><input id="weibo-cookie-input" type="password" autocomplete="off" spellcheck="false" placeholder="粘贴微博网页版 Cookie"><p class="input-hint">在微博网页版登录后复制 Cookie；解析微博链接时仅随本次 POST 请求发送，不会写入 URL。</p><div class="account-actions"><button id="weibo-cookie-save" class="btn" type="button">保存到本浏览器</button><button id="weibo-cookie-clear" class="btn btn-quiet" type="button">清除</button></div></div></section><section class="panel side-panel" aria-label="工作台信息"><div class="side-heading"><div><h2>工作台</h2><p>让每次整理更顺手</p></div></div><div class="quick-list"><div class="quick-row"><span class="quick-label"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path></svg>单个解析</span><strong class="quick-value">即时</strong></div><div class="quick-row"><span class="quick-label"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 6h13"></path><path d="M8 12h13"></path><path d="M8 18h13"></path><path d="M3 6h.01"></path><path d="M3 12h.01"></path><path d="M3 18h.01"></path></svg>批量处理</span><strong class="quick-value">最多 3 路</strong></div><div class="quick-row"><span class="quick-label"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v18"></path><path d="M3 12h18"></path></svg>记录方式</span><strong class="quick-value">本地保存</strong></div></div></section></aside>
</main>
<footer>去水印解析 · 仅用于个人学习与研究</footer>
<script>
// 在线更新：网站启动时读取服务端代理的国内 Gitee 最新 Release。
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
const input=$('#video-url'),parseBtn=$('#parse-btn'),pasteBtn=$('#paste-btn'),singleModeBtn=$('#single-mode-btn'),batchModeBtn=$('#batch-mode-btn'),profileModeBtn=$('#profile-mode-btn'),collectionModeBtn=$('#collection-mode-btn'),liveModeBtn=$('#live-mode-btn'),discoverModeBtn=$('#discover-mode-btn'),singlePanel=$('#single-panel'),batchPanel=$('#batch-panel'),batchInput=$('#batch-input'),batchParseBtn=$('#batch-parse-btn'),batchStopBtn=$('#batch-stop-btn'),loading=$('#loading'),loadingText=$('#loading-text'),batchProgress=$('#batch-progress'),batchProgressText=$('#batch-progress-text'),errorBox=$('#error'),errorText=$('#error-text'),result=$('#result'),video=$('#result-video'),avatar=$('#result-avatar'),titleEl=$('#result-title'),metaEl=$('#result-meta'),authorEl=$('#result-author'),likeEl=$('#result-like'),timeEl=$('#result-time'),platformEl=$('#result-platform'),downloadBtn=$('#download-btn'),musicBtn=$('#music-btn'),copyBtn=$('#copy-btn'),copyTextBtn=$('#copy-text-btn'),openBtn=$('#open-btn'),historyList=$('#history-list'),historyEmpty=$('#history-empty'),historyCount=$('#history-count'),clearHistoryBtn=$('#clear-history-btn'),batchSection=$('#batch-results'),batchList=$('#batch-list'),batchEmpty=$('#batch-empty'),batchSummary=$('#batch-summary'),batchExportBtn=$('#batch-export'),themeToggle=$('#theme-toggle'),modeLabel=$('#mode-label'),serviceStatus=$('#service-status'),weiboCookieInput=$('#weibo-cookie-input'),weiboCookieSave=$('#weibo-cookie-save'),weiboCookieClear=$('#weibo-cookie-clear'),weiboCookieStatus=$('#weibo-cookie-status'),inputHint=document.querySelector('#single-panel .input-hint');
const HISTORY_KEY='watermark-parse-history-v1',WEIBO_COOKIE_KEY='watermark-weibo-cookie-v1',HISTORY_LIMIT=20,BATCH_LIMIT=20,URL_PATTERN=/https?:\/\/[^\s"'<>，。；：！？【】（）《》]+/i;
let ignoreMediaError=false,currentResult=null,batchItems=[],batchRunning=false,batchCancelRequested=false,singleAbortController=null,batchAbortController=null,mediaRefreshKey='';
const state={mode:'single',profile:null,discover:{tab:'hot',platform:'bilibili',page:1,items:[],hasMore:false,total:0,keyword:''},comments:{url:'',page:1,total:0,comments:[],hasMore:false}};
const PLAY_ICON='<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="6 3 20 12 6 21 6 3"></polygon></svg>',DOWNLOAD_ICON='<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12"></path><path d="m7 10 5 5 5-5"></path><path d="M5 21h14"></path></svg>',COPY_ICON='<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v1"></path></svg>',TRASH_ICON='<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>';

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

async function requestParse(url,signal){const payload={url};if(isWeiboUrl(url)){const weiboCookie=getWeiboCookie().trim();if(weiboCookie)payload.weiboCookie=weiboCookie}const options={method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)};if(signal)options.signal=signal;const r=await fetch('api/v1/parse',options),j=await r.json().catch(()=>null);if(!r.ok||!j||!j.success||!j.data||!j.data.url)throw new Error(j&&j.message||'解析失败，请稍后再试');return{url,data:j.data}}
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
  }else{
    platformEl.textContent=platformName(raw)+' · 解析完成';
    video.hidden=false;resultImages.hidden=true;
    video.src=mediaUrl(data.url);
    const cover=data.cover||'';
    if(cover)video.poster=mediaUrl(cover);else video.removeAttribute('poster');
    downloadBtn.href=buildDownloadUrl(data.url,data.title);
    downloadBtn.setAttribute('download',(data.title||'video')+'.mp4');
  }
  result.hidden=false;
}

function loadHistory(){try{const x=JSON.parse(localStorage.getItem(HISTORY_KEY)||'[]');return Array.isArray(x)?x:[]}catch(e){return[]}}function saveHistory(x){localStorage.setItem(HISTORY_KEY,JSON.stringify(x))}function addHistory(item){const list=loadHistory().filter(x=>x.rawUrl!==item.url);list.unshift({rawUrl:item.url,title:item.data.title||'',author:item.data.author||'',like:item.data.like??0,time:item.data.time??0,cover:item.data.cover||'',videoUrl:item.data.url||'',savedAt:Date.now()});saveHistory(list.slice(0,HISTORY_LIMIT))}function removeHistory(i){const list=loadHistory();list.splice(i,1);saveHistory(list);renderHistory()}
function historyItemHtml(item,i){const t=item.cover?mediaUrl(item.cover):'';return'<article class="history-entry" data-index="'+i+'"><img class="history-thumb" src="'+esc(t)+'" alt="" loading="lazy"><div class="history-copy"><h3 class="history-title">'+esc(item.title||'未命名视频')+'</h3><p class="history-meta">'+esc([platformName(item.rawUrl),formatTime(item.time)].filter(Boolean).join(' · '))+'</p></div><div class="history-actions"><button type="button" data-action="reparse" title="重新解析" aria-label="重新解析">'+PLAY_ICON+'</button><button type="button" data-action="copy" title="复制链接" aria-label="复制链接">'+COPY_ICON+'</button><button type="button" data-action="delete" title="删除记录" aria-label="删除记录">'+TRASH_ICON+'</button></div></article>'}
function renderHistory(){const list=loadHistory();historyCount.textContent=String(list.length);historyEmpty.hidden=list.length>0;historyList.innerHTML=list.map(historyItemHtml).join('')}
function setBatchRunning(on){batchRunning=on;batchParseBtn.disabled=on;batchStopBtn.hidden=!on;batchProgress.hidden=!on;batchParseBtn.querySelector('span').textContent=on?'处理中…':'开始批量处理'}function updateBatchSummary(){const ok=batchItems.filter(x=>x&&x.data).length,fail=batchItems.filter(x=>x&&x.error).length;batchSummary.textContent=[ok?'成功 '+ok:'',fail?'失败 '+fail:''].filter(Boolean).join(' · ');if(batchExportBtn)batchExportBtn.hidden=ok===0}
function batchItemHtml(item,i){if(item.loading)return'<article class="batch-item is-loading" data-index="'+i+'"><div class="batch-thumb"></div><div class="batch-content"><h3 class="batch-title">处理中</h3><p class="batch-meta">正在读取视频信息…</p></div><div class="batch-actions"><span class="batch-meta">请稍候</span></div></article>';if(item.error)return'<article class="batch-item is-error" data-index="'+i+'"><div class="batch-content"><h3 class="batch-title">'+esc(item.url)+'</h3><p class="batch-meta">'+esc(item.error)+'</p></div><div class="batch-actions"><button class="btn" type="button" data-action="retry">重试</button></div></article>';const d=item.data,t=d.cover?mediaUrl(d.cover):'';return'<article class="batch-item" data-index="'+i+'"><img class="batch-thumb" src="'+esc(t)+'" alt="" loading="lazy"><div class="batch-content"><h3 class="batch-title">'+esc(d.title||'未命名视频')+'</h3><p class="batch-meta">'+esc(platformName(item.url))+' · '+esc(d.author||'未知作者')+' · 点赞 '+esc(formatCount(d.like))+'</p></div><div class="batch-actions"><button class="btn" type="button" data-action="play">'+PLAY_ICON+'播放</button><a class="btn" href="'+esc(buildDownloadUrl(d.url,d.title))+'" download="'+esc((d.title||'video')+'.mp4')+'" target="_blank" rel="noopener">'+DOWNLOAD_ICON+'下载</a><button class="btn" type="button" data-action="copy">'+COPY_ICON+'复制</button></div></article>'}
function renderBatchList(){batchEmpty.hidden=batchItems.length>0;batchList.innerHTML=batchItems.map(batchItemHtml).join('');updateBatchSummary()}
async function parseBatch(){
  const urls=batchInput.value.split(/\n/).map(extractUrl).map(x=>x.trim()).filter(x=>/^https?:\/\//i.test(x)).filter((x,i,a)=>a.indexOf(x)===i);
  if(!urls.length){showError('请先粘贴至少一个有效的视频链接');return}
  if(urls.length>BATCH_LIMIT){showError('批量任务最多支持 '+BATCH_LIMIT+' 条链接，请分批处理');return}
  hideError();
  batchItems=urls.map(url=>({url,loading:true}));
  batchCancelRequested=false;
  batchAbortController=new AbortController();
  batchSection.hidden=false;
  renderBatchList();
  setBatchRunning(true);
  let done=0,next=0;
  async function worker(){
    while(!batchCancelRequested){
      const i=next++;
      if(i>=urls.length)return;
      const url=urls[i];
      try{batchItems[i]=await requestParse(url,batchAbortController.signal);addHistory(batchItems[i])}
      catch(e){batchItems[i]={url,error:batchCancelRequested||e.name==='AbortError'?'已停止处理':(e.message||'解析失败')}}
      done++;
      batchProgressText.textContent='正在处理 '+done+'/'+urls.length;
      renderBatchList();
    }
  }
  await Promise.all(Array.from({length:Math.min(3,urls.length)},()=>worker()));
  if(batchCancelRequested)batchItems=batchItems.map(x=>x&&x.loading?{url:x.url,error:'已停止处理'}:x);
  renderBatchList();renderHistory();setBatchRunning(false);
  batchAbortController=null;
  batchProgressText.textContent=batchCancelRequested?'已停止':'已完成 '+urls.length+' 项';
  batchSection.scrollIntoView({behavior:'smooth',block:'start'});
}
async function retryBatchItem(i){const old=batchItems[i];if(!old||batchRunning)return;batchItems[i]={url:old.url,loading:true};renderBatchList();try{batchItems[i]=await requestParse(old.url);addHistory(batchItems[i])}catch(e){batchItems[i]={url:old.url,error:e.message||'解析失败'}}renderBatchList();renderHistory()}
async function copyText(text){try{await navigator.clipboard.writeText(text)}catch(e){const t=document.createElement('textarea');t.value=text;t.style.position='fixed';t.style.opacity='0';document.body.appendChild(t);t.select();document.execCommand('copy');t.remove()}}
function flashButton(b,text){const s=b.querySelector('span');if(!s)return;const old=s.textContent;s.textContent=text;setTimeout(()=>s.textContent=old,1200)}
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
function isParseableVideoUrl(url){return/^https?:\/\/(www\.)?(bilibili\.com|b23\.tv)\//i.test(String(url))}
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
  const csv=rows.map(row=>row.map(cell=>'"'+String(cell??'').replace(/"/g,'""')+'"').join(',')).join('\r\n');
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
$('#batch-export')?.addEventListener('click',()=>{
  const rows=batchItems.filter(x=>x&&x.data).map(x=>[x.data.title||'',x.data.author||'',platformName(x.url),buildDownloadUrl(x.data.url,x.data.title)]);
  if(!rows.length){showError('还没有可导出的结果');return}
  exportCsv('batch-results.csv',[['标题','作者','平台','无水印链接'],...rows]);
});
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

historyList.addEventListener('click',async e=>{const card=e.target.closest('.history-entry'),a=e.target.closest('[data-action]');if(!card||!a)return;const item=loadHistory()[Number(card.dataset.index)];if(!item)return;const action=a.dataset.action;if(action==='reparse'){setMode('single');input.value=item.rawUrl;await parseUrlIntoResult(item.rawUrl)}if(action==='copy'){await copyText(item.rawUrl);a.innerHTML='✓';setTimeout(()=>a.innerHTML=COPY_ICON,1000)}if(action==='delete')removeHistory(Number(card.dataset.index))});clearHistoryBtn.addEventListener('click',()=>{saveHistory([]);renderHistory()});
weiboCookieSave?.addEventListener('click',()=>{const value=weiboCookieInput?.value.trim()||'';if(value)localStorage.setItem(WEIBO_COOKIE_KEY,value);else localStorage.removeItem(WEIBO_COOKIE_KEY);updateWeiboCookieStatus(value?'已保存':'已清空');setTimeout(()=>updateWeiboCookieStatus(),1200)});
weiboCookieClear?.addEventListener('click',()=>{localStorage.removeItem(WEIBO_COOKIE_KEY);if(weiboCookieInput)weiboCookieInput.value='';updateWeiboCookieStatus('已清空');setTimeout(()=>updateWeiboCookieStatus(),1200)});
batchList.addEventListener('click',async e=>{const card=e.target.closest('.batch-item'),a=e.target.closest('[data-action]');if(!card||!a)return;const item=batchItems[Number(card.dataset.index)];if(!item)return;if(a.dataset.action==='play'&&item.data){setMode('single');input.value=item.url;await parseUrlIntoResult(item.url)}if(a.dataset.action==='copy'&&item.data){await copyText(buildShareText(item));a.textContent='已复制';setTimeout(()=>{a.innerHTML=COPY_ICON+'复制'},1000)}if(a.dataset.action==='retry')await retryBatchItem(Number(card.dataset.index))});

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
input.addEventListener('keydown',e=>{if(e.key==='Enter')parseVideo()});
batchParseBtn.addEventListener('click',parseBatch);
batchStopBtn.addEventListener('click',()=>{if(!batchRunning)return;batchCancelRequested=true;if(batchAbortController)batchAbortController.abort();batchProgressText.textContent='正在停止…'});
batchInput.addEventListener('keydown',e=>{if((e.ctrlKey||e.metaKey)&&e.key==='Enter')parseBatch()});
copyBtn.addEventListener('click',async()=>{if(currentResult&&downloadBtn.href){await copyText(downloadBtn.href);flashButton(copyBtn,'已复制')}});
copyTextBtn.addEventListener('click',async()=>{if(currentResult){await copyText(buildShareText(currentResult));flashButton(copyTextBtn,'已复制')}});

// 播放失败时自动重新解析一次刷新失效的媒体地址
video.addEventListener('error',async()=>{
  if(ignoreMediaError)return;
  if(currentResult&&currentResult.url&&mediaRefreshKey!==currentResult.url&&mediaType(currentResult.data)!=='image'){
    mediaRefreshKey=currentResult.url;
    ignoreMediaError=true;
    try{
      const item=await requestParse(currentResult.url);
      if(item&&item.data&&item.data.url){video.src=mediaUrl(item.data.url);video.load();ignoreMediaError=false;return}
    }catch(e){}
    ignoreMediaError=false;
    showError('视频源地址已失效，已尝试重新获取，请稍后再试');
    return;
  }
  showError('视频播放失败，源站链接可能已失效或暂时禁止访问。');
});

function applyTheme(theme){document.documentElement.classList.toggle('dark',theme==='dark');themeToggle.innerHTML=theme==='dark'?'<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"></circle><path d="M12 2v2"></path><path d="M12 20v2"></path><path d="m4.93 4.93 1.41 1.41"></path><path d="m17.66 17.66 1.41 1.41"></path><path d="M2 12h2"></path><path d="M20 12h2"></path></svg>':'<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.5 14.7A8.5 8.5 0 0 1 9.3 3.5 8.5 8.5 0 1 0 20.5 14.7z"></path></svg>';themeToggle.title=theme==='dark'?'切换浅色主题':'切换深色主题';themeToggle.setAttribute('aria-label',themeToggle.title);localStorage.setItem('watermark-parse-theme-v2',theme)}
function preferredTheme(){const saved=localStorage.getItem('watermark-parse-theme-v2');return saved==='dark'||saved==='light'?saved:'dark'}
themeToggle.addEventListener('click',()=>applyTheme(document.documentElement.classList.contains('dark')?'light':'dark'));applyTheme(preferredTheme());renderHistory();updateWeiboCookieStatus();
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
