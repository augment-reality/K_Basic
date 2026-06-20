# Kalua — State Machine & Sequence Bug Investigator

You are helping debug the BGA (Board Game Arena) implementation of the card game **Kalua**. The game is largely complete; your job is to trace a specific state transition or sequence bug.

## Game overview (for context)
Kalua is a 2-4 player religion/resource card game. Each round:
1. **Phase 1** — Active player draws cards
2. **Phase 2** — All players take turns activating their leader (sacrifice, convert atheists/believers, give speech)
3. **Phase 3** — Players take turns playing cards (global disasters, local disasters, bonus cards); cards are resolved one at a time, with possible amulet choices, dice rolls, and discards
4. **Phase 4** — Convert/Pray: unhappy religions lose families; all religions pray (gain prayer tokens)

## Architecture
BGA uses a PHP server-side state machine + JS client. The pattern:
- `states.inc.php` — defines all game states, transitions, and allowed player actions
- `modules/php/constants.inc.php` — state ID constants (ST_*) and card effect data
- `modules/php/Game.php` — server-side: `st*` methods (state entry actions), `arg*` methods (pass args to client), `act*` methods (player action handlers), `notifyAllPlayers()` calls
- `kalua.js` — client-side: `onEnteringState()` sets up the UI for each state, `setupNotifications()` + `onNotif*` handlers animate game events

## Known sequence bugs (from DevNotes)
- Leader meeples not updating for everyone right away when sacrificed — likely a notification timing or missing `notifyAllPlayers` in `actSacrificeLeader`
- State transitions after card resolution may skip steps depending on card type

## Your task
$ARGUMENTS

If no specific bug was given, read `misc/DevNotes_and_ToDo` for the current bug list, then:
1. Read `states.inc.php` to understand the affected state(s) and their transitions
2. Grep `modules/php/Game.php` for the relevant `st*` / `act*` / `arg*` methods
3. Grep `kalua.js` for the corresponding `onEnteringState` case and `onNotif*` handlers
4. Trace the full flow from player action → PHP handler → notify → JS handler → UI update
5. Identify where the sequence breaks and propose a targeted fix

Focus on the minimum change that fixes the specific bug. Do not refactor surrounding code.
