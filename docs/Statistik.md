# Statistik

Die Statistik-Seite (`/statistics`) wertet das aktuelle Turnier aus.
Sie wird aus `App\Services\StatisticsService::summary()` gespeist und
zeigt sowohl den Champion-Banner (wenn das Turnier beendet ist) als
auch eine Reihe „Fun-Stats" über das ganze Turnier.

> 🔒 Sichtbar nur für Admins — der Sidebar-Eintrag steht hinter
> `middleware('role:admin')`. Auf der öffentlichen Turnier-Seite
> (siehe [Öffentliche-Ansicht.md](Öfentliche-Ansicht.md)) erscheinen
> die gleichen Stats nach Turnierende.

## Inhaltsverzeichnis

- [Polling](#polling)
- [Champion](#champion)
- [Fun-Stats](#fun-stats)
  - [Schärfste Schützen](#schärfste-schützen)
  - [Wasserspeier](#wasserspeier)
  - [Blitzsieg](#blitzsieg)
  - [Marathonspieler](#marathonspieler)
  - [Knapper Krimi](#knapper-krimi)
  - [Strafbechermagnet](#strafbechermagnet)
  - [Effizienzrate](#effizienzrate)
  - [Schluck-Olymp](#schluck-olymp)
  - [Wurfkönig](#wurfkönig)
- [Cups gesamt](#cups-gesamt)
- [Wenn alles leer ist](#wenn-alles-leer-ist)
- [Beziehung zu anderen Sidebar-Punkten](#beziehung-zu-anderen-sidebar-punkten)

## Polling

Die Seite pollt alle **30 Sekunden** neu (`wire:poll.30s`). Das reicht,
weil sich Statistiken nur dann ändern, wenn ein Match abgeschlossen
wird — und das passiert nicht im Sekundentakt.

---

## Champion

Erscheint nur, wenn das Turnier den Status `finished` hat.

- **Quelle:** Match mit `phase = ko`, `ko_round = 1`, `status =
  finished`, dazu der `winnerTeam`.
- **Anzeige:** Großes goldenes Banner mit Teamname (und Farbe als
  Streifen) und Total-Cup-Counter.

Vor Turnierende wird der Champion-Bereich nicht gerendert — auch nicht
mit einem Platzhalter.

---

## Fun-Stats

Acht Kacheln, die immer dann gezeigt werden, wenn die zugehörige
Berechnung mindestens ein Match findet, plus die optionale Kachel
**Wurfkönig** (nur bei aktivierter Sonderregel). Reihenfolge und Inhalt
sind fest verdrahtet.

Hinter jedem Label sitzt ein **Info-i** mit einer Tooltip-Erklärung,
damit Schiris im Live-Modus die Bedeutung ablesen können.

### Schärfste Schützen

> Höchste Trefferquote über alle Matches — getroffene Becher pro Wurf.

- Aggregiert pro Team: Summe `cups_scored` und Summe `throws` über alle
  beendeten Matches.
- Quote = `scored / throws`.
- Teams ohne einen einzigen Wurf werden ausgeschlossen
  (Filter `throws > 0`).
- Anzeige: Team mit der höchsten Quote, dazu Prozentwert (1 Nachkomma)
  und das absolute Verhältnis `getroffen / geworfen`.

### Wasserspeier

Spiegelbild von „Schärfste Schützen": Team mit der **niedrigsten**
Quote, gleicher Filter `throws > 0`. Anzeige nur als Prozentwert.

### Blitzsieg

> Schnellster Sieg in der Gruppenphase, gemessen an der Spieldauer.

- Schaut nur auf **Gruppen-Matches** mit gesetztem `winner_team_id`.
- Für das siegreiche Team wird `match_stats.duration_seconds` gelesen.
- Das Match mit der kürzesten Dauer gewinnt.
- Anzeige: Sieger, Dauer als `MM:SS`, Gegner.

Warum nur Gruppenphase? Weil KO-Spiele eine längere Standard-Dauer
haben (`ko_match_duration_minutes` vs. `group_match_duration_minutes`)
und ein direkter Vergleich verzerrt wäre.

### Marathonspieler

> Längstes Match nach Dauer — die zwei Teams haben sich am längsten
> duelliert.

- Schaut auf alle beendeten Matches (Gruppen _und_ KO).
- Nimmt das Maximum von `duration_seconds` über beide
  `match_stats`-Zeilen eines Matches (Werte sollten identisch sein,
  aber `max()` ist defensiv).
- Anzeige: beide Teamnamen + Dauer.

### Knapper Krimi

> Engster Match-Ausgang im Turnier — geringste Becher-Differenz, bei
> Gleichstand das Match mit den meisten Bechern.

- Berechnet pro Match: `|home.cups_scored − away.cups_scored|`.
- Bei Gleichstand der Differenz gewinnt das Match mit der höchsten
  Cup-Summe — Begründung: mehr Cups = mehr Action.
- Anzeige: Sieger zuerst, dann Verlierer, Ergebnis aus Sicht des
  Siegers, plus Differenz oder „Patt" falls 0.

> Hinweis: „Patt" als Anzeige erscheint nur in dem extrem seltenen Fall,
> dass beide Teams gleiche Cups haben — der eigentliche Sieger wurde
> dann über Würfe → Strafe → Heim-Fallback bestimmt
> ([Matches – Wertungslogik](Matches.md#wertungslogik-im-detail)).

### Strafbechermagnet

> Team mit den meisten Strafbechern über das gesamte Turnier.

- Summe `penalty_cups` pro Team über alle beendeten Matches.
- Erscheint nur, wenn mindestens ein Strafbecher vergeben wurde
  (`penalty_cups > 0`). Sonst wird die Kachel komplett ausgeblendet.

### Effizienzrate

> Beste Wurf-Bilanz: (Treffer − Strafbecher) pro Wurf, als Prozentwert.

- Formel: `((scored − penalty) / throws) * 100`.
- Wer einen Becher trifft, sich aber gleichzeitig einen Strafbecher
  einhandelt (z. B. weil der eigene Ball den Strafbecher des Gegners
  trifft), wird hier neutralisiert. Die Kachel honoriert also nicht
  nur Trefferquote, sondern „saubere" Treffer.
- Aggregiert wie „Schärfste Schützen", gleicher Filter `throws > 0`.
- Anzeige: höchste Effizienz mit Prozentwert.

### Schluck-Olymp

> Team, das im Turnier am meisten getrunken hat: gegnerische Treffer +
> eigene Strafbecher + bei Niederlagen die übrigen Becher auf der
> Gegnerseite (Verlierer-Strafe).

Pro Match wird für jedes Team gezählt:

- **Gegnerische Treffer** — `opponent.cups_scored`. Logik: jeder
  gegnerische Treffer landet einen eigenen Becher, der dann getrunken
  werden muss.
- **Eigene Strafbecher** — `own.penalty_cups`. Strafbecher gehen immer
  ans eigene Team.
- **Verlierer-Strafe** — falls das Team verloren hat, addiere die
  absolute Cup-Differenz `|home.cups_scored − away.cups_scored|`. Das
  entspricht der Bierpong-Konvention, dass der Verlierer am Ende die
  verbliebenen Becher auf der Gewinnerseite leeren muss.
- Anzeige: Team mit der höchsten Summe, dazu Anzahl Becher.

Erscheint nur, wenn ein Team echte Becher gesammelt hat (`cups > 0`).

> Diese Berechnung kommt ohne explizite „Cup-pro-Seite"-Konfiguration
> aus: die Verlierer-Strafe wird über die _tatsächliche_ Differenz
> abgeleitet, nicht über eine fixe Annahme „immer 10 Cups". Wenn ihr an
> einigen Tischen mit 6 statt 10 Bechern spielt, bleibt das korrekt.

### Wurfkönig

> Einzelspieler mit den meisten getroffenen Bechern über das gesamte
> Turnier.

- **Nur sichtbar, wenn die Sonderregel „Wurfkönig ermitteln" an ist**
  (`tournaments.determine_cup_king`). Ist sie aus, wird die Kachel — wie
  die zugrunde liegende Tabelle — gar nicht angefasst.
- Quelle: `match_member_cups`, gefüllt über das Modal **Getroffene
  Becher verteilen** nach jedem Spiel (siehe
  [Sonderregeln – Wurfkönig ermitteln](Sonderregeln.md#wurfkönig-ermitteln)).
- Berechnung: Summe `cups_hit` je Spieler über alle Matches des
  Turniers, absteigend; der Spieler mit der höchsten Summe gewinnt.
- Anzeige: Spielername, dazu Gesamt-Becher und Teamname.
- Erscheint nur, wenn überhaupt Becher verteilt wurden (Summe > 0).

---

## Cups gesamt

Footer-Zeile unter den Kacheln: Summe aller `cups_scored` über alle
beendeten Matches und alle Teams. Steht auch im Champion-Banner (wenn
sichtbar).

---

## Wenn alles leer ist

Solange im aktuellen Turnier _kein_ Match auf `finished` ist und _kein_
Champion feststeht, zeigt die Seite nur:

> Noch keine Daten. Statistiken erscheinen sobald Matches gespielt wurden.

Sobald das erste Match endet, füllt sich die Seite beim nächsten Poll.

## Beziehung zu anderen Sidebar-Punkten

- Alle Werte stammen aus den `match_stats`-Zeilen, die beim
  Abschließen eines Matches in der **Match-Steuerung** entstehen.
- Die öffentliche Turnier-Seite zeigt dieselbe Kachel-Auswahl, nach
  dem Turnierende, ohne Tooltips — siehe
  [Oeffentliche-Ansicht.md](Öfentliche-Ansicht.md).
