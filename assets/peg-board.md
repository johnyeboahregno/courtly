# COURTLY — PEG-QUEUE MATCHMAKING REFACTOR

Refactor the Courtly matchmaking specification so the system behaves like an intelligent digital badminton peg board.

The key principle is:

```text
The queue decides who is due to play.
The matchmaking engine decides which due players form the best game.
The team-balancing engine decides how those four players should be paired.
```

This must replace any design where player selection is based only on a large opaque weighted score.

---

# 1. VIRTUAL PEG BOARD

Every active session must maintain a visible virtual peg queue.

Players may have one of these states:

```text
PLAYING
WAITING
RESTING
PAUSED
LEFT
```

`WAITING` players exist in an ordered queue.

Example:

```text
NEXT UP

1. John       82
2. Sarah      35
3. Michael    79
4. Jenny      76
5. David      32
6. Rachel     81
7. Sam        38
```

The position of the player in this queue represents how due they are for a game.

---

# 2. QUEUE IS THE PRIMARY FAIRNESS SYSTEM

Do not allow matchmaking skill optimisation to completely override queue order.

A player near the front of the queue is entitled to receive strong priority.

The system should never repeatedly skip a player simply because they are difficult to match by rating.

Courtly must remain understandable to players in the same way as a physical peg board.

---

# 3. ANCHOR PLAYER

When a court becomes available, Courtly should normally choose the first eligible player in the waiting queue as:

```text
ANCHOR PLAYER
```

The anchor is the player who is most due to play.

Example:

```text
Queue:

1. John       82
2. Sarah      35
3. Michael    79
4. Jenny      76
5. David      32
6. Rachel     81
```

John becomes the anchor.

Courtly then asks:

```text
Which three eligible waiting players would create
the best game with John?
```

---

# 4. ANCHOR MUST NORMALLY PLAY

The matchmaking engine should not simply discard the anchor because another set of four produces a mathematically better match.

The anchor is included unless:

```text
the player has requested REST
the player is PAUSED
the player has LEFT
there are insufficient eligible players
a hard session rule prevents selection
```

This gives the queue meaningful authority.

---

# 5. PICK ZONE

Do not search the entire waiting list equally for the anchor's three partners/opponents.

Create a configurable:

```text
PICK ZONE
```

The pick zone represents the first N eligible players after the anchor.

Example:

```text
PICK_ZONE_SIZE = 8
```

If the queue is:

```text
1. John
2. Sarah
3. Michael
4. Jenny
5. David
6. Rachel
7. Sam
8. Chloe
9. Ravi
10. Emma
```

John is the anchor.

Courtly may primarily consider positions:

```text
2–9
```

when selecting the other three players.

This preserves the traditional peg-board concept while still allowing intelligent match selection.

---

# 6. MAXIMUM SKIP RULE

Every waiting player must track:

```text
skip_count
```

A skip occurs when:

```text
the player was eligible and inside the active pick zone

a match was created

another player behind them in the queue was selected

they were not selected
```

Set a configurable default:

```text
MAX_SKIPS = 2
```

When:

```text
skip_count >= MAX_SKIPS
```

that player becomes:

```text
MANDATORY / DUE
```

for the next suitable allocation.

This prevents starvation.

---

# 7. DUE PLAYERS

Courtly should visually identify players whose skip threshold has been reached.

Example:

```text
NEXT UP

1. Sarah      35     DUE
2. David      32
3. Sam        38
4. Chloe      41
```

A DUE player must receive extremely strong priority.

The matchmaking engine should try to build the next suitable game around that player.

---

# 8. SKIP COUNT RESET

When a player is selected for a game:

```text
skip_count = 0
```

Do not carry historical skips indefinitely.

---

# 9. NORMAL QUEUE MOVEMENT

When a match finishes, the four players return to the waiting system.

Do not simply put all four at the exact same priority.

Apply the session's winner/loser rule.

Preferred order:

```text
waiting players who did not play
↓
returning winners
↓
returning losers
```

However, existing waiting players remain ahead of newly returning players unless fairness rules require otherwise.

---

# 10. WINNERS BEFORE LOSERS

The existing Courtly rule remains:

```text
winners are allocated before losers
```

Implement this through queue re-entry rather than letting winners jump the entire waiting queue.

Example after a completed game:

```text
Existing Waiting:
Sarah
David
Chloe

Winners:
John
Jenny

Losers:
Michael
Rachel
```

Queue becomes approximately:

