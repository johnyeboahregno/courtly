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
    <link rel="icon" type="image/png" href="<?= e($base ?? '') ?>/assets/favicon.png?v=<?= e(config('courtly.app.version', '1.0.0')) ?>">
    <link rel="stylesheet" href="<?= e($base ?? '') ?>/css/courtly.css?v=<?= e(config('courtly.app.version', '1.0.0')) ?>">
    <style>
        .rankings-wrap { max-width: 920px; margin: 0 auto; padding: 24px 20px 64px; }
        .rankings-head { display: flex; align-items: center; gap: 14px; margin-bottom: 22px; }
        .rankings-head h1 { font-size: 1.4rem; margin: 0; }
    </style>
</head>
<body>
<div class="rankings-wrap">
    <header class="rankings-head">
        <a href="<?= e($base ?? '') ?>/" class="back-btn" title="Back to dashboard">←</a>
        <h1>Rankings</h1>
        <button type="button" class="theme-switch" id="themeSwitch" onclick="toggleCourtlyTheme()" aria-label="Switch theme" title="Switch theme">☾</button>
    </header>

    <nav class="view-nav" aria-label="Views">
        <a href="<?= e($base ?? '') ?>/" class="pill-link">Sessions</a>
        <a href="<?= e($base ?? '') ?>/stats" class="pill-link">Player Stats</a>
        <a href="<?= e($base ?? '') ?>/rankings" class="pill-link pill-link--active" aria-current="page">Rankings</a>
    </nav>

    <?php include resource_path('views/partials/rankings-content.php'); ?>
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
