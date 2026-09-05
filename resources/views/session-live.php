<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title>Courtly — <?= htmlspecialchars($sessionName ?? 'Session') ?></title>
    <link rel="icon" type="image/png" href="<?= $base ?? '/courtly' ?>/assets/favicon.png?v=3">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;700;800&family=Space+Grotesk:wght@700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
    <script>
        (function () {
            var stored = localStorage.getItem('courtly-theme');
            if (stored === 'light' || stored === 'dark') document.documentElement.setAttribute('data-theme', stored);
        })();
        function toggleCourtlyTheme() {
            var isLight = document.documentElement.getAttribute('data-theme') === 'light';
            var next = isLight ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', next);
            localStorage.setItem('courtly-theme', next);
            var button = document.getElementById('themeSwitch');
            if (button) {
                button.textContent = isLight ? '☀' : '☾';
                button.title = isLight ? 'Switch to light theme' : 'Switch to dark theme';
            }
        }
        document.addEventListener('DOMContentLoaded', function () {
            var button = document.getElementById('themeSwitch');
            var isLight = document.documentElement.getAttribute('data-theme') === 'light';
            if (button) {
                button.textContent = isLight ? '☾' : '☀';
                button.title = isLight ? 'Switch to dark theme' : 'Switch to light theme';
            }
        });
    </script>
    <link rel="stylesheet" href="<?= $base ?? '/courtly' ?>/css/courtly.css?v=50">
