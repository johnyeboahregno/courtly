<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title>Player Stats — Courtly</title>
    <link rel="icon" type="image/png" href="<?= e($base ?? '') ?>/assets/favicon.png?v=3">
    <link rel="stylesheet" href="<?= e($base ?? '') ?>/css/courtly.css?v=24">
    <style>
        .stats-wrap { max-width: 920px; margin: 0 auto; padding: 24px 20px 64px; }
        .stats-head { display: flex; align-items: center; gap: 14px; margin-bottom: 22px; }
        .stats-head h1 { font-size: 1.4rem; margin: 0; }

        .stats-select { margin-bottom: 24px; }
        .stats-select label { display: block; font-size: .78rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--text-muted); margin-bottom: 6px; }

        .autocomplete { position: relative; max-width: 460px; }
        .autocomplete input {
            width: 100%; padding: 12px 14px; font-size: 1rem; font-family: inherit;
            border: 1px solid var(--stroke); border-radius: var(--radius);
            background: var(--surface); color: var(--text); box-sizing: border-box;
        }
        .autocomplete input:focus { outline: none; border-color: var(--accent); }
        .autocomplete__menu {
            position: absolute; top: calc(100% + 4px); left: 0; right: 0; z-index: 60;
            background: var(--surface); border: 1px solid var(--stroke);
            border-radius: var(--radius); box-shadow: var(--shadow-card);
            max-height: 280px; overflow: auto; display: none;
        }
        .autocomplete__menu.open { display: block; }
        .autocomplete__item {
            display: flex; justify-content: space-between; align-items: center; gap: 10px;
            padding: 11px 14px; cursor: pointer; font-size: .95rem;
        }
        .autocomplete__item:hover, .autocomplete__item.active { background: var(--bg-accent, #1a1a2e); }
        .autocomplete__item .ac-rating { color: var(--text-muted); font-size: .78rem; }

        .stats-empty { color: var(--text-muted); padding: 48px 0; text-align: center; }

        .stats-player { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
        .stats-player h2 { margin: 0; font-size: 1.5rem; }
        .tag { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: .72rem; font-weight: 700; }
        .tag--rating { background: #10243a; color: #5b9bd5; }
        .tag--provisional { background: #3a2c10; color: #e0a91a; }
        .tag--established { background: #1a3a2a; color: #3cae67; }

        .stat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; margin-bottom: 20px; }
        .stat-card {
            background: var(--surface); border: 1px solid var(--stroke);
            border-radius: var(--radius); padding: 14px 16px; box-shadow: var(--shadow-soft, 0 2px 8px rgba(0,0,0,.25));
        }
        .stat-card__label { font-size: .72rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--text-muted); margin-bottom: 8px; }
        .stat-card__value { font-size: 1.35rem; font-weight: 800; line-height: 1.1; }
        .stat-card__value--good { color: var(--court-green-light, #3cae67); }
        .stat-card__value--bad { color: #d47a8a; }
        .stat-card__sub { font-size: .78rem; color: var(--text-muted); margin-top: 6px; line-height: 1.4; }

        .form-chips { display: flex; gap: 4px; flex-wrap: wrap; margin-top: 2px; }
        .form-chip {
            width: 26px; height: 26px; display: flex; align-items: center; justify-content: center;
            border-radius: 6px; font-size: .72rem; font-weight: 800;
        }
        .form-chip--w { background: #1a3a2a; color: #3cae67; }
        .form-chip--l { background: #3a2024; color: #d47a8a; }

        .chart-card {
            background: var(--surface); border: 1px solid var(--stroke);
            border-radius: var(--radius); padding: 18px; box-shadow: var(--shadow-card);
        }
        .chart-card h3 { margin: 0 0 14px; font-size: 1rem; }
        .chart-svg { width: 100%; height: auto; display: block; }
        .chart-grid { stroke: var(--stroke); }
        .chart-label { fill: var(--text-muted); font-size: 11px; }
        .chart-line { stroke: var(--accent); fill: none; stroke-width: 2.5; stroke-linejoin: round; stroke-linecap: round; }
        .chart-area { fill: var(--accent); opacity: .12; }
        .chart-dot { fill: var(--accent); stroke: var(--surface); stroke-width: 1.5; }
        .chart-empty { color: var(--text-muted); font-size: .9rem; padding: 16px 0; }
    </style>
</head>
<body>
<div class="stats-wrap">
    <header class="stats-head">
        <a href="<?= e($base ?? '') ?>/" class="back-btn" title="Back to dashboard">←</a>
        <h1>Player Stats</h1>
        <button type="button" class="theme-switch" id="themeSwitch" onclick="toggleCourtlyTheme()" aria-label="Switch theme" title="Switch theme">☾</button>
    </header>

    <div class="stats-select">
        <label for="playerSelect">Select a player</label>
        <div class="autocomplete">
            <input id="playerSelect" type="text" placeholder="Type a name…" autocomplete="off" spellcheck="false">
            <div id="acMenu" class="autocomplete__menu" role="listbox"></div>
        </div>
    </div>

    <div id="emptyState" class="stats-empty">Select a player above to see their stats.</div>

    <div id="statsContent" hidden>
        <section class="stats-player">
            <h2 id="playerName"></h2>
            <span id="ratingBadge" class="tag tag--rating"></span>
            <span id="statusBadge" class="tag"></span>
        </section>

        <section id="statGrid" class="stat-grid"></section>

        <section class="chart-card">
            <h3>Rating over time</h3>
            <div id="chart"></div>
        </section>
    </div>
</div>

<script>
function courtlyUpdateThemeIcon() {
    var button = document.getElementById('themeSwitch');
    if (!button) return;
    var light = document.documentElement.getAttribute('data-theme') === 'light';
    button.textContent = light ? '☾' : '☀';
    button.title = light ? 'Switch to dark theme' : 'Switch to light theme';
    button.setAttribute('aria-label', button.title);
}
function toggleCourtlyTheme() {
    var isLight = document.documentElement.getAttribute('data-theme') === 'light';
    var next = isLight ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('courtly-theme', next);
    courtlyUpdateThemeIcon();
}
(function() {
    var stored = localStorage.getItem('courtly-theme');
    if (stored === 'light' || stored === 'dark') document.documentElement.setAttribute('data-theme', stored);
    courtlyUpdateThemeIcon();
})();

(function () {
    'use strict';

    var BASE = <?= json_encode($base ?? '') ?>;
    var CACHE_KEY = 'courtly.playersCache.v1';

    var input = document.getElementById('playerSelect');
    var menu = document.getElementById('acMenu');
    var emptyState = document.getElementById('emptyState');
    var statsContent = document.getElementById('statsContent');
    var playerName = document.getElementById('playerName');
    var ratingBadge = document.getElementById('ratingBadge');
    var statusBadge = document.getElementById('statusBadge');
    var statGrid = document.getElementById('statGrid');
    var chartEl = document.getElementById('chart');

    var players = [];
    var filtered = [];
    var activeIndex = -1;
    var selectedId = null;

    function esc(s) {
        return String(s).replace(/[&<>"']/g, function (ch) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
        });
    }

    function r0(x) { return Math.round(Number(x)); }
    function signed(x) { return (x > 0 ? '+' : '') + Number(x).toFixed(2); }

    var MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    function fmtDate(iso) {
        if (!iso) { return ''; }
        var parts = iso.split('-');
        if (parts.length !== 3) { return iso; }
        return parseInt(parts[2], 10) + ' ' + MONTHS[parseInt(parts[1], 10) - 1] + ' ' + parts[0];
    }

    // ── Roster (autocomplete source) ───────────────────────────────────
    function loadPlayers() {
        try {
            var raw = localStorage.getItem(CACHE_KEY);
            if (raw) { players = JSON.parse(raw); }
        } catch (e) { players = []; }

        fetch(BASE + '/api/players', { headers: { 'Accept': 'application/json' } })
            .then(function (res) { return res.json(); })
            .then(function (json) {
                players = json.data || [];
                try { localStorage.setItem(CACHE_KEY, JSON.stringify(players)); } catch (e) {}
                if (players.length === 0 && !selectedId) {
                    emptyState.textContent = 'No players yet — create a session and add players first.';
                    emptyState.hidden = false;
                }
            })
            .catch(function () { /* keep cached list on failure */ });
    }

    function filter(query) {
        var q = query.toLowerCase();
        if (!q) { return players.slice(0, 10); }
        return players.filter(function (p) {
            return p.name.toLowerCase().indexOf(q) !== -1;
        }).slice(0, 10);
    }

    function openMenu(items) {
        filtered = items;
        activeIndex = -1;
        if (!items.length) { menu.classList.remove('open'); return; }
        menu.innerHTML = items.map(function (p, i) {
            return '<div class="autocomplete__item" data-index="' + i + '" data-id="' + p.id + '" role="option">'
                + '<span>' + esc(p.name) + '</span>'
                + '<span class="ac-rating">' + r0(p.rating) + '</span>'
                + '</div>';
        }).join('');
        menu.classList.add('open');
    }

    function closeMenu() { menu.classList.remove('open'); }

    function setActive() {
        var items = menu.querySelectorAll('.autocomplete__item');
        for (var i = 0; i < items.length; i++) {
            items[i].classList.toggle('active', i === activeIndex);
        }
        if (items[activeIndex]) { items[activeIndex].scrollIntoView({ block: 'nearest' }); }
    }

    input.addEventListener('input', function () {
        selectedId = null;
        openMenu(filter(input.value));
    });
    input.addEventListener('focus', function () { openMenu(filter(input.value)); });
    input.addEventListener('keydown', function (e) {
        var items = menu.querySelectorAll('.autocomplete__item');
        if (!items.length) { return; }
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            activeIndex = (activeIndex + 1) % items.length;
            setActive();
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            activeIndex = (activeIndex - 1 + items.length) % items.length;
            setActive();
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (activeIndex >= 0 && filtered[activeIndex]) { selectById(filtered[activeIndex].id); }
        } else if (e.key === 'Escape') {
            closeMenu();
        }
    });

    menu.addEventListener('click', function (e) {
        var item = e.target.closest('.autocomplete__item');
        if (!item) { return; }
        selectById(parseInt(item.getAttribute('data-id'), 10));
    });

    document.addEventListener('click', function (e) {
        if (!e.target.closest('.autocomplete')) { closeMenu(); }
    });

    // ── Stats fetching / rendering ─────────────────────────────────────
    function find(id) {
        for (var i = 0; i < players.length; i++) {
            if (players[i].id === id) { return players[i]; }
        }
        return null;
    }

    function selectById(id) {
        selectedId = id;
        var p = find(id);
        if (p) { input.value = p.name; }
        closeMenu();
        fetchStats(id);
        if (history.replaceState) {
            history.replaceState(null, '', BASE + '/stats?player=' + id);
        }
    }

    function fetchStats(id) {
        emptyState.hidden = true;
        statsContent.hidden = false;
        statGrid.innerHTML = '<div class="stat-card"><div class="stat-card__label">Loading</div><div class="stat-card__value">…</div></div>';
        chartEl.innerHTML = '';

        fetch(BASE + '/api/players/' + id + '/stats', { headers: { 'Accept': 'application/json' } })
            .then(function (res) {
                if (!res.ok) { throw new Error('failed'); }
                return res.json();
            })
            .then(function (json) { renderStats(json.data); })
            .catch(function () {
                emptyState.textContent = 'Could not load stats. Try again.';
                emptyState.hidden = false;
                statsContent.hidden = true;
            });
    }

    function renderStats(data) {
        emptyState.hidden = true;
        statsContent.hidden = false;

        input.value = data.name;
        playerName.textContent = data.name;

        ratingBadge.textContent = 'Rating ' + r0(data.summary.rating);
        statusBadge.textContent = data.summary.rating_status;
        statusBadge.className = 'tag ' + (data.summary.rating_status === 'ESTABLISHED' ? 'tag--established' : 'tag--provisional');

        renderCards(data);
        renderChart(data.rating_history || []);
    }

    function renderCards(data) {
        var s = data.summary;

        var tm = s.most_common_teammate;
        var op = s.toughest_opponent;
        var streak = s.current_streak || { type: null, length: 0 };

        var cards = [
            { label: 'Current rating', value: String(r0(s.rating)), sub: 'Peak ' + r0(s.peak_rating) + ' · Low ' + r0(s.low_rating) },
            { label: 'Record', value: s.wins + '–' + s.losses, sub: s.win_percentage + '% win rate' },
            { label: 'Form (last 10)', form: data.form || [] },
            { label: 'Current streak', value: streak.length ? streak.length + (streak.type === 'WIN' ? 'W' : 'L') : '—', sub: streak.type ? (streak.type === 'WIN' ? 'On a win streak' : 'On a losing streak') : 'No rated games yet' },
            { label: 'Best streaks', value: s.longest_win_streak + 'W / ' + s.longest_loss_streak + 'L', sub: 'Longest win / loss runs' },
            { label: 'Momentum', value: signed(s.rating_momentum), sub: 'Avg rating change · last 5 games', good: s.rating_momentum > 0, bad: s.rating_momentum < 0 },
            { label: 'Upset wins', value: String(s.upset_wins), sub: s.upset_rate == null ? 'No underdog games yet' : s.upset_rate + '% as underdog' },
            { label: 'Clutch rate', value: s.clutch_rate == null ? '—' : s.clutch_rate + '%', sub: 'Close games (40–60% odds)' },
            { label: 'Sessions', value: String(s.sessions_attended), sub: s.avg_games_per_session + ' games/session avg' },
            { label: 'Favourite teammate', value: tm ? tm.name : '—', sub: tm ? (tm.games + ' games · ' + Math.round(tm.wins / tm.games * 100) + '% wins') : 'Play with someone first' },
            { label: 'Toughest opponent', value: op ? op.name : '—', sub: op ? (op.games + ' meetings · ' + op.wins + '–' + op.losses) : 'Face someone first' }
        ];

        statGrid.innerHTML = cards.map(function (c) {
            var valueHtml;
            if (c.form !== undefined) {
                if (!c.form.length) {
                    valueHtml = '<div class="stat-card__value">—</div>';
                } else {
                    valueHtml = '<div class="form-chips">' + c.form.map(function (r) {
                        return '<span class="form-chip form-chip--' + r.toLowerCase() + '">' + r + '</span>';
                    }).join('') + '</div>';
                }
            } else {
                var cls = 'stat-card__value';
                if (c.good) { cls += ' stat-card__value--good'; }
                if (c.bad) { cls += ' stat-card__value--bad'; }
                valueHtml = '<div class="' + cls + '">' + esc(c.value) + '</div>';
            }
            return '<div class="stat-card">'
                + '<div class="stat-card__label">' + esc(c.label) + '</div>'
                + valueHtml
                + (c.sub ? '<div class="stat-card__sub">' + esc(c.sub) + '</div>' : '')
                + '</div>';
        }).join('');
    }

    function renderChart(points) {
        if (!points.length) {
            chartEl.innerHTML = '<p class="chart-empty">No rated games yet — play some matches to see the trend.</p>';
            return;
        }

        var W = 720, H = 260, padL = 46, padR = 14, padT = 14, padB = 28;
        var innerW = W - padL - padR;
        var innerH = H - padT - padB;

        var ratings = points.map(function (p) { return p.rating; });
        var min = Math.min.apply(null, ratings);
        var max = Math.max.apply(null, ratings);
        if (min === max) { min -= 2; max += 2; }
        var pad = (max - min) * 0.15;
        min -= pad; max += pad;
        var span = max - min;

        function x(i) {
            return points.length === 1 ? padL + innerW / 2 : padL + (i / (points.length - 1)) * innerW;
        }
        function y(r) { return padT + (1 - (r - min) / span) * innerH; }

        var path = points.map(function (p, i) {
            return (i === 0 ? 'M' : 'L') + x(i).toFixed(1) + ' ' + y(p.rating).toFixed(1);
        }).join(' ');
        var areaPath = 'M' + x(0).toFixed(1) + ' ' + (padT + innerH).toFixed(1) + ' '
            + path.slice(1) + ' L' + x(points.length - 1).toFixed(1) + ' ' + (padT + innerH).toFixed(1) + ' Z';

        var grid = '', yLabels = '';
        var ticks = 4;
        for (var t = 0; t <= ticks; t++) {
            var val = min + (span * t / ticks);
            var yy = y(val).toFixed(1);
            grid += '<line class="chart-grid" x1="' + padL + '" y1="' + yy + '" x2="' + (W - padR) + '" y2="' + yy + '" stroke-dasharray="3 5" opacity="0.5"></line>';
            yLabels += '<text class="chart-label" x="' + (padL - 8) + '" y="' + (parseFloat(yy) + 4).toFixed(1) + '" text-anchor="end">' + Math.round(val) + '</text>';
        }

        var dots = points.map(function (p, i) {
            var tip = (p.date || '') + ' — ' + p.rating.toFixed(1) + ' (' + (p.change >= 0 ? '+' : '') + p.change.toFixed(2) + ') ' + (p.result || '');
            return '<circle class="chart-dot" cx="' + x(i).toFixed(1) + '" cy="' + y(p.rating).toFixed(1) + '" r="3.5"><title>' + esc(tip) + '</title></circle>';
        }).join('');

        var xLabels = '';
        if (points.length > 1) {
            xLabels = '<text class="chart-label" x="' + padL + '" y="' + (H - 6) + '" text-anchor="start">' + esc(fmtDate(points[0].date)) + '</text>'
                + '<text class="chart-label" x="' + (W - padR) + '" y="' + (H - 6) + '" text-anchor="end">' + esc(fmtDate(points[points.length - 1].date)) + '</text>';
        }

        chartEl.innerHTML = '<svg class="chart-svg" viewBox="0 0 ' + W + ' ' + H + '" role="img" aria-label="Rating over time">'
            + grid + yLabels
            + '<path class="chart-area" d="' + areaPath + '"></path>'
            + '<path class="chart-line" d="' + path + '"></path>'
            + dots + xLabels
            + '</svg>';
    }

    // ── Boot ───────────────────────────────────────────────────────────
    loadPlayers();

    var params = new URLSearchParams(location.search);
    var deepId = parseInt(params.get('player'), 10);
    if (deepId) {
        selectById(deepId);
    }
})();
</script>
</body>
</html>
