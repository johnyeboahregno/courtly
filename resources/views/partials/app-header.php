<?php
$base = $base ?? rtrim(request()->getBasePath(), '/');
$active = $active ?? 'sessions';
?>
<header class="app-header">
    <?php include resource_path('views/partials/app-brand.php'); ?>
    <?php /* The Manage Players pill only works on the dashboard, which defines openManage(). */ ?>
    <?php $managePlayers = $managePlayers ?? ($active === 'sessions' ? 'dashboard' : false); ?>
    <?php include resource_path('views/partials/app-nav.php'); ?>
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
