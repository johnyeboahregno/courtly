Yes — because the app is already running, I’d give Copilot a **refactor/implementation prompt**, not the original build-from-scratch prompt.

The current strategy already has useful foundations such as active-player guards, exact-repeat handling, teammate/opponent penalties, balance weighting, skill spread, and configurable matchmaking weights.  The main changes are to replace the strict rotation hold, strengthen fairness and relationship cooldown, add paused-player behaviour, and introduce rolling planning.

# Courtly Matchmaking Refactor

You are working on an existing production application called **Courtly**.

Courtly is a social badminton application that manages players, courts, ratings, waiting players, results and automatic doubles matchmaking.

The existing matchmaking implementation is already working.

Before modifying any code, read:

```text
MATCHMAKING_STRATEGY.md
```

Also inspect:

```text
app/Services/MatchmakingService.php
app/Services/MatchResultService.php
config/courtly.php
```

and all existing matchmaking tests.

Do **not** rewrite the application from scratch.

Refactor the current matchmaking strategy and implementation according to the requirements below.

---

# 1. Core Objective

The new matchmaking hierarchy should be:

```text
1. Keep court time fair.
2. Prevent players sitting out too long.
3. Respect voluntarily PAUSED players.
4. Select four players of broadly similar ability.
5. Balance those four into two even doubles teams.
6. Avoid playing with the same people in consecutive games.
7. Prefer previous court companions as opponents rather than teammates when repetition is unavoidable.
8. Continue creating games indefinitely as historical combinations are exhausted.
9. Keep courts running rather than waiting unnecessarily for perfect matches.
```

The system should behave like an intelligent digital badminton peg board.

---

# 2. Important Existing Behaviour to Change

The existing strategy contains a strict **rotation hold** that prevents players who just finished a court from being used while other courts are still playing.

Remove this as a hard rule.

A free court should be able to receive a new match as soon as at least four eligible players are available.

However, players who were already waiting before the court finished must receive strong priority over players who have just come off court.

Do not leave a playable court idle simply because another court is still playing.

---

# 3. Separate Matchmaking Into Three Stages

Do not use one opaque total score to decide everything.

The algorithm should conceptually have three stages:

```text
STAGE 1
Who deserves to play?

STAGE 2
Which four eligible players will create the best-quality game?

STAGE 3
How should those four players be divided into two teams?
```

Keep these concerns separate in the code where practical.

---

# 4. Player Session States

Support these states:

```text
WAITING
PLAYING
PAUSED
LEFT
```

Only:

```text
WAITING
```

players are eligible for matchmaking.

`PLAYING`, `PAUSED`, and `LEFT` players must never be selected.

---

# 5. PAUSED Players

A player can voluntarily choose to sit out.

When a player is:

```text
PAUSED
```

Courtly must:

```text
exclude them completely from matchmaking

not increase their fairness skip count

not treat their pause as an unfair sit-out

not automatically return them to the queue

preserve all session statistics
```

Track:

```text
paused_since
paused_rounds
```

or derive the number of matchmaking rounds completed while they were paused.

---

# 6. PAUSED Player Warning

If a player remains PAUSED for more than:

```text
2 matchmaking rounds
```

visually alert the organiser.

The player must:

```text
flash / pulse RED
```

on the live tablet UI.

Example:

```text
PAUSED

Rachel     63

James      48     PAUSED   🔴
```

The warning means:

```text
This player has voluntarily been sitting out for more than two matchmaking rounds.
Check whether they want to return.
```

Do NOT automatically resume them.

Clicking/tapping the warning player should offer something similar to:

```text
James has been paused for 3 rounds.

[ RETURN TO QUEUE ]

[ KEEP PAUSED ]
```

If KEEP PAUSED is selected, keep them paused.

Add configurable values:

```php
'paused_player_warning_rounds' => 2,
'paused_player_flash_enabled' => true,
```

---

# 7. Matchmaking Round Definition

Define a matchmaking round clearly.

A round should represent a completed allocation cycle, not simply every API call.

The implementation must ensure paused-round counts increase consistently and do not accidentally increase multiple times because several courts complete almost simultaneously.

Document the chosen definition in `MATCHMAKING_STRATEGY.md`.

