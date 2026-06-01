---
name: pongtable
description: Beerpong tournament management with broadcast-grade liveness.
colors:
  stage-bg: "oklch(11% 0.011 80)"
  stage-surface: "oklch(16% 0.013 80)"
  stage-surface-2: "oklch(22% 0.015 80)"
  stage-line: "oklch(32% 0.017 80)"
  stage-line-strong: "oklch(48% 0.020 80)"
  stage-text: "oklch(96% 0.011 80)"
  stage-text-muted: "oklch(78% 0.022 80)"
  stage-text-dim: "oklch(66% 0.024 80)"
  red-corner: "oklch(62% 0.220 26)"
  red-corner-bright: "oklch(72% 0.210 28)"
  red-corner-soft: "oklch(30% 0.080 26)"
  blue-corner: "oklch(62% 0.180 240)"
  blue-corner-bright: "oklch(72% 0.170 240)"
  blue-corner-soft: "oklch(30% 0.075 240)"
  trophy-gold: "oklch(82% 0.160 78)"
  trophy-gold-deep: "oklch(72% 0.180 75)"
  trophy-gold-soft: "oklch(34% 0.070 75)"
  status-success: "oklch(72% 0.180 150)"
  status-success-soft: "oklch(32% 0.075 150)"
  status-danger: "oklch(64% 0.210 28)"
  status-danger-soft: "oklch(30% 0.080 28)"
  status-info: "oklch(72% 0.140 230)"
  status-info-soft: "oklch(30% 0.060 230)"
  light-stage-bg: "oklch(97% 0.010 80)"
  light-stage-surface: "oklch(94% 0.012 80)"
  light-stage-text: "oklch(20% 0.014 80)"
typography:
  display:
    fontFamily: "Inter Variable, Inter, ui-sans-serif, system-ui, sans-serif"
    fontSize: "clamp(2.5rem, 7vw, 5.5rem)"
    fontWeight: 800
    lineHeight: 0.95
    letterSpacing: "-0.025em"
  headline:
    fontFamily: "Inter Variable, Inter, ui-sans-serif, system-ui, sans-serif"
    fontSize: "clamp(1.75rem, 4vw, 2.75rem)"
    fontWeight: 700
    lineHeight: 1.05
  title:
    fontFamily: "Inter Variable, Inter, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.25rem"
    fontWeight: 600
    lineHeight: 1.2
  body:
    fontFamily: "Inter Variable, Inter, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: 1.5
  label:
    fontFamily: "Inter Variable, Inter, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.7rem"
    fontWeight: 600
    lineHeight: 1.2
    letterSpacing: "0.08em"
  numeric:
    fontFamily: "JetBrains Mono Variable, JetBrains Mono, ui-monospace, monospace"
    fontSize: "1rem"
    fontWeight: 500
    lineHeight: 1.2
    fontFeature: "'tnum' 1, 'zero' 1, 'ss01' 1"
rounded:
  md: "0.375rem"
  lg: "0.5rem"
  pill: "9999px"
spacing:
  tap-min: "2.75rem"
  tap-score: "3.5rem"
components:
  button-primary:
    backgroundColor: "{colors.stage-text}"
    textColor: "{colors.stage-bg}"
    rounded: "{rounded.lg}"
    padding: "1.25rem 1.25rem"
    height: "3.5rem"
    typography: "{typography.title}"
  button-danger:
    backgroundColor: "{colors.status-danger-soft}"
    textColor: "{colors.status-danger}"
    rounded: "{rounded.lg}"
    padding: "1.25rem 1.25rem"
  surface-card:
    backgroundColor: "{colors.stage-surface}"
    textColor: "{colors.stage-text}"
    rounded: "{rounded.lg}"
    padding: "1.5rem"
  score-control:
    backgroundColor: "{colors.stage-bg}"
    textColor: "{colors.stage-text}"
    rounded: "{rounded.md}"
    height: "3.5rem"
    width: "3.5rem"
  timer-display:
    textColor: "{colors.status-success}"
    typography: "{typography.numeric}"
  rank-chip:
    backgroundColor: "{colors.stage-surface-2}"
    textColor: "{colors.stage-text-muted}"
    rounded: "{rounded.md}"
    height: "2rem"
    width: "2rem"
  badge-active:
    backgroundColor: "{colors.status-success-soft}"
    textColor: "{colors.status-success}"
    rounded: "{rounded.pill}"
    padding: "0.25rem 0.625rem"
    typography: "{typography.label}"
