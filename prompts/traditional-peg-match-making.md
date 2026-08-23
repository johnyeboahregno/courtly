# Courtly — Traditional Peg System Refactor

You are working on an existing application called **Courtly**.

Courtly is a social badminton application that manages:

* players
* courts
* sessions
* ratings
* waiting players
* match results
* automatic doubles matchmaking

The current matchmaking implementation contains weighted rotation logic, skill-spread optimisation, repeat penalties and winner-related priority.

Refactor the matchmaking system so that Courtly follows a **traditional badminton peg-board model**.

Do not rewrite unrelated parts of the application.

Before changing code, read:

```text
MATCHMAKING_STRATEGY.md
```

Then inspect:

```text
app/Services/MatchmakingService.php
app/Services/MatchResultService.php
config/courtly.php
```

and all existing matchmaking tests.

---

# 1. Core Philosophy

The traditional peg queue becomes the main source of truth for fairness.

The system should follow this principle:

```text
The queue decides who is due to play.

The first eligible player in the queue is the picker / anchor.

Courtly chooses three suitable players from the nearby queue.

Courtly then balances those four players into two teams.
```

Do not use a large opaque weighted rotation score to determine who gets to play next.

The waiting queue itself should provide fairness.

---

# 2. Player States

Support these player session states:

```text
WAITING
PLAYING
PAUSED
LEFT
```

Only `WAITING` players participate in the active peg queue.

`PLAYING`, `PAUSED`, and `LEFT` players must never be selected.

---

# 3. Waiting Peg Queue

Every active session has one ordered queue of waiting players.

Example:

```text
NEXT UP

1. Sarah      34
2. David      31
3. John       81
4. Sam        37
5. Chloe      40
6. Michael    79
7. Rachel     83
8. Emma       36
```

The position in this queue represents how due a player is for a game.

The first eligible player is always considered first.

---

# 4. Anchor / Picker

When a court becomes available:

```text
anchor = first WAITING player in queue
```

The anchor must normally be included in the next match.

Do not skip the anchor merely because another group of four produces a better theoretical match.

The anchor may only be skipped if:

```text
player is PAUSED
player has LEFT
player is already PLAYING
fewer than 4 eligible players exist
a hard organiser constraint prevents selection
```

The queue must remain meaningful.

---

# 5. Pick Zone

Courtly should choose the other three players from a limited section of the queue behind the anchor.

Add:

```php
'pick_zone_size' => 8,
```

as the initial default.

For example:

```text
Queue:

1. Sarah      34  <- anchor
2. David      31
3. John       81
4. Sam        37
5. Chloe      40
6. Michael    79
7. Rachel     83
8. Emma       36
9. Ravi       74
```

Courtly should primarily consider players in the next 8 eligible queue positions.

This preserves the traditional peg-board behaviour.

---

# 6. Skill-Aware Picking

Within the pick zone, Courtly should choose three players who create a good-quality badminton game with the anchor.

Player ratings range:

```text
0–100
```

Do not create rigid permanent divisions.

However, Courtly should naturally prefer:

```text
strong players with strong players
medium players with medium players
beginners with beginners
```

when enough suitable players are nearby in the queue.

---

# 7. Skill Spread

For every possible group:

```text
anchor + B + C + D
```

calculate:

```text
skillSpread =
highest rating - lowest rating
```

Lower is better.

Example:

```text
34
31
37
40

skillSpread = 9
```

This is a strong group.

Compare:

```text
34
31
81
79

skillSpread = 50
```

This should generally be avoided when better nearby alternatives exist.

---

# 8. Queue Order Still Matters

Do not ignore queue positions in pursuit of perfect skill matching.

The system should prefer nearby pegs.

Selecting positions:

```text
1, 2, 4, 5
```

should normally be preferable to:

```text
1, 7, 8, 9
```

unless the latter produces a substantially better match.

Introduce a small queue displacement penalty if useful.

Example:

```php
'queue_displacement_weight' => 3,
```

Do not make this large enough to override obviously poor skill matches.

---

# 9. Anchor Example

Queue:

```text
1. Sarah       34
2. David       31
3. John        81
4. Sam         37
5. Chloe       40
6. Michael     79
7. Rachel      83
8. Emma        36
```

Sarah is the anchor.

Do not blindly choose:

```text
Sarah 34
David 31
John 81
Sam 37
```

Courtly should instead strongly prefer:

```text
Sarah 34
David 31
Sam 37
Chloe 40
```

because they are similar in ability.

The queue remains fair because Sarah, the first peg, still gets her game.

---

# 10. Balance the Selected Four

Once four players have been selected:

```text
A
B
C
D
```

evaluate all three possible doubles team arrangements:

```text
AB vs CD
AC vs BD
AD vs BC
```

For each team:

```text
TeamStrength =
average(player ratings)
```

