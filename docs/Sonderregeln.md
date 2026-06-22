# Sonderregeln & Einstellungen

Die Seite **Sonderregeln & Einstellungen** (`/sonderregeln`) ist über
den Sidebar-Eintrag mit dem 🍺-Icon erreichbar. Sie ist die zentrale
Stelle für turnierweite Konfiguration: klassische Einstellungen
(Name, Match-Dauer) und Sonderregeln, die das Spielverhalten und die
Statistik beeinflussen.

> 🔒 Diese Seite ist hinter `middleware('role:admin')` versteckt.
> Schiedsrichter sehen den Sidebar-Eintrag nicht und erhalten beim
> direkten Aufruf einen HTTP 403.

## Inhaltsverzeichnis

- [Auf welchem Turnier wirken die Änderungen?](#auf-welchem-turnier-wirken-die-änderungen)
- [Einstellungen-Block](#einstellungen-block)
- [Sonderregeln](#sonderregeln)
  - [Würfe zählen](#würfe-zählen)
  - [Platzierungsspiele austragen](#platzierungsspiele-austragen)
  - [Bestimmung des Siegers in der KO-Phase](#bestimmung-des-siegers-in-der-ko-phase)
  - [Wurfkönig ermitteln](#wurfkönig-ermitteln)
- [UI-Einstellungen](#ui-einstellungen)
  - [Farbige Kreise in den Urkunden ausblenden](#farbige-kreise-in-den-urkunden-ausblenden)
  - [Turnierplan anzeigen](#turnierplan-anzeigen)
- [Speichern](#speichern)
- [Datenmodell-Querverweis](#datenmodell-querverweis)

## Auf welchem Turnier wirken die Änderungen?

Die Seite lädt — wie das Setup — immer das _zuletzt angelegte_
Turnier (`Tournament::latest()`). Über diese Seite selbst lässt sich
das bearbeitete Turnier nicht wechseln; ein neues startet man im Setup
über **Neues Turnier**, vergangene Turniere liegen schreibgeschützt im
**Archiv** (siehe [Setup.md](Setup.md)). Existiert noch kein Turnier,
wird automatisch eines mit Default-Werten angelegt.

---

## Einstellungen-Block

Vier Felder, identisch zu den ursprünglich im Setup verankerten
Einstellungen:

- **Turniername** – beliebiger String, max. 255 Zeichen.
- **Gruppen-Match (Min)** – Match-Dauer in Minuten für alle
  Gruppenspiele. Default 10, Range 1–60.
- **KO-Match (Min)** – Match-Dauer für KO-Spiele. Default 15,
  Range 1–60.
- **Beschreibung** – Freitext (max. 1000 Zeichen), wird auf der
  Startseite unter dem Turniernamen angezeigt. Ist anfangs mit
  `Tournament::DEFAULT_DESCRIPTION` vorbelegt.

Die Dauer-Werte werden zur Laufzeit eines Spiels vom Timer in der
Match-Steuerung eingelesen. Änderungen wirken sich also _sofort_ auf
das nächste startende Spiel aus. Bereits laufende Matches benutzen den
Wert, der zum Zeitpunkt des Starts galt.

---

## Sonderregeln

Die Sonderregeln verändern, wie das Turnier gespielt und ausgewertet
wird. Aktuell gibt es vier Regeln: **Würfe zählen** (Schalter),
**Platzierungsspiele austragen** (Schalter), **Bestimmung des
Siegers in der KO-Phase** (Auswahl) und **Wurfkönig ermitteln**
(Schalter).

### Würfe zählen

Schalter (Switch), Default **an**. Steuert, ob die Schiris im laufenden
Match einen Würfe-Zähler bedienen müssen.

| Zustand | Auswirkung im Match | Auswirkung in der Statistik |
|---|---|---|
| an _(Default)_ | Würfe- und Strafbecher-Zähler sind während des Spiels sichtbar. Die Würfe werden in der Finished-Übersicht angezeigt (3-spaltige Stat-Leiste: Dauer · Würfe · Strafe). | Alle Statistiken verfügbar (inkl. Schärfste Schützen, Wasserspeier, Effizienzrate). |
| aus | Nur der Strafbecher-Zähler bleibt sichtbar. Die Stat-Leiste im Finished-Banner wechselt auf 2 Spalten (Dauer · Strafe). Der Hinweistext im Modal "Runde beenden?" passt sich an. | Die wurf-basierten Statistiken **Schärfste Schützen**, **Wasserspeier** und **Effizienzrate** werden ausgeblendet. Übrige Statistiken (Blitzsieg, Marathon, Knapper Krimi, Strafbechermagnet, Schluck-Olymp, Champion, Cups gesamt) bleiben unverändert. |

**Warum?** Wenn nur ein Schiri ein Match betreut, ist gleichzeitig
Würfe zählen, Strafbecher zählen und Becher zählen anstrengend.
"Würfe zählen aus" reduziert die manuelle Erfassungsarbeit auf das
Nötigste — der Wertungs- und Bracket-Mechanismus bleibt voll
funktionsfähig, da Sieger ausschließlich über die getroffenen Becher
ermittelt werden.

> Hinweis: Die Option ist eine reine Anzeige-/Erfassungsoption. Wurden
> in einem früheren Match Würfe gezählt, bleiben diese in der DB
> erhalten. Wird die Option später wieder eingeschaltet, erscheinen
> die wurf-basierten Statistiken automatisch wieder, sobald wieder
> Würfe in neuen Matches erfasst werden.

### Platzierungsspiele austragen

Schalter (Switch), Default **aus**. Steuert, ob Teams, die die KO-Phase
verpassen, ihre Endplatzierung noch ausspielen dürfen.

| Zustand | Auswirkung |
|---|---|
| aus _(Default)_ | Die Platzierungen unterhalb der KO-Plätze bleiben so, wie sie in der Gruppenphase erspielt wurden. Vom Status `group` geht es direkt in die KO-Phase. |
| an | Beim Klick auf **KO-Phase starten** wird – sofern mindestens 2 Teams die KO-Phase verpassen – zuerst eine **Platzierungsrunde** ausgetragen: Die beiden letzten der Gesamtwertung spielen gegeneinander, die nächsten beiden darüber usw. Das Turnier wechselt dafür in den Status `placement`. Erst wenn alle Platzierungsspiele beendet sind, baut ein erneutes **KO-Phase starten** das Bracket. |

Beendete Platzierungsspiele überschreiben die Reihenfolge im
Leaderboard: Der jeweilige Sieger eines Paarungs-Slots nimmt den
besseren der beiden Plätze ein. Details zum Phasen-Übergang in
[Setup – Platzierungsspiele](Setup.md#platzierungsspiele).

### Bestimmung des Siegers in der KO-Phase

Auswahl (Select), Default **Automatisch**. Legt fest, wie ein
KO-Spiel entschieden wird, das mit **gleicher Becherzahl** endet.

| Option | Verhalten bei Becher-Gleichstand im KO |
|---|---|
| Automatisch _(Default)_ | Der Gleichstand wird automatisch aufgelöst: weniger Würfe gewinnt, bei erneutem Gleichstand weniger Strafbecher, sonst Heimteam (Fallback). |
| Sudden Death (Auswahl durch Schiri) | Die Match-Steuerung blendet beim Speichern einen **Sudden-Death-Block** ein; der Schiri wählt das siegreiche Team aus (Pflichtauswahl, sonst Validierungsfehler). Das Finished-Banner vermerkt „Entschieden im Sudden Death". |

Die Einstellung wirkt **nur in der KO-Phase** und **nur bei
Becher-Gleichstand**. Eindeutige Ergebnisse werden immer über die
getroffenen Becher entschieden. Mehr dazu in
[Matches – Unentschieden in der KO-Phase](Matches.md#unentschieden-in-der-ko-phase).

### Wurfkönig ermitteln

Schalter (Switch), Default **aus**. Wenn an, werden die einzelnen
Spieler eines Teams erfasst und nach jedem Spiel die getroffenen Becher
auf sie verteilt. Die Statistik kürt daraus den **Wurfkönig** — den
Einzelspieler mit den meisten getroffenen Bechern über das ganze
Turnier.

> [!TIP]
> Diese Statisktik bedeutet einen Mehraufwand für den Schiri und ist daher standardmäßig aus
> Die Würfe pro Spieler können erst am Ende der Runde eingetragen werden und müssen zwischendurch auf Papier mitgezählt werden.
> Wenn die Schiris eh schon viel zu tun haben (und daher vielleicht auch "Würfe zählen" abgeschaltet wurde) ist diese Regel nicht 
> empfehlenswert.

Der Ablauf:

1. **Teammitglieder benennen.** Direkt unter dem Schalter erscheint —
   sobald er an ist — entweder ein Hinweis oder ein Button:
   - Solange das Turnier noch im Setup ist (`isSetup()`, Gruppen noch
     nicht ausgelost/importiert), steht dort der Hinweis, dass die
     Gruppen erst angelegt und zugelost werden müssen. Grund: Erst nach
     dem Auslosen stehen die Teams fest.
   - Danach erscheint der Button **Teammitglieder benennen**. Er öffnet
     ein Modal, in dem pro Team zwei Spieler eingetragen werden. (Die
     Tabelle erlaubt beliebig viele Mitglieder pro Team; die UI bietet
     der Einfachheit halber zwei Felder.) Erneutes Speichern ersetzt
     die bisherigen Mitglieder des Teams; leere Felder werden ignoriert.
2. **Becher verteilen.** Ist die Regel an, erscheint nach jedem
   **Ergebnis speichern** in der Match-Steuerung zusätzlich das Modal
   **Getroffene Becher verteilen**. Das Ergebnis ist zu diesem Zeitpunkt
   bereits gespeichert und das Sieger-Banner sichtbar — das Modal ist
   ein **Bonus** und blockiert nichts. Links und rechts stehen die
   beiden Teams mit ihren Spielern; je Spieler wird die getroffene
   Becherzahl eingetragen. Es gibt **keine** Summen-Validierung
   (Strafbecher müssen nicht aufgehen). Erneutes Speichern ersetzt die
   Verteilung für dieses Match.
3. **Statistik.** Die Kachel **Wurfkönig** auf der Statistik-Seite
   (und in der öffentlichen Ansicht) zeigt den Spieler mit den meisten
   getroffenen Bechern. Sie ist **nur sichtbar, wenn der Schalter an
   ist**.

**Robustheit:** Ist die Regel aus, wird die Verteil-Tabelle nie
angefasst — der gesamte übrige Ablauf ist identisch zu vorher. Sind
für ein Team (noch) keine Mitglieder benannt, zeigt das Verteil-Modal
für dieses Team einen Hinweis statt Eingabefeldern; das Werten von
Matches wird dadurch nie blockiert.

---

## UI-Einstellungen

Ganz unten auf der Seite liegt der ausklappbare Block **UI-Einstellungen**
(standardmäßig **zugeklappt**). Er sammelt sehr selten genutzte
Anzeige-Optionen, die das Aussehen von Ausgaben beeinflussen, aber nicht
den Spiel- oder Wertungsablauf. Gespeichert wird über den **gemeinsamen
Speichern-Button** ganz unten auf der Seite.

### Farbige Kreise in den Urkunden ausblenden

Schalter (Switch), Default **aus**. Steuert die beiden dekorativen
Eck-Kreise (rot/blau) auf den generierten Urkunden-PDFs.

| Zustand | Auswirkung auf die Urkunde |
|---|---|
| aus _(Default)_ | Die Urkunde zeigt links unten und rechts unten je einen angeschnittenen, dezent eingefärbten Kreis als Dekoelement (siehe `resources/views/pdf/certificates.blade.php`). |
| an | Die beiden Kreis-Elemente werden beim Rendern weggelassen; der restliche Aufbau (Gold-Hairlines, Typografie, Footer) bleibt unverändert. |

**Warum?** Manche Vereine drucken die Urkunden — entgegen der Erwartung
— auf marmoriertem oder farbigem Papier. Darauf wirken die farbigen
Kreise unruhig. Die Option blendet sie aus, ohne das übrige
Urkunden-Layout anzutasten.

> Hinweis: Die Einstellung wird beim **Generieren** der Urkunden im
> Setup ausgewertet (`Tournament::hide_certificate_circles` wird als
> `$hideCircles` an die PDF-View übergeben). Bereits heruntergeladene
> PDFs ändern sich nicht rückwirkend; einfach neu generieren.

### Turnierplan anzeigen

Schalter (Switch), Default **aus**. Blendet einen öffentlich sichtbaren
Tab **Turnierplan** neben **Regeln** im Header ein, der die geplante
Spielreihenfolge anzeigt.

| Zustand | Auswirkung |
|---|---|
| aus _(Default)_ | Kein Turnierplan-Tab. |
| an | Unter dem Schalter erscheint ein Textfeld **Spielreihenfolge**. Sobald dort etwas eingetragen und gespeichert wurde, erscheint öffentlich der Tab **Turnierplan** (`/turnierplan`). |

**Eingabeformat:** eine Zeile pro Spiel, Teams mit **Semikolon**
getrennt — z. B. `Team 1;Team 2`. Auf der öffentlichen Seite wird pro
nicht-leerer Zeile eine nummerierte Zeile erzeugt; mehrere Teams einer
Zeile werden mit „vs“ verbunden. Leere Zeilen und Zeilen ohne Inhalt
werden ignoriert (`Tournament::scheduleEntries()`).

> **Rein informativ.** Der Turnierplan beeinflusst die Match-Liste und
> den Spielablauf **nicht** — der Schiri kann weiterhin jedes Spiel zu
> jeder Zeit starten. Es ist eine grobe Orientierung für die
> Gruppenphase, damit die Spieler wissen, wann sie ungefähr dran sind.

Der eingetragene Text bleibt in der DB erhalten (`tournaments.schedule`)
und kann jederzeit im Textfeld geändert werden, solange die Option an
ist. Der Tab wird genau dann angezeigt, wenn `show_schedule` an ist
**und** mindestens ein gültiger Eintrag existiert
(`Tournament::hasPublicSchedule()`). Die Header (Start-, Regeln-,
Turnierplan-Seite und öffentliche Turnier-Ansicht) prüfen das zentral
über `Tournament::publicScheduleVisible()`; der Tab bezieht sich stets
auf das **zuletzt angelegte** Turnier.

---

## Speichern

Es gibt **einen einzelnen Speichern-Button** ganz unten auf der Seite —
unterhalb sowohl der Sonderregeln als auch des ausklappbaren Blocks
**UI-Einstellungen**. Er persistiert die klassischen Einstellungen, die
Sonderregeln und die UI-Einstellungen in einem Rutsch. Bei Erfolg wird
ein Toast "Einstellungen gespeichert." eingeblendet.

Validierung:

- `tournamentName` ist Pflichtfeld, max. 255 Zeichen.
- `description` ist Pflichtfeld, max. 1000 Zeichen.
- `groupMinutes` / `koMinutes` müssen ganzzahlig zwischen 1 und 60
  liegen.
- `koWinnerMode` muss `auto` oder `sudden_death` sein.
- `schedule` ist optional, max. 5000 Zeichen.

Die Schalter `countThrows`, `playPlacementMatches`,
`determineCupKing`, `hideCertificateCircles` und `showSchedule` sind
Booleans und werden ohne weitere Validierung übernommen. Das Benennen der Teammitglieder und das Verteilen der
Becher laufen über eigene Buttons/Modals und sind vom zentralen
Speichern-Button unabhängig.

---

## Datenmodell-Querverweis

- `tournaments.name` — Turniername.
- `tournaments.description` — Freitext-Beschreibung für die Startseite.
- `tournaments.group_match_duration_minutes` — Match-Dauer Gruppe.
- `tournaments.ko_match_duration_minutes` — Match-Dauer KO.
- `tournaments.count_throws` — Boolean, Default `true`. Steuert die
  Würfe-Erfassung und die Sichtbarkeit der wurf-basierten Statistiken.
- `tournaments.play_placement_matches` — Boolean, Default `false`.
  Steuert, ob vor der KO-Phase Platzierungsspiele ausgetragen werden.
- `tournaments.ko_sudden_death` — Boolean, Default `false`. `true`
  entspricht der Select-Option „Sudden Death", `false` der Option
  „Automatisch".
- `tournaments.determine_cup_king` — Boolean, Default `false`. Steuert,
  ob Teammitglieder erfasst, nach jedem Spiel Becher verteilt und die
  Wurfkönig-Statistik angezeigt werden.
- `tournaments.hide_certificate_circles` — Boolean, Default `false`.
  `true` lässt die dekorativen Eck-Kreise auf den Urkunden-PDFs weg
  (für marmoriertes/farbiges Papier).
- `tournaments.show_schedule` — Boolean, Default `false`. Schaltet den
  öffentlichen Turnierplan-Tab frei (zusammen mit `schedule`).
- `tournaments.schedule` — Text (nullable). Freitext-Spielreihenfolge,
  eine Zeile pro Spiel, Teams mit Semikolon getrennt. Wird über
  `Tournament::scheduleEntries()` in Zeilen zerlegt.
- `team_members` — Spieler eines Teams (`team_id`, `name`). Wird nur
  bei aktivierter Regel befüllt.
- `match_member_cups` — getroffene Becher je Spieler und Match
  (`match_id`, `team_member_id`, `cups_hit`; eindeutig pro
  Match+Spieler). Die Wurfkönig-Statistik summiert `cups_hit` je
  Spieler über das Turnier.
