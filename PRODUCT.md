# Product

## Register

product

## Users

**Primary: Referees scoring matches on a phone.** Standing at a table, possibly tipsy, with sticky fingers from spilled beer, while a crowd watches. Their screen runs the match-score flow: pre-entry of throws and penalty cups, the live timer, then cups-scored entry. They need to be fast, glanceable, and never make a destructive mistake.

**Secondary: A tournament admin running the show.** Sets up teams, tables, and match durations before the event, generates groups and the KO bracket, oversees the flow from a laptop. Power-user density is welcome here; this surface is not phone-first.

**Tertiary: Spectators watching the bracket and leaderboard projected across the room.** No login, no input. The view has to read across 5+ meters and feel like a real sporting event when it lights up.

## Product Purpose

`pongtable` is a self-hostable web app for running beerpong tournaments end-to-end: multi-table parallel matches, group phase with automatic round-robin bracket generation, KO phase with cross-bracket seeding, live timers via Laravel Reverb, and fun stats after the final whistle. It exists so a crew of friends or a hackerspace can run a proper tournament without spreadsheets, hand-drawn brackets, or arguments about tiebreakers.

Originally built for the **Bierpong-WM**, an annual hackerspace tradition, and designed to be cloned and self-hosted by any group that wants their own tournament. The event is the canonical reference, not the only audience.

Success looks like: a referee never mis-taps, a spectator looks at the projected bracket and instantly knows what is happening, and an admin can set up a 16-team tournament in under five minutes.

## Brand Personality

**Playful. Competitive. Communal.**

The voice takes the game seriously without taking itself seriously. Team names like "NullPointer", "404", or "Team Bierherz" survive intact in the UI, not laundered into corporate-safe placeholders. Microcopy carries personality. The scoring math, tiebreakers, and KO bracket logic underneath are precise and unforgiving.

Visually, the reference is a **UFC fight-night card**: heavyweight typography, dramatic two-corner face-off framing during an active match, broadcast-grade weight on the spectator screens. The bracket on a TV should make the room react. That is the north-star moment.

Tonal goals: tension when the timer crosses zero, a real moment when a team wins, warmth and recognition in the team-color treatments. Never sterile, never corporate, never juvenile.

## Anti-references

- **Generic SaaS dashboard.** Stripe-clone navy sidebar, three-stat-cards along the top, indigo accents. The cliché the design world has already seen ten thousand times. Avoid the entire layout grammar, not just the palette.
- **Corporate sports betting site.** DraftKings or FanDuel green-and-gold-on-black, aggressive CTAs, casino-adjacent affordances. This is a party, not a transaction.
- **Frat-house clipart.** Cartoon beer mugs, red Solo cups as decoration, comic-sans energy. Playful is not the same as cartoonish.
- **Linear-clean utility.** Restraint and whitespace are not the right answer here. The chosen personality is loud, communal, alive; a too-quiet tool would feel like wearing a suit to a basement tournament.

## Design Principles

1. **The bracket is the showpiece.** When projected, it has to feel like a real sporting event. Type scale, contrast, and the live-update language on the public bracket and leaderboard drive the rest of the design system. Other surfaces are calibrated to that bar, not the other way around.

2. **Forgive the wet thumb.** Referee taps are sticky-fingered and possibly tipsy. Score-entry controls are oversized, every destructive action is reversible or confirmable, and no two interactive elements sit closer than a fingertip apart.

3. **Two-corner framing.** Every match is a face-off. Home and away get equal visual weight, opposed positioning, distinct team colors. Never an asymmetric layout where one team looks like the underdog by design.

4. **Live or it didn't happen.** Reverb-driven liveness is the magic ingredient. Timers pulse across every connected screen, scores ripple instantly to the projected bracket, the app feels connected rather than refreshed. A static UI is a regression.

5. **Take the game seriously, not the app.** In-jokes and personality live in microcopy, team identity, and the seeder fixtures. The scoring engine, tiebreakers, and bracket math stay precise and boring. Playfulness sits at the surface; competence runs underneath.

## Accessibility & Inclusion

- **WCAG AA contrast** as a baseline across all surfaces, with extra headroom on the public spectator screens so the bracket and leaderboard remain readable across 5+ meters under varying ambient light.
- **Oversized tap targets** on referee scoring: ➕ / ➖ at 56px minimum, primary action buttons at 48px minimum, with deliberate spacing so a sticky thumb cannot fat-finger an adjacent control.
- **Reversibility** for destructive or state-changing referee actions (start match, end round, save result). At minimum a confirmation pattern, ideally a short undo window where feasible.
- **Keyboard navigation and visible focus rings** on the admin surfaces. That is where keyboard users will spend their time.
- **German UI** is the default audience; copy is written for that crowd. Code, file names, and project documentation stay in English per Laravel convention.
