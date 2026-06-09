# Sonderregeln & Einstellungen

Die Seite **Sonderregeln & Einstellungen** (`/sonderregeln`) ist über
den Sidebar-Eintrag mit dem 🍺-Icon erreichbar. Sie ist die zentrale
Stelle für turnierweite Konfiguration: klassische Einstellungen
(Name, Match-Dauer) und Sonderregeln, die das Spielverhalten und die
Statistik beeinflussen.

> 🔒 Diese Seite ist hinter `middleware('role:admin')` versteckt.
> Schiedsrichter sehen den Sidebar-Eintrag nicht und erhalten beim
> direkten Aufruf einen HTTP 403.

## Auf welchem Turnier wirken die Änderungen?

Die Seite lädt — wie das Setup — immer das _zuletzt angelegte_
Turnier (`Tournament::latest()`). Ein UI zum Wechseln zwischen
Turnieren gibt es nicht. Existiert noch kein Turnier, wird automatisch
eines mit Default-Werten angelegt.

---

## Einstellungen-Block

Drei Felder, identisch zu den ursprünglich im Setup verankerten
Einstellungen:

- **Turniername** – beliebiger String, max. 255 Zeichen.
- **Gruppen-Match (Min)** – Match-Dauer in Minuten für alle
  Gruppenspiele. Default 10, Range 1–60.
- **KO-Match (Min)** – Match-Dauer für KO-Spiele. Default 15,
  Range 1–60.

Diese Werte werden zur Laufzeit eines Spiels vom Timer in der
Match-Steuerung eingelesen. Änderungen wirken sich also _sofort_ auf
das nächste startende Spiel aus. Bereits laufende Matches benutzen den
Wert, der zum Zeitpunkt des Starts galt.

---

## Sonderregeln

Die Sonderregeln verändern, wie das Turnier gespielt und ausgewertet
wird. Aktuell gibt es genau eine Regel.

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

---

## Speichern

Es gibt **einen einzelnen Speichern-Button** am Ende der Seite. Er
persistiert sowohl die klassischen Einstellungen als auch die
Sonderregeln in einem Rutsch. Bei Erfolg wird ein Toast
"Einstellungen gespeichert." eingeblendet.

Validierung:

- `tournamentName` ist Pflichtfeld, max. 255 Zeichen.
- `groupMinutes` / `koMinutes` müssen ganzzahlig zwischen 1 und 60
  liegen.

---

## Datenmodell-Querverweis

- `tournaments.name` — Turniername.
- `tournaments.group_match_duration_minutes` — Match-Dauer Gruppe.
- `tournaments.ko_match_duration_minutes` — Match-Dauer KO.
- `tournaments.count_throws` — Boolean, Default `true`. Steuert die
  Würfe-Erfassung und die Sichtbarkeit der wurf-basierten Statistiken.
