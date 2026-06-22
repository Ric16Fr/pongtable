# Dashboard

Das Dashboard ist die Startseite nach dem Login (`/dashboard`). Es zeigt
auf einen Blick, in welcher Phase sich das laufende Turnier befindet, was
gerade an den Tischen passiert und wie weit der Spielplan abgearbeitet ist.

## Inhaltsverzeichnis

- [Was wird angezeigt?](#was-wird-angezeigt)
  - [Kopfbereich](#kopfbereich)
  - [Live-Sektion](#live-sektion)
  - [Leaderboard](#leaderboard)
- [Aktualisierung](#aktualisierung)
- [Sichtbarkeit für Rollen](#sichtbarkeit-für-rollen)
- [Was tun, wenn nichts angezeigt wird?](#was-tun-wenn-nichts-angezeigt-wird)
- [Datenquellen (kurz)](#datenquellen-kurz)

## Was wird angezeigt?

### Kopfbereich

- **Phasen-Label** – `Vorbereitung`, `Gruppenphase`,
  `Platzierungsspiele`, `KO-Phase` oder `Beendet`. Wird aus
  `tournaments.status` abgeleitet.
- **Turniername** – aus `tournaments.name`.
- **Public-Link-Button** – öffnet die öffentliche Ansicht
  (`/t/{public_token}`) in einem neuen Tab. Diese URL kannst du z. B.
  per QR-Code an die Großleinwand werfen, ohne dass Zuschauende einen
  Login brauchen.
- **Kennzahlen-Leiste** – drei Zahlen, alle bezogen auf das aktuelle
  Turnier:
    - **Matches** `<erledigt> / <gesamt>` – wie viele Spiele schon
      `status = finished` haben gegenüber der Gesamtzahl.
    - **Offen** – Spiele mit `status = pending` (also weder gestartet
      noch im Pre-Entry).
    - **Live** – Spiele mit `status` in `active`, `pre_entry` oder
      `scoring`. Diese Zahl färbt sich gold, sobald mindestens ein
      Match läuft.

### Live-Sektion

Sobald mindestens ein Match nicht `pending` und nicht `finished` ist,
erscheint die Sektion *„Live · jetzt am Tisch"*. Jede Karte zeigt:

- den Tischnamen,
- den aktuellen Status als Badge (`pre_entry`, `active`, `scoring`),
- beide Teams mit Farbpunkt.

Ein Klick auf die Karte führt direkt zur **Match-Steuerung**
(`/match/{id}`). Das ist der schnellste Weg für Schiris, vom Dashboard
in das gerade laufende Spiel zu springen.

### Leaderboard

Unter dem Live-Bereich wird das gleiche Leaderboard wie auf der
öffentlichen Seite eingebettet (`pages::leaderboard`). Es ist
turnierweit, d. h. alle Gruppen werden in eine gemeinsame Tabelle
zusammengefasst. Sortiert wird nach:

1. **Punkten** – 3 pro Sieg, 0 pro Niederlage (siehe
   [Matches – Wertungslogik](Matches.md#wertungslogik-im-detail)).
2. **Cup-Differenz** – `cups_scored_total − cups_conceded_total`.
3. **Getroffenen Bechern** – `cups_scored_total`.

Die Tabelle nutzt das Pivot-Feld der `group_team`-Beziehung, d. h.
die Werte werden bei jedem Match-Abschluss in
`App\Services\MatchResultService::updateGroupStandings()` fortgeschrieben.

> ⚠️ Achtung: Das Dashboard-Leaderboard sortiert _global_ nach den
> hinterlegten Punkten/Cup-Differenz. Die Tiebreaker-Reihenfolge
> _innerhalb einer Gruppe_ („direkter Vergleich" usw.) wird hier nicht
> angewendet, weil sich der Direkte-Vergleich konzeptionell nur
> innerhalb einer Gruppe definieren lässt. Für die KO-Setzung gilt die
> volle Tiebreaker-Logik aus `KoBracketService::groupStandings()` —
> siehe [Setup – Tiebreaker in der Gruppenphase](Setup.md#tiebreaker-in-der-gruppenphase).

## Aktualisierung

Das Dashboard pollt alle **15 Sekunden** (`wire:poll.15s`) neu. Schiris
müssen die Seite also nicht manuell neu laden, sobald ein anderer Schiri
an einem anderen Tisch ein Ergebnis einträgt.

Die eingebettete Live-Match-Liste übernimmt diesen Takt; jede
Live-Karte verlinkt direkt in die Match-Steuerung. Wenn dort der Status
wechselt (z. B. `active` → `scoring`), erscheint das beim nächsten
Poll-Intervall auch im Dashboard.

## Sichtbarkeit für Rollen

Das Dashboard ist für **Admins und Schiedsrichter** identisch. Die
Sidebar zeigt Schiris aber nur die Punkte *Dashboard* und *Matches*.
Die Admin-Punkte *Setup*, *Statistik*, *Sonderregeln & Einstellungen*
und *Archiv* sind hinter `middleware('role:admin')` versteckt
(`routes/web.php`). *Archiv* erscheint zusätzlich erst, wenn mehr als
ein Turnier existiert.

## Was tun, wenn nichts angezeigt wird?

- **„Kein Turnier vorhanden"** – noch nie ein Turnier angelegt. Als
  Admin im **Setup** ein Turnier erstellen
  (siehe [Setup.md](Setup.md)). Schiris müssen warten, bis der Admin
  Tische und Teams angelegt und die Gruppen generiert hat.
- **„Live: 0"** – keine Spiele laufen. Gehe in **Matches** und starte
  ein Spiel über *„Spiel starten"* (siehe [Matches.md](Matches.md)).
- **Leaderboard leer** – noch keine Gruppenphase generiert oder noch
  kein Spiel mit Ergebnis. Sobald das erste Match auf `finished` geht,
  füllt sich die Tabelle automatisch beim nächsten Poll.

## Datenquellen (kurz)

| Anzeige | Tabelle / Methode |
|---|---|
| Phase | `tournaments.status` |
| Turniername | `tournaments.name` |
| Public-Link | `tournaments.public_token` (beim Anlegen via UUID gesetzt) |
| Matches gesamt/erledigt/offen | `matches` mit `status`-Filter |
| Live-Karten | `matches` wo `status IN (active, pre_entry, scoring)` |
| Leaderboard-Zeilen | `group_team` Pivot + `KoBracketService::groupStandings()` |
