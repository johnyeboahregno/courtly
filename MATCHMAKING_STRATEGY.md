# Courtly — Matchmaking Strategy

This document describes the matchmaking algorithm as it exists today, and
establishes an explicit **order of precedence** for the rules. When two rules
conflict, the higher-precedence rule wins.

The implementation lives in:

- `app/Services/MatchmakingService.php` — the algorithm.
- `app/Services/MatchResultService.php` — atomic result recording + re-allocation.
- `config/courtly.php` — every tunable under `matchmaking.*`.

---

## 0. Matchmaking modes

Each session has a `matchmaking_mode` (`smart` by default, switchable in the
live view):

- **`smart`** — the weighted / fairness-first strategy documented below.
- **`peg`** — a traditional badminton peg-board queue: the first waiting player
  is the anchor, three companions are chosen from a pick zone behind the anchor,
  and the four are balanced into teams. The queue itself provides fairness.

The toggle in the session header switches modes per session and persists on the
session record. The sections below describe the `smart` strategy; the peg
strategy is implemented in `MatchmakingService::findPegAssignments()`.

---

## 1. Rule precedence (highest first)

Rules higher in the list always override rules lower in the list. Where rules
are expressed as costs, a higher-cost penalty is checked before a lower-cost one
when ranking candidate groups.

| # | Rule | Type | Default | Notes |
|---|------|------|---------|-------|
| 1 | Session-state guard | hard return | — | `PAUSED`/`FINISHED` ⇒ never matchmake. `UPCOMING` is allowed (auto-starts). |
| 2 | Supply guard | hard return | — | No `AVAILABLE` court, **or** fewer than 4 `WAITING` players ⇒ do nothing. |
| 3 | Active-player guard (MM-005) | exclusion | — | A player already in a `PLAYING` match is never re-allocated. |
| 4 | Fairness ranking | ranking | — | Fewer games, longer wait, previous sit-out, then winner tie-break. |
| 5 | Max-wait DUE | ranking boost | `max_wait_minutes` (12) | A `WAITING` player who waited too long becomes mandatory. |
| 6 | Exact-repeat block | hard (tier) | — | Same 4 players as any court's own last round — supersedes soft rules; only a completely-unfair alternative allows a repeat. |
| 7 | Unfair-group block | cost `100000` | `unfair_group_penalty` | Team-average rating difference > `max_balance_difference` (25). |
| 8 | Same-side repeat block | split skip | `same_side_consecutive_block` | Two players who were teammates last round may not be teamed again. |
| 9 | Consecutive-matchup block | cost `10000` | `consecutive_matchup_penalty` | Identical 2v2 pairing as any court's last round. |
| 10 | Court rotation | cost `800`/player (+`2000` for winners) | `court_return_penalty`, `winner_return_penalty` | Players are pushed off the court they just played on; winners cost extra (soft). |
| 11 | Relationship penalties | cost | see config | Recent teammate (50), consecutive teammate (20), recent opponent (5). |
| 12 | Balance & cohesion | cost | see config | Team balance (15 × diff), skill spread (8 × spread), rotation fairness (2 × priority gap). |
| 13 | Selection & fallbacks | selection | — | Lowest total cost, non-overlapping groups; adjacent-window fallback; keep-playing escape hatch. |

---

## 2. Current rules in detail

### Rule 1 — Session-state guard

```php
if ($session->status === PAUSED || $session->status === FINISHED) {
    return [];
}
```

- `UPCOMING` sessions **are** allowed to matchmake: this is how courts fill as
  soon as four players have checked in, before the session is formally started.
- Creating the first match flips `UPCOMING → ACTIVE` automatically.

### Rule 2 — Supply guard

```php
if ($availableCourts->isEmpty()) return [];        // no free court
if ($waitingPlayers->count() < 4) return [];       // fewer than 4 on the bench
```

- A court is **never** filled unless at least four players are waiting.

### Rule 3 — Active-player guard (MM-005)

A player with an active `PLAYING` match is removed from the candidate pool, even
if their `session_players.status` is stale. A player can only be on one court at
a time.

### Rule 4 — Fairness ranking

A free court is filled as soon as four eligible `WAITING` players are available —
even while other courts are still `PLAYING`. Players are ranked by fairness
priority (see §3), so players who have been waiting longer and have played fewer
games are selected before players who have just come off court.

### Rule 5 — Max-wait DUE

A `WAITING` player who has waited at least `max_wait_minutes` (default `12`)
receives a large priority boost and becomes mandatory for the next allocation,
regardless of skill optimisation.

