<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title>Courtly — <?= htmlspecialchars($sessionName ?? 'Session') ?></title>
    <link rel="icon" type="image/png" href="<?= $base ?? '/courtly' ?>/assets/favicon.png?v=<?= htmlspecialchars($appVersion ?? '1.0.0') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;700;800&family=Space+Grotesk:wght@700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
    <script>
        var COURT_THEMES = ['dark', 'blue', 'cyber', 'emerald', 'light'];
        var COURT_GLYPHS = { dark: '☾', blue: '✦', cyber: '✧', emerald: '❖', light: '☀' };
        var COURT_LABELS = { dark: 'Dark', blue: 'Blue', cyber: 'Cyber', emerald: 'Emerald', light: 'Light' };
        (function () {
            try {
                var s = localStorage.getItem('courtly-theme');
                if (COURT_THEMES.indexOf(s) !== -1 && s !== 'dark') document.documentElement.setAttribute('data-theme', s);
            } catch (e) {}
        })();
        function courtlyCurrentTheme() {
            var t = document.documentElement.getAttribute('data-theme');
            return COURT_THEMES.indexOf(t) !== -1 ? t : 'dark';
        }
        function courtlyUpdateThemeIcon() {
            var b = document.getElementById('themeSwitch');
            if (!b) return;
            var t = courtlyCurrentTheme();
            b.textContent = COURT_GLYPHS[t];
            b.title = 'Theme: ' + COURT_LABELS[t] + ' — click to switch';
            b.setAttribute('aria-label', b.title);
        }
        function toggleCourtlyTheme() {
            var next = COURT_THEMES[(COURT_THEMES.indexOf(courtlyCurrentTheme()) + 1) % COURT_THEMES.length];
            if (next === 'dark') document.documentElement.removeAttribute('data-theme');
            else document.documentElement.setAttribute('data-theme', next);
            try { localStorage.setItem('courtly-theme', next); } catch (e) {}
            courtlyUpdateThemeIcon();
        }
        document.addEventListener('DOMContentLoaded', courtlyUpdateThemeIcon);
    </script>
    <link rel="stylesheet" href="<?= $base ?? '/courtly' ?>/css/courtly.css?v=<?= htmlspecialchars($appVersion ?? '1.0.0') ?>">
