<?php

/** @var string $base */
/** @var \Illuminate\Support\Collection $players */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title>Rankings - Courtly</title>
    <link rel="icon" type="image/png" href="<?= e($base ?? '') ?>/assets/favicon.png?v=3">
    <link rel="stylesheet" href="<?= e($base ?? '') ?>/css/courtly.css?v=24">
    <style>
        .rankings-wrap { max-width: 920px; margin: 0 auto; padding: 24px 20px 64px; }
        .rankings-head { display: flex; align-items: center; gap: 14px; margin-bottom: 22px; }
        .rankings-head h1 { font-size: 1.4rem; margin: 0; }
        .rankings-card { background: var(--surface); border: 1px solid var(--stroke); border-radius: var(--radius); padding: 18px; box-shadow: var(--shadow-card); }
        .rankings-card__intro { color: var(--text-muted); margin: 0 0 16px; font-size: .86rem; }
        .ranking-table-wrap { overflow-x: auto; }
        .ranking-table { width: 100%; border-collapse: collapse; font-size: .88rem; min-width: 480px; }
        .ranking-table th, .ranking-table td { padding: 12px 10px; border-bottom: 1px solid var(--stroke); text-align: right; white-space: nowrap; }
        .ranking-table th:first-child, .ranking-table td:first-child, .ranking-table th:nth-child(2), .ranking-table td:nth-child(2) { text-align: left; }
        .ranking-table thead th { color: var(--text-muted); font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; }
        .ranking-table tbody th { color: var(--text); font-weight: 700; }
        .ranking-table tbody tr:last-child th, .ranking-table tbody tr:last-child td { border-bottom: 0; }
        .ranking-table__rank { color: var(--accent); font-weight: 800; width: 48px; }
        .ranking-table__empty { color: var(--text-muted); text-align: left !important; padding: 24px 10px !important; }
        .ranking-table__rating { font-weight: 800; color: var(--accent); }
    </style>
</head>
<body>
<div class="rankings-wrap">
    <header class="rankings-head">
        <a href="<?= e($base ?? '') ?>/" class="back-btn" title="Back to dashboard">←</a>
        <h1>Rankings</h1>
        <button type="button" class="theme-switch" id="themeSwitch" onclick="toggleCourtlyTheme()" aria-label="Switch theme" title="Switch theme">☾</button>
    </header>

    <section class="rankings-card" aria-labelledby="rankings-title">
        <h2 id="rankings-title">Player Rankings</h2>
        <p class="rankings-card__intro">Your roster, ordered by rating.</p>
        <div class="ranking-table-wrap">
            <table class="ranking-table">
                <thead>
                    <tr>
                        <th scope="col">Rank</th>
                        <th scope="col">Player</th>
                        <th scope="col">Rating</th>
                        <th scope="col">Games</th>
                        <th scope="col">Win rate</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($players->isEmpty()): ?>
                    <tr><td colspan="5" class="ranking-table__empty">No players yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($players as $rank => $player): ?>
                        <?php $winPercentage = $player->total_games > 0 ? round(($player->wins / $player->total_games) * 100, 1) : 0; ?>
                        <tr>
                            <td class="ranking-table__rank"><?= $rank + 1 ?></td>
                            <th scope="row"><?= e($player->name) ?></th>
                            <td class="ranking-table__rating"><?= number_format((float) $player->rating, 1) ?></td>
                            <td><?= $player->total_games ?></td>
                            <td><?= $winPercentage ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
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
</script>
</body>
</html>