</head>
<body>
<div id="courtly-app">
    <header class="session-header">
        <div class="session-header__left">
            <a href="<?= $base ?? '/courtly' ?>/" class="session-header__logo" title="Back to home">
                <img src="<?= $base ?? '/courtly' ?>/assets/courtly-mark.png" alt="Courtly" class="session-header__logo-img">
            </a>
            <span class="session-sport-icon" :style="{ '--session-sport-image': 'url(/assets/' + session.sport + '.png)' }" aria-hidden="true"></span>
            <h1 class="session-header__name">{{ sessionName }}</h1>
        </div>
        <div class="session-header__stats">
            <span>👥 {{ players.length }} Players</span>
            <span class="court-count">🏟 {{ courts.length }} Courts
                <span v-if="session.status !== 'FINISHED'" class="court-count__controls">
                    <button class="court-count__button" type="button" :disabled="courts.length <= 1 || updatingCourts" @click="adjustCourts('remove')" title="Remove a court">−</button>
                    <button class="court-count__button" type="button" :disabled="courts.length >= 8 || updatingCourts" @click="adjustCourts('add')" title="Add a court">+</button>
                </span>
            </span>
            <span v-if="elapsed" class="session-header__timer">⏱ {{ elapsed }}</span>
            <span v-if="session.type === 'tournament' && tournament && tournament.format === 'round_robin' && tournament.round_progress" class="session-header__badge session-header__badge--tournament">Round {{ tournament.round_progress.current_round }}/{{ tournament.round_progress.total_rounds }}</span>
            <span v-if="session.type === 'tournament' && tournament && tournament.format === 'ladder'" class="session-header__badge session-header__badge--tournament">LADDER</span>
            <span class="session-header__badge" :class="'session-header__badge--' + session.status.toLowerCase()">{{ session.status }}</span>
            <span v-if="connectionState !== 'connected'" class="connection-dot" :class="'connection-dot--' + connectionState" :title="connectionState === 'connecting' ? 'Connecting to server…' : 'Server unreachable — data may be stale'"></span>
            <button class="mode-switch" :class="['mode-switch--' + matchmakingMode, { 'is-busy': uiPending.mode }]" :disabled="uiPending.mode" @click="toggleMode" :title="'Matchmaking: ' + modeLabel + ' — click to switch'">{{ matchmakingMode === 'peg' ? 'PEG' : 'SMART' }}</button>
            <button v-if="session.status === 'ACTIVE'" class="mode-switch mode-switch--finish" :class="{ 'is-busy': sessionActionPending === 'finish' }" :disabled="sessionActionPending === 'finish'" @click="finishSession">FINISH</button>
            <button v-if="session.type === 'tournament' && session.status === 'UPCOMING'" class="mode-switch mode-switch--players" @click="openTeams">TEAMS</button>
            <button class="mode-switch mode-switch--players" @click="openPlayers">+ PLAYERS</button>
            <button class="theme-switch" id="themeSwitch" type="button" onclick="toggleCourtlyTheme()" aria-label="Switch theme" title="Switch theme">☾</button>
            <a href="<?= $base ?? '/courtly' ?>/" class="session-header__back" title="Back to dashboard">DASHBOARD</a>
            <span class="session-header__version" title="Courtly version"><?= htmlspecialchars($appVersion ?? 'v2.0.0') ?></span>
        </div>
    </header>

    <div v-if="authError" class="sync-error-banner" role="alert">
        ⚠️ Changes couldn't be saved — this account doesn't own the session. Return to the dashboard and sign in as the session owner.
    </div>

    <div class="courts-grid" :class="'courts-' + courts.length">
        <div v-for="court in courts" :key="court.id" class="court-card" :class="'court-card--' + (session.sport || 'badminton')">
            <div class="court-card__head">
                <span class="court-card__number">COURT {{ court.court_number }}</span>
                <span class="court-card__status" :class="'court-card__status--' + (court.match ? 'playing' : 'available')">{{ court.match ? 'PLAYING' : 'AVAILABLE' }}</span>
            </div>
            <div v-if="!court.match" class="court-card__body court-card__body--empty"
                :class="{ 'court-card__body--drop': dragOverCourtId === court.id }"
                @dragover.prevent="dragOverCourtId = court.id"
                @dragleave="dragOverCourtId === court.id && (dragOverCourtId = null)"
                @drop="dropPlayerOnCourt($event, court)">
                <div class="court-card__lines"></div>
                <div v-if="pendingCourtPlayers[court.id] && pendingCourtPlayers[court.id].length" class="court-card__pending">
                    <div class="court-card__pending-grid">
                        <div v-for="sp in pendingCourtPlayers[court.id]" :key="sp.player_id" class="court-card__player-box court-card__player-box--pending" @click="removePendingPlayer(court.id, sp.player_id)" title="Tap to return to NEXT UP">
                            <span class="court-card__player">{{ formatName(sp.player.name) }}</span>
                        </div>
                    </div>
                    <span class="court-empty-text">{{ pendingCourtPlayers[court.id].length >= 4 ? 'Ready to start' : 'Waiting for ' + (4 - pendingCourtPlayers[court.id].length) + ' more…' }}</span>
                    <button v-if="pendingCourtPlayers[court.id].length >= 4" class="fill-courts-btn fill-courts-btn--start" :class="{ 'is-busy': uiPending.court[court.id] }" type="button" :disabled="uiPending.court[court.id]" @click="startCourtMatch(court.id)">START MATCH</button>
                </div>
                <template v-else>
                    <span class="court-empty-text">Waiting for players — drag from NEXT UP</span>
                    <button class="fill-courts-btn" type="button" @click="openManualAssignment(court.id)">ASSIGN</button>
                </template>
            </div>
            <div v-else class="court-card__body">
                <div class="court-card__lines"></div>
                <div class="court-card__court">
                    <div class="court-card__side court-card__side--team-1" :class="{ 'court-card__side--locked': submitting[court.match.id + '_1'] || submitting[court.match.id + '_2'] }" @click="openScorePicker(court, 1, $event)" title="Tap to record a win for this team">
                        <div class="court-card__player-box court-card__player-box--team-1" :class="{ 'court-card__player-box--streak': court.match.t1[0].streak >= 3 }">
                            <span class="court-card__player">{{ formatName(court.match.t1[0].name) }}</span>
                            <span v-if="court.match.t1[0].wins || court.match.t1[0].streak > 3" class="court-card__player-meta"><i v-if="court.match.t1[0].wins" class="court-card__win">{{ court.match.t1[0].wins }}W</i><i v-if="court.match.t1[0].streak > 3" class="court-card__streak">{{ court.match.t1[0].streak }}</i></span>
                        </div>
                        <div class="court-card__player-box court-card__player-box--team-1" :class="{ 'court-card__player-box--streak': court.match.t1[1].streak >= 3 }">
                            <span class="court-card__player">{{ formatName(court.match.t1[1].name) }}</span>
                            <span v-if="court.match.t1[1].wins || court.match.t1[1].streak > 3" class="court-card__player-meta"><i v-if="court.match.t1[1].wins" class="court-card__win">{{ court.match.t1[1].wins }}W</i><i v-if="court.match.t1[1].streak > 3" class="court-card__streak">{{ court.match.t1[1].streak }}</i></span>
                        </div>
                    </div>
                    <div class="court-card__divider"><span>VS</span></div>
                    <div class="court-card__side court-card__side--team-2" :class="{ 'court-card__side--locked': submitting[court.match.id + '_1'] || submitting[court.match.id + '_2'] }" @click="openScorePicker(court, 2, $event)" title="Tap to record a win for this team">
                        <div class="court-card__player-box court-card__player-box--team-2" :class="{ 'court-card__player-box--streak': court.match.t2[0].streak >= 3 }">
                            <span class="court-card__player">{{ formatName(court.match.t2[0].name) }}</span>
                            <span v-if="court.match.t2[0].wins || court.match.t2[0].streak > 3" class="court-card__player-meta"><i v-if="court.match.t2[0].wins" class="court-card__win">{{ court.match.t2[0].wins }}W</i><i v-if="court.match.t2[0].streak > 3" class="court-card__streak">{{ court.match.t2[0].streak }}</i></span>
                        </div>
                        <div class="court-card__player-box court-card__player-box--team-2" :class="{ 'court-card__player-box--streak': court.match.t2[1].streak >= 3 }">
                            <span class="court-card__player">{{ formatName(court.match.t2[1].name) }}</span>
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
            <span class="waiting-list__mode" :class="'waiting-list__mode--' + matchmakingMode">{{ modeLabel }}</span>
        </div>
        <div class="waiting-list__cards">
            <TransitionGroup name="queue" tag="div" class="waiting-list__row">
                <div v-for="sp in queuePlayers" :key="sp.player_id" class="player-card" :class="{ 'player-card--paused': sp.status === 'PAUSED', 'player-card--next': nextFourIds.includes(sp.player_id) }" :draggable="sp.status === 'WAITING'" @dragstart="dragPlayerToCourtStart(sp, $event)" @dragend="dragPlayerToCourtEnd">
                    <div class="player-card__col">
                        <span class="player-card__name"><span class="rank-icon" v-html="rankIcon(sp.player.rating)"></span>{{ formatName(sp.player.name) }}</span>
                        <span class="player-card__rating"><span class="rating-value">{{ Math.round(sp.player.rating) }}</span>-{{ sp.wins }}-{{ sitOuts(sp) }}</span>
                    </div>
                    <div class="player-card__actions">
                        <button class="player-card__pause" :class="{ 'is-busy': uiPending.player[sp.id] }" :disabled="uiPending.player[sp.id]" @click="sp.status === 'PAUSED' ? resumePlayer(sp.id) : pausePlayer(sp.id)" :title="sp.status === 'PAUSED' ? 'Resume' : 'Pause — take out of rotation'">{{ sp.status === 'PAUSED' ? '▶' : '⏸' }}</button>
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
        <summary class="match-history__summary">MATCH HISTORY</summary>
        <div class="match-history__search-wrap">
            <input v-model="historySearch" class="match-history__search" type="search" placeholder="Search players..." @click.stop>
        </div>
        <div v-if="filteredHistory.length" class="match-history__list">
            <div v-for="row in filteredHistory" :key="row.id" class="match-history__item">
                <strong :class="{ 'match-history__winner': row.winner }">{{ row.pair }}</strong>
            </div>
        </div>
        <p v-else class="match-history__empty">{{ history.length ? 'No matching players.' : 'No completed matches yet.' }}</p>
    </details>

    <footer class="session-controls">
        <button v-if="session.status === 'PAUSED'" class="btn btn--primary" :class="{ 'is-busy': sessionActionPending === 'resume' }" :disabled="sessionActionPending === 'resume'" @click="resumeSession">▶ RESUME</button>
        <button v-if="session.status === 'FINISHED'" class="btn btn--primary" :class="{ 'is-busy': sessionActionPending === 'newSession' }" :disabled="sessionActionPending === 'newSession'" @click="startNewSession">▶ START NEW SESSION</button>
    </footer>

    <div v-if="manualAssignment.show" class="modal-overlay" @click.self="closeManualAssignment">
        <div class="modal modal--wide modal--manual-assign">
            <div class="modal__head">
                <h3>Assign Court {{ manualAssignment.court.court_number }}</h3>
                <button class="modal__close" @click="closeManualAssignment">✕</button>
            </div>
            <p class="add-section__label">Select four waiting players, then drag between teams to swap.</p>
            <div class="existing-list manual-assign__list">
                <button v-for="sp in waitingPlayers" :key="sp.id" type="button" class="existing-item manual-assignment__player" :class="{ 'existing-item--selected': manualAssignment.playerIds.includes(sp.player_id) }" @click="toggleManualPlayer(sp.player_id)">
                    <span class="existing-item__name"><span class="rank-icon" v-html="rankIcon(sp.player.rating)"></span>{{ formatName(sp.player.name) }}</span>
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
                        <span class="manual-team__name">{{ formatName(sp.player.name) }}</span>
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
        <div class="modal modal--wide">
            <div class="modal__head">
                <h3>Players</h3>
                <button class="modal__close" @click="showPlayers = false">✕</button>
            </div>

            <!-- Add section -->
            <div class="add-section">
                <div class="add-section__new">
                    <input ref="playerNameInput" v-model="newPlayerName" placeholder="Player name" class="modal__input" @keyup.enter="addPlayers" @focus="showSuggestionsNow" @blur="hideSuggestionsLater">
                    <button class="btn btn--primary" :class="{ 'is-busy': uiPending.add }" @click="addPlayers" :disabled="!newPlayerName.trim() || uiPending.add">Add</button>
                </div>

                <!-- Autocomplete suggestions: top 10 on focus, matches while typing -->
                <div v-if="showSuggestions && playerSuggestions.length" class="add-section__existing">
                    <p class="add-section__label">{{ newPlayerName.trim() ? 'Suggestions:' : 'Top players:' }}</p>
                    <div class="existing-list">
                        <div v-for="p in playerSuggestions" :key="p.id" class="existing-item" @mousedown.prevent @click="addExistingPlayer(p.id)">
                            <span class="existing-item__name"><span class="rank-icon" v-html="rankIcon(p.rating)"></span>{{ formatName(p.name) }}</span>
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
                        <span class="existing-item__name"><span class="rank-icon" v-html="rankIcon(p.rating)"></span>{{ formatName(p.name) }}</span>
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
    <div v-if="confirmRemove.show" class="modal-overlay" @click.self="confirmRemove.show = false">
        <div class="modal modal--confirm">
            <div class="confirm-icon">✕</div>
            <h3>Remove {{ confirmRemove.name }}?</h3>
            <p v-if="confirmRemove.isPlaying" class="confirm-note">This player is currently on court. They'll finish the current game, then be removed from the session.</p>
            <p v-else class="confirm-note">This player will be removed from the session and won't be allocated to any more courts.</p>
            <div class="modal__actions">
                <button class="btn btn--secondary" @click="confirmRemove.show = false">Cancel</button>
                <button class="btn btn--danger" @click="confirmLeave">Remove</button>
            </div>
        </div>
    </div>

    <!-- Delete permanently dialog -->
    <div v-if="confirmDelete.show" class="modal-overlay" @click.self="confirmDelete.show = false">
        <div class="modal modal--confirm">
            <div class="confirm-icon confirm-icon--delete">🗑</div>
            <h3>Delete {{ confirmDelete.name }}?</h3>
            <p class="confirm-note">This removes the player from the entire system — they won't appear in any session or future event. This cannot be undone.</p>
            <div class="modal__actions">
                <button class="btn btn--secondary" @click="confirmDelete.show = false">Cancel</button>
                <button class="btn btn--danger" @click="deletePlayer">Delete Permanently</button>
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

    <!-- Score picker — roller deck shown after a winner is tapped -->
    <div v-if="scorePicker.show" class="score-picker" @click.self="closeScorePicker">
        <div class="score-picker__panel" role="dialog" aria-label="Enter match score">
            <div class="score-picker__head">
                <span class="score-picker__court">COURT {{ scorePicker.courtNumber }} — FINAL SCORE</span>
                <button class="score-picker__close" type="button" @click="closeScorePicker" aria-label="Cancel">✕</button>
            </div>
            <div class="score-picker__teams">
                <span class="score-picker__team score-picker__team--1" :class="{ 'score-picker__team--winner': scorePicker.team === 1 }">{{ scorePicker.t1Names }}</span>
                <span class="score-picker__team score-picker__team--2" :class="{ 'score-picker__team--winner': scorePicker.team === 2 }">{{ scorePicker.t2Names }}</span>
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
                <button class="score-picker__btn score-picker__btn--confirm" :class="'score-picker__btn--team-' + scorePicker.team" type="button" :disabled="!scoreValid" @click="confirmScore">CONFIRM</button>
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
        const uiPending = reactive({ mode: false, fill: false, add: false, player: {}, court: {} });
        const players = ref([]);
        const tournament = ref(null);
        const history = ref([]);
        const historySearch = ref('');
        const filteredHistory = computed(() => {
            const query = historySearch.value.trim().toLowerCase();
            return history.value.flatMap(match => [1, 2]
                .map(team => ({
                    id: match.id + '-' + team,
                    pair: historyTeam(match, team),
                    winner: match.winning_team === team,
                }))
                .filter(row => !query || row.pair.toLowerCase().includes(query))
            );
        });
        const pendingAddedPlayers = ref([]);
        const waitingPlayers = computed(() => players.value.filter(p => p.status === 'WAITING'));
        const activePlayers = computed(() => players.value.filter(p => p.status !== 'LEFT'));
        const emptyCourts = computed(() => courts.value.filter(court => !court.match));
        const canFillCourts = computed(() => session.status === 'ACTIVE' && session.type !== 'tournament' && emptyCourts.value.length > 0);
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
        // Players manually dragged from NEXT UP onto each empty court, keyed by
        // court id. When a court reaches four, START MATCH submits them (the
        // server auto-balances the teams).
        const pendingCourtPlayers = reactive({});

        // Player ids currently shown on a court preview — hidden from NEXT UP
        // so a player never appears in two places at once.
        const previewedPlayerIds = computed(() => {
            const ids = new Set();
            Object.values(pendingCourtPlayers).forEach(list => {
                list.forEach(sp => ids.add(sp.player_id));
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
        const pendingResultMatchIds = new Set();

        // Score picker — the wheels are index-addressed, so value === index.
        const MATCH_POINTS = 21;
        const CLOSE_MARGIN = 3;
        const SCORE_ITEM_HEIGHT = 56;
        const scoreValues = Object.freeze(Array.from({ length: 41 }, (_, index) => index));
        const scorePicker = reactive({ show: false, matchId: null, team: 1, courtNumber: null, t1: 21, t2: 15, t1Names: '', t2Names: '' });
        const wheelT1 = ref(null);
        const wheelT2 = ref(null);
        const wheelFrames = { t1: 0, t2: 0 };
        let scorePickerSpot = null;
        const celebration = ref(null);
        const celebrationParticles = Array.from({ length: 28 }, (_, index) => index);
        let celebrationTimer = null;
        const connectionState = ref('connecting');
        const authError = ref(false);
        const elapsed = ref('');
        const showPlayers = ref(false);
        const showSuggestions = ref(false);
        const newPlayerName = ref('');
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
        const confirmRemove = ref({ show: false, spId: null, name: '', isPlaying: false });
        const confirmDelete = ref({ show: false, playerId: null, name: '' });
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
            try {
                const res = await fetch(BASE_URL + '/api/sessions/' + SESSION_ID + '/tournament/teams/swap', {
                    method: 'POST',
                    credentials: 'include',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                    body: JSON.stringify({ player_id_a: playerIdA, player_id_b: playerIdB }),
                });
                const json = await res.json();
                if (!res.ok) throw new Error(json.message || 'Failed to swap players');
                teamsList.value = json.data;
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
            try {
                const res = await fetch(BASE_URL + '/api/sessions/' + SESSION_ID + '/tournament/teams/regenerate', {
                    method: 'POST',
                    credentials: 'include',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                });
                const json = await res.json();
                if (!res.ok) throw new Error(json.message || 'Failed to shuffle teams');
                teamsList.value = json.data;
            } catch (ex) {
                teamsError.value = ex.message;
            } finally {
                teamsLoading.value = false;
            }
        }
        let pollTimer = null;
        let pollSince = null;
        let pollDelay = 3000;
        let pollingStopped = false;
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
                if (d.history) history.value = d.history;

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
                        const build = (mp) => ({ name: mp.player.name, rating: mp.player.rating, wins: (stats[mp.player_id] || {}).wins || 0, streak: mp.player.consecutive_wins || 0 });
                        md = { id: match.id, t1: [build(t1[0]), build(t1[1])], t2: [build(t2[0]), build(t2[1])] };
                    }
                    return { ...c, match: md };
                });
                const serverPlayers = d.session_players || [];
                players.value = serverPlayers;
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
                const query = pollSince
                    ? '?since=' + encodeURIComponent(pollSince)
                    : '?snapshot=1';
                const response = await fetch(BASE_URL + '/api/sessions/' + SESSION_ID + '/events' + query, {
                    credentials: 'include',
                    headers: { 'Accept': 'application/json' },
                });
                if (!response.ok) throw new Error('Event poll failed');

                const payload = (await response.json()).data;
                if (payload.snapshot) {
                    applySessionData(payload.snapshot);
                } else if ((payload.events || []).length > 0) {
                    await fetchSession();
                }
                pollSince = payload.server_time;
                pollDelay = 3000;
                connectionState.value = 'connected';
            } catch {
                connectionState.value = 'offline';
                pollDelay = Math.min(pollDelay * 2, 15000);
            } finally {
                if (!pollingStopped) pollTimer = setTimeout(pollEvents, pollDelay);
            }
        }

        async function postApi(url, body) { const res = await fetch(BASE_URL + url, { method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':CSRF_TOKEN}, credentials:'include', body: body ? JSON.stringify(body) : undefined }); return { ok: res.ok, data: await res.json() }; }
        const scoreValid = computed(() => {
            const winner = scorePicker.team === 1 ? scorePicker.t1 : scorePicker.t2;
            const loser = scorePicker.team === 1 ? scorePicker.t2 : scorePicker.t1;
            if (winner < MATCH_POINTS || winner <= loser) return false;
            return winner === MATCH_POINTS ? (winner - loser) >= 2 : (winner - loser) === 2;
        });
        const scoreHint = computed(() => {
            const winner = scorePicker.team === 1 ? scorePicker.t1 : scorePicker.t2;
            const loser = scorePicker.team === 1 ? scorePicker.t2 : scorePicker.t1;
            if (winner <= loser) return 'The winning team needs the higher score';
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
            scorePickerSpot = null;
        }

        function confirmScore() {
            if (!scoreValid.value || !scorePicker.matchId) return;
            const payload = { matchId: scorePicker.matchId, team: scorePicker.team, scores: { t1: scorePicker.t1, t2: scorePicker.t2 }, spot: scorePickerSpot };
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

        async function recordResult(matchId, team, scores = null, spot = null) {
            const submissionKey = matchId + '_' + team;
            if (pendingResultMatchIds.has(matchId)) return;
            submitting[submissionKey] = true;
            pendingResultMatchIds.add(matchId);

            const court = courts.value.find(item => item.match && item.match.id === matchId);
            const previousMatch = court ? court.match : null;
            if (court) {
                court.match = null;
                celebration.value = { courtId: court.id, x: spot ? spot.x : (team === 1 ? 25 : 75), y: spot ? spot.y : 50 };
                clearTimeout(celebrationTimer);
                celebrationTimer = setTimeout(() => { celebration.value = null; }, 850);
            }

            postApi('/api/matches/' + matchId + '/result', scores
                ? { winning_team: team, team_1_score: scores.t1, team_2_score: scores.t2 }
                : { winning_team: team })
                .then(result => {
                    if (!result.ok) {
                        pendingResultMatchIds.delete(matchId);
                        if (court && !court.match) court.match = previousMatch;
                        celebration.value = null;
                        return;
                    }

                    const completedMatch = result.data?.data?.match;
                    if (completedMatch && !history.value.some(match => match.id === completedMatch.id)) {
                        history.value = [completedMatch, ...history.value];
                    }
                    fetchSession().catch(() => { connectionState.value = 'offline'; });
                })
                .catch(() => {
                    pendingResultMatchIds.delete(matchId);
                    if (court && !court.match) court.match = previousMatch;
                    celebration.value = null;
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
                const result = await postApi('/api/sessions/' + SESSION_ID + '/matchmaking-mode', { mode: next });
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
                const result = await postApi('/api/sessions/' + SESSION_ID + '/fill');
                if (result.ok) await fetchSession();
            } finally {
                uiPending.fill = false;
            }
        }
        async function adjustCourts(action) {
            if (updatingCourts.value) return;

            updatingCourts.value = true;
            try {
                const response = await fetch(BASE_URL + '/api/sessions/' + SESSION_ID + '/courts', {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                    credentials: 'include',
                    body: JSON.stringify({ action }),
                });
                const payload = await response.json();
                if (response.ok) applySessionData(payload.data);
            } finally {
                updatingCourts.value = false;
            }
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
            if (sp.status !== 'WAITING') return;
            draggedQueuePlayerId.value = sp.player_id;
            if (event && event.dataTransfer) {
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', String(sp.player_id));
            }
        }
        function dragPlayerToCourtEnd() {
            draggedQueuePlayerId.value = null;
            dragOverCourtId.value = null;
        }
        function dropPlayerOnCourt(event, court) {
            if (event) event.preventDefault();
            const playerId = draggedQueuePlayerId.value;
            dragOverCourtId.value = null;
            draggedQueuePlayerId.value = null;
            if (!playerId || court.match) return;
            const sp = waitingPlayers.value.find(p => p.player_id === playerId);
            if (!sp) return;
            const alreadyPending = Object.values(pendingCourtPlayers).some(list => list.some(p => p.player_id === playerId));
            if (alreadyPending) return;
            const list = pendingCourtPlayers[court.id] || [];
            if (list.length >= 4) return;
            pendingCourtPlayers[court.id] = [...list, sp];
        }
        function removePendingPlayer(courtId, playerId) {
            const list = pendingCourtPlayers[courtId] || [];
            const next = list.filter(sp => sp.player_id !== playerId);
            if (next.length === 0) delete pendingCourtPlayers[courtId];
            else pendingCourtPlayers[courtId] = next;
        }
        async function startCourtMatch(courtId) {
            const list = pendingCourtPlayers[courtId] || [];
            if (list.length !== 4 || uiPending.court[courtId]) return;
            uiPending.court[courtId] = true;
            try {
                const result = await postApi('/api/sessions/' + SESSION_ID + '/manual-assignment', {
                    court_id: courtId,
                    player_ids: list.map(sp => sp.player_id),
                });
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
            const selected = ids.map(id => waitingPlayers.value.find(sp => sp.player_id === id)).filter(Boolean);
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
            try {
                const result = await postApi('/api/sessions/' + SESSION_ID + '/manual-assignment', {
                    court_id: manualAssignment.court.id,
                    player_ids: manualAssignment.playerIds,
                    team_1_ids: manualAssignment.playerIds.slice(0, 2),
                    team_2_ids: manualAssignment.playerIds.slice(2, 4),
                });
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
            try { await postApi('/api/sessions/' + SESSION_ID + '/start'); } finally { sessionActionPending.value = null; }
        }
        async function pauseSession() { postApi('/api/sessions/' + SESSION_ID + '/pause'); }
        async function resumeSession() {
            if (sessionActionPending.value) return;
            sessionActionPending.value = 'resume';
            try { await postApi('/api/sessions/' + SESSION_ID + '/resume'); } finally { sessionActionPending.value = null; }
        }
        async function finishSession() {
            if (sessionActionPending.value) return;
            sessionActionPending.value = 'finish';
            try { await postApi('/api/sessions/' + SESSION_ID + '/finish'); } finally { sessionActionPending.value = null; }
        }
        async function addPlayers() {
            const name = newPlayerName.value.trim();
            if (!name || uiPending.add) return;
            uiPending.add = true;
            newPlayerName.value = '';
            try {
                await postApi('/api/sessions/' + SESSION_ID + '/players', { name });
                await fetchSession();
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
                const result = await postApi('/api/sessions/' + SESSION_ID + '/players', { player_ids: [id] });
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
        async function updatePlayerStatus(spId, status, action) {
            if (typeof spId !== 'number' || uiPending.player[spId]) return;
            const player = players.value.find(item => item.id === spId);
            if (!player) return;
            const previous = player.status;
            player.status = status;
            uiPending.player[spId] = true;
            try {
                const result = await postApi('/api/session-players/' + spId + '/' + action);
                if (!result.ok) player.status = previous;
            } catch {
                player.status = previous;
            } finally {
                delete uiPending.player[spId];
            }
        }

        // Styled remove confirmation
        function openRemove(sp) {
            confirmRemove.value = { show: true, spId: sp.id, name: sp.player.name, isPlaying: sp.status === 'PLAYING' };
        }
        function confirmLeave() {
            if (!confirmRemove.value.spId) return;
            const spId = confirmRemove.value.spId;
            confirmRemove.value = { show: false, spId: null, name: '', isPlaying: false };
            if (typeof spId === 'number') {
                postApi('/api/session-players/' + spId + '/leave');
            }
        }

        // Permanent delete from system
        function openDelete(sp) {
            confirmDelete.value = { show: true, playerId: sp.player_id, name: sp.player.name };
        }
        function openDeleteById(playerId, playerName) {
            confirmDelete.value = { show: true, playerId: playerId, name: playerName };
        }
        function deletePlayer() {
            if (!confirmDelete.value.playerId) return;
            const playerId = confirmDelete.value.playerId;
            confirmDelete.value = { show: false, playerId: null, name: '' };
            fetch(BASE_URL + '/api/players/' + playerId, { method: 'DELETE', credentials: 'include', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN } });
        }

        onMounted(() => {
            loadKnownPlayers();
            pollEvents();
        });
        onUnmounted(() => {
            pollingStopped = true;
            if (pollTimer) clearTimeout(pollTimer);
            if (celebrationTimer) clearTimeout(celebrationTimer);
        });

        // Lock body scroll when any modal is open
        const modalOpen = computed(() => showPlayers.value || confirmRemove.value.show || confirmDelete.value.show || confirmNewSession.value.show || manualAssignment.show || scorePicker.show);
        watch(modalOpen, (val) => { document.body.style.overflow = val ? 'hidden' : ''; });

        function formatName(name) {
            const parts = name.trim().split(/\s+/);
            if (parts.length < 2) return name;
            const last = parts[parts.length - 1];
            if (/^[A-Z]\.?$/i.test(last)) return name;
            parts[parts.length - 1] = last.charAt(0).toUpperCase() + '.';
            return parts.join(' ');
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

        return { session, sessionName, matchmakingMode, modeLabel, toggleMode, fillCourts, courts, updatingCourts, sessionActionPending, uiPending, adjustCourts, players, tournament, history, historySearch, filteredHistory, waitingPlayers, canFillCourts, queuePlayers, nextFourIds, pendingCourtPlayers, activePlayers, submitting, celebration, celebrationParticles, connectionState, authError, elapsed, showPlayers, showSuggestions, showSuggestionsNow, hideSuggestionsLater, newPlayerName, availablePlayers, playerSuggestions, isInSession, confirmRemove, confirmDelete, confirmNewSession, dragOverCourtId, manualAssignment, manualTeams, manualDraggedId, manualDragOverId, manualTapId, openManualAssignment, dropPlayerOnCourt, removePendingPlayer, startCourtMatch, dragPlayerToCourtStart, dragPlayerToCourtEnd, closeManualAssignment, toggleManualPlayer, balanceManualTeam, swapManualPlayers, manualDragStart, manualDragEnd, manualDrop, manualTap, submitManualAssignment, courtAccent, recordResult, scorePicker, scoreValues, scoreValid, scoreHint, wheelT1, wheelT2, onWheelScroll, openScorePicker, closeScorePicker, confirmScore, skipScore, startSession, startNewSession, doStartNewSession, pauseSession, resumeSession, finishSession, openPlayers, addPlayers, addExistingPlayer, pausePlayer, resumePlayer, openRemove, confirmLeave, openDelete, openDeleteById, deletePlayer, formatName, ratingBadge, rankIcon, sitOuts, historyTeam, Math, showTeams, teamsList, teamsError, teamsLoading, selectedPlayerId, draggedPlayerId, dragOverPlayerId, openTeams, closeTeams, selectPlayerForSwap, onPlayerDragStart, onPlayerDragEnd, onPlayerDrop, regenerateTeams };
    }
}).mount('#courtly-app');
</script>
</body>
</html>
