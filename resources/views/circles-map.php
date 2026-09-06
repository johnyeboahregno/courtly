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
  --glow1:rgba(60,60,160,.28);
  --glow2:rgba(120,50,160,.18);
}
[data-theme="blue"]{--bg:#0f172a;--panel:rgba(15,23,42,.92);--stroke:rgba(147,197,253,.28);--text:#e5e7eb;--muted:#b7c1d1;--accent:#3b82f6;--accent2:#60a5fa;--cyan:#93c5fd;--team1:#3b82f6;--team2:#10b981;--gold:#fbbf24;--glow1:rgba(59,130,246,.22);--glow2:rgba(147,197,253,.14)}
[data-theme="cyber"]{--bg:#04151f;--panel:rgba(6,30,40,.88);--stroke:rgba(0,229,255,.22);--text:#d8f7ff;--muted:#7fb8c8;--accent:#00e5ff;--accent2:#00ff9d;--cyan:#7df3ff;--team1:#00e5ff;--team2:#00ff9d;--gold:#ffd166;--glow1:rgba(0,229,255,.20);--glow2:rgba(0,255,157,.12)}
[data-theme="crimson"]{--bg:#1a060b;--panel:rgba(32,9,15,.88);--stroke:rgba(255,45,85,.24);--text:#ffe9ec;--muted:#c9949e;--accent:#ff2d55;--accent2:#ff8a5c;--cyan:#ff7a9c;--team1:#ff2d55;--team2:#00e0a0;--gold:#ffd166;--glow1:rgba(255,45,85,.20);--glow2:rgba(255,138,92,.12)}
[data-theme="emerald"]{--bg:#061711;--panel:rgba(8,28,20,.88);--stroke:rgba(0,199,100,.22);--text:#e1fff0;--muted:#8fc7aa;--accent:#00c764;--accent2:#22d3ee;--cyan:#22d3ee;--team1:#00c764;--team2:#22d3ee;--gold:#ffd166;--glow1:rgba(0,199,100,.20);--glow2:rgba(34,211,238,.12)}
[data-theme="light"]{--bg:#f5f5fa;--panel:rgba(255,255,255,.95);--stroke:rgba(15,23,42,.15);--text:#0f172a;--muted:#475569;--accent:#6d4fff;--accent2:#d61e7b;--cyan:#0e7490;--team1:#2563eb;--team2:#059669;--gold:#b45309;--glow1:rgba(99,102,241,.10);--glow2:rgba(217,70,239,.06)}
[data-theme="light"] .map-header{background:linear-gradient(180deg,rgba(255,255,255,.94),rgba(255,255,255,.6))}
[data-theme="light"] #grid{background-image:linear-gradient(rgba(15,23,42,.06) 1px,transparent 1px),linear-gradient(90deg,rgba(15,23,42,.06) 1px,transparent 1px)}
[data-theme="light"] .link{stroke:rgba(15,23,42,.30)}
[data-theme="light"] .link--soft{stroke:rgba(15,23,42,.16)}
*{box-sizing:border-box}
html,body{height:100%;margin:0}
body{background:var(--bg);color:var(--text);font-family:"SF Mono","JetBrains Mono","Fira Code",monospace;overflow:hidden}
button{font-family:inherit}

/* ── Header ─────────────────────────────────────────────── */
.map-header{position:fixed;top:0;left:0;right:0;min-height:56px;display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;padding:8px 16px;z-index:40;background:linear-gradient(180deg,rgba(8,10,30,.94),rgba(8,10,30,.55));backdrop-filter:blur(10px);pointer-events:none}
.map-header>*{pointer-events:auto}
.map-brand{display:flex;align-items:center;gap:10px;font-weight:800;font-size:1.05rem;letter-spacing:.01em;color:var(--text);text-decoration:none}
.map-brand img{width:30px;height:30px;object-fit:contain}
.map-brand b{color:var(--accent2)}
.map-nav{display:flex;gap:6px;flex-wrap:wrap;align-items:center}
.map-pill{display:inline-flex;align-items:center;font-size:.8rem;letter-spacing:.03em;padding:6px 13px;border-radius:999px;border:1px solid var(--stroke);color:var(--muted);background:transparent;text-decoration:none;font-weight:700;transition:border-color .15s,color .15s,background .15s}
.map-pill:hover{border-color:var(--accent);color:var(--text)}
.map-pill--active{border-color:var(--accent);color:#fff;background:linear-gradient(135deg,var(--accent),var(--accent2))}
.hud{display:flex;align-items:center;gap:14px}
.hud__score{text-align:center}
.hud__score .n{font-size:1.7rem;font-weight:900;line-height:1;color:#fff}
.hud__score .l{font-size:.6rem;letter-spacing:.14em;color:var(--muted)}
.hud__name{font-size:.95rem;font-weight:700}
.hud__name small{display:block;color:var(--muted);font-weight:400;font-size:.68rem;letter-spacing:.06em}
.hdr-btn{border:1px solid var(--stroke);background:var(--panel);color:var(--text);border-radius:999px;padding:8px 14px;cursor:pointer;font-size:.85rem;font-weight:700;backdrop-filter:blur(6px)}
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
.link{stroke:rgba(140,160,255,.42);stroke-width:1.6;fill:none;stroke-linecap:round;stroke-dasharray:1 8}
.link--soft{stroke:rgba(140,160,255,.22)}
#nodes{position:absolute;left:0;top:0}

/* ── Circle nodes ───────────────────────────────────────── */
.cnode{position:absolute;left:0;top:0;z-index:2}
.cnode__inner{position:absolute;left:0;top:0;width:0;height:0;touch-action:none;cursor:grab}
.cnode__inner:active{cursor:grabbing}
.cnode__wrap{position:absolute;left:0;top:0;transform:translate(-50%,-50%)}
.cnode__core{position:absolute;inset:0;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:900;color:#fff;box-shadow:0 8px 40px rgba(0,0,0,.45);background:radial-gradient(circle at 35% 30%,rgba(255,255,255,.28),transparent 45%),var(--c,var(--accent));border:1px solid rgba(255,255,255,.25)}
.cnode--discoverable .cnode__core{background:radial-gradient(circle at 35% 30%,rgba(255,255,255,.22),transparent 45%),#28316a;border-color:rgba(140,160,255,.35)}
.cnode--visiting .cnode__core{background:radial-gradient(circle at 35% 30%,rgba(255,255,255,.22),transparent 45%),#3a1f63;border-color:rgba(180,120,255,.4)}
.cnode--joined .cnode__core{background:radial-gradient(circle at 35% 30%,rgba(255,255,255,.22),transparent 45%),var(--c,var(--cyan))}
.cnode__label{position:absolute;left:0;top:0;font-weight:800;font-size:1.02rem;white-space:nowrap;text-shadow:0 2px 8px rgba(0,0,0,.7)}
.cnode__meta{position:absolute;left:0;top:0;font-size:.8rem;color:var(--muted);white-space:nowrap;text-shadow:0 1px 6px rgba(0,0,0,.5)}
.cnode__gauge{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);pointer-events:none}
.cnode__rings i{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);border-radius:50%;border:1px solid rgba(140,160,255,.28);pointer-events:none}
.cnode__rings i:nth-child(2){border-color:rgba(140,160,255,.14)}
.cnode__rings i:nth-child(3){border-style:dashed;border-color:rgba(255,93,162,.3)}
.cnode__radar{position:absolute;left:50%;top:50%;width:170px;height:170px;transform:translate(-50%,-50%);border-radius:50%;background:conic-gradient(from 0deg,rgba(124,92,255,.28),transparent 72deg);animation:radarspin 5.5s linear infinite;pointer-events:none}
@keyframes radarspin{to{transform:translate(-50%,-50%) rotate(360deg)}}
.cnode__members{position:absolute;left:50%;top:50%;width:0;height:0}
.member{position:absolute;left:0;top:0;width:46px;height:46px;transform:translate(0,0) translate(-50%,-50%);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.85rem;font-weight:800;color:#fff;background:radial-gradient(circle at 35% 30%,rgba(255,255,255,.25),transparent 45%),#16204d;border:1.5px solid rgba(140,160,255,.45);cursor:pointer;box-shadow:0 4px 14px rgba(0,0,0,.4);transition:transform .18s cubic-bezier(.34,1.56,.64,1),border-color .15s}
.member:hover{border-color:var(--accent2);z-index:5}
.member::after{content:"";position:absolute;inset:-5px;border-radius:50%;border:1px solid transparent;transition:border-color .15s}
.member:hover::after{border-color:var(--accent2)}
.cnode.is-spring .cnode__inner{transition:transform .5s cubic-bezier(.34,1.56,.64,1)}
.cnode--ghost{opacity:.5}

/* ── Bottom sheet ───────────────────────────────────────── */
#sheet{position:fixed;left:50%;bottom:16px;transform:translateX(-50%);z-index:35;width:min(680px,calc(100vw - 24px));background:var(--panel);border:1px solid var(--stroke);border-radius:18px;backdrop-filter:blur(14px);padding:14px 16px;box-shadow:0 20px 60px rgba(0,0,0,.5);transition:transform .25s cubic-bezier(.34,1.3,.64,1)}
.sheet__head{display:flex;align-items:center;gap:12px;margin-bottom:10px}
.sheet__dot{width:42px;height:42px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-weight:900;color:#fff;background:var(--c,var(--accent));border:1px solid rgba(255,255,255,.25)}
.sheet__titles{min-width:0;flex:1}
.sheet__titles h2{margin:0;font-size:1.05rem;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sheet__titles p{margin:2px 0 0;font-size:.72rem;color:var(--muted)}
.sheet__actions{display:flex;gap:8px;flex-wrap:wrap}
.act{border:1px solid var(--stroke);background:rgba(255,255,255,.04);color:var(--text);border-radius:999px;padding:8px 14px;cursor:pointer;font-size:.8rem;font-weight:700;transition:border-color .15s,background .15s}
.act:hover{border-color:var(--accent)}
.act--primary{background:linear-gradient(135deg,var(--accent),var(--accent2));border-color:transparent}
.act--primary:hover{filter:brightness(1.1)}
.act:disabled{opacity:.45;cursor:not-allowed}
.reqlist{margin-top:10px;border-top:1px solid var(--stroke);padding-top:10px}
.req{display:flex;align-items:center;gap:10px;padding:6px 0}
.req .n{flex:1;font-size:.85rem}
.req .ok{border:1px solid var(--team2);color:var(--team2);background:transparent;border-radius:999px;padding:5px 12px;cursor:pointer;font-weight:700}
.req .no{border:1px solid var(--accent2);color:var(--accent2);background:transparent;border-radius:999px;padding:5px 12px;cursor:pointer;font-weight:700}

/* ── Popover ────────────────────────────────────────────── */
#popover{position:fixed;z-index:50;display:none;background:var(--panel);border:1px solid var(--stroke);border-radius:14px;padding:12px;backdrop-filter:blur(14px);box-shadow:0 16px 50px rgba(0,0,0,.55);min-width:210px}
#popover h3{margin:0 0 2px;font-size:.95rem}
#popover .p-sub{font-size:.7rem;color:var(--muted);margin:0 0 10px}
#popover .act{width:100%;text-align:left;margin-bottom:6px}
#popover .act:last-child{margin-bottom:0}

/* ── Beacon (creative join) ─────────────────────────────── */
#beacon{position:fixed;right:22px;bottom:22px;z-index:38;width:64px;height:64px;border-radius:50%;border:none;cursor:pointer;background:radial-gradient(circle at 35% 30%,rgba(255,255,255,.3),transparent 45%),linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;font-size:1.5rem;box-shadow:0 10px 30px rgba(124,92,255,.5)}
#beacon::before{content:"";position:absolute;inset:0;border-radius:50%;border:2px solid rgba(255,255,255,.5);animation:ping 2.4s ease-out infinite}
#beacon::after{content:"";position:absolute;inset:0;border-radius:50%;border:2px solid rgba(255,255,255,.35);animation:ping 2.4s ease-out .8s infinite}
@keyframes ping{0%{transform:scale(1);opacity:.9}100%{transform:scale(2.1);opacity:0}}
#joinpanel{position:fixed;right:22px;bottom:100px;z-index:39;display:none;width:280px;background:var(--panel);border:1px solid var(--stroke);border-radius:18px;padding:18px;backdrop-filter:blur(16px);box-shadow:0 20px 60px rgba(0,0,0,.5)}
#joinpanel h3{margin:0 0 4px;font-size:1rem}
#joinpanel .p-sub{font-size:.72rem;color:var(--muted);margin:0 0 12px}
.join-row{display:flex;gap:8px}
.join-row input{flex:1;padding:10px 12px;border:1px solid var(--stroke);border-radius:999px;background:rgba(255,255,255,.05);color:var(--text);font-size:.9rem;letter-spacing:.08em;text-transform:uppercase}
.join-row input:focus{outline:none;border-color:var(--accent)}
.join-err{color:var(--accent2);font-size:.75rem;margin-top:8px;display:none}

/* ── Toasts ─────────────────────────────────────────────── */
#toasts{position:fixed;top:66px;left:50%;transform:translateX(-50%);z-index:60;display:flex;flex-direction:column;gap:8px;align-items:center}
.toast{background:var(--panel);border:1px solid var(--stroke);border-radius:999px;padding:9px 16px;font-size:.82rem;font-weight:700;backdrop-filter:blur(12px);box-shadow:0 8px 30px rgba(0,0,0,.4);animation:toastin .3s cubic-bezier(.34,1.56,.64,1)}
@keyframes toastin{from{transform:translateY(-14px) scale(.9);opacity:0}to{transform:none;opacity:1}}

@media(max-width:640px){
  .hud__name{display:none}
  #beacon{right:16px;bottom:16px;width:56px;height:56px;font-size:1.3rem}
}
</style>
</head>
<body>
<header class="map-header">
  <a class="map-brand" href="<?= $base ?>/circles" title="Circles"><img src="<?= $base ?>/assets/courtly-mark.png" alt="">COURT<b>LY</b></a>
  <div class="hud">
    <div class="hud__name" id="hudName">—</div>
    <div class="hud__score"><div class="n" id="hudScore">—</div><div class="l">NETWORK SCORE</div></div>
  </div>
  <nav class="map-nav">
    <a class="map-pill map-pill--active" href="<?= $base ?>/circles">Circles</a>
    <a class="map-pill" href="<?= $base ?>/">Sessions</a>
    <a class="map-pill" href="<?= $base ?>/stats">Player Stats</a>
    <a class="map-pill" href="<?= $base ?>/rankings">Rankings</a>
    <button class="hdr-icon" id="btnTheme" title="Theme">☾</button>
    <form method="POST" action="<?= $base ?>/logout" style="margin:0">
      <input type="hidden" name="_token" value="<?= e($csrf) ?>">
      <button type="submit" class="hdr-btn" title="Log out">Logout</button>
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

<div id="sheet"></div>

<button id="beacon" title="Join a circle">⌖</button>
<div id="joinpanel">
  <h3>Join a circle</h3>
  <p class="p-sub">Enter an invite code, or drag your circle onto another on the map.</p>
  <div class="join-row">
    <input id="joinCode" type="text" placeholder="AB12CD34" maxlength="32">
    <button class="act act--primary" id="joinGo">Join</button>
  </div>
  <div class="join-err" id="joinErr"></div>
</div>

<div id="popover"></div>
<div id="toasts"></div>

<script>
const BASE = <?= json_encode($base) ?>;
const CSRF = <?= json_encode($csrf) ?>;
const ME = <?= json_encode($userName) ?>;

const state = {
  nodes: [], nodeMap: {}, positions: {}, personalId: null,
  focusId: null, pending: [],
  view: { x: 0, y: 0, scale: 1 },
  drag: null, pointers: new Map(), lastWheel: 0,
};

/* ── utils ──────────────────────────────────────────────── */
function clamp(v,a,b){return Math.min(b,Math.max(a,v))}
function hashId(id){let x=(id*2654435761)>>>0;x=((x>>16)^x)*0x45d9f3b;x=((x>>16)^x)>>>0;return x/4294967296}
function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]))}
function initialsOf(name){const p=String(name||'?').trim().split(/\s+/);const a=p[0]?p[0][0]:'?';const b=p.length>1?p[p.length-1][0]:'';return (a+b).toUpperCase()}
function toast(msg){const t=document.createElement('div');t.className='toast';t.textContent=msg;document.getElementById('toasts').appendChild(t);setTimeout(()=>{t.style.opacity='0';t.style.transition='opacity .3s';setTimeout(()=>t.remove(),300)},2600)}
function colorFor(id){const hues=[255,190,330,30,205,100,280,45];const h=hues[id%hues.length];return `hsl(${h} 70% 52%)`}

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
  const disc=byKind('discoverable');
  disc.forEach(n=>{const h=hashId(n.id),h2=hashId(n.id*7+13);const a=h*Math.PI*2;const r=760+h2*980;pos[n.id]={x:Math.cos(a)*r,y:Math.sin(a)*r}});
  const focusPos=pos[state.focusId]||pos[state.personalId]||{x:0,y:0};
  byKind('visiting').forEach(n=>{const h=hashId(n.id);const a=h*Math.PI*2;pos[n.id]={x:focusPos.x+Math.cos(a)*300,y:focusPos.y+Math.sin(a)*300}});
  state.positions=pos;
}

function memberOffsets(node){
  const list=node.members||[];const R=126;
  return list.map((m,i)=>{const a=(i/Math.max(list.length,1))*Math.PI*2-Math.PI/2;return Object.assign({},m,{x:Math.cos(a)*R,y:Math.sin(a)*R})});
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
  const c=colorFor(n.id);
  const members=(focused?memberOffsets(n):[]).map(m=>{
    const t=`translate(${m.x}px, ${m.y}px) translate(-50%,-50%)`;
    return `<div class="member" data-user="${m.user_id}" data-pcircle="${m.personal_circle_id||''}" data-public="${m.personal_circle_public?1:0}" style="transform:${t}" title="${esc(m.name)}">${esc(initialsOf(m.name))}</div>`;
  }).join('');
  const ringStyle=[];const rings=focused?`<div class="cnode__rings"><i style="width:${s.px+56}px;height:${s.px+56}px"></i><i style="width:${s.px+112}px;height:${s.px+112}px"></i><i style="width:${s.px+168}px;height:${s.px+168}px"></i></div>`:'';
  const gauge=focused?gaugeSvg(n.network_score||0,s.px+20):'';
  const radar=focused?`<div class="cnode__radar" style="width:${s.px}px;height:${s.px}px"></div>`:'';
  el.innerHTML=`<div class="cnode__inner">
    <div class="cnode__wrap" style="width:${s.px}px;height:${s.px}px;--c:${c}">
      ${rings}${gauge}${radar}
      <div class="cnode__core" style="--c:${c};font-size:${Math.round(s.px*0.28)}px">${esc(initialsOf(n.name))}</div>
      <div class="cnode__members">${members}</div>
    </div>
    <div class="cnode__label" style="transform:translate(-50%,${s.label}px)">${esc(n.name)}</div>
    <div class="cnode__meta" style="transform:translate(-50%,${s.label+16}px)">${n.player_count} players · ${n.member_count} members</div>
  </div>`;
  el.addEventListener('pointerdown',e=>onNodePointerDown(e,n,el));
  return el;
}

function render(){
  const nodesEl=document.getElementById('nodes');nodesEl.replaceChildren();
  const linksEl=document.getElementById('links');linksEl.replaceChildren();
  const focusId=state.focusId||state.personalId;
  for(const n of state.nodes){
    const p=state.positions[n.id]||{x:0,y:0};
    nodesEl.appendChild(buildNode(n,p,n.id===focusId));
  }
  drawLinks(linksEl,focusId);
  updateHud(focusId);
  updateSheet(focusId);
}

function drawLinks(linksEl,focusId){
  const O=2400;
  const add=(a,b,cls)=>{const line=document.createElementNS('http://www.w3.org/2000/svg','line');line.setAttribute('x1',a.x+O);line.setAttribute('y1',a.y+O);line.setAttribute('x2',b.x+O);line.setAttribute('y2',b.y+O);line.setAttribute('class','link '+(cls||''));linksEl.appendChild(line)};
  const linked=new Set();
  const connect=(a,b,cls)=>{if(a==null||b==null)return;const key=[Math.min(a,b),Math.max(a,b)].join('-');if(linked.has(key))return;linked.add(key);const pa=state.positions[a],pb=state.positions[b];if(pa&&pb)add(pa,pb,cls)};
  // personal circle -> each joined circle
  state.nodes.forEach(n=>{if(n.kind==='joined')connect(state.personalId,n.id,'link--soft')});
  // every circle I belong to -> each member's personal circle (if on the map)
  state.nodes.forEach(n=>{
    if((n.kind==='mine'||n.kind==='joined')&&n.members){
      n.members.forEach(m=>{
        if(m.personal_circle_id&&state.nodeMap[m.personal_circle_id]&&m.personal_circle_id!==n.id){
          connect(n.id,m.personal_circle_id);
        }
      });
    }
  });
}

function updateHud(focusId){
  const n=state.nodeMap[focusId];
  document.getElementById('hudScore').textContent=n?(n.network_score||0):'—';
  document.getElementById('hudName').innerHTML=n?esc(n.name)+'<small>FOCUSED CIRCLE</small>':'—';
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

/* ── sheet ──────────────────────────────────────────────── */
function updateSheet(focusId){
  const n=state.nodeMap[focusId];const sheet=document.getElementById('sheet');
  if(!n){sheet.innerHTML='';return}
  const c=colorFor(n.id);
  const isMember=n.kind==='mine'||n.kind==='joined';
  let acts=[];
  if(isMember)acts.push(`<button class="act act--primary" onclick="startMeetup(${n.id})">▶ Start meetup</button>`);
  acts.push(`<button class="act" onclick="openLeaderboard(${n.id})">Rankings</button>`);
  if(n.is_admin)acts.push(`<button class="act" onclick="copyCode()">Invite code</button>`);
  if(!isMember&&n.kind==='discoverable')acts.push(`<button class="act act--primary" onclick="askJoin(${n.id})">Ask to join</button>`);
  if(isMember&&!n.is_admin)acts.push(`<button class="act" onclick="leaveCircle(${n.id})">Leave</button>`);
  if(n.is_admin)acts.push(`<button class="act" onclick="toggleVisibility(${n.id})">${n.visibility==='PUBLIC'?'Make private':'Make public'}</button>`);
  let reqHtml='';
  if(n.is_admin&&state.nodeMap[n.id]&&state.nodeMap[n.id].pending_requests&&state.nodeMap[n.id].pending_requests.length){
    reqHtml='<div class="reqlist">'+state.nodeMap[n.id].pending_requests.map(r=>`<div class="req"><span class="n">${esc(r.name)}</span><button class="ok" onclick="decideRequest(${r.id},'approve')">Approve</button><button class="no" onclick="decideRequest(${r.id},'decline')">Decline</button></div>`).join('')+'</div>';
  }
  sheet.innerHTML=`<div class="sheet__head">
    <div class="sheet__dot" style="--c:${c}">${esc(initialsOf(n.name))}</div>
    <div class="sheet__titles"><h2>${esc(n.name)}</h2><p>${esc(n.location_label||'No location set')} · ${n.player_count} players · ${n.member_count} members · ${n.kind.toUpperCase()}</p></div>
  </div>
  <div class="sheet__actions">${acts.join('')}</div>${reqHtml}`;
}

async function refreshSheet(){
  const id=state.focusId||state.personalId;if(!id)return;
  const r=await api(`/api/circles/${id}`);
  if(r.ok&&r.data){
    if(state.nodeMap[id]){
      const oldKind=state.nodeMap[id].kind;
      state.nodeMap[id]=Object.assign({},state.nodeMap[id],r.data);
      state.nodeMap[id].kind=oldKind;
      updateSheet(id);updateHud(id);
    }
  }
}

/* ── actions ────────────────────────────────────────────── */
function closePopover(){document.getElementById('popover').style.display='none'}

function openPopover(nodeEl,node){
  const pop=document.getElementById('popover');
  const isMember=node.kind==='mine'||node.kind==='joined';
  let acts=`<button class="act" onclick="focusOn(${node.id});closePopover()">🔭 Focus</button>`;
  if(isMember)acts+=`<button class="act" onclick="startMeetup(${node.id})">▶ Start meetup</button>`;
  acts+=`<button class="act" onclick="openLeaderboard(${node.id})">Rankings</button>`;
  if(!isMember&&node.kind==='discoverable'){
    const pending=state.pending.some(p=>p.circle_id===node.id);
    acts+=pending?`<button class="act" disabled>⏳ Request pending</button>`:`<button class="act act--primary" onclick="askJoin(${node.id})">➕ Ask to join</button>`;
  }
  if(node.is_admin)acts+=`<button class="act" onclick="copyCode()">Invite code: ${esc(node.invite_code||'')}</button>`;
  pop.innerHTML=`<h3>${esc(node.name)}</h3><p class="p-sub">${node.kind.toUpperCase()} · ${node.network_score||0} network score</p>${acts}`;
  pop.style.display='block';
  const r=nodeEl.getBoundingClientRect();
  const pw=pop.offsetWidth,ph=pop.offsetHeight;
  let x=r.left+r.width/2-pw/2,y=r.top-ph-12;
  x=clamp(x,8,window.innerWidth-pw-8);if(y<60)y=r.bottom+12;
  pop.style.left=x+'px';pop.style.top=y+'px';
}

async function askJoin(id){
  const r=await api(`/api/circles/${id}/request-join`,{method:'POST',body:{}});
  if(r.ok){toast('Join request sent ✦');closePopover();state.pending.push({circle_id:id});}
  else toast(r.message||'Could not send request');
  refreshSheet();
}

async function startMeetup(id){
  const n=state.nodeMap[id];
  const r=await api('/api/sessions',{method:'POST',body:{name:(n?n.name+' Meetup':'Quick Meetup'),number_of_courts:1,circle_id:id}});
  if(r.ok&&r.data){toast('Meetup started ✦');setTimeout(()=>{window.location.href=BASE+'/sessions/'+r.data.id+'/live'},600)}
  else toast((r.message||'Could not start meetup'));
}

function openLeaderboard(id){
  const n=state.nodeMap[id];
  const sheet=document.getElementById('sheet');
  if(!n)return;
  api(`/api/circles/${id}/leaderboard`).then(r=>{
    if(!r.ok){toast(r.message||'Could not load rankings');return}
    const rows=(r.data||[]).map((p,i)=>`<div class="req"><span class="n"><b style="color:var(--muted)">${i+1}.</b> ${esc(p.name)} <span style="color:var(--accent2)">${esc(p.tier)}</span></span><span style="color:var(--muted);font-size:.8rem">${Math.round(p.rating)} · ${p.wins}W/${p.losses}L</span></div>`).join('')||'<div class="req"><span class="n">No ranked players yet.</span></div>';
    sheet.innerHTML=`<div class="sheet__head"><div class="sheet__dot" style="--c:${colorFor(n.id)}">${esc(initialsOf(n.name))}</div><div class="sheet__titles"><h2>${esc(n.name)} — Rankings</h2><p>Tier · rating · record</p></div></div><div class="reqlist" style="max-height:220px;overflow:auto">${rows}</div><div class="sheet__actions" style="margin-top:10px"><button class="act" onclick="render()">Back</button></div>`;
  });
}

async function leaveCircle(id){
  const r=await api(`/api/circles/${id}/leave`,{method:'POST',body:{}});
  if(r.ok){toast('Left circle');state.nodes=state.nodes.filter(n=>n.id!==id);delete state.nodeMap[id];layout();render();refreshMap()}
  else toast(r.message||'Could not leave');
}

async function toggleVisibility(id){
  const n=state.nodeMap[id];
  const r=await api(`/api/circles/${id}`,{method:'PATCH',body:{visibility:n.visibility==='PUBLIC'?'PRIVATE':'PUBLIC'}});
  if(r.ok){toast('Visibility updated');refreshMap()}
  else toast(r.message||'Could not update');
}

function copyCode(){
  const n=state.nodeMap[state.focusId||state.personalId];
  if(!n||!n.invite_code){toast('No invite code');return}
  if(navigator.clipboard)navigator.clipboard.writeText(n.invite_code).then(()=>toast('Invite code copied ✦'));
  else toast(n.invite_code);
}

async function decideRequest(id,action){
  const r=await api(`/api/circle-join-requests/${id}/${action}`,{method:'POST',body:{}});
  if(r.ok){toast(action==='approve'?'Approved ✦':'Declined');refreshMap()}
  else toast(r.message||'Failed');
}

async function visitCircle(id){
  if(state.nodeMap[id]){focusOn(id);return}
  const r=await api(`/api/circles/${id}`);
  if(!r.ok){toast(r.status===403?'That circle is private':'Could not open circle');return}
  const node=Object.assign({kind:'visiting'},r.data);
  state.nodes.push(node);state.nodeMap[id]=node;layout();render();focusOn(id);
}

/* ── data ───────────────────────────────────────────────── */
async function loadMap(){
  const r=await api('/api/circles/map');
  if(!r.ok){toast(r.message||'Could not load the map');return}
  const d=r.data||{nodes:[],pending_requests:[],personal_circle_id:null};
  state.personalId=d.personal_circle_id;
  state.pending=d.pending_requests||[];
  state.nodes=d.nodes||[];
  state.nodeMap={};state.nodes.forEach(n=>state.nodeMap[n.id]=n);
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
  viewLoaded=true;
  setTimeout(refreshSheet,400);}
async function refreshMap(){
  const r=await api('/api/circles/map');
  if(!r.ok)return;
  const d=r.data||{nodes:[],pending_requests:[],personal_circle_id:state.personalId};
  const keepFocus=state.focusId;
  state.nodes=d.nodes||[];state.pending=d.pending_requests||[];
  state.nodeMap={};state.nodes.forEach(n=>state.nodeMap[n.id]=n);
  state.focusId=keepFocus&&state.nodeMap[keepFocus]?keepFocus:(state.personalId||null);
  layout();render();refreshSheet();
}

/* ── input: pan / zoom / node drag ──────────────────────── */
const map=document.getElementById('map');

function onNodePointerDown(e,n,el){
  if(e.target.closest('.member')){e.stopPropagation();return}
  if(e.button!==undefined&&e.button!==0)return;
  e.stopPropagation();
  const start={sx:e.clientX,sy:e.clientY};
  let moved=false;
  const inner=el.querySelector('.cnode__inner');
  const p=state.positions[n.id]||{x:0,y:0};
  const onMove=ev=>{
    const dx=(ev.clientX-start.sx)/state.view.scale,dy=(ev.clientY-start.sy)/state.view.scale;
    if(Math.abs(dx)+Math.abs(dy)>6)moved=true;
    if(moved){el.classList.remove('is-spring');inner.style.transform=`translate(-50%,-50%) translate(${dx}px,${dy}px)`}
  };
  const onUp=ev=>{
    window.removeEventListener('pointermove',onMove);window.removeEventListener('pointerup',onUp);window.removeEventListener('pointercancel',onUp);
    el.classList.add('is-spring');inner.style.transform='';
    if(moved){
      const w=screenToWorld(ev.clientX,ev.clientY);
      const target=findNodeAt(w.x,w.y,90);
      if(target&&target.id!==n.id){
        if(n.kind==='mine'&&target.kind==='discoverable'){askJoin(target.id)}
        else if(target.kind==='discoverable'||target.kind==='joined'||target.kind==='visiting'){visitCircle(target.id)}
      }
    }else{
      openPopover(el,n);
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
  if(e.target.closest('.cnode')||e.target.closest('#sheet')||e.target.closest('#popover')||e.target.closest('#joinpanel')||e.target.closest('#beacon'))return;
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
var COURT_THEMES=['dark','blue','cyber','crimson','emerald','light'];
var COURT_GLYPHS={dark:'☾',blue:'✦',cyber:'✧',crimson:'♥',emerald:'❖',light:'☀'};
var COURT_LABELS={dark:'Dark',blue:'Blue',cyber:'Cyber',crimson:'Crimson',emerald:'Emerald',light:'Light'};
function courtlyCurrentTheme(){var t=document.documentElement.getAttribute('data-theme');return COURT_THEMES.indexOf(t)!==-1?t:'dark'}
function updateThemeIcon(){var b=document.getElementById('btnTheme');if(!b)return;var t=courtlyCurrentTheme();b.textContent=COURT_GLYPHS[t];b.title='Theme: '+COURT_LABELS[t]+' — click to switch';b.setAttribute('aria-label',b.title)}
function toggleCourtlyTheme(){var next=COURT_THEMES[(COURT_THEMES.indexOf(courtlyCurrentTheme())+1)%COURT_THEMES.length];if(next==='dark')document.documentElement.removeAttribute('data-theme');else document.documentElement.setAttribute('data-theme',next);try{localStorage.setItem('courtly-theme',next)}catch(e){}updateThemeIcon()}
document.getElementById('btnTheme').addEventListener('click',toggleCourtlyTheme);
(function(){try{var s=localStorage.getItem('courtly-theme');if(COURT_THEMES.indexOf(s)!==-1&&s!=='dark')document.documentElement.setAttribute('data-theme',s)}catch(e){}updateThemeIcon()})();

window.addEventListener('resize',()=>{if(!state.drag)centerView()});

/* boot */
loadMap();
setInterval(refreshMap,15000);
</script>
</body>
</html>