---

# Design System: pongtable

## 1. Overview

**Creative North Star: "The Brewery Broadcast"**

`pongtable` sits at the intersection of three traditions. The heavyweight industrial typography of craft-brewery merch (BrewDog, Mikkeller, Stone). The HUD energy of esports broadcast overlays (LoL Worlds, Valorant Champions). The two-corner face-off drama of a UFC fight-night card. The result is an interface that takes beerpong as seriously as Premier League, and reads as a tournament instead of a SaaS dashboard. Every neutral is warm-tinted on hue 80 so the canvas evokes a taproom under low light, not a sterile workspace.

The referee-on-a-phone is the primary surface, but the design language is calibrated to the projected bracket. When the room watches the leaderboard light up on a TV, it has to feel like a real sporting event; admin density and referee-tap ergonomics inherit from that bar.

The system runs **dark by default** and ships a light counterpart for projector-on-a-bright-wall scenarios. Both share token names; the `.dark` class (set by Flux's appearance handler on first paint) flips the lightness ladder. Users can toggle from the header. The system explicitly rejects navy-sidebar SaaS dashboards, casino-gold sports-betting affordances, cartoon-beer-mug frat clipart, and Linear-clean utility restraint.

**Key Characteristics:**
- Four-role committed palette: red corner, blue corner, trophy gold, warm-tinted stage neutrals on hue 80.
- Single-family typography (Inter Variable for sans, JetBrains Mono Variable for numerics), with the broadcast energy living in scale and weight rather than decoration.
- Flat-by-default surfaces. Depth is tonal step, not shadow.
- Two-corner framing on every match surface; home and away are visually opposed and equal.
- Mobile-first referee surface (oversized tap targets, +/- score controls); desktop-dense admin surface; broadcast-first public surface.
- Responsive motion only: timer pulse, badge dot, gold glow on live elements. No choreographed entrances.

## 2. Colors

A committed four-role palette built around the two-corner face-off. Red corner, blue corner, trophy gold for moments of glory, warm-tinted near-black as the canvas. No fifth accent. The light scheme mirrors the same token names with inverted lightness, anchored on the same hue 80.

### Primary
- **Red Corner** (`oklch(62% 0.220 26)`): home-team identity. Carries the home side of every match screen, the "Red Corner" label on face-off framing, the home connector on the bracket. Broadcast-saturated, slight orange shift; never pink-leaning. `red-corner-bright` (`oklch(72% 0.210 28)`) is used for labels that need to lift off the canvas; `red-corner-soft` (`oklch(30% 0.080 26)`) is the matching low-chroma background wash for tinted regions.

### Secondary
- **Blue Corner** (`oklch(62% 0.180 240)`): away-team identity. Symmetric counterpart to Red Corner — same chroma, same lightness step, same visual weight. The face-off requires perfect balance. `blue-corner-bright` and `blue-corner-soft` mirror the red triad.

### Tertiary
- **Trophy Gold** (`oklch(82% 0.160 78)`): a reserved color. Appears on the live-match glow, the active timer's warning state, the rank-1 podium chip, championship banners, KO-bracket live trim. Brewery-amber heritage, never casino-gold. The rarity is the entire point. `trophy-gold-deep` (`oklch(72% 0.180 75)`) is the focus-ring color; `trophy-gold-soft` (`oklch(34% 0.070 75)`) wraps the champion banner background.

### Neutral
- **Stage Bg** (`oklch(11% 0.011 80)`): warm near-black canvas, the matte black of a brewery taproom under low light. Light counterpart `oklch(97% 0.010 80)` reads as warm cream paper.
- **Stage Surface** (`oklch(16% 0.013 80)`): one tonal step up; cards, sidebars, elevated regions.
- **Stage Surface-2** (`oklch(22% 0.015 80)`): nested surfaces, active-row highlights, hovered list items. Never used as a baseline.
- **Stage Line** (`oklch(32% 0.017 80)`): decorative dividers, default borders.
- **Stage Line-Strong** (`oklch(48% 0.020 80)`): form inputs, button outlines, focus rings.
- **Stage Text** (`oklch(96% 0.011 80)`): primary body text. Tinted near-white, never pure white.
- **Stage Text Muted** (`oklch(78% 0.022 80)`): secondary labels, supporting copy.
- **Stage Text Dim** (`oklch(66% 0.024 80)`): tertiary captions, timestamps, badge metadata.

### Semantic states
- **Status Success** (`oklch(72% 0.180 150)`) / **Soft** (`oklch(32% 0.075 150)`): the running timer's "ok" state, the live-badge pulse, finished-with-confidence affordances.
- **Status Danger** (`oklch(64% 0.210 28)`) / **Soft** (`oklch(30% 0.080 28)`): the "Runde beenden" destructive action, validation errors, overtime timer.
- **Status Info** (`oklch(72% 0.140 230)`) / **Soft** (`oklch(30% 0.060 230)`): the scoring-phase badge, informational asides.

### Named Rules

**The Two-Corner Rule.** Red Corner and Blue Corner carry equal visual weight on every match screen. Never adjust saturation, size, or position to favor one side. The face-off is symmetric or it is broken.

**The Gold-For-Glory Rule.** Trophy Gold is reserved. Live-match glow, active timer warning, podium #1 chip, champion banner, KO-bracket live trim. Never on default buttons. Never on icons that aren't winning something. If gold is showing, something is being won.

**The Tinted-Black Rule.** No pure black, no pure white. Every neutral carries a small warm chroma on hue 80 (0.011 to 0.024 across the ladder) so the canvas reads taproom-warm rather than sterile.

**The OKLCH-Only Rule.** All color tokens are authored in OKLCH. Chroma is reduced as lightness approaches the extremes (0.005 to 0.014 on near-blacks and near-whites). Stitch's linter will flag this; the doctrine outranks the linter.

## 3. Typography

**Sans Font:** Inter Variable (with Inter, ui-sans-serif, system-ui fallback).
**Numeric / Mono Font:** JetBrains Mono Variable (with JetBrains Mono, ui-monospace fallback).

**Character:** One sans family carries every text role. The broadcast energy lives in scale, weight, and color, not in decorative type. Numerics are always set in JetBrains Mono Variable with tabular figures and Stylistic Set 01 (slashed zero) so timers, scores, and ranks never reflow under live update. OpenType features `cv11`, `ss03`, `cv02`, `cv03` are applied globally to lift Inter's default character set into its more editorial alternates.

### Hierarchy

- **Display** (`font-display` utility: weight 800, `clamp(2.5rem, 7vw, 5.5rem)`, line-height 0.95, letter-spacing -0.025em): team names on the active match screen, tournament titles, projected bracket champions, the leaderboard winner. The tale-of-the-tape moment.
- **Headline** (weight 700, `clamp(1.75rem, 4vw, 2.75rem)`, line-height 1.05): page titles, group names, KO round labels.
- **Title** (weight 600, 1.25rem, line-height 1.2): card titles, match-list rows, modal headings.
- **Body** (weight 400, 1rem, line-height 1.5, max 65 to 75ch): admin descriptions, microcopy, public-surface body content.
- **Label** (`font-label` utility: weight 600, 0.7rem, letter-spacing 0.08em, uppercase): status badges, table headers, metadata, corner labels.
- **Numeric** (`font-numeric` utility: JetBrains Mono Variable, tabular figures, ss01 slashed zero): timer display (clamped up to 9rem on the live screen), score numbers, points, cup counts. Always mono.

### Named Rules

**The Tabular-Numeric Rule.** Every number, timer, score, points, cups, rank, is set in `font-numeric` (JetBrains Mono Variable, `font-variant-numeric: tabular-nums`, `font-feature-settings: 'tnum' 1, 'zero' 1, 'ss01' 1`). Sans numerics for numbers are prohibited; digit reflow during live update is unacceptable on the broadcast surface.

**The Scale-Hierarchy Rule.** A minimum 1.25× step between adjacent hierarchy levels. Display headlines `clamp` up to 5.5rem on the public surface and 9rem on the live timer. The bracket reads from the back of the room because the scale earns it.

**The Single-Family Rule.** No display/body font pairing. Inter Variable carries every text role; numerics step out to JetBrains Mono. Two faces in the system, no more.

## 4. Elevation

Flat by default. Depth is tonal layering on the warm canvas (Stage Surface elevated above Stage Bg, Stage Surface-2 above Stage Surface) rather than shadow. Shadows appear only as state responses, never as a baseline.

### Shadow Vocabulary

- **Live-match glow** (`box-shadow: inset 0 0 0 1px var(--color-trophy-gold), 0 0 24px -2px color-mix(in oklch, var(--color-trophy-gold) 22%, transparent), 0 18px 38px -18px color-mix(in oklch, var(--color-trophy-gold) 16%, transparent)`): a subtle warm trophy-gold glow with a 1px gold inset ring, applied to actively-running match containers and live KO-bracket nodes. A state shadow, not a UI shadow.
- **Focus ring** (`box-shadow: 0 0 0 2px var(--color-stage-bg), 0 0 0 4px var(--color-trophy-gold-deep)`): two-layer ring on focused form controls. The deep-gold outer ring against the stage-bg inner ring earns enough contrast on every surface.

### Named Rules

**The Flat-By-Default Rule.** Cards, surfaces, and panels are flat at rest. Depth is conveyed by tonal step (Stage Bg → Stage Surface → Stage Surface-2), not by shadow. Shadows are a state response (live, focus), not a baseline characteristic.

**The Gold-Glow Rule.** The only ambient shadow in the system is the trophy-gold live-match glow. Other colors do not earn glow; the rarity is what makes the live match read as alive.

## 5. Components

### Buttons

- **Shape:** rounded-lg (`0.5rem`). Never pill-shaped on primary actions; only badges go pill.
- **Primary** (`bg-stage-text` on `text-stage-bg`): the cream-on-stage inversion. Used for "Spiel starten", "Ergebnis speichern", "Zum Turnier". Padding `1.25rem`, hover via `opacity-90` (never a color shift; the inversion stays clean).
- **Secondary / Outline:** `border border-stage-line-strong` on transparent background, `text-stage-text`. Used for "Match-Verwaltung", "Zurück zur Match-Liste".
- **Danger** (`bg-status-danger-soft text-status-danger border-status-danger`): "Runde beenden" trigger only. The button opens a Flux confirmation modal; the destructive action lives behind the modal's "Ja, Runde beenden".
- **Subtle** (Flux `variant="subtle"`): the appearance-toggle in the header, navbar icons. No background at rest; `stage-surface-2` on hover.

### Score Control (signature component)

The score-entry +/- triad is the most-used primitive in the system and the wet-thumb principle made visual. Pattern: `[ – ]  [ input ]  [ + ]` with a numeric input between two oversized buttons.

- **Tap target:** 3.5rem × 3.5rem (56px) on referee scoring entry. Falls to 2.75rem (44px) inside the live timer box where space is tighter but the user is no longer entering blind.
- **Background:** `bg-stage-bg` (or `bg-stage-bg/60` over the face-off-bg gradient).
- **Type:** `font-numeric` for the input, sans for the +/- glyphs.
- **Hover:** background steps to `stage-surface-2`. Active scales to 0.95.
- **Input:** centered, no spinners, `inputmode="numeric"`, focused state gets the gold focus ring.

### Surface / Card

- **Corner:** rounded-lg (`0.5rem`).
- **Background:** `bg-stage-surface` (one step above the canvas).
- **Border:** none by default; `border border-stage-line` only where the card sits directly on `bg-stage-surface` and needs separation.
- **Padding:** 1.25rem on phone, 1.5rem on desktop.
- **Nesting:** prohibited. If a section inside a card needs containment, use a tonal step (`bg-stage-surface-2`) or a tinted gradient, never a card-inside-a-card.

### Status Badges (`.badge` + variants)

Pill-shaped, uppercase label scale, with a leading dot.

- **Shape:** `rounded-full`, padding `0.25rem 0.625rem`, `font-label`.
- **Leading dot:** 0.375rem rounded square. The `.badge-active` dot pulses on a 1.4s ease-out loop (`stage-pulse` keyframes), the only animation that ships in the badge vocabulary.
- **Variants:** `pending` (muted text on `stage-surface-2`), `pre-entry` (gold-on-gold-soft), `active` (success on success-soft, pulsing dot), `scoring` (info on info-soft), `finished` (muted on surface-2).

### KO Match Node

The bracket primitive. Each node is 5.5rem tall, padded `0.75rem 1rem`, `bg-stage-surface`, rounded-lg. Two team rows stacked, with a right-side score per team. The winning team's row gets `text-trophy-gold` and weight 700; the loser's row goes to `text-stage-text-dim`. A live node carries the live-match glow plus a 1px gold inset ring. Bracket connectors are 1px `stage-line-strong` lines drawn with `::before` / `::after` pseudo-elements; never SVG.

### Rank Chip (leaderboard)

2rem × 2rem, rounded-md (`0.5rem`), tabular-mono digit centered. Default is `bg-stage-surface-2` on `text-stage-text-muted`. Rank 1 takes `bg-trophy-gold` on `stage-bg`; rank 2 a desaturated warm silver (`oklch(72% 0.020 80)`); rank 3 a warm bronze (`oklch(60% 0.110 50)`). The podium variants invert to dark text on saturated background, they earn the visual weight.

### Timer Display

The hero number on the active match screen. Set in `font-numeric` at `clamp(5rem, 18vw, 9rem)`, weight 700, line-height 1, letter-spacing tight. Color tracks state via three classes: `timer-ok` (`status-success`), `timer-warning` (`trophy-gold`), `timer-overtime` (`status-danger`, plus a 0.9s ease-out opacity pulse). Negative time renders with a leading minus sign.

### Appearance Toggle

A Flux subtle button in the header. Sun icon when dark, moon icon when light. Bound to `$flux.dark` via Alpine. `x-cloak` hides both icons until Alpine evaluates so the toggle never flashes both glyphs.

### Two-Corner Face-Off Frame (`.face-off-bg`)

Background pattern used on the active match header, the live face-off teaser on home, and the live timer container. Two radial gradients (red-corner-soft on the left edge, blue-corner-soft on the right edge, both at 70% mix-amount over `stage-surface`) bisected by a 1px gradient divider. The `color-mix(in oklch, ...)` author lets the wash switch seamlessly with the light/dark scheme.

## 6. Do's and Don'ts

### Do:

- **Do** commit to the four-role palette. Red Corner and Blue Corner stay symmetric; Trophy Gold stays reserved; warm-tinted near-black is the canvas.
- **Do** set every number in `font-numeric` (JetBrains Mono Variable, tabular figures, ss01). Timers, scores, ranks, points, cups. Sans numerics for numbers are prohibited.
- **Do** make tap targets oversized on referee scoring: +/- at 3.5rem (56px) on entry surfaces, primary action buttons at 3rem (48px) minimum. The wet-thumb principle from PRODUCT.md is a visual contract.
- **Do** design the projected bracket and leaderboard for 5+ meter readability first. Calibrate referee and admin surfaces down from that bar, not the other way around.
- **Do** use `font-display` (weight 800, letter-spacing -0.025em, line-height 0.95) on team names and active-match surfaces. The fight-card character lives in type weight.
- **Do** route motion to state changes: timer color shift, badge dot pulse, live-glow ramp, status transitions. The interface stays alive without becoming theatrical.
- **Do** author every color in OKLCH. Reduce chroma toward the lightness extremes (0.011 on the near-black canvas, 0.020 on the strongest line).
- **Do** keep dark as the default. Light is the projector-on-a-bright-wall scheme; the broadcast canvas is the home.

### Don't:

- **Don't** build a generic SaaS dashboard. No navy sidebar, no three-stat-cards along the top, no indigo accents. The Stripe-clone layout grammar is the visual cliché this design exists to reject.
- **Don't** evoke a corporate sports betting site. No DraftKings or FanDuel green-and-gold-on-black, no casino-adjacent CTAs, no transactional language. This is a party, not a wagering pipeline.
- **Don't** introduce frat-house clipart. No cartoon beer mugs, no red Solo cups as decoration, no comic-sans energy. Playful is not cartoonish.
- **Don't** Linear-clean the system. Restraint and whitespace are not the answer; the personality is loud, communal, alive.
- **Don't** use pure `#000` or `#fff`. Tint every neutral toward hue 80 with chroma 0.011 to 0.024.
- **Don't** put Trophy Gold on a default button. The rarity is the point. Reserve gold for moments, not affordances.
- **Don't** animate CSS layout properties. Pulse and ripple with `transform` and `opacity` only.
- **Don't** introduce a fifth color role. The four roles carry the system; adding more dilutes the face-off discipline.
- **Don't** use side-stripe borders (`border-left` or `border-right` greater than 1px as a colored accent on cards, list items, or alerts). Use full borders, tonal backgrounds, or leading icons instead.
- **Don't** stack nested cards. If a section needs containment, use a tonal step, not a card-inside-a-card.
- **Don't** choreograph entrance animations or scroll-driven sequences. Motion is responsive only.
- **Don't** introduce a display font for body, labels, or buttons. Inter Variable carries every sans role; mono only for numerics.
- **Don't** show modals as a first thought. The system has exactly one confirmation modal (end-of-round destructive action). Default to inline affordances.
