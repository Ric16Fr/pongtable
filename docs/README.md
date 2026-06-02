# pongtable – Dokumentation

Diese Dokumentation beschreibt, wie das System bedient wird. Sie geht
gezielt **nicht** auf die Bierpong-Regeln selbst ein — die liegen in
[Bierpongregeln.md](./Bierpongregeln.md). Hier geht es um die App:
wie ein Schiri ein Match steuert, wie ein Admin ein Turnier aufbaut,
wie der Sieger ermittelt wird, was die Statistiken bedeuten.

## Wegweiser

Pro Sidebar-/Profil-Eintrag gibt es ein Dokument:

| Dokument | Sidebar-Eintrag | Zielrolle |
|---|---|---|
| [Dashboard.md](Dashboard.md) | Dashboard | Schiri & Admin |
| [Matches.md](Matches.md) | Matches | Schiri & Admin |
| [Setup.md](Setup.md) | Setup | Admin |
| [Statistik.md](Statistik.md) | Statistik | Admin |
| [Einstellungen.md](Einstellungen.md) | _(Profil-Dropdown)_ | Schiri & Admin |
| [Oeffentliche-Ansicht.md](Oeffentliche-Ansicht.md) | _(Public-Link auf dem Dashboard)_ | Publikum |

## Quick-Start

1. **Initial einloggen.** Beim ersten Migrieren entsteht der Account
   `admin` mit Passwort `password` — siehe
   [Einstellungen – Initialer Admin](Einstellungen.md#initialer-admin).
   Erste Aktion: Passwort über die Einstellungsseite ändern.
2. **Schiris anlegen.** In den Einstellungen Schiri-Accounts erzeugen
   und Zugangsdaten weitergeben.
3. **Setup aufrufen.** Tische und Teams eintragen, Match-Dauer
   konfigurieren, **Gruppen generieren** anklicken.
4. **Public-Link teilen.** Im Dashboard auf den Public-Link-Button
   klicken; die URL (als QR-Code) auf dem Beamer aufrufen
5. **Spielen lassen.** Schiris öffnen `/matches`, klicken sich in das
   nächste Match und steuern die Phasen `pre_entry` → `active` →
   `scoring` → `finished` durch.
6. **KO-Phase starten.** Sobald alle Gruppenspiele beendet sind,
   erscheint im Setup der Button **KO-Phase starten** — der baut das
   Bracket.
7. **Finale beenden** — Turnier-Status springt automatisch auf
   `finished`, der Champion taucht im Dashboard, in der Statistik und
   auf der öffentlichen Seite auf.

## Cross-Cutting-Themen

Manche Themen sind so eng zwischen Seiten verzahnt, dass sie in den
Einzeldokumenten verlinkt sind:

- **Wertungslogik bei Becher-Gleichstand** —
  [Matches – Wertungslogik](Matches.md#wertungslogik-im-detail).
- **Tiebreaker innerhalb einer Gruppe** —
  [Setup – Tiebreaker in der Gruppenphase](Setup.md#tiebreaker-in-der-gruppenphase).
- **Vorgehen bei Unentschieden in der KO-Phase** —
  [Setup – Unentschieden in der KO-Phase](Setup.md#unentschieden-in-der-ko-phase)
  bzw.
  [Matches – Unentschieden in der KO-Phase](Matches.md#unentschieden-in-der-ko-phase).
- **Lebenszyklus eines Turniers** —
  [Setup – Lebenszyklus eines Turniers](Setup.md#lebenszyklus-eines-turniers).
- **Lebenszyklus eines Matches** —
  [Matches – Match-Steuerung](Matches.md#match-steuerung).

## Technischer Kurz-Stack

Falls jemand die Doku liest und auch in den Code schauen will:

- **Laravel 13**, **Livewire 4 SFCs** (Single-File Components in
  `resources/views/pages/`), **Flux UI 2**, **Tailwind 4**.
- **Fortify** für Authentifizierung.
- Geschäftslogik liegt in `app/Services/`:
    - `GroupGeneratorService` – Auslosung der Gruppenphase.
    - `KoBracketService` – KO-Bracket-Aufbau, Vorwärtsschaltung,
      Gruppen-Sortierung mit Tiebreakern.
    - `MatchResultService` – Lebenszyklus eines Matches.
    - `StatisticsService` – Aggregation für die Statistik-Seite.
- Datenbankschema in `database/migrations/`.
