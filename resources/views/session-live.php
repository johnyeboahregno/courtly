<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title>Courtly — <?= htmlspecialchars($sessionName ?? 'Session') ?></title>
    <link rel="icon" type="image/png" href="<?= $base ?? '/courtly' ?>/assets/favicon.png?v=2">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;700;800&family=Space+Grotesk:wght@700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
    <link rel="stylesheet" href="<?= $base ?? '/courtly' ?>/css/courtly.css?v=13">
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
            <button class="mode-switch" :class="'mode-switch--' + matchmakingMode" @click="toggleMode" :title="'Matchmaking: ' + modeLabel + ' — click to switch'">{{ matchmakingMode === 'peg' ? 'PEG' : 'SMART' }}</button>
            <span class="session-header__version" title="Courtly version"><?= htmlspecialchars($appVersion ?? 'v2.0.0') ?></span>
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
                <div v-if="pendingCourtPlayers[court.id] && pendingCourtPlayers[court.id].length" class="court-card__pending">
                    <div class="court-card__pending-grid">
                        <div v-for="sp in pendingCourtPlayers[court.id]" :key="sp.player_id" class="court-card__player-box court-card__player-box--pending">
                            <i class="court-card__rating">{{ ratingBadge(sp.player.rating) }}</i>
                            <span class="court-card__player">{{ formatName(sp.player.name) }}</span>
                        </div>
                    </div>
                    <span class="court-empty-text">{{ pendingCourtPlayers[court.id].length >= 4 ? 'Ready to start' : 'Waiting for ' + (4 - pendingCourtPlayers[court.id].length) + ' more…' }}</span>
                </div>
                <span v-else class="court-empty-text">Waiting for players</span>
            </div>
            <div v-else class="court-card__body">
                <div class="court-card__lines"></div>
                <div class="court-card__court">
                    <div class="court-card__side court-card__side--team-1" :class="{ 'court-card__side--locked': submitting[court.match.id + '_1'] || submitting[court.match.id + '_2'] }" @click="recordResult(court.match.id, 1)" title="Tap to record a win for this team">
                        <div class="court-card__player-box court-card__player-box--team-1">
                            <i class="court-card__rating">{{ ratingBadge(court.match.t1[0].rating) }}</i>
                            <span class="court-card__player">{{ formatName(court.match.t1[0].name) }}<i v-if="court.match.t1[0].wins" class="court-card__win">{{ court.match.t1[0].wins }}W</i><i v-if="court.match.t1[0].streak >= 3" class="court-card__streak">🔥{{ court.match.t1[0].streak }}</i></span>
                        </div>
                        <div class="court-card__player-box court-card__player-box--team-1">
                            <i class="court-card__rating">{{ ratingBadge(court.match.t1[1].rating) }}</i>
                            <span class="court-card__player">{{ formatName(court.match.t1[1].name) }}<i v-if="court.match.t1[1].wins" class="court-card__win">{{ court.match.t1[1].wins }}W</i><i v-if="court.match.t1[1].streak >= 3" class="court-card__streak">🔥{{ court.match.t1[1].streak }}</i></span>
                        </div>
                    </div>
                    <div class="court-card__divider"><span>VS</span></div>
                    <div class="court-card__side court-card__side--team-2" :class="{ 'court-card__side--locked': submitting[court.match.id + '_1'] || submitting[court.match.id + '_2'] }" @click="recordResult(court.match.id, 2)" title="Tap to record a win for this team">
                        <div class="court-card__player-box court-card__player-box--team-2">
                            <i class="court-card__rating">{{ ratingBadge(court.match.t2[0].rating) }}</i>
                            <span class="court-card__player">{{ formatName(court.match.t2[0].name) }}<i v-if="court.match.t2[0].wins" class="court-card__win">{{ court.match.t2[0].wins }}W</i><i v-if="court.match.t2[0].streak >= 3" class="court-card__streak">🔥{{ court.match.t2[0].streak }}</i></span>
                        </div>
                        <div class="court-card__player-box court-card__player-box--team-2">
                            <i class="court-card__rating">{{ ratingBadge(court.match.t2[1].rating) }}</i>
                            <span class="court-card__player">{{ formatName(court.match.t2[1].name) }}<i v-if="court.match.t2[1].wins" class="court-card__win">{{ court.match.t2[1].wins }}W</i><i v-if="court.match.t2[1].streak >= 3" class="court-card__streak">🔥{{ court.match.t2[1].streak }}</i></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="waiting-list">
        <div class="waiting-list__head">
            <h3 class="waiting-list__title">NEXT UP</h3>
            <span class="waiting-list__mode" :class="'waiting-list__mode--' + matchmakingMode">{{ modeLabel }}</span>
        </div>
        <div class="waiting-list__cards">
            <TransitionGroup name="queue" tag="div" class="waiting-list__row">
                <div v-for="sp in queuePlayers" :key="sp.player_id" class="player-card" :class="{ 'player-card--paused': sp.status === 'PAUSED', 'player-card--next': nextFourIds.includes(sp.player_id) }">
                    <div class="player-card__col">
                        <span class="player-card__name">{{ formatName(sp.player.name) }}</span>
                        <span class="player-card__rating">{{ Math.round(sp.player.rating) }}-{{ sp.wins }}-{{ sitOuts(sp) }}</span>
                    </div>
                    <div class="player-card__actions">
                        <button class="player-card__pause" @click="sp.status === 'PAUSED' ? resumePlayer(sp.id) : pausePlayer(sp.id)" :title="sp.status === 'PAUSED' ? 'Resume' : 'Pause — take out of rotation'">{{ sp.status === 'PAUSED' ? '▶' : '⏸' }}</button>
                    </div>
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
        <button v-if="syncEnabled" class="btn btn--secondary" @click="syncNow" :disabled="syncing">⟳ SYNC<span v-if="pendingCount"> ({{ pendingCount }})</span></button>
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
                    <input ref="playerNameInput" v-model="newPlayerName" placeholder="Player name" class="modal__input" @keyup.enter="addPlayers" @focus="showSuggestionsNow" @blur="hideSuggestionsLater">
                    <button class="btn btn--primary" @click="addPlayers" :disabled="!newPlayerName.trim()">Add</button>
                </div>

                <!-- Autocomplete suggestions: top 10 on focus, matches while typing -->
                <div v-if="showSuggestions && playerSuggestions.length" class="add-section__existing">
                    <p class="add-section__label">{{ newPlayerName.trim() ? 'Suggestions:' : 'Top players:' }}</p>
                    <div class="existing-list">
                        <div v-for="p in playerSuggestions" :key="p.id" class="existing-item" @mousedown.prevent @click="addExistingPlayer(p.id)">
                            <span class="existing-item__name">{{ formatName(p.name) }}</span>
                            <span class="existing-item__rating">{{ Math.round(p.rating) }}</span>
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
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').getAttribute("content");
const SYNC_CONFIG = <?= json_encode($syncConfig ?? []) ?>;

