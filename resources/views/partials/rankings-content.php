<style>
    .rankings-card { background: var(--surface); border: 1px solid var(--stroke); border-radius: var(--radius); padding: 18px; box-shadow: var(--shadow-card); }
    .rankings-card__intro { color: var(--text-muted); margin: 0 0 16px; font-size: .86rem; }
    .ranking-table-wrap { overflow-x: auto; }
    .ranking-table { width: 100%; border-collapse: collapse; font-size: .88rem; min-width: 480px; }
    .ranking-table th, .ranking-table td { padding: 12px 10px; border-bottom: 1px solid var(--stroke); text-align: right; white-space: nowrap; }
    .ranking-table th:first-child, .ranking-table td:first-child, .ranking-table th:nth-child(2), .ranking-table td:nth-child(2) { text-align: left; }
    .ranking-table thead th { color: var(--text-muted); font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; }
    .ranking-table tbody th { color: var(--text); font-weight: 700; }
    .ranking-table tbody tr:last-child th, .ranking-table tbody tr:last-child td { border-bottom: 0; }
    .ranking-table__rank { color: var(--accent); font-weight: 800; width: 48px; font-size: 1.05rem; }
    .ranking-table__icon { width: 30px; height: 30px; object-fit: contain; vertical-align: middle; }
    .ranking-table__empty { color: var(--text-muted); text-align: left !important; padding: 24px 10px !important; }
    .ranking-table__rating { font-weight: 800; color: var(--accent); }
    .ranking-table .rank-icon { margin-left: 0; margin-right: 6px; }
    .ranking-table tbody tr.ranking-row { cursor: pointer; transition: background .15s; }
    .ranking-table tbody tr.ranking-row:hover { background: var(--bg-accent, #1a1a2e); }

    .player-stats-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.6); display: none; align-items: center; justify-content: center; z-index: 10000; padding: 16px; }
    .player-stats-overlay.open { display: flex; }
    .player-stats-card { background: var(--surface); border: 1px solid var(--stroke); border-radius: 12px; padding: 20px; width: 100%; max-width: 680px; max-height: 84vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,.5); }
    .player-stats-card__head { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; }
    .player-stats-card__name { font-size: 1.3rem; font-weight: 800; margin: 0; }
    .player-stats-card__close { margin-left: auto; border: 1px solid var(--stroke); background: transparent; color: var(--text-muted); border-radius: 999px; padding: 6px 14px; cursor: pointer; font-size: .8rem; font-weight: 700; }
    .player-stats-card__close:hover { border-color: var(--accent); color: var(--text); }
    .player-stats-card .rank-icon { margin-left: 0; margin-right: 6px; }
    .ps-badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: .7rem; font-weight: 700; }
    .ps-badge--provisional { background: var(--tag-provisional-bg); color: var(--tag-provisional-text); }
    .ps-badge--established { background: var(--tag-established-bg); color: var(--tag-established-text); }
    .ps-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 10px; }
    .ps-item { background: var(--bg); border: 1px solid var(--stroke); border-radius: 8px; padding: 12px 14px; }
    .ps-item__label { font-size: .68rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--text-muted); margin-bottom: 4px; }
    .ps-item__value { font-size: 1.15rem; font-weight: 800; line-height: 1.15; }
    .ps-item__value--good { color: var(--status-active-text); }
    .ps-item__value--bad { color: var(--status-passed-text); }
    .ps-item__sub { font-size: .75rem; color: var(--text-muted); margin-top: 4px; line-height: 1.35; }
    .ps-form { display: flex; gap: 4px; flex-wrap: wrap; margin: 0 0 14px; }
    .ps-form-chip { width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; border-radius: 6px; font-size: .68rem; font-weight: 800; }
    .ps-form-chip--w { background: var(--win-chip-bg); color: var(--win-chip-text); }
    .ps-form-chip--l { background: var(--loss-chip-bg); color: var(--loss-chip-text); }
</style>

<section class="rankings-card" aria-labelledby="rankings-title">
    <h2 id="rankings-title">Player Rankings</h2>
    <p class="rankings-card__intro">Your roster, ordered by rating.</p>
    <div class="ranking-table-wrap">
        <table class="ranking-table">
            <thead>
                <tr>
                    <th scope="col">Rank</th>
                    <th scope="col">Player</th>
                    <th scope="col">Rating</th>
                    <th scope="col">Games</th>
                    <th scope="col">Win rate</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($players->isEmpty()): ?>
                <tr><td colspan="5" class="ranking-table__empty">No players yet.</td></tr>
            <?php else: ?>
                <?php foreach ($players as $rank => $player): ?>
                    <?php $winPercentage = $player->total_games > 0 ? round(($player->wins / $player->total_games) * 100, 1) : 0; ?>
                    <?php $rankNumber = $rank + 1; ?>
                    <?php
                        $rating = (float) $player->rating;
                        $tier = $rating >= 75 ? 'apex' : ($rating >= 50 ? 'pace' : ($rating >= 25 ? 'rise' : 'start'));
                        $emblemBase = e($base ?? '') . '/assets/ranks/' . $tier;
                    ?>
                    <tr class="ranking-row" data-player-id="<?= (int) $player->id ?>" onclick="showPlayerStats(<?= (int) $player->id ?>)">
                        <td class="ranking-table__rank" aria-label="Rank <?= $rankNumber ?>"><?= $rankNumber ?></td>
                        <th scope="row">
                            <span class="rank-icon">
                                <img src="<?= $emblemBase ?>@2x.png" srcset="<?= $emblemBase ?>@1x.png 1x, <?= $emblemBase ?>@2x.png 2x, <?= $emblemBase ?>@3x.png 3x" alt="<?= e(ucfirst($tier)) ?> rank" width="28" height="28" decoding="async">
                            </span>
                            <?= e($player->name) ?>
                        </th>
                        <td class="ranking-table__rating"><?= number_format($rating, 1) ?></td>
                        <td><?= $player->total_games ?></td>
                        <td><?= $winPercentage ?>%</td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<div class="player-stats-overlay" id="playerStatsOverlay">
    <div class="player-stats-card" id="playerStatsCard" role="dialog" aria-modal="true"></div>
