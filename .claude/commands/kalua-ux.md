# Kalua — UX & Notification Auditor

You are helping polish the player-facing experience of **Kalua** on Board Game Arena. The game is functionally complete; your job is to audit, consolidate, or tune notifications and UI messages.

## How BGA notifications work
- **Server side** (`modules/php/Game.php`): `$this->notifyAllPlayers(type, message, args)` or `$this->notifyPlayer(player_id, type, message, args)` queues a notification
- **Client side** (`kalua.js`): `setupNotifications()` registers handlers; each handler is a method named `onNotif_<type>(notif)` that animates the result
- Notifications play sequentially with optional delays. Redundant or consecutive notifications feel spammy.

## Current UX issues (from DevNotes)
- "Temple has been resolved" notification is superfluous — not all card resolutions need an ending notification
- Need longer delay after each card resolution, and between each HK token move (`hkTokenMoveDelay`, `hkTokenTransferDelay` in kalua.js constructor)
- Should merge: "Round has ended. Unhappy religions lose families and all religions pray." + "Card resolution complete. Proceeding to convert/pray phase" into one message
- Player card areas may need padding
- Review and consolidate all messages for clarity

## Your task
$ARGUMENTS

If no specific issue was given, do a full notification audit:
1. Grep `modules/php/Game.php` for all `notifyAllPlayers` and `notifyPlayer` calls — list each notification type, its message string, and where it's sent
2. Read `kalua.js` `setupNotifications()` and all `onNotif_*` handlers to understand what each notification does on the client
3. For each notification: assess if it's needed, if it duplicates another, and if the message text is clear to a player unfamiliar with the code
4. Propose specific consolidations or cuts — include the exact message text rewrites and which `notifyAllPlayers` calls to merge or remove
5. Note any timing adjustments needed (delay values in kalua.js constructor)

Do not touch game logic. Only change notification calls, message strings, and timing constants.
