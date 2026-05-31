---
name: pongtable
description: Beerpong tournament management with broadcast-grade liveness.
---

<!-- SEED: re-run /impeccable document once there's code to capture the actual tokens and components. -->

# Design System: pongtable

## 1. Overview

**Creative North Star: "The Brewery Broadcast"**

`pongtable` sits at the intersection of three traditions: the heavyweight industrial typography of craft-brewery merch (BrewDog, Mikkeller, Stone), the modern HUD energy of esports broadcast overlays (LoL Worlds, Valorant Champions), and the two-corner face-off drama of a UFC fight-night card. The result is an interface that takes beerpong as seriously as Premier League, and looks like a tournament instead of a SaaS dashboard.

The referee-on-a-phone is the primary surface, but the design language is calibrated to the projected bracket. When the room watches the leaderboard light up on a TV, it has to feel like a real sporting event. Every other surface (admin setup, match list, scoring controls) inherits its scale and weight from that bar.

The system explicitly rejects: navy-sidebar SaaS dashboards, casino-gold sports-betting affordances, cartoon-beer-mug frat clipart, and Linear-clean utility restraint. The personality is loud, communal, and alive.

**Key Characteristics:**
- Full-palette commitment: four deliberate roles (red corner, blue corner, trophy gold, tinted near-black).
- Editorial sans for display and body; tabular mono for every number.
- Responsive motion: timer pulses, scores ripple, feedback on every tap. No choreographed theater.
- Two-corner framing on every match surface; home and away are visually opposed and equal.
- Mobile-first referee surface; broadcast-first public surface; desktop-dense admin surface.

## 2. Colors

A committed four-role palette built around the two-corner face-off. Red corner, blue corner, trophy gold for moments of glory and timer drama, tinted near-black as the canvas. No fifth accent.

### Primary
- **Red Corner** `[to be resolved at implementation]`: home-team identity. Carries the home side of every match screen, the home row in tables, the home connector on the bracket. Broadcast-saturated, not pink-leaning.

### Secondary
- **Blue Corner** `[to be resolved at implementation]`: away-team identity. Symmetric counterpart to Red Corner: same chroma, same lightness, same visual weight. The face-off requires perfect balance.

### Tertiary
- **Trophy Gold** `[to be resolved at implementation]`: a reserved color. Appears on winners, on the active timer, on the final whistle, on championship moments. Brewery-amber heritage, never casino-gold. The rarity is the entire point.

### Neutral
- **Stage Black** `[to be resolved at implementation]`: tinted near-black canvas, warm-shifted (chroma ~0.005–0.01). The matte black of a brewery taproom under low light, never `#000`.
- **Stage Surface** `[to be resolved at implementation]`: one tonal step above Stage Black. Cards, containers, elevated regions live here.
- **Stage Text** `[to be resolved at implementation]`: tinted near-white body text. Never `#fff`. Slight warm shift to match the canvas.
- **Stage Muted** `[to be resolved at implementation]`: muted neutral for secondary labels, timestamps, badge text.

### Named Rules

**The Two-Corner Rule.** Red Corner and Blue Corner carry equal visual weight on every match screen. Never adjust saturation, size, or position to favor one side. The face-off is symmetric or it is broken.

**The Gold-For-Glory Rule.** Trophy Gold is reserved. It appears on winners, on the live timer, on championship surfaces. Not on default buttons. Not on icons. Not on backgrounds. If gold is showing, something is being won.

**The Tinted-Black Rule.** No pure black, no pure white. Every neutral carries a small warm chroma so the canvas reads like a taproom, not a sterile workspace.

## 3. Typography

**Display Font:** `[editorial sans to be chosen at implementation, e.g. Inter Display, General Sans, or similar]`
**Body Font:** `[same family or paired editorial sans]`
**Numeric / Mono Font:** `[tabular mono with true tabular figures, e.g. JetBrains Mono, IBM Plex Mono]`

**Character:** A modern editorial sans paired with a tabular mono. The display end is heavyweight; the body end is calm and readable. The broadcast energy lives in scale, weight, and color, not in decorative type. Numbers are always mono so timers and scores never reflow under live update.

