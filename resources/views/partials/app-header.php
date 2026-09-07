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
        <a class="app-pill<?= $active === 'circles' ? ' app-pill--active' : '' ?>" href="<?= e($base) ?>/circles" title="Circles"><svg class="app-pill__icon" viewBox="0 0 24 24" width="1.15em" height="1.15em" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3"/></svg><span class="app-pill__label">Circles</span></a>
        <a class="app-pill<?= $active === 'sessions' ? ' app-pill--active' : '' ?>" href="<?= e($base) ?>/" title="Sessions"><svg class="app-pill__icon" viewBox="0 0 24 24" width="1.15em" height="1.15em" fill="currentColor" aria-hidden="true"><path d="M8 5.5v13l11-6.5z"/></svg><span class="app-pill__label">Sessions</span></a>
        <a class="app-pill<?= $active === 'stats' ? ' app-pill--active' : '' ?>" href="<?= e($base) ?>/stats" title="Player Stats"><svg class="app-pill__icon" viewBox="0 0 24 24" width="1.15em" height="1.15em" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M5 20v-8M12 20V4M19 20v-6"/></svg><span class="app-pill__label">Player Stats</span></a>
        <a class="app-pill<?= $active === 'rankings' ? ' app-pill--active' : '' ?>" href="<?= e($base) ?>/rankings" title="Rankings"><svg class="app-pill__icon" viewBox="0 0 24 24" width="1.15em" height="1.15em" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="6"/><path d="M15.5 13 17 22l-5-3-5 3 1.5-9"/></svg><span class="app-pill__label">Rankings</span></a>
        <?php if ($active === 'sessions'): ?>
        <button type="button" class="app-pill" onclick="openManage()" title="Manage Players"><svg class="app-pill__icon" viewBox="0 0 24 24" width="1.15em" height="1.15em" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg><span class="app-pill__label">Manage Players</span></button>
        <?php endif; ?>
    </nav>
    <div class="app-actions">
        <button type="button" class="theme-switch" id="themeSwitch" onclick="toggleCourtlyTheme()" aria-label="Switch theme" title="Switch theme">☾</button>
        <form method="POST" action="<?= e($base) ?>/logout" style="margin:0">
            <input type="hidden" name="_token" value="<?= csrf_token() ?>">
            <button type="submit" class="app-logout" title="Log out"><svg class="app-logout__icon" viewBox="0 0 24 24" width="1.15em" height="1.15em" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg><span class="app-logout__label">Logout</span></button>
        </form>
    </div>
</header>
<script>
var COURT_THEMES=['dark','blue','cyber','emerald','light'];var COURT_GLYPHS={dark:'☾',blue:'✦',cyber:'✧',emerald:'❖',light:'☀'};var COURT_LABELS={dark:'Dark',blue:'Blue',cyber:'Cyber',emerald:'Emerald',light:'Light'};
function courtlyCurrentTheme(){var t=document.documentElement.getAttribute('data-theme');return COURT_THEMES.indexOf(t)!==-1?t:'dark';}
function courtlyUpdateThemeIcon(){var b=document.getElementById('themeSwitch')||document.getElementById('btnTheme');if(!b)return;var t=courtlyCurrentTheme();b.textContent=COURT_GLYPHS[t];b.title='Theme: '+COURT_LABELS[t]+' — click to switch';b.setAttribute('aria-label',b.title);}
function toggleCourtlyTheme(){var next=COURT_THEMES[(COURT_THEMES.indexOf(courtlyCurrentTheme())+1)%COURT_THEMES.length];if(next==='dark')document.documentElement.removeAttribute('data-theme');else document.documentElement.setAttribute('data-theme',next);try{localStorage.setItem('courtly-theme',next)}catch(e){}courtlyUpdateThemeIcon();}
(function(){try{var s=localStorage.getItem('courtly-theme');if(COURT_THEMES.indexOf(s)!==-1&&s!=='dark')document.documentElement.setAttribute('data-theme',s)}catch(e){}courtlyUpdateThemeIcon();})();
</script>