```text
Sarah
David
Chloe
John
Jenny
Michael
Rachel
```

This preserves fairness while still giving winners preference over the losers from the same completed match.

---

# 11. WAITING PLAYERS MUST NOT LOSE POSITION

Players already waiting should normally retain priority over players coming off court.

This is critical.

Do not implement:

```text
winner finishes
↓
winner immediately jumps ahead of someone
who has been sitting out
```

The winner preference applies among comparable returning players, not above someone who has already waited.

---

# 12. PLAYER JOINING MID-SESSION

A new player joining an active session enters near the back of the waiting queue.

They should not immediately become the anchor.

Track:

```text
joined_at
queue_position
```

Normal waiting fairness will move them forward naturally.

---

# 13. PLAYER REST

Add explicit:

```text
RESTING
```

state.

A player may choose or be placed into RESTING when they do not want the next game.

A RESTING player:

```text
does not appear in active matchmaking
does not accumulate skips
does not lose their historical session statistics
```

When they return from rest, place them back into the waiting queue according to a clear configurable policy.

Default:

```text
return near the back of the waiting queue
```

Do not allow REST to become a mechanism for unfairly preserving first position.

---

# 14. ANCHOR + SKILL MATCHING

Once an anchor is chosen, Courtly should find three players who maximise game quality while respecting queue fairness.

For every candidate combination:

```text
Anchor + B + C + D
```

calculate:

```text
skill spread
queue displacement
skip implications
recent teammate penalties
recent opponent penalties
rating confidence
```

The anchor must be included.

---

# 15. QUEUE DISPLACEMENT PENALTY

Selecting a player far behind several other eligible players should carry a cost.

Example:

```text
Anchor = position 1

Selected:
2
3
8
```

Positions:

```text
4
5
6
7
```

were skipped.

This should be more expensive than selecting:

```text
2
3
4
```

unless player 8 produces a substantially better-quality match.

Introduce:

```text
QUEUE_DISPLACEMENT_WEIGHT
```

This allows Courtly to intelligently trade a small amount of queue order for significantly better games.

---

# 16. SKILL QUALITY VS QUEUE FAIRNESS

Courtly should avoid two extremes.

Do NOT blindly choose:

```text
positions 1,2,3,4
```

regardless of ability.

Do NOT choose:

```text
positions 1,9,10,11
```

every time because those ratings happen to match perfectly.

Instead optimise both.

Example queue:

```text
1. John       82
2. Sarah      35
3. Michael    79
4. Jenny      76
5. David      32
6. Rachel     81
7. Sam        38
```

John is anchor.

A naive queue system would choose:

```text
John 82
Sarah 35
Michael 79
Jenny 76
```

Courtly may prefer:

```text
John 82
Michael 79
Jenny 76
Rachel 81
```

because this produces a significantly better game.

Sarah has been skipped.

Her:

```text
skip_count += 1
```

and she remains near the front.

---

# 17. WHAT HAPPENS TO SARAH NEXT

After the previous selection, the queue may conceptually become:

```text
1. Sarah      35   skip 1
2. David      32
3. Sam        38
4. Chloe      41
...
```

Sarah becomes the next anchor.

Courtly should now attempt to build a suitable lower-rated match around her.

This is the key mechanism that allows both:

```text
good skill grouping
```

and:

```text
fair queue rotation
```

to coexist.

---

# 18. ANCHOR CANNOT BE REPEATEDLY SKIPPED

Normally the first eligible waiting player is the anchor.

Do not implement a system where the algorithm searches for a different anchor just because it creates a slightly better match.

Anchor replacement should only happen for explicit reasons such as:

```text
RESTING
PAUSED
LEFT
temporary organiser constraint
insufficient available compatible players under a hard rule
```

Any anchor bypass must be logged.

---

# 19. MULTIPLE COURTS

When multiple courts become free simultaneously, do not process each one independently if doing so would produce poor overall allocations.

Use the queue to determine the next several anchors.

Example:

```text
3 courts become available
```

Potential anchor candidates might initially be:

```text
queue position 1
queue position 2
queue position 3
```

However the allocation engine must prevent those anchors from being selected into the same match accidentally.

Instead solve the available courts as one allocation problem.

---

# 20. MULTI-COURT PEG ALLOCATION

For multiple available courts:

1. Determine how many player slots are available.

```text
available courts × 4
```

2. Identify the front portion of the queue.

3. Apply mandatory/DUE players first.

4. Construct candidate games that include appropriate anchor players.

5. Optimise the overall court set.

