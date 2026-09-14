<?php

/**
 * Shared app menu — the single navigation row rendered by every screen.
 *
 * Icon-only pills (the Circles map header style). Labels stay in the markup but
 * are visually hidden, so they still reach screen readers and hover titles.
 *
 * Expects:
 *   $base    — app base path
 *   $active  — 'circles' | 'sessions' | 'stats' | 'rankings' | 'admin'
 *
 * Optional (defaults keep every screen safe):
 *   $managePlayers     — false | 'dashboard' (openManage) | 'map' (id=managePlayersBtn)
 *                        | 'players' (openPlayers)
 *   $showNotifications — true renders the bell; the Circles map wires #notifBell itself
 */
$base = $base ?? rtrim(request()->getBasePath(), '/');
$active = $active ?? 'sessions';
$managePlayers = $managePlayers ?? false;
$showNotifications = $showNotifications ?? false;

$icon = 'viewBox="0 0 24 24" width="1.15em" height="1.15em" aria-hidden="true"';
$stroke = 'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"';
?>
<nav class="app-nav" aria-label="Main navigation">
    <a class="app-pill<?= $active === 'circles' ? ' app-pill--active' : '' ?>" href="<?= e($base) ?>/circles" title="Circles"><svg class="app-pill__icon" <?= $icon ?> <?= $stroke ?>><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3"/></svg><span class="app-pill__label">Circles</span></a>
    <a class="app-pill<?= $active === 'sessions' ? ' app-pill--active' : '' ?>" href="<?= e($base) ?>/" title="Sessions"><svg class="app-pill__icon" <?= $icon ?> fill="currentColor"><path d="M8 5.5v13l11-6.5z"/></svg><span class="app-pill__label">Sessions</span></a>
    <a class="app-pill<?= $active === 'stats' ? ' app-pill--active' : '' ?>" href="<?= e($base) ?>/stats" title="Player Stats"><svg class="app-pill__icon" <?= $icon ?> <?= $stroke ?>><path d="M5 20v-8M12 20V4M19 20v-6"/></svg><span class="app-pill__label">Player Stats</span></a>
    <a class="app-pill<?= $active === 'rankings' ? ' app-pill--active' : '' ?>" href="<?= e($base) ?>/rankings" title="Rankings"><svg class="app-pill__icon" <?= $icon ?> <?= $stroke ?>><circle cx="12" cy="8" r="6"/><path d="M15.5 13 17 22l-5-3-5 3 1.5-9"/></svg><span class="app-pill__label">Rankings</span></a>
    <?php if (\Illuminate\Support\Facades\Auth::user()?->isSuperAdmin()): ?>
    <a class="app-pill<?= $active === 'admin' ? ' app-pill--active' : '' ?>" href="<?= e($base) ?>/admin" title="Admin"><svg class="app-pill__icon" <?= $icon ?> <?= $stroke ?>><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg><span class="app-pill__label">Admin</span></a>
    <?php endif; ?>
    <?php if ($managePlayers === 'dashboard'): ?>
    <button type="button" class="app-pill" onclick="openManage()" title="Manage Players"><svg class="app-pill__icon" <?= $icon ?> <?= $stroke ?>><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg><span class="app-pill__label">Manage Players</span></button>
    <?php elseif ($managePlayers === 'map'): ?>
    <button type="button" class="app-pill" id="managePlayersBtn" title="Manage players"><svg class="app-pill__icon" <?= $icon ?> <?= $stroke ?>><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg><span class="app-pill__label">Manage players</span></button>
    <?php elseif ($managePlayers === 'players'): ?>
    <button type="button" class="app-pill" onclick="openPlayers()" title="Players"><svg class="app-pill__icon" <?= $icon ?> <?= $stroke ?>><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg><span class="app-pill__label">Players</span></button>
    <?php endif; ?>
    <?php if ($showNotifications): ?>
    <div class="notif-wrap">
        <button type="button" class="app-pill" id="notifBell" title="Notifications" aria-label="Notifications"><svg class="app-pill__icon" <?= $icon ?> <?= $stroke ?>><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg><span class="notif-badge" id="notifBadge" style="display:none">0</span></button>
    </div>
    <?php endif; ?>
</nav>
