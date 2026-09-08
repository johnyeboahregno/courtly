<?php
$base = $base ?? rtrim(request()->getBasePath(), '/');
$csrf = $csrf ?? csrf_token();
$version = config('courtly.app.version', '1.0.0');
$userName = e(\Illuminate\Support\Facades\Auth::user()->name);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<meta name="csrf-token" content="<?= e($csrf) ?>">
<title>Circles — Courtly</title>
<link rel="icon" type="image/png" href="<?= $base ?>/assets/favicon.png?v=<?= e($version) ?>">
<style>
:root{
  --bg:#0b0e2a;
  --panel:rgba(16,20,48,.82);
  --stroke:rgba(120,140,255,.16);
  --text:#e6e8ff;
  --muted:#8f96c9;
  --accent:#7c5cff;
  --accent2:#ff5da2;
  --cyan:#39d0ff;
  --team1:#0084ff;
  --team2:#00c764;
  --gold:#ffc24b;
  --glow1:rgba(60,60,160,.14);
  --glow2:rgba(120,50,160,.08);
}
[data-theme="blue"]{--bg:#0f172a;--panel:rgba(15,23,42,.92);--stroke:rgba(147,197,253,.28);--text:#e5e7eb;--muted:#b7c1d1;--accent:#3b82f6;--accent2:#60a5fa;--cyan:#93c5fd;--team1:#3b82f6;--team2:#10b981;--gold:#fbbf24;--glow1:rgba(59,130,246,.11);--glow2:rgba(147,197,253,.07)}
[data-theme="cyber"]{--bg:#04151f;--panel:rgba(6,30,40,.88);--stroke:rgba(0,229,255,.22);--text:#d8f7ff;--muted:#7fb8c8;--accent:#00e5ff;--accent2:#00ff9d;--cyan:#7df3ff;--team1:#00e5ff;--team2:#00ff9d;--gold:#ffd166;--glow1:rgba(0,229,255,.10);--glow2:rgba(0,255,157,.06)}
[data-theme="emerald"]{--bg:#061711;--panel:rgba(8,28,20,.88);--stroke:rgba(0,199,100,.22);--text:#e1fff0;--muted:#8fc7aa;--accent:#00c764;--accent2:#22d3ee;--cyan:#22d3ee;--team1:#00c764;--team2:#22d3ee;--gold:#ffd166;--glow1:rgba(0,199,100,.10);--glow2:rgba(34,211,238,.06)}
[data-theme="light"]{--bg:#f5f5fa;--panel:rgba(255,255,255,.95);--stroke:rgba(15,23,42,.15);--text:#0f172a;--muted:#475569;--accent:#6d4fff;--accent2:#d61e7b;--cyan:#0e7490;--team1:#2563eb;--team2:#059669;--gold:#b45309;--glow1:rgba(99,102,241,.06);--glow2:rgba(217,70,239,.04)}
[data-theme="light"] .map-header{background:linear-gradient(180deg,rgba(255,255,255,.94),rgba(255,255,255,.6))}
[data-theme="light"] #grid{background-image:linear-gradient(rgba(15,23,42,.06) 1px,transparent 1px),linear-gradient(90deg,rgba(15,23,42,.06) 1px,transparent 1px)}
[data-theme="light"] .link{stroke:rgba(15,23,42,.30)}
[data-theme="light"] .link--soft{stroke:rgba(15,23,42,.16)}
*{box-sizing:border-box}
html{font-size:121%}
html,body{height:100%;margin:0}
body{background:var(--bg);color:var(--text);font-family:"SF Mono","JetBrains Mono","Fira Code",monospace;overflow:hidden;user-select:none;-webkit-user-select:none;-webkit-touch-callout:none;-webkit-tap-highlight-color:transparent}
button{font-family:inherit}
input,textarea{user-select:text;-webkit-user-select:text}

/* ── Header ─────────────────────────────────────────────── */
.map-header{position:fixed;top:0;left:0;right:0;min-height:56px;display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;padding:8px 16px;z-index:40;background:linear-gradient(180deg,rgba(8,10,30,.94),rgba(8,10,30,.55));backdrop-filter:blur(10px);pointer-events:none}
.map-header>*{pointer-events:auto}
.map-brand{display:flex;align-items:center;gap:10px;font-weight:800;font-size:1.05rem;letter-spacing:.01em;color:var(--text);text-decoration:none}
.map-brand img{width:30px;height:30px;object-fit:contain}
.map-brand b{color:var(--accent2)}
.map-nav{display:flex;gap:6px;flex-wrap:wrap;align-items:center}
.map-pill{display:inline-flex;align-items:center;gap:6px;font-size:.8rem;letter-spacing:.03em;padding:6px 13px;border-radius:999px;border:1px solid var(--stroke);color:var(--muted);background:transparent;text-decoration:none;font-weight:700;cursor:pointer;transition:border-color .15s,color .15s,background .15s}
.map-pill:hover{border-color:var(--accent);color:var(--text)}
.map-pill--active{border-color:var(--accent);color:#fff;background:linear-gradient(135deg,var(--accent),var(--accent2))}
.hud{display:flex;align-items:center;gap:14px}
.hud__score{text-align:center}
.hud__score .n{font-size:1.7rem;font-weight:900;line-height:1;color:#fff}
.hud__score .l{font-size:.6rem;letter-spacing:.14em;color:var(--muted)}
.hud__name{font-size:.95rem;font-weight:700}
.hud__name small{display:block;color:var(--muted);font-weight:400;font-size:.68rem;letter-spacing:.06em}
.hdr-btn{display:inline-flex;align-items:center;gap:6px;border:1px solid var(--stroke);background:var(--panel);color:var(--text);border-radius:999px;padding:8px 14px;cursor:pointer;font-size:.85rem;font-weight:700;backdrop-filter:blur(6px)}
.hdr-btn:hover{border-color:var(--accent)}
.hdr-icon{border:1px solid var(--stroke);background:var(--panel);color:var(--text);border-radius:50%;width:38px;height:38px;cursor:pointer;font-size:1rem;backdrop-filter:blur(6px)}

/* ── Map ────────────────────────────────────────────────── */
#map{position:fixed;inset:0;overflow:hidden;touch-action:none;cursor:grab;background:
  radial-gradient(1200px 800px at 50% 40%,var(--glow1),transparent 70%),
  radial-gradient(900px 700px at 80% 85%,var(--glow2),transparent 70%),
  var(--bg)}
#map.dragging{cursor:grabbing}
#world{position:absolute;left:0;top:0;transform-origin:0 0;will-change:transform}
#grid{position:absolute;left:-2400px;top:-2400px;width:4800px;height:4800px;
  background-image:
    linear-gradient(rgba(120,140,255,.055) 1px,transparent 1px),
    linear-gradient(90deg,rgba(120,140,255,.055) 1px,transparent 1px);
  background-size:64px 64px;
  -webkit-mask:radial-gradient(circle at center,rgba(0,0,0,.9),rgba(0,0,0,.15) 70%,transparent);
  mask:radial-gradient(circle at center,rgba(0,0,0,.9),rgba(0,0,0,.15) 70%,transparent)}
#landmass{position:absolute;left:-2400px;top:-2400px;pointer-events:none}
#links{position:absolute;left:-2400px;top:-2400px;width:4800px;height:4800px;pointer-events:none;overflow:visible}
.link{fill:none;stroke-linecap:round}
.link--soft{opacity:.5}
#nodes{position:absolute;left:0;top:0}

/* ── Circle nodes ───────────────────────────────────────── */
.cnode{position:absolute;left:0;top:0;z-index:2}
.cnode__inner{position:absolute;left:0;top:0;width:0;height:0;touch-action:none;cursor:grab}
.cnode__inner:active{cursor:grabbing}
.cnode__wrap{position:absolute;left:0;top:0;transform:translate(-50%,-50%)}
.cnode__core{position:absolute;inset:0;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:900;color:#fff;box-shadow:0 0 0 1px rgba(255,255,255,.18),0 0 10px var(--c,var(--accent)),0 6px 20px rgba(0,0,0,.5);background:radial-gradient(circle at 35% 30%,rgba(255,255,255,.28),transparent 45%),var(--c,var(--accent));border:1.5px solid rgba(255,255,255,.35)}
.cnode--discoverable .cnode__core{background:radial-gradient(circle at 35% 30%,rgba(255,255,255,.18),transparent 45%),#28316a;border-color:rgba(140,160,255,.5);box-shadow:0 0 0 1px rgba(140,160,255,.2),0 0 16px rgba(90,130,255,.35),0 6px 24px rgba(0,0,0,.5)}
.cnode--visiting .cnode__core{background:radial-gradient(circle at 35% 30%,rgba(255,255,255,.18),transparent 45%),#3a1f63;border-color:rgba(180,120,255,.55);box-shadow:0 0 0 1px rgba(180,120,255,.2),0 0 16px rgba(160,100,255,.4),0 6px 24px rgba(0,0,0,.5)}
.cnode--joined .cnode__core{background:radial-gradient(circle at 35% 30%,rgba(255,255,255,.22),transparent 45%),var(--c,var(--cyan));border-color:rgba(255,255,255,.35)}
.cnode--connected .cnode__core{background:radial-gradient(circle at 35% 30%,rgba(255,255,255,.18),transparent 45%),#0f5c4d;border-color:rgba(0,199,100,.5);box-shadow:0 0 0 1px rgba(0,199,100,.2),0 0 14px rgba(0,199,100,.3),0 6px 22px rgba(0,0,0,.5)}
.cnode__core--hub{background:radial-gradient(circle at 35% 30%,rgba(255,255,255,.24),transparent 52%),var(--c,var(--accent));box-shadow:0 0 0 2px rgba(255,255,255,.28),0 0 14px var(--c,var(--accent)),0 0 28px var(--c,var(--accent)),0 10px 28px rgba(0,0,0,.5)}
.hub{display:flex;flex-direction:column;align-items:center;justify-content:center;line-height:1;gap:3px}
.hub__num{font-size:2.05rem;font-weight:900;text-shadow:0 2px 14px rgba(0,0,0,.65)}
.hub__cap{font-size:.52rem;letter-spacing:.22em;font-weight:800;opacity:.9}
.cnode__name{max-width:82%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.62em;line-height:1.15;text-align:center}
.cnode__label{position:absolute;left:0;top:0;font-weight:800;font-size:1.02rem;white-space:nowrap;text-shadow:0 2px 8px rgba(0,0,0,.7)}
.cnode__meta{position:absolute;left:0;top:0;font-size:.8rem;color:var(--muted);white-space:nowrap;text-shadow:0 1px 6px rgba(0,0,0,.5)}
.cnode__gauge{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);pointer-events:none;filter:drop-shadow(0 0 3px rgba(124,92,255,.35))}
.cnode__rings i{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);border-radius:50%;border:1px solid rgba(140,160,255,.28);pointer-events:none}
.cnode__rings i:nth-child(2){border-color:rgba(140,160,255,.14)}
.cnode__rings i:nth-child(3){border-style:dashed;border-color:rgba(255,93,162,.3)}
.cnode__radar{position:absolute;left:50%;top:50%;width:170px;height:170px;transform:translate(-50%,-50%);border-radius:50%;background:conic-gradient(from 0deg,rgba(124,92,255,.14),transparent 72deg);animation:radarspin 5.5s linear infinite;pointer-events:none}
@keyframes radarspin{to{transform:translate(-50%,-50%) rotate(360deg)}}
.cnode__members{position:absolute;left:50%;top:50%;width:0;height:0}
.member-wrap{position:absolute;left:0;top:0;z-index:3}
.member{position:absolute;left:0;top:0;width:46px;height:46px;transform:translate(0,0) translate(-50%,-50%);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.85rem;font-weight:800;color:#fff;background:radial-gradient(circle at 35% 30%,rgba(255,255,255,.25),transparent 45%),#16204d;border:1.5px solid rgba(140,160,255,.45);cursor:pointer;box-shadow:0 4px 14px rgba(0,0,0,.4);transition:transform .18s cubic-bezier(.34,1.56,.64,1),border-color .15s}
.member:hover{border-color:var(--accent2);z-index:5}
.member::after{content:"";position:absolute;inset:-5px;border-radius:50%;border:1px solid transparent;transition:border-color .15s}
.member:hover::after{border-color:var(--accent2)}
.cnode.is-spring .cnode__inner{transition:transform .5s cubic-bezier(.34,1.56,.64,1)}
.cnode--ghost{opacity:.5}
.cnode__lock{position:absolute;right:2px;top:2px;z-index:4;font-size:.95rem;line-height:1;filter:drop-shadow(0 1px 4px rgba(0,0,0,.55))}
.cnode__badge{position:absolute;left:-7px;top:-7px;min-width:20px;height:20px;padding:0 5px;border-radius:999px;background:var(--accent2);color:#fff;font-size:.66rem;font-weight:900;display:flex;align-items:center;justify-content:center;box-shadow:0 0 12px var(--accent2);z-index:6}

/* ── Live session nodes + pulsing link ─────────────────── */
.snode{position:absolute;left:0;top:0;transform:translate(-50%,-50%);z-index:3;display:flex;flex-direction:column;align-items:center;gap:4px}
.snode__orb{position:relative;display:flex;align-items:center;justify-content:center;width:46px;height:46px;border-radius:50%;border:1.5px solid rgba(0,199,100,.55);background:radial-gradient(circle at 35% 30%,rgba(255,255,255,.18),transparent 45%),rgba(0,199,100,.12);cursor:pointer;box-shadow:0 4px 14px rgba(0,0,0,.4),0 0 14px rgba(0,199,100,.25);transition:border-color .15s}
.snode__orb:hover{border-color:var(--team2)}
.snode__init{font-size:.78rem;font-weight:800;color:var(--team2);text-shadow:0 0 8px var(--team2)}
.snode__orb--paused .snode__init{color:#9aa0b4;text-shadow:none}
.snode__label{max-width:110px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.66rem;font-weight:800;color:var(--text);text-shadow:0 1px 6px rgba(0,0,0,.6)}
@keyframes livedot{0%,100%{opacity:.45;transform:scale(.75)}50%{opacity:1;transform:scale(1.2)}}
.link--member{stroke:#9aa0b4;stroke-width:1;opacity:.5;stroke-linecap:round}
.link--session{stroke:var(--team2,#00c764);stroke-width:1.5;opacity:.5;stroke-linecap:round}

/* ── Glass stat cards ───────────────────────────────────── */
#statcards{position:fixed;top:74px;left:16px;z-index:34;display:flex;flex-direction:column;gap:12px;pointer-events:none}
.scard{background:rgba(16,20,48,.55);border:1px solid var(--stroke);border-radius:16px;backdrop-filter:blur(14px);padding:14px;box-shadow:0 16px 44px rgba(0,0,0,.4);display:flex;align-items:center;gap:14px;min-width:176px}
.scard__ring{width:76px;height:76px;border-radius:50%;background:conic-gradient(var(--accent) calc(var(--p,0)*1%),rgba(255,255,255,.08) 0);display:flex;align-items:center;justify-content:center;position:relative;flex-shrink:0}
.scard__ring::before{content:"";position:absolute;inset:6px;border-radius:50%;background:rgba(10,13,34,.92)}
.scard__ring .n{position:relative;font-size:1.45rem;font-weight:900;color:#fff}
.scard__txt .t{font-size:.6rem;letter-spacing:.14em;color:var(--muted);font-weight:800}
.scard__txt .s{font-size:.68rem;color:var(--text);font-weight:700;margin-top:3px}
.scard__name{font-size:1rem;font-weight:900;color:#fff;letter-spacing:.01em;max-width:210px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.scard--row{justify-content:space-between;gap:20px}
.scard__cell .v{font-size:1.45rem;font-weight:900;color:#fff;line-height:1}
.scard__cell .t{font-size:.58rem;letter-spacing:.12em;color:var(--muted);font-weight:800;margin-top:4px}

/* ── Shared actions + request lists ─────────────────────── */
.act{border:1px solid var(--stroke);background:rgba(255,255,255,.04);color:var(--text);border-radius:999px;padding:8px 14px;cursor:pointer;font-size:.8rem;font-weight:700;transition:border-color .15s,background .15s}
.act:hover{border-color:var(--accent)}
.act--primary{background:linear-gradient(135deg,var(--accent),var(--accent2));border-color:transparent}
.act--primary:hover{filter:brightness(1.1)}
.act--danger{background:linear-gradient(135deg,#ff4d6d,#e0284f);border-color:transparent}
.act--danger:hover{filter:brightness(1.08)}
.act:disabled{opacity:.45;cursor:not-allowed}
.switch{display:flex;align-items:center;gap:10px;padding:8px 14px;border:1px solid var(--stroke);border-radius:999px;cursor:pointer;font-size:.8rem;font-weight:700;margin-bottom:6px}
.switch input{display:none}
.switch__track{width:34px;height:18px;border-radius:999px;background:rgba(255,255,255,.14);position:relative;transition:background .15s}
.switch__track::after{content:'';position:absolute;left:2px;top:2px;width:14px;height:14px;border-radius:50%;background:#fff;transition:transform .18s}
.switch input:checked+.switch__track{background:var(--accent)}
.switch input:checked+.switch__track::after{transform:translateX(16px)}
.switch em{font-style:normal;color:var(--text)}
.reqlist{margin-top:10px;border-top:1px solid var(--stroke);padding-top:10px}
.req{display:flex;align-items:center;gap:10px;padding:6px 0}
.req .n{flex:1;font-size:.85rem}
.req .ok{border:1px solid var(--team2);color:var(--team2);background:transparent;border-radius:999px;padding:5px 12px;cursor:pointer;font-weight:700}
.req .no{border:1px solid var(--accent2);color:var(--accent2);background:transparent;border-radius:999px;padding:5px 12px;cursor:pointer;font-weight:700}

/* ── Notifications ──────────────────────────────────────── */
.notif-wrap{position:relative}
.notif-badge{position:absolute;right:-5px;top:-5px;min-width:18px;height:18px;padding:0 5px;border-radius:999px;background:var(--accent2);color:#fff;font-size:.62rem;font-weight:900;display:flex;align-items:center;justify-content:center;box-shadow:0 0 10px var(--accent2);pointer-events:none}
#inbox{position:fixed;top:66px;right:16px;z-index:52;display:none;background:var(--panel);border:1px solid var(--stroke);border-radius:14px;padding:12px;backdrop-filter:blur(14px);box-shadow:0 16px 50px rgba(0,0,0,.55);width:min(350px,calc(100vw - 32px));max-height:calc(100vh - 92px);overflow-y:auto}
#inbox h3{margin:0 0 6px;font-size:1rem}
#inbox h3 .x{float:right;border:none;background:transparent;color:var(--muted);cursor:pointer;font-size:1rem}
.inbox-item{padding:8px 0;border-bottom:1px solid var(--stroke);font-size:.8rem;line-height:1.45}
.inbox-item:last-child{border-bottom:none}
.inbox-item .tag-ok{color:var(--team2);font-weight:800}
.inbox-item .tag-no{color:var(--accent2);font-weight:800}
.inbox-empty{color:var(--muted);font-size:.8rem;padding:4px 0}

/* ── Popover ────────────────────────────────────────────── */
#popover{position:fixed;z-index:50;display:none;background:var(--panel);border:1px solid var(--stroke);border-radius:14px;padding:12px;backdrop-filter:blur(14px);box-shadow:0 16px 50px rgba(0,0,0,.55);min-width:210px;max-width:min(320px,calc(100vw - 16px));max-height:calc(100vh - 20px);overflow-y:auto;box-sizing:border-box}
#popover h3{margin:0 0 2px;font-size:.95rem;word-break:break-word}
#popover .p-sub{font-size:.7rem;color:var(--muted);margin:0 0 10px;word-break:break-word}
#popover .act{width:100%;text-align:left;margin-bottom:6px}
#popover .act:last-child{margin-bottom:0}

/* ── Beacon (creative join) ─────────────────────────────── */
#beacon{position:fixed;right:22px;bottom:22px;z-index:38;width:64px;height:64px;border-radius:50%;border:none;cursor:pointer;background:radial-gradient(circle at 35% 30%,rgba(255,255,255,.3),transparent 45%),linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;font-size:1.5rem;box-shadow:0 10px 30px rgba(124,92,255,.5)}
#beacon::before{content:"";position:absolute;inset:0;border-radius:50%;border:2px solid rgba(255,255,255,.5);animation:ping 2.4s ease-out infinite}
#beacon::after{content:"";position:absolute;inset:0;border-radius:50%;border:2px solid rgba(255,255,255,.35);animation:ping 2.4s ease-out .8s infinite}
@keyframes ping{0%{transform:scale(1);opacity:.9}100%{transform:scale(2.1);opacity:0}}
#recenter{position:fixed;left:22px;bottom:22px;z-index:38;width:52px;height:52px;border-radius:50%;border:1px solid var(--stroke);background:var(--panel);color:var(--text);cursor:pointer;font-size:1.15rem;backdrop-filter:blur(10px);box-shadow:0 10px 30px rgba(0,0,0,.4)}
#recenter:hover{border-color:var(--accent)}
#joinpanel{position:fixed;right:22px;bottom:100px;z-index:39;display:none;width:280px;background:var(--panel);border:1px solid var(--stroke);border-radius:18px;padding:18px;backdrop-filter:blur(16px);box-shadow:0 20px 60px rgba(0,0,0,.5);max-height:calc(100vh - 140px);overflow:auto}
#joinpanel h3{margin:0 0 4px;font-size:1rem}
#joinpanel .p-sub{font-size:.72rem;color:var(--muted);margin:0 0 12px}
.join-row{display:flex;gap:8px}
.join-row input{flex:0 0 80%;min-width:0;box-sizing:border-box;padding:10px 12px;border:1px solid var(--stroke);border-radius:999px;background:rgba(255,255,255,.05);color:var(--text);font-size:.9rem;letter-spacing:.08em;text-transform:uppercase}
.join-row .act{flex:1;padding-left:6px;padding-right:6px;display:flex;align-items:center;justify-content:center;text-align:center;white-space:nowrap}
.join-row input:focus{outline:none;border-color:var(--accent)}
.join-err{color:var(--accent2);font-size:.75rem;margin-top:8px;display:none}
.qr{margin-top:10px;padding-top:10px;border-top:1px solid var(--stroke);text-align:center}
.qr__box{position:relative;display:inline-block;line-height:0}
.qr__img{display:block;border-radius:10px;background:#fff;padding:4px;box-sizing:border-box}
.qr__logo{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:22px;height:22px;padding:4px;border-radius:50%;background:#fff;box-sizing:border-box}
.qr__code{font-size:.95rem;letter-spacing:.18em;margin-top:4px;font-weight:800}

/* ── Toasts ─────────────────────────────────────────────── */
#toasts{position:fixed;top:66px;left:50%;transform:translateX(-50%);z-index:60;display:flex;flex-direction:column;gap:8px;align-items:center}
.toast{background:var(--panel);border:1px solid var(--stroke);border-radius:999px;padding:9px 16px;font-size:.82rem;font-weight:700;backdrop-filter:blur(12px);box-shadow:0 8px 30px rgba(0,0,0,.4);animation:toastin .3s cubic-bezier(.34,1.56,.64,1)}
@keyframes toastin{from{transform:translateY(-14px) scale(.9);opacity:0}to{transform:none;opacity:1}}

/* ── Player stats popup ────────────────────────────────── */
#statsModal{position:fixed;inset:0;z-index:70;display:none;background:rgba(8,10,30,.5);backdrop-filter:blur(6px)}
.stats-card{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:min(420px,calc(100vw - 32px));max-height:80vh;overflow:auto;background:var(--panel);border:1px solid var(--stroke);border-radius:18px;padding:18px;box-shadow:0 24px 70px rgba(0,0,0,.6)}
.stats-card__head{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
.stats-card__head h3{margin:0;font-size:1.05rem}
.stats-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px}
.stat{background:rgba(255,255,255,.05);border:1px solid var(--stroke);border-radius:12px;padding:12px;text-align:center}
.stat b{display:block;font-size:1.4rem;font-weight:900;color:#fff}
.stat span{font-size:.68rem;letter-spacing:.08em;color:var(--muted);text-transform:uppercase}
.stats-note{margin:10px 0 0;font-size:.85rem;color:var(--text)}
.stats-note b{color:var(--accent2)}
#leaderModal{position:fixed;inset:0;z-index:70;display:none;background:rgba(8,10,30,.5);backdrop-filter:blur(6px)}
#manageModal{position:fixed;inset:0;z-index:70;display:none;background:rgba(8,10,30,.5);backdrop-filter:blur(6px)}
#sessionModal{position:fixed;inset:0;z-index:70;display:none;background:rgba(8,10,30,.5);backdrop-filter:blur(6px)}
#confirmModal{position:fixed;inset:0;z-index:75;display:none;background:rgba(8,10,30,.55);backdrop-filter:blur(6px)}
.manage-name{flex:1;min-width:0;padding:8px 10px;border:1px solid var(--stroke);border-radius:8px;background:rgba(255,255,255,.05);color:var(--text);font-size:.9rem}
.manage-gender{width:20px;text-align:center;font-size:.85rem}
.manage-rating{font-size:.8rem;color:var(--muted);min-width:34px;text-align:center}

@media(max-width:640px){
  /* Header — compact two-row layout: brand + score on top, nav scrolls below */
  .map-header{padding:8px 10px;gap:8px;min-height:0;justify-content:space-between}
  .map-brand{font-size:.92rem;gap:6px}
  .map-brand img{width:24px;height:24px}
  .hud{margin-left:auto;gap:10px}
  .hud__name{display:none}
  .hud__score .n{font-size:1.25rem}
  .hud__score .l{font-size:.5rem;letter-spacing:.1em}
  .map-nav{order:3;width:100%;flex-wrap:nowrap;justify-content:flex-start;overflow-x:auto;-webkit-overflow-scrolling:touch;gap:6px;padding-bottom:2px;scrollbar-width:none}
  .map-nav::-webkit-scrollbar{display:none}
  .map-pill{flex:0 0 auto;width:34px;height:34px;padding:0;justify-content:center;gap:0}
  .map-pill__icon{font-size:1rem;line-height:1}
  .map-pill__label,
  .hdr-btn__label{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
  .hdr-btn{width:34px;height:34px;padding:0;justify-content:center;gap:0}
  .hdr-btn__icon{font-size:1rem;line-height:1}
  .hdr-icon{width:32px;height:32px;font-size:.9rem}

  /* Stat cards — compact, pinned bottom-left so they never cover the focused circle */
  #statcards{top:auto;bottom:14px;left:10px;right:auto;gap:8px}
  .scard{padding:10px 12px;gap:10px;min-width:0;border-radius:14px}
  .scard__ring{width:52px;height:52px}
  .scard__ring::before{inset:5px}
  .scard__ring .n{font-size:1.05rem}
  .scard__txt .t{font-size:.5rem;letter-spacing:.1em}
  .scard__txt .s{font-size:.6rem}
  .scard--row{gap:14px;padding:10px 14px}
  .scard__cell .v{font-size:1.15rem}
  .scard__cell .t{font-size:.5rem}

  /* Beacon + join panel */
  #beacon{right:14px;bottom:14px;width:54px;height:54px;font-size:1.25rem}
  #joinpanel{left:12px;right:12px;width:auto;bottom:82px;max-height:calc(100vh - 110px);padding:16px}

  /* Popover — let it stretch on small screens */
  #popover{min-width:0}

  /* Toasts — clear the taller header */
  #toasts{top:104px;width:calc(100vw - 24px)}
  .toast{max-width:100%;text-align:center}

  /* Modals */
  .stats-card{padding:16px}
  .stats-grid{gap:8px}
}
</style>
</head>
<body>
<header class="map-header">
  <a class="map-brand" href="<?= $base ?>/circles" title="Circles"><img src="<?= $base ?>/assets/courtly-mark.png" alt=""><span>COURT<b>LY</b></span></a>
  <div class="hud">
    <div class="hud__name" id="hudName">—</div>
  </div>
  <nav class="map-nav">
    <a class="map-pill map-pill--active" href="<?= $base ?>/circles" title="Circles"><svg class="map-pill__icon" viewBox="0 0 24 24" width="1.15em" height="1.15em" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3"/></svg><span class="map-pill__label">Circles</span></a>
    <a class="map-pill" href="<?= $base ?>/" title="Sessions"><svg class="map-pill__icon" viewBox="0 0 24 24" width="1.15em" height="1.15em" fill="currentColor" aria-hidden="true"><path d="M8 5.5v13l11-6.5z"/></svg><span class="map-pill__label">Sessions</span></a>
    <a class="map-pill" href="<?= $base ?>/rankings" title="Rankings"><svg class="map-pill__icon" viewBox="0 0 24 24" width="1.15em" height="1.15em" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="6"/><path d="M15.5 13 17 22l-5-3-5 3 1.5-9"/></svg><span class="map-pill__label">Rankings</span></a>
    <button class="map-pill" id="managePlayersBtn" title="Manage players"><svg class="map-pill__icon" viewBox="0 0 24 24" width="1.15em" height="1.15em" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg><span class="map-pill__label">Manage players</span></button>
    <div class="notif-wrap">
      <button class="hdr-icon" id="notifBell" title="Notifications" aria-label="Notifications" style="color:var(--muted)"><svg viewBox="0 0 24 24" width="1em" height="1em" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg><span class="notif-badge" id="notifBadge" style="display:none">0</span></button>
    </div>
    <button class="hdr-icon" id="btnTheme" title="Theme">☾</button>
    <form method="POST" action="<?= $base ?>/logout" style="margin:0">
      <input type="hidden" name="_token" value="<?= e($csrf) ?>">
      <button type="submit" class="hdr-btn" title="Log out"><svg class="hdr-btn__icon" viewBox="0 0 24 24" width="1.15em" height="1.15em" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg><span class="hdr-btn__label">Logout</span></button>
    </form>
  </nav>
</header>

<div id="map">
  <div id="world">
    <div id="grid"></div>
    <svg id="landmass" viewBox="0 0 4800 4800" width="4800" height="4800">
      <g fill="rgba(120,150,255,.06)" stroke="rgba(120,150,255,.08)" stroke-width="2">
        <path d="M900 1200 Q1250 950 1550 1150 Q1800 1300 2100 1150 Q2350 1000 2150 1450 Q2000 1850 2250 2200 Q2500 2600 2250 2950 Q2050 3250 1600 3200 Q1150 3150 1000 2800 Q850 2450 900 2050 Q940 1650 900 1200Z"/>
        <path d="M2600 900 Q3000 700 3400 900 Q3750 1100 3900 1400 Q4050 1750 3850 2050 Q3650 2300 3300 2250 Q3000 2200 2850 1900 Q2700 1550 2600 1200Z"/>
        <path d="M1150 3400 Q1500 3200 1850 3350 Q2200 3500 2450 3350 Q2650 3200 2800 3450 Q2950 3700 2750 4000 Q2550 4250 2100 4300 Q1650 4350 1300 4200 Q1050 4050 1150 3750Z"/>
        <path d="M3300 2500 Q3600 2400 3900 2600 Q4200 2850 4100 3150 Q4000 3450 3650 3500 Q3350 3550 3200 3300 Q3050 3050 3150 2750Z"/>
      </g>
    </svg>
    <svg id="links" viewBox="0 0 4800 4800" width="4800" height="4800"></svg>
    <div id="nodes"></div>
  </div>
</div>

<button id="beacon" title="Join a circle">⌖</button>
<button id="recenter" title="Recenter on my circle" aria-label="Recenter">◎</button>
<div id="joinpanel">
  <h3>Join a circle</h3>
  <p class="p-sub">Enter an invite code, or drag your circle onto another on the map.</p>
  <div class="join-row">
    <input id="joinCode" type="text" placeholder="AB12CD34" maxlength="32">
    <button class="act act--primary" id="joinGo">Join</button>
  </div>
  <div class="join-err" id="joinErr"></div>
</div>

<div id="statcards">
  <div class="scard">
    <div class="scard__ring" id="scRing"><span class="n" id="scScore">—</span></div>
    <div class="scard__txt">
      <div class="scard__name" id="scName">FOCUSED CIRCLE</div>
      <div class="t">NETWORK SCORE</div>
      <div class="s" id="scScoreCap">0–100 engagement</div>
    </div>
  </div>
</div>

<div id="popover"></div>
<div id="inbox"></div>
<div id="statsModal"></div>
<div id="leaderModal"></div>
<div id="manageModal"></div>
<div id="sessionModal"></div>
<div id="confirmModal"></div>
<div id="toasts"></div>

<script>
const BASE = <?= json_encode($base) ?>;
const CSRF = <?= json_encode($csrf) ?>;
const ME = <?= json_encode($userName) ?>;

const state = {
  nodes: [], nodeMap: {}, positions: {}, personalId: null,
  focusId: null, pending: [], liveSessions: [], connections: [], notifications: [], unreadNotifs: 0,
  nodePos: {}, focusedAttachments: [], orbitAngles: {}, lastFocusId: null, lastClickId: null, lastClickTime: 0,
  view: { x: 0, y: 0, scale: 1 },
  drag: null, pointers: new Map(), lastWheel: 0,
};

/* ── utils ──────────────────────────────────────────────── */
function clamp(v,a,b){return Math.min(b,Math.max(a,v))}
function hashId(id){let x=(id*2654435761)>>>0;x=((x>>16)^x)*0x45d9f3b;x=((x>>16)^x)>>>0;return x/4294967296}
function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]))}
function initialsOf(name){const p=String(name||'?').trim().split(/\s+/);const a=p[0]?p[0][0]:'?';const b=p.length>1?p[p.length-1][0]:'';return (a+b).toUpperCase()}
function toast(msg){const t=document.createElement('div');t.className='toast';t.textContent=msg;document.getElementById('toasts').appendChild(t);setTimeout(()=>{t.style.opacity='0';t.style.transition='opacity .3s';setTimeout(()=>t.remove(),300)},2600)}
const NODE_COLORS=['#7c5cff','#39d0ff','#8b5cf6','#00c764','#3b82f6','#ff8a5c','#a06bff','#00e0a0'];function colorFor(id){return NODE_COLORS[id%NODE_COLORS.length]}function nodeColor(n){return n&&n.kind==='mine'?'var(--accent)':colorFor(n.id)}

/* ── api ────────────────────────────────────────────────── */
async function api(path, opts={}){
  const headers=Object.assign({'Accept':'application/json','X-CSRF-TOKEN':CSRF},opts.headers||{});
  let body=opts.body;
  if(body && typeof body==='object'){headers['Content-Type']='application/json';body=JSON.stringify(body)}
  const res=await fetch(BASE+path,Object.assign({credentials:'include'},opts,{headers,body}));
  if(res.status===204)return{ok:true,status:204};
  let json={};
  try{json=await res.json()}catch(e){}
  return Object.assign({ok:res.ok,status:res.status},json);
}

/* ── layout ─────────────────────────────────────────────── */
function layout(){
  const pos={};
  const byKind=k=>state.nodes.filter(n=>n.kind===k);
  const mine=state.nodes.find(n=>n.id===state.personalId);
  if(mine)pos[mine.id]={x:0,y:0};
  const joined=byKind('joined');
  joined.forEach((n,i)=>{const a=(i/Math.max(joined.length,1))*Math.PI*2-Math.PI/2;pos[n.id]={x:Math.cos(a)*460,y:Math.sin(a)*460}});
  const conn=byKind('connected');
  conn.forEach((n,i)=>{const a=(i/Math.max(conn.length,1))*Math.PI*2+Math.PI/4;pos[n.id]={x:Math.cos(a)*600,y:Math.sin(a)*600}});
  const disc=byKind('discoverable');
  disc.forEach(n=>{const h=hashId(n.id),h2=hashId(n.id*7+13);const a=h*Math.PI*2;const r=760+h2*980;pos[n.id]={x:Math.cos(a)*r,y:Math.sin(a)*r}});
  const focusPos=pos[state.focusId]||pos[state.personalId]||{x:0,y:0};
  byKind('visiting').forEach(n=>{const h=hashId(n.id);const a=h*Math.PI*2;pos[n.id]={x:focusPos.x+Math.cos(a)*300,y:focusPos.y+Math.sin(a)*300}});
  for(const id in state.nodePos){pos[id]=state.nodePos[id];}
  state.positions=pos;
}

function attachmentsFor(node){
  const list=[];
  (node && node.members ? node.members : []).forEach(m=>list.push(Object.assign({type:'member',key:'m'+m.user_id},m)));
  if(node)state.liveSessions.filter(s=>s.circle_id===node.id).forEach(s=>list.push(Object.assign({type:'session',key:'s'+s.id},s)));
  return list;
}

function computeAttachPositions(){
  const focusId=state.focusId||state.personalId;
  const node=state.nodeMap[focusId];
  const base=state.positions[focusId]||{x:0,y:0};
  const list=attachmentsFor(node);
  const ring=i=>(206+i*56)/2; // radius of the decorative .cnode__rings lines
  const place=(items,R,offset)=>items.map((item,i)=>{
    const def=(i/Math.max(items.length,1))*Math.PI*2-Math.PI/2+offset;
    const a=state.orbitAngles[item.key]!==undefined?state.orbitAngles[item.key]:def;
    return Object.assign({},item,{x:base.x+Math.cos(a)*R,y:base.y+Math.sin(a)*R,r:R,a:a});
  });
  state.focusedAttachments=[
    ...place(list.filter(i=>i.type==='member'),ring(1),0),
    ...place(list.filter(i=>i.type==='session'),ring(2),Math.PI/4),
  ];
}

/* ── render ─────────────────────────────────────────────── */
function coreSize(n,focused){
  if(focused)return{px:150,label:90};
  if(n.kind==='discoverable')return{px:64,label:44};
  if(n.kind==='mine')return{px:110,label:70};
  return{px:92,label:60};
}

function gaugeSvg(score,size){
  const r=Math.max(6,size/2-4);
  const c=2*Math.PI*r;
  const pct=Math.max(0,Math.min(100,score||0));
  const dash=(pct/100)*c;
  return `<svg class="cnode__gauge" width="${size}" height="${size}" viewBox="0 0 ${size} ${size}">
    <circle cx="${size/2}" cy="${size/2}" r="${r}" fill="none" stroke="rgba(255,255,255,.10)" stroke-width="4"/>
    <circle cx="${size/2}" cy="${size/2}" r="${r}" fill="none" stroke="var(--accent2)" stroke-width="4" stroke-linecap="round" stroke-dasharray="${dash.toFixed(2)} ${(c-dash).toFixed(2)}" transform="rotate(-90 ${size/2} ${size/2})"/>
  </svg>`;
}

function buildNode(n,p,focused){
  const el=document.createElement('div');
  el.className='cnode cnode--'+n.kind+(focused?' cnode--focused':'');
  el.style.left=p.x+'px';el.style.top=p.y+'px';
  el.dataset.id=n.id;
  const s=coreSize(n,focused);
  const c=nodeColor(n);
  const liveCount=(focused&&n.id===state.personalId)?state.liveSessions.length:0;
  const ringCount=3+Math.min(liveCount,4);
  const rings=focused?`<div class="cnode__rings">${Array.from({length:ringCount},(_,i)=>{const d=s.px+56+i*56;return `<i style="width:${d}px;height:${d}px"></i>`}).join('')}</div>`:'';
  const gauge=focused?gaugeSvg(n.network_score||0,s.px+20):'';
  const radar=focused?`<div class="cnode__radar" style="width:${s.px}px;height:${s.px}px"></div>`:'';
  const coreHtml=`<span class="cnode__name">${esc(n.name)}</span>`;
  const coreStyle=`--c:${c};font-size:${Math.round(s.px*(focused?0.2:0.28))}px`;
  const lockHtml=n.visibility==='PRIVATE'?`<span class="cnode__lock" title="Private">🔒</span>`:'';
  const pendCount=(n.is_admin&&n.pending_requests)?n.pending_requests.length:0;
  const badgeHtml=pendCount?`<span class="cnode__badge">${pendCount}</span>`:'';
  el.innerHTML=`<div class="cnode__inner">
    <div class="cnode__wrap" style="width:${s.px}px;height:${s.px}px;--c:${c}">
      ${rings}${gauge}${radar}
      <div class="cnode__core${focused?' cnode__core--hub':''}" style="${coreStyle}">${coreHtml}</div>
      ${lockHtml}${badgeHtml}
    </div>
  </div>`;
  el.addEventListener('pointerdown',e=>onNodePointerDown(e,n,el));
  el.addEventListener('contextmenu',e=>{e.preventDefault();openPopover(el,n)});
  return el;
}

function buildSessionNode(s,p){
  const el=document.createElement('div');
  el.className='snode';
  el.dataset.key=s.key;
  el.style.left=p.x+'px';el.style.top=p.y+'px';
  const paused=s.status==='PAUSED';
  el.innerHTML=`<button class="snode__orb${paused?' snode__orb--paused':''}" type="button" onclick="openSessionCard(${s.id})" title="${esc(s.name)}"><span class="snode__init">${esc(initialsOf(s.name))}</span></button>`;
  el.addEventListener('pointerdown',e=>onItemPointerDown(e,s.key,el));
  return el;
}

function buildMemberNode(m,p){
  const el=document.createElement('div');
  el.className='member-wrap';
  el.dataset.key=m.key;
  el.style.left=p.x+'px';el.style.top=p.y+'px';
  el.innerHTML=`<div class="member" data-user="${m.user_id}" data-player="${m.player_id||''}" data-pcircle="${m.personal_circle_id||''}" data-public="${m.personal_circle_public?1:0}" title="${esc(m.name)}">${esc(initialsOf(m.name))}</div>`;
  el.addEventListener('pointerdown',e=>onItemPointerDown(e,m.key,el));
  return el;
}

function goToSession(id){window.location.href=BASE+'/sessions/'+id+'/live'}

function openSessionCard(id){
  const s=state.liveSessions.find(x=>x.id===id);
  if(!s)return;
  const modal=document.getElementById('sessionModal');
  if(!modal)return;
  const when=s.date||'';
  const sportIcon=s.sport?`<img src="${BASE}/assets/${esc(s.sport)}.png" alt="" style="width:22px;height:22px;object-fit:contain">`:'';
  const joinBtn=s.joined
    ? `<button class="act act--danger" style="width:100%;margin-top:14px" onclick="leaveSession(${s.id})">Leave session</button>`
    : `<button class="act act--primary" style="width:100%;margin-top:14px" onclick="joinSession(${s.id})">Join</button>`;
  const viewBtn=`<button class="act" style="width:100%;margin-top:8px" onclick="goToSession(${s.id})">View session</button>`;
  modal.innerHTML=`<div class="stats-card">
    <div class="stats-card__head">
      <div style="display:flex;align-items:center;gap:10px;min-width:0">${sportIcon}<h3 style="margin:0">${esc(s.name)}</h3></div>
      <button class="act" onclick="closeSessionCard()">✕</button>
    </div>
    <div class="stats-grid">
      <div class="stat"><b>${s.player_count||0}</b><span>Players</span></div>
      <div class="stat"><b>${s.number_of_courts||1}</b><span>Courts</span></div>
      <div class="stat"><b style="color:${s.status==='ACTIVE'?'var(--team2)':'var(--muted)'}">${s.status==='ACTIVE'?'LIVE':'PAUSED'}</b><span>Status</span></div>
      <div class="stat"><b>${esc(when)||'—'}</b><span>Date</span></div>
    </div>
    ${joinBtn}${viewBtn}
  </div>`;
  modal.style.display='block';
}

function closeSessionCard(){const m=document.getElementById('sessionModal');if(m)m.style.display='none'}

let confirmCb=null;
function showConfirm(title,message,confirmLabel,cb){
  confirmCb=cb;
  const m=document.getElementById('confirmModal');
  if(!m)return;
  m.innerHTML=`<div class="stats-card">
    <div class="stats-card__head"><h3>${esc(title)}</h3><button class="act" onclick="closeConfirm()">✕</button></div>
    <p class="stats-note">${esc(message)}</p>
    <div style="display:flex;gap:8px;margin-top:14px">
      <button class="act" style="flex:1" onclick="closeConfirm()">Cancel</button>
      <button class="act act--danger" style="flex:1" onclick="confirmYes()">${esc(confirmLabel)}</button>
    </div>
  </div>`;
  m.style.display='block';
}
function closeConfirm(){const m=document.getElementById('confirmModal');if(m)m.style.display='none';confirmCb=null}
function confirmYes(){const cb=confirmCb;closeConfirm();if(cb)cb()}

function leaveSession(id){
  const s=state.liveSessions.find(x=>x.id===id);
  if(!s||!s.my_session_player_id){toast('You are not in this session');return}
  showConfirm('Leave session','If you leave, you will lose all stats recorded for this session. This cannot be undone.','Leave',async()=>{
    const r=await api(`/api/session-players/${s.my_session_player_id}/leave`,{method:'POST',body:{}});
    if(r.ok){toast('Left session');closeSessionCard();refreshMap();}
    else toast(r.message||'Could not leave session');
  });
}

async function joinSession(id){
  const s=state.liveSessions.find(x=>x.id===id);
  if(!s)return;
  if(!s.my_player_id){toast('No player profile in this circle yet');return}
  const r=await api(`/api/sessions/${id}/players`,{method:'POST',body:{player_ids:[s.my_player_id]}});
  if(r.ok){toast('Joined '+s.name+' ✦');closeSessionCard();refreshMap();}
  else toast(r.message||'Could not join');
}

function render(){
  const nodesEl=document.getElementById('nodes');nodesEl.replaceChildren();
  const linksEl=document.getElementById('links');linksEl.replaceChildren();
  const focusId=state.focusId||state.personalId;
  computeAttachPositions();
  for(const n of state.nodes){
    const p=state.positions[n.id]||{x:0,y:0};
    nodesEl.appendChild(buildNode(n,p,n.id===focusId));
  }
  state.focusedAttachments.forEach(a=>{
    const p={x:a.x,y:a.y};
    if(a.type==='session')nodesEl.appendChild(buildSessionNode(a,p));
    else nodesEl.appendChild(buildMemberNode(a,p));
  });
  drawLinks(linksEl,focusId);
  updateHud(focusId);
}

function drawLinks(linksEl,focusId,offset){
  linksEl.replaceChildren();
  offset=offset||{};
  const O=2400;
  const LINK_COLORS=['#39d0ff','#a06bff','#8b5cf6','#00c764','#3b82f6','#ff8a5c'];
  let seq=0;
  const pos=id=>{
    const p=state.positions[id];
    if(!p)return null;
    const o=offset[id];
    return o?{x:p.x+o.x,y:p.y+o.y}:p;
  };
  const add=(a,b,soft)=>{
    const x1=a.x+O,y1=a.y+O,x2=b.x+O,y2=b.y+O;
    const col=LINK_COLORS[seq++%LINK_COLORS.length];
    const glow=document.createElementNS('http://www.w3.org/2000/svg','line');
    glow.setAttribute('x1',x1);glow.setAttribute('y1',y1);glow.setAttribute('x2',x2);glow.setAttribute('y2',y2);
    glow.style.stroke=col;glow.style.strokeWidth=soft?'3':'5';glow.style.opacity=soft?'0.10':'0.18';glow.style.strokeLinecap='round';glow.style.fill='none';
    linksEl.appendChild(glow);
    const line=document.createElementNS('http://www.w3.org/2000/svg','line');
    line.setAttribute('x1',x1);line.setAttribute('y1',y1);line.setAttribute('x2',x2);line.setAttribute('y2',y2);
    line.style.stroke=col;line.style.strokeWidth=soft?'1':'1.6';line.style.opacity=soft?'0.4':'0.85';line.style.strokeLinecap='round';line.style.fill='none';
    linksEl.appendChild(line);
  };
  const linked=new Set();
  const connect=(a,b,soft)=>{if(a==null||b==null)return;const key=[Math.min(a,b),Math.max(a,b)].join('-');if(linked.has(key))return;linked.add(key);const pa=pos(a),pb=pos(b);if(pa&&pb)add(pa,pb,soft)};
  // personal circle -> each joined circle
  state.nodes.forEach(n=>{if(n.kind==='joined')connect(state.personalId,n.id,true)});
  // every circle I belong to -> each member's personal circle (if on the map)
  state.nodes.forEach(n=>{
    if((n.kind==='mine'||n.kind==='joined')&&n.members){
      n.members.forEach(m=>{
        if(m.personal_circle_id&&state.nodeMap[m.personal_circle_id]&&m.personal_circle_id!==n.id){
          connect(n.id,m.personal_circle_id,false);
        }
      });
    }
  });
  // explicit connection pairs returned by the API (approved joins)
  (state.connections||[]).forEach(c=>connect(c.a,c.b,false));
  // connectors from the focused circle to each orbiting item
  const focusedNode=state.nodeMap[focusId];
  if(focusedNode){
    const fc=pos(focusedNode.id);
    if(fc){
      const g=document.createElementNS('http://www.w3.org/2000/svg','g');
      g.setAttribute('id','attachGroup');
      const coreR=coreSize(focusedNode,true).px/2;
      const fo=offset[focusedNode.id]||{x:0,y:0};
      state.focusedAttachments.forEach(a=>{
        const ap={x:a.x+fo.x,y:a.y+fo.y};
        attachConnector(g,fc.x,fc.y,ap,coreR,O,a.type==='member'?'link--member':'link--session');
      });
      linksEl.appendChild(g);
    }
  }
}

function attachConnector(g,cx,cy,a,coreR,O,cls){
  const len=Math.hypot(a.x-cx,a.y-cy)||1;
  const ux=(a.x-cx)/len,uy=(a.y-cy)/len;
  const itemR=23;
  const sx=cx+ux*coreR+O, sy=cy+uy*coreR+O;
  const ex=a.x-ux*itemR+O, ey=a.y-uy*itemR+O;
  const line=document.createElementNS('http://www.w3.org/2000/svg','line');
  line.setAttribute('x1',sx.toFixed(1));line.setAttribute('y1',sy.toFixed(1));
  line.setAttribute('x2',ex.toFixed(1));line.setAttribute('y2',ey.toFixed(1));
  line.setAttribute('class',cls);
  g.appendChild(line);
}

function onItemPointerDown(e,key,el){
  if(e.button!==undefined&&e.button!==0)return;
  e.stopPropagation();
  const item=state.focusedAttachments.find(x=>x.key===key);
  if(!item)return;
  const focusId=state.focusId||state.personalId;
  const center=state.positions[focusId]||{x:0,y:0};
  const R=item.r||131;
  const onMove=ev=>{
    const w=screenToWorld(ev.clientX,ev.clientY);
    const ang=Math.atan2(w.y-center.y,w.x-center.x);
    item.a=ang;item.x=center.x+Math.cos(ang)*R;item.y=center.y+Math.sin(ang)*R;
    state.orbitAngles[key]=ang;
    el.style.left=item.x+'px';el.style.top=item.y+'px';
    drawLinks(document.getElementById('links'),focusId);
  };
  const onUp=ev=>{
    window.removeEventListener('pointermove',onMove);window.removeEventListener('pointerup',onUp);window.removeEventListener('pointercancel',onUp);
  };
  window.addEventListener('pointermove',onMove);window.addEventListener('pointerup',onUp);window.addEventListener('pointercancel',onUp);
}

function moveAttach(dx,dy){
  state.focusedAttachments.forEach(a=>{
    const el=document.querySelector(`[data-key="${a.key}"]`);
    if(el){el.style.left=(a.x+dx)+'px';el.style.top=(a.y+dy)+'px';}
  });
}

function updateHud(focusId){
  const n=state.nodeMap[focusId];
  const name=document.getElementById('hudName');
  if(name)name.innerHTML=n?esc(n.name)+'<small>FOCUSED CIRCLE</small>':'—';
  updateStatCards(focusId);
}

function updateStatCards(focusId){
  const n=state.nodeMap[focusId];
  const score=n?(n.network_score||0):0;
  const num=document.getElementById('scScore');
  if(num)num.textContent=n?score:'—';
  const ring=document.getElementById('scRing');
  if(ring)ring.style.setProperty('--p',score);
  const name=document.getElementById('scName');
  if(name)name.textContent=n?n.name:'FOCUSED CIRCLE';
}

/* ── view transform ─────────────────────────────────────── */
let viewLoaded=false;
let persistTimer=null;
function schedulePersistView(){clearTimeout(persistTimer);persistTimer=setTimeout(persistView,250)}
function persistView(){try{localStorage.setItem('courtly-map-view',JSON.stringify({scale:state.view.scale,focusId:state.focusId}))}catch(e){}}
function restoreView(){try{const raw=localStorage.getItem('courtly-map-view');if(!raw)return null;const v=JSON.parse(raw);return{scale:clamp(Number(v.scale)||1,0.35,3),focusId:v.focusId||null}}catch(e){return null}}
function applyView(){document.getElementById('world').style.transform=`translate(${state.view.x}px, ${state.view.y}px) scale(${state.view.scale})`;if(viewLoaded)schedulePersistView()}
function screenToWorld(sx,sy){return{x:(sx-state.view.x)/state.view.scale,y:(sy-state.view.y)/state.view.scale}}
function centerView(){
  const map=document.getElementById('map');
  state.view={x:map.clientWidth/2,y:map.clientHeight/2,scale:1};
  applyView();
}
let viewAnim=null;
function animateView(tx,ty,ts,dur=500){
  const s={x:state.view.x,y:state.view.y,scale:state.view.scale};const t0=performance.now();
  cancelAnimationFrame(viewAnim);
  const ease=t=>1-Math.pow(1-t,3);
  function step(now){const k=Math.min(1,(now-t0)/dur);const e=ease(k);
    state.view.x=s.x+(tx-s.x)*e;state.view.y=s.y+(ty-s.y)*e;state.view.scale=s.scale+(ts-s.scale)*e;
    applyView();if(k<1)viewAnim=requestAnimationFrame(step)}
  viewAnim=requestAnimationFrame(step);
}
function focusOn(id){
  if(!state.nodeMap[id]||!state.positions[id])return;
  state.focusId=id;render();
  const map=document.getElementById('map');const p=state.positions[id];
  animateView(map.clientWidth/2-p.x*state.view.scale,map.clientHeight/2-p.y*state.view.scale,Math.max(state.view.scale,1));
}

/* ── (bottom sheet removed — all actions live in the click menu) ── */

/* ── actions ────────────────────────────────────────────── */
function closePopover(){document.getElementById('popover').style.display='none'}

function openPopover(nodeEl,node){
  const pop=document.getElementById('popover');
  const isMember=node.kind==='mine'||node.kind==='joined';
  let acts='';
  if(isMember)acts+=`<button class="act act--primary" onclick="startMeetup(${node.id})">▶ Start meetup</button>`;
  if(!isMember&&node.kind==='discoverable'){
    const pending=state.pending.some(p=>p.circle_id===node.id);
    acts+=pending?`<button class="act" disabled>⏳ Request pending</button>`:`<button class="act act--primary" onclick="askJoin(${node.id})">➕ Ask to join</button>`;
  }
  if(node.is_admin){
    acts+=`<button class="act" onclick="toggleInvite()">Invite by email</button>`;
    acts+=`<label class="switch"><input type="checkbox" ${node.visibility!=='PRIVATE'?'checked':''} onchange="toggleVisibility(${node.id})"><span class="switch__track"></span><em>${node.visibility==='PRIVATE'?'🔒 Private':'🌐 Public'}</em></label>`;
  }
  if(isMember&&!node.is_admin)acts+=`<button class="act" onclick="leaveCircle(${node.id})">Disconnect</button>`;
  let reqHtml='';
  if(node.is_admin&&node.pending_requests&&node.pending_requests.length){
    reqHtml='<div class="reqlist">'+node.pending_requests.map(r=>`<div class="req"><span class="n">${esc(r.name)}</span><button class="ok" onclick="decideRequest(${r.id},'approve')">Approve</button><button class="no" onclick="decideRequest(${r.id},'decline')">Decline</button></div>`).join('')+'</div>';
  }
  const qrHtml = node.is_admin && node.invite_code ? inviteQr(node) : '';
  const inviteRow = node.is_admin
    ? `<div class="join-row" id="inviteRow" style="display:none;margin-top:8px">
        <input id="inviteEmail" type="email" placeholder="friend@example.com" autocomplete="off" style="text-transform:none;letter-spacing:0">
        <button class="act act--primary" onclick="sendInvite(${node.id})">Send</button>
      </div>`
    : '';
  pop.innerHTML=`<h3>${esc(node.name)}</h3>${acts}${qrHtml}${reqHtml}${inviteRow}`;
  pop.style.display='block';
  const r=nodeEl.getBoundingClientRect();
  const pw=pop.offsetWidth,ph=pop.offsetHeight;
  let x=r.left+r.width/2-pw/2,y=r.top-ph-12;
  x=clamp(x,8,window.innerWidth-pw-8);if(y<60)y=r.bottom+12;
  y=clamp(y,8,window.innerHeight-ph-8);
  pop.style.left=x+'px';pop.style.top=y+'px';
}

async function askJoin(id){
  const r=await api(`/api/circles/${id}/request-join`,{method:'POST',body:{}});
  if(r.ok){toast('Join request sent ✦');closePopover();state.pending.push({circle_id:id});}
  else toast(r.message||'Could not send request');
}

async function startMeetup(id){
  const n=state.nodeMap[id];
  const r=await api('/api/sessions',{method:'POST',body:{name:(n?n.name+' Meetup':'Quick Meetup'),number_of_courts:1,circle_id:id}});
  if(r.ok&&r.data){toast('Meetup started ✦');setTimeout(()=>{window.location.href=BASE+'/sessions/'+r.data.id+'/live'},600)}
  else toast((r.message||'Could not start meetup'));
}

function closeLeaderboard(){document.getElementById('leaderModal').style.display='none'}

function closeManage(){document.getElementById('manageModal').style.display='none'}

function openManagePlayers(id){
  const n=state.nodeMap[id];
  const modal=document.getElementById('manageModal');
  modal.innerHTML=`<div class="stats-card"><div class="stats-card__head"><h3>Manage players — ${esc(n?n.name:'')}</h3><button class="act" onclick="closeManage()">✕</button></div><p class="p-sub">Loading…</p></div>`;
  modal.style.display='block';
  closePopover();
  api(`/api/players?circle_id=${id}`).then(r=>{
    if(!r.ok){modal.innerHTML=`<div class="stats-card"><div class="stats-card__head"><h3>Manage players</h3><button class="act" onclick="closeManage()">✕</button></div><p class="p-sub">${esc(r.message||'Could not load players')}</p></div>`;return}
    const rows=(r.data||[]).map(p=>`<div class="req"><input class="manage-name" value="${esc(p.name)}" onchange="savePlayerName(${p.id},this.value)"><span class="manage-gender">${p.gender==='MALE'?'♂':p.gender==='FEMALE'?'♀':'–'}</span><span class="manage-rating">${Math.round(p.rating)}</span><button class="no" onclick="deleteCirclePlayer(${p.id},${id})">✕</button></div>`).join('')||'<div class="req"><span class="n">No players yet.</span></div>';
    modal.innerHTML=`<div class="stats-card"><div class="stats-card__head"><h3>Manage players — ${esc(n?n.name:'')}</h3><button class="act" onclick="closeManage()">✕</button></div><div class="reqlist" style="max-height:320px;overflow:auto">${rows}</div></div>`;
  });
}

async function savePlayerName(id,name){
  const r=await api(`/api/players/${id}`,{method:'PATCH',body:{name:name}});
  if(r.ok)toast('Player updated');else toast(r.message||'Could not rename');
}

async function deleteCirclePlayer(id,circleId){
  if(!confirm('Delete this player?'))return;
  const r=await api(`/api/players/${id}`,{method:'DELETE'});
  if(r.ok){toast('Player deleted');refreshMap();openManagePlayers(circleId)}else toast(r.message||'Could not delete');
}
function showLeaderboard(id){
  const n=state.nodeMap[id];
  if(!n)return;
  const modal=document.getElementById('leaderModal');
  modal.innerHTML=`<div class="stats-card"><div class="stats-card__head"><h3>${esc(n.name)} — Rankings</h3><button class="act" onclick="closeLeaderboard()">✕</button></div><p class="p-sub">Loading…</p></div>`;
  modal.style.display='block';
  closePopover();
  api(`/api/circles/${id}/leaderboard`).then(r=>{
    if(!r.ok){modal.innerHTML=`<div class="stats-card"><div class="stats-card__head"><h3>${esc(n.name)} — Rankings</h3><button class="act" onclick="closeLeaderboard()">✕</button></div><p class="p-sub">${esc(r.message||'Could not load rankings')}</p></div>`;return}
    const rows=(r.data||[]).map((p,i)=>`<div class="req"><span class="n"><b style="color:var(--muted)">${i+1}.</b> ${esc(p.name)} <span style="color:var(--accent2)">${esc(p.tier)}</span></span><span style="color:var(--muted);font-size:.8rem">${Math.round(p.rating)} · ${p.wins}W/${p.losses}L</span></div>`).join('')||'<div class="req"><span class="n">No ranked players yet.</span></div>';
    modal.innerHTML=`<div class="stats-card"><div class="stats-card__head"><h3>${esc(n.name)} — Rankings</h3><button class="act" onclick="closeLeaderboard()">✕</button></div><div class="reqlist" style="max-height:300px;overflow:auto">${rows}</div></div>`;
  });
}

async function leaveCircle(id){
  const r=await api(`/api/circles/${id}/leave`,{method:'POST',body:{}});
  if(r.ok){toast('Disconnected');closePopover();state.nodes=state.nodes.filter(n=>n.id!==id);delete state.nodeMap[id];layout();render();refreshMap()}
  else toast(r.message||'Could not leave');
}

async function toggleVisibility(id){
  const n=state.nodeMap[id];
  const r=await api(`/api/circles/${id}`,{method:'PATCH',body:{visibility:n.visibility==='PUBLIC'?'PRIVATE':'PUBLIC'}});
  if(r.ok){toast('Visibility updated');closePopover();refreshMap()}
  else toast(r.message||'Could not update');
}

function copyCode(id){
  const n=state.nodeMap[id];
  if(!n||!n.invite_code){toast('No invite code');return}
  const fallback=()=>toast('Invite code: '+n.invite_code);
  if(navigator.clipboard&&navigator.clipboard.writeText){
    navigator.clipboard.writeText(n.invite_code).then(()=>toast('Invite code copied ✦'),fallback);
  }else fallback();
}

function inviteQr(node){
  const url=location.origin+BASE+'/circles?join='+encodeURIComponent(node.invite_code);
  const img='https://api.qrserver.com/v1/create-qr-code/?size=102x102&margin=8&ecc=H&data='+encodeURIComponent(url);
  const logo=BASE+'/assets/courtly-mark-transparent.png';
  return `<div class="qr"><div class="qr__box"><img class="qr__img" src="${img}" alt="Invite QR code"><img class="qr__logo" src="${logo}" alt=""></div><div class="qr__code">${esc(node.invite_code)}</div></div>`;
}

function recenter(){
  if(!state.personalId)return;
  state.focusId=state.personalId;
  computeAttachPositions();
  render();
  const p=state.positions[state.personalId]||{x:0,y:0};
  const mapEl=document.getElementById('map');
  state.view={x:mapEl.clientWidth/2-p.x,y:mapEl.clientHeight/2-p.y,scale:1};
  applyView();
}

async function handleJoinParam(){
  const code=new URLSearchParams(location.search).get('join');
  if(!code)return;
  history.replaceState(null,'',location.pathname);
  const r=await api('/api/circles/join',{method:'POST',body:{invite_code:code}});
  if(r.ok){toast('Joined '+((r.data&&r.data.circle&&r.data.circle.name)||'circle')+' ✦');refreshMap();}
  else toast(r.message||'Invalid invite code.');
}

function toggleInvite(){
  const row=document.getElementById('inviteRow');
  if(!row)return;
  const show=row.style.display==='none'||!row.style.display;
  row.style.display=show?'flex':'none';
  if(show){const input=document.getElementById('inviteEmail');if(input)setTimeout(()=>input.focus(),0)}
}

async function sendInvite(id){
  const input=document.getElementById('inviteEmail');
  if(!input)return;
  const email=input.value.trim();
  if(!email){toast('Enter an email address');return}
  const r=await api(`/api/circles/${id}/invite`,{method:'POST',body:{email}});
  if(r.ok){
    const d=r.data||{};
    if(d.status==='failed'){toast(d.message||'Could not send the invite email.');return}
    toast(d.message||'Invite sent');
    if(d.status==='joined'){refreshMap();closePopover()}
    else if(input){input.value='';const row=document.getElementById('inviteRow');if(row)row.style.display='none'}
  }else{
    toast(r.message||'Could not send invite');
  }
}

async function decideRequest(id,action){
  const r=await api(`/api/circle-join-requests/${id}/${action}`,{method:'POST',body:{}});
  if(r.ok){toast(action==='approve'?'Approved ✦':'Declined');refreshMap()}
  else toast(r.message||'Failed');
}

/* ── notifications ──────────────────────────────────────── */
function incomingRequests(){
  const out=[];
  state.nodes.forEach(n=>{
    if(n.is_admin&&n.pending_requests&&n.pending_requests.length){
      n.pending_requests.forEach(r=>out.push(Object.assign({circle_id:n.id,circle_name:n.name},r)));
    }
  });
  return out;
}
function updateNotifBadge(){
  const b=document.getElementById('notifBadge');
  if(!b)return;
  const total=incomingRequests().length+state.unreadNotifs;
  b.style.display=total>0?'flex':'none';
  b.textContent=total>99?'99+':String(total);
}
function applyNotifications(list){
  list=list||[];
  state.notifications=list;
  let seen=[];
  try{seen=JSON.parse(localStorage.getItem('courtly-seen-notifs')||'[]')}catch(e){seen=[]}
  const fresh=list.filter(n=>seen.indexOf(n.id)===-1);
  fresh.forEach(n=>{
    toast(n.status==='APPROVED'
      ? `Your request to join ${n.circle_name} was approved ✦`
      : `Your request to join ${n.circle_name} was declined`);
  });
  if(fresh.length){
    try{localStorage.setItem('courtly-seen-notifs',JSON.stringify(list.map(n=>n.id)))}catch(e){}
    state.unreadNotifs+=fresh.length;
  }
  updateNotifBadge();
}
function toggleInbox(){
  const box=document.getElementById('inbox');
  if(!box)return;
  const show=box.style.display==='none'||!box.style.display;
  if(show){renderInbox();box.style.display='block';state.unreadNotifs=0;updateNotifBadge();closePopover()}
  else box.style.display='none';
}
function renderInbox(){
  const box=document.getElementById('inbox');
  if(!box)return;
  const inc=incomingRequests();
  const items=[];
  inc.forEach(r=>{
    items.push(`<div class="inbox-item">🔔 <b>${esc(r.name)}</b> wants to join <b>${esc(r.circle_name)}</b><div style="margin-top:6px;display:flex;gap:8px"><button class="ok" onclick="decideRequest(${r.id},'approve')">Approve</button><button class="no" onclick="decideRequest(${r.id},'decline')">Decline</button></div></div>`);
  });
  (state.notifications||[]).forEach(n=>{
    items.push(n.status==='APPROVED'
      ? `<div class="inbox-item"><span class="tag-ok">✔ Approved</span> — your request to join <b>${esc(n.circle_name)}</b> was accepted. You're now connected.</div>`
      : `<div class="inbox-item"><span class="tag-no">✕ Declined</span> — your request to join <b>${esc(n.circle_name)}</b> was declined.</div>`);
  });
  box.innerHTML=`<h3>Notifications <button class="x" onclick="toggleInbox()">✕</button></h3>`+(items.length?items.join(''):'<div class="inbox-empty">No notifications.</div>');
}

async function visitCircle(id){
  if(state.nodeMap[id]){focusOn(id);return}
  const r=await api(`/api/circles/${id}`);
  if(!r.ok){toast(r.status===403?'That circle is private':'Could not open circle');return}
  const node=Object.assign({kind:'visiting'},r.data);
  state.nodes.push(node);state.nodeMap[id]=node;layout();render();focusOn(id);
}

function closeStats(){document.getElementById('statsModal').style.display='none'}

async function openPlayerStats(playerId){
  const modal=document.getElementById('statsModal');
  modal.innerHTML=`<div class="stats-card"><div class="stats-card__head"><h3>Loading…</h3><button class="act" onclick="closeStats()">✕</button></div></div>`;
  modal.style.display='block';
  const r=await api(`/api/players/${playerId}/stats`);
  const s=(r.ok&&r.data)?r.data:null;
  if(!s){modal.innerHTML=`<div class="stats-card"><div class="stats-card__head"><h3>Stats unavailable</h3><button class="act" onclick="closeStats()">✕</button></div></div>`;return}
  const sum=s.summary||{};
  const pct=v=>v==null?'—':Math.round(v)+'%';
  const tm=sum.most_common_teammate;const to=sum.toughest_opponent;
  modal.innerHTML=`<div class="stats-card">
    <div class="stats-card__head"><h3>${esc(s.name)}</h3><button class="act" onclick="closeStats()">✕</button></div>
    <div class="stats-grid">
      <div class="stat"><b>${Math.round(sum.rating||0)}</b><span>Rating</span></div>
      <div class="stat"><b>${sum.wins||0}–${sum.losses||0}</b><span>Record</span></div>
      <div class="stat"><b>${pct(sum.win_percentage)}</b><span>Win %</span></div>
      <div class="stat"><b>${sum.peak_rating!=null?Math.round(sum.peak_rating):'—'}</b><span>Peak</span></div>
      <div class="stat"><b>${sum.longest_win_streak||0}</b><span>Win streak</span></div>
      <div class="stat"><b>${sum.sessions_attended||0}</b><span>Sessions</span></div>
      <div class="stat"><b>${pct(sum.upset_rate)}</b><span>Upset rate</span></div>
      <div class="stat"><b>${pct(sum.clutch_rate)}</b><span>Clutch rate</span></div>
    </div>
    ${tm?`<p class="stats-note">Top teammate: <b>${esc(tm.name)}</b> (${tm.games} games)</p>`:''}
    ${to?`<p class="stats-note">Toughest opponent: <b>${esc(to.name)}</b> (${to.games} games)</p>`:''}
  </div>`;
}

/* ── data ───────────────────────────────────────────────── */
async function loadMap(){
  const r=await api('/api/circles/map');
  if(!r.ok){toast(r.message||'Could not load the map');return}
  const d=r.data||{nodes:[],pending_requests:[],personal_circle_id:null};
  state.personalId=d.personal_circle_id;
  state.pending=d.pending_requests||[];
  state.liveSessions=d.live_sessions||[];
  state.nodes=d.nodes||[];
  state.nodeMap={};state.nodes.forEach(n=>state.nodeMap[n.id]=n);
  state.connections=d.connections||[];
  applyNotifications(d.notifications||[]);
  const saved=restoreView();
  if(!saved){
    state.focusId=state.personalId;
    layout();render();
    centerView();
    if(state.personalId)setTimeout(()=>focusOn(state.personalId),60);
  }else{
    state.focusId=(saved.focusId&&state.nodeMap[saved.focusId])?saved.focusId:state.personalId;
    layout();render();
    const focusP=state.positions[state.focusId];
    if(focusP){
      const mapEl=document.getElementById('map');
      state.view={x:mapEl.clientWidth/2-focusP.x*saved.scale,y:mapEl.clientHeight/2-focusP.y*saved.scale,scale:saved.scale};
      applyView();
    }else{
      centerView();
    }
  }
  viewLoaded=true;}
async function refreshMap(){
  const r=await api('/api/circles/map');
  if(!r.ok)return;
  const d=r.data||{nodes:[],pending_requests:[],personal_circle_id:state.personalId};
  const keepFocus=state.focusId;
  state.nodes=d.nodes||[];state.pending=d.pending_requests||[];state.liveSessions=d.live_sessions||[];
  state.nodeMap={};state.nodes.forEach(n=>state.nodeMap[n.id]=n);
  state.connections=d.connections||[];
  applyNotifications(d.notifications||[]);
  state.focusId=keepFocus&&state.nodeMap[keepFocus]?keepFocus:(state.personalId||null);
  layout();render();
}

/* ── input: pan / zoom / node drag ──────────────────────── */
const map=document.getElementById('map');

let nodePressTimer=null;
let nodeLongPressed=false;
function onNodePointerDown(e,n,el){
  if(e.target.closest('.member')){e.stopPropagation();return}
  if(e.button!==undefined&&e.button!==0)return;
  e.stopPropagation();
  const start={sx:e.clientX,sy:e.clientY};
  let moved=false;
  const inner=el.querySelector('.cnode__inner');
  const p=state.positions[n.id]||{x:0,y:0};
  if(nodePressTimer){clearTimeout(nodePressTimer);nodePressTimer=null}
  nodeLongPressed=false;
  nodePressTimer=setTimeout(()=>{
    nodePressTimer=null;
    if(!moved){nodeLongPressed=true;openPopover(el,n);}
  },500);
  const onMove=ev=>{
    const dx=(ev.clientX-start.sx)/state.view.scale,dy=(ev.clientY-start.sy)/state.view.scale;
    if(Math.abs(dx)+Math.abs(dy)>6){if(!moved){moved=true;if(nodePressTimer){clearTimeout(nodePressTimer);nodePressTimer=null}}}
    if(moved){
      el.classList.remove('is-spring');
      inner.style.transform=`translate(-50%,-50%) translate(${dx}px,${dy}px)`;
      if(n.id===(state.focusId||state.personalId))moveAttach(dx,dy);
      drawLinks(document.getElementById('links'),state.focusId||state.personalId,{[n.id]:{x:dx,y:dy}});
    }
  };
  const onUp=ev=>{
    window.removeEventListener('pointermove',onMove);window.removeEventListener('pointerup',onUp);window.removeEventListener('pointercancel',onUp);
    if(nodePressTimer){clearTimeout(nodePressTimer);nodePressTimer=null}
    if(nodeLongPressed){nodeLongPressed=false;return}
    if(moved){
      const w=screenToWorld(ev.clientX,ev.clientY);
      const target=findNodeAt(w.x,w.y,90);
      if(target&&target.id!==n.id){
        el.classList.add('is-spring');inner.style.transform='';
        if(n.kind==='mine'&&target.kind==='discoverable'){askJoin(target.id)}
        else if(target.kind==='discoverable'||target.kind==='joined'||target.kind==='visiting'){visitCircle(target.id)}
      }else{
        state.nodePos[n.id]={x:w.x,y:w.y};
        layout();render();
      }
    }else{
      el.classList.add('is-spring');inner.style.transform='';
      const now=Date.now();
      if(n.id===state.lastClickId && now-state.lastClickTime<350){
        state.lastClickId=null;state.lastClickTime=0;
        state.focusId=n.id;
        computeAttachPositions();
        render();
      }else{
        state.lastClickId=n.id;state.lastClickTime=now;
        focusOn(n.id);
      }
    }
  };
  window.addEventListener('pointermove',onMove);window.addEventListener('pointerup',onUp);window.addEventListener('pointercancel',onUp);
}

function findNodeAt(wx,wy,thresh){
  let best=null,bd=thresh;
  for(const n of state.nodes){const p=state.positions[n.id];if(!p)continue;const d=Math.hypot(p.x-wx,p.y-wy);if(d<bd){bd=d;best=n}}
  return best;
}

map.addEventListener('pointerdown',e=>{
  if(e.target.closest('.cnode')||e.target.closest('.member-wrap')||e.target.closest('.snode')||e.target.closest('#popover')||e.target.closest('#joinpanel')||e.target.closest('#beacon'))return;
  map.setPointerCapture(e.pointerId);
  state.pointers.set(e.pointerId,{x:e.clientX,y:e.clientY});
  if(state.pointers.size===1){
    state.drag={type:'pan',sx:e.clientX,sy:e.clientY,lastX:e.clientX,lastY:e.clientY,t:performance.now(),vx:0,vy:0};
    map.classList.add('dragging');
  }else if(state.pointers.size===2){
    state.drag={type:'pinch',dist:pinchDist(),scale:state.view.scale};
  }
});
map.addEventListener('pointermove',e=>{
  if(!state.pointers.has(e.pointerId))return;
  state.pointers.set(e.pointerId,{x:e.clientX,y:e.clientY});
  if(state.drag&&state.drag.type==='pan'&&state.pointers.size===1){
    const dx=e.clientX-state.drag.lastX,dy=e.clientY-state.drag.lastY;
    const now=performance.now(),dt=Math.max(1,now-state.drag.t);
    state.drag.vx=dx/dt;state.drag.vy=dy/dt;state.drag.t=now;
    state.view.x+=dx;state.view.y+=dy;state.drag.lastX=e.clientX;state.drag.lastY=e.clientY;
    applyView();
  }else if(state.drag&&state.drag.type==='pinch'&&state.pointers.size===2){
    const d=pinchDist();
    if(state.drag.dist>0){
      const factor=d/state.drag.dist;
      const scale=clamp(state.drag.scale*factor,0.35,3);
      const mid=pinchMid();
      const before=screenToWorld(mid.x,mid.y);
      state.view.scale=scale;state.view.x=mid.x-before.x*scale;state.view.y=mid.y-before.y*scale;
      applyView();
    }
  }
});
function endPointer(e){
  if(!state.pointers.has(e.pointerId))return;
  state.pointers.delete(e.pointerId);
  if(state.pointers.size===0&&state.drag){
    if(state.drag.type==='pan')momentum(state.drag.vx,state.drag.vy);
    state.drag=null;map.classList.remove('dragging');
  }else if(state.pointers.size<2){state.drag=null}
}
map.addEventListener('pointerup',endPointer);
map.addEventListener('pointercancel',endPointer);

function pinchDist(){const a=[...state.pointers.values()];if(a.length<2)return 0;return Math.hypot(a[0].x-a[1].x,a[0].y-a[1].y)}
function pinchMid(){const a=[...state.pointers.values()];return{x:(a[0].x+a[1].x)/2,y:(a[0].y+a[1].y)/2}}

let momAnim=null;
function momentum(vx,vy){
  const mapEl=map;
  cancelAnimationFrame(momAnim);
  function step(){
    state.view.x+=vx*16;state.view.y+=vy*16;applyView();
    vx*=0.93;vy*=0.93;
    if(Math.abs(vx)>0.05||Math.abs(vy)>0.05)momAnim=requestAnimationFrame(step);
  }
  momAnim=requestAnimationFrame(step);
}

map.addEventListener('wheel',e=>{
  e.preventDefault();
  const rect=map.getBoundingClientRect();
  const sx=e.clientX-rect.left,sy=e.clientY-rect.top;
  const before=screenToWorld(sx,sy);
  const scale=clamp(state.view.scale*Math.exp(-e.deltaY*0.0012),0.35,3);
  state.view.scale=scale;state.view.x=sx-before.x*scale;state.view.y=sy-before.y*scale;
  applyView();
},{passive:false});

/* member click → their circle */
document.addEventListener('click',e=>{
  const m=e.target.closest('.member');
  if(!m)return;
  e.stopPropagation();
  const pid=parseInt(m.dataset.player,10);
  if(pid){openPlayerStats(pid);return}
  const id=parseInt(m.dataset.pcircle,10);
  if(id)visitCircle(id);else toast('This member has no circle');
});

/* popover click-away */
document.addEventListener('pointerdown',e=>{
  if(!e.target.closest('#popover')&&!e.target.closest('.cnode'))closePopover();
});

/* beacon + join panel */
document.getElementById('beacon').addEventListener('click',()=>{
  const p=document.getElementById('joinpanel');
  p.style.display=p.style.display==='block'?'none':'block';
});
document.getElementById('joinGo').addEventListener('click',async()=>{
  const input=document.getElementById('joinCode');const err=document.getElementById('joinErr');
  err.style.display='none';
  const code=input.value.trim();
  if(!code){err.textContent='Enter an invite code.';err.style.display='block';return}
  const r=await api('/api/circles/join',{method:'POST',body:{invite_code:code}});
  if(r.ok){toast('Joined '+((r.data&&r.data.circle&&r.data.circle.name)||'circle')+' ✦');input.value='';document.getElementById('joinpanel').style.display='none';refreshMap()}
  else{err.textContent=r.message||'Invalid invite code.';err.style.display='block'}
});
document.getElementById('joinCode').addEventListener('keydown',e=>{if(e.key==='Enter')document.getElementById('joinGo').click()});

/* theme */
var COURT_THEMES=['dark','blue','cyber','emerald','light'];
var COURT_GLYPHS={dark:'☾',blue:'✦',cyber:'✧',emerald:'❖',light:'☀'};
var COURT_LABELS={dark:'Dark',blue:'Blue',cyber:'Cyber',emerald:'Emerald',light:'Light'};
function courtlyCurrentTheme(){var t=document.documentElement.getAttribute('data-theme');return COURT_THEMES.indexOf(t)!==-1?t:'dark'}
function updateThemeIcon(){var b=document.getElementById('btnTheme');if(!b)return;var t=courtlyCurrentTheme();b.textContent=COURT_GLYPHS[t];b.title='Theme: '+COURT_LABELS[t]+' — click to switch';b.setAttribute('aria-label',b.title)}
function toggleCourtlyTheme(){var next=COURT_THEMES[(COURT_THEMES.indexOf(courtlyCurrentTheme())+1)%COURT_THEMES.length];if(next==='dark')document.documentElement.removeAttribute('data-theme');else document.documentElement.setAttribute('data-theme',next);try{localStorage.setItem('courtly-theme',next)}catch(e){}updateThemeIcon()}
document.getElementById('btnTheme').addEventListener('click',toggleCourtlyTheme);
document.getElementById('notifBell').addEventListener('click',toggleInbox);
document.getElementById('recenter').addEventListener('click',recenter);
document.getElementById('managePlayersBtn').addEventListener('click',()=>{
  const id=state.focusId||state.personalId;
  const n=state.nodeMap[id];
  if(!n||!n.is_admin){toast('You can only manage players in your own circle.');return}
  openManagePlayers(id);
});
(function(){try{var s=localStorage.getItem('courtly-theme');if(COURT_THEMES.indexOf(s)!==-1&&s!=='dark')document.documentElement.setAttribute('data-theme',s)}catch(e){}updateThemeIcon()})();

window.addEventListener('resize',()=>{if(!state.drag)centerView()});

/* boot */
(async()=>{ await loadMap(); await handleJoinParam(); })();
setInterval(refreshMap,15000);
</script>
</body>
</html>
