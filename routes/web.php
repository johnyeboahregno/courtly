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

// ── Email verification ──────────────────────────────────────────────
Route::get('/email/verify', function () {
    $base = rtrim(request()->getBasePath(), '/');
    $email = e(\Illuminate\Support\Facades\Auth::user()->email);

    $statusHtml = session('status') === 'verification-link-sent'
        ? '<p style="color:#00c764;margin:16px 0 0">A fresh verification link has been sent to <strong>'.$email.'</strong>.</p>'
        : '';

    return response('<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Verify your email — Courtly</title></head>'
        .'<body style="background:#12121f;color:#e4e4f0;font-family:system-ui,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0">'
        .'<div style="background:#1e1e32;border:1px solid #2e2e4a;border-radius:12px;padding:32px;max-width:420px;width:100%;margin:16px;box-shadow:0 4px 20px rgba(0,0,0,.3)">'
        .'<h1 style="font-size:1.3rem;margin:0 0 12px">Verify your email address</h1>'
        .'<p style="color:#8888a8;line-height:1.6;margin:0">We sent a verification link to <strong style="color:#e4e4f0">'.$email.'</strong>. Click the link in that email to activate your account.</p>'
        .$statusHtml
        .'<form method="POST" action="'.$base.'/email/verification-notification" style="margin:20px 0 0">'
        .'<input type="hidden" name="_token" value="'.csrf_token().'">'
        .'<button type="submit" style="width:100%;padding:12px;border:none;border-radius:6px;background:#ff2d55;color:#fff;font-weight:700;cursor:pointer">Resend verification email</button>'
        .'</form>'
        .'<form method="POST" action="'.$base.'/logout" style="margin:12px 0 0">'
        .'<input type="hidden" name="_token" value="'.csrf_token().'">'
        .'<button type="submit" style="width:100%;padding:12px;border:1px solid #2e2e4a;border-radius:6px;background:transparent;color:#8888a8;cursor:pointer">Logout</button>'
        .'</form>'
        .'</div></body></html>');
})->middleware('auth')->name('verification.notice');

Route::get('/email/verify/{id}/{hash}', function ($id, $hash) {
    $user = \App\Models\User::findOrFail($id);

    if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
        abort(403, 'Invalid verification link.');
    }

    if ($user->hasVerifiedEmail()) {
        return redirect()->route('dashboard')->with('status', 'Your email is already verified.');
    }

    $user->markEmailAsVerified();

    return redirect()->route('dashboard')->with('status', 'Email verified — welcome to Courtly!');
})->middleware(['auth', 'signed'])->name('verification.verify');

Route::post('/email/verification-notification', function (\Illuminate\Http\Request $request) {
    if ($request->user()->hasVerifiedEmail()) {
        return redirect()->route('dashboard');
    }

    $request->user()->sendEmailVerificationNotification();

    return redirect()->route('verification.notice')->with('status', 'verification-link-sent');
})->middleware(['auth', 'throttle:6,1'])->name('verification.send');

// Interactive Circles map — the app's landing view
Route::get('/circles', function () {
    $base = rtrim(request()->getBasePath(), '/');
    $csrf = csrf_token();

    $__path = resource_path('views/circles-map.php');
    extract(['base' => $base, 'csrf' => $csrf], EXTR_SKIP);
    ob_start();
    include $__path;

    return response(ob_get_clean());
})->middleware(['auth', 'verified'])->name('circles.map');