</head>
<body>
<div id="courtly-app">
    <header class="session-header">
        <div class="session-header__left">
            <a href="<?= $base ?? '/courtly' ?>/circles" class="session-header__logo" title="Back to circles">
                <img src="<?= $base ?? '/courtly' ?>/assets/courtly-mark.png" alt="Courtly" class="session-header__logo-img session-header__logo-img--light">
                <img src="<?= $base ?? '/courtly' ?>/assets/courtly-mark-dark.png" alt="Courtly" class="session-header__logo-img session-header__logo-img--dark">
            </a>
            <h1 class="session-header__name">{{ sessionName }}</h1>
        </div>
        <div class="session-header__stats">
            <span v-if="elapsed" class="session-header__timer">⏱ {{ elapsed }}</span>
            <span v-if="session.type === 'tournament' && tournament && tournament.format === 'round_robin' && tournament.round_progress" class="session-header__badge session-header__badge--tournament">Round {{ tournament.round_progress.current_round }}/{{ tournament.round_progress.total_rounds }}</span>
            <span v-if="session.type === 'tournament' && tournament && tournament.format === 'ladder'" class="session-header__badge session-header__badge--tournament">LADDER</span>
            <span v-if="connectionState !== 'connected'" class="connection-dot" :class="'connection-dot--' + connectionState" :title="connectionState === 'connecting' ? 'Connecting to server…' : 'Server unreachable — data may be stale'"></span>
            <button class="mode-switch" :class="['mode-switch--' + matchmakingMode, { 'is-busy': uiPending.mode }]" :disabled="uiPending.mode" @click="toggleMode" :title="'Matchmaking: ' + modeLabel + ' — click to switch'">{{ matchmakingMode === 'peg' ? 'PEG' : 'SMART' }}</button>
            <button v-if="session.type === 'tournament' && session.status === 'UPCOMING'" class="mode-switch mode-switch--players" @click="openTeams">TEAMS</button>
            <span class="session-sport-icon" :style="{ '--session-sport-image': 'url(/assets/' + session.sport + '.png)' }" aria-hidden="true"></span>
            <div class="offline-control">
                <button type="button" class="offline-indicator" :class="'offline-indicator--' + offlineStatus" :title="offlineIndicatorTitle" @click="offlineMenuOpen = !offlineMenuOpen" aria-label="Offline mode">
                    <svg v-if="offlinePreference === 'offline'" class="offline-icon-svg" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><line x1="6" y1="18" x2="18" y2="6"/></svg>
                    <svg v-else-if="offlinePreference === 'online'" class="offline-icon-svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 8.5a15 15 0 0 1 20 0"/><path d="M5 12a10 10 0 0 1 14 0"/><path d="M8.5 15.5a5 5 0 0 1 7 0"/><circle cx="12" cy="19" r="1" fill="currentColor" stroke="none"/></svg>
                    <svg v-else class="offline-icon-svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12a9 9 0 1 1-3-6.7"/><polyline points="21 3 21 9 15 9"/></svg>
                </button>
                <div v-if="offlineMenuOpen" class="offline-menu">
                    <button type="button" class="offline-menu__item" :class="{ 'offline-menu__item--active': offlinePreference === 'auto' }" @click="setOfflinePreference('auto')">Automatic</button>
                    <button type="button" class="offline-menu__item" :class="{ 'offline-menu__item--active': offlinePreference === 'offline' }" @click="setOfflinePreference('offline')">Offline</button>
                    <button type="button" class="offline-menu__item" :class="{ 'offline-menu__item--active': offlinePreference === 'online' }" @click="setOfflinePreference('online')">Online</button>
                </div>
            </div>
            <button class="theme-switch" id="themeSwitch" type="button" onclick="toggleCourtlyTheme()" aria-label="Switch theme" title="Switch theme">☾</button>
        </div>
    </header>

    <div v-if="authError" class="sync-error-banner" role="alert">
        ⚠️ Changes couldn't be saved — this account doesn't own the session. Return to the dashboard and sign in as the session owner.
    </div>

    <div v-if="session.status !== 'FINISHED'" class="courts-toolbar">
        <button class="court-toolbar-btn" type="button" :disabled="courts.length >= 8 || updatingCourts" @click="adjustCourts('add')" title="Add a court"><svg class="court-toolbar-btn__icon" viewBox="0 0 24 24" width="1.15em" height="1.15em" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg><span class="court-toolbar-btn__label">ADD COURT</span></button>
        <div class="courts-toolbar__actions">
            <button class="mode-switch mode-switch--insights" @click="openInsights" title="Matchmaking insights"><svg class="mode-switch__icon" viewBox="0 0 24 24" width="1.15em" height="1.15em" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg><span class="mode-switch__label">INSIGHTS</span></button>
            <button class="mode-switch mode-switch--players" @click="openPlayers" title="Players"><svg class="mode-switch__icon" viewBox="0 0 24 24" width="1.15em" height="1.15em" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg><span class="mode-switch__label">PLAYERS</span></button>
            <button v-if="session.status === 'ACTIVE'" class="mode-switch mode-switch--finish" :class="{ 'is-busy': sessionActionPending === 'finish' }" :disabled="sessionActionPending === 'finish'" @click="finishSession" title="Finish session"><svg class="mode-switch__icon" viewBox="0 0 24 24" width="1.15em" height="1.15em" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg><span class="mode-switch__label">FINISH</span></button>
        </div>
    </div>

    <div v-if="blockedByMissingGender" class="gender-block-banner" role="alert">
        <span class="gender-block-banner__text">⚡ Set a gender for {{ missingGenderPlayers.map(sp => formatName(sp.player.name)).join(', ') }} to auto-fill the courts.</span>
    </div>

    <div class="courts-grid" :class="'courts-' + courts.length">
        <div v-for="court in courts" :key="court.id" class="court-card" :class="'court-card--' + (session.sport || 'badminton')" :data-court-id="court.id">
            <div class="court-card__head">
                <span class="court-card__number" :class="{ 'court-card__number--editable': session.status !== 'FINISHED' && !updatingCourts }" @click="openCourtRename(court, $event)" :title="session.status !== 'FINISHED' ? ('Rename ' + (court.name || ('Court ' + court.court_number))) : null">{{ court.name || ('COURT ' + court.court_number) }}</span>
                <span class="court-card__head-actions">
                    <button v-if="session.status !== 'FINISHED'" class="court-clear-btn" type="button" :class="{ 'is-busy': uiPending.clear[court.id] }" :disabled="uiPending.clear[court.id]" @click="clearCourt(court)" :title="court.match ? 'Cancel this match' : 'Clear this court'">CLEAR</button>
                    <span class="court-card__status" :class="'court-card__status--' + (court.match ? 'playing' : 'available')">{{ court.match ? 'PLAYING' : 'AVAILABLE' }}</span>
                    <button v-if="session.status !== 'FINISHED'" class="court-remove-btn" type="button" :disabled="courts.length <= 1 || updatingCourts" @click="adjustCourts('remove', court.court_number)" title="Remove this court" :aria-label="'Remove court ' + court.court_number">×</button>
                </span>
            </div>
            <div v-if="!court.match" class="court-card__body court-card__body--empty"
                :class="{ 'court-card__body--drop': dragOverCourtId === court.id, 'court-card__body--picked': pickedQueuePlayerId != null }">
                <div class="court-card__lines"></div>
                <div class="court-card__pending">
                    <div class="court-card__pending-grid">
                        <div v-for="(sp, index) in courtPendingSlots(court.id)" :key="court.id + '-' + index"
                            class="court-card__player-box court-card__player-box--pending"
                            :class="{ 'court-card__player-box--pending-empty': !sp, 'court-card__player-box--drop-target': pendingDropSlot === court.id + '-' + index }"
                            :data-slot-index="index"
                            @dragover.prevent="onSlotDragOver(court, index, $event)"
                            @dragleave="onSlotDragLeave(court, index)"
                            @drop="onSlotDrop(court, index, $event)"
                            @click="onSlotClick(court, index, sp, $event)"
                            :title="sp ? 'Tap to return to NEXT UP' : 'Drop a player here'">
                            <span v-if="sp" class="court-card__player"><span class="gender-dot" :class="genderDotClass(sp.player.gender)" :title="genderLabel(sp.player.gender)"></span>{{ formatName(sp.player.name) }}</span>
                            <span v-else class="court-card__player court-card__player--placeholder">+</span>
                        </div>
                    </div>
                    <span v-if="courtPendingCount(court.id) >= 4" class="court-empty-text">Ready to start</span>
                    <button v-if="courtPendingCount(court.id) >= 4" class="fill-courts-btn fill-courts-btn--start" :class="{ 'is-busy': uiPending.court[court.id] }" type="button" :disabled="uiPending.court[court.id]" @click="startCourtMatch(court.id)">START MATCH</button>
                </div>
                <div v-if="selectingCourts[court.id]" class="court-card__selecting">
                    <span class="court-card__selecting-ring"></span>
                    <span class="court-card__selecting-text">Selecting teams…</span>
                </div>
            </div>
            <div v-else class="court-card__body"
                @dragover.prevent="onPlayingCourtDragOver(court, $event)"
                @dragleave="onPlayingCourtDragLeave(court, $event)"
                @drop="dropOnPlayingCourt(court, $event.clientX, $event.clientY, $event)">
                <div class="court-card__lines"></div>
                <div class="court-card__court">
                    <div class="court-card__side court-card__side--team-1" :class="{ 'court-card__side--locked': submitting[court.match.id + '_1'] || submitting[court.match.id + '_2'] }" @click="openScorePicker(court, 1, $event)" title="Tap to record a win for this team">
                        <div class="court-card__player-box court-card__player-box--team-1"
                            :class="{ 'court-card__player-box--streak': court.match.t1[0].streak >= 3, 'court-card__player-box--drop-target': subDragOver === subDragKey(court.match.id, 1, 0), 'court-card__player-box--picked': pickedQueuePlayerId != null }"
                            @drop="substituteOnCourt(court, court.match.t1[0].player_id, $event)"
                            @click="tapOnCourtPlayer(court, court.match.t1[0].player_id, $event)"
                            :data-player-id="court.match.t1[0].player_id"
                            title="Swap in a waiting player (drag or tap)">
                            <span class="court-card__player"><span class="gender-dot" :class="genderDotClass(court.match.t1[0].gender)" :title="genderLabel(court.match.t1[0].gender)"></span>{{ formatName(court.match.t1[0].name) }}</span>
                            <span v-if="court.match.t1[0].wins || court.match.t1[0].streak > 3" class="court-card__player-meta"><i v-if="court.match.t1[0].wins" class="court-card__win">{{ court.match.t1[0].wins }}W</i><i v-if="court.match.t1[0].streak > 3" class="court-card__streak">{{ court.match.t1[0].streak }}</i></span>
                        </div>
                        <div class="court-card__player-box court-card__player-box--team-1"
                            :class="{ 'court-card__player-box--streak': court.match.t1[1].streak >= 3, 'court-card__player-box--drop-target': subDragOver === subDragKey(court.match.id, 1, 1), 'court-card__player-box--picked': pickedQueuePlayerId != null }"
                            @drop="substituteOnCourt(court, court.match.t1[1].player_id, $event)"
                            @click="tapOnCourtPlayer(court, court.match.t1[1].player_id, $event)"
                            :data-player-id="court.match.t1[1].player_id"
                            title="Swap in a waiting player (drag or tap)">
                            <span class="court-card__player"><span class="gender-dot" :class="genderDotClass(court.match.t1[1].gender)" :title="genderLabel(court.match.t1[1].gender)"></span>{{ formatName(court.match.t1[1].name) }}</span>
                            <span v-if="court.match.t1[1].wins || court.match.t1[1].streak > 3" class="court-card__player-meta"><i v-if="court.match.t1[1].wins" class="court-card__win">{{ court.match.t1[1].wins }}W</i><i v-if="court.match.t1[1].streak > 3" class="court-card__streak">{{ court.match.t1[1].streak }}</i></span>
                        </div>
                    </div>
                    <div class="court-card__divider"><span>VS</span></div>
                    <div class="court-card__side court-card__side--team-2" :class="{ 'court-card__side--locked': submitting[court.match.id + '_1'] || submitting[court.match.id + '_2'] }" @click="openScorePicker(court, 2, $event)" title="Tap to record a win for this team">
                        <div class="court-card__player-box court-card__player-box--team-2"
                            :class="{ 'court-card__player-box--streak': court.match.t2[0].streak >= 3, 'court-card__player-box--drop-target': subDragOver === subDragKey(court.match.id, 2, 0), 'court-card__player-box--picked': pickedQueuePlayerId != null }"
                            @drop="substituteOnCourt(court, court.match.t2[0].player_id, $event)"
                            @click="tapOnCourtPlayer(court, court.match.t2[0].player_id, $event)"
                            :data-player-id="court.match.t2[0].player_id"
                            title="Swap in a waiting player (drag or tap)">
                            <span class="court-card__player"><span class="gender-dot" :class="genderDotClass(court.match.t2[0].gender)" :title="genderLabel(court.match.t2[0].gender)"></span>{{ formatName(court.match.t2[0].name) }}</span>
                            <span v-if="court.match.t2[0].wins || court.match.t2[0].streak > 3" class="court-card__player-meta"><i v-if="court.match.t2[0].wins" class="court-card__win">{{ court.match.t2[0].wins }}W</i><i v-if="court.match.t2[0].streak > 3" class="court-card__streak">{{ court.match.t2[0].streak }}</i></span>
                        </div>
                        <div class="court-card__player-box court-card__player-box--team-2"
                            :class="{ 'court-card__player-box--streak': court.match.t2[1].streak >= 3, 'court-card__player-box--drop-target': subDragOver === subDragKey(court.match.id, 2, 1), 'court-card__player-box--picked': pickedQueuePlayerId != null }"
                            @drop="substituteOnCourt(court, court.match.t2[1].player_id, $event)"
                            @click="tapOnCourtPlayer(court, court.match.t2[1].player_id, $event)"
                            :data-player-id="court.match.t2[1].player_id"
                            title="Swap in a waiting player (drag or tap)">
                            <span class="court-card__player"><span class="gender-dot" :class="genderDotClass(court.match.t2[1].gender)" :title="genderLabel(court.match.t2[1].gender)"></span>{{ formatName(court.match.t2[1].name) }}</span>
                            <span v-if="court.match.t2[1].wins || court.match.t2[1].streak > 3" class="court-card__player-meta"><i v-if="court.match.t2[1].wins" class="court-card__win">{{ court.match.t2[1].wins }}W</i><i v-if="court.match.t2[1].streak > 3" class="court-card__streak">{{ court.match.t2[1].streak }}</i></span>
                        </div>
                    </div>
                </div>
            </div>
            <div v-if="celebration && celebration.courtId === court.id" class="court-card__celebration" :style="{ '--origin-x': celebration.x + '%', '--origin-y': celebration.y + '%' }" aria-hidden="true">
                <span v-for="particle in celebrationParticles" :key="particle" class="court-card__confetti" :style="{ '--particle': particle }"></span>
                <strong>WIN</strong>
            </div>
        </div>
    </div>

    <div class="waiting-list">
        <div class="waiting-list__head">
            <h3 class="waiting-list__title">NEXT UP</h3>
            <button class="fill-courts-btn" :class="{ 'is-busy': uiPending.fill }" type="button" :disabled="!canFillCourts || uiPending.fill" @click="fillCourts">FILL COURTS</button>
            <span class="waiting-list__players">👥 {{ players.length }} Players</span>
            <span class="waiting-list__mode" :class="'waiting-list__mode--' + matchmakingMode">{{ modeLabel }}</span>
        </div>
        <p v-if="pickedQueuePlayer" class="waiting-list__pick-hint">Picked <strong>{{ formatName(pickedQueuePlayer.player.name) }}</strong> — tap an empty court or a player on court to place, or tap again to cancel.</p>
        <div class="waiting-list__cards">
            <TransitionGroup name="queue" tag="div" class="waiting-list__row">
                <div v-for="(sp, index) in queuePlayers" :key="sp.player_id" :style="{ '--leave-order': index }" class="player-card" :class="{ 'player-card--paused': sp.status === 'PAUSED', 'player-card--next': nextFourIds.includes(sp.player_id), 'player-card--picked': pickedQueuePlayerId === sp.player_id, 'player-card--draggable': sp.status === 'WAITING' || sp.status === 'PAUSED' }" :draggable="sp.status === 'WAITING' || sp.status === 'PAUSED'" @dragstart="dragPlayerToCourtStart(sp, $event)" @dragend="dragPlayerToCourtEnd" @click="pickQueuePlayer(sp, $event)" @pointerdown="onCardPointerDown(sp, $event)" @pointermove="onCardPointerMove(sp, $event)" @pointerup="onCardPointerUp(sp, $event)" @pointercancel="onCardPointerCancel(sp, $event)">
                    <div class="player-card__col">
                        <span class="player-card__name"><span class="rank-icon" v-html="rankIcon(sp.player.rating)"></span><span class="gender-dot" :class="genderDotClass(sp.player.gender)" :title="genderLabel(sp.player.gender)"></span><span class="name-label">{{ formatName(sp.player.name) }}</span></span>
                        <span class="player-card__rating"><span class="rating-value">{{ Math.round(sp.player.rating) }}</span>-{{ sp.wins }}-{{ sitOuts(sp) }}</span>
                    </div>
                    <div class="player-card__actions">
                        <template v-if="!sp.player.gender">
                            <button class="player-card__gender player-card__gender--male" type="button" @click="setPlayerGender(sp.player_id, 'MALE')" title="Set gender: Male">♂</button>
                            <button class="player-card__gender player-card__gender--female" type="button" @click="setPlayerGender(sp.player_id, 'FEMALE')" title="Set gender: Female">♀</button>
                        </template>
                        <button class="player-card__pause" :class="{ 'is-busy': uiPending.player[sp.id] }" :disabled="uiPending.player[sp.id]" @click="sp.status === 'PAUSED' ? resumePlayer(sp.id) : pausePlayer(sp.id)" :title="sp.status === 'PAUSED' ? 'Resume' : 'Pause — take out of rotation'">{{ sp.status === 'PAUSED' ? '▶' : '⏸' }}</button>
                        <button class="player-card__remove" type="button" @click="openRemove(sp)" title="Remove from session">×</button>
                    </div>
                </div>
            </TransitionGroup>
            <p v-if="queuePlayers.length === 0" class="waiting-list__empty">No players waiting</p>
        </div>
    </div>

    <div v-if="session.type === 'tournament' && tournament && tournament.standings && tournament.standings.length" class="waiting-list standings-panel">
        <div class="waiting-list__head">
            <h3 class="waiting-list__title">{{ tournament.format === 'ladder' ? 'LADDER' : 'STANDINGS' }}</h3>
            <span v-if="session.status === 'FINISHED'" class="waiting-list__mode">FINAL</span>
        </div>
        <div class="waiting-list__cards">
            <div v-for="(team, i) in tournament.standings" :key="team.team_id" class="player-card standings-panel__row">
                <div class="player-card__col">
                    <span class="player-card__name">#{{ tournament.format === 'ladder' ? team.rank : (i + 1) }} {{ team.players.join(' / ') }}</span>
                    <span class="player-card__rating">{{ team.wins }}W - {{ team.losses }}L ({{ team.played }} played)<template v-if="team.points_for || team.points_against"> · {{ team.point_diff > 0 ? '+' : '' }}{{ team.point_diff }} pts</template></span>
                </div>
            </div>
        </div>
    </div>

    <details class="match-history">
        <summary class="match-history__summary">
            <span class="match-history__summary-label">MATCH HISTORY</span>
            <span class="match-history__summary-count">{{ historyTotal }} games</span>
        </summary>
        <div class="match-history__search-wrap">
            <input v-model="historySearch" class="match-history__search" type="search" placeholder="Search players..." @click.stop>
        </div>
        <div v-if="filteredHistory.length" class="match-history__list">
            <div v-for="match in filteredHistory" :key="match.id" class="match-history__match">
                <div class="match-history__meta">
                    <span class="match-history__game">#{{ match.gameNumber }}</span>
                    <span v-if="match.courtNumber" class="match-history__court">Court {{ match.courtNumber }}</span>
                </div>
                <div class="match-history__teams">
                    <div class="match-history__team" :class="{ 'match-history__team--win': match.winner === 1 }">
                        <span class="match-history__team-name">{{ match.team1 }}</span>
                        <span v-if="match.team1Rating != null" class="match-history__team-rating">{{ match.team1Rating }}</span>
                        <span v-if="match.winner === 1" class="match-history__badge">WINNER</span>
                    </div>
                    <div class="match-history__team" :class="{ 'match-history__team--win': match.winner === 2 }">
                        <span class="match-history__team-name">{{ match.team2 }}</span>
                        <span v-if="match.team2Rating != null" class="match-history__team-rating">{{ match.team2Rating }}</span>
                        <span v-if="match.winner === 2" class="match-history__badge">WINNER</span>
                    </div>
                </div>
                <div class="match-history__feedback">
                    <span class="match-history__feedback-label">Quality</span>
                    <button type="button" class="fb-btn fb-btn--poor" :class="{ 'fb-btn--active': matchFeedback[match.id] === 'POOR' }" @click="submitFeedback(match.id, 'POOR')">POOR</button>
                    <button type="button" class="fb-btn fb-btn--good" :class="{ 'fb-btn--active': matchFeedback[match.id] === 'GOOD' }" @click="submitFeedback(match.id, 'GOOD')">GOOD</button>
                    <button type="button" class="fb-btn fb-btn--great" :class="{ 'fb-btn--active': matchFeedback[match.id] === 'GREAT' }" @click="submitFeedback(match.id, 'GREAT')">GREAT</button>
                </div>
            </div>
        </div>
        <p v-else class="match-history__empty">{{ history.length ? 'No matching players.' : 'No completed matches yet.' }}</p>
    </details>

    <div v-if="touchDrag.active && touchDrag.sp" class="touch-drag-ghost" :style="{ transform: 'translate(' + (touchDrag.x - touchDrag.halfW) + 'px, ' + (touchDrag.y - touchDrag.halfH) + 'px)' }">
        <div class="player-card">
            <div class="player-card__col">
                <span class="player-card__name"><span class="rank-icon" v-html="rankIcon(touchDrag.sp.player.rating)"></span><span class="gender-dot" :class="genderDotClass(touchDrag.sp.player.gender)" :title="genderLabel(touchDrag.sp.player.gender)"></span><span class="name-label">{{ formatName(touchDrag.sp.player.name) }}</span></span>
                <span class="player-card__rating"><span class="rating-value">{{ Math.round(touchDrag.sp.player.rating) }}</span>-{{ touchDrag.sp.wins }}-{{ sitOuts(touchDrag.sp) }}</span>
            </div>
            <div class="player-card__actions">
                <button class="player-card__pause" type="button" disabled>⏸</button>
                <button class="player-card__remove" type="button" disabled>×</button>
            </div>
        </div>
    </div>

    <footer class="session-controls">
        <button v-if="session.status === 'PAUSED'" class="btn btn--primary" :class="{ 'is-busy': sessionActionPending === 'resume' }" :disabled="sessionActionPending === 'resume'" @click="resumeSession">▶ RESUME</button>
        <button v-if="session.status === 'FINISHED'" class="btn btn--primary" :class="{ 'is-busy': sessionActionPending === 'newSession' }" :disabled="sessionActionPending === 'newSession' || offlineMode" :title="offlineMode ? 'Unavailable offline — reconnect to start a new session' : ''" @click="startNewSession">▶ START NEW SESSION</button>
    </footer>

    <div v-if="manualAssignment.show" class="modal-overlay" @click.self="closeManualAssignment">
        <div class="modal modal--wide modal--manual-assign">
            <div class="modal__head">
                <h3>Assign {{ manualAssignment.court.name || ('Court ' + manualAssignment.court.court_number) }}</h3>
                <button class="modal__close" @click="closeManualAssignment">✕</button>
            </div>
            <p class="add-section__label">Select four waiting players, then drag between teams to swap.</p>
            <div class="existing-list manual-assign__list">
                <button v-for="sp in waitingPlayers" :key="sp.id" type="button" class="existing-item manual-assignment__player" :class="{ 'existing-item--selected': manualAssignment.playerIds.includes(sp.player_id) }" @click="toggleManualPlayer(sp.player_id)">
                    <span class="existing-item__name"><span class="rank-icon" v-html="rankIcon(sp.player.rating)"></span><span class="gender-dot" :class="genderDotClass(sp.player.gender)" :title="genderLabel(sp.player.gender)"></span><span class="name-label">{{ formatName(sp.player.name) }}</span></span>
                    <span class="existing-item__rating"><span class="rating-value">{{ Math.round(sp.player.rating) }}</span></span>
                </button>
            </div>

            <div v-if="manualAssignment.playerIds.length === 4" class="manual-teams">
                <div v-for="(team, teamIndex) in manualTeams" :key="teamIndex" class="manual-team" :class="'manual-team--' + (teamIndex + 1)">
                    <div class="manual-team__head">TEAM {{ teamIndex + 1 }}</div>
                    <div v-for="sp in team" :key="sp.player_id"
                        class="manual-team__slot"
                        :class="{ 'manual-team__slot--dragging': manualDraggedId === sp.player_id, 'manual-team__slot--over': manualDragOverId === sp.player_id, 'manual-team__slot--tapped': manualTapId === sp.player_id }"
                        draggable="true"
                        @dragstart="manualDragStart(sp.player_id, $event)"
                        @dragend="manualDragEnd"
                        @dragover.prevent="manualDragOverId = sp.player_id"
                        @dragleave="manualDragOverId === sp.player_id && (manualDragOverId = null)"
                        @drop="manualDrop(sp.player_id, $event)"
                        @click="manualTap(sp.player_id)">
                        <span class="manual-team__name"><span class="gender-dot" :class="genderDotClass(sp.player.gender)" :title="genderLabel(sp.player.gender)"></span>{{ formatName(sp.player.name) }}</span>
                    </div>
                </div>
            </div>

            <p v-if="manualAssignment.error" class="err" style="display:block">{{ manualAssignment.error }}</p>
            <div class="modal__actions">
                <button class="btn btn--secondary" @click="closeManualAssignment">Cancel</button>
                <button class="btn btn--primary" :disabled="manualAssignment.playerIds.length !== 4 || manualAssignment.submitting" @click="submitManualAssignment">Start match ({{ manualAssignment.playerIds.length }}/4)</button>
            </div>
        </div>
    </div>

    <!-- Players dialog: add new/select existing + manage roster -->
    <div v-if="showPlayers" class="modal-overlay" @click.self="showPlayers = false">
        <div class="modal modal--wide modal--players">
            <div class="modal__head">
                <h3>Players</h3>
                <button class="modal__close" @click="showPlayers = false">✕</button>
            </div>

            <!-- Add section -->
            <div class="add-section">
                <div class="add-section__new">
                    <input ref="playerNameInput" v-model="newPlayerName" placeholder="Player name" class="modal__input" @keyup.enter="addPlayers" @focus="showSuggestionsNow" @blur="hideSuggestionsLater">
                    <select v-model="newPlayerGender" class="modal__input modal__input--select" aria-label="Gender">
                        <option value="">Gender</option>
                        <option value="MALE">Male</option>
                        <option value="FEMALE">Female</option>
                    </select>
                    <button class="btn btn--primary" :class="{ 'is-busy': uiPending.add }" @click="addPlayers" :disabled="!newPlayerName.trim() || !newPlayerGender || uiPending.add">Add</button>
                </div>

                <!-- Autocomplete suggestions: top 10 on focus, matches while typing -->
                <div v-if="showSuggestions && playerSuggestions.length" class="add-section__existing">
                    <p class="add-section__label">{{ newPlayerName.trim() ? 'Suggestions:' : 'Top players:' }}</p>
                    <div class="existing-list">
                        <div v-for="p in playerSuggestions" :key="p.id" class="existing-item" @mousedown.prevent @click="addExistingPlayer(p.id)">
                            <span class="existing-item__name"><span class="rank-icon" v-html="rankIcon(p.rating)"></span><span class="gender-dot" :class="genderDotClass(p.gender)" :title="genderLabel(p.gender)"></span><span class="name-label">{{ formatName(p.name) }}</span></span>
                            <span class="existing-item__rating"><span class="rating-value">{{ Math.round(p.rating) }}</span></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tournament teams: preview/edit before Start (drag a player onto another to swap) -->
    <div v-if="showTeams" class="modal-overlay" @click.self="closeTeams">
        <div class="modal modal--wide">
            <div class="modal__head">
                <h3>Teams</h3>
                <button class="modal__close" @click="closeTeams">✕</button>
            </div>
            <p v-if="teamsError" class="err" style="display:block">{{ teamsError }}</p>
            <p v-else class="add-section__label">Drag a player onto another player to swap them between teams. (Tap-tap works too.)</p>
            <div class="teams-grid">
                <div v-for="team in teamsList" :key="team.team_id" class="team-card">
                    <div v-for="p in team.players" :key="p.player_id"
                        class="existing-item team-card__player"
                        :class="{ 'existing-item--selected': selectedPlayerId === p.player_id, 'existing-item--drag-over': dragOverPlayerId === p.player_id, 'existing-item--dragging': draggedPlayerId === p.player_id }"
                        draggable="true"
                        @click="selectPlayerForSwap(p.player_id)"
                        @dragstart="onPlayerDragStart(p.player_id, $event)"
                        @dragend="onPlayerDragEnd"
                        @dragover.prevent
                        @dragenter.prevent="dragOverPlayerId = p.player_id"
                        @dragleave="dragOverPlayerId === p.player_id && (dragOverPlayerId = null)"
                        @drop="onPlayerDrop(p.player_id, $event)">
                        <span class="team-card__handle">⠿</span>
                        <span class="existing-item__name"><span class="rank-icon" v-html="rankIcon(p.rating)"></span><span class="gender-dot" :class="genderDotClass(p.gender)" :title="genderLabel(p.gender)"></span><span class="name-label">{{ formatName(p.name) }}</span></span>
                        <span class="existing-item__rating"><span class="rating-value">{{ Math.round(p.rating) }}</span></span>
                    </div>
                </div>
            </div>
            <div class="modal__actions">
                <button class="btn btn--secondary" :disabled="teamsLoading" @click="regenerateTeams">🔀 Shuffle</button>
                <button class="btn btn--primary" @click="closeTeams">Done</button>
            </div>
        </div>
    </div>

    <!-- Styled confirmation dialog (remove from session) -->
    <div v-if="confirmRemove.show" class="modal-overlay" @click.self="!confirmRemove.loading && (confirmRemove.show = false)">
        <div class="modal modal--confirm">
            <div class="confirm-icon">✕</div>
            <h3>Remove {{ confirmRemove.name }}?</h3>
            <p v-if="confirmRemove.isPlaying" class="confirm-note">This player is currently on court. They'll finish the current game, then be removed from the session.</p>
            <p v-else class="confirm-note">This player will be removed from the session and won't be allocated to any more courts.</p>
            <div class="modal__actions">
                <button class="btn btn--secondary" :disabled="confirmRemove.loading" @click="confirmRemove.show = false">Cancel</button>
                <button class="btn btn--danger" :class="{ 'is-busy': confirmRemove.loading }" :disabled="confirmRemove.loading" @click="confirmLeave">{{ confirmRemove.loading ? 'Removing...' : 'Remove' }}</button>
            </div>
        </div>
    </div>

    <!-- Delete permanently dialog -->
    <div v-if="confirmDelete.show" class="modal-overlay" @click.self="!confirmDelete.loading && (confirmDelete.show = false)">
        <div class="modal modal--confirm">
            <div class="confirm-icon confirm-icon--delete">🗑</div>
            <h3>Delete {{ confirmDelete.name }}?</h3>
            <p class="confirm-note">This removes the player from the entire system — they won't appear in any session or future event. This cannot be undone.</p>
            <div class="modal__actions">
                <button class="btn btn--secondary" :disabled="confirmDelete.loading" @click="confirmDelete.show = false">Cancel</button>
                <button class="btn btn--danger" :class="{ 'is-busy': confirmDelete.loading }" :disabled="confirmDelete.loading" @click="deletePlayer">{{ confirmDelete.loading ? 'Deleting...' : 'Delete Permanently' }}</button>
            </div>
        </div>
    </div>

    <!-- New session confirmation dialog -->
    <div v-if="confirmNewSession.show" class="modal-overlay" @click.self="confirmNewSession.show = false">
        <div class="modal modal--confirm">
            <div class="confirm-icon confirm-icon--new">🆕</div>
            <h3>Start a new session?</h3>
            <p class="confirm-note">This will create a new "{{ sessionName }}" session with {{ courts.length || 3 }} court(s). The current session will remain available.</p>
            <div class="modal__actions">
                <button class="btn btn--secondary" @click="confirmNewSession.show = false">Cancel</button>
                <button class="btn btn--primary" @click="doStartNewSession">Start New Session</button>
            </div>
        </div>
    </div>

    <!-- Offline sync prompt -->
    <div v-if="syncPrompt.show" class="modal-overlay" @click.self="!syncPrompt.syncing && (syncPrompt.show = false)">
        <div class="modal modal--confirm">
            <div class="confirm-icon confirm-icon--sync">⇅</div>
            <h3>You're back online</h3>
            <p class="confirm-note">{{ offlineQueue.length }} change{{ offlineQueue.length === 1 ? '' : 's' }} made while offline {{ offlineQueue.length === 1 ? 'is' : 'are' }} waiting to sync.</p>
            <ul v-if="offlineQueue.length" class="offline-queue-list">
                <li v-for="item in offlineQueue" :key="item.id">{{ item.label }}</li>
            </ul>
            <p v-if="syncPrompt.error" class="err" style="display:block">{{ syncPrompt.error }}</p>
            <div class="modal__actions">
                <button class="btn btn--secondary" :disabled="syncPrompt.syncing" @click="discardOfflineQueue">Discard changes</button>
                <button class="btn btn--primary" :class="{ 'is-busy': syncPrompt.syncing }" :disabled="syncPrompt.syncing" @click="syncOfflineQueue">{{ syncPrompt.syncing ? 'Syncing…' : 'Sync now' }}</button>
            </div>
        </div>
    </div>

    <!-- Matchmaking insights modal -->
    <div v-if="insights.show" class="modal-overlay" @click.self="insights.show = false">
        <div class="modal modal--wide modal--insights">
            <div class="modal__head">
                <h3>Matchmaking insights</h3>
                <button class="modal__close" @click="insights.show = false">✕</button>
            </div>
            <p v-if="insights.loading" class="add-section__label">Analysing matchmaking quality…</p>
            <p v-else-if="insights.error" class="err" style="display:block">{{ insights.error }}</p>
            <template v-else-if="insights.data">
                <p class="insights-summary">{{ insights.data.summary }}</p>
                <div v-if="insights.data.issues && insights.data.issues.length" class="insights-list">
                    <div v-for="(issue, i) in insights.data.issues" :key="i" class="insights-item" :class="'insights-item--' + (issue.severity || 'info')">
                        <span class="insights-item__issue">{{ issue.issue }}</span>
                        <span v-if="issue.suggestion" class="insights-item__suggestion">{{ issue.suggestion }}</span>
                    </div>
                </div>
                <div v-if="insights.data.suggested_weights && Object.keys(insights.data.suggested_weights).length" class="insights-weights">
                    <h4>Suggested weight changes</h4>
                    <div v-for="(value, key) in insights.data.suggested_weights" :key="key" class="insights-weight">
                        <span>{{ key }}</span>
                        <span>{{ value }}</span>
                    </div>
                    <p class="insights-weights__note">Suggestions only — weights aren't applied automatically.</p>
                </div>
                <p class="insights-source">{{ insights.data.source === 'ai' ? 'AI-generated' : 'Auto-generated from session data' }}</p>
            </template>
        </div>
    </div>

    <!-- Score picker — roller deck shown after a winner is tapped -->
    <div v-if="scorePicker.show" class="score-picker" @click.self="closeScorePicker">
        <div class="score-picker__panel" role="dialog" aria-label="Enter match score">
            <div class="score-picker__head">
                <span class="score-picker__court">{{ scorePicker.courtLabel || ('COURT ' + scorePicker.courtNumber) }} — FINAL SCORE</span>
                <button class="score-picker__close" type="button" @click="closeScorePicker" aria-label="Cancel">✕</button>
            </div>
            <div class="score-picker__teams">
                <span class="score-picker__team score-picker__team--1" :class="{ 'score-picker__team--winner': scoreWinner === 1 }">{{ scorePicker.t1Names }}</span>
                <span class="score-picker__team score-picker__team--2" :class="{ 'score-picker__team--winner': scoreWinner === 2 }">{{ scorePicker.t2Names }}</span>
            </div>
            <div class="score-picker__deck">
                <div class="score-picker__band" aria-hidden="true"></div>
                <div class="score-picker__wheel" ref="wheelT1" @scroll.passive="onWheelScroll('t1', $event)">
                    <div class="score-picker__spacer"></div>
                    <div v-for="value in scoreValues" :key="'t1-' + value" class="score-picker__item" :class="{ 'score-picker__item--active': scorePicker.t1 === value }">{{ value }}</div>
                    <div class="score-picker__spacer"></div>
                </div>
                <span class="score-picker__colon">:</span>
                <div class="score-picker__wheel" ref="wheelT2" @scroll.passive="onWheelScroll('t2', $event)">
                    <div class="score-picker__spacer"></div>
                    <div v-for="value in scoreValues" :key="'t2-' + value" class="score-picker__item" :class="{ 'score-picker__item--active': scorePicker.t2 === value }">{{ value }}</div>
                    <div class="score-picker__spacer"></div>
                </div>
            </div>
            <p class="score-picker__hint" :class="{ 'score-picker__hint--error': !scoreValid }">{{ scoreHint }}</p>
            <div class="score-picker__actions">
                <button class="score-picker__btn score-picker__btn--skip" type="button" @click="skipScore">SKIP</button>
                <button class="score-picker__btn score-picker__btn--confirm" :class="'score-picker__btn--team-' + (scoreWinner || scorePicker.team)" type="button" :disabled="!scoreValid" @click="confirmScore">CONFIRM</button>
            </div>
        </div>
    </div>

    <!-- Court rename popover — inline roller deck of court numbers 1–20 -->
    <div v-if="courtRename.show" class="court-name-picker" :style="{ left: courtRename.x + 'px', top: courtRename.y + 'px' }" @click.stop>
        <div class="court-name-picker__deck">
            <div class="court-name-picker__band" aria-hidden="true"></div>
            <div class="court-name-picker__wheel" ref="courtWheel" @scroll.passive="onCourtWheelScroll">
                <div class="court-name-picker__spacer"></div>
                <div v-for="value in courtRenameValues" :key="value" class="court-name-picker__item" :class="{ 'court-name-picker__item--active': courtRename.value === value }" @click="selectCourtName(value)">Court {{ value }}</div>
                <div class="court-name-picker__spacer"></div>
            </div>
        </div>
    </div>
