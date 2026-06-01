# Öffentliche Ansicht

Die öffentliche Turnier-Seite hat keinen eigenen Sidebar-Eintrag — sie
ist nur über den **Public-Link**-Button im Dashboard erreichbar
(siehe [Dashboard.md](Dashboard.md#kopfbereich)) bzw. direkt über die
URL:

```
/t/{public_token}
```

Sie ist die Bühne fürs Publikum: kein Login, kein Schiri-Tool, sondern
eine reine Anzeige.

## Was zeigt sie?

Die View wird vom `PublicTournamentController` gerendert. Sichtbar
sind, in dieser Reihenfolge:

1. **Header** – Phase (`Vorbereitung` / `Gruppenphase` / `KO-Phase` /
   `Beendet`), Turniername, ein Lauf-Counter („Läuft seit …") und
   eine Fortschrittsleiste in Gold („X / Y Matches gespielt").
2. **Champion-Banner** – nur nach Turnierende. Großer Sieger-Name in
   Gold, dazu ein Satz „Hat sich durch X Matches nach oben getrunken".
3. **Live-Matches & Bracket** – embedded via
   `livewire:pages::tournament-bracket`. Diese Komponente zeigt:
    - laufende Spiele als „Red Corner vs Blue Corner"-Karten,
    - Gruppenphase-Tabellen pro Tisch (Top 2 = farbig, Rest gedimmt),
    - KO-Bracket mit Verbindern.
4. **Leaderboard** – turnierweit, gleiche Sortierung wie auf dem
   Dashboard.
5. **Bilanz / Fun-Stats** – nur nach Turnierende. Gleiche Kachel-
   Auswahl wie auf der Statistik-Seite, aber ohne Tooltip-Icons (das
   Publikum braucht keine Erklärung jedes Tile-Namens).

## Polling

- Das eingebettete **Bracket-Widget** pollt alle 10 Sekunden
  (`wire:poll.10s`) — schnell genug, dass ein neuer KO-Sieger nach
  wenigen Sekunden bei den Zuschauenden auftaucht.
- Das eingebettete **Leaderboard** pollt alle 15 Sekunden.
- Der Header-Lauf-Counter rechnet im Browser (Alpine) alle 30
  Sekunden hoch und braucht dafür keinen Server-Roundtrip.

## Der `public_token`

Beim Anlegen eines Turniers wird automatisch ein UUID-Token erzeugt
(`Tournament::booted()`). Er steckt in der URL und ist die einzige
Zugangsvoraussetzung. Aktuelle Eigenschaften:

- **Nicht rotierbar.** Wer den Link kennt, kann das Turnier dauerhaft
  einsehen.
- **Nur Lesen.** Keine Aktion auf der Seite ändert Daten.
- Kein UI zum Neugenerieren. Wenn der Token kompromittiert werden
  sollte, ist der direkte Weg ein UPDATE in der `tournaments`-Tabelle.

> Praktischer Workflow: Den Link auf einen QR-Code legen und auf den
> Beamer projizieren. Wer das Match auf dem Handy mitverfolgen will,
> scannt den Code; alle anderen schauen einfach auf den Beamer.

## Status-bedingte Sichtbarkeit

| Phase | Was die öffentliche Seite zeigt |
|---|---|
| `setup` | Nur Header + Hinweis aus dem Bracket-Widget („Hier wird gleich gespielt"). Keine Matches, keine Tabellen. |
| `group` | Header mit Fortschritt, Live-Matches (falls vorhanden), Gruppen-Tabellen. KO-Bracket ist leer. |
| `ko` | Wie `group`, plus voll gerendertes KO-Bracket. Gruppen-Tabellen bleiben sichtbar (jeweils Top 2 hervorgehoben). |
| `finished` | Champion-Banner, Bracket, Leaderboard, Fun-Stats. Lauf-Counter wird durch „Letzte Runde gespielt" ersetzt. |

## Login-Eingang

Im Header der öffentlichen Seite steht oben rechts ein **Schiri-Login**-
Button (bzw. **Dashboard**, falls die Person ohnehin schon eingeloggt
ist). Wer das Turnier nur ansehen will, ignoriert ihn einfach.

## Vergleich zur Schiri-Seite

| Element | Schiri-UI (Dashboard) | Öffentliche Seite (`/t/{token}`) |
|---|---|---|
| Login nötig? | Ja | Nein |
| Match-Steuerung | Anklickbar | Read-only |
| Tooltips bei Fun-Stats | Ja | Nein |
| Setup-Aktionen | Admin-only | Nicht vorhanden |
| Live-Timer pro Match | Sekundenpoll | Nur Status-Badge |
| Lauf-Counter (Turnier seit) | Nein | Ja |

## Was tun, wenn die Seite leer aussieht?

- **Status `setup`, keine Gruppen** – Admin hat noch nicht
  ausgelost. Das Publikum muss noch warten.
- **`Tournament not found` / 404** – Token in der URL ist falsch
  oder das Turnier wurde gelöscht (passiert nur manuell in der DB,
  *nicht* durch „Turnier zurücksetzen" — das behält das Turnier mit
  demselben Token).
- **Leaderboard leer trotz Gruppenphase** – kein Match ist
  abgeschlossen. Sobald das erste fertig ist, befüllt sich die Tabelle
  beim nächsten Poll.
