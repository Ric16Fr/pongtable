# Matches

Die Match-Sektion ist das Arbeits-Cockpit für Schiedsrichter. Sie besteht
aus zwei Seiten:

- **Match-Liste** – `/matches`, alle Spiele eines Turniers im Überblick.
- **Match-Steuerung** – `/match/{id}`, das einzelne Spiel zum Zählen und
  Auswerten.

Diese Seiten sind für **alle eingeloggten Nutzer** sichtbar, also auch
für Schiris (im Gegensatz zu Setup und Statistik).

## Inhaltsverzeichnis

- [Die Match-Liste](#die-match-liste)
  - [Filter](#filter)
  - [Sortierung](#sortierung)
  - [Was zeigt jede Zeile?](#was-zeigt-jede-zeile)
  - [Klickbar oder nicht?](#klickbar-oder-nicht)
  - [Polling](#polling)
- [Match-Steuerung](#match-steuerung)
  - [Phase 1: `pending`](#phase-1-pending)
  - [Phase 2: `pre_entry`](#phase-2-pre_entry)
  - [Phase 3: `active` – Live-Spiel](#phase-3-active--live-spiel)
  - [Phase 4: `scoring`](#phase-4-scoring)
  - [Phase 5: `finished`](#phase-5-finished)
  - [Polling](#polling-1)
- [Wertungslogik im Detail](#wertungslogik-im-detail)
  - [Sieger bestimmen](#sieger-bestimmen)
  - [Gruppenphase: Punktevergabe](#gruppenphase-punktevergabe)
  - [KO-Phase: Bracket-Vorwärtsschaltung](#ko-phase-bracket-vorwärtsschaltung)
  - [Unentschieden in der KO-Phase](#unentschieden-in-der-ko-phase)
- [Spezialfälle](#spezialfälle)
- [Beziehung zu anderen Sidebar-Punkten](#beziehung-zu-anderen-sidebar-punkten)

---

## Die Match-Liste

### Filter

Oben rechts liegen vier Filter-Buttons:

| Filter | zeigt Spiele mit Status |
|---|---|
| Alle | jedes Match des Turniers |
| Live | `active`, `scoring` |
| Offen | `pending`, `pre_entry` |
| Beendet | `finished` |

### Sortierung

Spiele werden _innerhalb_ jedes Filters fest sortiert, damit das, was
gerade Aufmerksamkeit braucht, ganz oben steht:

```
active → scoring → pre_entry → pending → finished
```

Innerhalb gleicher Status-Stufen wird nach `id` aufsteigend sortiert
(Reihenfolge der Erzeugung). Die Sortierung ist nicht konfigurierbar.

### Was zeigt jede Zeile?

- **Status-Spalte** – Status-Badge + Tisch und Gruppe bzw. KO-Runde.
- **Face-Off** – beide Teams mit Farbpunkten. Bei `finished` steht in
  der Mitte das Endergebnis (Sieger in Gold), sonst nur `vs`.
- **Action** – Pfeil nach rechts, wenn die Zeile klickbar ist. Sonst
  steht da „wartet“ (siehe unten).

### Klickbar oder nicht?

Eine Zeile ist klickbar (und führt zur Match-Steuerung), wenn beide
Bedingungen erfüllt sind:

- `status !== finished`
- beide Teams stehen fest (`home_team_id` _und_ `away_team_id` sind
  gesetzt).

Trifft eines der beiden nicht zu, wird die Zeile abgedunkelt
dargestellt:

- **Beendete Spiele** sind nicht mehr editierbar. Wer sich vertippt hat,
  muss das Turnier im Setup zurücksetzen
  (siehe [Setup – Turnier zurücksetzen](Setup.md#turnier-zurücksetzen)). Daher wird man extra gefragt, ob man ein Ergebnis speichern will ;)
- **Offene KO-Spiele ohne Teams** warten auf den Sieger der Vorrunde.
  Im KO-Bracket werden Folge-Matches erzeugt, sobald die erste Runde
  beendet ist; bis dahin steht statt eines Teamnamens
  „Sieger Vorrunde“ (siehe [Setup – KO-Phase](Setup.md#ko-phase)).

### Polling

Die Match-Liste pollt alle **5 Sekunden**. Wenn parallel an mehreren
Tischen gezählt wird, sieht jeder Schiri also schnell, ob sein nächstes
Spiel schon bereit ist.

---

## Match-Steuerung

`/match/{id}` führt den Schiri durch den Lebenszyklus eines Spiels.
Der Zustand wird im Feld `matches.status` gespeichert und durchläuft
fünf Phasen, die im UI jeweils unterschiedlich aussehen.

```
pending → pre_entry → active → scoring → finished
```

Übergänge werden ausschließlich durch `App\Services\MatchResultService`
ausgelöst. Tippfehler in der UI können also keinen ungültigen Status
erzeugen.

### Phase 1: `pending`

Default beim Anlegen. Der Schiri sieht das Match, sobald er es öffnet,
gar nicht in dieser Phase: Beim ersten Aufruf wird automatisch
`startPreEntry()` aufgerufen, wenn beide Teams stehen — der Status
springt also sofort auf `pre_entry`.

Ausnahme: KO-Spiele, deren Vorrunde noch läuft. Hier ist mindestens ein
Team-Slot leer und der Schiri sieht „Wartet auf Vorrunde“. Die Steuerung
ist gesperrt, bis der Sieger des Vorrunden-Matches feststeht
(KO-Vorwärtsschaltung passiert in
`KoBracketService::advanceKoWinner()`).

### Phase 2: `pre_entry`

Bereitmach-Phase. Im UI steht „Teams an den Tisch“ und ein großer
Button **Spiel starten**.

Wichtig: In dieser Phase werden **keine Eingaben gemacht**. Es dient nur der Anzeige, dass es gleich losgeht. Denn, logischerweise, werden
Würfe und
Strafbecher _live_ während des Spiels gezählt, nicht vorab.

Sobald der Schiri auf **Spiel starten** klickt:

- `MatchStat`-Zeilen werden für beide Teams angelegt (mit `throws = 0`
  und `penalty_cups = 0`),
- `started_at` wird gesetzt,
- der Status springt auf `active`.

### Phase 3: `active` – Live-Spiel

Der zentrale Bildschirm während eines Bierpong-Spiels:

- Großer **Timer** in der Mitte, Countdown ab der eingestellten
  Match-Dauer (`group_match_duration_minutes` bzw.
  `ko_match_duration_minutes`).
    - Bis 2 Minuten vor Ablauf: neutraler Grünton.
    - Letzte 2 Minuten: gelber Warn-Modus.
    - Nach Ablauf: rot, mit `-MM:SS` als Overtime-Zähler.
- Pro Team werden **Würfe** und **Strafbecher** geführt. Es gibt für
  jedes Feld ein `−`-, ein `+`- und ein Eingabe-Feld.
- Jeder Klick auf `±` und jede manuelle Eingabe schreibt sofort den
  neuen Wert in `match_stats` (über
  `persistLiveStat()` im Livewire-Component). So gehen keine Daten
  verloren, wenn ein Schiri die Seite neu lädt.

Knapper Tipp für die UI: Werte können nicht unter 0 fallen, der Server
clamped automatisch (`max(0, value)`).

Unten ein roter Button **Runde beenden**. Er öffnet einen
Bestätigungsdialog — wichtig, weil Würfe und Strafe nach Ende der Runde
nicht mehr editierbar sind. Bestätigt der Schiri, läuft
`endTimer()`:

- `ended_at` wird gesetzt,
- die Dauer (in Sekunden) wird in alle `match_stats`-Zeilen
  geschrieben (`duration_seconds`),
- Status springt auf `scoring`.

### Phase 4: `scoring`

Jetzt zählt der Schiri die übrig gebliebenen Becher. Die UI ist auf
diesen einen Schritt eingedampft: zwei große Eingabefelder mit `±`.

> **Default-Trick:** Die Felder werden mit `10` vorbelegt (Bierpong-
> Standard sind 10 Becher pro Seite). In den meisten Fällen hat der
> Sieger fast alle Becher getroffen und der Schiri muss nur _für die
> Verliererseite_ herunterzählen, wie viele Becher die noch übrig
> haben. Das ist verlustfrei: falls eure Tische mit anderer Cup-Zahl
> spielen, kann der Schiri den Wert einfach hochsetzen.

Endet ein **KO-Spiel** mit gleicher Becherzahl und ist die Sonderregel
**Sudden Death** aktiv, erscheint hier zusätzlich ein Auswahlblock, in
dem der Schiri das siegreiche Team bestimmt (siehe
[Unentschieden in der KO-Phase](#unentschieden-in-der-ko-phase)).

Bei Klick auf **Ergebnis speichern** läuft `saveResult()`:

- Validation: beide Werte müssen `integer`, `>= 0`, `<= 30` sein.
- Beide `match_stats`-Zeilen bekommen ihren `cups_scored`-Wert.
- Der Sieger wird über `determineWinner()` ermittelt (siehe unten).
- Status springt auf `finished`, `winner_team_id` wird gesetzt.
- Bei **Gruppenphase**-Matches: die Pivot-Werte der Gruppe werden
  fortgeschrieben (Punkte, W/L, Cups).
- Bei **KO**-Matches: der Sieger wird ins nächste Bracket-Match
  einsortiert. War das die Finalrunde (`ko_round = 1`), wird das
  Turnier als `finished` markiert.

### Phase 5: `finished`

Großes goldenes Banner mit Siegerteam, Endstand und drei Kennzahlen:

| Kennzahl | Bedeutung |
|---|---|
| Dauer | `match_stats.duration_seconds` als `MM:SS` |
| Würfe | `home.throws : away.throws` |
| Strafe | `home.penalty_cups : away.penalty_cups` |

Ein Klick auf **Zurück zur Match-Liste** schließt die Steuerung.

### Polling

Die Match-Steuerung pollt im **active**-Status alle 1 Sekunde, in
allen anderen Phasen alle 3 Sekunden. Damit ist gewährleistet, dass
der Timer flüssig läuft und parallele Eingaben (z. B. wenn zwei Schiris
versehentlich gleichzeitig zählen) sich schnell synchronisieren.

> Der eigentliche Countdown rechnet client-seitig in Alpine.js (auf
> Basis von `started_at`), läuft also weiter, auch wenn ein Poll mal
> verzögert eintrifft.

---

## Wertungslogik im Detail

Diese Regeln laufen in `App\Services\MatchResultService::determineWinner()`
zusammen.

### Sieger bestimmen

1. **Mehr Becher = Sieger.** Eindeutiger Fall, kein Tiebreaker nötig.
2. **Gleichstand an Bechern → weniger Würfe gewinnt.** Beispiel: 10:10
   nach Bechern, aber Team A hat 22 Würfe, Team B hat 25 → A gewinnt.
3. **Gleichstand an Würfen → weniger Strafbecher gewinnt.**
4. **Alles gleich → Heimteam gewinnt** (Fallback).

> Schritt 4 ist absichtlich ein simpler Fallback: das ist extrem
> unwahrscheinlich (Cups gleich, Würfe gleich, Strafbecher gleich) und
> hier vermeidet die App ein hängendes Match. Wenn das in einem realen
> Turnier passieren sollte, kann das Turnier zurückgesetzt und das
> letzte Match neu gewertet werden — oder ihr einigt euch im Setup auf
> einen anderen Wert für Würfe/Strafe und tragt ihn entsprechend ein.

### Gruppenphase: Punktevergabe

Pro abgeschlossenem Match werden die `group_team`-Pivot-Felder so
fortgeschrieben:

| Feld | Sieger | Verlierer |
|---|---|---|
| `points` | +3 | +0 |
| `wins` | +1 | +0 |
| `losses` | +0 | +1 |
| `cups_scored_total` | + eigene Cups | + eigene Cups |
| `cups_conceded_total` | + gegnerische Cups | + gegnerische Cups |

Unentschieden _gibt es nicht_ – die Tiebreaker oben sorgen dafür, dass
immer ein Sieger feststeht.

### KO-Phase: Bracket-Vorwärtsschaltung

Sobald ein KO-Match auf `finished` geht, ruft
`MatchResultService` die Methode
`KoBracketService::advanceKoWinner()` auf. Diese:

- berechnet die nächste Runde (`ko_round / 2`) und Position
  (`floor(ko_position / 2)`),
- holt oder legt das Match dieser Runde an (`firstOrCreate`),
- schreibt den Sieger auf die richtige Seite:
    - gerade `ko_position` → Heimseite (`home_team_id`),
    - ungerade `ko_position` → Auswärtsseite (`away_team_id`).

Das nächste Bracket-Spiel wird _erst dann_ spielbar, wenn beide Teams
fest stehen. Bis dahin steht in der Liste „Sieger Vorrunde“ und die
Zeile ist nicht anklickbar.

### Unentschieden in der KO-Phase

Was bei **Becher-Gleichstand** passiert, hängt von der Sonderregel
**Bestimmung des Siegers in der KO-Phase** ab
(siehe [Sonderregeln](Sonderregeln.md#bestimmung-des-siegers-in-der-ko-phase)):

- **Automatisch** _(Default)_ — es greift dieselbe Tiebreaker-Reihen­
  folge wie in der Gruppenphase (Cups → Würfe → Strafbecher →
  Heimteam-Fallback). Kein explizites Verlängerungsspiel; das Match
  endet mit einem Sieger, sobald das Ergebnis gespeichert ist.
- **Sudden Death** — die Match-Steuerung blendet vor dem Speichern
  einen Sudden-Death-Block ein (`needsSuddenDeath`), in dem der Schiri
  das siegreiche Team auswählt. Ohne Auswahl schlägt das Speichern mit
  einem Validierungsfehler fehl. Das Finished-Banner vermerkt dann
  „Entschieden im Sudden Death“.

Beide Wege liefern garantiert einen Sieger — eine frei spielbare
Verlängerung mit eigenem Timer gibt es nicht.

Wenn ihr stattdessen lieber eine echte Verlängerung wollt: Das
Turnier zurücksetzen ist nicht das richtige Werkzeug (das löscht
sämtliche Matches und Gruppen). Aktuell ist die saubere Variante,
das eine Match-Ergebnis im Setup zurückzusetzen und manuell die
Strafbecher/Würfe so anzupassen, dass das gewünschte Team über die
Tiebreaker-Logik gewinnt. Eine Verlängerungs-Funktion ist nicht
eingebaut.

---

## Spezialfälle

- **Status zurückspulen.** Es gibt keine UI dafür. Wenn ein Schiri zu
  früh auf „Runde beenden“ geklickt hat, kann er sofort die
  Becher-Werte korrekt eintragen und speichern — beendete Matches
  lassen sich danach jedoch nicht mehr editieren.
- **Match-Steuerung ohne Teams.** Solange `home_team_id` oder
  `away_team_id` `null` ist, lässt sich `pre_entry`/`active` nicht
  starten — der Service erkennt das und schickt den Aufruf ohne
  Statuswechsel zurück (`return` ohne Fehler). Im UI sieht der Schiri
  „Wartet auf Vorrunde“.
- **Mehrere Schiris an einem Match.** Funktioniert, weil alle
  Schreibvorgänge über die Live-Felder direkt auf
  `match_stats.updateOrCreate` gehen. Wer zuletzt klickt, gewinnt.
  Polling synchronisiert die UI alle 1–3 Sekunden.
- **Browser-Reload während `active`.** Unkritisch: `started_at` ist
  serverseitig gespeichert, der Alpine-Timer rechnet beim erneuten
  Mount aus `started_at` weiter. Würfe/Strafe sind ebenfalls
  persistiert, weil jede Eingabe sofort geschrieben wird.

## Beziehung zu anderen Sidebar-Punkten

- **Setup** legt fest, welche Match-Dauer der Timer benutzt
  (`group_match_duration_minutes`, `ko_match_duration_minutes`).
- **Statistik** baut auf abgeschlossenen `match_stats`-Zeilen auf —
  speziell die Felder `cups_scored`, `throws`, `penalty_cups`,
  `duration_seconds`, die alle hier in der Match-Steuerung entstehen.
- **Dashboard** linkt direkt in jedes laufende Match.