// Dashboard — lists the authenticated user's sessions
Route::get('/', function () {
    $base = rtrim(request()->getBasePath(), '/');

    $active = 'sessions';
    ob_start();
    include resource_path('views/partials/app-header.php');
    $headerHtml = ob_get_clean();

    $circleId = \Illuminate\Support\Facades\Auth::user()->personalCircle?->id;

    $sessions = \App\Models\Session::select('id', 'name', 'sport', 'date', 'number_of_courts', 'status', 'matchmaking_mode')
        ->where('circle_id', $circleId)
        ->orderByDesc('date')->get();

    $players = \App\Models\Player::select('id', 'name', 'rating', 'total_games', 'wins')
        ->where('circle_id', $circleId)
        ->orderByDesc('rating')
        ->orderByDesc('total_games')
        ->orderBy('name')
        ->orderBy('id')
        ->get();

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
        $sport = $session->sport?->value ?? 'badminton';

        $row = '<div class="session-row">'
            .'<a class="session-link" href="'.$base.'/sessions/'.$session->id.'/live">'
            .'<span class="session-link__name"><span class="session-row-sport" style="--session-row-sport-image:url('.$base.'/assets/'.e($sport).'.png)" aria-hidden="true"></span>'.e($session->name).'</span>'
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

    ob_start();
    include resource_path('views/partials/stats-content.php');
    $statsViewHtml = ob_get_clean();

    ob_start();
    include resource_path('views/partials/rankings-content.php');
    $rankingsViewHtml = ob_get_clean();

    return '<!DOCTYPE html><html><head><title>Courtly</title><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="'.csrf_token().'">
    <link rel="icon" type="image/png" href="'.$base.'/assets/favicon.png?v=' . config('courtly.app.version', '1.0.0') . '">
    <link rel="stylesheet" href="'.$base.'/css/courtly.css?v=' . config('courtly.app.version', '1.0.0') . '">
    <style>
        html,body{height:100%}
        body{font-family:"SF Mono","JetBrains Mono","Fira Code",monospace;margin:0;padding:0;overflow:hidden}
        .wrap{width:100%;max-width:none;height:100dvh;display:flex;flex-direction:column;box-sizing:border-box;padding:0;margin:0}
        .view{flex:1;min-height:0;overflow-y:auto;padding:20px 16px 24px;-webkit-overflow-scrolling:touch}
        h1{font-size:2rem;margin:0 0 4px}
        .sub{color:var(--text-muted);margin:0 0 24px}
        .manage-link{font-family:inherit;font-size:inherit;color:var(--text-muted);background:none;border:none;cursor:pointer;padding:0;font-weight:inherit}
        .manage-link:hover{color:var(--accent);text-decoration:underline}
        .pill-link{display:inline-flex;align-items:center;font-size:.85rem;letter-spacing:.04em;padding:8px 16px;border-radius:999px;border:1px solid var(--stroke);color:var(--text-muted);background:transparent;text-decoration:none;cursor:pointer;font-family:inherit;transition:background .15s,color .15s,border-color .15s}
        .pill-link:hover{border-color:var(--accent);color:var(--text)}
        .session-link{display:block;background:var(--surface);border:1px solid var(--stroke);border-radius:8px;padding:16px;margin-bottom:10px;text-decoration:none;color:var(--text);box-shadow:var(--shadow-card);transition:border-color .15s}
        .session-link:hover{border-color:var(--accent)}
        .session-link__name{font-weight:700;font-size:1.05rem;display:flex;align-items:center;gap:9px;margin-bottom:4px}
        .session-row-sport{width:28px;height:28px;flex:0 0 28px;background:var(--text);-webkit-mask-image:var(--session-row-sport-image);mask-image:var(--session-row-sport-image);-webkit-mask-repeat:no-repeat;mask-repeat:no-repeat;-webkit-mask-position:center;mask-position:center;-webkit-mask-size:contain;mask-size:contain;-webkit-mask-mode:luminance;mask-mode:luminance}
        .session-link__meta{font-size:.85rem;color:var(--text-muted)}
        .tag{display:inline-block;padding:1px 8px;border-radius:999px;font-size:.7rem;font-weight:700}
        .tag--active{background:var(--status-active-bg);color:var(--status-active-text)}.tag--upcoming{background:var(--status-upcoming-bg);color:var(--status-upcoming-text)}.tag--paused{background:var(--status-paused-bg);color:var(--status-paused-text)}.tag--finished{background:var(--status-finished-bg);color:var(--status-finished-text)}
        .empty{color:var(--text-muted)}
        .user-name{font-size:.85rem;font-weight:700;color:var(--text);padding:6px 12px;border:1px solid var(--stroke);border-radius:999px;background:var(--surface)}
        .tag--passed{background:var(--status-passed-bg);color:var(--status-passed-text)}
        .tag--mode{font-size:.65rem;padding:1px 8px;border-radius:999px;font-weight:700;display:inline-block}
        .tag--mode--peg{background:var(--mode-peg-bg);color:var(--mode-peg-text)}
        .tag--mode--smart{background:var(--mode-smart-bg);color:var(--mode-smart-text)}
        .session-row{display:flex;align-items:center;gap:10px;margin-bottom:10px;transition:opacity .3s ease,transform .3s ease,margin-bottom .3s ease}
        .session-row.session-row--removing{opacity:0;transform:translateX(16px);margin-bottom:0;pointer-events:none}
        .session-row .session-link{flex:1;margin-bottom:0}
        .session-delete{border:none;background:transparent;color:var(--text-muted);border-radius:6px;width:32px;height:32px;font-size:1.1rem;line-height:1;cursor:pointer;flex-shrink:0;display:flex;align-items:center;justify-content:center;transition:color .15s,background .15s}
        .session-delete:hover{color:var(--accent);background:var(--surface)}
        .past-section{margin-top:24px}
        .past-toggle{cursor:pointer;font-size:1.05rem;font-weight:700;color:var(--text-muted);list-style:none;user-select:none}
        .past-toggle::-webkit-details-marker{display:none}
        .past-toggle::before{content:\'▸ \';display:inline-block;transition:transform .15s}
        .past-section[open] .past-toggle::before{transform:rotate(90deg)}
        .past-list{margin-top:12px}
        .dialog-overlay{position:fixed;inset:0;background:rgba(0,0,0,.6);display:flex;align-items:center;justify-content:center;z-index:9999}
        #appDialog{z-index:10001}
        .dialog{background:var(--surface);border:1px solid var(--stroke);border-radius:12px;padding:24px;width:100%;max-width:360px;margin:0 16px;box-shadow:0 20px 60px rgba(0,0,0,.5)}
        .dialog__title{margin:0 0 8px;font-size:1.1rem;font-weight:800;color:var(--text)}
        .dialog__message{margin:0 0 20px;font-size:.9rem;color:var(--text-muted);line-height:1.5}
        .dialog__actions{display:flex;justify-content:flex-end;gap:10px}
        .dialog__btn{padding:9px 16px;border-radius:8px;font-size:.85rem;font-weight:700;cursor:pointer;border:1px solid transparent}
        .dialog__btn--cancel{background:transparent;border-color:var(--stroke);color:var(--text-muted)}
        .dialog__btn--cancel:hover{border-color:var(--accent);color:var(--text)}
        .dialog__btn--danger{background:var(--accent);color:#fff}
        .dialog__btn--danger:hover{filter:brightness(1.1)}
        .dialog__btn--reset{background:transparent;border-color:#d9a441;color:#d9a441}
        .dialog__btn--reset:hover{background:rgba(217,164,65,.12);color:#e0b457}
        .dialog__btn--save{background:var(--accent);color:#fff}
        .dialog__btn--save:hover{filter:brightness(1.1)}
        .card{background:var(--surface);border:1px solid var(--stroke);border-radius:8px;padding:18px;margin-bottom:20px;box-shadow:var(--shadow-card)}
        .card h2{font-size:1.05rem;margin:0 0 14px;color:var(--text)}
        .field{margin-bottom:12px}
        .field label{display:block;font-size:.8rem;font-weight:700;color:var(--text-muted);margin-bottom:4px}
        .field input{width:100%;padding:10px;border:1px solid var(--stroke);border-radius:6px;font-size:.95rem;box-sizing:border-box;background:var(--bg);color:var(--text)}
        .field input:focus{outline:none;border-color:var(--accent)}
        .field select{width:100%;padding:10px;border:1px solid var(--stroke);border-radius:6px;font-size:.95rem;box-sizing:border-box;background:var(--bg);color:var(--text);appearance:none;-webkit-appearance:none;background-image:url("data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'12\' height=\'8\'><path d=\'M1 1l5 5 5-5\' stroke=\'%238888a8\' stroke-width=\'2\' fill=\'none\' fill-rule=\'evenodd\'/></svg>");background-repeat:no-repeat;background-position:right 12px center}
        .field select:focus{outline:none;border-color:var(--accent)}
        .row{display:flex;gap:12px}
        .row .field{flex:1}
        @media(max-width:520px){.row{flex-direction:column;gap:0}}
        .create-btn{width:100%;padding:12px;border:none;border-radius:6px;background:var(--accent);color:#fff;font-size:.95rem;font-weight:700;cursor:pointer;transition:filter .15s}
        .create-btn:hover{filter:brightness(1.1)}
        .err{color:var(--accent);font-size:.85rem;margin-top:8px;display:none}
        .manage-row{display:flex;align-items:center;gap:8px;margin-bottom:8px}
        .manage-name{flex:1;min-width:0;padding:8px 10px;border:none;border-radius:6px;background:var(--bg);color:var(--text);font-size:.9rem}
        .manage-name:disabled{opacity:.5}
        .manage-gender-toggle{width:32px;height:32px;padding:0;border:1px solid var(--stroke);border-radius:50%;background:var(--bg);display:flex;align-items:center;justify-content:center;cursor:pointer}
        .manage-gender-toggle:disabled{opacity:.5;cursor:not-allowed}
        .manage-gender-toggle::before{content:"";width:12px;height:12px;border:1px solid rgba(0,0,0,.45);border-radius:50%;background:#9ca3af}
        .manage-gender-toggle--male::before{background:#000}
        .manage-gender-toggle--female::before{background:#fff}
        .manage-rating{font-size:.8rem;color:var(--text-muted);min-width:34px;text-align:center}
        .manage-lock{font-size:.9rem}
        .manage-btn{border:1px solid var(--stroke);background:transparent;color:var(--text-muted);border-radius:6px;padding:7px 10px;cursor:pointer;font-size:.85rem;font-weight:700}
        .manage-btn:hover{border-color:var(--accent);color:var(--text)}
        .manage-btn:disabled{opacity:.4;cursor:not-allowed}
        .manage-del:hover{color:var(--accent)}
        h2.list-title{margin-top:28px;margin-bottom:12px}
        .brand-word{font-family:"Arial Black","Space Grotesk","Manrope",sans-serif;font-size:1.7rem;font-weight:900;letter-spacing:.01em;line-height:1;color:var(--text)}
    </style>
    </head><body><div class="wrap">
        '.$headerHtml.'
        <div class="view" id="view-sessions">
        <div class="card">
            <h2>New Session</h2>
            <form id="createForm">
                <div class="field"><input id="fName" type="text" placeholder="e.g. Tuesday Night Social" required></div>
                <div class="row">
                    <div class="field"><label>Sport</label><select id="fSport"><option value="badminton" selected>Badminton</option><option value="tennis">Tennis</option><option value="pickleball">Pickleball</option><option value="padel">Padel</option><option value="squash">Squash</option></select></div>
                    <div class="field"><label>Courts</label><input id="fCourts" type="number" min="1" max="8" value="3" required></div>
                    <div class="field"><label>Session type</label><select id="fType" onchange="document.getElementById(\'fFormatField\').style.display = this.value === \'tournament\' ? \'block\' : \'none\'"><option value="casual" selected>Casual</option><option value="tournament">Tournament</option></select></div>
                </div>
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
        <div class="view" id="view-manage" hidden>
        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">
                <h2 style="margin:0">Manage Players</h2>
                <button type="button" class="pill-link" onclick="closeManage()">Close</button>
            </div>
            <p class="dialog__message" style="margin:0 0 16px">Edit names, gender, or delete players. Players on court are locked.</p>
            <div id="manageList" style="margin-bottom:16px"></div>
            <div style="display:flex;justify-content:flex-end;gap:10px">
                <button type="button" class="dialog__btn dialog__btn--reset" onclick="resetAllPlayers()" title="Reset all player ratings" aria-label="Reset all player ratings">Reset All</button>
                <button type="button" class="dialog__btn dialog__btn--save" onclick="saveAllPlayers()" title="Save all player changes" aria-label="Save all player changes">Save</button>
            </div>
        </div>
        </div>
        <div class="view" id="view-circles" hidden>
        <div class="card">
            <h2>Your Circles</h2>
            <p class="dialog__message" style="margin:0 0 16px">A circle is a shared roster of players and sessions. Share your invite code so others can join it.</p>
            <div id="circleList" style="margin-bottom:16px"></div>
        </div>
        <div class="card">
            <h2>Join a Circle</h2>
            <p class="dialog__message" style="margin:0 0 16px">Enter an invite code that was shared with you.</p>
            <div style="display:flex;gap:8px">
                <input id="joinCode" type="text" placeholder="e.g. AB12CD34" style="flex:1">
                <button type="button" class="create-btn" style="width:auto" onclick="joinCircle()">Join</button>
            </div>
            <div id="joinErr" class="err"></div>
        </div>
        </div>
        <div class="view" id="view-stats" hidden>'.$statsViewHtml.'</div>
        <div class="view" id="view-rankings" hidden>'.$rankingsViewHtml.'</div>
    </div>
    <div class="dialog-overlay" id="appDialog" style="display:none">
        <div class="dialog">
            <h3 class="dialog__title" id="appDialogTitle"></h3>
            <p class="dialog__message" id="appDialogMessage"></p>
            <div class="dialog__actions" id="appDialogActions"></div>
        </div>
    </div>
    <script>
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

    function showConfirmDialog(title, message, onConfirm, actionLabel) {
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
        ok.textContent = actionLabel || "Delete";
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
    var manageDrafts = {};
    var manageOriginals = {};

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
    function showView(name) {
        var views = ["sessions", "stats", "rankings", "manage", "circles"];
        for (var i = 0; i < views.length; i++) {
            var el = document.getElementById("view-" + views[i]);
            if (el) { el.hidden = (views[i] !== name); }
        }
        var badges = document.querySelectorAll(".dashboard-subhead__actions .pill-link[data-view]");
        for (var j = 0; j < badges.length; j++) {
            var b = badges[j];
            var active = b.getAttribute("data-view") === name;
            b.classList.toggle("pill-link--active", active);
            if (active) { b.setAttribute("aria-current", "page"); } else { b.removeAttribute("aria-current"); }
        }
        if (name === "circles") { loadCircles(); }
    }
    function openManage() {
        showView("manage");
        manageDrafts = {};
        manageOriginals = {};
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
        var manageView = document.getElementById("view-manage");
        if (manageView) { manageView.scrollTop = 0; }
    }
    function closeManage() { showView("sessions"); }
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
            if (!manageOriginals[p.id]) {
                manageOriginals[p.id] = { name: p.name, gender: p.gender || null };
            }
            if (!manageDrafts[p.id]) {
                manageDrafts[p.id] = { name: p.name, gender: p.gender || null };
            }
            var draft = manageDrafts[p.id];
            var row = document.createElement("div");
            row.className = "manage-row";
            var input = document.createElement("input");
            input.className = "manage-name";
            input.value = draft.name;
            input.disabled = !!p.is_playing;
            input.addEventListener("input", function(){ manageDrafts[p.id].name = input.value; });
            var gender = document.createElement("button");
            gender.type = "button";
            gender.className = "manage-gender-toggle";
            gender.setAttribute("aria-label", "Toggle gender for " + p.name);
            gender.disabled = !!p.is_playing;
            setGenderToggle(gender, draft.gender);
            gender.addEventListener("click", function(){
                var nextGender = gender.dataset.gender === "MALE" ? "FEMALE" : "MALE";
                setGenderToggle(gender, nextGender);
                manageDrafts[p.id].gender = nextGender;
            });
            var rating = document.createElement("span");
            rating.className = "manage-rating";
            rating.textContent = Math.round(p.rating);
            row.appendChild(input);
            row.appendChild(gender);
            row.appendChild(rating);
            if (p.is_playing) {
                var lock = document.createElement("span");
                lock.className = "manage-lock";
                lock.title = "On court — locked";
                lock.textContent = "🔒";
                row.appendChild(lock);
            }
            var del = document.createElement("button");
            del.className = "manage-btn manage-del";
            del.textContent = "✕";
            del.disabled = !!p.is_playing;
            del.addEventListener("click", function(){ deletePlayer(p.id); });
            var reset = document.createElement("button");
            reset.className = "manage-btn manage-reset";
            reset.textContent = "Reset";
            reset.disabled = !!p.is_playing;
            reset.title = "Reset rating to the default";
            reset.setAttribute("aria-label", "Reset rating to the default");
            reset.addEventListener("click", function(){ resetPlayer(p.id); });
            row.appendChild(reset);
            row.appendChild(del);
            list.appendChild(row);
        });
    }
    function setGenderToggle(button, value) {
        button.dataset.gender = value || "";
        button.classList.toggle("manage-gender-toggle--male", value === "MALE");
        button.classList.toggle("manage-gender-toggle--female", value === "FEMALE");
        button.title = value === "MALE" ? "Male — click to switch to female" : value === "FEMALE" ? "Female — click to switch to male" : "Gender not set — click to set male";
        button.setAttribute("aria-pressed", value ? "true" : "false");
    }
    function saveAllPlayers() {
        var changes = [];
        Object.keys(manageDrafts).forEach(function(id){
            var draft = manageDrafts[id];
            var original = manageOriginals[id];
            var name = draft.name.trim();
            if (!name) return;
            if (name !== original.name || draft.gender !== original.gender) {
                changes.push({ id: id, name: name, gender: draft.gender || null });
            }
        });
        if (!changes.length) return;

        var saveButton = document.querySelector("#view-manage .dialog__btn--save");
        if (saveButton) saveButton.disabled = true;
        Promise.all(changes.map(function(change){
            return fetch("/api/players/" + change.id, {
                method: "PATCH",
                headers: { "Content-Type": "application/json", "Accept": "application/json", "X-CSRF-TOKEN": "'.csrf_token().'" },
                body: JSON.stringify({ name: change.name, gender: change.gender })
            }).then(function(res){ return res.json().then(function(j){ return { ok: res.ok, message: j.message }; }); });
        })).then(function(results){
            var failed = results.find(function(result){ return !result.ok; });
            if (failed) {
                alert(failed.message || "Could not save all player changes");
                return;
            }
            fetchPlayers().then(function(players){
                manageDrafts = {};
                manageOriginals = {};
                renderManage(players);
            });
        }).catch(function(){ alert("Network error"); })
        .finally(function(){ if (saveButton) saveButton.disabled = false; });
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

    function resetPlayer(id) {
        showConfirmDialog("Reset player", "Reset this player\'s rating, games and history to the default starting values?", function(){
        fetch("/api/players/" + id + "/reset-rating", {
            method: "POST",
            headers: { "Accept": "application/json", "X-CSRF-TOKEN": "'.csrf_token().'" }
        })
        .then(function(res){ return res.json().then(function(j){ return { ok: res.ok, message: j.message }; }); })
        .then(function(r){
            if (r.ok) {
                fetchPlayers().then(renderManage);
            } else { alert(r.message || "Could not reset player"); }
        })
        .catch(function(){ alert("Network error"); });
        }, "Reset");
    }

    function resetAllPlayers() {
        showConfirmDialog("Reset all players", "Reset every player\'s rating, games and history to the default starting values? Players currently on court are skipped. This cannot be undone.", function(){
        fetch("/api/players/reset-all", {
            method: "POST",
            headers: { "Accept": "application/json", "X-CSRF-TOKEN": "'.csrf_token().'" }
        })
        .then(function(res){ return res.json().then(function(j){ return { ok: res.ok, message: j.message }; }); })
        .then(function(r){
            if (r.ok) {
                fetchPlayers().then(renderManage);
            } else { alert(r.message || "Could not reset players"); }
        })
        .catch(function(){ alert("Network error"); });
        }, "Reset all");
    }

    // Preload the roster into local memory on startup.
    loadPlayersCache();
    fetchPlayers();
    </script>
    <script>
    function renderCircle(c){
        var row = document.createElement("div");
        row.className = "manage-row";
        var name = document.createElement("span");
        name.className = "manage-name";
        name.style.background = "none";
        name.style.border = "none";
        name.textContent = c.name;
        row.appendChild(name);
        if (c.is_admin) {
            name.appendChild(document.createTextNode(" "));
            var badge = document.createElement("span");
            badge.className = "tag tag--active";
            badge.style.fontSize = ".65rem";
            badge.textContent = "YOURS";
            name.appendChild(badge);
        }
        var meta = document.createElement("span");
        meta.className = "manage-rating";
        meta.style.minWidth = "110px";
        meta.style.textAlign = "right";
        meta.textContent = c.player_count + " players";
        row.appendChild(meta);
        if (c.invite_code) {
            var code = document.createElement("span");
            code.style.marginLeft = "12px";
            code.style.letterSpacing = ".06em";
            code.textContent = "Invite code: " + c.invite_code;
            row.appendChild(code);
        }
        return row;
    }
    function loadCircles(){
        fetch("/api/circles", {headers:{"Accept":"application/json"}}).then(function(r){ return r.json(); }).then(function(j){
            var list = document.getElementById("circleList");
            list.replaceChildren();
            var circles = j.data || [];
            if (!circles.length) {
                var e = document.createElement("p");
                e.className = "empty";
                e.textContent = "No circles yet.";
                list.appendChild(e);
                return;
            }
            circles.forEach(function(c){ list.appendChild(renderCircle(c)); });
        }).catch(function(){
            var list = document.getElementById("circleList");
            list.replaceChildren();
            var e = document.createElement("p");
            e.className = "empty";
            e.textContent = "Failed to load circles.";
            list.appendChild(e);
        });
    }
    function joinCircle(){
        var code = document.getElementById("joinCode").value.trim();
        var err = document.getElementById("joinErr");
        err.style.display = "none";
        if (!code) { err.textContent = "Enter an invite code."; err.style.display = "block"; return; }
        fetch("/api/circles/join", {method:"POST", headers:{"Content-Type":"application/json","Accept":"application/json","X-CSRF-TOKEN":"'.csrf_token().'"}, body:JSON.stringify({invite_code:code})}).then(function(r){ return r.json().then(function(j){ return {ok:r.ok, message:j.message}; }); }).then(function(r){
            if (r.ok) { document.getElementById("joinCode").value = ""; loadCircles(); }
            else { err.textContent = r.message || "Could not join."; err.style.display = "block"; }
        }).catch(function(){ err.textContent = "Network error."; err.style.display = "block"; });
    }
    </script>
    </body></html>';
})->middleware(['auth', 'verified'])->name('dashboard');

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
})->middleware(['auth', 'verified'])->name('stats');

// Player rankings — all players in the authenticated user's roster
Route::get('/rankings', function () {
    $data = [
        'base' => rtrim(request()->getBasePath(), '/'),
        'players' => \App\Models\Player::select('id', 'name', 'rating', 'total_games', 'wins')
            ->where('circle_id', \Illuminate\Support\Facades\Auth::user()->personalCircle?->id)
            ->orderByDesc('rating')
            ->orderByDesc('total_games')
            ->orderBy('name')
            ->orderBy('id')
            ->get(),
    ];

    $__path = resource_path('views/rankings.php');
    extract($data, EXTR_SKIP);
    ob_start();
    include $__path;

    return response(ob_get_clean());
})->middleware(['auth', 'verified'])->name('rankings');

// Session live view — the tablet UI (owner only)
Route::get('/sessions/{session}/live', function ($session) {
    $sessionName = 'Session #'.$session;
    $sessionStatus = 'UNKNOWN';

    try {
        $s = \App\Models\Session::with(['courts', 'sessionPlayers.player'])
            ->whereIn('circle_id', \Illuminate\Support\Facades\Auth::user()->circles()->pluck('circles.id'))
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
        'appVersion' => config('courtly.app.version', '1.0.0'),
        'syncConfig' => config('courtly.sync'),
    ];

    $__path = resource_path('views/session-live.php');
    extract($data, EXTR_SKIP);
    ob_start();
    include $__path;

    return response(ob_get_clean());
})->middleware(['auth', 'verified']);