Calculate:

```text
balanceDifference =
abs(team1Strength - team2Strength)
```

Choose the valid arrangement with the lowest difference.

---

# 11. Example Team Balance

Selected players:

```text
40
37
34
31
```

Courtly should evaluate all splits and likely choose:

```text
40 + 31
vs
37 + 34
```

because:

```text
71 vs 71
```

is perfectly balanced.

---

# 12. Avoid Previous Match Companions

A player should ideally not share their immediately following match with any of the same three players from their previous court.

For each player derive:

```text
last_match_player_ids
```

Example:

If Sarah's previous game contained:

```text
David
John
Rachel
```

prefer selecting three different players for Sarah's next match.

This is a strong preference, not a permanent prohibition.

---

# 13. Too Few Players — Relax Gracefully

When player numbers make fresh opponents impossible, use this preference:

```text
new player
    ↓
previous opponent
    ↓
previous teammate
```

If repetition is unavoidable, prefer placing someone from the previous match on the opposite side.

---

# 14. Previous Teammate Rule

Strongly avoid the same teammate in consecutive games.

Example:

Previous:

```text
Sarah + David
vs
John + Rachel
```

If these four must share a court again, prefer:

```text
Sarah + John
vs
David + Rachel
```

or:

```text
Sarah + Rachel
vs
David + John
```

Do not repeat:

```text
Sarah + David
```

unless all other valid team splits are impossible.

---

# 15. Exact Match Repeat

Strongly avoid repeating the exact same:

```text
A + B
vs
C + D
```

in consecutive games.

Swapping court sides still counts as the same matchup.

If player numbers make it mathematically unavoidable, use the least-bad alternative rather than leaving the court empty.

Log when this fallback occurs.

---

# 16. Recent History

Use a short decaying relationship history.

Suggested:

```text
previous match:
very strong avoidance

2–3 games ago:
moderate avoidance

4–5 games ago:
small avoidance

older:
no meaningful penalty
```

Do not permanently ban old combinations.

Courtly must continue producing matches indefinitely.

---

# 17. Returning Players Go to Back of Queue

When a match finishes, all four players return behind everyone who was already waiting.

Example:

Existing queue:

```text
Emma
Ravi
Chloe
Michael
```

Completed match:

```text
Sarah + David
vs
John + Rachel
```

All four returning players go behind:

```text
Emma
Ravi
Chloe
Michael
...
```

They must not jump ahead of players who were already waiting.

This is a fundamental peg-system fairness rule.

---

# 18. Winners Before Losers

Preserve the Courtly preference:

```text
winners before losers
```

but apply it only when the four completed-match players return to the back of the queue.

Example:

Existing waiting players:

```text
Emma
Ravi
Chloe
```

Winners:

```text
Sarah
David
```

Losers:

```text
John
Rachel
```

New queue:

```text
Emma
Ravi
Chloe
Sarah
David
John
Rachel
```

Winners do NOT jump in front of existing waiting players.

---

# 19. Stable Order Within Winners / Losers

Use deterministic ordering.

For example preserve their previous team/peg order.

Do not randomise unless required.

---

# 20. Paused Players

Players may voluntarily pause themselves.

When:

```text
status = PAUSED
```

they must:

```text
leave the active peg queue
not be selected
not accumulate normal waiting time
not count as being unfairly skipped
not automatically return to matchmaking
```

Track:

```text
paused_since
paused_rounds
```

---

# 21. Pause Warning

If a player remains paused for more than:

```text
2 completed matchmaking rounds
```

show a strong organiser warning.

The paused player should:

```text
flash / pulse red
```

on the live tablet interface.

Example:

```text
PAUSED

Rachel       63

James        48      PAUSED 3 ROUNDS   RED
```

This is only a reminder.

Do not automatically resume the player.

---

# 22. Pause Interaction

Tapping a long-paused player should show:

```text
James has been paused for 3 rounds.

[ RETURN TO QUEUE ]

[ KEEP PAUSED ]
```

If `KEEP PAUSED` is selected, preserve the paused state.

---

# 23. Reduced Motion

Respect:

```css
prefers-reduced-motion
```

If enabled, do not flash.

Use a strong static red indicator instead.

---

# 24. Returning From Pause

When a player resumes, place them:

```text
at the back of the current WAITING queue
```

by default.

Do not preserve their old front-of-queue position.

This prevents strategic pausing from being used to hold a queue position.

---

# 25. Player Joining Mid-Session

A player joining an active session enters:

```text
at the back of the WAITING queue
```

They do not jump ahead of players who are already waiting.

---

# 26. Player Leaving

When:

```text
status = LEFT
```

remove them from the active queue immediately.

They must never be selected.

---

# 27. Court Becomes Free

When a court becomes `AVAILABLE`:

If:

```text
WAITING players >= 4
```