6. Minimise queue skipping.

7. Minimise skill spread.

8. Balance teams.

9. Avoid repetition.

No player can appear twice.

---

# 21. MANDATORY PLAYER PRIORITY

If one or more players have:

```text
skip_count >= MAX_SKIPS
```

they must be allocated before normal anchors where possible.

If there are several DUE players, use:

```text
queue position
then total wait time
```

to prioritise them.

---

# 22. MAXIMUM WAIT SAFETY

Also maintain:

```text
waiting_since
```

and configurable:

```text
MAX_WAIT_MINUTES
```

A player exceeding the maximum wait should become DUE even if their skip count is low.

Example:

```text
MAX_WAIT_MINUTES = 12
```

This acts as a second starvation-protection mechanism.

The exact value must be configurable.

---

# 23. QUEUE PRIORITY MODEL

Internally define a queue priority state rather than only one opaque numeric score.

Example:

```text
MANDATORY
ANCHOR
HIGH_PRIORITY
NORMAL
RETURNING_WINNER
RETURNING_LOSER
RESTING
```

Use explicit states where useful.

A small numeric score may still be used within a state, but the user-facing behaviour should remain understandable.

---

# 24. QUEUE DISPLAY

The tablet should clearly display the virtual peg board.

Example:

```text
NEXT UP

1  Sarah       35      DUE
2  David       32
3  Sam         38
4  Chloe       41
5  John        82
6  Jenny       76
```

Use subtle indicators for:

```text
DUE
RESTING
PROVISIONAL
```

Do not clutter the UI with every internal metric.

---

# 25. OPTIONAL PEG VISUAL METAPHOR

The UI may use a visual peg/chip/avatar metaphor.

For example each player can appear as:

```text
[ Sarah 35 ]
```

with draggable-looking styling.

However Courtly must not require manual dragging for normal operation.

The peg-board concept should be visible and intuitive without recreating a literal physical board unnecessarily.

---

# 26. MANUAL ORGANISER CONTROL

Provide optional organiser controls:

```text
Move player up
Move player down
Send to rest
Return from rest
Prioritise next game
```

Manual changes must be audited.

Normal operation should still be automatic.

---

# 27. TEMPORARY PRIORITY

Organiser may explicitly mark:

```text
PLAY NEXT
```

for a player.

This acts like a temporary mandatory flag.

Examples:

```text
player has to leave soon
player was accidentally skipped
organiser is correcting the queue
```

This override should expire after the player's next game.

---

# 28. TEAM CREATION AFTER GROUP SELECTION

After selecting the four players, generate:

```text
AB vs CD
AC vs BD
AD vs BC
```

Calculate:

```text
team average rating difference
recent teammate history
recent opponent history
exact previous matchup
```

Select the lowest-cost valid pairing.

---

# 29. SAME TEAMMATE RULE

Strongly avoid the same teammate in consecutive games.

This remains a high-weight soft constraint.

If avoidable, split consecutive teammate pairs.

---

# 30. EXACT MATCH RULE

The same complete matchup cannot occur in consecutive games.

Treat:

```text
Alice + Bob
vs
Charlie + David
```

and:

```text
Charlie + David
vs
Alice + Bob
```

as identical.

This is a hard rule except mathematically unavoidable emergency cases.

Any exception must be logged.

---

# 31. GROUP COST REFACTOR

Refactor the Courtly four-player group cost to something conceptually like:

```text
GroupCost =

skillSpread × SKILL_SPREAD_WEIGHT

+ queueDisplacement × QUEUE_DISPLACEMENT_WEIGHT

+ skipImpactPenalty

+ recentRelationshipPenalty

+ provisionalUncertaintyPenalty
```

The anchor itself is not optional.

---

# 32. TEAM PAIRING COST

After the group is selected:

```text
PairingCost =

teamBalanceDifference × BALANCE_WEIGHT

+ consecutiveTeammatePenalty

+ recentTeammatePenalty

+ opponentRepeatPenalty

+ exactMatchPenalty
```

---

# 33. SUGGESTED DEFAULT CONFIGURATION

Add:

```text
PICK_ZONE_SIZE = 8

MAX_SKIPS = 2

MAX_WAIT_MINUTES = 12

SKILL_SPREAD_WEIGHT = 5

QUEUE_DISPLACEMENT_WEIGHT = 3

BALANCE_WEIGHT = 10

REPEAT_TEAMMATE_PENALTY = 20

RECENT_TEAMMATE_PENALTY = 50

REPEAT_OPPONENT_PENALTY = 5

CONSECUTIVE_MATCHUP_PENALTY = 10000
```