---

# 8. Fair Rotation

The most important fairness goal is:

```text
Players should not sit off for too long.
```

Track at minimum:

```text
games_played_in_session
waiting_since
last_played_at
skip_count
consecutive_games
```

Fairness priority should primarily favour:

```text
1. Players with fewer games played
2. Players waiting longest
3. Players skipped during the previous matchmaking opportunity
4. Players who have already sat out
```

Recent winners must not receive enough priority to override someone who has been waiting substantially longer.

---

# 9. Maximum Game Difference

For players who have been present and available throughout the session, aim for:

```text
max(games_played)
-
min(games_played)
<= 1
```

where mathematically possible.

Add automated simulation tests to validate this.

---

# 10. Maximum Skip Protection

Introduce:

```text
MAX_SKIPS = 1
```

as the initial default.

A WAITING player can be passed over once to create a significantly better skill grouping.

If they would otherwise be skipped again, they become:

```text
DUE
```

and receive extremely strong priority for the next allocation.

This protection applies only to WAITING players.

PAUSED players do not accumulate skips.

Make this configurable:

```php
'max_skips' => 1,
```

---

# 11. Maximum Wait Protection

Also track real waiting duration.

Introduce configurable:

```php
'max_wait_minutes' => 12,
```

as an initial default.

If a WAITING player exceeds this threshold, make them DUE regardless of normal skill optimisation.

Do not apply this rule while the player is PAUSED.

---

# 12. Digital Peg Queue

Represent the waiting list as a virtual peg queue.

The queue should remain understandable to users.

Players near the front should generally be closer to their next game.

The first player or highest-priority DUE player can be considered the:

```text
ANCHOR
```

for a new match.

The matchmaking engine then searches for three appropriate companions for that anchor.

---

# 13. Skill Matching

Courtly must favour similarly skilled players sharing a court.

Ratings are continuous from:

```text
0–100
```

Do not create rigid permanent divisions.

Conceptually, however:

```text
strong players should generally play strong players

medium players should generally play medium players

beginners should generally play beginners
```

when enough players are available.

Calculate:

```text
skillSpread =
max(player ratings)
-
min(player ratings)
```

Lower is better.

---

# 14. Important Skill Example

Prefer:

```text
82
79
76
74
```

over:

```text
90
85
38
33
```

even if the second group can be mathematically divided into equally rated teams.

The objective is a good badminton game, not merely equal team totals.

---

# 15. Team Balancing

After selecting four players:

```text
A
B
C
D
```

evaluate all three doubles arrangements:

```text
AB vs CD
AC vs BD
AD vs BC
```

Calculate team strength:

```text
TeamStrength =
average(player ratings)
```

Then:

```text
balanceDifference =
abs(team1Strength - team2Strength)
```

Prefer the lowest valid balance difference.

---

# 16. Consecutive Player Cooldown

Change the relationship rule.

The new ideal rule is:

```text
A player should avoid playing with ANY of the same three players
from their immediately previous match.
```

For each player maintain or derive:

```text
last_match_player_ids
```

If John's previous court contained:

```text
Sarah
David
Rachel
```

Courtly should ideally create John's next match using three different players.

This includes both:

```text
previous teammate
previous opponents
```

---

# 17. Relationship Cooldown = One Match

Set:

```text
RELATIONSHIP_COOLDOWN_MATCHES = 1
```

as the default.

The strongest variety constraint applies only to the player's immediately previous game.

This should be configurable.

---

# 18. When Repetition Is Unavoidable

For sessions with too few players, the relationship cooldown may become impossible.

Relax gracefully.

Preference order should be:

```text
NEW PLAYER
    ↓
PREVIOUS OPPONENT
    ↓
PREVIOUS TEAMMATE
```

Therefore:

If two players must share a court again immediately, strongly prefer putting them on opposite teams rather than making them teammates again.

---

# 19. Example

Previous:

```text
John + Sarah
vs
David + Rachel
```

If Courtly needs to use all four again, prefer something like:

```text
John + David
vs
Sarah + Rachel
```

or:

```text
John + Rachel
vs
Sarah + David
```

rather than repeating:

```text
John + Sarah
vs
David + Rachel
```

