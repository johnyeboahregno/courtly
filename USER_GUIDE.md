# Courtly — User Guide

Courtly is a badminton session manager that handles courts, players, and matchmaking for you. You create a session, add players, and Courtly automatically decides who plays next, forms balanced doubles matches, and updates player ratings.

> **Your account is your own space.** Every session, player, and rating you see belongs to your account. Other users cannot see or change your data.

---

## 1. Getting Started

1. Open Courtly and **log in** (or register with email/password, or use Google/Facebook).
2. You land on the **Dashboard** — your home screen.

---

## 2. The Dashboard

The dashboard lists:

- **Current sessions** — upcoming, active, or paused sessions.
- **Past sessions** — finished sessions, collapsed under a "Past Sessions" toggle.

From here you can:

- **Create a session** — enter a name and the number of courts, then click *Create Session*.
- **Open a session** — click its card to enter the live view.
- **Delete a session** — click the ✕ next to a session.
- **Switch theme** — light (☀), dark (☾), or system (◐).
- **Log out** — top-right button.

---

## 3. Running a Session

A session moves through a simple lifecycle:

```
UPCOMING → ACTIVE ⇄ PAUSED → FINISHED
```

1. **Create** the session (pick a name and number of courts).
2. **Add players** (see below) — before or after starting.
3. **Start** the session — matchmaking begins filling courts.
4. **Record results** — tap WIN on the winning side of each match.
5. **Pause / Resume** as needed (e.g. for a break).
6. **Finish** the session when done — a summary becomes available.

---

## 4. Adding & Managing Players

Open the **+ PLAYERS** dialog from the live view.

### Add a new player
Type a name in the input and press **Add** (or Enter). Courtly creates the player and adds them to the waiting queue.

### Add an existing player
The dialog shows your roster. Tap a name to add them. As you type, suggestions appear.

### Manage a player
In the waiting list, each player card has a **⏸ / ▶** button:

- **⏸ Pause** — take a player out of the rotation (they stay in the session but won't be allocated to courts).
- **▶ Resume** — put them back into the rotation.
- **Remove** — take them out of the session.
- **Delete permanently** — remove the player from your roster entirely (cannot be undone).

---

## 5. The Live View

- **Courts grid** — each court shows the current 2v2 match with player names, ratings, and a **WIN** button on each side.
- **NEXT UP** — the waiting queue, sorted by rotation priority. Paused players appear greyed out.
- **Header** — session name, player count, court count, elapsed timer, status badge, and connection indicator.
- **Footer controls** — Start / Pause / Resume / Finish, + Players, and Manage.

---

## 6. Recording a Match Result

Tap the **WIN** button on the winning team's side. Courtly immediately:

1. Records the result and updates both teams' ratings.
2. Moves the four players back to the waiting list.
3. Fills the court with the next best match (no empty courts while 4+ players wait).

A green "connected" dot means live data is flowing; the view polls every few seconds automatically.

---

## 7. Ratings

Each player has a **0–100 rating** (Elo-derived):

- **Provisional** — new players with fewer than 3 rated games. Their rating moves quickly.
- **Established** — after 3 rated games; ratings move more slowly.
- **Win streaks** earn a small K-factor bonus, so consistent winners rise a little faster.

Ratings update after every completed match and are visible on the player cards and in the Players dialog.

---

## 8. How Matchmaking Picks Who Plays

Courtly balances **fairness** and **competitiveness**:

- **Fewest games + longest wait** play first.
- Players who **sat out** the last round get priority.
- Players with **similar ratings** are grouped together, then split into the most **balanced teams**.
- The same four players won't immediately repeat, and the exact same 2v2 matchup is avoided.

---

## 9. Session Summary

After you **finish** a session, the summary shows:

- Total matches played
- Average and peak skill spread
- Average team balance and match quality
- Per-player stats (games, wins, losses, average wait time)

---

## 10. Troubleshooting

| Problem | What to do |
|---------|------------|
| Can't see your sessions/players | Make sure you're logged in — data is scoped to your account |
| "Server unreachable" banner | Check your connection; Courtly keeps retrying and syncs when back online |
| WIN button didn't seem to work | Wait a moment — the next match is filled from the server response, and the view re-syncs |
| 405 Method Not Allowed after an update | Ask your administrator to clear the stale route cache (`bootstrap/cache/routes-v7.php`) |
| Forgot password | Use the password-reset link on the login page |

---

*For developers: see `README.md` (setup) and `CLAUDE.md` (full architecture reference).*
