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
Route::post('/login', [AuthController::class, 'login']);
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Social login
Route::get('/auth/google/redirect', [AuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);
Route::get('/auth/facebook/redirect', [AuthController::class, 'redirectToFacebook']);
Route::get('/auth/facebook/callback', [AuthController::class, 'handleFacebookCallback']);

// Dashboard — lists all sessions dynamically
Route::get('/', function () {
    $sessions = \App\Models\Session::select('id', 'name', 'date', 'number_of_courts', 'status')
        ->orderByDesc('date')->limit(20)->get();

    $rows = '';
    foreach ($sessions as $session) {
        $status = $session->status->value;
        $rows .= '<a class="session-link" href="/sessions/'.$session->id.'/live">'
            .'<span class="session-link__name">'.e($session->name).'</span>'
            .'<span class="session-link__meta">'.e($session->date->format('d M Y')).' · '.$session->number_of_courts.' courts · <span class="tag tag--'.strtolower($status).'">'.$status.'</span></span>'
            .'</a>';
    }

    if ($rows === '') {
        $rows = '<p class="empty">No sessions yet. Create one below to get started.</p>';
    }

    return '<!DOCTYPE html><html><head><title>Courtly</title><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="icon" type="image/png" href="/assets/favicon.png?v=2">
    <link rel="stylesheet" href="/css/courtly.css?v=3">
    <style>
        body{font-family:"SF Mono","JetBrains Mono","Fira Code",monospace;background:var(--bg,#12121f);color:var(--text,#e4e4f0);margin:0;padding:40px 20px}
        .wrap{max-width:560px;margin:0 auto}
        h1{font-size:2rem;margin:0 0 4px}
        .sub{color:var(--text-muted,#8888a8);margin:0 0 24px}
        .session-link{display:block;background:var(--surface,#1e1e32);border:1px solid var(--stroke,#2e2e4a);border-radius:8px;padding:16px;margin-bottom:10px;text-decoration:none;color:var(--text,#e4e4f0);box-shadow:var(--shadow-card,0 4px 20px rgba(0,0,0,.3));transition:border-color .15s}
        .session-link:hover{border-color:var(--accent,#ff2d55)}
        .session-link__name{font-weight:700;font-size:1.05rem;display:block;margin-bottom:4px}
        .session-link__meta{font-size:.85rem;color:var(--text-muted,#8888a8)}
        .tag{display:inline-block;padding:1px 8px;border-radius:999px;font-size:.7rem;font-weight:700}
        .tag--active{background:#1a3a2a;color:#3cae67}.tag--upcoming{background:#1a2a3a;color:#5b9bd5}.tag--paused{background:#3a2a10;color:#d4a017}.tag--finished{background:#2a2a3a;color:#8a8aaa}
        .empty{color:var(--text-muted,#8888a8)}
        .card{background:var(--surface,#1e1e32);border:1px solid var(--stroke,#2e2e4a);border-radius:8px;padding:18px;margin-bottom:20px;box-shadow:var(--shadow-card,0 4px 20px rgba(0,0,0,.3))}
        .card h2{font-size:1.05rem;margin:0 0 14px;color:var(--text,#e4e4f0)}
        .field{margin-bottom:12px}
        .field label{display:block;font-size:.8rem;font-weight:700;color:var(--text-muted,#8888a8);margin-bottom:4px}
        .field input{width:100%;padding:10px;border:1px solid var(--stroke,#2e2e4a);border-radius:6px;font-size:.95rem;box-sizing:border-box;background:var(--bg,#12121f);color:var(--text,#e4e4f0)}
        .field input:focus{outline:none;border-color:var(--accent,#ff2d55)}
        .row{display:flex;gap:12px}
        .row .field{flex:1}
        .create-btn{width:100%;padding:12px;border:none;border-radius:6px;background:var(--accent,#ff2d55);color:#fff;font-size:.95rem;font-weight:700;cursor:pointer;transition:filter .15s}
        .create-btn:hover{filter:brightness(1.1)}
        .err{color:var(--accent,#ff2d55);font-size:.85rem;margin-top:8px;display:none}
        h2.list-title{margin-top:28px;margin-bottom:12px}
    </style>
    </head><body><div class="wrap">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
            <h1 style="margin:0"><img src="/assets/courtly_light.png" style="height:128px;vertical-align:middle;"></h1>
            <div style="display:flex;gap:8px;align-items:center">
                <div class="theme-toggle" id="themeToggle">
                    <button onclick="setTheme(\'light\')" title="Light">☀</button>
                    <button onclick="setTheme(\'dark\')" title="Dark">☾</button>
                    <button onclick="setTheme(\'system\')" title="System">◐</button>
                </div>
                <form method="POST" action="/logout" style="margin:0">
                    <input type="hidden" name="_token" value="'.csrf_token().'">
                    <button type="submit" style="background:transparent;border:1px solid var(--stroke,#2e2e4a);padding:8px 18px;border-radius:6px;font-size:.85rem;font-weight:700;cursor:pointer;color:var(--text-muted,#8888a8)">Logout</button>
                </form>
            </div>
        </div>
        <p class="sub">Badminton session management</p>
        <div class="card">
            <h2>New Session</h2>
            <form id="createForm">
                <div class="field"><label>Session name</label><input id="fName" type="text" placeholder="e.g. Tuesday Night Social" required></div>
                <div class="field"><label>Number of courts</label><input id="fCourts" type="number" min="1" max="8" value="3" required></div>
                <button type="submit" class="create-btn">Create Session</button>
                <div id="err" class="err"></div>
            </form>
        </div>
        <h2 class="list-title">Sessions</h2>'.$rows.'
    </div>
    <script>
    document.getElementById("createForm").addEventListener("submit", async function(e){
        e.preventDefault();
        var err = document.getElementById("err");
        err.style.display = "none";
        try {
            var res = await fetch("/api/sessions", {
                method: "POST",
                headers: {"Content-Type": "application/json", "Accept": "application/json"},
                body: JSON.stringify({
                    name: document.getElementById("fName").value.trim(),
                    number_of_courts: parseInt(document.getElementById("fCourts").value, 10)
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
    (function(){
        var t = localStorage.getItem("courtly-theme") || "system";
        function apply(s) {
            if (s === "system") document.documentElement.removeAttribute("data-theme");
            else document.documentElement.setAttribute("data-theme", s);
            var btns = document.querySelectorAll("#themeToggle button");
            btns.forEach(function(b,i){
                var modes = ["light","dark","system"];
                b.className = modes[i] === s ? "active" : "";
            });
        }
        window.setTheme = function(s) { localStorage.setItem("courtly-theme", s); apply(s); };
        apply(t);
    })();
    </script>
    </body></html>';
})->name('dashboard');

// Session live view — the tablet UI
Route::get('/sessions/{session}/live', function ($session) {
    $sessionName = 'Session #'.$session;
    $sessionStatus = 'UNKNOWN';

    try {
        $s = \App\Models\Session::with(['courts', 'sessionPlayers.player'])
            ->findOrFail($session);
        $sessionName = $s->name;
        $sessionStatus = $s->status->value;
    } catch (\Throwable $e) {
        // DB unreachable — still render the shell so the client can show the
        // "server unreachable" banner and keep retrying once it's back.
    }

    return view('session-live', [
        'sessionId' => (int) $session,
        'sessionName' => $sessionName,
        'sessionStatus' => $sessionStatus,
    ]);
});