attempt immediate allocation.

Do NOT wait for every other court to finish.

This replaces the old strict rotation-hold behaviour.

---

# 28. Example — 14 Players / 3 Courts

Initial:

```text
12 players playing
2 waiting
```

Court 1 finishes.

The four Court 1 players return to the back of the waiting queue.

There are now:

```text
6 WAITING players
```

The first existing waiting player becomes anchor.

Courtly chooses three suitable players from the queue and starts the next Court 1 game immediately.

Do not wait for Courts 2 and 3 to finish.

---

# 29. Traditional Peg Fairness

Do not require complex skip counters for normal fairness.

The queue itself provides the guarantee:

```text
if you keep waiting,
you move towards the front,
and eventually become the anchor.
```

This should be the primary fairness mechanism.

---

# 30. No Opaque Rotation Score

Remove or significantly simplify existing weighted rotation logic such as:

```text
games fairness score
winner bonus
sit-out bonus
waiting score
```

if these conflict with queue order.

Queue position should replace most of this behaviour.

A small score may still help choose the three players behind the anchor, but it must not determine whether the anchor plays.

---

# 31. Winner Priority Refactor

Remove any large winner bonus from the main selection priority.

Winner status only determines return order among the players who just completed the same game.

It does NOT decide who plays before existing waiting players.

---

# 32. Suggested Match Selection Cost

For candidate companions B, C, D:

```text
GroupCost =

skillSpread × SKILL_SPREAD_WEIGHT

+ queueDisplacement × QUEUE_DISPLACEMENT_WEIGHT

+ previousMatchCompanionPenalty

+ recentRelationshipPenalty
```

The anchor is fixed.

Suggested initial values:

```php
'pick_zone_size' => 8,

'skill_spread_weight' => 8,

'queue_displacement_weight' => 3,

'previous_match_companion_penalty' => 5000,

'recent_teammate_penalty' => 100,

'recent_opponent_penalty' => 40,
```

Tune through simulation.

---

# 33. Suggested Team Pairing Cost

After selecting four:

```text
PairingCost =

balanceDifference × BALANCE_WEIGHT

+ consecutiveTeammatePenalty

+ recentTeammatePenalty

+ recentOpponentPenalty

+ exactMatchPenalty
```

Suggested initial values:

```php
'balance_weight' => 15,

'consecutive_teammate_penalty' => 10000,

'recent_teammate_penalty' => 100,

'recent_opponent_penalty' => 40,

'exact_match_penalty' => 100000,
```

---

# 34. Expand Pick Zone Only When Necessary

If no reasonable group exists within the initial pick zone:

```text
8 players
```

expand gradually:

```text
8
↓
10
↓
12
```

Do not search the entire waiting queue immediately.

This preserves the traditional peg-board feel.

---

# 35. Full Allocation Flow

The new matchmaking process should be:

```text
COURT AVAILABLE
      ↓
GET WAITING PEG QUEUE
      ↓
FIRST PLAYER = ANCHOR
      ↓
GET PICK ZONE
      ↓
FIND 3 SUITABLE PLAYERS
      ↓
MINIMISE SKILL SPREAD
      ↓
MINIMISE QUEUE DISPLACEMENT
      ↓
AVOID RECENT COURT COMPANIONS
      ↓
SELECT FOUR
      ↓
TEST 3 TEAM SPLITS
      ↓
BALANCE TEAMS
      ↓
AVOID SAME TEAMMATES
      ↓
ASSIGN COURT
```

---

# 36. Completed Match Flow

When a result is entered:

```text
WIN tapped
      ↓
record result
      ↓
update ratings
      ↓
mark court AVAILABLE
      ↓
remove four from PLAYING
      ↓
append winners to back of queue
      ↓
append losers behind winners
      ↓
attempt next allocation
```

All existing transaction and concurrency safety must remain intact.

---

# 37. Queue UI

The main tablet screen should make the peg system visually obvious.

Example:

```text
COURTLY
Sunday Social

COURT 1
...

COURT 2
...

COURT 3
...

----------------------------

NEXT UP

1. Sarah        34
2. David        31
3. John         81
4. Sam          37
5. Chloe        40
6. Michael      79

----------------------------

PAUSED

Rachel          63
James           48    PAUSED 3 ROUNDS
```

The first peg should be visually clear as:

```text
NEXT / PICKER
```

without cluttering the screen.

---

# 38. Why This Match

Courtly should generate a deterministic explanation.

Example:

```text
Sarah was first in the peg queue, so she was selected as the anchor.

David, Sam and Chloe were selected from the pick zone because their ratings were closest to Sarah's.

The four players have a skill spread of 9.

The teams were arranged to produce equal combined strength.

No consecutive teammate pairing was repeated.
```

No AI should be required for this explanation.

---

# 39. Database / State Changes