### Rule 6 — Exact-repeat block (hard)

A candidate group that contains exactly the same four players as **any court's
own last round** is demoted to a worse selection tier instead of merely adding a
cost.

- Guard is per-court (`matchmaking.per_court_repeat_guards = true`): each court
  compares against its own previous match, not just the globally-latest match.
- The no-repeat rule supersedes the soft rules: a repeat group is only chosen
  when every non-repeat alternative would be completely unfair, or when the
  numbers force it (see the tier ordering in Rule 13).

### Rule 7 — Unfair-group block

If the best split for a group has `team_balance_difference > max_balance_difference`
(default `25.0`), the group is penalised `unfair_group_penalty` (default `100000`).

- Like Rule 5, this is "block unless forced" — unfair groups keep playing only if
  no fair group exists.

### Rule 8 — Same-side repeat block

When choosing a team split, any split that would pair two players who were
teammates in **any court's last round** is skipped entirely.

- If all three possible splits are blocked, the least-bad split is used so the
  court does not sit idle (escape hatch).

### Rule 9 — Consecutive-matchup block

A split whose two teams are identical (same 2v2, order-independent) to a court's
last round is penalised `consecutive_matchup_penalty` (default `10000`).

### Rule 10 — Court rotation

Players avoid returning to the court they just played on.

- During group selection, a group is penalised `winner_return_penalty` (default
  `2000`) × the **fewest** winners it would return to any available court.
- After groups are chosen, courts are assigned greedily: each group takes the
  remaining court that returns the fewest of its players — `court_return_penalty`
  (default `800`) per returning player, plus `winner_return_penalty` per
  returning winner (tie-break: lowest court number).

### Rule 11 — Relationship penalties

Soft penalties discourage repeated pairings across recent history
(`recent_match_window`, default `5`):

- `repeat_teammate_penalty` (20) — consecutive repeat teammate pair.
- `recent_teammate_penalty` (50) — teammate pair within the recent window.
- `repeat_opponent_penalty` (5) — repeated opponent pair.

### Rule 12 — Balance & cohesion

- **Team balance** — `balance_weight` (15) × absolute difference between the two
  team average ratings.
- **Skill spread** — `skill_spread_weight` (8) × (max rating − min rating) of the
  four players.
- **Rotation fairness** — 2 × the gap between the highest and average rotation
  priority of the group.

### Rule 13 — Selection & fallbacks

1. Rank all waiting players by **rotation priority** (see §3).
2. Take the top `N×4 + buffer` players, sort them by rating for skill cohesion.
3. Generate candidate groups of four — adjacent rating windows **and** strided
   windows (every 2nd rating position) so a non-repeat grouping is always in
   contention.
4. Assign each group a **tier** (lower is chosen first):

   | Tier | Meaning |
   |------|---------|
   | 0 | fair, new group |
   | 1 | fair, repeat |
   | 2 | unfair, new group |
   | 3 | unfair, repeat |

5. Select the **non-overlapping** set that minimises (unfilled courts, then tier
   sum, then total cost). Greedy completion is tried from several seeds so the
   single cheapest group can't strand the remaining courts.
6. If still under-filled, fall back to adjacent rating windows so no court is
   left idle (the "numbers force it" escape hatch).

---

## 3. Rotation priority

`calculateRotationPriority()` ranks who plays next. Higher = sooner.

| Component | Weight | Effect |
|-----------|--------|--------|
| Games fairness | `100` × (max games − player games) | Fewest games played first. |
| Waiting time | `2` × minutes waiting | Longer waits move up. |
| Sit-out bonus | `50` | Sat out last round. |
| Forced sit-out | `-200` | Played `max_consecutive_games` (2) in a row. |
| Winner preference | `10` | Won last match — soft tie-break only. |
| Max-wait DUE | `10000` | Waited ≥ `max_wait_minutes` — mandatory. |

---

## 4. Cost model summary

A candidate group's **total cost** is:

```
total =
    groupCost
  + pairingCost
  + (unfair ? unfair_group_penalty : 0)
  + returningWinners × winner_return_penalty
```

where:

```
groupCost =
    skillSpread × skill_spread_weight
  + (maxPriority − avgPriority) × 2
  + (exactRepeat ? 100000 : 0)

pairingCost =
    balanceDiff × balance_weight
  + consecutiveRepeatTeammates × repeat_teammate_penalty
  + recentTeammates × recent_teammate_penalty
  + recentOpponents × repeat_opponent_penalty
  + (consecutiveMatchup ? consecutive_matchup_penalty : 0)
```

