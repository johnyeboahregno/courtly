<?php
$base = $base ?? rtrim(request()->getBasePath(), '/');
$active = $active ?? 'sessions';
?>
<header class="app-header">
    <a class="app-brand" href="<?= e($base) ?>/circles" title="Circles home">
        <img src="<?= e($base) ?>/assets/courtly-mark.png" alt="Courtly">
        <span>COURT<b>LY</b></span>
    </a>
    <nav class="app-nav" aria-label="Main navigation">
        <a class="app-pill<?= $active === 'circles' ? ' app-pill--active' : '' ?>" href="<?= e($base) ?>/circles">Circles</a>
        <a class="app-pill<?= $active === 'sessions' ? ' app-pill--active' : '' ?>" href="<?= e($base) ?>/">Sessions</a>
        <a class="app-pill<?= $active === 'stats' ? ' app-pill--active' : '' ?>" href="<?= e($base) ?>/stats">Player Stats</a>
        <a class="app-pill<?= $active === 'rankings' ? ' app-pill--active' : '' ?>" href="<?= e($base) ?>/rankings">Rankings</a>
        <?php if ($active === 'sessions'): ?>
        <button type="button" class="app-pill" onclick="openManage()">Manage Players</button>
        <?php endif; ?>
    </nav>
    <div class="app-actions">
        <button type="button" class="theme-switch" id="themeSwitch" onclick="toggleCourtlyTheme()" aria-label="Switch theme" title="Switch theme">☾</button>
        <form method="POST" action="<?= e($base) ?>/logout" style="margin:0">
            <input type="hidden" name="_token" value="<?= csrf_token() ?>">
            <button type="submit" class="app-logout">Logout</button>
        </form>
    </div>
</header>
<script>
var COURT_THEMES=['dark','blue','cyber','crimson','emerald','light'];var COURT_GLYPHS={dark:'☾',blue:'✦',cyber:'✧',crimson:'♥',emerald:'❖',light:'☀'};var COURT_LABELS={dark:'Dark',blue:'Blue',cyber:'Cyber',crimson:'Crimson',emerald:'Emerald',light:'Light'};
function courtlyCurrentTheme(){var t=document.documentElement.getAttribute('data-theme');return COURT_THEMES.indexOf(t)!==-1?t:'dark';}
function courtlyUpdateThemeIcon(){var b=document.getElementById('themeSwitch')||document.getElementById('btnTheme');if(!b)return;var t=courtlyCurrentTheme();b.textContent=COURT_GLYPHS[t];b.title='Theme: '+COURT_LABELS[t]+' — click to switch';b.setAttribute('aria-label',b.title);}
function toggleCourtlyTheme(){var next=COURT_THEMES[(COURT_THEMES.indexOf(courtlyCurrentTheme())+1)%COURT_THEMES.length];if(next==='dark')document.documentElement.removeAttribute('data-theme');else document.documentElement.setAttribute('data-theme',next);try{localStorage.setItem('courtly-theme',next)}catch(e){}courtlyUpdateThemeIcon();}
(function(){try{var s=localStorage.getItem('courtly-theme');if(COURT_THEMES.indexOf(s)!==-1&&s!=='dark')document.documentElement.setAttribute('data-theme',s)}catch(e){}courtlyUpdateThemeIcon();})();
</script>