Review `session_players`.

Add or retain fields needed for:

```text
queue_order
status
waiting_since
paused_since
paused_rounds
last_played_at
```

Prefer a robust sortable queue key rather than continuously renumbering every player after each operation if possible.

Document the chosen approach.

---

# 40. Queue Event Audit

If useful, add:

```text
queue_events
```

with:

```text
player_id
session_id
event_type
old_position
new_position
reason
match_id
created_at
```

Potential events:

```text
JOINED_QUEUE
SELECTED
RETURNED_WINNER
RETURNED_LOSER
PAUSED
RESUMED
LEFT
MANUAL_MOVE
```

This is optional but useful for diagnosing fairness.

---

# 41. Manual Organiser Controls

Support:

```text
Move player up
Move player down
Pause
Resume
Mark leaving
```

Manual queue changes should be explicit and auditable.

Normal operation remains automatic.

---

# 42. Matchmaking Algorithm Version

Increment the algorithm version.

For example:

```text
courtly-peg-v1.0
```

Every newly created match should record the version.

---

# 43. Update MATCHMAKING_STRATEGY.md

Rewrite the strategy document so it clearly reflects the traditional peg system.

It should include:

```text
peg queue
anchor/picker
pick zone
skill-aware selection
team balancing
winner/loser queue return
pause behaviour
pause warning
relationship repetition
fallback rules
court availability behaviour
configuration
examples
tests
```

Remove documentation of obsolete weighted-rotation and strict-rotation-hold behaviour.

---

# 44. Required Tests

Add automated tests covering:

### Queue Order

First WAITING player becomes anchor.

### Existing Waiters

Players already waiting remain ahead of players returning from court.

### Winner / Loser Return

Returning winners go behind existing waiters but ahead of returning losers.

### Pause

PAUSED player is never selected.

### Pause Warning

Player paused for more than 2 matchmaking rounds receives warning state.

### Resume

Resumed player joins the back of the queue.

### Mid-session Join

New player joins the back.

### Skill Cohesion

Anchor gets similarly rated players when available within pick zone.

### Queue Locality

Algorithm prefers suitable nearby pegs over players far down the queue when quality difference is small.

### Team Balance

Selected four are split into the best valid teams.

### Consecutive Teammates

Same teammate is strongly avoided in the immediately following game.

### Previous Opponent

When repetition is unavoidable, previous companion is preferably placed on opposite side.

### Exact Repeat

Exact consecutive match is avoided if any valid alternative exists.

### Court Throughput

Available court fills immediately when at least four WAITING players exist.

### Continuous Play

After many rounds, old combinations become usable again and matchmaking continues.

---

# 45. Simulation

Run simulations for:

```text
8 players / 2 courts
10 players / 2 courts
12 players / 3 courts
14 players / 3 courts
20 players / 4 courts
30 players / 6 courts
```

Include:

```text
mixed ratings
all similar ratings
one unusually strong player
one unusually weak player
paused players
join mid-session
leave mid-session
```

---

# 46. Simulation Metrics

Report:

```text
games per player

queue position when selected

average wait time

maximum wait time

average skill spread

95th percentile skill spread

average team balance difference

same teammate consecutive repeats

same court-companion repeats

exact match repeats

paused rounds
```

The peg system should naturally keep games played reasonably even without a complex rotation score.

---

# 47. Definition of Done

Do not consider the refactor complete until:

```text
traditional peg queue is authoritative

first eligible peg becomes anchor

pick zone works

skill-aware selection works

team balancing works

existing waiting players stay ahead of returning players

winners return before losers

PAUSED players are excluded

>2 round pause warning works

same teammate repetition is avoided

previous companions become opponents when repetition is unavoidable

exact repeated matches are avoided

old combinations become available again

free courts fill without waiting for every court

concurrency remains safe

MATCHMAKING_STRATEGY.md is updated

tests pass

simulation results are documented
```

---

# 48. Implementation Order

Work in this order:

```text
1. Read existing strategy and implementation.

2. Summarise current behaviour that conflicts with traditional peg rules.

3. Update MATCHMAKING_STRATEGY.md first.

4. Add/update matchmaking tests.

5. Implement ordered peg queue.

6. Implement anchor selection.

7. Implement pick zone.

8. Implement skill-aware companion selection.

9. Implement balanced team splitting.

10. Implement winner/loser queue return.

11. Implement pause/resume behaviour.

12. Implement >2 round paused warning.

13. Implement recent-player repeat logic.

14. Remove old strict rotation hold.

15. Remove/reduce obsolete weighted rotation logic.

16. Run simulations.

17. Tune configuration from evidence.

18. Run full test suite.

19. Document final behaviour.
```

Do not overengineer the solution.

The objective is to make Courtly behave like a traditional badminton peg board with intelligent automatic picking and team balancing.
