# Setup

Das Setup (`/setup`) ist die Kommandozentrale für Admins. Hier wird das
Turnier angelegt, Tische und Teams werden gepflegt, die Gruppenphase
generiert und die KO-Phase gestartet.

> 🔒 Diese Seite ist hinter `middleware('role:admin')` versteckt.
> Schiedsrichter sehen den Setup-Eintrag in der Sidebar nicht.

> Hinweis: Die turnierweiten Einstellungen (Name, Match-Dauer) und
> die Sonderregeln (z. B. „Würfe zählen") sind aus dem Setup
> ausgelagert und liegen jetzt unter
> [Sonderregeln & Einstellungen](Sonderregeln.md). Im Setup geht es
> nur noch um das einzelne Turnier-Spiel (Tische, Teams, Auslosen,
> KO-Start, Reset).

## Lebenszyklus eines Turniers

Ein Turnier durchläuft bis zu fünf Phasen, hinterlegt in
`tournaments.status`. `placement` ist dabei optional und wird nur
durchlaufen, wenn die Sonderregel **Platzierungsspiele austragen**
aktiv ist und mindestens 2 Teams die KO-Phase verpassen:

```
setup → group → (placement) → ko → finished
```

Die Übergänge sind nicht frei wählbar — jeder davon wird von einer
konkreten UI-Aktion ausgelöst:

| Übergang | Wird ausgelöst durch |
|---|---|
| `setup → group` | **Gruppen generieren** im Setup |
| `group → placement` | **KO-Phase starten** im Setup, wenn Platzierungsspiele aktiv sind und ≥ 2 Teams nicht qualifiziert |
| `group → ko` | **KO-Phase starten** im Setup (ohne Platzierungsspiele) |
| `placement → ko` | erneutes **KO-Phase starten**, sobald alle Platzierungsspiele beendet sind |
| `ko → finished` | Speichern des Finals (`ko_round = 1`) in der Match-Steuerung |
| _zurück auf_ `setup` | **Turnier zurücksetzen** im Setup |

---

## Beim Aufruf: Auto-Anlage

Wenn noch nie ein Turnier angelegt wurde, erzeugt das Setup beim ersten
Aufruf automatisch ein Default-Turnier:

```
name = "Bierpong Cup <aktuelles Jahr>"
group_match_duration_minutes = 10
ko_match_duration_minutes = 15
status = "setup"
```

Das spart einen Klick — der Admin landet sofort auf der Setup-Seite und
kann direkt anfangen, Tische und Teams einzutragen.

Existiert bereits ein Turnier, lädt das Setup immer das _zuletzt
angelegte_ (`Tournament::latest()`). Es ist also immer genau ein
Turnier „aktiv". Wer ein neues Turnier starten will, nutzt im
Setup-Header **Neues Turnier** (mit Bestätigung): Das legt ein
frisches Turnier im Status `setup` an, das damit zum aktiven wird.
Das bisherige Turnier bleibt unangetastet in der Datenbank und ist ab
dann über den Menüpunkt **Archiv** schreibgeschützt einsehbar
(siehe [Archiv](#archiv)).

---

## Tische

Tische repräsentieren parallel laufende Spielflächen (z. B.
„Tisch Kellerbar", „Tisch Wohnzimmer"). Sie haben direkt zwei Funktionen:

1. **Gruppen-Container.** Beim Generieren der Gruppenphase entsteht
   genau _eine Gruppe pro Tisch_. Wer zwei Gruppen pro Tisch will,
   muss zwei Tische anlegen.
2. **Match-Zuordnung.** Jedes Match liegt einem Tisch zu — das ist
   die Information, die Schiris und Zuschauende sehen, um zu wissen,
   wo gerade gespielt wird.

### Anlegen / Entfernen

- Nur im Status `setup` möglich. Sobald `group` läuft, ist das
  Hinzufügen/Entfernen gesperrt (Abort 422), damit die Gruppenstruktur
  konsistent bleibt.
- Beim Entfernen eines Tischs wird vorher `wire:confirm` angezeigt.
- Tische haben keine Kapazitätsgrenze; jedes Team-Mismatch löst der
  Generator auf, indem er die Teams gleichmäßig verteilt.

---

## Teams

Ein Team hat einen Namen und eine Farbe (Hex, z. B. `#f59e0b`). Die
Farbe wird überall als kleiner Punkt neben dem Teamnamen gerendert
(Match-Liste, Bracket, Leaderboard, Live-Karten). Sie hat keinen
Einfluss auf die Wertung.

### Anlegen / Entfernen

- Nur im Status `setup`.
- Default-Farbe ist Turnier-Gold (`#f59e0b`). Beim Anlegen kann sie
  über einen Color-Picker geändert werden.
- Mindestens 2 Teams sind nötig, damit die Gruppenphase generiert
  werden kann (sonst Abort 422 mit
  `Mindestens 2 Teams erforderlich.`).

---

## Gruppen hochladen (CSV)

Alternative zur App-internen Auslosung: Wer die Gruppenverteilung
**außerhalb** der App ermittelt (z. B. per Hand ausgelost, per Excel
vorbereitet, in einem anderen Tool berechnet), kann sie direkt
übernehmen — ohne dass die App neu auslost.

Oben rechts im Setup-Header steht in der Setup-Phase die Aktion
**Gruppen hochladen**. Sie öffnet ein Modal mit einer Textarea, in
die eine **semikolon-getrennte CSV** gepastet wird.

### Erwartetes Format

Die erste Zeile enthält die Gruppennamen (sie wird _ignoriert_, weil
die App die Gruppen immer alphabetisch A, B, C, … benennt — die
Spaltenanzahl bestimmt aber, wie viele Gruppen es gibt). In den
folgenden Zeilen steht pro Spalte ein Team. Beispiel mit 4 Gruppen à
4 Teams:

```
Gruppe A;Gruppe B;Gruppe C;Gruppe D
Renx & Philipp;Stefan & Henry;Kitty & Till;Schachi & Huschke
Dennis & Yves H.;Paul N. & Niklas;Tobi & Richard;Felo & Kluwe
Kevin & Grodon;Valle & TB;Justin & Yves M.;Franik & MB
Felix & Lude;Mörre & Gussi;John & Ede;Marvin & Luki
```

Wird die CSV als _eine einzige Zeile_ ohne Zeilenumbrüche gepastet,
erkennt der Parser die Gruppen automatisch an den führenden Zellen,
die mit "Gruppe " beginnen, und verteilt die restlichen Zellen
reihum (modulo Spaltenanzahl) auf die Buckets.

### Voraussetzungen

- Das Turnier muss im Status `setup` sein.
- Die **Anzahl der bestehenden Tische** muss exakt zur Anzahl der
  Gruppenspalten in der CSV passen. Beispiel: 4 Spalten → exakt 4
  Tische müssen vorher angelegt sein. Sonst HTTP 422 mit klarer
  Fehlermeldung.
- Mindestens 2 Teams pro Gruppe.

### Was passiert beim Upload?

`GroupGeneratorService::importFromCsv()` läuft in einer
DB-Transaktion:

1. **Alle bisherigen Matches, Gruppen und Teams** des Turniers werden
   gelöscht. Tische bleiben erhalten, denn sie sind physische
   Spielflächen.
2. Pro Spalte wird eine Gruppe (`Gruppe A`, `Gruppe B`, …) am
   passenden Tisch (in Tisch-`id`-Reihenfolge) angelegt.
3. Die Teamzellen werden in der CSV-natürlichen Reihenfolge auf die
   Gruppen verteilt (`cell[index]` → Gruppe `index % Spaltenanzahl`).
4. Jedem Team wird automatisch eine Farbe aus einer 8er-Palette
   zugewiesen (zyklisch). Wer individuelle Farben braucht, kann die
   Teams nach dem Import nicht mehr editieren — dafür müsste das
   Turnier zurückgesetzt werden.
5. Innerhalb jeder Gruppe wird das Round-Robin-Set an
   `pending`-Matches erzeugt (jedes Team gegen jedes andere genau
   einmal).
6. Der Tournament-Status springt auf `group`.

Danach landet der Admin direkt auf `/matches` — die Schiris können
sofort loslegen.

> ⚠️ **Achtung:** Beim Upload werden **alle existierenden Teams
> ersetzt**. Wer schon Teams + Farben in der Setup-UI angelegt hat,
> verliert diese durch den Import. Die Warnung steht auch im Modal.

### Wann sinnvoll, wann nicht?

- **Sinnvoll**, wenn die Auslosung manuell vorab gemacht wurde (z. B.
  Setz-Verfahren, Wunsch-Pairings, fester Modus aus einer Vorrunde)
  oder die Teamliste sowieso schon als CSV vorliegt.
- **Nicht sinnvoll**, wenn man die Teams einfach zufällig verteilen
  möchte — dafür ist der Standard-Pfad **Gruppen generieren** (siehe
  unten) bequemer.

---

## Gruppen generieren

Sobald mindestens 1 Tisch und mindestens 2 Teams angelegt sind, wird
unten der goldene Bereich **Bereit zum Auslosen** aktiv.

### Vorschau

Ein Klick auf **Gruppen generieren →** öffnet zuerst eine
_Vorschau-Modal_. Sie zeigt die geplante Verteilung **deterministisch
in `id`-Reihenfolge** — diese Vorschau dient also nur dazu, das
Konzept zu zeigen („so viele Teams pro Tisch"), nicht die spätere
echte Verteilung. Die tatsächliche Generierung weiter unten verteilt
die Teams mit `inRandomOrder()`.

### Tatsächliche Generierung

Nach Bestätigung läuft `GroupGeneratorService::generate()`:

1. Bisherige Matches und Gruppen werden gelöscht (innerhalb einer
   DB-Transaktion).
2. Pro Tisch wird eine Gruppe erzeugt – Name `Gruppe A`, `Gruppe B`,
   … nach Tisch-Reihenfolge.
3. Die Teams werden in zufälliger Reihenfolge über alle Tische gleich
   verteilt (Round-Robin via `index % count(tables)`).
4. Innerhalb jeder Gruppe wird _jedes Team gegen jedes andere genau
   einmal_ als `pending` Match angelegt (Bierpong-Round-Robin).
5. Der Tournament-Status springt auf `group`.

Danach wird der Admin direkt auf `/matches` umgeleitet — die
Schiedsrichter können loslegen.

### Konsequenz

- Sobald die Gruppenphase generiert ist, lässt sich die Setup-Seite
  zwar weiter aufrufen, aber Tische/Teams können nicht mehr verändert
  werden.
- Tournament-Name und Match-Dauer bleiben editierbar.

---

## Platzierungsspiele

Nur relevant, wenn die Sonderregel **Platzierungsspiele austragen**
aktiv ist (siehe [Sonderregeln](Sonderregeln.md#platzierungsspiele-austragen)).
Dann schiebt sich zwischen Gruppen- und KO-Phase eine optionale Runde
für die Teams, die die KO-Phase _nicht_ erreichen.

Ablauf:

1. Sind alle Gruppenspiele beendet, erscheint im Setup — sofern
   mindestens 2 Teams nicht qualifiziert sind — der Block
   **Platzierungsspiele starten**.
2. `KoBracketService::startPlacementRound()` legt die Paarungen an
   (`phase = placement`) und setzt den Turnierstatus auf `placement`:
   die beiden Letzten der Gesamtwertung gegeneinander, die nächsten
   beiden darüber usw.
3. Die Spiele werden wie gewohnt in der Match-Steuerung ausgetragen.
   Beendete Platzierungsspiele überschreiben die Reihenfolge im
   Leaderboard (der Sieger einer Paarung nimmt den besseren Platz ein).
4. Sind alle Platzierungsspiele beendet, erscheint **KO-Phase starten**
   erneut und baut das Bracket aus den qualifizierten Teams.

Sind weniger als 2 Teams nicht qualifiziert, wird die Platzierungsrunde
übersprungen und es geht direkt von `group` nach `ko`.

---

## KO-Phase

Sobald **alle Gruppen-Matches beendet sind** (bzw. nach den
Platzierungsspielen), taucht der goldene Block
**KO-Phase starten** auf (`koPhaseReady`-Check). Er ist erst dann
sichtbar, wenn:

- `tournament.status === 'group'` und
- _keine_ Gruppenmatches mehr offen sind
  (`KoBracketService::isGroupPhaseComplete()`).

### Was passiert beim Start?

`KoBracketService::startKoPhase()` baut das initiale Bracket:

1. Pro Gruppe werden die Top 2 ermittelt (über
   `groupStandings()`, siehe unten).
2. Aus allen Gruppensiegern + allen Gruppen-Zweiten wird die
   Teilnehmerzahl bestimmt.
3. Die KO-Runden-Größe wird auf die nächste Zweierpotenz aufgerundet:
   `roundSize = 2^ceil(log2(max(participants, 2)))`.
   So entsteht ein normales Tournament-Bracket; ungerade
   Teilnehmerzahlen führen zu Freilosen (siehe „Bye-Slots" unten).
4. Die erste Runde wird **cross-bracket** gepaart: Gruppensieger `i`
   gegen Gruppen-Zweiten `(count - 1 - i)`. Damit treffen
   z. B. Sieger A auf Zweiter D und nicht direkt auf Zweiter A.
5. Tische werden im Round-Robin verteilt (`matchIndex % count(tables)`).
6. Status springt auf `ko`.

### Bye-Slots

Wenn z. B. 5 Teams ins KO einziehen, wird die nächste Zweierpotenz
(8) als Bracket-Größe genommen. Es entstehen aber nur die Matches,
die _explizit_ paarbar sind:

- Sieger ohne passenden Gegner (weil zu wenig Zweite) wird _nicht_
  in ein Match gepackt.
- Die Folgerunde wird erst dann angelegt, wenn das erste tatsächlich
  spielbare Match beendet ist (über `advanceKoWinner`).

Das bedeutet praktisch: Freilose kommen automatisch in die nächste
Runde durch, sobald ihr Gegner-Match endet — und werden vom UI als
"Sieger Vorrunde" angezeigt, bis dahin.

> **Bewusste Einschränkung:** das KO-Bracket im aktuellen Stand setzt
> Top 1 und Top 2 jeder Gruppe ins KO. Mehr Setzplätze pro Gruppe
> sind nicht konfigurierbar.

### Tiebreaker in der Gruppenphase

Welches Team Top 1 / Top 2 einer Gruppe ist, entscheidet
`KoBracketService::groupStandings()` mit dieser Reihenfolge:

1. **Punkte** – aus dem Pivot, 3 pro Sieg.
2. **Direkter Vergleich** – Punkte aus Spielen, an denen _alle_
   gleichplatzierten Teams beteiligt sind. Bei einem 2-er-Patt also
   schlicht „wer hat das direkte Match gewonnen". Bei 3-er-Patt wird
   eine Mini-Tabelle nur über die direkten Spiele dieser drei Teams
   gerechnet.
3. **Cup-Differenz** – `cups_scored_total − cups_conceded_total`.
4. **Getroffene Cups** – `cups_scored_total`.
5. **Fallback** – stabile Sortierung nach `team.id`. Eigentlich sehen
   die offiziellen Bierpong-Regeln hier ein „Entscheidungsspiel" vor;
   weil das eine manuelle Aktion ist und die App keine
   Verlängerungs-UI hat, behalten wir uns hier eine deterministische
   Reihenfolge vor.

### KO-Vorwärtsschaltung

Sobald ein KO-Match beendet wird, ruft
`MatchResultService::saveResult()` automatisch
`KoBracketService::advanceKoWinner()` auf. Damit:

- entsteht (falls noch nicht vorhanden) das Match der nächsten Runde
  mit `firstOrCreate`,
- der Sieger wird je nach Position auf die Home- oder Away-Seite
  gesetzt:
    - gerade `ko_position` → Home,
    - ungerade `ko_position` → Away.

War das Match das Finale (`ko_round === 1`), wird der Turnierstatus auf
`finished` gesetzt.

### Unentschieden in der KO-Phase

Im Default-Modus (**Automatisch**) gibt es _keine_ extra Verlängerung.
Auch in der KO-Phase greifen dann die Tiebreaker aus
`MatchResultService::determineWinner()`:

1. Mehr Cups → Sieger.
2. Bei Cup-Gleichstand: weniger Würfe → Sieger.
3. Bei Würfe-Gleichstand: weniger Strafbecher → Sieger.
4. Sonst: Heimteam gewinnt (Fallback).

Ist hingegen die Sonderregel **Bestimmung des Siegers in der KO-Phase**
auf **Sudden Death** gestellt, wählt bei Becher-Gleichstand der Schiri
das siegreiche Team selbst aus (siehe
[Sonderregeln](Sonderregeln.md#bestimmung-des-siegers-in-der-ko-phase)).

Mehr Details dazu in [Matches – Wertungslogik](Matches.md#wertungslogik-im-detail).

---

## Turnier zurücksetzen

In jedem Status außer `setup` taucht oben rechts der Button
**Turnier zurücksetzen** auf. Er öffnet eine Bestätigungs-Modal mit
folgender Wirkung:

- **Alle Matches** des Turniers werden gelöscht.
- **Alle Gruppen** und ihre Team-Zuordnungen werden gelöscht.
- **Status** geht zurück auf `setup`.

**Was bleibt:**

- Tische,
- Teams,
- Turnier-Einstellungen (Name, Match-Dauer),
- Schiri-Accounts (die liegen in `users`, nicht am Turnier).

Diese Aktion ist praktisch, wenn z. B. eine Gruppenphase mit einer
ungünstigen Auslosung gestartet wurde und neu gemischt werden soll —
ohne dass man Tische und Teams neu eintragen muss.

> ⚠️ Es gibt keine Undo-Funktion. Wer Statistiken aus einer alten
> Turnier-Variante behalten will, sollte vor dem Zurücksetzen einen
> Screenshot der Statistik-Seite machen.

---

## Archiv

Das **Archiv** (`/archiv`, nur Admin) macht vergangene Turniere wieder
einsehbar. Der Sidebar-Eintrag erscheint erst, **sobald mehr als ein
Turnier existiert** (sonst gibt es nichts zu archivieren).

- **Was zählt als „vergangen"?** Alle Turniere _außer_ dem aktuellen
  (= dem zuletzt angelegten). Das aktive Turnier bleibt über die
  normale App erreichbar und taucht im Archiv daher nicht auf.
- **Übersicht.** Ein Klick auf **Archiv** öffnet die Liste der
  vergangenen Turniere (neuestes zuerst) mit Datum, Team- und
  Match-Zahl.
- **Detailansicht.** Die Auswahl eines Turniers öffnet eine Seite mit
  einem Tab-Umschalter zwischen **Wertung** (KO-Bracket + Leaderboard)
  und **Statistik** — exakt die öffentlichen Ansichten, aber
  **schreibgeschützt** und ohne Live-Polling. Der Aufruf des _aktuellen_
  Turniers über die Archiv-URL wird mit 404 abgewiesen.
- **Kein zweites Datenmodell.** Es gibt bewusst _keine_ Archivtabelle:
  Turnierdaten werden nie turnierübergreifend gelöscht (Reset und
  CSV-Import betreffen nur das eigene Turnier), daher _sind_ die
  bestehenden Tabellen das Archiv. Die Detailseite verwendet dieselben
  Livewire-Komponenten wie Dashboard und öffentliche Seite, nur mit
  fixiertem `tournament_id` und abgeschaltetem Polling.

## Schiris anlegen

Nicht im Setup, sondern unter **Einstellungen** (Profil-Dropdown).
Siehe [Einstellungen.md](Einstellungen.md#schiris-anlegen-und-verwalten-admin).

---

## Datenmodell-Querverweis

- `tournaments` — ein Turnier, mit `status` und beiden Match-Dauern.
- `tables` — Tische, je Turnier; zugleich Gruppen-Container.
- `teams` — Teams, je Turnier.
- `groups` — wird beim Generieren erzeugt, 1 pro Tisch.
- `group_team` — Pivot mit den Tabellenständen (Punkte, W/L, Cups).
- `matches` — alle Spiele (Phase `group`, `placement` oder `ko`).
- `match_stats` — je Match _pro Team_ eine Zeile mit Würfen,
  Strafbechern, getroffenen Bechern und Dauer.