These are starting values only.

All must be configurable.

---

# 34. PICK ZONE EXPANSION

If Courtly cannot find a reasonable valid match inside the initial pick zone:

```text
expand the pick zone gradually
```

Example:

```text
8
↓
10
↓
12
```

Do not immediately search every waiting player.

This preserves queue locality.

---

# 35. MATCH QUALITY THRESHOLD

Support a configurable threshold such as:

```text
MIN_ACCEPTABLE_MATCH_QUALITY
```

If no candidate inside the initial pick zone reaches the quality threshold, Courtly may expand the zone.

However fairness remains stronger than indefinitely waiting for an ideal match.

---

# 36. PROVISIONAL PLAYER HANDLING

Provisional players may have uncertain ratings.

When possible:

```text
mix a provisional player with established players
```

rather than placing four completely unknown players together.

But do not repeatedly skip provisional players.

The peg queue remains authoritative.

---

# 37. QUEUE RETURN AFTER MATCH

When four players finish:

```text
existing waiting players remain ahead
```

Then returning players re-enter.

Default ordering among those four:

```text
winners first
losers second
```

Within winners/losers, preserve a stable deterministic order.

Do not randomise unnecessarily.

---

# 38. EXAMPLE COMPLETE FLOW

Initial:

```text
Queue

1 Sarah 35
2 David 32
3 John 82
4 Sam 38
5 Michael 79
6 Jenny 76
7 Rachel 81
8 Chloe 41
```

Court becomes free.

Anchor:

```text
Sarah 35
```

Courtly searches the pick zone.

Good candidate:

```text
Sarah 35
David 32
Sam 38
Chloe 41
```

Teams might be:

```text
Sarah 35 + Chloe 41
vs
David 32 + Sam 38
```

These four leave the queue.

Remaining:

```text
John 82
Michael 79
Jenny 76
Rachel 81
```

Another court becomes free.

Anchor:

```text
John 82
```

Courtly creates:

```text
John 82
Michael 79
Jenny 76
Rachel 81
```

This demonstrates the intended behaviour naturally without fixed skill divisions.

---

# 39. MATCH FINISH EXAMPLE

Suppose:

```text
Sarah + Chloe
beat
David + Sam
```

Existing queue:

```text
John
Michael
Jenny
Rachel
Emma
Ravi
```

Returning players should re-enter after existing waiting players:

```text
John
Michael
Jenny
Rachel
Emma
Ravi
Sarah
Chloe
David
Sam
```

because:

```text
Sarah + Chloe = winners
David + Sam = losers
```

---

# 40. DATABASE CHANGES

Update `session_players` to include:

```text
queue_position
skip_count
waiting_since
last_selected_at
resting_since nullable
manual_priority boolean/default false
manual_priority_expires_at nullable
```

Consider whether `queue_position` should be persisted directly or derived through an ordered queue field.

The design must support transaction-safe queue updates.

---

# 41. QUEUE EVENT HISTORY

Add optional structured audit data for queue behaviour.

Suggested:

```text
queue_events

id
session_id
player_id
type
old_position nullable
new_position nullable
reason
match_id nullable
created_at
```

Types:

```text
JOINED_QUEUE
SELECTED
SKIPPED
RETURNED_WINNER
RETURNED_LOSER
RESTED
RESUMED
MANUAL_PRIORITY
MANUAL_MOVE
LEFT
```

This is valuable for debugging fairness.

---

# 42. MATCHMAKING LOG ENHANCEMENT

Add to matchmaking logs:

```text
anchor_player_id

anchor_queue_position

pick_zone_size

selected_queue_positions

skipped_player_ids

queue_displacement_cost

due_player_count

expanded_pick_zone boolean
```

This makes the algorithm explainable.

---

# 43. WHY THIS MATCH EXPLANATION

Example deterministic explanation:

```text
Sarah was first in the queue and was selected as the anchor.

David, Sam and Chloe were chosen from the next eligible players because their ratings are close to Sarah's.

The four players have a skill spread of 9.

No consecutive teammate combination is repeated.

The proposed teams differ by 1 rating point.
```

This should work without AI.

---

# 44. WHY WAS I SKIPPED?

Add an organiser-visible explanation for skipped players.

Example:

```text
John was temporarily skipped because the first available court contained an anchor rated 35, while John is rated 82.

John remains near the front of the queue and his skip count is now 1 of 2.
```

