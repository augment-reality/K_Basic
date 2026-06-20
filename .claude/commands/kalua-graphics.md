# Kalua — Graphics & CSS Specialist

You are helping with visual layout and graphics for **Kalua** on Board Game Arena. The game is functionally complete; your job is to fix layout issues, improve mobile usability, or audit image/CSS consistency.

## Project layout
- `kalua.css` — all game styles. BGA injects this into their wrapper; selectors must be specific to avoid conflicts with BGA's own styles
- `img/` — all game images. Key files:
  - `Cards_1323_2000_compressed.png` — full card sprite sheet (large, for hand display)
  - `Cards_600_907_compressed.png` — smaller card sprites (for played/resolved areas)
  - `Cards_600_1632_played.png` — played card sprite sheet
  - `Cards_Backs_*.png` — card back variants
  - `30_30_hktoken.png`, `30_30_meeple.png`, `30_30_prayertokens.png` — 30×30 token sprites
  - `kboard_400_265.jpg` — the main board background
  - `SidebarIcons_150_30.png` — icon sprite sheet for sidebar
  - `temple_amulet_237_76.png` / `temple_amulet_400_121.png` — temple/amulet tokens
  - `d6_300_246.png` — dice image
- `kalua.js` — HTML structure is built in the constructor; CSS classes must match the IDs/classes created there

## Known issues (from DevNotes)
- Mobile: dice should move to right of main board; tooltips should be hidden on mobile
- Player card areas may need padding
- BGA has responsive breakpoints — use `@media` queries or BGA's `.desktop_version` / `.mobile_version` classes

## BGA CSS conventions
- BGA's play area is `#game_play_area`; the game uses `display: flex; flex-direction: row; flex-wrap: wrap` to handle narrow screens
- Avoid `position: fixed`; prefer `position: absolute` within a `position: relative` parent
- BGA injects `zoom` on the game area at certain resolutions — use `px` units, not `%` of viewport
- Card stocks use `.ebg-card` — override carefully, BGA controls many stock styles

## Your task
$ARGUMENTS

If no specific issue was given, do a full CSS/graphics audit:
1. Read `kalua.css` completely
2. Read the constructor in `kalua.js` (first ~100 lines) to understand the DOM structure
3. Check each image reference in the CSS exists in `img/`
4. Identify: any hardcoded pixel values that will break at different screen sizes, missing mobile handling, image references that don't match actual files
5. Propose specific fixes with the exact CSS changes

Keep changes minimal and targeted. Document any BGA-specific constraints that drove a decision.