const { createApp, ref, reactive, computed, onMounted, onUnmounted, watch, nextTick, TransitionGroup } = Vue;

createApp({
    setup() {
        const session = reactive({ status: START_STATUS });
        const sessionName = ref(START_NAME);
        const matchmakingMode = ref('smart');
        const modeLabel = computed(() => matchmakingMode.value === 'peg' ? 'Traditional Pegs' : 'Smart Match Making');
        const courts = ref([]);
        const players = ref([]);
        const waitingPlayers = computed(() => players.value.filter(p => p.status === 'WAITING'));
        const activePlayers = computed(() => players.value.filter(p => p.status !== 'LEFT'));
        // Preview of players heading to each empty court. They render grayed
        // out until the court reaches a full four — the server then forms the
        // real match and the court switches to full colour. Sorted by name so
        // the distribution stays stable across polls.
        // Court "previews" are disabled: players stay in the NEXT UP list until
        // the server actually forms a match (which then shows as PLAYING). Empty
        // courts show "Waiting for players" rather than a misleading preview.
        const pendingCourtPlayers = computed(() => ({}));

        // Player ids currently shown on a court preview — hidden from NEXT UP
        // so a player never appears in two places at once.
        const previewedPlayerIds = computed(() => {
            const ids = new Set();
            Object.values(pendingCourtPlayers.value).forEach(list => {
                list.forEach(sp => ids.add(sp.player_id));
            });
            return ids;
        });

        // NEXT UP — waiting/paused players not already placed on a court preview.
        const queuePlayers = computed(() => {
            const order = { WAITING: 0, PAUSED: 1 };
            const previewed = previewedPlayerIds.value;
            return players.value
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
        const connectionState = ref('connecting');
        const elapsed = ref('');
        const showPlayers = ref(false);
        const showSuggestions = ref(false);
        const newPlayerName = ref('');
        const playerNameInput = ref(null);
        const allKnownPlayers = ref([]);
        let blurTimer = null;
        const availablePlayers = computed(() =>
            allKnownPlayers.value.filter(p => !activePlayers.value.some(sp => sp.player_id === p.id))
        );
        // Autocomplete: exclude players already in the session. Show the top 10
        // (by rating) when the box is empty/focused, and matching names when the
        // user actually searches.
        const playerSuggestions = computed(() => {
            const q = newPlayerName.value.trim().toLowerCase();
            const inSession = new Set(activePlayers.value.map(sp => sp.player_id));
            const pool = allKnownPlayers.value.filter(p => !inSession.has(p.id));

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
        let syncTimer = null;
        let reconcileTimer = null;
        const syncing = ref(false);
        const syncEnabled = ref(SYNC_CONFIG.sync_button !== false);

        // ── Offline-first player store + sync queue ──
        // Remote connection is only needed for the initial player load and
        // session-end sync. During a session, players are stored locally and
        // pushed to the server periodically; failures are silent and retried.
        const LS_PLAYERS = 'courtly.players.v1';
        const LS_QUEUE = 'courtly.syncQueue.v1';
        const LS_SESSION = 'courtly.session.' + SESSION_ID + '.v1';
        const SYNC_INTERVAL_MS = Number(SYNC_CONFIG.auto_sync_interval_ms ?? 30000);
        const RECONCILE_INTERVAL_MS = Number(SYNC_CONFIG.reconcile_interval_ms ?? SYNC_INTERVAL_MS);
        const SYNC_ON_IDLE = SYNC_CONFIG.sync_on_idle !== false;
        const SYNC_ON_SESSION_END = SYNC_CONFIG.sync_on_session_end !== false;
        function readLS(key, fallback) {
            try { const raw = localStorage.getItem(key); return raw === null ? fallback : JSON.parse(raw); } catch { return fallback; }
        }
        function writeLS(key, val) {
            try { localStorage.setItem(key, JSON.stringify(val)); } catch {}
        }
        const syncQueue = ref(readLS(LS_QUEUE, []));
        const pendingCount = computed(() => syncQueue.value.length);
        function persistQueue() { writeLS(LS_QUEUE, syncQueue.value); }
        function enqueueSync(path, method, body) {
            syncQueue.value.push({
                id: Date.now().toString(36) + Math.random().toString(36).slice(2, 8),
                path, method, body, attempts: 0
            });
            persistQueue();
        }
        // Persist the current session state to browser storage so the UI can
        // restore instantly and run snappily without waiting on the network.
        function persistSessionState() {
            writeLS(LS_SESSION, {
                status: session.status,
                players: players.value,
                courts: courts.value,
                savedAt: Date.now()
            });
        }
        function loadSessionState() {
            const cached = readLS(LS_SESSION, null);
            if (!cached) return;
            if (cached.status) session.status = cached.status;
            if (Array.isArray(cached.players)) players.value = cached.players;
            if (Array.isArray(cached.courts)) courts.value = cached.courts;
        }
        // Keep the local store in sync with reactive state (any change persists).
        watch([players, courts, () => session.status], persistSessionState, { deep: true });
        // Fetch the full player list from the server and cache it locally.
        async function refreshPlayerCache() {
            try {
                const res = await fetch(BASE_URL + '/api/players', { credentials: 'include', headers: { 'Accept': 'application/json' } });
                if (res.ok) {
                    const json = await res.json();
                    const list = json.data || [];
                    allKnownPlayers.value = list;
                    writeLS(LS_PLAYERS, list);
                }
            } catch { /* server unreachable — keep cached players */ }
        }
        // Push pending ops to the server. Failures are silent — kept in queue,
        // retried 30s later by the sync loop.
        async function flushSyncQueue() {
            if (syncing.value || syncQueue.value.length === 0) return;
            syncing.value = true;
            try {
                const remaining = [];
                let progressed = false;
                for (const op of syncQueue.value) {
                    try {
                        const res = await fetch(BASE_URL + op.path, {
                            method: op.method,
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                            credentials: 'include',
                            body: op.body ? JSON.stringify(op.body) : undefined
                        });
                        if (res.ok) { progressed = true; }
                        else { op.attempts++; remaining.push(op); }
                    } catch { op.attempts++; remaining.push(op); }
                }
                syncQueue.value = remaining;
                persistQueue();
                // On any success, reconcile local state with the server in the background
                if (progressed) { refreshPlayerCache(); fetchSession(); }
            } finally { syncing.value = false; }
        }
        function startSyncLoop() {
            flushSyncQueue();
            if (SYNC_INTERVAL_MS > 0) {
                syncTimer = setInterval(flushSyncQueue, SYNC_INTERVAL_MS);
            }
        }

        // Theme always follows the OS (system). No data-theme attribute is ever
        // set, so the CSS @media (prefers-color-scheme) rules apply.
        const COURT_COLORS = { 1:'#3B82F6', 2:'#EF4444', 3:'#F59E0B', 4:'#10B981', 5:'#8B5CF6', 6:'#EC4899', 7:'#06B6D4', 8:'#F97316' };
        function courtAccent(n) { return COURT_COLORS[n] || '#6B7280'; }

        async function loadKnownPlayers() {
            // 1. Instant from local cache (works offline)
            const cached = readLS(LS_PLAYERS, []);
            if (cached.length > 0) allKnownPlayers.value = cached;
            // 2. Background refresh from server
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

        async function fetchSession() {
            // Abort after 45s so a hung server (e.g. DB down) is flagged as unreachable.
            // (Remote DB can take ~30s per request, so 8s was too aggressive.)
            const controller = new AbortController();
            const timer = setTimeout(() => controller.abort(), 45000);
            try {
                const res = await fetch(BASE_URL + '/api/sessions/' + SESSION_ID, { credentials: 'include', signal: controller.signal });
                clearTimeout(timer);
                if (!res.ok) { connectionState.value = 'offline'; return; }
                const json = await res.json();
                const d = json.data;
                if (!d) { connectionState.value = 'offline'; return; }
                session.status = d.status;
                matchmakingMode.value = d.matchmaking_mode || 'smart';

                // Lookup of per-player session stats (wins/losses) by player id
                const stats = {};
                (d.session_players || []).forEach(sp => { stats[sp.player_id] = { wins: sp.wins, losses: sp.losses }; });

                courts.value = (d.courts || []).map(c => {
                    const match = (d.matches || []).find(m => m.court_id === c.id && m.status === 'PLAYING');
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
                // Keep optimistic local-only players (not yet synced) so they
                // don't blink out of the list before the server catches up.
                const serverNames = new Set(serverPlayers.map(sp => sp.player && sp.player.name));
                const localOnly = players.value.filter(sp =>
                    sp.player && sp.player._local && !serverNames.has(sp.player.name)
                );
                players.value = [...serverPlayers, ...localOnly];
                connectionState.value = 'connected';
            } catch (err) {
                clearTimeout(timer);
                connectionState.value = 'offline';
            }
        }

        async function api(url, body) {
            const res = await fetch(BASE_URL + url, { method: body ? 'POST' : 'GET', headers: body ? {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':CSRF_TOKEN} : {'Accept':'application/json'}, credentials: 'include', body: body ? JSON.stringify(body) : undefined });
            return res.json();
        }

        async function postApi(url) { const res = await fetch(BASE_URL + url, { method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':CSRF_TOKEN}, credentials:'include' }); return res.json(); }
        async function recordResult(matchId, team) {
            if (submitting[matchId + '_' + team]) return;
            submitting[matchId + '_' + team] = true;

            // Optimistic UI: move players to waiting and clear the court.
            const losingTeam = team === 1 ? 2 : 1;
            const court = courts.value.find(c => c.match && c.match.id === matchId);
            if (court && court.match) {
                const losers = losingTeam === 1 ? court.match.t1 : court.match.t2;
                const winnerNames = (team === 1 ? court.match.t1 : court.match.t2).map(p => p.name);

                players.value = players.value.map(sp => {
                    const isLoser = losers.some(l => l.name === sp.player.name);
                    const isWinner = winnerNames.includes(sp.player.name);
                    if (isLoser) return { ...sp, status: 'WAITING', games_played: sp.games_played + 1, wins: sp.wins, losses: sp.losses + 1 };
                    if (isWinner) return { ...sp, status: 'WAITING', games_played: sp.games_played + 1, wins: sp.wins + 1, losses: sp.losses };
                    return sp;
                });

                court.match = null;
            }
            submitting[matchId + '_' + team] = false;

            // Offline-first: store the result locally, then push immediately.
            // If the server is slow or down, the result stays queued in local
            // storage and is retried by the sync loop, the Sync button, or at
            // session end.
            enqueueSync('/api/matches/' + matchId + '/result', 'POST', { winning_team: team });
            flushSyncQueue();
        }
        async function startNewSession() {
            confirmNewSession.value = { show: true };
        }
        async function doStartNewSession() {
            confirmNewSession.value = { show: false };
            const courtCount = courts.value.length || 3;
            const res = await fetch(BASE_URL + '/api/sessions', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                credentials: 'include',
                body: JSON.stringify({ name: sessionName.value, number_of_courts: courtCount })
            });
            const json = await res.json();
            if (!res.ok || !json.data || !json.data.id) return;
            window.location.href = BASE_URL + '/sessions/' + json.data.id + '/live';
        }
        async function toggleMode() {
            const next = matchmakingMode.value === 'peg' ? 'smart' : 'peg';

            // Flip instantly — the POST is slow (remote DB), so don't block the
            // switch on it. The request runs in the background and the label
            // updates immediately from local state.
            matchmakingMode.value = next;

            fetch(BASE_URL + '/api/sessions/' + SESSION_ID + '/matchmaking-mode', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                credentials: 'include',
                body: JSON.stringify({ mode: next })
            })
                .catch(() => {})
                .finally(() => fetchSession());
        }
        async function startSession() {
            await flushSyncQueue(); // push any pending player additions first
            await postApi('/api/sessions/' + SESSION_ID + '/start');
            fetchSession();
        }
        async function pauseSession() { await postApi('/api/sessions/' + SESSION_ID + '/pause'); fetchSession(); }
        async function resumeSession() { await postApi('/api/sessions/' + SESSION_ID + '/resume'); fetchSession(); }
        async function finishSession() {
            if (SYNC_ON_SESSION_END) await flushSyncQueue(); // push any pending changes
            await postApi('/api/sessions/' + SESSION_ID + '/finish');
            fetchSession();
        }
        async function addPlayers() {
            const name = newPlayerName.value.trim();
            if (!name) return;

            const tempId = 'local_' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6);

            // Local-first: show in the waiting queue immediately (no server wait)
            players.value.push({
                player_id: tempId,
                player: { id: tempId, name: name, rating: 0, _local: true },
                status: 'WAITING',
                games_played: 0, wins: 0, losses: 0
            });
            newPlayerName.value = '';

            // Queue for background sync (flushed on the configured schedule,
            // on idle, via the Sync button, or at session end).
            enqueueSync('/api/sessions/' + SESSION_ID + '/players', 'POST', { name: name });
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

            newPlayerName.value = ''; // clear the autocomplete input
            enqueueSync('/api/sessions/' + SESSION_ID + '/players', 'POST', { player_ids: [id] });
        }

        function pausePlayer(spId) {
            if (typeof spId === 'number') {
                players.value = players.value.map(sp => sp.id === spId ? { ...sp, status: 'PAUSED', waiting_since: null } : sp);
                enqueueSync('/api/session-players/' + spId + '/pause', 'POST');
            }
        }
        function resumePlayer(spId) {
            if (typeof spId === 'number') {
                players.value = players.value.map(sp => sp.id === spId ? { ...sp, status: 'WAITING' } : sp);
                enqueueSync('/api/session-players/' + spId + '/resume', 'POST');
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
                players.value = players.value.map(sp => sp.id === spId ? { ...sp, status: 'LEFT', left_at: new Date().toISOString() } : sp);
                enqueueSync('/api/session-players/' + spId + '/leave', 'POST');
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
            allKnownPlayers.value = allKnownPlayers.value.filter(p => p.id !== playerId);
            enqueueSync('/api/players/' + playerId, 'DELETE');
        }

        function onVisibilityChange() {
            if (document.visibilityState === 'hidden') flushSyncQueue();
        }
        function onPageHide() {
            flushSyncQueue();
        }

        onMounted(() => {
            loadSessionState();          // instant from browser storage
            fetchSession();              // reconcile with the server once
            loadKnownPlayers();
            startSyncLoop();

            // Background reconcile (safety net) instead of a 3s poll.
            if (RECONCILE_INTERVAL_MS > 0) {
                reconcileTimer = setInterval(fetchSession, RECONCILE_INTERVAL_MS);
            }

            // Flush pending changes when the user leaves / tab goes idle.
            if (SYNC_ON_IDLE) {
                document.addEventListener('visibilitychange', onVisibilityChange);
                window.addEventListener('pagehide', onPageHide);
            }
        });
        onUnmounted(() => {
            if (syncTimer) clearInterval(syncTimer);
            if (reconcileTimer) clearInterval(reconcileTimer);
            document.removeEventListener('visibilitychange', onVisibilityChange);
            window.removeEventListener('pagehide', onPageHide);
        });

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

        // Clamp a rating into the visible 1–100 badge range.
        function ratingBadge(r) { return Math.max(1, Math.min(100, Math.round(Number(r) || 0))); }

        // How many games this player has sat out (relative to the session leader).
        const sessionMaxGames = computed(() => players.value.reduce((m, p) => Math.max(m, p.games_played || 0), 0));
        function sitOuts(sp) { return Math.max(0, sessionMaxGames.value - (sp.games_played || 0)); }

        return { session, sessionName, matchmakingMode, modeLabel, toggleMode, courts, players, waitingPlayers, queuePlayers, nextFourIds, pendingCourtPlayers, activePlayers, submitting, connectionState, elapsed, showPlayers, showSuggestions, showSuggestionsNow, hideSuggestionsLater, newPlayerName, availablePlayers, playerSuggestions, isInSession, confirmRemove, confirmDelete, confirmNewSession, courtAccent, recordResult, startSession, startNewSession, doStartNewSession, pauseSession, resumeSession, finishSession, openPlayers, addPlayers, addExistingPlayer, pausePlayer, resumePlayer, openRemove, confirmLeave, openDelete, openDeleteById, deletePlayer, formatName, ratingBadge, sitOuts, syncNow: flushSyncQueue, syncing, pendingCount, syncEnabled, Math };
    }
}).mount('#courtly-app');
</script>
</body>
</html>