The relative weights make the precedence in §1: hard blocks (`100000`, `10000`)
dominate soft relationship/balance penalties, which dominate raw skill/rotation
scores.

After groups are selected, courts are assigned greedily. That assignment adds
`court_return_penalty` (default `800`) per player returning to their previous
court, plus `winner_return_penalty` per returning winner — so a losing pair can't
camp on the same court round after round.

---

## 5. Worked example — 12 players, 3 courts

1. Session starts: 3 courts `AVAILABLE`, 12 players `WAITING`. Supply guard
   passes; rotation hold does not apply (no court is playing yet).
2. Players are ranked, sorted by rating, and grouped into three balanced,
   non-repeat groups; team splits are chosen with winners/teammates avoided.
3. Three `PLAYING` matches are created; session becomes `ACTIVE`.
4. Court 1 finishes → its four players become `WAITING`, court 1 becomes
   `AVAILABLE`. Courtly immediately builds a new match from the waiting players
   (the four who just came off plus anyone already waiting), prioritising the
   players who were already waiting and the most-due.
5. Any player who has been waiting past `max_wait_minutes` becomes DUE and is
   placed in the next match regardless of skill optimisation.
6. Winners only receive a small tie-break preference; they no longer override
   players who have been waiting substantially longer.

---

## 6. Config reference

See `config/courtly.php` → `matchmaking`. Key tunables:

| Key | Default | Purpose |
|-----|---------|---------|
| `algorithm_version` | `courtly-v2.0` | Stored on each `smart` match. |
| `peg_algorithm_version` | `courtly-peg-v1.0` | Stored on each `peg` match. |
| `skill_spread_weight` | `8` | Penalty per rating point of group spread. |
| `balance_weight` | `15` | Penalty per rating point of team imbalance. |
| `repeat_teammate_penalty` | `20` | Consecutive repeat teammate pair. |
| `recent_teammate_penalty` | `50` | Recent repeat teammate pair. |
| `repeat_opponent_penalty` | `5` | Recent repeat opponent pair. |
| `consecutive_matchup_penalty` | `10000` | Same 2v2 as last round. |
| `candidate_pool_buffer` | `3` | Extra players beyond `N×4` for the pool. |
| `recent_match_window` | `5` | Matches considered "recent". |
| `winner_priority_bonus` | `10` | Rotation-priority boost for winners (soft tie-break). |
| `winner_return_penalty` | `2000` | Cost per winner returning to their court. |
| `court_return_penalty` | `800` | Cost per player returning to the court they just played on. |
| `same_side_consecutive_block` | `true` | Never re-team a pair from the last round. |
| `per_court_repeat_guards` | `true` | Check each court's own last round, not just the latest match. |
| `max_balance_difference` | `25.0` | Above this a match is "completely unfair". |
| `max_consecutive_games` | `2` | Games in a row before forced sit-out. |
| `consecutive_games_penalty` | `200` | Priority penalty for the forced sit-out. |
| `unfair_group_penalty` | `100000` | Cost added to unfair groups. |
| `max_wait_minutes` | `12` | Minutes waited before a player becomes DUE. |
| `max_skips` | `1` | Skips allowed before a player becomes DUE (staged). |
| `relationship_cooldown_matches` | `1` | Matches to avoid repeating companions (staged). |
| `paused_player_warning_rounds` | `2` | Rounds paused before a red warning (staged). |
| `paused_player_flash_enabled` | `true` | Flash paused players red when overdue (staged). |

---

## 7. Known limitations / next steps (staged work)

- **Skip tracking / `max_skips` DUE** — config exists, but persistent per-player
  skip counts are not yet tracked; a migration and selection-time logic are next.
- **PAUSED round tracking & red warning UI** — `paused_player_warning_rounds`
  config exists, but `paused_rounds` tracking and the flashing-red UI are staged.
- **Relationship cooldown** — the "avoid any of the same 3 players from the
  previous match" rule (cooldown = 1) is staged; today's implementation blocks
  exact repeats and same-side teammate repeats.
- **Rolling planner** — a future-round planning layer is not yet implemented;
  allocation remains immediate and greedy.
- "Keep playing" always wins at the extremes — when numbers force a repeat or an
  unfair group, the least-bad option is used rather than leaving a court empty.
- Candidate selection is greedy (lowest-cost non-overlapping groups), not a
  global optimum. Fine for ≤8 courts; revisit if court counts grow.
