<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — Courtly (PHP-rendered Vue.js UI)
|--------------------------------------------------------------------------
*/

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
    <style>
        body{font-family:sans-serif;background:#eef3f1;color:#172026;margin:0;padding:40px 20px}
        .wrap{max-width:560px;margin:0 auto}
        h1{font-size:2rem;margin:0 0 4px}
        .sub{color:#5f707f;margin:0 0 24px}
        .session-link{display:block;background:#fff;border:1px solid #d7e1de;border-radius:12px;padding:16px;margin-bottom:10px;text-decoration:none;color:#172026;box-shadow:0 4px 10px rgba(26,40,50,.06);transition:border-color .15s}
        .session-link:hover{border-color:#2f9a58}
        .session-link__name{font-weight:700;font-size:1.05rem;display:block;margin-bottom:4px}
        .session-link__meta{font-size:.85rem;color:#5f707f}
        .tag{display:inline-block;padding:1px 8px;border-radius:999px;font-size:.7rem;font-weight:700}
        .tag--active{background:#d7f6e2;color:#0e6b3a}.tag--upcoming{background:#d9e8ff;color:#174ea8}.tag--paused{background:#ffefc8;color:#8a5200}.tag--finished{background:#e6ebf0;color:#46515b}
        .empty{color:#5f707f}
        .card{background:#fff;border:1px solid #d7e1de;border-radius:14px;padding:18px;margin-bottom:20px;box-shadow:0 4px 10px rgba(26,40,50,.06)}
        .card h2{font-size:1.05rem;margin:0 0 14px}
        .field{margin-bottom:12px}
        .field label{display:block;font-size:.8rem;font-weight:700;color:#5f707f;margin-bottom:4px}
        .field input{width:100%;padding:10px;border:1px solid #ccd8d3;border-radius:9px;font-size:.95rem;box-sizing:border-box}
        .field input:focus{outline:none;border-color:#2f9a58}
        .row{display:flex;gap:12px}
        .row .field{flex:1}
        .create-btn{width:100%;padding:12px;border:none;border-radius:10px;background:#0f62fe;color:#fff;font-size:.95rem;font-weight:700;cursor:pointer;transition:filter .15s}
        .create-btn:hover{filter:brightness(1.05)}
        .err{color:#c0392b;font-size:.85rem;margin-top:8px;display:none}
        h2.list-title{margin-top:28px}
    </style>
    </head><body><div class="wrap">
        <h1><img src="/assets/courtly_dark.png" style="height:36px;vertical-align:middle;margin-right:8px;">Courtly</h1><p class="sub">Badminton session management</p>
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
    </body></html>';
})->name('login');

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