</div>

<script>
(function () {
    var BASE = <?= json_encode($base ?? '') ?>;
    var overlay = document.getElementById('playerStatsOverlay');
    var card = document.getElementById('playerStatsCard');

    function esc(s) {
        return String(s).replace(/[&<>"']/g, function (ch) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
        });
    }
    function r0(x) { return Math.round(Number(x)); }
    function signed(x) { return (x > 0 ? '+' : '') + Number(x).toFixed(2); }

    window.showPlayerStats = function (id) {
        card.innerHTML = '<div class="ps-item"><div class="ps-item__label">Loading</div><div class="ps-item__value">…</div></div>';
        overlay.classList.add('open');
        fetch(BASE + '/api/players/' + id + '/stats', { headers: { 'Accept': 'application/json' } })
            .then(function (res) { if (!res.ok) { throw new Error('failed'); } return res.json(); })
            .then(function (json) { renderPlayerStats(json.data); })
            .catch(function () {
                card.innerHTML = '<div class="ps-item"><div class="ps-item__value">Could not load stats.</div></div>';
            });
    };

    window.closePlayerStats = function () {
        overlay.classList.remove('open');
    };

    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) { closePlayerStats(); }
    });

    function renderPlayerStats(data) {
        if (!data) { return; }
        var s = data.summary || {};
        var streak = s.current_streak || { type: null, length: 0 };
        var tm = s.most_common_teammate;
        var op = s.toughest_opponent;

        var formHtml = (data.form && data.form.length)
            ? '<div class="ps-form">' + data.form.map(function (r) {
                return '<span class="ps-form-chip ps-form-chip--' + r.toLowerCase() + '">' + r + '</span>';
            }).join('') + '</div>'
            : '';

        var items = [
            { label: 'Rating', value: String(r0(s.rating)), sub: 'Peak ' + r0(s.peak_rating) + ' · Low ' + r0(s.low_rating) },
            { label: 'Status', value: s.rating_status || '—', sub: null },
            { label: 'Record', value: (s.wins || 0) + '–' + (s.losses || 0), sub: (s.win_percentage || 0) + '% win rate' },
            { label: 'Current streak', value: streak.length ? streak.length + (streak.type === 'WIN' ? 'W' : 'L') : '—', sub: streak.type ? (streak.type === 'WIN' ? 'On a win streak' : 'On a losing streak') : 'No rated games yet' },
            { label: 'Best streaks', value: (s.longest_win_streak || 0) + 'W / ' + (s.longest_loss_streak || 0) + 'L', sub: 'Longest win / loss runs' },
            { label: 'Momentum', value: signed(s.rating_momentum), sub: 'Avg rating change · last 5 games', good: s.rating_momentum > 0, bad: s.rating_momentum < 0 },
            { label: 'Upset wins', value: String(s.upset_wins || 0), sub: s.upset_rate == null ? 'No underdog games yet' : s.upset_rate + '% as underdog' },
            { label: 'Clutch rate', value: s.clutch_rate == null ? '—' : s.clutch_rate + '%', sub: 'Close games (40–60% odds)' },
            { label: 'Sessions', value: String(s.sessions_attended || 0), sub: (s.avg_games_per_session || 0) + ' games/session avg' },
            { label: 'Favourite teammate', value: tm ? tm.name : '—', sub: tm ? (tm.games + ' games · ' + Math.round(tm.wins / tm.games * 100) + '% wins') : 'Play with someone first' },
            { label: 'Toughest opponent', value: op ? op.name : '—', sub: op ? (op.games + ' meetings · ' + op.wins + '–' + op.losses) : 'Face someone first' }
        ];

        var gridHtml = items.map(function (c) {
            var cls = 'ps-item__value';
            if (c.good) { cls += ' ps-item__value--good'; }
            if (c.bad) { cls += ' ps-item__value--bad'; }
            return '<div class="ps-item">'
                + '<div class="ps-item__label">' + esc(c.label) + '</div>'
                + '<div class="' + cls + '">' + esc(c.value) + '</div>'
                + (c.sub ? '<div class="ps-item__sub">' + esc(c.sub) + '</div>' : '')
                + '</div>';
        }).join('');

        var rating = Number(s.rating) || 0;
        var tier = rating >= 75 ? 'apex' : (rating >= 50 ? 'pace' : (rating >= 25 ? 'rise' : 'start'));
        var emblem = BASE + '/assets/ranks/' + tier;
        var rankIconHtml = '<span class="rank-icon"><img src="' + emblem + '@2x.png" srcset="' + emblem + '@1x.png 1x, ' + emblem + '@2x.png 2x, ' + emblem + '@3x.png 3x" alt="' + esc(tier) + ' rank" width="28" height="28" decoding="async"></span>';

        card.innerHTML =
            '<div class="player-stats-card__head">'
            + rankIconHtml
            + '<h3 class="player-stats-card__name">' + esc(data.name || 'Player') + '</h3>'
            + '<span class="ps-badge ps-badge--' + (s.rating_status === 'ESTABLISHED' ? 'established' : 'provisional') + '">' + esc(s.rating_status || '') + '</span>'
            + '<button type="button" class="player-stats-card__close" onclick="closePlayerStats()">Close</button>'
            + '</div>'
            + formHtml
            + '<div class="ps-grid">' + gridHtml + '</div>';
    }
})();
</script>