The same teammate in consecutive matches should be the least desirable option.

---

# 20. Exact Repeat

The exact same:

```text
A+B vs C+D
```

must remain strongly blocked when any alternative exists.

Swapping sides does not make it a new match.

However, if numbers make repetition unavoidable, use the least-bad arrangement rather than leaving the court unused.

Log when this fallback occurs.

---

# 21. Recent History Should Decay

Do not permanently penalise every combination that has ever happened.

Use a decaying relationship history.

Suggested philosophy:

```text
Previous match:
very strong restriction

2–3 matches ago:
strong preference against repetition

4–5 matches ago:
small preference

Older:
little or no penalty
```

This allows Courtly to continue matchmaking indefinitely.

---

# 22. Once Combinations Have Been Used

Do NOT stop matchmaking because combinations have been exhausted.

Do NOT require every mathematical combination to be explicitly tracked as "completed".

Instead, older relationship history should naturally fall out of the recent-history window.

This means previously used combinations gradually become valid again.

The session can therefore continue indefinitely.

---

# 23. Suggested Relationship Configuration

Refactor configuration towards something like:

```php
'relationship_cooldown_matches' => 1,

'recent_match_window' => 5,

'previous_match_player_penalty' => 5000,

'consecutive_teammate_penalty' => 10000,

'recent_teammate_penalty' => 100,

'recent_opponent_penalty' => 40,

'exact_match_penalty' => 100000,
```

Treat these values as initial tuning values, not immutable requirements.

Use tests and simulation to tune them.

---

# 24. Winner Priority

The current winner bonus should no longer dominate rotation fairness.

Winner status may be used as:

```text
a small soft preference
or
a final tie-break
```

It must never cause a player who has been waiting substantially longer to continue sitting out.

Reduce the existing winner priority significantly.

Do not implement traditional "winner stays on" behaviour.

---

# 25. Court Throughput

When a court becomes free:

If there are at least four eligible WAITING players:

```text
attempt to fill it immediately.
```

Do not wait for every other court to finish.

If there are insufficient eligible players, leave the court available until enough become available.

---

# 26. Example — 3 Courts, 14 Players

Initial:

```text
12 playing
2 waiting
```

Court 1 finishes.

Now:

```text
2 existing waiting
+
4 players just off Court 1
=
6 available players
```

Courtly should normally build the next Court 1 match immediately.

The two players who were already waiting should receive strong priority.

The other two places may be selected from the four players who just finished, based on:

```text
skill cohesion
relationship cooldown
team balance
```

---

# 27. Rolling Evening Planner

Add a future-planning layer.

Do NOT create one immutable schedule for the whole evening.

Instead maintain a:

```text
ROLLING PLAN
```

for approximately:

```text
next 3 matchmaking rounds
```

The planner should estimate future allocations using the current:

```text
player pool
ratings
queue
match history
court count
pause state
```

---

# 28. Why Rolling Planning

Planning several rounds ahead can improve:

```text
fair sit-outs
partner variety
opponent variety
skill grouping
```

but Courtly must adapt when:

```text
a player joins
a player leaves
a player pauses
a player resumes
a court becomes unavailable
games finish in a different order
```

Therefore the plan is provisional.

---

# 29. Replanning

Recalculate future rounds when meaningful state changes occur, including:

```text
match completion
player joins
player leaves
player pauses
player resumes
court availability changes
manual organiser override
```

Only the currently assigned/playing matches are fixed.

Future planned matches may be changed.

---

# 30. Planner Must Not Delay Live Allocation

Planning should not make Courtly feel slow.

The next court allocation remains the priority.

If planning several future rounds is computationally expensive:

```text
allocate the immediate court first

then calculate future rounds afterwards
```

The live result path must remain fast.

---

# 31. Suggested Overall Allocation Pipeline

Refactor towards:

```text
AVAILABLE COURT
      ↓
WAITING PLAYERS ONLY
      ↓
DUE / FAIRNESS CHECK
      ↓
PEG QUEUE / ANCHOR
      ↓
CANDIDATE PLAYER GROUPS
      ↓
SKILL COHESION
      ↓
RELATIONSHIP COOLDOWN
      ↓
SELECT FOUR
      ↓
TEAM BALANCING
      ↓
REPEAT VALIDATION
      ↓
ASSIGN COURT
      ↓
UPDATE ROLLING PLAN
```

