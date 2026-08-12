<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Courtly — <?= htmlspecialchars($sessionName ?? 'Session') ?></title>
    <link rel="icon" type="image/png" href="<?= $base ?? '/courtly' ?>/assets/favicon.png?v=2">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;700;800&family=Space+Grotesk:wght@700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
    <link rel="stylesheet" href="<?= $base ?? '/courtly' ?>/css/courtly.css?v=6">
</head>
<body>
<div id="courtly-app">
    <header class="session-header">
        <div class="session-header__left">
            <a href="<?= $base ?? '/courtly' ?>/" class="back-btn" title="Back to dashboard">←</a>
            <a href="<?= $base ?? '/courtly' ?>/" class="session-header__logo" title="Back to home">
                <img src="<?= $base ?? '/courtly' ?>/assets/courtly_light.png" alt="Courtly" class="session-header__logo-img">
            </a>
            <h1 class="session-header__name">{{ sessionName }}</h1>
        </div>
        <div class="session-header__stats">
            <span>👥 {{ players.length }} Players</span>
            <span>🏟 {{ courts.length }} Courts</span>
            <span v-if="elapsed" class="session-header__timer">⏱ {{ elapsed }}</span>
            <span class="session-header__badge" :class="'session-header__badge--' + session.status.toLowerCase()">{{ session.status }}</span>
            <span v-if="connectionState !== 'connected'" class="connection-dot" :class="'connection-dot--' + connectionState" :title="connectionState === 'connecting' ? 'Connecting to server…' : 'Server unreachable — data may be stale'"></span>
            <div class="theme-toggle">
                <button :class="{ active: theme === 'light' }" @click="setTheme('light')" title="Light">☀</button>
                <button :class="{ active: theme === 'dark' }" @click="setTheme('dark')" title="Dark">☾</button>
                <button :class="{ active: theme === 'system' }" @click="setTheme('system')" title="System">◐</button>
            </div>
        </div>
    </header>

    <div class="courts-grid" :class="'courts-' + courts.length">
        <div v-for="court in courts" :key="court.id" class="court-card">
            <div class="court-card__head">
                <span class="court-card__number">COURT {{ court.court_number }}</span>
                <span class="court-card__status" :class="'court-card__status--' + (court.match ? 'playing' : 'available')">{{ court.match ? 'PLAYING' : 'AVAILABLE' }}</span>
            </div>
            <div v-if="!court.match" class="court-card__body court-card__body--empty">
                <div class="court-card__lines"></div>
                <span class="court-empty-text">Waiting for players</span>
            </div>
            <div v-else class="court-card__body">
                <div class="court-card__lines"></div>
                <div class="court-card__court">
                    <div class="court-card__side court-card__side--team-1">
                        <div class="court-card__player-box court-card__player-box--team-1">
                            <span class="court-card__player">{{ formatName(court.match.t1[0].name) }}<i v-if="court.match.t1[0].wins" class="court-card__win">{{ court.match.t1[0].wins }}W</i></span>
                        </div>
                        <div class="court-card__player-box court-card__player-box--team-1">
                            <span class="court-card__player">{{ formatName(court.match.t1[1].name) }}<i v-if="court.match.t1[1].wins" class="court-card__win">{{ court.match.t1[1].wins }}W</i></span>
                        </div>
                        <button class="btn-win btn-win--team-1" :class="{ 'is-submitting': submitting[court.match.id + '_1'] }" :disabled="submitting[court.match.id + '_2']" @click="recordResult(court.match.id, 1)">WIN</button>
                    </div>
                    <div class="court-card__divider"><span>VS</span></div>
                    <div class="court-card__side court-card__side--team-2">
                        <div class="court-card__player-box court-card__player-box--team-2">
                            <span class="court-card__player">{{ formatName(court.match.t2[0].name) }}<i v-if="court.match.t2[0].wins" class="court-card__win">{{ court.match.t2[0].wins }}W</i></span>
                        </div>
                        <div class="court-card__player-box court-card__player-box--team-2">
                            <span class="court-card__player">{{ formatName(court.match.t2[1].name) }}<i v-if="court.match.t2[1].wins" class="court-card__win">{{ court.match.t2[1].wins }}W</i></span>
                        </div>
                        <button class="btn-win btn-win--team-2" :class="{ 'is-submitting': submitting[court.match.id + '_2'] }" :disabled="submitting[court.match.id + '_1']" @click="recordResult(court.match.id, 2)">WIN</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="waiting-list">
        <h3 class="waiting-list__title">NEXT UP</h3>
        <div class="waiting-list__cards">
            <TransitionGroup name="queue" tag="div" class="waiting-list__row">
                <div v-for="sp in queuePlayers" :key="sp.id" class="player-card" :class="{ 'player-card--paused': sp.status === 'PAUSED' }">
                    <div class="player-card__row">
                        <span class="player-card__name">{{ formatName(sp.player.name) }}</span>
                        <button class="player-card__pause" @click="sp.status === 'PAUSED' ? resumePlayer(sp.id) : pausePlayer(sp.id)" :title="sp.status === 'PAUSED' ? 'Resume' : 'Pause — take out of rotation'">{{ sp.status === 'PAUSED' ? '▶' : '⏸' }}</button>
                    </div>
                    <span class="player-card__rating">{{ Math.round(sp.player.rating) }}</span>
                </div>
            </TransitionGroup>
            <p v-if="queuePlayers.length === 0" class="waiting-list__empty">No players waiting</p>
        </div>
    </div>

    <footer class="session-controls">
        <button v-if="session.status === 'UPCOMING'" class="btn btn--primary" @click="startSession">▶ START SESSION</button>
        <button v-if="session.status === 'ACTIVE'" class="btn btn--warning" @click="pauseSession">⏸ PAUSE</button>
        <button v-if="session.status === 'PAUSED'" class="btn btn--primary" @click="resumeSession">▶ RESUME</button>
        <button v-if="session.status === 'ACTIVE' || session.status === 'PAUSED'" class="btn btn--danger" @click="finishSession">⏹ FINISH</button>
        <button v-if="session.status === 'FINISHED'" class="btn btn--primary" @click="startNewSession">▶ START NEW SESSION</button>
        <button class="btn btn--secondary" @click="openPlayers">+ PLAYERS</button>
        <button class="btn btn--secondary" @click="openPlayers">👥 MANAGE</button>
    </footer>

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
                    <input v-model="newPlayerName" placeholder="New player name (or guest)" class="modal__input" @keyup.enter="addPlayers">
                    <button class="btn btn--primary" @click="addPlayers" :disabled="!newPlayerName.trim()">New</button>
                </div>

                <div v-if="availablePlayers.length" class="add-section__existing">
                    <p class="add-section__label">Tap to add:</p>
                    <div class="existing-list">
                        <div v-for="p in availablePlayers" :key="p.id" class="existing-item" @click="addExistingPlayer(p.id)">
                            <span class="existing-item__name">{{ formatName(p.name) }}</span>
                            <span class="existing-item__rating">{{ Math.round(p.rating) }}</span>
                            <button class="existing-item__del" @click.stop="openDeleteById(p.id, p.name)" title="Delete permanently">🗑</button>
                        </div>
                    </div>
                </div>
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
</div>