This is important for trust.

---

# 45. FAIRNESS ANALYTICS

Add:

```text
average skips per player

maximum skips

number of players reaching MAX_SKIPS

average queue displacement

maximum waiting time

percentage of games using the first queue anchor
```

Also report:

```text
number of anchor bypasses
```

Target:

```text
anchor bypasses should be very low
```

---

# 46. PEG SYSTEM SIMULATOR METRICS

The simulator must now also report:

```text
average queue position when selected

maximum skip count

average skip count

players skipped twice consecutively

maximum wait

anchor selection rate

pick-zone expansion rate
```

---

# 47. FAIRNESS ACCEPTANCE TEST

Given a stable session:

```text
14 players
3 courts
20 rounds
```

Courtly should target:

```text
max games difference <= 1

no player exceeds MAX_SKIPS without being selected

no player exceeds MAX_WAIT_MINUTES without becoming DUE
```

---

# 48. SKILL COHESION ACCEPTANCE TEST

Queue:

```text
1. 82
2. 35
3. 79
4. 76
5. 32
6. 81
7. 38
8. 41
```

With the 82-rated player as anchor, Courtly should be allowed to select:

```text
82
79
76
81
```

if this is substantially better than selecting positions 1–4.

Players passed over must accumulate skip state correctly.

---

# 49. STARVATION ACCEPTANCE TEST

Create a player whose rating is far outside the rest of the session.

Verify that:

```text
the player can initially be skipped for match quality

but cannot be skipped indefinitely
```

Once:

```text
MAX_SKIPS
```

or:

```text
MAX_WAIT_MINUTES
```

is reached, the system must prioritise their next suitable match.

---

# 50. MULTI-COURT ACCEPTANCE TEST

For several simultaneously available courts:

Verify:

```text
front-of-queue players receive strong priority

mandatory players are allocated first

no player appears twice

skill cohesion remains good

queue displacement is bounded

team balance remains good
```

---

# 51. UI REFACTOR

The main live session screen should now visually emphasise two things:

```text
COURTS
```

and:

```text
PEG QUEUE / NEXT UP
```

Example:

```text
COURTLY — Sunday Social

COURT 1
Alice + Bob
vs
James + Rachel

COURT 2
...

--------------------------------

NEXT UP

1  Sarah       35     DUE
2  David       32
3  Sam         38
4  Chloe       41
5  Michael     79
6  Jenny       76
```

---

# 52. CORE PRODUCT METAPHOR

Courtly should be positioned internally and potentially publicly as:

```text
An intelligent digital badminton peg board.
```

Traditional peg-board fairness is preserved.

Courtly adds:

```text
player ratings

automatic skill grouping

balanced team creation

match history

repeat prevention

digital registration

automatic results

analytics

realtime updates

future AI insights
```

---

# 53. UPDATED MATCHMAKING PRIORITY

Courtly's matchmaking philosophy becomes:

```text
1. Respect the peg queue.

2. Make the first eligible / mandatory player the anchor.

3. Search nearby eligible players.

4. Prefer similar skill.

5. Minimise queue displacement.

6. Prevent starvation through skip limits.

7. Balance the selected four into teams.

8. Avoid repeated teammates and opponents.

9. Record why the decision was made.
```

---

# 54. IMPORTANT PRINCIPLE

Courtly must not feel like a mysterious optimisation engine.

A player should be able to look at the queue and broadly understand:

```text
I'm near the front.

I'm likely to play soon.

If I get skipped for a better-quality match,
I keep my place.

I cannot keep getting skipped forever.
```

That predictability is a core product requirement.

---

# 55. FINAL MATCHMAKING MODEL

The final Courtly allocation pipeline should be:

```text
WAITING PEG QUEUE
        ↓
MANDATORY / DUE CHECK
        ↓
ANCHOR PLAYER
        ↓
PICK ZONE
        ↓
SKILL-COHESIVE GROUP OF FOUR
        ↓
QUEUE FAIRNESS CHECK
        ↓
MAX-SKIP / MAX-WAIT CHECK
        ↓
BEST TEAM SPLIT
        ↓
REPEAT CHECK
        ↓
COURT ASSIGNMENT
        ↓
PLAY
        ↓
WIN RESULT
        ↓
RETURN TO QUEUE
        ↓
WINNERS BEFORE LOSERS
```

This is the core Courtly matchmaking model and should supersede earlier purely weighted player-selection approaches where they conflict.