---

# 32. PAUSED UI Requirements

In the main live-session interface, show PAUSED players separately from NEXT UP.

Example:

```text
NEXT UP

1. Sarah        42
2. David        55
3. Michael      71


PAUSED

Rachel          63
James           48     PAUSED 3 ROUNDS
```

If paused for more than two rounds:

```text
James flashes/pulses red.
```

Use accessible animation.

Respect:

```css
prefers-reduced-motion
```

If reduced motion is enabled, use a strong static red state instead of flashing.

---

# 33. Important Visual Meaning

Red must mean:

```text
Player has voluntarily been paused for longer than expected.
```

It must NOT mean:

```text
Courtly has unfairly left them waiting.
```

WAITING fairness alerts and PAUSED warnings should be visually distinguishable.

---

# 34. Analytics

Track separately:

```text
normal_wait_time
voluntary_pause_time
normal_wait_rounds
voluntary_pause_rounds
skip_count
```

A voluntarily paused player must not make Courtly's fairness analytics appear worse.

---

# 35. New Matchmaking Metrics

Add simulator/report metrics for:

```text
maximum game-count difference

average wait rounds

maximum wait rounds

average wait time

maximum wait time

average skill spread

95th percentile skill spread

average team balance difference

consecutive same-court-companion repeats

consecutive teammate repeats

recent teammate repeats

recent opponent repeats

exact repeated matches

maximum skip count

number of DUE players

paused rounds per player
```

---

# 36. Critical Acceptance Tests

Add explicit tests for the following.

### Fair Rotation

```text
14 players
3 courts
20+ rounds
```

Target:

```text
max games - min games <= 1
```

for continuously available players.

---

### Paused Player

A PAUSED player:

```text
must never be allocated.
```

---

### Pause Does Not Count Against Fairness

A PAUSED player:

```text
does not increment skip_count
does not accumulate normal_wait_rounds
```

---

### Paused Warning

After:

```text
3 completed matchmaking rounds
```

a continuously paused player must enter the red-warning state when:

```text
paused_player_warning_rounds = 2
```

---

### Paused Player Never Auto-Resumes

The warning must never automatically return them to matchmaking.

---

### Previous Court Companions

Where sufficient players exist:

A player's next match should contain none of the same three players from their previous match.

---

### Too Few Players

When repetition is unavoidable:

Prefer:

```text
previous companion as opponent
```

over:

```text
previous teammate again
```

---

### Exact Repeat

Avoid exact consecutive 2v2 matches whenever any alternative exists.

---

### Continued Matchmaking

After many rounds:

Courtly must continue producing matches.

Older combinations should naturally become usable again.

---

### Free Court

If:

```text
court = AVAILABLE
waiting eligible players >= 4
```

Courtly should attempt immediate allocation even while another court remains PLAYING.

---

### Skill Cohesion

Given appropriate alternatives:

Do not group:

```text
90
85
35
30
```

when:

```text
90
85
82
78
```

can form one match and lower-rated players can form another.

---

### Team Balance

After selecting four players, choose the best valid balanced 2v2 arrangement.

---

# 37. Concurrency

Preserve all existing safety around simultaneous court completion.

The refactor must continue preventing:

```text
same player allocated twice

duplicate results

duplicate rating updates

duplicate matches
```

Use the existing transaction/locking strategy unless there is a documented reason to change it.

---

# 38. Matchmaking Explainability

Update the deterministic "Why this match?" data.

Example:

```text
Sarah and David were among the players most due to play.

Michael and Rachel were selected because their ratings create a cohesive four-player group.

Sarah and David had already sat out the previous allocation.

None of the four shared a court in their immediately previous game.

The selected teams differ by 1.5 rating points.
```

---

# 39. Explain Why Someone Was Skipped

Add diagnostic data such as:

```text
John was due to play but was skipped once because the available group would have produced a 48-point skill spread.

His skip count is now 1/1 and he will receive mandatory priority on the next suitable allocation.
```

This should be available to organisers/debugging.