</div>

<script>
const SESSION_ID = <?= (int) ($sessionId ?? 0) ?>;
const START_STATUS = <?= json_encode($sessionStatus ?? 'UNKNOWN', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
const START_NAME = <?= json_encode($sessionName ?? 'Session', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
const BASE_URL = <?= json_encode(($base ?? '') . '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').getAttribute("content");

const { createApp, ref, reactive, computed, onMounted, onUnmounted, watch, nextTick, TransitionGroup } = Vue;

createApp({
    setup() {
        const session = reactive({ status: START_STATUS, type: 'casual', sport: 'badminton' });
        const sessionName = ref(START_NAME);
        const matchmakingMode = ref('smart');
        const modeLabel = computed(() => matchmakingMode.value === 'peg' ? 'Traditional Pegs' : 'Smart Match Making');
        const courts = ref([]);
        const updatingCourts = ref(false);
        const sessionActionPending = ref(null);
        const uiPending = reactive({ mode: false, fill: false, add: false, player: {}, court: {}, clear: {}, gender: {} });
        const players = ref([]);
        const tournament = ref(null);
        const history = ref([]);
        const historyTotal = ref(0);
        const historySearch = ref('');
        const filteredHistory = computed(() => {
            const query = historySearch.value.trim().toLowerCase();
            return history.value
                .map(match => ({
                    id: match.id,
                    gameNumber: match.game_number,
                    courtNumber: match.court ? match.court.court_number : null,
                    team1: historyTeam(match, 1),
                    team2: historyTeam(match, 2),
                    winner: match.winning_team,
                    team1Rating: match.team_1_rating != null ? Math.round(Number(match.team_1_rating)) : null,
                    team2Rating: match.team_2_rating != null ? Math.round(Number(match.team_2_rating)) : null,
                }))
                .filter(m => !query || (m.team1 + ' ' + m.team2).toLowerCase().includes(query));
        });
        const pendingAddedPlayers = ref([]);
        const waitingPlayers = computed(() => players.value.filter(p => p.status === 'WAITING'));
        const activePlayers = computed(() => players.value.filter(p => p.status !== 'LEFT'));
        const emptyCourts = computed(() => courts.value.filter(court => !court.match));
        const canFillCourts = computed(() => session.status === 'ACTIVE' && session.type !== 'tournament' && emptyCourts.value.length > 0);
        const missingGenderPlayers = computed(() => waitingPlayers.value.filter(p => !p.player || !p.player.gender));
        const blockedByMissingGender = computed(() => session.status === 'ACTIVE' && session.type !== 'tournament' && emptyCourts.value.length > 0 && waitingPlayers.value.length >= 4 && missingGenderPlayers.value.length > 0);
        const manualAssignment = reactive({ show: false, court: null, playerIds: [], submitting: false, error: '' });
        const manualDraggedId = ref(null);
        const manualDragOverId = ref(null);
        const manualTapId = ref(null);
        const manualTeams = computed(() => {
            const ids = manualAssignment.playerIds;
            return [
                ids.slice(0, 2).map(id => waitingPlayers.value.find(sp => sp.player_id === id)).filter(Boolean),
                ids.slice(2, 4).map(id => waitingPlayers.value.find(sp => sp.player_id === id)).filter(Boolean),
            ];
        });
        const dragOverCourtId = ref(null);
        const draggedQueuePlayerId = ref(null);
        const subDragOver = ref(null);
        const dragOverPlayingCourtId = ref(null);
        const pendingDropSlot = ref(null);
        const pickedQueuePlayerId = ref(null);
        const pickedQueuePlayer = computed(() => players.value.find(p => p.player_id === pickedQueuePlayerId.value) || null);
        const touchDrag = reactive({ active: false, playerId: null, sp: null, x: 0, y: 0, startX: 0, startY: 0, moved: false, halfW: 130, halfH: 35 });
        const TOUCH_DRAG_THRESHOLD = 8;
        let suppressPickClickUntil = 0;
        // Players manually dragged from NEXT UP onto each empty court, keyed by
        // court id. When a court reaches four, START MATCH submits them (the
        // server auto-balances the teams).
        const pendingCourtPlayers = reactive({});
        function courtPendingSlots(courtId) {
            const list = pendingCourtPlayers[courtId];
            return (list && list.length) ? list : [null, null, null, null];
        }
        function courtPendingCount(courtId) {
            return courtPendingSlots(courtId).filter(Boolean).length;
        }

        // Player ids currently shown on a court preview — hidden from NEXT UP
        // so a player never appears in two places at once.
        const previewedPlayerIds = computed(() => {
            const ids = new Set();
            Object.values(pendingCourtPlayers).forEach(list => {
                list.forEach(sp => { if (sp) ids.add(sp.player_id); });
            });
            return ids;
        });

        // NEXT UP — waiting/paused players not already placed on a court preview.
        const queuePlayers = computed(() => {
            const order = { WAITING: 0, PAUSED: 1 };
            const previewed = previewedPlayerIds.value;
            return [...players.value, ...pendingAddedPlayers.value]
                .filter(p => (p.status === 'WAITING' || p.status === 'PAUSED') && !previewed.has(p.player_id))
                .sort((a, b) => (order[a.status] ?? 9) - (order[b.status] ?? 9));
        });
        // The first 4 WAITING players — the next game on court
        const nextFourIds = computed(() =>
            players.value
                .filter(p => p.status === 'WAITING')
                .slice(0, 4)
                .map(p => p.player_id)
        );
        const submitting = reactive({});
        const matchFeedback = reactive({});
        const insights = reactive({ show: false, loading: false, data: null, error: '' });
        const pendingResultMatchIds = new Set();

        // Courts awaiting the server's next-match allocation after a result is
        // recorded — drives the "Selecting teams…" overlay.
        const selectingCourts = reactive({});
        const selectingTimers = {};
        function markCourtSelecting(courtId) {
            if (!courtId) return;
            clearTimeout(selectingTimers[courtId]);
            selectingCourts[courtId] = true;
            selectingTimers[courtId] = setTimeout(() => {
                delete selectingCourts[courtId];
                delete selectingTimers[courtId];
            }, 20000);
        }
        function clearCourtSelecting(courtId) {
            if (!courtId) return;
            clearTimeout(selectingTimers[courtId]);
            delete selectingCourts[courtId];
            delete selectingTimers[courtId];
        }

        // Score picker — the wheels are index-addressed, so value === index.
        const MATCH_POINTS = 21;
        const CLOSE_MARGIN = 3;
        const SCORE_ITEM_HEIGHT = 56;
        const scoreValues = Object.freeze(Array.from({ length: 41 }, (_, index) => index));
        const scorePicker = reactive({ show: false, matchId: null, team: 1, courtNumber: null, courtLabel: null, t1: 21, t2: 15, t1Names: '', t2Names: '' });
        const wheelT1 = ref(null);
        const wheelT2 = ref(null);
        const wheelFrames = { t1: 0, t2: 0 };
        let scorePickerSpot = null;

        // Court rename popover — a compact roller deck of court numbers 1–20.
        const courtValues = Object.freeze(Array.from({ length: 20 }, (_, index) => index + 1));
        const COURT_ITEM_HEIGHT = 44;
        const courtRename = reactive({ show: false, court: null, value: null, x: 0, y: 0 });
        const courtRenameValues = computed(() => {
            const taken = new Set();
            courts.value.forEach(c => {
                if (courtRename.court && c.court_number === courtRename.court.court_number) return;
                const label = c.name || ('Court ' + c.court_number);
                const matched = /(\d+)/.exec(label || '');
                if (matched) taken.add(parseInt(matched[1], 10));
            });
            return courtValues.filter(v => !taken.has(v));
        });
        const courtWheel = ref(null);
        let courtWheelFrame = 0;
        const celebration = ref(null);
        const celebrationParticles = Array.from({ length: 28 }, (_, index) => index);
        let celebrationTimer = null;
        const connectionState = ref('connecting');
        const authError = ref(false);
        const elapsed = ref('');
        const showPlayers = ref(false);
        const showSuggestions = ref(false);
        const newPlayerName = ref('');
        const newPlayerGender = ref('');
        const playerNameInput = ref(null);
        const allKnownPlayers = ref([]);
        const pendingExistingPlayerIds = ref(new Set());
        let blurTimer = null;
        const availablePlayers = computed(() =>
            allKnownPlayers.value.filter(p =>
                !activePlayers.value.some(sp => sp.player_id === p.id)
                && !pendingExistingPlayerIds.value.has(p.id)
            )
        );
        // Autocomplete: exclude players already in the session. Show the top 10
        // (by rating) when the box is empty/focused, and matching names when the
        // user actually searches.
        const playerSuggestions = computed(() => {
            const q = newPlayerName.value.trim().toLowerCase();
            const inSession = new Set(activePlayers.value.map(sp => sp.player_id));
            const pool = allKnownPlayers.value.filter(p =>
                !inSession.has(p.id) && !pendingExistingPlayerIds.value.has(p.id)
            );

            if (!q) {
                return [...pool].sort((a, b) => (b.rating || 0) - (a.rating || 0)).slice(0, 10);
            }

            return pool
                .filter(p => (p.name || '').toLowerCase().startsWith(q))
                .slice(0, 10);
        });
        function isInSession(playerId) {
            return activePlayers.value.some(sp => sp.player_id === playerId);
        }
        const confirmRemove = ref({ show: false, spId: null, name: '', isPlaying: false, loading: false });
        const confirmDelete = ref({ show: false, playerId: null, name: '', loading: false });
        const confirmNewSession = ref({ show: false });
        const showTeams = ref(false);
        const teamsList = ref([]);
        const teamsError = ref('');
        const teamsLoading = ref(false);
        const selectedPlayerId = ref(null);
        const draggedPlayerId = ref(null);
        const dragOverPlayerId = ref(null);

        async function loadTeams() {
            teamsLoading.value = true;
            teamsError.value = '';
            try {
                const res = await fetch(BASE_URL + '/api/sessions/' + SESSION_ID + '/tournament/teams', {
                    credentials: 'include',
                    headers: { 'Accept': 'application/json' },
                });
                const json = await res.json();
                if (!res.ok) throw new Error(json.message || 'Failed to load teams');
                teamsList.value = json.data;
            } catch (ex) {
                teamsError.value = ex.message;
            } finally {
                teamsLoading.value = false;
            }
        }
        function openTeams() {
            selectedPlayerId.value = null;
            draggedPlayerId.value = null;
            dragOverPlayerId.value = null;
            showTeams.value = true;
            loadTeams();
        }
        function closeTeams() {
            showTeams.value = false;
            selectedPlayerId.value = null;
            draggedPlayerId.value = null;
            dragOverPlayerId.value = null;
        }
        async function performSwap(playerIdA, playerIdB) {
            teamsError.value = '';
            if (offlineMode.value) {
                queueAction('POST', '/api/sessions/' + SESSION_ID + '/tournament/teams/swap', { player_id_a: playerIdA, player_id_b: playerIdB }, 'Swap tournament players');
                teamsError.value = "You're offline — this swap is queued and will apply once you reconnect.";
                return;
            }
            try {
                const result = await sendRequest('POST', '/api/sessions/' + SESSION_ID + '/tournament/teams/swap', { player_id_a: playerIdA, player_id_b: playerIdB });
                if (!result.ok) throw new Error(result.data.message || 'Failed to swap players');
                teamsList.value = result.data.data;
            } catch (ex) {
                teamsError.value = ex.message;
            }
        }
        // Tap-to-swap fallback for touch devices where native drag isn't available.
        function selectPlayerForSwap(playerId) {
            if (selectedPlayerId.value === null) {
                selectedPlayerId.value = playerId;
                return;
            }
            if (selectedPlayerId.value === playerId) {
                selectedPlayerId.value = null;
                return;
            }
            const a = selectedPlayerId.value;
            selectedPlayerId.value = null;
            performSwap(a, playerId);
        }
        function onPlayerDragStart(playerId, event) {
            draggedPlayerId.value = playerId;
            selectedPlayerId.value = null;
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', String(playerId));
        }
        function onPlayerDragEnd() {
            draggedPlayerId.value = null;
            dragOverPlayerId.value = null;
        }
        function onPlayerDrop(playerId, event) {
            event.preventDefault();
            dragOverPlayerId.value = null;
            const source = draggedPlayerId.value;
            draggedPlayerId.value = null;
            if (!source || source === playerId) return;
            performSwap(source, playerId);
        }
        async function regenerateTeams() {
            teamsLoading.value = true;
            teamsError.value = '';
            if (offlineMode.value) {
                queueAction('POST', '/api/sessions/' + SESSION_ID + '/tournament/teams/regenerate', null, 'Shuffle tournament teams');
                teamsError.value = "You're offline — the shuffle is queued and will apply once you reconnect.";
                teamsLoading.value = false;
                return;
            }
            try {
                const result = await sendRequest('POST', '/api/sessions/' + SESSION_ID + '/tournament/teams/regenerate', undefined);
                if (!result.ok) throw new Error(result.data.message || 'Failed to shuffle teams');
                teamsList.value = result.data.data;
            } catch (ex) {
                teamsError.value = ex.message;
            } finally {
                teamsLoading.value = false;
            }
        }
        let pollTimer = null;
        let lastEventId = 0;
        let hasLoadedSnapshot = false;
        let pollDelay = 3000;
        let pollingStopped = false;

        // --- Offline mode -------------------------------------------------
        // Entered automatically whenever the server can't be reached (or
        // manually, via the offline indicator's menu). Every mutating action
        // is queued to localStorage instead of sent, so nothing is lost.
        // Once the server is reachable again we stop and ask the user
        // whether to sync or discard, rather than silently applying (or
        // losing) what happened while offline.
        const OFFLINE_QUEUE_KEY = 'courtly-offline-queue-' + SESSION_ID;
        const OFFLINE_PREFERENCE_KEY = 'courtly-offline-preference';
        function loadOfflineQueue() {
            try {
                const parsed = JSON.parse(localStorage.getItem(OFFLINE_QUEUE_KEY) || '[]');
                return Array.isArray(parsed) ? parsed : [];
            } catch {
                return [];
            }
        }
        function loadOfflinePreference() {
            try {
                const stored = localStorage.getItem(OFFLINE_PREFERENCE_KEY);
                return ['auto', 'offline', 'online'].includes(stored) ? stored : 'auto';
            } catch {
                return 'auto';
            }
        }
        const offlineQueue = ref(loadOfflineQueue());
        const offlinePreference = ref(loadOfflinePreference());
        // Start in offline mode whenever forced, or whenever a queue was left
        // over from a previous visit — better to surface it via the sync
        // prompt than to silently sit on unsynced changes.
        const offlineMode = ref(offlinePreference.value === 'offline' || offlineQueue.value.length > 0);
        const offlineMenuOpen = ref(false);
        const syncPrompt = reactive({ show: false, syncing: false, error: '' });

        // Manually force Automatic / Offline / Online from the indicator's
        // menu. Switching away from a forced Offline (or out of Automatic)
        // while changes are queued asks whether to sync, exactly like
        // reconnecting on its own would.
        function setOfflinePreference(pref) {
            offlinePreference.value = pref;
            try { localStorage.setItem(OFFLINE_PREFERENCE_KEY, pref); } catch { /* storage unavailable */ }
            offlineMenuOpen.value = false;

            if (pref === 'offline') {
                offlineMode.value = true;
                syncPrompt.show = false;
                return;
            }

            if (offlineQueue.value.length > 0) {
                offlineMode.value = true;
                syncPrompt.show = true;
            } else {
                offlineMode.value = false;
            }
        }

        function saveOfflineQueue() {
            try {
                if (offlineQueue.value.length) localStorage.setItem(OFFLINE_QUEUE_KEY, JSON.stringify(offlineQueue.value));
                else localStorage.removeItem(OFFLINE_QUEUE_KEY);
            } catch {
                // Storage unavailable (private browsing, quota) — the queue
                // still works for the rest of this tab session, in memory.
            }
        }

        function queueAction(method, url, body, label, meta) {
            const id = (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : ('off-' + Date.now() + '-' + Math.random().toString(16).slice(2));
            const entry = { id, method, url, body: body ?? null, label, ts: Date.now() };
            // A locally-created match uses a placeholder id until it's really
            // created on sync. Tag the assignment entry that produces it so
            // syncOfflineQueue can rewrite any later queued action (e.g. its
            // eventual result) to point at the real match id once known.
            if (meta && meta.producesMatchId) entry.producesMatchId = meta.producesMatchId;
            offlineQueue.value = [...offlineQueue.value, entry];
            saveOfflineQueue();
            return { ok: true, queued: true, data: {} };
        }

        // Raw network call, bypassing the offline queue — used both for the
        // "online" path and to replay queued actions during sync.
        async function sendRequest(method, url, body) {
            const headers = { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN };
            const opts = { method, credentials: 'include', headers };
            if (body !== undefined && body !== null) {
                headers['Content-Type'] = 'application/json';
                opts.body = JSON.stringify(body);
            }
            const res = await fetch(BASE_URL + url, opts);
            let data = {};
            try { data = await res.json(); } catch { /* no JSON body, e.g. 204 */ }
            return { ok: res.ok, data };
        }

        async function apiRequest(method, url, body, label) {
            if (offlineMode.value) return queueAction(method, url, body, label);
            return sendRequest(method, url, body);
        }

        async function syncOfflineQueue() {
            if (syncPrompt.syncing) return;
            syncPrompt.syncing = true;
            syncPrompt.error = '';
            while (offlineQueue.value.length) {
                const item = offlineQueue.value[0];
                try {
                    const result = await sendRequest(item.method, item.url, item.body);
                    if (!result.ok) {
                        syncPrompt.error = 'Failed to sync "' + item.label + '" — stopped here so nothing is lost. Try again, or discard the remaining changes.';
                        break;
                    }
                    let rest = offlineQueue.value.slice(1);
                    if (item.producesMatchId) {
                        // Locally-created matches use a placeholder id until
                        // synced. Now that the real one exists, patch it into
                        // any later queued action (its eventual result) that
                        // still refers to the placeholder.
                        const realId = result.data?.data?.id;
                        if (realId) {
                            rest = rest.map(entry => (entry.url && entry.url.includes(item.producesMatchId))
                                ? { ...entry, url: entry.url.split(item.producesMatchId).join(String(realId)) }
                                : entry);
                        }
                    }
                    offlineQueue.value = rest;
                    saveOfflineQueue();
                } catch {
                    syncPrompt.error = 'Still can\'t reach the server. Try again once you have a connection.';
                    break;
                }
            }
            syncPrompt.syncing = false;
            if (!offlineQueue.value.length) {
                offlineMode.value = false;
                syncPrompt.show = false;
                syncPrompt.error = '';
                pendingAddedPlayers.value = [];
                pendingExistingPlayerIds.value = new Set();
                pollDelay = 3000;
                fetchSession().catch(() => { connectionState.value = 'offline'; });
            }
        }

        function discardOfflineQueue() {
            offlineQueue.value = [];
            saveOfflineQueue();
            offlineMode.value = false;
            syncPrompt.show = false;
            syncPrompt.error = '';
            pendingAddedPlayers.value = [];
            pendingExistingPlayerIds.value = new Set();
            pollDelay = 3000;
            fetchSession().catch(() => { connectionState.value = 'offline'; });
        }

        const offlineStatus = computed(() => {
            if (syncPrompt.show) return 'pending';
            if (offlineMode.value) return 'offline';
            return 'online';
        });
        const offlineIndicatorTitle = computed(() => {
            const prefLabel = { auto: 'Automatic', offline: 'Forced offline', online: 'Forced online' }[offlinePreference.value];
            if (syncPrompt.show) return 'Back online — review your offline changes';
            if (offlineMode.value) {
                return prefLabel + ' — ' + (offlineQueue.value.length
                    ? offlineQueue.value.length + ' change' + (offlineQueue.value.length === 1 ? '' : 's') + ' queued'
                    : 'queuing changes until you reconnect');
            }
            return prefLabel + ' — online';
        });
        function closeOfflineMenuOnOutsideClick(event) {
            if (offlineMenuOpen.value && !event.target.closest('.offline-control')) {
                offlineMenuOpen.value = false;
            }
        }

        // Fetch the full player list from the server.
        async function refreshPlayerCache() {
            try {
                const res = await fetch(BASE_URL + '/api/players', { credentials: 'include', headers: { 'Accept': 'application/json' } });
                if (res.ok) {
                    const json = await res.json();
                    const list = json.data || [];
                    allKnownPlayers.value = list;
                }
            } catch { /* server unreachable — keep cached players */ }
        }

        // Theme always follows the OS (system). No data-theme attribute is ever
        // set, so the CSS @media (prefers-color-scheme) rules apply.
        const COURT_COLORS = { 1:'#3B82F6', 2:'#EF4444', 3:'#F59E0B', 4:'#10B981', 5:'#8B5CF6', 6:'#EC4899', 7:'#06B6D4', 8:'#F97316' };
        function courtAccent(n) { return COURT_COLORS[n] || '#6B7280'; }

        async function loadKnownPlayers() {
            await refreshPlayerCache();
        }

        function openPlayers() {
            newPlayerName.value = '';
            newPlayerGender.value = '';
            showPlayers.value = true;
            showSuggestions.value = true;
            nextTick(() => playerNameInput.value && playerNameInput.value.focus());
        }
        function showSuggestionsNow() {
            clearTimeout(blurTimer);
            showSuggestions.value = true;
        }
        function hideSuggestionsLater() {
            clearTimeout(blurTimer);
            blurTimer = setTimeout(() => { showSuggestions.value = false; }, 150);
        }

        function applySessionData(d) {
            if (!d) return;
                session.status = d.status;
                session.sport = d.sport || 'badminton';
                session.type = d.type || 'casual';
                tournament.value = d.tournament || null;
                matchmakingMode.value = d.matchmaking_mode || 'smart';
                if (d.history) {
                    history.value = d.history;
                    historyTotal.value = d.history_total ?? d.history.length;
                }

                const playingMatchIds = new Set(
                    (d.matches || []).filter(match => match.status === 'PLAYING').map(match => match.id)
                );
                pendingResultMatchIds.forEach(matchId => {
                    if (!playingMatchIds.has(matchId)) pendingResultMatchIds.delete(matchId);
                });

                // Lookup of per-player session stats (wins/losses) by player id
                const stats = {};
                (d.session_players || []).forEach(sp => { stats[sp.player_id] = { wins: sp.wins, losses: sp.losses }; });

                courts.value = (d.courts || []).filter(c => c.status !== 'INACTIVE').map(c => {
                    const match = (d.matches || []).find(m =>
                        m.court_id === c.id
                        && m.status === 'PLAYING'
                        && !pendingResultMatchIds.has(m.id)
                    );
                    let md = null;
                    if (match && match.match_players && match.match_players.length === 4) {
                        const t1 = match.match_players.filter(p => p.team === 1);
                        const t2 = match.match_players.filter(p => p.team === 2);
                        const build = (mp) => ({ player_id: mp.player_id, name: mp.player.name, rating: mp.player.rating, gender: mp.player.gender, wins: (stats[mp.player_id] || {}).wins || 0, streak: mp.player.consecutive_wins || 0 });
                        md = { id: match.id, t1: [build(t1[0]), build(t1[1])], t2: [build(t2[0]), build(t2[1])] };
                    }
                    return { ...c, match: md };
                });
                courts.value.forEach(c => { if (c.match) clearCourtSelecting(c.id); });
                const serverPlayers = d.session_players || [];
                players.value = serverPlayers;
                if (pickedQueuePlayerId.value != null) {
                    const picked = serverPlayers.find(sp => sp.player_id === pickedQueuePlayerId.value);
                    if (!picked || picked.status !== 'WAITING') pickedQueuePlayerId.value = null;
                }
                const confirmedIds = new Set(serverPlayers.map(sp => sp.player_id));
                pendingAddedPlayers.value = pendingAddedPlayers.value.filter(sp => !confirmedIds.has(sp.player_id));
                pendingExistingPlayerIds.value = new Set(
                    [...pendingExistingPlayerIds.value].filter(id => !confirmedIds.has(id))
                );
                connectionState.value = 'connected';
        }

        async function fetchSession() {
            const response = await fetch(BASE_URL + '/api/sessions/' + SESSION_ID, {
                credentials: 'include',
                headers: { 'Accept': 'application/json' },
            });
            if (!response.ok) throw new Error('Session refresh failed');
            applySessionData((await response.json()).data);
        }

        async function pollEvents() {
            try {
                const query = hasLoadedSnapshot
                    ? '?last_event_id=' + lastEventId
                    : '?snapshot=1';
                const response = await fetch(BASE_URL + '/api/sessions/' + SESSION_ID + '/events' + query, {
                    credentials: 'include',
                    headers: { 'Accept': 'application/json' },
                });
                if (!response.ok) throw new Error('Event poll failed');

                const payload = (await response.json()).data;
                connectionState.value = 'connected';
                pollDelay = 3000;

                if (offlineMode.value) {
                    // The server is reachable again, but don't touch local
                    // state until the user decides what to do with the
                    // queued changes — applying the (stale) server snapshot
                    // now would silently overwrite them. Skip this entirely
                    // while forced offline — that's a deliberate choice, not
                    // something a live server response should override.
                    if (offlinePreference.value !== 'offline') {
                        if (offlineQueue.value.length > 0) {
                            syncPrompt.show = true;
                        } else {
                            offlineMode.value = false;
                        }
                    }
                } else {
                    if (payload.snapshot) {
                        applySessionData(payload.snapshot);
                    } else if ((payload.events || []).length > 0) {
                        await fetchSession();
                    }
                    lastEventId = payload.last_event_id || lastEventId;
                    hasLoadedSnapshot = true;
                }
            } catch {
                connectionState.value = 'offline';
                pollDelay = Math.min(pollDelay * 2, 15000);
                if (offlinePreference.value === 'auto' && !offlineMode.value) offlineMode.value = true;
            } finally {
                if (!pollingStopped) pollTimer = setTimeout(pollEvents, pollDelay);
            }
        }

        async function postApi(url, body, label) { return apiRequest('POST', url, body, label || url); }
        // The score determines the winner, not whichever side was originally
        // tapped to open the picker — scrolling the loser's score above the
        // winner's flips who wins.
        const scoreWinner = computed(() => {
            if (scorePicker.t1 === scorePicker.t2) return null;
            return scorePicker.t1 > scorePicker.t2 ? 1 : 2;
        });
        const scoreValid = computed(() => {
            if (!scoreWinner.value) return false;
            const winner = scoreWinner.value === 1 ? scorePicker.t1 : scorePicker.t2;
            const loser = scoreWinner.value === 1 ? scorePicker.t2 : scorePicker.t1;
            if (winner < MATCH_POINTS) return false;
            return winner === MATCH_POINTS ? (winner - loser) >= 2 : (winner - loser) === 2;
        });
        const scoreHint = computed(() => {
            const winner = Math.max(scorePicker.t1, scorePicker.t2);
            const loser = Math.min(scorePicker.t1, scorePicker.t2);
            if (winner === loser) return 'The winning team needs the higher score';
            if (winner < MATCH_POINTS) return 'A game is played to ' + MATCH_POINTS;
            if (winner === MATCH_POINTS && (winner - loser) < 2) return 'A game must be won by two';
            if (winner > MATCH_POINTS && (winner - loser) !== 2) return 'Past ' + MATCH_POINTS + ' the game ends on a two-point lead';
            return (winner - loser) <= CLOSE_MARGIN ? 'Close game — ratings move gently' : 'Comfortable win — ratings move further';
        });

        function onWheelScroll(key, event) {
            if (wheelFrames[key]) return;
            const el = event.target;
            wheelFrames[key] = requestAnimationFrame(() => {
                wheelFrames[key] = 0;
                const index = Math.max(0, Math.min(scoreValues.length - 1, Math.round(el.scrollTop / SCORE_ITEM_HEIGHT)));
                if (scorePicker[key] !== index) {
                    scorePicker[key] = index;
                    if (navigator.vibrate) navigator.vibrate(5);
                }
            });
        }

        function openScorePicker(court, team, event) {
            const match = court.match;
            if (!match || pendingResultMatchIds.has(match.id)) return;

            const card = event?.currentTarget?.closest('.court-card');
            const bounds = card?.getBoundingClientRect();
            scorePickerSpot = {
                courtId: court.id,
                x: bounds ? ((event.clientX - bounds.left) / bounds.width) * 100 : (team === 1 ? 25 : 75),
                y: bounds ? ((event.clientY - bounds.top) / bounds.height) * 100 : 50,
            };

            scorePicker.matchId = match.id;
            scorePicker.team = team;
            scorePicker.courtNumber = court.court_number;
            scorePicker.courtLabel = court.name || ('COURT ' + court.court_number);
            scorePicker.t1Names = match.t1.map(p => formatName(p.name)).join(' + ');
            scorePicker.t2Names = match.t2.map(p => formatName(p.name)).join(' + ');
            scorePicker.t1 = team === 1 ? MATCH_POINTS : 15;
            scorePicker.t2 = team === 2 ? MATCH_POINTS : 15;
            scorePicker.show = true;

            nextTick(() => {
                if (wheelT1.value) wheelT1.value.scrollTop = scorePicker.t1 * SCORE_ITEM_HEIGHT;
                if (wheelT2.value) wheelT2.value.scrollTop = scorePicker.t2 * SCORE_ITEM_HEIGHT;
            });
        }

        function closeScorePicker() {
            scorePicker.show = false;
            scorePicker.matchId = null;
            scorePicker.courtLabel = null;
            scorePickerSpot = null;
        }

        function confirmScore() {
            if (!scoreValid.value || !scorePicker.matchId || !scoreWinner.value) return;
            const payload = { matchId: scorePicker.matchId, team: scoreWinner.value, scores: { t1: scorePicker.t1, t2: scorePicker.t2 }, spot: scorePickerSpot };
            scorePicker.show = false;
            scorePicker.matchId = null;
            recordResult(payload.matchId, payload.team, payload.scores, payload.spot);
        }

        function skipScore() {
            if (!scorePicker.matchId) return;
            const payload = { matchId: scorePicker.matchId, team: scorePicker.team, spot: scorePickerSpot };
            scorePicker.show = false;
            scorePicker.matchId = null;
            recordResult(payload.matchId, payload.team, null, payload.spot);
        }

        function openCourtRename(court, event) {
            if (!court || updatingCourts.value || session.status === 'FINISHED') return;
            const label = court.name || ('Court ' + court.court_number);
            const matched = /(\d+)/.exec(label || '');
            const initial = matched ? parseInt(matched[1], 10) : court.court_number;
            const rect = event?.currentTarget?.getBoundingClientRect();
            const width = 240;
            const height = 230;
            const left = rect ? Math.min(Math.max(8, rect.left + rect.width / 2 - width / 2), window.innerWidth - width - 8) : 8;
            const top = (rect && rect.bottom + 8 + height > window.innerHeight)
                ? Math.max(8, (rect.top || 8) - 8 - height)
                : (rect ? rect.bottom + 8 : 8);
            courtRename.court = court;
            const available = courtRenameValues.value;
            courtRename.value = available.includes(initial) ? initial : (available[0] ?? courtValues[0]);
            courtRename.x = left;
            courtRename.y = top;
            courtRename.show = true;
            nextTick(() => {
                if (courtWheel.value) {
                    const index = Math.max(0, courtRenameValues.value.indexOf(courtRename.value));
                    courtWheel.value.scrollTop = index * COURT_ITEM_HEIGHT;
                }
            });
        }

        function closeCourtRename() {
            courtRename.show = false;
            courtRename.court = null;
            courtRename.value = null;
        }

        function closeCourtRenameOnOutsideClick(event) {
            if (courtRename.show
                && !event.target.closest('.court-name-picker')
                && !event.target.closest('.court-card__number--editable')) {
                closeCourtRename();
            }
        }

        function onCourtWheelScroll(event) {
            if (courtWheelFrame) return;
            const el = event.target;
            courtWheelFrame = requestAnimationFrame(() => {
                courtWheelFrame = 0;
                const values = courtRenameValues.value;
                const index = Math.max(0, Math.min(values.length - 1, Math.round(el.scrollTop / COURT_ITEM_HEIGHT)));
                const value = values[index];
                if (value != null && courtRename.value !== value) {
                    courtRename.value = value;
                    if (navigator.vibrate) navigator.vibrate(5);
                }
            });
        }

        function selectCourtName(value) {
            if (!courtRename.court || !value) return;
            const courtNumber = courtRename.court.court_number;
            const name = 'Court ' + value;
            closeCourtRename();
            adjustCourts('rename', courtNumber, name);
        }

        async function recordResult(matchId, team, scores = null, spot = null) {
            const submissionKey = matchId + '_' + team;
            if (pendingResultMatchIds.has(matchId)) return;
            submitting[submissionKey] = true;
            pendingResultMatchIds.add(matchId);

            const court = courts.value.find(item => item.match && item.match.id === matchId);
            const previousMatch = court ? court.match : null;
            const optimisticPlayers = [];

            if (court && previousMatch) {
                // Optimistic: free the court and return its players to NEXT UP
                // immediately, before the server round-trip completes. The real
                // ratings/wins arrive via the following fetch/poll.
                [...(previousMatch.t1 || []), ...(previousMatch.t2 || [])].forEach(mp => {
                    const sp = players.value.find(item => item.player_id === mp.player_id);
                    if (sp) {
                        optimisticPlayers.push({ sp, status: sp.status, games: sp.games_played });
                        sp.status = 'WAITING';
                        sp.games_played = (sp.games_played || 0) + 1;
                    }
                });
                court.match = null;
                if (!offlineMode.value) markCourtSelecting(court.id);
                celebration.value = { courtId: court.id, x: spot ? spot.x : (team === 1 ? 25 : 75), y: spot ? spot.y : 50 };
                clearTimeout(celebrationTimer);
                celebrationTimer = setTimeout(() => { celebration.value = null; }, 850);
                if (offlineMode.value) autoFillCourtsOffline();
            }

            const revert = () => {
                if (court && !court.match) court.match = previousMatch;
                optimisticPlayers.forEach(({ sp, status, games }) => {
                    sp.status = status;
                    sp.games_played = games;
                });
                if (court) clearCourtSelecting(court.id);
                celebration.value = null;
            };

            const courtLabel = court ? (court.name || ('Court ' + court.court_number)) : 'Court ?';
            const label = 'Match result — ' + courtLabel + ' (Team ' + team + ' won)';
            apiRequest('POST', '/api/matches/' + matchId + '/result', scores
                ? { winning_team: team, team_1_score: scores.t1, team_2_score: scores.t2 }
                : { winning_team: team }, label)
                .then(result => {
                    if (!result.ok) {
                        pendingResultMatchIds.delete(matchId);
                        revert();
                        return;
                    }
                    if (result.queued) return;

                    const completedMatch = result.data?.data?.match;
                    if (completedMatch && !history.value.some(match => match.id === completedMatch.id)) {
                        history.value = [completedMatch, ...history.value];
                        historyTotal.value += 1;
                    }
                    fetchSession().catch(() => { connectionState.value = 'offline'; });
                })
                .catch(() => {
                    pendingResultMatchIds.delete(matchId);
                    revert();
                })
                .finally(() => { submitting[submissionKey] = false; });
        }
        async function startNewSession() {
            confirmNewSession.value = { show: true };
        }
        async function doStartNewSession() {
            confirmNewSession.value = { show: false };
            sessionActionPending.value = 'newSession';
            const courtCount = courts.value.length || 3;
            try {
                const res = await fetch(BASE_URL + '/api/sessions', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                    credentials: 'include',
                    body: JSON.stringify({ name: sessionName.value, number_of_courts: courtCount })
                });
                const json = await res.json();
                if (!res.ok || !json.data || !json.data.id) { sessionActionPending.value = null; return; }
                window.location.href = BASE_URL + '/sessions/' + json.data.id + '/live';
            } catch (e) {
                sessionActionPending.value = null;
            }
        }
        async function toggleMode() {
            if (uiPending.mode) return;
            const next = matchmakingMode.value === 'peg' ? 'smart' : 'peg';
            const previous = matchmakingMode.value;
            matchmakingMode.value = next;
            uiPending.mode = true;
            try {
                const result = await postApi('/api/sessions/' + SESSION_ID + '/matchmaking-mode', { mode: next }, 'Switch matchmaking mode to ' + next.toUpperCase());
                if (!result.ok) matchmakingMode.value = previous;
            } catch {
                matchmakingMode.value = previous;
            } finally {
                uiPending.mode = false;
            }
        }
        async function fillCourts() {
            if (uiPending.fill) return;
            uiPending.fill = true;
            try {
                if (offlineMode.value) {
                    autoFillCourtsOffline();
                    return;
                }
                const result = await postApi('/api/sessions/' + SESSION_ID + '/fill', undefined, 'Fill empty courts');
                if (result.ok) await fetchSession();
            } finally {
                uiPending.fill = false;
            }
        }
        async function adjustCourts(action, courtNumber = null, courtName = null) {
            if (updatingCourts.value) return;

            const body = { action };
            if (courtNumber != null) body.court_number = courtNumber;
            let label = action === 'add' ? 'Add a court' : 'Remove court ' + courtNumber;

            if (action === 'rename') {
                body.court_name = (courtName || '').trim();
                label = body.court_name
                    ? 'Rename court ' + courtNumber + ' to "' + body.court_name + '"'
                    : 'Reset court ' + courtNumber + ' name';
            }

            // Optimistic: update the board instantly so the user sees the
            // change before the (multi-second) server round-trip completes.
            // A failure rolls the local state back.
            let rollback = null;

            if (action === 'remove') {
                const targetNumber = courtNumber ?? courts.value.reduce((max, c) => Math.max(max, c.court_number), 0);
                const index = courts.value.findIndex(c => c.court_number === targetNumber);
                if (index >= 0) {
                    const previousCourts = courts.value;
                    const removed = courts.value[index];
                    const playingIds = new Set(
                        removed.match ? [...removed.match.t1, ...removed.match.t2].map(mp => mp.player_id) : []
                    );

                    courts.value = courts.value.filter(c => c.court_number !== targetNumber);
                    if (playingIds.size) {
                        players.value.forEach(sp => {
                            if (playingIds.has(sp.player_id)) sp.status = 'WAITING';
                        });
                    }

                    rollback = () => {
                        courts.value = previousCourts;
                        if (playingIds.size) {
                            players.value.forEach(sp => {
                                if (playingIds.has(sp.player_id)) sp.status = 'PLAYING';
                            });
                        }
                    };
                }
            } else if (action === 'add') {
                const nextNumber = courts.value.reduce((max, c) => Math.max(max, c.court_number), 0) + 1;
                if (nextNumber <= 8) {
                    const previousCourts = courts.value;
                    courts.value = [...courts.value, { id: 'offline-court-' + nextNumber, court_number: nextNumber, name: 'Court ' + nextNumber, match: null }];
                    rollback = () => { courts.value = previousCourts; };
                }
            } else if (action === 'rename' && courtNumber != null) {
                const previousCourts = courts.value;
                courts.value = courts.value.map(c =>
                    c.court_number === courtNumber ? { ...c, name: body.court_name || null } : c
                );
                rollback = () => { courts.value = previousCourts; };
            }

            updatingCourts.value = true;
            try {
                if (offlineMode.value) {
                    queueAction('PATCH', '/api/sessions/' + SESSION_ID + '/courts', body, label);
                    return;
                }

                const result = await apiRequest('PATCH', '/api/sessions/' + SESSION_ID + '/courts', body, label);
                if (result.ok) applySessionData(result.data.data);
                else if (rollback) rollback();
            } catch {
                if (rollback) rollback();
            } finally {
                updatingCourts.value = false;
            }
        }
        function clearCourt(court) {
            if (!court || uiPending.clear[court.id]) return;

            // Empty court: clear the drag-to-place preview instantly.
            if (!court.match) {
                delete pendingCourtPlayers[court.id];
                return;
            }

            const onCourt = [...court.match.t1, ...court.match.t2];
            const snapshot = JSON.parse(JSON.stringify(court.match));

            // Optimistic: free the court and return its players to the queue
            // immediately, then let the server confirm in the background.
            court.match = null;
            onCourt.forEach(mp => {
                const sp = players.value.find(p => p.player_id === mp.player_id);
                if (sp) sp.status = 'WAITING';
            });

            const revert = () => {
                court.match = snapshot;
                onCourt.forEach(mp => {
                    const sp = players.value.find(p => p.player_id === mp.player_id);
                    if (sp) sp.status = 'PLAYING';
                });
            };

            const label = 'Clear ' + (court.name || ('Court ' + court.court_number));
            uiPending.clear[court.id] = true;

            if (offlineMode.value) {
                queueAction('PATCH', '/api/sessions/' + SESSION_ID + '/courts', { action: 'clear', court_number: court.court_number }, label);
                delete uiPending.clear[court.id];
                return;
            }

            apiRequest('PATCH', '/api/sessions/' + SESSION_ID + '/courts', { action: 'clear', court_number: court.court_number }, label)
                .then(result => {
                    if (!result.ok) { revert(); return; }
                    if (result.queued) return;
                    applySessionData(result.data.data);
                })
                .catch(() => revert())
                .finally(() => { delete uiPending.clear[court.id]; });
        }
        function openManualAssignment(courtId) {
            const court = courts.value.find(c => c.id === courtId);
            if (!court || court.match) return;
            manualAssignment.show = true;
            manualAssignment.court = court;
            const ids = waitingPlayers.value.slice(0, 4).map(sp => sp.player_id);
            manualAssignment.playerIds = ids.length === 4 ? balanceManualTeam(ids) : ids;
            manualAssignment.error = '';
        }
        function closeManualAssignment() {
            manualAssignment.show = false;
            manualAssignment.court = null;
            manualAssignment.playerIds = [];
            manualAssignment.error = '';
            manualDraggedId.value = null;
            manualDragOverId.value = null;
            manualTapId.value = null;
        }
        function dragPlayerToCourtStart(sp, event) {
            if (sp.status !== 'WAITING' && sp.status !== 'PAUSED') return;
            draggedQueuePlayerId.value = sp.player_id;
            pickedQueuePlayerId.value = null;
            if (event && event.dataTransfer) {
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', String(sp.player_id));
            }
        }
        function dragPlayerToCourtEnd() {
            draggedQueuePlayerId.value = null;
            dragOverCourtId.value = null;
            subDragOver.value = null;
            dragOverPlayingCourtId.value = null;
            pendingDropSlot.value = null;
        }
        // Touch fallback for native drag-and-drop: tap a waiting player to pick
        // them up, then tap a court (or a player on court) to place/swap them.
        function pickQueuePlayer(sp, event) {
            if (sp.status !== 'WAITING' && sp.status !== 'PAUSED') return;
            if (Date.now() < suppressPickClickUntil) return;
            if (event && event.target.closest('button')) return;
            pickedQueuePlayerId.value = pickedQueuePlayerId.value === sp.player_id ? null : sp.player_id;
        }
        // Resolve the player being moved onto a court from either a native
        // drag (desktop) or a tap-to-pick (touch). Consumes whichever is set.
        function takeIncomingPlayerId() {
            const id = draggedQueuePlayerId.value ?? pickedQueuePlayerId.value;
            draggedQueuePlayerId.value = null;
            pickedQueuePlayerId.value = null;
            return id;
        }
        function dropPlayerOnCourt(court, x, y, event) {
            if (event) event.preventDefault();
            const playerId = takeIncomingPlayerId();
            dragOverCourtId.value = null;
            pendingDropSlot.value = null;
            if (!playerId || court.match) return;
            const sp = players.value.find(p => p.player_id === playerId && (p.status === 'WAITING' || p.status === 'PAUSED'));
            if (!sp) return;
            // Move the player out of any court's pending slots first.
            for (const key of Object.keys(pendingCourtPlayers)) {
                const list = pendingCourtPlayers[key];
                const idx = list.findIndex(slot => slot && slot.player_id === playerId);
                if (idx !== -1) {
                    list[idx] = null;
                    pendingCourtPlayers[key] = list;
                }
            }
            const list = courtPendingSlots(court.id);
            list[quadrantIndex(court.id, x, y)] = sp;
            pendingCourtPlayers[court.id] = list;
        }
        // Place a dragged/picked player into a specific empty-court slot.
        function placePlayerOnSlot(court, index, playerId) {
            if (!court || court.match || playerId == null) return;
            const sp = players.value.find(p => p.player_id === playerId && (p.status === 'WAITING' || p.status === 'PAUSED'));
            if (!sp) return;
            // Move the player out of any other court's pending slots first.
            for (const key of Object.keys(pendingCourtPlayers)) {
                const list = pendingCourtPlayers[key];
                const idx = list.findIndex(slot => slot && slot.player_id === playerId);
                if (idx !== -1) {
                    list[idx] = null;
                    pendingCourtPlayers[key] = list;
                }
            }
            const list = courtPendingSlots(court.id);
            list[index] = sp;
            pendingCourtPlayers[court.id] = list;
        }
        function onSlotDragOver(court, index, event) {
            if (event) event.preventDefault();
            dragOverCourtId.value = court.id;
            pendingDropSlot.value = court.id + '-' + index;
        }
        function onSlotDragLeave(court, index) {
            if (pendingDropSlot.value === court.id + '-' + index) pendingDropSlot.value = null;
        }
        function onSlotDrop(court, index, event) {
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }
            const playerId = takeIncomingPlayerId();
            dragOverCourtId.value = null;
            pendingDropSlot.value = null;
            placePlayerOnSlot(court, index, playerId);
        }
        function onSlotClick(court, index, sp, event) {
            if (sp) {
                removePendingPlayer(court.id, sp.player_id);
                return;
            }
            if (pickedQueuePlayerId.value == null) return;
            const playerId = pickedQueuePlayerId.value;
            pickedQueuePlayerId.value = null;
            placePlayerOnSlot(court, index, playerId);
        }
        // Tap-to-place fallback: an empty court body becomes a drop target for
        // a picked player, without hijacking its ASSIGN/START buttons.
        function tapEmptyCourt(court, event) {
            if (pickedQueuePlayerId.value == null) return;
            if (event.target.closest('button')) return;
            const box = event.target.closest('.court-card__player-box');
            if (box && !box.classList.contains('court-card__player-box--pending-empty')) return;
            dropPlayerOnCourt(court, event.clientX, event.clientY, null);
        }

        // Identifies a drop target on a playing court, used to highlight the
        // player box a waiting player is being dragged over.
        function subDragKey(matchId, team, index) {
            return matchId + '-' + team + '-' + index;
        }

        // Swap a waiting player (dragged from NEXT UP) onto a court in place of
        // the player they were dropped on. The replaced player returns to the
        // queue; ratings are untouched until the match actually finishes.
        function substituteOnCourt(court, outPlayerId, event, inPlayerIdOverride) {
            if (event) event.preventDefault();
            const inPlayerId = inPlayerIdOverride != null ? inPlayerIdOverride : takeIncomingPlayerId();
            subDragOver.value = null;
            dragOverPlayingCourtId.value = null;
            if (!court || !court.match || !inPlayerId || inPlayerId === outPlayerId) return;
            if (session.type === 'tournament') return;

            const inSp = players.value.find(p => p.player_id === inPlayerId);
            if (!inSp || (inSp.status !== 'WAITING' && inSp.status !== 'PAUSED')) return;

            const match = court.match;
            const onCourt = [...match.t1, ...match.t2];
            if (onCourt.some(p => p.player_id === inPlayerId)) return;

            const outSp = players.value.find(p => p.player_id === outPlayerId);
            const snapshot = JSON.parse(JSON.stringify(match));
            const outStatus = outSp ? outSp.status : null;
            const inStatus = inSp.status;

            const applyLocal = () => {
                const incoming = { player_id: inSp.player_id, name: inSp.player.name, rating: inSp.player.rating, gender: inSp.player.gender, wins: inSp.wins || 0, streak: inSp.player.consecutive_wins || 0 };
                for (const team of ['t1', 't2']) {
                    const idx = match[team].findIndex(p => p.player_id === outPlayerId);
                    if (idx !== -1) match[team].splice(idx, 1, incoming);
                }
                if (outSp) outSp.status = 'WAITING';
                inSp.status = 'PLAYING';
            };
            const revertLocal = () => {
                court.match = snapshot;
                if (outSp) outSp.status = outStatus;
                inSp.status = inStatus;
            };

            const label = 'Swap player on ' + (court.name || ('Court ' + court.court_number));
            if (offlineMode.value) {
                applyLocal();
                queueAction('POST', '/api/matches/' + match.id + '/substitute', { out_player_id: outPlayerId, in_player_id: inPlayerId }, label);
                return;
            }

            applyLocal();
            apiRequest('POST', '/api/matches/' + match.id + '/substitute', { out_player_id: outPlayerId, in_player_id: inPlayerId }, label)
                .then(result => {
                    if (!result.ok) { revertLocal(); return; }
                    if (result.queued) return;
                    fetchSession().catch(() => { connectionState.value = 'offline'; });
                })
                .catch(() => { revertLocal(); });
        }
        // Tap-to-swap fallback: when a waiting player is picked, tapping an
        // on-court player swaps them in. Otherwise the tap falls through to
        // the score picker on the team side.
        function tapOnCourtPlayer(court, playerId, event) {
            if (pickedQueuePlayerId.value == null) return;
            if (event) event.stopPropagation();
            substituteOnCourt(court, playerId, null);
        }

        // Map a drop point on a court to its corner index (0=TL,1=TR,2=BL,3=BR).
        function quadrantIndex(courtId, x, y) {
            const el = document.querySelector('.court-card[data-court-id="' + courtId + '"] .court-card__body');
            const rect = el ? el.getBoundingClientRect() : null;
            if (!rect) return 0;
            const left = x < rect.left + rect.width / 2;
            const top = y < rect.top + rect.height / 2;
            return top ? (left ? 0 : 1) : (left ? 2 : 3);
        }
        // The player occupying the corner a drop point lands on (playing court).
        function cornerPlayerId(court, x, y) {
            const el = document.querySelector('.court-card[data-court-id="' + court.id + '"] .court-card__body');
            const rect = el ? el.getBoundingClientRect() : null;
            const left = rect ? x < rect.left + rect.width / 2 : false;
            const top = rect ? y < rect.top + rect.height / 2 : false;
            const match = court.match;
            if (!match) return null;
            if (top) return left ? match.t1[0].player_id : match.t2[0].player_id;
            return left ? match.t1[1].player_id : match.t2[1].player_id;
        }
        // Highlight the specific empty-court slot under the pointer during a
        // native mouse drag.
        function onEmptyCourtDragOver(court, event) {
            dragOverCourtId.value = court.id;
            pendingDropSlot.value = court.id + '-' + quadrantIndex(court.id, event.clientX, event.clientY);
        }
        function onEmptyCourtDragLeave(court, event) {
            const next = event.relatedTarget;
            if (next && event.currentTarget && event.currentTarget.contains(next)) return;
            dragOverCourtId.value = null;
            pendingDropSlot.value = null;
        }
        // Highlight the specific playing-court corner box under the pointer.
        function onPlayingCourtDragOver(court, event) {
            const playerId = cornerPlayerId(court, event.clientX, event.clientY);
            if (playerId == null) return;
            const t1i = court.match.t1.findIndex(p => p.player_id === playerId);
            if (t1i !== -1) subDragOver.value = subDragKey(court.match.id, 1, t1i);
            else {
                const t2i = court.match.t2.findIndex(p => p.player_id === playerId);
                if (t2i !== -1) subDragOver.value = subDragKey(court.match.id, 2, t2i);
            }
        }
        function onPlayingCourtDragLeave(court, event) {
            const next = event.relatedTarget;
            if (next && event.currentTarget && event.currentTarget.contains(next)) return;
            subDragOver.value = null;
        }

        // Whole-card drop target for a playing court: a drop on the card (but
        // not directly on a player box) swaps the dragged player in for the
        // player occupying the corner the drop landed on.
        function dropOnPlayingCourt(court, x, y, event) {
            if (event) {
                if (event.target && event.target.closest && event.target.closest('.court-card__player-box')) return;
                event.preventDefault();
            }
            const inPlayerId = takeIncomingPlayerId();
            dragOverPlayingCourtId.value = null;
            subDragOver.value = null;
            if (!inPlayerId || !court || !court.match) return;
            const outPlayerId = cornerPlayerId(court, x, y);
            if (outPlayerId == null) return;
            substituteOnCourt(court, outPlayerId, null, inPlayerId);
        }

        // --- Touch drag (pointer events) ---------------------------------
        // Native HTML5 drag-and-drop never fires on touch, so waiting-player
        // cards are also draggable via pointer events. A quick tap still falls
        // through to pickQueuePlayer; a press-and-move becomes a drag with a
        // floating ghost that drops on whichever court is under the finger.
        function onCardPointerDown(sp, event) {
            if (event.pointerType !== 'touch' && event.pointerType !== 'pen') return;
            if (sp.status !== 'WAITING' && sp.status !== 'PAUSED') return;
            if (event.target && event.target.closest && event.target.closest('button')) return;
            touchDrag.playerId = sp.player_id;
            touchDrag.sp = sp;
            touchDrag.halfW = (event.currentTarget.offsetWidth || 260) / 2;
            touchDrag.halfH = (event.currentTarget.offsetHeight || 70) / 2;
            touchDrag.x = event.clientX;
            touchDrag.y = event.clientY;
            touchDrag.startX = event.clientX;
            touchDrag.startY = event.clientY;
            touchDrag.moved = false;
            touchDrag.active = false;
            try { event.currentTarget.setPointerCapture(event.pointerId); } catch { /* ignore */ }
        }
        function onCardPointerMove(sp, event) {
            if (touchDrag.playerId !== sp.player_id) return;
            touchDrag.x = event.clientX;
            touchDrag.y = event.clientY;
            if (!touchDrag.moved) {
                const dx = event.clientX - touchDrag.startX;
                const dy = event.clientY - touchDrag.startY;
                if (Math.hypot(dx, dy) < TOUCH_DRAG_THRESHOLD) return;
                touchDrag.moved = true;
                touchDrag.active = true;
                draggedQueuePlayerId.value = touchDrag.playerId;
            }
            updateTouchDragTarget(event.clientX, event.clientY);
        }
        function onCardPointerUp(sp, event) {
            if (touchDrag.playerId !== sp.player_id) return;
            const wasDragging = touchDrag.active;
            const playerId = touchDrag.playerId;
            const el = wasDragging ? document.elementFromPoint(touchDrag.x, touchDrag.y) : null;
            if (wasDragging) suppressPickClickUntil = Date.now() + 60;
            clearTouchDrag();
            if (wasDragging && el) completeTouchDrop(el, playerId);
        }
        function onCardPointerCancel(sp, event) {
            if (touchDrag.playerId === sp.player_id) clearTouchDrag();
        }
        function clearTouchDrag() {
            touchDrag.active = false;
            touchDrag.moved = false;
            touchDrag.playerId = null;
            touchDrag.sp = null;
            draggedQueuePlayerId.value = null;
            dragOverCourtId.value = null;
            subDragOver.value = null;
            dragOverPlayingCourtId.value = null;
            pendingDropSlot.value = null;
        }
        function courtFromCard(card) {
            const id = Number(card && card.dataset ? card.dataset.courtId : NaN);
            if (!id) return null;
            return courts.value.find(c => c.id === id) || null;
        }
        function updateTouchDragTarget(x, y) {
            dragOverCourtId.value = null;
            subDragOver.value = null;
            dragOverPlayingCourtId.value = null;
            pendingDropSlot.value = null;
            const el = document.elementFromPoint(x, y);
            const card = el && el.closest ? el.closest('.court-card') : null;
            const court = courtFromCard(card);
            if (!court) return;
            if (!court.match) {
                dragOverCourtId.value = court.id;
                const slotBox = el.closest('.court-card__player-box--pending');
                if (slotBox && slotBox.dataset && slotBox.dataset.slotIndex != null) {
                    pendingDropSlot.value = court.id + '-' + slotBox.dataset.slotIndex;
                }
                return;
            }
            const box = el.closest('.court-card__player-box');
            const playerId = box && box.dataset && box.dataset.playerId ? Number(box.dataset.playerId) : cornerPlayerId(court, x, y);
            if (playerId != null) {
                const t1i = court.match.t1.findIndex(p => p.player_id === playerId);
                if (t1i !== -1) subDragOver.value = subDragKey(court.match.id, 1, t1i);
                else {
                    const t2i = court.match.t2.findIndex(p => p.player_id === playerId);
                    if (t2i !== -1) subDragOver.value = subDragKey(court.match.id, 2, t2i);
                }
            }
        }
        function completeTouchDrop(el, playerId) {
            const card = el && el.closest ? el.closest('.court-card') : null;
            const court = courtFromCard(card);
            if (!court || playerId == null) return;
            const x = touchDrag.x;
            const y = touchDrag.y;
            if (!court.match) {
                const slotBox = el.closest('.court-card__player-box--pending');
                const slotIndex = slotBox && slotBox.dataset && slotBox.dataset.slotIndex != null
                    ? Number(slotBox.dataset.slotIndex)
                    : null;
                if (slotIndex != null) placePlayerOnSlot(court, slotIndex, playerId);
                return;
            }
            const box = el.closest('.court-card__player-box');
            const outPlayerId = box && box.dataset && box.dataset.playerId
                ? Number(box.dataset.playerId)
                : cornerPlayerId(court, x, y);
            if (outPlayerId != null) substituteOnCourt(court, outPlayerId, null, playerId);
        }

        function removePendingPlayer(courtId, playerId) {
            const list = pendingCourtPlayers[courtId] || [];
            const idx = list.findIndex(slot => slot && slot.player_id === playerId);
            if (idx === -1) return;
            list[idx] = null;
            if (list.every(slot => !slot)) delete pendingCourtPlayers[courtId];
            else pendingCourtPlayers[courtId] = list;
        }
        // Builds a match card locally (offline mode only) so the court
        // doesn't look stuck empty until the queued assignment syncs. The
        // server is the source of truth and recomputes this for real on sync;
        // matchId is a placeholder that syncOfflineQueue swaps for the real
        // one once the queued assignment actually runs.
        function makeOfflineMatchId(courtId) {
            return 'offline-match-' + courtId + '-' + Date.now() + '-' + Math.random().toString(16).slice(2, 6);
        }
        function applyLocalMatch(courtId, team1Ids, team2Ids, matchId) {
            const build = (id) => {
                const sp = players.value.find(item => item.player_id === id);
                return sp
                    ? { player_id: id, name: sp.player.name, rating: sp.player.rating, gender: sp.player.gender, wins: sp.wins || 0, streak: sp.player.consecutive_wins || 0 }
                    : { player_id: id, name: '?', rating: 0, gender: null, wins: 0, streak: 0 };
            };
            const targetCourt = courts.value.find(c => c.id === courtId);
            if (targetCourt) {
                targetCourt.match = { id: matchId, t1: team1Ids.map(build), t2: team2Ids.map(build) };
            }
            [...team1Ids, ...team2Ids].forEach(id => {
                const sp = players.value.find(item => item.player_id === id);
                if (sp) sp.status = 'PLAYING';
            });
        }

        // Fills empty courts from the waiting list while offline, the same
        // way the server's matchmaking pass normally would. This is a plain
        // rating-balanced grouping, not the real fairness/rotation/repeat-
        // avoidance algorithm — the server recalculates that for real once
        // each queued assignment actually runs on sync.
        function autoFillCourtsOffline() {
            if (!offlineMode.value || session.status !== 'ACTIVE' || session.type === 'tournament') return;
            // Mirrors the real matchmaking rule: players who just came off a
            // court wait in the queue until every court is free, rather than
            // trickling straight back in while others are still playing.
            if (courts.value.some(c => c.match)) return;
            const emptyCourts = courts.value.filter(c => !c.match && typeof c.id === 'number');
            for (const court of emptyCourts) {
                const waiting = players.value.filter(p => p.status === 'WAITING');
                if (waiting.length < 4) break;
                const ids = waiting.slice(0, 4).map(sp => sp.player_id);
                const balanced = balanceManualTeam(ids);
                const team1Ids = balanced.slice(0, 2);
                const team2Ids = balanced.slice(2, 4);
                const matchId = makeOfflineMatchId(court.id);
                applyLocalMatch(court.id, team1Ids, team2Ids, matchId);
                queueAction('POST', '/api/sessions/' + SESSION_ID + '/manual-assignment', {
                    court_id: court.id, player_ids: ids, team_1_ids: team1Ids, team_2_ids: team2Ids,
                }, 'Auto-fill ' + (court.name || ('Court ' + court.court_number)), { producesMatchId: matchId });
            }
        }
        async function startCourtMatch(courtId) {
            const list = pendingCourtPlayers[courtId] || [];
            const filled = list.filter(Boolean);
            if (filled.length !== 4 || uiPending.court[courtId]) return;
            uiPending.court[courtId] = true;
            try {
                const playerIds = filled.map(sp => sp.player_id);
                const court = courts.value.find(c => c.id === courtId);
                const label = 'Assign ' + (court ? (court.name || ('Court ' + court.court_number)) : '') + ' from queue';

                if (offlineMode.value) {
                    const balanced = balanceManualTeam(playerIds);
                    const matchId = makeOfflineMatchId(courtId);
                    applyLocalMatch(courtId, balanced.slice(0, 2), balanced.slice(2, 4), matchId);
                    queueAction('POST', '/api/sessions/' + SESSION_ID + '/manual-assignment', { court_id: courtId, player_ids: playerIds }, label, { producesMatchId: matchId });
                    delete pendingCourtPlayers[courtId];
                    return;
                }

                const result = await postApi('/api/sessions/' + SESSION_ID + '/manual-assignment', { court_id: courtId, player_ids: playerIds }, label);
                if (result.ok) {
                    delete pendingCourtPlayers[courtId];
                    await fetchSession();
                }
            } finally {
                delete uiPending.court[courtId];
            }
        }
        function toggleManualPlayer(playerId) {
            const selected = manualAssignment.playerIds;
            if (selected.includes(playerId)) {
                manualAssignment.playerIds = selected.filter(id => id !== playerId);
            } else if (selected.length < 4) {
                const next = [...selected, playerId];
                manualAssignment.playerIds = next.length === 4 ? balanceManualTeam(next) : next;
            }
        }
        function balanceManualTeam(ids) {
            const selected = ids.map(id => players.value.find(sp => sp.player_id === id)).filter(Boolean);
            selected.sort((a, b) => (Number(a.player.rating) || 0) - (Number(b.player.rating) || 0));
            return [selected[0].player_id, selected[3].player_id, selected[1].player_id, selected[2].player_id];
        }
        function swapManualPlayers(playerIdA, playerIdB) {
            const ids = [...manualAssignment.playerIds];
            const a = ids.indexOf(playerIdA);
            const b = ids.indexOf(playerIdB);
            if (a === -1 || b === -1 || a === b) return;
            [ids[a], ids[b]] = [ids[b], ids[a]];
            manualAssignment.playerIds = ids;
        }
        function manualDragStart(playerId, event) {
            manualDraggedId.value = playerId;
            manualTapId.value = null;
            if (event && event.dataTransfer) {
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', String(playerId));
            }
        }
        function manualDragEnd() {
            manualDraggedId.value = null;
            manualDragOverId.value = null;
        }
        function manualDrop(playerId, event) {
            if (event) event.preventDefault();
            const source = manualDraggedId.value;
            manualDragEnd();
            if (source && source !== playerId) swapManualPlayers(source, playerId);
        }
        function manualTap(playerId) {
            if (manualTapId.value === null) {
                manualTapId.value = playerId;
                return;
            }
            if (manualTapId.value === playerId) {
                manualTapId.value = null;
                return;
            }
            const a = manualTapId.value;
            manualTapId.value = null;
            swapManualPlayers(a, playerId);
        }
        async function submitManualAssignment() {
            if (!manualAssignment.court || manualAssignment.playerIds.length !== 4 || manualAssignment.submitting) return;
            manualAssignment.submitting = true;
            manualAssignment.error = '';
            const court = manualAssignment.court;
            const playerIds = manualAssignment.playerIds;
            const team1Ids = playerIds.slice(0, 2);
            const team2Ids = playerIds.slice(2, 4);
            const label = 'Assign ' + (court.name || ('Court ' + court.court_number)) + ' manually';

            if (offlineMode.value) {
                const matchId = makeOfflineMatchId(court.id);
                applyLocalMatch(court.id, team1Ids, team2Ids, matchId);
                queueAction('POST', '/api/sessions/' + SESSION_ID + '/manual-assignment', { court_id: court.id, player_ids: playerIds, team_1_ids: team1Ids, team_2_ids: team2Ids }, label, { producesMatchId: matchId });
                closeManualAssignment();
                manualAssignment.submitting = false;
                return;
            }

            try {
                const result = await postApi('/api/sessions/' + SESSION_ID + '/manual-assignment', {
                    court_id: court.id,
                    player_ids: playerIds,
                    team_1_ids: team1Ids,
                    team_2_ids: team2Ids,
                }, label);
                if (!result.ok) {
                    manualAssignment.error = result.data.message || 'Unable to start this match.';
                    return;
                }
                closeManualAssignment();
                await fetchSession();
            } catch {
                manualAssignment.error = 'Unable to start this match.';
            } finally {
                manualAssignment.submitting = false;
            }
        }
        async function startSession() {
            if (sessionActionPending.value) return;
            sessionActionPending.value = 'start';
            try {
                if (offlineMode.value) { session.status = 'ACTIVE'; queueAction('POST', '/api/sessions/' + SESSION_ID + '/start', null, 'Start session'); autoFillCourtsOffline(); return; }
                await postApi('/api/sessions/' + SESSION_ID + '/start', undefined, 'Start session');
            } finally { sessionActionPending.value = null; }
        }
        async function pauseSession() {
            if (offlineMode.value) { session.status = 'PAUSED'; queueAction('POST', '/api/sessions/' + SESSION_ID + '/pause', null, 'Pause session'); return; }
            postApi('/api/sessions/' + SESSION_ID + '/pause', undefined, 'Pause session');
        }
        async function resumeSession() {
            if (sessionActionPending.value) return;
            sessionActionPending.value = 'resume';
            try {
                if (offlineMode.value) { session.status = 'ACTIVE'; queueAction('POST', '/api/sessions/' + SESSION_ID + '/resume', null, 'Resume session'); autoFillCourtsOffline(); return; }
                await postApi('/api/sessions/' + SESSION_ID + '/resume', undefined, 'Resume session');
            } finally { sessionActionPending.value = null; }
        }
        async function finishSession() {
            if (sessionActionPending.value) return;
            sessionActionPending.value = 'finish';
            try {
                if (offlineMode.value) { session.status = 'FINISHED'; queueAction('POST', '/api/sessions/' + SESSION_ID + '/finish', null, 'Finish session'); return; }
                await postApi('/api/sessions/' + SESSION_ID + '/finish', undefined, 'Finish session');
            } finally { sessionActionPending.value = null; }
        }
        async function addPlayers() {
            const name = newPlayerName.value.trim();
            const gender = newPlayerGender.value;
            if (!name || !gender || uiPending.add) return;
            uiPending.add = true;
            newPlayerName.value = '';
            newPlayerGender.value = '';

            // Optimistic: show the new player in NEXT UP immediately — the POST
            // also runs matchmaking, which can take seconds on the remote DB.
            const tempId = 'offline-player-' + Date.now();
            const pendingEntry = {
                player_id: tempId,
                player: { id: tempId, name, gender, rating: 0 },
                status: 'WAITING',
                games_played: 0,
                wins: 0,
                losses: 0,
                pending: true,
            };
            pendingAddedPlayers.value = [...pendingAddedPlayers.value, pendingEntry];

            try {
                if (offlineMode.value) {
                    queueAction('POST', '/api/sessions/' + SESSION_ID + '/players', { name, gender }, 'Add player "' + name + '"');
                    return;
                }
                const result = await postApi('/api/sessions/' + SESSION_ID + '/players', { name, gender }, 'Add player "' + name + '"');
                pendingAddedPlayers.value = pendingAddedPlayers.value.filter(sp => sp.player_id !== tempId);
                if (result.ok) await fetchSession();
            } catch {
                pendingAddedPlayers.value = pendingAddedPlayers.value.filter(sp => sp.player_id !== tempId);
            } finally {
                uiPending.add = false;
            }
        }

        async function addExistingPlayer(id) {
            if (pendingExistingPlayerIds.value.has(id) || isInSession(id)) return;

            pendingExistingPlayerIds.value = new Set(pendingExistingPlayerIds.value).add(id);
            const player = allKnownPlayers.value.find(item => item.id === id);
            if (player) {
                pendingAddedPlayers.value = [...pendingAddedPlayers.value, {
                    player_id: id,
                    player,
                    status: 'WAITING',
                    games_played: 0,
                    wins: 0,
                    losses: 0,
                    pending: true,
                }];
            }
            newPlayerName.value = '';

            try {
                const result = await postApi('/api/sessions/' + SESSION_ID + '/players', { player_ids: [id] }, 'Add existing player');
                if (result.ok) return;
            } catch {
                // Restore the suggestion when the server cannot accept it.
            }

            {
                const pending = new Set(pendingExistingPlayerIds.value);
                pending.delete(id);
                pendingExistingPlayerIds.value = pending;
                pendingAddedPlayers.value = pendingAddedPlayers.value.filter(sp => sp.player_id !== id);
            }
        }

        async function pausePlayer(spId) {
            updatePlayerStatus(spId, 'PAUSED', 'pause');
        }
        async function resumePlayer(spId) {
            updatePlayerStatus(spId, 'WAITING', 'resume');
        }
        async function setPlayerGender(playerId, gender) {
            if (!playerId || uiPending.gender[playerId]) return;
            const sp = players.value.find(p => p.player_id === playerId);
            if (!sp || !sp.player) return;
            const previous = sp.player.gender;
            sp.player.gender = gender;
            uiPending.gender[playerId] = true;
            try {
                const result = await apiRequest('PATCH', '/api/players/' + playerId, { gender }, 'Set gender for ' + sp.player.name);
                if (!result.ok) sp.player.gender = previous;
                await fetchSession();
            } catch {
                sp.player.gender = previous;
            } finally {
                delete uiPending.gender[playerId];
            }
        }
        async function updatePlayerStatus(spId, status, action) {
            if (typeof spId !== 'number' || uiPending.player[spId]) return;
            const player = players.value.find(item => item.id === spId);
            if (!player) return;
            const previous = player.status;
            player.status = status;
            uiPending.player[spId] = true;
            try {
                const label = (action === 'pause' ? 'Pause ' : 'Resume ') + player.player.name;
                const result = await postApi('/api/session-players/' + spId + '/' + action, undefined, label);
                if (!result.ok) player.status = previous;
                else if (offlineMode.value && action === 'resume') autoFillCourtsOffline();
            } catch {
                player.status = previous;
            } finally {
                delete uiPending.player[spId];
            }
        }

        // Styled remove confirmation
        function openRemove(sp) {
            confirmRemove.value = { show: true, spId: sp.id, name: sp.player.name, isPlaying: sp.status === 'PLAYING', loading: false };
        }
        async function confirmLeave() {
            if (!confirmRemove.value.spId || confirmRemove.value.loading) return;
            const spId = confirmRemove.value.spId;
            const name = confirmRemove.value.name;
            confirmRemove.value.loading = true;
            try {
                if (offlineMode.value) {
                    const sp = players.value.find(item => item.id === spId);
                    if (sp) sp.status = 'LEFT';
                    queueAction('POST', '/api/session-players/' + spId + '/leave', null, 'Remove ' + name + ' from session');
                    confirmRemove.value = { show: false, spId: null, name: '', isPlaying: false, loading: false };
                    return;
                }
                const result = await postApi('/api/session-players/' + spId + '/leave', undefined, 'Remove ' + name + ' from session');
                if (result.ok) {
                    confirmRemove.value = { show: false, spId: null, name: '', isPlaying: false, loading: false };
                } else {
                    confirmRemove.value.loading = false;
                }
            } catch (e) {
                confirmRemove.value.loading = false;
            }
        }

        // Permanent delete from system
        function openDelete(sp) {
            confirmDelete.value = { show: true, playerId: sp.player_id, name: sp.player.name, loading: false };
        }
        function openDeleteById(playerId, playerName) {
            confirmDelete.value = { show: true, playerId: playerId, name: playerName, loading: false };
        }
        async function deletePlayer() {
            if (!confirmDelete.value.playerId || confirmDelete.value.loading) return;
            const playerId = confirmDelete.value.playerId;
            const name = confirmDelete.value.name;
            confirmDelete.value.loading = true;
            try {
                if (offlineMode.value) {
                    allKnownPlayers.value = allKnownPlayers.value.filter(p => p.id !== playerId);
                    players.value = players.value.filter(sp => sp.player_id !== playerId);
                    queueAction('DELETE', '/api/players/' + playerId, null, 'Delete player "' + name + '" permanently');
                    confirmDelete.value = { show: false, playerId: null, name: '', loading: false };
                    return;
                }
                const result = await apiRequest('DELETE', '/api/players/' + playerId, null, 'Delete player "' + name + '" permanently');
                if (result.ok) {
                    confirmDelete.value = { show: false, playerId: null, name: '', loading: false };
                } else {
                    confirmDelete.value.loading = false;
                }
            } catch (e) {
                confirmDelete.value.loading = false;
            }
        }

        async function submitFeedback(matchId, rating) {
            if (!matchId) return;
            const previous = matchFeedback[matchId];
            matchFeedback[matchId] = rating;
            try {
                const result = await postApi('/api/matches/' + matchId + '/feedback', { quality_rating: rating }, 'Rate match quality: ' + rating);
                if (!result.ok) matchFeedback[matchId] = previous;
            } catch {
                matchFeedback[matchId] = previous;
            }
        }

        async function openInsights() {
            insights.show = true;
            insights.error = '';
            insights.data = null;
            await loadInsights();
        }

        async function loadInsights() {
            if (insights.loading) return;
            insights.loading = true;
            insights.error = '';
            try {
                const res = await fetch(BASE_URL + '/api/sessions/' + SESSION_ID + '/matchmaking-insights', {
                    credentials: 'include',
                    headers: { 'Accept': 'application/json' },
                });
                const json = await res.json();
                if (!res.ok) throw new Error(json.message || 'Failed to load insights');
                insights.data = json.data;
            } catch (ex) {
                insights.error = ex.message;
            } finally {
                insights.loading = false;
            }
        }

        onMounted(() => {
            loadKnownPlayers();
            pollEvents();
            document.addEventListener('click', closeOfflineMenuOnOutsideClick);
            document.addEventListener('click', closeCourtRenameOnOutsideClick);
        });
        onUnmounted(() => {
            pollingStopped = true;
            if (pollTimer) clearTimeout(pollTimer);
            if (celebrationTimer) clearTimeout(celebrationTimer);
            document.removeEventListener('click', closeOfflineMenuOnOutsideClick);
            document.removeEventListener('click', closeCourtRenameOnOutsideClick);
        });

        // Lock body scroll when any modal is open
        const modalOpen = computed(() => showPlayers.value || confirmRemove.value.show || confirmDelete.value.show || confirmNewSession.value.show || manualAssignment.show || scorePicker.show || insights.show || syncPrompt.show);
        watch(modalOpen, (val) => { document.body.style.overflow = val ? 'hidden' : ''; });

        function formatName(name) {
            const parts = name.trim().split(/\s+/);
            if (parts.length < 2) return name;
            const last = parts[parts.length - 1];
            if (/^[A-Z]\.?$/i.test(last)) return name;
            parts[parts.length - 1] = last.charAt(0).toUpperCase() + '.';
            return parts.join(' ');
        }

        function genderDotClass(gender) {
            return gender === 'MALE' ? 'gender-dot--male' : gender === 'FEMALE' ? 'gender-dot--female' : 'gender-dot--missing';
        }

        function genderLabel(gender) {
            return gender === 'MALE' ? 'Male' : gender === 'FEMALE' ? 'Female' : 'Gender not set';
        }

        // Clamp a rating into the visible 1–100 badge range.
        function ratingBadge(r) { return Math.max(1, Math.min(100, Math.round(Number(r) || 0))); }

        // Rank emblems (START/RISE/PACE/APEX) — brand artwork from public/assets/ranks.
        // Exact 1x/2x/3x exports so the 28px slot never relies on browser downscaling.
        // Static markup built from a fixed tier list, so it is safe for v-html.
        const RANK_EMBLEMS = ['START', 'RISE', 'PACE', 'APEX'].reduce((set, tier) => {
            const base = `${BASE_URL}/assets/ranks/${tier.toLowerCase()}`;
            set[tier] = `<img src="${base}@1x.png" srcset="${base}@1x.png 1x, ${base}@2x.png 2x, ${base}@3x.png 3x" alt="" width="28" height="28" decoding="async">`;
            return set;
        }, {});

        // Rank tier by rating (rounded to the displayed value).
        function rankTier(r) {
            const rating = Math.round(Number(r) || 0);
            if (rating >= 75) return 'APEX';
            if (rating >= 50) return 'PACE';
            if (rating >= 25) return 'RISE';
            return 'START';
        }

        function rankIcon(r) { return RANK_EMBLEMS[rankTier(r)]; }

        // How many games this player has sat out (relative to the session leader).
        const sessionMaxGames = computed(() => players.value.reduce((m, p) => Math.max(m, p.games_played || 0), 0));
        function sitOuts(sp) { return Math.max(0, sessionMaxGames.value - (sp.games_played || 0)); }
        function historyTeam(match, team) {
            return (match.match_players || [])
                .filter(player => player.team === team)
                .map(player => formatName(player.player.name))
                .join(' + ');
        }

        return { session, sessionName, matchmakingMode, modeLabel, toggleMode, fillCourts, courts, selectingCourts, updatingCourts, sessionActionPending, uiPending, adjustCourts, players, tournament, history, historyTotal, historySearch, filteredHistory, waitingPlayers, canFillCourts, missingGenderPlayers, blockedByMissingGender, setPlayerGender, queuePlayers, nextFourIds, pendingCourtPlayers, activePlayers, submitting, celebration, celebrationParticles, connectionState, authError, elapsed, showPlayers, showSuggestions, showSuggestionsNow, hideSuggestionsLater, newPlayerName, newPlayerGender, availablePlayers, playerSuggestions, isInSession, confirmRemove, confirmDelete, confirmNewSession, dragOverCourtId, manualAssignment, manualTeams, manualDraggedId, manualDragOverId, manualTapId, openManualAssignment, dropPlayerOnCourt, onSlotDragOver, onSlotDragLeave, onSlotDrop, onSlotClick, removePendingPlayer, startCourtMatch, dragPlayerToCourtStart, dragPlayerToCourtEnd, closeManualAssignment, toggleManualPlayer, balanceManualTeam, swapManualPlayers, manualDragStart, manualDragEnd, manualDrop, manualTap, submitManualAssignment, courtAccent, submitFeedback, matchFeedback, openInsights, loadInsights, insights, recordResult, scorePicker, scoreValues, scoreValid, scoreHint, scoreWinner, wheelT1, wheelT2, onWheelScroll, openScorePicker, closeScorePicker, confirmScore, skipScore, courtRename, courtRenameValues, courtValues, courtWheel, onCourtWheelScroll, openCourtRename, closeCourtRename, selectCourtName, startSession, startNewSession, doStartNewSession, pauseSession, resumeSession, finishSession, openPlayers, addPlayers, addExistingPlayer, pausePlayer, resumePlayer, openRemove, confirmLeave, openDelete, openDeleteById, deletePlayer, formatName, genderDotClass, genderLabel, ratingBadge, rankIcon, sitOuts, historyTeam, Math, showTeams, teamsList, teamsError, teamsLoading, selectedPlayerId, draggedPlayerId, dragOverPlayerId, openTeams, closeTeams, selectPlayerForSwap, onPlayerDragStart, onPlayerDragEnd, onPlayerDrop, regenerateTeams, offlineMode, offlineQueue, offlinePreference, offlineMenuOpen, setOfflinePreference, syncPrompt, offlineStatus, offlineIndicatorTitle, syncOfflineQueue, discardOfflineQueue, subDragOver, subDragKey, substituteOnCourt, pickedQueuePlayerId, pickedQueuePlayer, pickQueuePlayer, tapEmptyCourt, tapOnCourtPlayer, dragOverPlayingCourtId, dropOnPlayingCourt, touchDrag, onCardPointerDown, onCardPointerMove, onCardPointerUp, onCardPointerCancel, pendingDropSlot, courtPendingSlots, courtPendingCount, onEmptyCourtDragOver, onEmptyCourtDragLeave, onPlayingCourtDragOver, onPlayingCourtDragLeave, clearCourt };
    }
}).mount('#courtly-app');
</script>
</body>
</html>
