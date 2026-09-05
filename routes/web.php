<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — Courtly (PHP-rendered Vue.js UI)
|--------------------------------------------------------------------------
*/

use App\Http\Controllers\Auth\AuthController;

// ── Authentication ────────────────────────────────────────────────────
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Social login
Route::get('/auth/google/redirect', [AuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);

// Dashboard — lists the authenticated user's sessions
Route::get('/', function () {
    $base = rtrim(request()->getBasePath(), '/');

    $userChip = '<span class="user-name">'.e(\Illuminate\Support\Facades\Auth::user()->name).'</span>';

    $sessions = \App\Models\Session::select('id', 'name', 'sport', 'date', 'number_of_courts', 'status', 'matchmaking_mode')
        ->where('created_by', \Illuminate\Support\Facades\Auth::id())
        ->orderByDesc('date')->get();

    $today = now()->startOfDay();
    $currentRows = '';
    $pastRows = '';
    $pastCount = 0;

    foreach ($sessions as $session) {
        $isDateInPast = $session->date !== null && $session->date->lt($today);
        $isPast = $isDateInPast || $session->status->value === 'FINISHED';
        $status = ($session->status->value === 'UPCOMING' && $isDateInPast)
            ? 'PASSED'
            : $session->status->value;

        $mode = ($session->matchmaking_mode ?? 'smart') === 'peg' ? 'PEG' : 'SMART';
        $modeTag = '<span class="tag tag--mode tag--mode--'.strtolower($mode).'">'.$mode.'</span>';

        $row = '<div class="session-row">'
            .'<a class="session-link" href="'.$base.'/sessions/'.$session->id.'/live">'
                .'<span class="session-link__name">'.e($session->name).'</span>'
                .'<span class="session-link__meta">'.e($session->date->format('d M Y')).' · '.ucfirst((string) $session->sport->value).' · '.$session->number_of_courts.' courts · <span class="tag tag--'.strtolower($status).'">'.$status.'</span> '.$modeTag.'</span>'
            .'</a>'
            .'<button type="button" class="session-delete" data-id="'.$session->id.'" title="Delete session">✕</button>'
            .'</div>';

        if ($isPast) {
            $pastRows .= $row;
            $pastCount++;
        } else {
            $currentRows .= $row;
        }
    }

    if ($currentRows === '') {
        $currentRows = '<p class="empty">No current sessions.</p>';
    }
    if ($pastRows === '') {
        $pastRows = '<p class="empty">No past sessions.</p>';
    }

    return '<!DOCTYPE html><html><head><title>Courtly</title><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="'.csrf_token().'">
    <link rel="icon" type="image/png" href="'.$base.'/assets/favicon.png?v=3">
    <link rel="stylesheet" href="'.$base.'/css/courtly.css?v=9">
    <style>
        body{font-family:"SF Mono","JetBrains Mono","Fira Code",monospace;background:var(--bg,#12121f);color:var(--text,#e4e4f0);margin:0;padding:40px 20px}
        .wrap{max-width:560px;margin:0 auto}
        h1{font-size:2rem;margin:0 0 4px}
        .sub{color:var(--text-muted,#8888a8);margin:0 0 24px}
        .manage-link{font-family:inherit;font-size:inherit;color:var(--text-muted,#8888a8);background:none;border:none;cursor:pointer;padding:0;font-weight:inherit}
        .manage-link:hover{color:var(--accent,#ff2d55);text-decoration:underline}
        .session-link{display:block;background:var(--surface,#1e1e32);border:1px solid var(--stroke,#2e2e4a);border-radius:8px;padding:16px;margin-bottom:10px;text-decoration:none;color:var(--text,#e4e4f0);box-shadow:var(--shadow-card,0 4px 20px rgba(0,0,0,.3));transition:border-color .15s}
        .session-link:hover{border-color:var(--accent,#ff2d55)}
        .session-link__name{font-weight:700;font-size:1.05rem;display:block;margin-bottom:4px}
        .session-link__meta{font-size:.85rem;color:var(--text-muted,#8888a8)}
        .tag{display:inline-block;padding:1px 8px;border-radius:999px;font-size:.7rem;font-weight:700}
        .tag--active{background:#1a3a2a;color:#3cae67}.tag--upcoming{background:#1a2a3a;color:#5b9bd5}.tag--paused{background:#3a2a10;color:#d4a017}.tag--finished{background:#2a2a3a;color:#8a8aaa}
        .empty{color:var(--text-muted,#8888a8)}
        .user-name{font-size:.85rem;font-weight:700;color:var(--text,#e4e4f0);padding:6px 12px;border:1px solid var(--stroke,#2e2e4a);border-radius:999px;background:var(--surface,#1e1e32)}
        .tag--passed{background:#3a2024;color:#d47a8a}
        .tag--mode{font-size:.65rem;padding:1px 8px;border-radius:999px;font-weight:700;display:inline-block}
        .tag--mode--peg{background:#3a2c10;color:#e0a91a}
        .tag--mode--smart{background:#10243a;color:#5b9bd5}
        .session-row{display:flex;align-items:center;gap:10px;margin-bottom:10px;transition:opacity .3s ease,transform .3s ease,margin-bottom .3s ease}
        .session-row.session-row--removing{opacity:0;transform:translateX(16px);margin-bottom:0;pointer-events:none}
        .session-row .session-link{flex:1;margin-bottom:0}
        .session-delete{border:none;background:transparent;color:var(--text-muted,#8888a8);border-radius:6px;width:32px;height:32px;font-size:1.1rem;line-height:1;cursor:pointer;flex-shrink:0;display:flex;align-items:center;justify-content:center;transition:color .15s,background .15s}
        .session-delete:hover{color:var(--accent,#ff2d55);background:var(--surface,#1e1e32)}
        .past-section{margin-top:24px}
        .past-toggle{cursor:pointer;font-size:1.05rem;font-weight:700;color:var(--text-muted,#8888a8);list-style:none;user-select:none}
        .past-toggle::-webkit-details-marker{display:none}
        .past-toggle::before{content:\'▸ \';display:inline-block;transition:transform .15s}
        .past-section[open] .past-toggle::before{transform:rotate(90deg)}
        .past-list{margin-top:12px}
        .dialog-overlay{position:fixed;inset:0;background:rgba(0,0,0,.6);display:flex;align-items:center;justify-content:center;z-index:9999}
        #appDialog{z-index:10001}
        .dialog{background:var(--surface,#1e1e32);border:1px solid var(--stroke,#2e2e4a);border-radius:12px;padding:24px;width:100%;max-width:360px;margin:0 16px;box-shadow:0 20px 60px rgba(0,0,0,.5)}
        .dialog__title{margin:0 0 8px;font-size:1.1rem;font-weight:800;color:var(--text,#e4e4f0)}
        .dialog__message{margin:0 0 20px;font-size:.9rem;color:var(--text-muted,#8888a8);line-height:1.5}
        .dialog__actions{display:flex;justify-content:flex-end;gap:10px}
        .dialog__btn{padding:9px 16px;border-radius:8px;font-size:.85rem;font-weight:700;cursor:pointer;border:1px solid transparent}
        .dialog__btn--cancel{background:transparent;border-color:var(--stroke,#2e2e4a);color:var(--text-muted,#8888a8)}
        .dialog__btn--cancel:hover{border-color:var(--accent,#ff2d55);color:var(--text,#e4e4f0)}
        .dialog__btn--danger{background:var(--accent,#ff2d55);color:#fff}
        .dialog__btn--danger:hover{filter:brightness(1.1)}
        .card{background:var(--surface,#1e1e32);border:1px solid var(--stroke,#2e2e4a);border-radius:8px;padding:18px;margin-bottom:20px;box-shadow:var(--shadow-card,0 4px 20px rgba(0,0,0,.3))}
        .card h2{font-size:1.05rem;margin:0 0 14px;color:var(--text,#e4e4f0)}
        .field{margin-bottom:12px}
        .field label{display:block;font-size:.8rem;font-weight:700;color:var(--text-muted,#8888a8);margin-bottom:4px}
        .field input{width:100%;padding:10px;border:1px solid var(--stroke,#2e2e4a);border-radius:6px;font-size:.95rem;box-sizing:border-box;background:var(--bg,#12121f);color:var(--text,#e4e4f0)}
        .field input:focus{outline:none;border-color:var(--accent,#ff2d55)}
        .field select{width:100%;padding:10px;border:1px solid var(--stroke,#2e2e4a);border-radius:6px;font-size:.95rem;box-sizing:border-box;background:var(--bg,#12121f);color:var(--text,#e4e4f0);appearance:none;-webkit-appearance:none;background-image:url("data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'12\' height=\'8\'><path d=\'M1 1l5 5 5-5\' stroke=\'%238888a8\' stroke-width=\'2\' fill=\'none\' fill-rule=\'evenodd\'/></svg>");background-repeat:no-repeat;background-position:right 12px center}
        .field select:focus{outline:none;border-color:var(--accent,#ff2d55)}
        .row{display:flex;gap:12px}
        .row .field{flex:1}
        .create-btn{width:100%;padding:12px;border:none;border-radius:6px;background:var(--accent,#ff2d55);color:#fff;font-size:.95rem;font-weight:700;cursor:pointer;transition:filter .15s}
        .create-btn:hover{filter:brightness(1.1)}
        .err{color:var(--accent,#ff2d55);font-size:.85rem;margin-top:8px;display:none}
        .manage-row{display:flex;align-items:center;gap:8px;margin-bottom:8px}
        .manage-name{flex:1;min-width:0;padding:8px 10px;border:none;border-radius:6px;background:var(--bg,#12121f);color:var(--text,#e4e4f0);font-size:.9rem}
        .manage-name:disabled{opacity:.5}
        .manage-rating{font-size:.8rem;color:var(--text-muted,#8888a8);min-width:34px;text-align:center}
        .manage-lock{font-size:.9rem}
        .manage-btn{border:1px solid var(--stroke,#2e2e4a);background:transparent;color:var(--text-muted,#8888a8);border-radius:6px;padding:7px 10px;cursor:pointer;font-size:.85rem;font-weight:700}
        .manage-btn:hover{border-color:var(--accent,#ff2d55);color:var(--text,#e4e4f0)}
        .manage-btn:disabled{opacity:.4;cursor:not-allowed}
        .manage-del:hover{color:var(--accent,#ff2d55)}
        h2.list-title{margin-top:28px;margin-bottom:12px}
    </style>
    </head><body><div class="wrap">
        <div class="dashboard-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
            <div style="display:flex;align-items:center;justify-content:flex-start"><h1 style="margin-left:-0.5rem;display:flex;align-items:center;justify-content:flex-start"><img src="'.$base.'/assets/favicon.png" style="width:48px;height:48px;object-fit:contain;display:block;"></h1></div>
            <div class="dashboard-header__actions" style="display:flex;gap:8px;align-items:center">
                '.$userChip.'
                <button type="button" class="theme-switch" id="themeSwitch" onclick="toggleCourtlyTheme()" aria-label="Switch theme" title="Switch theme">☾</button>
                <form method="POST" action="/logout" style="margin:0">
                    <input type="hidden" name="_token" value="'.csrf_token().'">
                    <button type="submit" style="background:transparent;border:1px solid var(--stroke,#2e2e4a);padding:8px 18px;border-radius:6px;font-size:.85rem;font-weight:700;cursor:pointer;color:var(--text-muted,#8888a8)">Logout</button>
                </form>
            </div>
        </div>
        <div class="dashboard-subhead" style="display:flex;justify-content:flex-end;align-items:baseline;margin:0 0 24px;gap:12px">
            <div class="dashboard-subhead__actions" style="display:flex;gap:16px;align-items:center">
                <a href="'.$base.'/stats" class="manage-link">Player Stats</a>
                <button type="button" onclick="openManage()" class="manage-link">Manage Players</button>
            </div>
        </div>
        <div class="card">
            <h2>New Session</h2>
            <form id="createForm">
                <div class="field"><label>Session name</label><input id="fName" type="text" placeholder="e.g. Tuesday Night Social" required></div>
                <div class="sport-picker" id="sportPicker" role="radiogroup" aria-label="Choose a court sport">
                    <span class="sport-picker__label">Built for every court</span>
                    <button type="button" class="sport-option is-selected" data-sport="badminton" aria-pressed="true">
                        <svg viewBox="0 0 40 40" aria-hidden="true"><path d="M20 5l8 8M20 5l-3 10M20 5l-8 8M17 15l-5 17M20 15l-1 18M23 15l5 17M17 15h6M12 32h16M14 36h12"/><path d="M25 10l7 7"/></svg><span>Badminton</span>
                    </button>
                    <button type="button" class="sport-option" data-sport="tennis" aria-pressed="false">
                        <svg viewBox="0 0 40 40" aria-hidden="true"><circle cx="20" cy="20" r="15"/><path d="M10 8c6 4 8 9 7 15s-4 10-9 12M30 8c-6 4-8 9-7 15s4 10 9 12"/></svg><span>Tennis</span>
                    </button>
                    <button type="button" class="sport-option" data-sport="pickleball" aria-pressed="false">
                        <svg viewBox="0 0 40 40" aria-hidden="true"><circle cx="20" cy="20" r="15"/><circle cx="14" cy="14" r="1" fill="currentColor"/><circle cx="25" cy="13" r="1" fill="currentColor"/><circle cx="28" cy="22" r="1" fill="currentColor"/><circle cx="17" cy="27" r="1" fill="currentColor"/><circle cx="11" cy="23" r="1" fill="currentColor"/></svg><span>Pickleball</span>
                    </button>
                    <button type="button" class="sport-option" data-sport="padel" aria-pressed="false">
                        <svg viewBox="0 0 40 40" aria-hidden="true"><ellipse cx="17" cy="14" rx="8" ry="11" transform="rotate(35 17 14)"/><path d="M21 22l9 12M13 9l8 6M11 13l8 6M10 17l7 5"/></svg><span>Padel</span>
                    </button>
                    <button type="button" class="sport-option" data-sport="squash" aria-pressed="false">
                        <svg viewBox="0 0 40 40" aria-hidden="true"><circle cx="20" cy="20" r="15"/><circle cx="16" cy="16" r="1" fill="currentColor"/><circle cx="25" cy="24" r="1" fill="currentColor"/><path d="M20 5c-2 7-2 14 0 30"/></svg><span>Squash</span>
                    </button>
                </div>
                <input id="fSport" type="hidden" value="badminton">
                <div class="field"><label>Number of courts</label><input id="fCourts" type="number" min="1" max="8" value="3" required></div>
                <div class="field"><label>Session type</label><select id="fType" onchange="document.getElementById(\'fFormatField\').style.display = this.value === \'tournament\' ? \'block\' : \'none\'"><option value="casual" selected>Casual</option><option value="tournament">Tournament</option></select></div>
                <div class="field" id="fFormatField" style="display:none"><label>Tournament format</label><select id="fFormat"><option value="round_robin" selected>Round Robin (everyone plays everyone)</option><option value="ladder">Ladder (challenge the rank above you)</option></select></div>
                <button type="submit" class="create-btn">Create Session</button>
                <div id="err" class="err"></div>
            </form>
        </div>
        <h2 class="list-title">Current Sessions</h2>'.$currentRows.'
        <details class="past-section">
            <summary class="past-toggle">Past Sessions ('.$pastCount.')</summary>
            <div class="past-list">'.$pastRows.'</div>
        </details>
    </div>
    <div class="dialog-overlay" id="appDialog" style="display:none">
        <div class="dialog">
            <h3 class="dialog__title" id="appDialogTitle"></h3>
            <p class="dialog__message" id="appDialogMessage"></p>
            <div class="dialog__actions" id="appDialogActions"></div>
        </div>
    </div>
    <div class="dialog-overlay" id="manageDialog" style="display:none">
        <div class="dialog" style="max-width:480px">
            <h3 class="dialog__title">Manage Players</h3>
            <p class="dialog__message">Edit names or delete players. Players on court are locked.</p>
            <div id="manageList" style="max-height:60vh;overflow:auto;margin-bottom:16px"></div>
            <div class="dialog__actions">
                <button type="button" class="dialog__btn dialog__btn--cancel" onclick="closeManage()">Close</button>
            </div>
        </div>
    </div>
    <script>
    function courtlyUpdateThemeIcon() {
        var button = document.getElementById("themeSwitch");
        if (!button) return;
        var light = document.documentElement.getAttribute("data-theme") === "light";
        button.textContent = light ? "☾" : "☀";
        button.title = light ? "Switch to dark theme" : "Switch to light theme";
        button.setAttribute("aria-label", button.title);
    }
    function toggleCourtlyTheme() {
        var isLight = document.documentElement.getAttribute("data-theme") === "light";
        var next = isLight ? "dark" : "light";
        document.documentElement.setAttribute("data-theme", next);
        localStorage.setItem("courtly-theme", next);
        courtlyUpdateThemeIcon();
    }
    (function() {
        var stored = localStorage.getItem("courtly-theme");
        if (stored === "light" || stored === "dark") document.documentElement.setAttribute("data-theme", stored);
        courtlyUpdateThemeIcon();
    })();

    var sportInput = document.getElementById("fSport");
    document.querySelectorAll(".sport-option").forEach(function(option) {
        option.addEventListener("click", function() {
            sportInput.value = option.dataset.sport;
            document.querySelectorAll(".sport-option").forEach(function(item) {
                var selected = item === option;
                item.classList.toggle("is-selected", selected);
                item.setAttribute("aria-pressed", selected ? "true" : "false");
            });
        });
    });

    document.getElementById("createForm").addEventListener("submit", async function(e){
        e.preventDefault();
        var err = document.getElementById("err");
        err.style.display = "none";
        try {
            var res = await fetch("/api/sessions", {
                method: "POST",
                headers: {"Content-Type": "application/json", "Accept": "application/json", "X-CSRF-TOKEN": "'.csrf_token().'"},
                body: JSON.stringify({
                    name: document.getElementById("fName").value.trim(),
                    sport: document.getElementById("fSport").value,
                    number_of_courts: parseInt(document.getElementById("fCourts").value, 10),
                    type: document.getElementById("fType").value,
                    tournament_format: document.getElementById("fFormat").value
                })
            });
            var json = await res.json();
            if (!res.ok) throw new Error(json.message || "Failed to create session");
            window.location.href = "/sessions/" + json.data.id + "/live";
        } catch (ex) {
            err.textContent = ex.message;
            err.style.display = "block";
        }
    });
    </script>
    <script>
    var appDialog = document.getElementById("appDialog");
    var appDialogTitle = document.getElementById("appDialogTitle");
    var appDialogMessage = document.getElementById("appDialogMessage");
    var appDialogActions = document.getElementById("appDialogActions");

    function closeAppDialog() { appDialog.style.display = "none"; }
    appDialog.addEventListener("click", function(e){ if (e.target === appDialog) closeAppDialog(); });

    function showAlertDialog(title, message) {
        appDialogTitle.textContent = title;
        appDialogMessage.textContent = message;
        appDialogActions.innerHTML = "";
        var ok = document.createElement("button");
        ok.type = "button";
        ok.className = "dialog__btn dialog__btn--danger";
        ok.textContent = "OK";
        ok.addEventListener("click", closeAppDialog);
        appDialogActions.appendChild(ok);
        appDialog.style.display = "flex";
    }

    function showConfirmDialog(title, message, onConfirm) {
        appDialogTitle.textContent = title;
        appDialogMessage.textContent = message;
        appDialogActions.innerHTML = "";
        var cancel = document.createElement("button");
        cancel.type = "button";
        cancel.className = "dialog__btn dialog__btn--cancel";
        cancel.textContent = "Cancel";
        cancel.addEventListener("click", closeAppDialog);
        var ok = document.createElement("button");
        ok.type = "button";
        ok.className = "dialog__btn dialog__btn--danger";
        ok.textContent = "Delete";
        ok.addEventListener("click", function(){ closeAppDialog(); onConfirm(); });
        appDialogActions.appendChild(cancel);
        appDialogActions.appendChild(ok);
        appDialog.style.display = "flex";
    }

    document.addEventListener("click", function(e){
        var btn = e.target.closest(".session-delete");
        if (!btn) return;
        e.preventDefault();
        var id = btn.getAttribute("data-id");
        var row = btn.closest(".session-row");
        showConfirmDialog("Delete session", "Delete this session and all its data? This cannot be undone.", function(){
            // Optimistic: fade the row out immediately.
            if (row) {
                row.classList.add("session-row--removing");
                setTimeout(function(){ if (row) row.remove(); }, 300);
            }
            fetch("/api/sessions/" + id, { method: "DELETE", headers: {"Accept": "application/json", "X-CSRF-TOKEN": "'.csrf_token().'"} })
                .then(function(res){
                    if (!res.ok) { showAlertDialog("Error", "Failed to delete session — refresh to restore the list."); }
                })
                .catch(function(){ showAlertDialog("Error", "Failed to delete session — refresh to restore the list."); });
        });
    });
    </script>
    <script>
    // Roster cache: the player list is loaded once on startup and kept in
    // localStorage so "Manage Players" renders instantly instead of waiting
    // on a network round-trip each time it opens.
    var playersCache = null;
    var PLAYERS_CACHE_KEY = "courtly.playersCache.v1";

    function loadPlayersCache() {
        try {
            var raw = localStorage.getItem(PLAYERS_CACHE_KEY);
            if (raw) playersCache = JSON.parse(raw);
        } catch (e) { playersCache = null; }
    }
    function savePlayersCache(players) {
        playersCache = players;
        try { localStorage.setItem(PLAYERS_CACHE_KEY, JSON.stringify(players)); } catch (e) {}
    }
    function fetchPlayers() {
        return fetch("/api/players", { headers: { "Accept": "application/json", "X-CSRF-TOKEN": "'.csrf_token().'" } })
            .then(function(res){ return res.json(); })
            .then(function(json){
                var players = json.data || [];
                savePlayersCache(players);
                return players;
            });
    }
    function openManage() {
        document.getElementById("manageDialog").style.display = "flex";
        var list = document.getElementById("manageList");
        if (playersCache !== null) {
            renderManage(playersCache);
        } else {
            list.replaceChildren();
            var loading = document.createElement("p");
            loading.className = "empty";
            loading.textContent = "Loading…";
            list.appendChild(loading);
        }
        // Always refresh in the background so the list stays current.
        fetchPlayers().then(function(players){
            renderManage(players);
        }).catch(function(){
            if (playersCache === null) {
                list.replaceChildren();
                var e = document.createElement("p");
                e.className = "empty";
                e.textContent = "Failed to load players.";
                list.appendChild(e);
            }
        });
    }
    function closeManage() { document.getElementById("manageDialog").style.display = "none"; }
    function renderManage(players) {
        var list = document.getElementById("manageList");
        list.replaceChildren();
        if (!players.length) {
            var e = document.createElement("p");
            e.className = "empty";
            e.textContent = "No players yet.";
            list.appendChild(e);
            return;
        }
        players.forEach(function(p){
            var row = document.createElement("div");
            row.className = "manage-row";
            var input = document.createElement("input");
            input.className = "manage-name";
            input.value = p.name;
            input.disabled = !!p.is_playing;
            var rating = document.createElement("span");
            rating.className = "manage-rating";
            rating.textContent = Math.round(p.rating);
            row.appendChild(input);
            row.appendChild(rating);
            if (p.is_playing) {
                var lock = document.createElement("span");
                lock.className = "manage-lock";
                lock.title = "On court — locked";
                lock.textContent = "🔒";
                row.appendChild(lock);
            }
            var save = document.createElement("button");
            save.className = "manage-btn";
            save.textContent = "Save";
            save.disabled = !!p.is_playing;
            save.addEventListener("click", function(){ saveName(input, p.id); });
            var del = document.createElement("button");
            del.className = "manage-btn manage-del";
            del.textContent = "✕";
            del.disabled = !!p.is_playing;
            del.addEventListener("click", function(){ deletePlayer(p.id); });
            row.appendChild(save);
            row.appendChild(del);
            list.appendChild(row);
        });
    }
    function saveName(input, id) {
        var name = input.value.trim();
        if (!name) return;
        fetch("/api/players/" + id, {
            method: "PATCH",
            headers: { "Content-Type": "application/json", "Accept": "application/json", "X-CSRF-TOKEN": "'.csrf_token().'" },
            body: JSON.stringify({ name: name })
        })
        .then(function(res){ return res.json().then(function(j){ return { ok: res.ok, message: j.message }; }); })
        .then(function(r){
            if (r.ok) {
                // Update the local cache immediately, then sync from the server.
                if (playersCache) {
                    for (var i = 0; i < playersCache.length; i++) {
                        if (playersCache[i].id === id) playersCache[i].name = name;
                    }
                    savePlayersCache(playersCache);
                    renderManage(playersCache);
                }
                fetchPlayers().then(renderManage);
            } else { alert(r.message || "Could not save"); }
        })
        .catch(function(){ alert("Network error"); });
    }
    function deletePlayer(id) {
        showConfirmDialog("Delete player", "Delete this player permanently? This cannot be undone.", function(){
        fetch("/api/players/" + id, {
            method: "DELETE",
            headers: { "Accept": "application/json", "X-CSRF-TOKEN": "'.csrf_token().'" }
        })
        .then(function(res){ return res.json().then(function(j){ return { ok: res.ok, message: j.message }; }); })
        .then(function(r){
            if (r.ok) {
                // Remove from the local cache immediately, then sync from the server.
                if (playersCache) {
                    var kept = [];
                    for (var i = 0; i < playersCache.length; i++) {
                        if (playersCache[i].id !== id) kept.push(playersCache[i]);
                    }
                    savePlayersCache(kept);
                    renderManage(kept);
                }
                fetchPlayers().then(renderManage);
            } else { alert(r.message || "Could not delete"); }
        })
        .catch(function(){ alert("Network error"); });
        });
    }

    // Preload the roster into local memory on startup.
    loadPlayersCache();
    fetchPlayers();
    </script>
    </body></html>';
})->middleware('auth')->name('dashboard');

// Player stats — select a player, see rating trend + performance metrics
Route::get('/stats', function () {
    $data = [
        'base' => rtrim(request()->getBasePath(), '/'),
    ];

    $__path = resource_path('views/stats.php');
    extract($data, EXTR_SKIP);
    ob_start();
    include $__path;

    return response(ob_get_clean());
})->middleware('auth')->name('stats');

// Session live view — the tablet UI (owner only)
Route::get('/sessions/{session}/live', function ($session) {
    $sessionName = 'Session #'.$session;
    $sessionStatus = 'UNKNOWN';

    try {
        $s = \App\Models\Session::with(['courts', 'sessionPlayers.player'])
            ->where('created_by', \Illuminate\Support\Facades\Auth::id())
            ->findOrFail($session);
        $sessionName = $s->name;
        $sessionStatus = $s->status->value;
    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        abort(404); // Not found, or owned by another user
    } catch (\Throwable $e) {
        // DB unreachable — still render the shell so the client can show the
        // "server unreachable" banner and keep retrying once it's back.
    }

    $data = [
        'sessionId' => (int) $session,
        'sessionName' => $sessionName,
        'sessionStatus' => $sessionStatus,
        'base' => rtrim(request()->getBasePath(), '/'),
        'appVersion' => config('courtly.app.version', 'v2.0.0'),
        'syncConfig' => config('courtly.sync'),
    ];

    $__path = resource_path('views/session-live.php');
    extract($data, EXTR_SKIP);
    ob_start();
    include $__path;

    return response(ob_get_clean());
})->middleware('auth');