### Hierarchy
- **Display** (weight ~800, large clamp scale, line-height ~0.95): team names on the active match screen, projected bracket champions, the leaderboard winner. The tale-of-the-tape moment.
- **Headline** (weight ~700, smaller clamp scale, line-height ~1.05): page titles, group names, KO round labels.
- **Title** (weight ~600, ~1.25rem, line-height ~1.2): card titles, match-list rows.
- **Body** (weight ~400, ~1rem, line-height ~1.5, max 65–75ch): admin descriptions, microcopy, public-surface body content.
- **Label** (weight ~500, ~0.75rem, letter-spacing ~0.08em, uppercase): status badges, table headers, metadata.
- **Numeric** (tabular mono, weights as needed): timer display, score numbers, points, cup counts. Always mono.

### Named Rules

**The Tabular-Numeric Rule.** Every number (timer, score, points, cups) is set in a tabular mono with true tabular figures. Sans-numerics for numbers are prohibited because digit reflow under live update is unacceptable on the broadcast surface.

**The Scale-Hierarchy Rule.** A minimum 1.25× step between adjacent hierarchy levels. No flat type ladders. The bracket reads from the back of the room because the scale earns it.

## 4. Elevation

Flat by default. Motion is responsive rather than choreographed, so depth comes from tonal layering on the dark canvas (Stage Surface elevated above Stage Black) rather than from shadows. Shadows appear only as state responses, never as a baseline.

### Shadow Vocabulary
- **Live-match glow** `[to be resolved at implementation]`: a subtle warm ambient glow under an actively-running match container, so it reads as alive on the public bracket. A state shadow, not a UI shadow.
- **Interactive hover** `[to be resolved at implementation]`: a soft ambient shadow on hoverable elements on the admin desktop surface only. Irrelevant on touch.

### Named Rules

**The Flat-By-Default Rule.** Cards, surfaces, and panels are flat at rest. Depth is conveyed by tonal step, not by shadow. Shadows are a response to state (live, hover, focus), not a baseline characteristic.

## 5. Components

Components will be specified once the first build pass produces real Livewire primitives. Re-run `/impeccable document` then to capture button variants, the score-entry control, status badges, the match-row, the bracket-node, and the timer display from actual code.

## 6. Do's and Don'ts

### Do:
- **Do** commit to the four-role palette. Red Corner and Blue Corner stay symmetric, Trophy Gold stays reserved, tinted near-black is the canvas.
- **Do** set every number in a tabular mono with true tabular figures. Timers must not reflow as digits change.
- **Do** make tap targets oversized on the referee scoring surface: ➕ / ➖ at 56px minimum, primary action buttons at 48px minimum. The wet-thumb principle from PRODUCT.md is a visual contract, not just a UX one.
- **Do** design the projected bracket and leaderboard for 5+ meter readability first. Calibrate referee and admin surfaces down from that bar, not the other way around.
- **Do** use heavyweight display type on team names and active-match surfaces. The fight-card character lives in type weight, not in decoration.
- **Do** route motion to state changes: timer pulse, score ripple, status transitions, badge color shifts. The interface stays alive without becoming theatrical.
- **Do** use OKLCH for all color tokens. Reduce chroma as lightness approaches the extremes.

### Don't:
- **Don't** build a generic SaaS dashboard. No navy sidebar, no three-stat-cards along the top, no indigo accents. The Stripe-clone layout grammar is the visual cliché this design exists to reject.
- **Don't** evoke a corporate sports betting site. No DraftKings or FanDuel green-and-gold-on-black, no casino-adjacent CTAs, no transactional language. This is a party, not a wagering pipeline.
- **Don't** introduce frat-house clipart. No cartoon beer mugs, no red Solo cups as decoration, no comic-sans energy. Playful is not cartoonish.
- **Don't** Linear-clean the system. Restraint and whitespace are not the answer; the personality is loud, communal, alive.
- **Don't** use `#000` or `#fff`. Tint every neutral toward the warm anchor (chroma 0.005–0.01 is enough).
- **Don't** put Trophy Gold on a default button. The rarity is the point. Reserve gold for moments, not affordances.
- **Don't** animate CSS layout properties. Pulse and ripple with `transform` and `opacity` only.
- **Don't** introduce a fifth color role. The four roles carry the system; adding more dilutes the face-off discipline.
- **Don't** use side-stripe borders (`border-left` or `border-right` greater than 1px as a colored accent on cards, list items, or alerts). Use full borders, tonal backgrounds, or leading icons instead.
- **Don't** choreograph entrance animations or scroll-driven sequences. The motion energy is responsive only; theatrical sequences are out of scope.
- **Don't** stack nested cards. If a section needs containment, use a tonal step, not a card-inside-a-card.
