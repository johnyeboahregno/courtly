<?php
$base = $base ?? rtrim(request()->getBasePath(), '/');
$csrf = $csrf ?? csrf_token();
$version = config('courtly.app.version', '1.0.0');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin — Courtly</title>
<link rel="icon" type="image/png" href="<?= e($base) ?>/assets/favicon.png?v=<?= e($version) ?>">
<link rel="stylesheet" href="<?= e($base) ?>/css/courtly.css?v=<?= e($version) ?>">
<style>
:root{--panel:var(--surface)}
*{box-sizing:border-box}
html,body{height:100%}
body{margin:0;font-family:"Manrope","Segoe UI",system-ui,sans-serif;background:var(--bg);color:var(--text)}
.admin{display:flex;flex-direction:column;height:100dvh}
.admin__head{display:flex;align-items:center;gap:14px;padding:12px 18px;border-bottom:1px solid var(--stroke);background:var(--surface);flex-wrap:wrap}
.admin__brand{display:flex;align-items:center;gap:9px;font-weight:900;letter-spacing:.03em;font-size:1.05rem;text-decoration:none;color:var(--text)}
.admin__brand img{height:26px}
.admin__brand b{color:var(--accent)}
.admin__title{font-weight:800;font-size:.8rem;letter-spacing:.12em;color:var(--text-muted);text-transform:uppercase;border:1px solid var(--stroke);border-radius:999px;padding:5px 12px}
.admin__spacer{flex:1}
.admin__tabs{display:flex;gap:6px;padding:10px 18px;border-bottom:1px solid var(--stroke);background:var(--bg);overflow-x:auto;scrollbar-width:none}
.admin__tabs::-webkit-scrollbar{display:none}
.tab{border:1px solid var(--stroke);background:transparent;color:var(--text-muted);border-radius:999px;padding:8px 16px;cursor:pointer;font-size:.85rem;font-weight:700;white-space:nowrap;transition:border-color .15s,color .15s,background .15s}
.tab:hover{border-color:var(--accent);color:var(--text)}
.tab--active{background:linear-gradient(135deg,var(--accent),var(--accent2));border-color:transparent;color:#fff}
.admin__body{flex:1;overflow-y:auto;padding:18px;-webkit-overflow-scrolling:touch}
.pane{display:none}
.pane--active{display:block}
.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:20px}
.card{background:var(--surface);border:1px solid var(--stroke);border-radius:12px;padding:16px}
.card b{display:block;font-size:1.6rem;font-weight:900}
.card span{font-size:.7rem;letter-spacing:.1em;color:var(--text-muted);text-transform:uppercase}
.table{width:100%;border-collapse:collapse;background:var(--surface);border:1px solid var(--stroke);border-radius:12px;overflow:hidden;font-size:.85rem}
.table th,.table td{text-align:left;padding:10px 12px;border-bottom:1px solid var(--stroke);vertical-align:middle}
.table th{font-size:.68rem;letter-spacing:.1em;text-transform:uppercase;color:var(--text-muted);background:rgba(255,255,255,.03);position:sticky;top:0}
.table tr:last-child td{border-bottom:none}
.table td .muted{color:var(--text-muted);font-size:.78rem}
.pill{display:inline-block;padding:2px 9px;border-radius:999px;font-size:.68rem;font-weight:800;letter-spacing:.04em}
.pill--super{background:rgba(124,92,255,.18);color:#b9a5ff}
.pill--admin{background:rgba(255,93,162,.16);color:#ff9ec4}
.pill--organiser{background:rgba(57,208,255,.14);color:#7fdcff}
.pill--player{background:rgba(255,255,255,.08);color:var(--text-muted)}
.pill--public{background:rgba(0,199,100,.14);color:#5fe6a3}
.pill--private{background:rgba(255,255,255,.08);color:var(--text-muted)}
.act{border:1px solid var(--stroke);background:transparent;color:var(--text-muted);border-radius:999px;padding:6px 12px;cursor:pointer;font-size:.78rem;font-weight:700;transition:border-color .15s,color .15s}
.act:hover{border-color:var(--accent);color:var(--text)}
.act--danger:hover{border-color:#ff4d6d;color:#ff6b84}
.act--primary{background:linear-gradient(135deg,var(--accent),var(--accent2));border-color:transparent;color:#fff}
.act--primary:hover{filter:brightness(1.1);color:#fff}
select.role{border:1px solid var(--stroke);background:var(--bg);color:var(--text);border-radius:8px;padding:6px 8px;font-size:.8rem;font-weight:700}
code.invite{letter-spacing:.12em;font-weight:800}
.toasts{position:fixed;top:14px;left:50%;transform:translateX(-50%);z-index:60;display:flex;flex-direction:column;gap:8px;align-items:center}
.toast{background:var(--surface);border:1px solid var(--stroke);border-radius:999px;padding:9px 16px;font-size:.82rem;font-weight:700;box-shadow:0 8px 30px rgba(0,0,0,.4)}
.dialog-overlay{position:fixed;inset:0;z-index:80;display:none;align-items:center;justify-content:center;background:rgba(8,10,30,.62);backdrop-filter:blur(5px);padding:18px}
.dialog-overlay--open{display:flex}
.dialog{width:min(420px,100%);background:var(--surface);border:1px solid var(--stroke);border-radius:16px;padding:20px;box-shadow:0 24px 70px rgba(0,0,0,.6)}
.dialog__icon{font-size:1.7rem;line-height:1}
.dialog h3{margin:10px 0 6px;font-size:1.05rem}
.dialog p{margin:0 0 18px;color:var(--text-muted);font-size:.9rem;line-height:1.55}
.dialog .row{display:flex;gap:8px;justify-content:flex-end}
.form{max-width:420px;background:var(--surface);border:1px solid var(--stroke);border-radius:12px;padding:18px}
.field{margin-bottom:12px}
.field label{display:block;font-size:.78rem;font-weight:700;color:var(--text-muted);margin-bottom:5px}
.field input{width:100%;padding:10px 12px;border:1px solid var(--stroke);border-radius:8px;background:var(--bg);color:var(--text);font-size:.9rem}
.field input:focus{outline:none;border-color:var(--accent)}
.empty{color:var(--text-muted);padding:14px;border:1px dashed var(--stroke);border-radius:12px;text-align:center}
@media(max-width:640px){.admin__head{gap:10px}.table{display:block;overflow-x:auto}}
</style>
</head>
<body>
<div class="admin">
  <?php /* Shared app menu — the same header every screen uses. */ ?>
  <?php $active = 'admin'; include resource_path('views/partials/app-header.php'); ?>

  <nav class="admin__tabs" id="tabs">
    <button class="tab tab--active" data-tab="overview">Overview</button>
    <button class="tab" data-tab="users">Users</button>
    <button class="tab" data-tab="circles">Circles</button>
    <button class="tab" data-tab="sessions">Sessions</button>
    <button class="tab" data-tab="players">Players</button>
    <button class="tab" data-tab="password">Password</button>
  </nav>

  <main class="admin__body">
    <section class="pane pane--active" id="pane-overview">
      <div class="cards" id="overviewCards"></div>
    </section>
    <section class="pane" id="pane-users"><div id="usersTable"></div></section>
    <section class="pane" id="pane-circles"><div id="circlesTable"></div></section>
    <section class="pane" id="pane-sessions"><div id="sessionsTable"></div></section>
    <section class="pane" id="pane-players"><div id="playersTable"></div></section>
    <section class="pane" id="pane-password">
      <div class="form">
        <h3 style="margin:0 0 4px">Change password</h3>
        <p style="color:var(--text-muted);font-size:.82rem;margin:0 0 14px">Updates your super-admin login. The change survives future deployments.</p>
        <div class="field"><label>Current password</label><input type="password" id="pwCurrent" autocomplete="current-password"></div>
        <div class="field"><label>New password</label><input type="password" id="pwNew" autocomplete="new-password"></div>
        <div class="field"><label>Confirm new password</label><input type="password" id="pwConfirm" autocomplete="new-password"></div>
        <button class="act act--primary" onclick="changePassword()" style="width:100%">Update password</button>
      </div>
    </section>
  </main>
</div>
<div class="dialog-overlay" id="errorDialog" role="dialog" aria-modal="true">
  <div class="dialog">
    <div class="dialog__icon">⚠️</div>
    <h3 id="errorDialogTitle">Could not delete</h3>
    <p id="errorDialogMessage"></p>
    <div class="row"><button class="act act--primary" onclick="closeErrorDialog()">OK</button></div>
  </div>
</div>
<div class="dialog-overlay" id="confirmDialog" role="dialog" aria-modal="true">
  <div class="dialog">
    <div class="dialog__icon">🗑️</div>
    <h3 id="confirmTitle">Delete</h3>
    <p id="confirmMessage"></p>
    <div class="row">
      <button class="act" onclick="resolveConfirm(false)">Cancel</button>
      <button class="act act--danger" id="confirmOk" onclick="resolveConfirm(true)">Delete</button>
    </div>
  </div>
</div>
<div class="toasts" id="toasts"></div>

<script>
const BASE = <?= json_encode($base) ?>;
const CSRF = <?= json_encode($csrf) ?>;
const data = { users: [], circles: [], sessions: [], players: [], counts: {} };

function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]))}
function toast(msg){const t=document.createElement('div');t.className='toast';t.textContent=msg;document.getElementById('toasts').appendChild(t);setTimeout(()=>{t.style.opacity='0';t.style.transition='opacity .3s';setTimeout(()=>t.remove(),300)},2600)}
function showErrorDialog(title,message){document.getElementById('errorDialogTitle').textContent=title;document.getElementById('errorDialogMessage').textContent=message;document.getElementById('errorDialog').classList.add('dialog-overlay--open')}
function closeErrorDialog(){document.getElementById('errorDialog').classList.remove('dialog-overlay--open')}
document.getElementById('errorDialog').addEventListener('click',e=>{if(e.target===e.currentTarget)closeErrorDialog()});
let confirmResolve=null;
function confirmDialog(title,message,label){document.getElementById('confirmTitle').textContent=title;document.getElementById('confirmMessage').textContent=message;const ok=document.getElementById('confirmOk');ok.textContent=label||'Delete';document.getElementById('confirmDialog').classList.add('dialog-overlay--open');return new Promise(res=>{confirmResolve=res})}
function resolveConfirm(v){document.getElementById('confirmDialog').classList.remove('dialog-overlay--open');if(confirmResolve){const r=confirmResolve;confirmResolve=null;r(v)}}
document.getElementById('confirmDialog').addEventListener('click',e=>{if(e.target===e.currentTarget)resolveConfirm(false)});

async function api(path, opts){
  opts=opts||{};
  const headers=Object.assign({'Accept':'application/json','X-CSRF-TOKEN':CSRF},opts.headers||{});
  let body=opts.body;
  if(body&&typeof body==='object'){headers['Content-Type']='application/json';body=JSON.stringify(body)}
  const res=await fetch(BASE+path,Object.assign({credentials:'include'},opts,{headers,body}));
  let json={};
  try{json=await res.json()}catch(e){}
  return {ok:res.ok,status:res.status,message:json.message,data:json};
}

function setPane(name){
  document.querySelectorAll('.admin__tabs .tab').forEach(t=>t.classList.toggle('tab--active',t.dataset.tab===name));
  document.querySelectorAll('.pane').forEach(p=>p.classList.toggle('pane--active',p.id==='pane-'+name));
}
document.getElementById('tabs').addEventListener('click',e=>{const t=e.target.closest('.tab');if(t)setPane(t.dataset.tab)});

function rolePill(role){
  const map={SUPER_ADMIN:'pill--super',ADMIN:'pill--admin',ORGANISER:'pill--organiser',PLAYER:'pill--player'};
  return `<span class="pill ${map[role]||'pill--player'}">${esc(role)}</span>`;
}

function renderOverview(){
  const c=data.counts;
  const items=[['users','Users'],['circles','Circles'],['sessions','Sessions'],['players','Players'],['matches','Matches']];
  document.getElementById('overviewCards').innerHTML=items.map(([k,l])=>`<div class="card"><b>${c[k]??0}</b><span>${l}</span></div>`).join('');
}

function renderUsers(){
  const rows=data.users.map(u=>`<tr>
    <td>${esc(u.name)}<div class="muted">${esc(u.email)}</div></td>
    <td>${rolePill(u.role)}</td>
    <td>${u.verified?'✓ verified':'<span class="muted">unverified</span>'}</td>
    <td class="muted">${esc((u.created_at||'').slice(0,10))}</td>
    <td>
      <select class="role" data-id="${u.id}" onchange="changeRole(this)">
        ${['SUPER_ADMIN','ADMIN','ORGANISER','PLAYER'].map(r=>`<option value="${r}"${u.role===r?' selected':''}>${r}</option>`).join('')}
      </select>
      <button class="act act--danger" onclick="delUser(${u.id})">Delete</button>
    </td>
  </tr>`).join('');
  document.getElementById('usersTable').innerHTML=`<table class="table"><thead><tr><th>Name / Email</th><th>Role</th><th>Email</th><th>Joined</th><th>Actions</th></tr></thead><tbody>${rows||'<tr><td colspan="5" class="empty">No users.</td></tr>'}</tbody></table>`;
}

function renderCircles(){
  const rows=data.circles.map(c=>`<tr>
    <td>${esc(c.name)}</td>
    <td><span class="pill ${c.visibility==='PUBLIC'?'pill--public':'pill--private'}">${esc(c.visibility)}</span></td>
    <td><code class="invite">${esc(c.invite_code||'—')}</code></td>
    <td>${esc(c.admin_name||'—')}<div class="muted">${esc(c.admin_email||'')}</div></td>
    <td>${c.members} members · ${c.players} players · ${c.sessions} sessions</td>
    <td><button class="act act--danger" onclick="delCircle(${c.id})">Delete</button></td>
  </tr>`).join('');
  document.getElementById('circlesTable').innerHTML=`<table class="table"><thead><tr><th>Circle</th><th>Visibility</th><th>Invite code</th><th>Admin</th><th>Usage</th><th>Actions</th></tr></thead><tbody>${rows||'<tr><td colspan="6" class="empty">No circles.</td></tr>'}</tbody></table>`;
}

function renderSessions(){
  const rows=data.sessions.map(s=>`<tr>
    <td>${esc(s.name)}<div class="muted">#${s.id}</div></td>
    <td>${esc(s.sport)}</td>
    <td>${esc(s.status)}</td>
    <td>${esc(s.date||'—')}</td>
    <td>${esc(s.circle||'—')}</td>
    <td>${s.courts} courts · ${s.players} players</td>
    <td>
      <a class="act" href="${BASE}/sessions/${s.id}/live" target="_blank">Open</a>
      <button class="act act--danger" onclick="delSession(${s.id})">Delete</button>
    </td>
  </tr>`).join('');
  document.getElementById('sessionsTable').innerHTML=`<table class="table"><thead><tr><th>Session</th><th>Sport</th><th>Status</th><th>Date</th><th>Circle</th><th>Usage</th><th>Actions</th></tr></thead><tbody>${rows||'<tr><td colspan="7" class="empty">No sessions.</td></tr>'}</tbody></table>`;
}

function renderPlayers(){
  const rows=data.players.map(p=>`<tr>
    <td>${esc(p.name)}</td>
    <td>${esc(p.circle||'—')}</td>
    <td>${Math.round(p.rating)}</td>
    <td>${p.wins}W · ${p.games}G</td>
    <td>${p.linked_account?'<span class="muted">'+esc(p.email||'linked')+'</span>':'<span class="muted">guest</span>'}</td>
    <td><button class="act act--danger" onclick="delPlayer(${p.id})">Delete</button></td>
  </tr>`).join('');
  document.getElementById('playersTable').innerHTML=`<table class="table"><thead><tr><th>Player</th><th>Circle</th><th>Rating</th><th>Record</th><th>Account</th><th>Actions</th></tr></thead><tbody>${rows||'<tr><td colspan="6" class="empty">No players.</td></tr>'}</tbody></table>`;
}

function renderAll(){renderOverview();renderUsers();renderCircles();renderSessions();renderPlayers()}

async function reload(){const r=await api('/admin/data');if(r.ok){Object.assign(data,r.data);renderAll()}else toast(r.message||'Could not load admin data')}

async function changeRole(sel){
  const role=sel.value;
  const id=sel.dataset.id;
  const r=await api(`/admin/users/${id}/role`,{method:'PATCH',body:{role}});
  if(r.ok){toast('Role updated');reload()}else{toast(r.message||'Could not update role');reload()}
}
async function delUser(id){const u=data.users.find(x=>x.id===id);const name=u?u.name:'this user';if(!await confirmDialog('Delete user',`Delete user "${name}" and all their data? This cannot be undone.`))return;const r=await api(`/admin/users/${id}`,{method:'DELETE'});if(r.ok){toast('User deleted');reload()}else showErrorDialog('Could not delete user',r.message||`The server returned an error (HTTP ${r.status}).`)}
async function delCircle(id){const c=data.circles.find(x=>x.id===id);const name=c?c.name:'this circle';if(!await confirmDialog('Delete circle',`Delete circle "${name}" and all its sessions/players? This cannot be undone.`))return;const r=await api(`/admin/circles/${id}`,{method:'DELETE'});if(r.ok){toast('Circle deleted');reload()}else showErrorDialog('Could not delete circle',r.message||`The server returned an error (HTTP ${r.status}).`)}
async function delSession(id){const s=data.sessions.find(x=>x.id===id);const name=s?s.name:'this session';if(!await confirmDialog('Delete session',`Delete session "${name}"? This cannot be undone.`))return;const r=await api(`/admin/sessions/${id}`,{method:'DELETE'});if(r.ok){toast('Session deleted');reload()}else showErrorDialog('Could not delete session',r.message||`The server returned an error (HTTP ${r.status}).`)}
async function delPlayer(id){const p=data.players.find(x=>x.id===id);const name=p?p.name:'this player';if(!await confirmDialog('Delete player',`Delete player "${name}" permanently? This cannot be undone.`))return;const r=await api(`/admin/players/${id}`,{method:'DELETE'});if(r.ok){toast('Player deleted');reload()}else showErrorDialog('Could not delete player',r.message||`The server returned an error (HTTP ${r.status}).`)}

async function changePassword(){
  const current=document.getElementById('pwCurrent').value;
  const next=document.getElementById('pwNew').value;
  const confirm=document.getElementById('pwConfirm').value;
  if(next!==confirm){toast('New passwords do not match');return}
  const r=await api('/admin/password',{method:'POST',body:{current_password:current,new_password:next,new_password_confirmation:confirm}});
  if(r.ok){toast('Password updated ✦');document.getElementById('pwCurrent').value='';document.getElementById('pwNew').value='';document.getElementById('pwConfirm').value=''}
  else toast(r.message||'Could not update password');
}

// Theme cycling (mirrors the rest of the app)
var THEMES=['dark','blue','cyber','emerald','light'];
var GLYPHS={dark:'☾',blue:'✦',cyber:'✧',emerald:'❖',light:'☀'};
function currentTheme(){var t=document.documentElement.getAttribute('data-theme');return THEMES.indexOf(t)!==-1?t:'dark'}
function updateThemeBtn(){document.getElementById('themeSwitch').textContent=GLYPHS[currentTheme()]}
function toggleTheme(){var n=THEMES[(THEMES.indexOf(currentTheme())+1)%THEMES.length];if(n==='dark')document.documentElement.removeAttribute('data-theme');else document.documentElement.setAttribute('data-theme',n);try{localStorage.setItem('courtly-theme',n)}catch(e){}updateThemeBtn()}
(function(){try{var s=localStorage.getItem('courtly-theme');if(THEMES.indexOf(s)!==-1&&s!=='dark')document.documentElement.setAttribute('data-theme',s)}catch(e){}updateThemeBtn()})();

reload();
</script>
</body>
</html>