---

# 40. Update Strategy Documentation

Update:

```text
MATCHMAKING_STRATEGY.md
```

to reflect the new implementation.

The document should clearly describe:

```text
rule precedence
fairness
virtual peg queue
DUE players
pause behaviour
pause warning behaviour
skill cohesion
team balancing
relationship cooldown
fallback behaviour
decaying recent history
rolling planner
configuration
simulation metrics
```

Do not leave the document describing removed behaviour such as the old strict rotation hold.

---

# 41. Algorithm Version

Increment the matchmaking algorithm version.

For example:

```text
courtly-v1.2
```

or the next appropriate version.

Every newly generated match must continue recording the algorithm version.

---

# 42. Preserve Configuration Architecture

Continue keeping tunable matchmaking values under:

```text
config/courtly.php
```

Do not hard-code weights throughout `MatchmakingService.php`.

---

# 43. Maintain Service Boundaries

Keep responsibilities clean.

For example:

```text
MatchmakingService
    player eligibility
    fairness
    group selection
    team selection
    court allocation

MatchResultService
    result transaction
    rating updates
    player state changes
    triggering next allocation

PlanningService
    rolling future-round planning
```

Create a separate `PlanningService` only if it improves clarity.

Do not over-engineer the application.

---

# 44. Performance

Typical Courtly sessions are approximately:

```text
8–40 players
1–8 courts
```

Maintain fast matchmaking.

Target:

```text
<100ms typical immediate allocation
```

where practical.

Use bounded search rather than uncontrolled combinatorial enumeration.

---

# 45. Simulation Before Completion

Before considering the refactor complete, run simulations including:

```text
8 players / 2 courts
10 players / 2 courts
12 players / 3 courts
14 players / 3 courts
20 players / 4 courts
30 players / 6 courts
```

Include scenarios where:

```text
one player pauses
multiple players pause
paused player stays paused >2 rounds
player resumes
players join mid-session
players leave mid-session
all players have similar ratings
ratings vary significantly
player numbers force repeated opponents
```

---

# 46. Report Results

For each important simulation report:

```text
games per player

maximum games difference

average waiting time

maximum waiting time

average skill spread

team balance

same-player immediate repetition

same-teammate immediate repetition

recent partner repetition

maximum skip count

paused players

number of exact repeat matches
```

---

# 47. Definition of Done

Do not consider this work complete until:

```text
MATCHMAKING_STRATEGY.md is updated

existing tests still pass or are intentionally updated

new tests cover the new requirements

paused players cannot be allocated

paused warning works after >2 rounds

fair waiting is validated

strict rotation hold is removed/replaced

same-player cooldown works

opponent-before-teammate fallback works

skill grouping works

team balancing works

old combinations become usable again

multiple courts remain concurrency-safe

rolling planner is implemented or cleanly staged if explicitly deferred

simulation results are documented
```

---

# 48. Implementation Process

Work in this order:

```text
1. Read the existing matchmaking strategy.

2. Inspect current implementation and tests.

3. Summarise how the current implementation differs from this specification.

4. Propose the minimal architecture changes.

5. Update the matchmaking specification.

6. Add/update tests BEFORE substantial implementation.

7. Refactor player eligibility and fairness.

8. Implement PAUSED handling and pause-round tracking.

9. Implement red pause warning state.

10. Replace strict rotation hold.

11. Implement relationship cooldown.

12. Implement opponent-before-teammate fallback.

13. Refine skill grouping.

14. Refine team balancing.

15. Implement history decay.

16. Implement or introduce rolling planning.

17. Run simulations.

18. Tune configuration only based on test/simulation evidence.

19. Run the full automated test suite.

20. Document the final behaviour.
```

Do not blindly preserve existing behaviour where it conflicts with this specification.

Do not rewrite unrelated parts of Courtly.

Prioritise deterministic, understandable and testable behaviour.

One thing I’d specifically have Copilot challenge during implementation is the current `winner_priority_bonus = 500`. Your existing priority formula gives only `100 × game deficit` and `2 × waiting minutes`, so that winner bonus can overpower the fairness signals quite dramatically.  For the model you now want, **waiting fairness should clearly win over winner status**.