<script>
const SESSION_ID = <?= (int) ($sessionId ?? 0) ?>;
const START_STATUS = "<?= htmlspecialchars($sessionStatus ?? 'UNKNOWN', ENT_QUOTES) ?>";
const START_NAME = "<?= htmlspecialchars($sessionName ?? 'Session', ENT_QUOTES) ?>";
const BASE_URL = "<?= htmlspecialchars(($base ?? '') . '', ENT_QUOTES) ?>";

const { createApp, ref, reactive, computed, onMounted, onUnmounted, watch, TransitionGroup } = Vue;

createApp({
    setup() {
        const session = reactive({ status: START_STATUS });
        const sessionName = ref(START_NAME);
        const courts = ref([]);
        const players = ref([]);
        const waitingPlayers = computed(() => players.value.filter(p => p.status === 'WAITING'));
        const queuePlayers = computed(() => {
            const order = { WAITING: 0, PAUSED: 1 };
            return players.value
                .filter(p => p.status === 'WAITING' || p.status === 'PAUSED')
                .sort((a, b) => (order[a.status] ?? 9) - (order[b.status] ?? 9));
        });
        const activePlayers = computed(() => players.value.filter(p => p.status !== 'LEFT'));
        const submitting = reactive({});
        const connectionState = ref('connecting');
        const elapsed = ref('');
        const showPlayers = ref(false);
        const newPlayerName = ref('');
        const allKnownPlayers = ref([]);
        const availablePlayers = computed(() =>
            allKnownPlayers.value.filter(p => !activePlayers.value.some(sp => sp.player_id === p.id))
        );
        const confirmRemove = ref({ show: false, spId: null, name: '', isPlaying: false });
        const confirmDelete = ref({ show: false, playerId: null, name: '' });
        const confirmNewSession = ref({ show: false });
        const theme = ref(localStorage.getItem('courtly-theme') || 'system');
        let pollTimer = null;

        function setTheme(t) {
            theme.value = t;
            localStorage.setItem('courtly-theme', t);
            if (t === 'system') document.documentElement.removeAttribute('data-theme');
            else document.documentElement.setAttribute('data-theme', t);
        }
        // Apply on load
        if (theme.value !== 'system') document.documentElement.setAttribute('data-theme', theme.value);

        const COURT_COLORS = { 1:'#3B82F6', 2:'#EF4444', 3:'#F59E0B', 4:'#10B981', 5:'#8B5CF6', 6:'#EC4899', 7:'#06B6D4', 8:'#F97316' };
        function courtAccent(n) { return COURT_COLORS[n] || '#6B7280'; }

        async function loadKnownPlayers() {
            const res = await fetch(BASE_URL + '/api/players', { credentials: 'include', headers: { 'Accept': 'application/json' } });
            const json = await res.json();
            allKnownPlayers.value = json.data || [];
        }

        function openPlayers() {
            newPlayerName.value = '';
            showPlayers.value = true;
        }

        async function fetchSession() {
            // Abort after 8s so a hung server (e.g. DB down) is flagged as unreachable
            const controller = new AbortController();
            const timer = setTimeout(() => controller.abort(), 8000);
            try {
                const res = await fetch(BASE_URL + '/api/sessions/' + SESSION_ID, { credentials: 'include', signal: controller.signal });
                clearTimeout(timer);
                if (!res.ok) { connectionState.value = 'offline'; return; }
                const json = await res.json();
                const d = json.data;
                if (!d) { connectionState.value = 'offline'; return; }
                session.status = d.status;

                // Lookup of per-player session stats (wins/losses) by player id
                const stats = {};
                (d.session_players || []).forEach(sp => { stats[sp.player_id] = { wins: sp.wins, losses: sp.losses }; });

                courts.value = (d.courts || []).map(c => {
                    const match = (d.matches || []).find(m => m.court_id === c.id && m.status === 'PLAYING');
                    let md = null;
                    if (match && match.match_players && match.match_players.length === 4) {
                        const t1 = match.match_players.filter(p => p.team === 1);
                        const t2 = match.match_players.filter(p => p.team === 2);
                        const build = (mp) => ({ name: mp.player.name, rating: mp.player.rating, wins: (stats[mp.player_id] || {}).wins || 0 });
                        md = { id: match.id, t1: [build(t1[0]), build(t1[1])], t2: [build(t2[0]), build(t2[1])] };
                    }
                    return { ...c, match: md };
                });
                players.value = d.session_players || [];
                connectionState.value = 'connected';
            } catch (err) {
                clearTimeout(timer);
                connectionState.value = 'offline';
            }
        }

        async function api(url, body) {
            const res = await fetch(url, { method: body ? 'POST' : 'GET', headers: body ? {'Content-Type':'application/json','Accept':'application/json'} : {'Accept':'application/json'}, credentials: 'include', body: body ? JSON.stringify(body) : undefined });
            return res.json();
        }

        async function postApi(url) { const res = await fetch(url, { method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json'}, credentials:'include' }); return res.json(); }
        async function recordResult(matchId, team) {
            submitting[matchId + '_' + team] = true;

            // Optimistic UI: move losing players to waiting list immediately
            const losingTeam = team === 1 ? 2 : 1;
            const court = courts.value.find(c => c.match && c.match.id === matchId);
            if (court && court.match) {
                const losers = losingTeam === 1 ? court.match.t1 : court.match.t2;
                const winnerNames = (team === 1 ? court.match.t1 : court.match.t2).map(p => p.name);

                // Update player statuses locally
                players.value = players.value.map(sp => {
                    const isLoser = losers.some(l => l.name === sp.player.name);
                    const isWinner = winnerNames.includes(sp.player.name);
                    if (isLoser) return { ...sp, status: 'WAITING', games_played: sp.games_played + 1, wins: sp.wins, losses: sp.losses + 1 };
                    if (isWinner) return { ...sp, status: 'WAITING', games_played: sp.games_played + 1, wins: sp.wins + 1, losses: sp.losses };
                    return sp;
                });

                // Clear the court
                court.match = null;
            }

            // Send to server
            const res = await fetch(BASE_URL + '/api/matches/'+matchId+'/result',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json'},credentials:'include',body:JSON.stringify({winning_team:team})});
            submitting[matchId + '_' + team] = false;

            if (res.ok) {
                const json = await res.json();
                const nextMatches = json?.data?.next_matches || [];

                // Populate courts immediately from the POST response — no extra GET roundtrip
                for (const nm of nextMatches) {
                    const targetCourt = courts.value.find(c => c.id === nm.court_id);
                    if (targetCourt && nm.match_players && nm.match_players.length === 4) {
                        const t1 = nm.match_players.filter(p => p.team === 1);
                        const t2 = nm.match_players.filter(p => p.team === 2);
                        const build = (mp) => ({ name: mp.player.name, rating: mp.player.rating, wins: 0 });
                        targetCourt.match = {
                            id: nm.id,
                            t1: [build(t1[0]), build(t1[1])],
                            t2: [build(t2[0]), build(t2[1])]
                        };
                    }
                }

                // Update player statuses for newly assigned players
                const newPlayingIds = nextMatches.flatMap(nm =>
                    (nm.match_players || []).map(mp => mp.player_id)
                );
                if (newPlayingIds.length > 0) {
                    players.value = players.value.map(sp => {
                        if (newPlayingIds.includes(sp.player_id)) {
                            return { ...sp, status: 'PLAYING' };
                        }
                        return sp;
                    });
                }

                // Background refresh for ratings/stats reconciliation
                fetchSession();
            }
        }
        async function startSession() { await postApi('/api/sessions/' + SESSION_ID + '/start'); fetchSession(); }
        async function startNewSession() {
            confirmNewSession.value = { show: true };
        }
        async function doStartNewSession() {
            confirmNewSession.value = { show: false };
            const courtCount = courts.value.length || 3;
            const res = await fetch(BASE_URL + '/api/sessions', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ name: sessionName.value, number_of_courts: courtCount })
            });
            const json = await res.json();
            if (!res.ok || !json.data || !json.data.id) return;
            window.location.href = BASE_URL + '/sessions/' + json.data.id + '/live';
        }
        async function pauseSession() { await postApi('/api/sessions/' + SESSION_ID + '/pause'); fetchSession(); }
        async function resumeSession() { await postApi('/api/sessions/' + SESSION_ID + '/resume'); fetchSession(); }
        async function finishSession() { await postApi('/api/sessions/' + SESSION_ID + '/finish'); fetchSession(); }
        async function addPlayers() {
            const name = newPlayerName.value.trim();
            if (!name) return;

            // Optimistic: add to waiting list immediately
            players.value.push({
                player_id: 'new',
                player: { id: 'new', name: name, rating: 0 },
                status: 'WAITING',
                games_played: 0, wins: 0, losses: 0
            });
            newPlayerName.value = '';

            const res = await fetch(BASE_URL + '/api/sessions/' + SESSION_ID + '/players', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ name: name })
            });
            await fetchSession();
        }

        async function addExistingPlayer(id) {
            // Optimistic: remove from available list, add to waiting list
            const player = allKnownPlayers.value.find(p => p.id === id);
            allKnownPlayers.value = allKnownPlayers.value.filter(p => p.id !== id);

            if (player) {
                players.value.push({
                    player_id: id,
                    player: { id: id, name: player.name, rating: player.rating },
                    status: 'WAITING',
                    games_played: 0, wins: 0, losses: 0
                });
            }

            await fetch(BASE_URL + '/api/sessions/' + SESSION_ID + '/players', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ player_ids: [id] })
            });
            await fetchSession();
        }

        async function pausePlayer(spId) { await postApi('/api/session-players/' + spId + '/pause'); fetchSession(); }
        async function resumePlayer(spId) { await postApi('/api/session-players/' + spId + '/resume'); fetchSession(); }

        // Styled remove confirmation
        function openRemove(sp) {
            confirmRemove.value = { show: true, spId: sp.id, name: sp.player.name, isPlaying: sp.status === 'PLAYING' };
        }
        async function confirmLeave() {
            if (!confirmRemove.value.spId) return;
            const spId = confirmRemove.value.spId;
            confirmRemove.value = { show: false, spId: null, name: '', isPlaying: false };
            await postApi('/api/session-players/' + spId + '/leave');
            fetchSession();
        }

        // Permanent delete from system
        function openDelete(sp) {
            confirmDelete.value = { show: true, playerId: sp.player_id, name: sp.player.name };
        }
        function openDeleteById(playerId, playerName) {
            confirmDelete.value = { show: true, playerId: playerId, name: playerName };
        }
        async function deletePlayer() {
            if (!confirmDelete.value.playerId) return;
            const playerId = confirmDelete.value.playerId;
            confirmDelete.value = { show: false, playerId: null, name: '' };
            await fetch(BASE_URL + '/api/players/' + playerId, { method: 'DELETE', credentials: 'include', headers: { 'Accept': 'application/json' } });
            allKnownPlayers.value = allKnownPlayers.value.filter(p => p.id !== playerId);
            fetchSession();
        }

        onMounted(() => {
            let backoff = 3000;
            function schedulePoll() {
                pollTimer = setTimeout(async () => {
                    await fetchSession();
                    backoff = connectionState.value === 'connected' ? 3000 : Math.min(backoff * 2, 15000);
                    schedulePoll();
                }, backoff);
            }
            fetchSession();
            loadKnownPlayers();
            schedulePoll();
        });
        onUnmounted(() => { if (pollTimer) clearTimeout(pollTimer); });

        // Lock body scroll when any modal is open
        const modalOpen = computed(() => showPlayers.value || confirmRemove.value.show || confirmDelete.value.show || confirmNewSession.value.show);
        watch(modalOpen, (val) => { document.body.style.overflow = val ? 'hidden' : ''; });

        function formatName(name) {
            const parts = name.trim().split(/\s+/);
            if (parts.length < 2) return name;
            const last = parts[parts.length - 1];
            if (/^[A-Z]\.?$/i.test(last)) return name;
            parts[parts.length - 1] = last.charAt(0).toUpperCase() + '.';
            return parts.join(' ');
        }

        return { session, sessionName, courts, players, waitingPlayers, queuePlayers, activePlayers, submitting, connectionState, elapsed, theme, setTheme, showPlayers, newPlayerName, availablePlayers, confirmRemove, confirmDelete, confirmNewSession, courtAccent, recordResult, startSession, startNewSession, doStartNewSession, pauseSession, resumeSession, finishSession, openPlayers, addPlayers, addExistingPlayer, pausePlayer, resumePlayer, openRemove, confirmLeave, openDelete, openDeleteById, deletePlayer, formatName, Math };
    }
}).mount('#courtly-app');
</script>
</body>
</html>
