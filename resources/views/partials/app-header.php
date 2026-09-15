<?php
$base = $base ?? rtrim(request()->getBasePath(), '/');
$active = $active ?? 'sessions';
?>
<header class="app-header">
    <?php include resource_path('views/partials/app-brand.php'); ?>
    <?php /* The Manage Players pill only works on the dashboard, which defines openManage(). */ ?>
    <?php $managePlayers = $managePlayers ?? ($active === 'sessions' ? 'dashboard' : false); ?>
    <?php include resource_path('views/partials/app-nav.php'); ?>
    <?php /* Theme toggle and logout are rendered by app-nav.php, so small
             screens collapse them into the hamburger with the links. */ ?>
</header>
<script>
var COURT_THEMES=['dark','blue','cyber','emerald','light'];var COURT_GLYPHS={dark:'☾',blue:'✦',cyber:'✧',emerald:'❖',light:'☀'};var COURT_LABELS={dark:'Dark',blue:'Blue',cyber:'Cyber',emerald:'Emerald',light:'Light'};
function courtlyCurrentTheme(){var t=document.documentElement.getAttribute('data-theme');return COURT_THEMES.indexOf(t)!==-1?t:'dark';}
function courtlyUpdateThemeIcon(){var b=document.getElementById('themeSwitch')||document.getElementById('btnTheme');if(!b)return;var t=courtlyCurrentTheme();b.textContent=COURT_GLYPHS[t];b.title='Theme: '+COURT_LABELS[t]+' — click to switch';b.setAttribute('aria-label',b.title);}
function toggleCourtlyTheme(){var next=COURT_THEMES[(COURT_THEMES.indexOf(courtlyCurrentTheme())+1)%COURT_THEMES.length];if(next==='dark')document.documentElement.removeAttribute('data-theme');else document.documentElement.setAttribute('data-theme',next);try{localStorage.setItem('courtly-theme',next)}catch(e){}courtlyUpdateThemeIcon();}
(function(){try{var s=localStorage.getItem('courtly-theme');if(COURT_THEMES.indexOf(s)!==-1&&s!=='dark')document.documentElement.setAttribute('data-theme',s)}catch(e){}courtlyUpdateThemeIcon();})();
</script>
