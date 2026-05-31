# pongtable — Bierpong-Turnierverwaltung
## Vollständige Projektspezifikation für Claude Code

---

## 1. Projektziel

`pongtable` ist eine selbst hostbare Web-App zur Verwaltung von Bierpong-Turnieren. Sie unterstützt mehrere Tische parallel, eine Gruppenphase mit automatischer Bracket-Generierung, eine KO-Phase sowie detaillierte Spielstatistiken. Die Bedienung durch Schiedsrichter soll schnell und mobiltauglich sein.

---

## 2. Tech-Stack

| Schicht | Technologie | Begründung |
|---|---|---|
| Backend | Laravel 13 | Gute Cloud-Unterstützung, Eloquent ORM, Auth out-of-the-box |
| Frontend-Reaktivität | Livewire 3 | Kein separates JS-Framework, direkt mit Blade/Laravel |
| WebSockets / Echtzeit | Laravel Reverb | Offizieller WebSocket-Server, nativ in Laravel Cloud unterstützt |
| CSS | Lightning CSS (via Vite-Plugin) | Modern, schnell, kein Tailwind-Overhead |
| Datenbank (lokal) | SQLite | Zero-Config für Entwicklung |
| Datenbank (Produktion) | MySQL / PlanetScale | Laravel Cloud kompatibel |
| Hosting | Laravel Cloud | Einfaches Deployment, Reverb nativ |
| Icons | Heroicons (via Blade-Komponente) | Passt zu Laravel-Ökosystem |

---

## 3. Rollen & Berechtigungen

```
public      Kein Login erforderlich. Zugang kann direkt über die Startseite erfolgen (im aktuellen Setup die Datei auf der "Let's get started
Laravel has an incredibly rich ecosystem.
We suggest starting with the following." steht). Diese Seite ist zu Testzwecken unter https://bierpongwm.test erreichbar.
            Kann sehen: Turnierplan, Gruppen, KO-Bracket, Ergebnisse, Leaderboard.
            Kann NICHT: Scores eintragen, Runden starten, Teams verwalten.

referee     Login erforderlich (Name + Passwort).
            Kann sehen: alles wie public + Match-Verwaltungsseite.
            Kann: eine laufende Runde starten, Pre-Match-Daten erfassen,
                  Post-Match-Scores eintragen, Runden beenden.
            Kann NICHT: Teams/Tische anlegen, Turnier starten/zurücksetzen.

admin       Erster registrierter Nutzer in der Instanz. Alle weiteren sind referee.
            Kann: alles. Teams und Tische anlegen, Gruppen generieren,
                  Turnier starten, KO-Bracket generieren, Instanz zurücksetzen,
                  Nutzer-Rollen verwalten, Turnier exportieren.
```

---

## 4. Datenbankschema

### 4.1 Migrations (Reihenfolge beachten)

```php
// tournaments
Schema::create('tournaments', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->uuid('public_token')->unique(); // für öffentlichen Link
    $table->enum('status', ['setup', 'group', 'ko', 'finished'])->default('setup');
    $table->unsignedInteger('group_match_duration_minutes')->default(10);
    $table->unsignedInteger('ko_match_duration_minutes')->default(15);
    $table->timestamps();
});

// tables (Tische)
Schema::create('tables', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
    $table->string('name'); // z.B. "Tisch 1", "Tisch Kellerbar"
    $table->timestamps();
});

// teams
Schema::create('teams', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->string('color')->nullable(); // Hex-Farbe für UI-Akzent
    $table->timestamps();
});

// groups (eine Gruppe je Tisch in der Gruppenphase)
Schema::create('groups', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
    $table->foreignId('table_id')->constrained()->cascadeOnDelete();
    $table->string('name'); // z.B. "Gruppe A", "Gruppe B"
    $table->timestamps();
});

// group_team (Pivot: welche Teams sind in welcher Gruppe + Gruppenphase-Statistik)
Schema::create('group_team', function (Blueprint $table) {
    $table->id();
    $table->foreignId('group_id')->constrained()->cascadeOnDelete();
    $table->foreignId('team_id')->constrained()->cascadeOnDelete();
    $table->unsignedInteger('points')->default(0);
    $table->unsignedInteger('wins')->default(0);
    $table->unsignedInteger('losses')->default(0);
    $table->unsignedInteger('cups_scored_total')->default(0);
    $table->unsignedInteger('cups_conceded_total')->default(0);
    $table->timestamps();
});

// matches
Schema::create('matches', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
    $table->enum('phase', ['group', 'ko']);
    $table->foreignId('group_id')->nullable()->constrained()->nullOnDelete();
    $table->unsignedInteger('ko_round')->nullable(); // 1=Finale, 2=Halbfinale, 4=Viertelfinale usw.
    $table->unsignedInteger('ko_position')->nullable(); // Position im Bracket (0-indexed)
    $table->foreignId('table_id')->constrained()->cascadeOnDelete();
    $table->foreignId('home_team_id')->constrained('teams')->cascadeOnDelete();
    $table->foreignId('away_team_id')->constrained('teams')->cascadeOnDelete();
    $table->foreignId('winner_team_id')->nullable()->constrained('teams')->nullOnDelete();
    $table->enum('status', ['pending', 'pre_entry', 'active', 'scoring', 'finished'])->default('pending');
    // pending    = noch nicht gestartet
    // pre_entry  = Schiri trägt Würfe + Strafbecher ein (vor Spielstart)
    // active     = Timer läuft (Spiel läuft)
    // scoring    = Zeit abgelaufen oder manuell beendet, Schiri trägt Becher ein
    // finished   = Ergebnis gespeichert
    $table->timestamp('started_at')->nullable();
    $table->timestamp('ended_at')->nullable();
    $table->timestamps();
});

// match_stats (eine Zeile pro Team pro Match)
Schema::create('match_stats', function (Blueprint $table) {
    $table->id();
    $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
    $table->foreignId('team_id')->constrained()->cascadeOnDelete();
    $table->unsignedInteger('cups_scored')->default(0);    // Getroffene Becher (Hauptmetrik)
    $table->unsignedInteger('throws')->default(0);         // Würfe (pre-match eingetragen)
    $table->unsignedInteger('penalty_cups')->default(0);   // Strafbecher (pre-match eingetragen)
    $table->unsignedInteger('duration_seconds')->nullable(); // Spieldauer
    $table->unique(['match_id', 'team_id']);
    $table->timestamps();
});

// users (Standard Laravel + Rolle)
// Ergänze die Standard-users Migration um:
$table->enum('role', ['admin', 'referee'])->default('referee');
```

### 4.2 Models & Relationships (Übersicht)

```
Tournament   hasMany Tables, Teams, Groups, Matches
             hat public_token (UUID, auto-generiert im Model-Boot)

Table        belongsTo Tournament
             hasMany Groups, Matches

Team         belongsTo Tournament
             belongsToMany Groups (via group_team)
             hasMany MatchStats

Group        belongsTo Tournament, Table
             belongsToMany Teams (via group_team)
             hasMany Matches

Match        belongsTo Tournament, Table, Group (nullable)
             belongsTo homeTeam, awayTeam, winnerTeam (via teams)
             hasMany MatchStats (immer genau 2 Einträge)

MatchStat    belongsTo Match, Team

User         hat role (admin/referee)
```

---

## 5. Routen-Struktur

```php
// Öffentlich (kein Auth)
Route::get('/t/{token}', PublicTournamentController::class)->name('tournament.public');

// Auth-Routen
Route::middleware('auth')->group(function () {

    // Referee + Admin
    Route::get('/matches', MatchListController::class)->name('matches.index');
    Route::get('/match/{match}', MatchScoreController::class)->name('match.score');

    // Nur Admin
    Route::middleware('role:admin')->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
        Route::get('/tournament/setup', TournamentSetupController::class)->name('tournament.setup');
        Route::post('/tournament/generate-groups', GenerateGroupsController::class)->name('tournament.generate-groups');
        Route::post('/tournament/start-ko', StartKoController::class)->name('tournament.start-ko');
        Route::get('/statistics', StatisticsController::class)->name('statistics');
    });
});

// Auth (Register/Login/Logout — standard Laravel Breeze minimal)
Route::get('/register', RegisterController::class)->name('register');
Route::post('/register', RegisterController::class)->name('register.store');
Route::get('/login', LoginController::class)->name('login');
Route::post('/login', LoginController::class)->name('login.store');
Route::post('/logout', LogoutController::class)->name('logout');
```

---

## 6. Spielfluss (detailliert)

### Phase 1: Setup (status = 'setup')

1. Admin legt Turniernamen und Match-Dauern fest.
2. Admin legt beliebig viele Tische an (Mindest: 1).
3. Admin legt beliebig viele Teams an (Mindest: 2).
4. Admin klickt „Gruppen generieren":
   - System verteilt Teams **gleichmäßig** auf Tische (Round-Robin-Verteilung).
   - Für jeden Tisch wird eine Gruppe erstellt.
   - Innerhalb jeder Gruppe werden alle Matches (Jeder-gegen-Jeden) automatisch erstellt.
   - Tournament-Status wechselt zu `group`.

### Phase 2: Gruppenphase (status = 'group')

Ablauf eines Matches:

```
[pending] 
   → Schiri wählt Match aus der Match-Liste
   → Status → [pre_entry]

[pre_entry]
   → Schiri trägt ein: Würfe (home), Würfe (away), Strafbecher (home), Strafbecher (away)
   → Schiri klickt „Spiel starten"
   → match_stats-Einträge werden angelegt (throws + penalty_cups gespeichert)
   → started_at = now()
   → Status → [active]
   → Timer-Event wird via Reverb an alle Clients gebroadcastet

[active]
   → Timer läuft auf der Schiri-Ansicht und der öffentlichen Ansicht
   → Nach Ablauf der konfigurierten Zeit läuft Timer ins Negative
   → Schiri klickt „Runde beenden" (jederzeit möglich)
   → ended_at = now(), duration_seconds = ended_at - started_at
   → Status → [scoring]

[scoring]
   → Schiri trägt ein: getroffene Becher (home), getroffene Becher (away)
   → System berechnet Gewinner (mehr Becher = Sieg; bei Gleichstand: weniger Würfe; dann Strafbecher)
   → group_team-Tabelle wird aktualisiert (points, wins, losses, cups_scored_total, cups_conceded_total)
   → winner_team_id wird gesetzt
   → Status → [finished]
   → Nächstes pending Match kann gestartet werden
```

Sind alle Matches einer Gruppe `finished`:
- Gruppenphase gilt als abgeschlossen wenn alle Gruppen fertig sind.
- Admin sieht „KO-Phase starten"-Button.

### Phase 3: KO-Phase (status = 'ko')

1. Admin klickt „KO-Phase starten".
2. System ermittelt Gruppensieger (Platz 1 jeder Gruppe) und Zweite.
3. System generiert KO-Bracket:
   - Gruppensieger spielen gegen Zweite anderer Gruppen (Cross-Bracket).
   - Bei ungerader Teilnehmerzahl: Freilose für beste Zweite.
   - ko_round gibt die Runde an (4 = Viertelfinale, 2 = Halbfinale, 1 = Finale).
   - ko_position gibt die Bracket-Position an.
4. Match-Ablauf identisch zur Gruppenphase.
5. Gewinner rücken automatisch in die nächste KO-Runde vor (neues Match wird angelegt).
6. Nach dem Finale: Tournament-Status → `finished`.

---

## 7. Livewire-Komponenten (Spezifikation)

### 7.1 `TournamentBracket` (öffentlich + auth)

- Zeigt Gruppenphase: Tabellen pro Gruppe (Punkte, W/L, Cups +/-).
- Zeigt KO-Bracket: Visuelles Bracket mit Team-Namen und Ergebnissen.
- Zeigt laufende Matches mit Live-Timer (via Reverb-Event `MatchStarted`).
- Pollt alle 15 Sekunden als Fallback.
- Kein Eingabe-Element.

### 7.2 `MatchList` (referee + admin)

- Liste aller Matches, gruppiert nach Status: Aktiv → Ausstehend → Beendet.
- Zeigt: Tisch, Teams, Phase, Status-Badge.
- Klick auf pending Match → navigiert zu `/match/{id}`.
- Echtzeit-Update wenn ein anderer Schiri ein Match startet (via Reverb).

### 7.3 `MatchScore` (referee + admin) — **Kern-Komponente**

Diese Komponente durchläuft die Match-Zustände:

**State: pre_entry**
```
╔══════════════════════════════════╗
║  Team Shotgun  vs  Team Bierherz ║
║  Tisch 1 · Gruppenphase          ║
╠══════════════════════════════════╣
║  VOR DEM SPIEL EINTRAGEN         ║
║                                  ║
║  Team Shotgun                    ║
║  Würfe:       [  0  ] ➕ ➖       ║
║  Strafbecher: [  0  ] ➕ ➖       ║
║                                  ║
║  Team Bierherz                   ║
║  Würfe:       [  0  ] ➕ ➖       ║
║  Strafbecher: [  0  ] ➕ ➖       ║
║                                  ║
║  [  🍺 SPIEL STARTEN  ]          ║
╚══════════════════════════════════╝
```

**State: active (Timer läuft)**
```
╔══════════════════════════════════╗
║  Team Shotgun  vs  Team Bierherz ║
╠══════════════════════════════════╣
║                                  ║
║         ⏱  08:42                 ║
║   (grün → gelb ab 2min → rot)    ║
║                                  ║
║  Shotgun: 3 Würfe · 1 Strafe     ║
║  Bierherz: 4 Würfe · 0 Strafe    ║
║                                  ║
║  [ ⏹ RUNDE BEENDEN ]             ║
╚══════════════════════════════════╝
```
- Timer zählt von `match_duration` runter.
- Unter 0: Anzeige „-00:12" in rot, Timer-Hintergrund pulsiert.
- „Runde beenden" ist jederzeit aktiv (auch im Minus).

**State: scoring**
```
╔══════════════════════════════════╗
║  Team Shotgun  vs  Team Bierherz ║
║  ✅ Runde beendet · 12:34 gespielt║
╠══════════════════════════════════╣
║  GETROFFENE BECHER EINTRAGEN     ║
║                                  ║
║  🍺 Team Shotgun                 ║
║  ╔═══╗                           ║
║  ║ 6 ║   ➖  ➕                   ║
║  ╚═══╝                           ║
║                                  ║
║  🍺 Team Bierherz                ║
║  ╔═══╗                           ║
║  ║ 4 ║   ➖  ➕                   ║
║  ╚═══╝                           ║
║                                  ║
║  [ ✅ ERGEBNIS SPEICHERN ]       ║
╚══════════════════════════════════╝
```
- Große, gut tippbare Zahlen mit + / - Buttons.
- Zahlen können auch direkt eingegeben werden.
- Plausibilitätsprüfung: Becher 0–10, keine negativen Werte.

**State: finished**
```
╔══════════════════════════════════╗
║  🏆 Team Shotgun gewinnt!        ║
║  6 : 4 Becher                    ║
╠══════════════════════════════════╣
║  Dauer: 12:34                    ║
║  Würfe: 8 vs 11                  ║
║  Strafbecher: 1 vs 0             ║
║                                  ║
║  [ ← Zurück zur Match-Liste ]    ║
╚══════════════════════════════════╝
```

### 7.4 `AdminSetup` (admin only)

- Turnier-Einstellungen (Name, Match-Dauer).
- Tische anlegen / umbenennen / löschen (solange status = setup).
- Teams anlegen / umbenennen / Farbe wählen / löschen (solange status = setup).
- „Gruppen generieren"-Button → Vorschau der Gruppenverteilung → Bestätigen.
- Liste aller generierten Matches der Gruppenphase.
- „KO-Phase starten"-Button (nur sichtbar wenn alle Gruppen-Matches finished).

### 7.5 `Leaderboard` (öffentlich)

- Zeigt nur: Rang, Teamname, Punkte, W, L.
- Sortierung: Punkte → Cup-Differenz → Cups geschossen.
- Echtzeit-Update via Reverb wenn ein Match finished wird.

### 7.6 `StatisticsPage` (nach Turnier, öffentlich)

Berechnet aus allen `match_stats`-Einträgen (Details siehe Abschnitt 9).

---

## 8. Events & Broadcasting (Laravel Reverb)

```php
// Alle Events implementieren ShouldBroadcast

MatchStarted::class
  → channel: tournament.{id}
  → payload: match_id, home_team, away_team, started_at, duration_minutes

MatchEnded::class
  → channel: tournament.{id}
  → payload: match_id, ended_at

MatchResultSaved::class
  → channel: tournament.{id}
  → payload: match_id, home_cups, away_cups, winner_team_id, updated leaderboard

TournamentPhaseChanged::class
  → channel: tournament.{id}
  → payload: new_status (group|ko|finished)
```

Auf der Client-Seite lauschen Livewire-Komponenten via `#[On('echo:tournament.{id},MatchStarted')]`.

---

## 9. Statistiken (Fun-Stats nach Turnierend)

Alle Statistiken werden on-the-fly aus `match_stats` + `matches` berechnet — keine separate Tabelle nötig.

| Statistik | Berechnung | Icon |
|---|---|---|
| 🏆 Turniersieger | KO-Finale winner_team_id | |
| 🎯 Schärfste Schützen | cups_scored / throws (höchste Quote) | |
| 💦 Wasserspeier | cups_scored / throws (niedrigste Quote) | |
| ⚡ Blitzsieg | min(duration_seconds) bei Gruppe wins | |
| 🐢 Marathonspieler | max(duration_seconds) | |
| 🍺 Becherkaiser | max(cups_scored) in einem einzigen Match | |
| 😈 Strafbechermagnet | max(penalty_cups) über alle Matches | |
| 💪 Effizienzrate | (cups_scored - penalty_cups) / throws × 100 | |
| 🔥 Heiß gespielt | Team mit den meisten Matches insgesamt | |
| 📊 Cups gesamt | sum(cups_scored) aller Teams zusammen | |

---

## 10. UI/UX-Richtlinien

### Design-Prinzipien
- Mobile-first: Schiri-Ansicht muss auf dem Handy bedienbar sein.
- Große Tap-Targets: Buttons mindestens 48px hoch, ➕/➖ mindestens 56px.
- Klare Zustände: Jeder Match-Status hat eine eigene Farbe/Icon.
- Dunkles Theme als Standard (Turniere finden oft abends statt).

### Farb-Schema (CSS Custom Properties)
```css
:root {
  --color-bg:           #0d0f14;
  --color-surface:      #161a24;
  --color-surface-2:    #1e2433;
  --color-border:       #2a3047;
  --color-accent:       #f59e0b;   /* Amber — Bier-Farbe */
  --color-accent-hover: #d97706;
  --color-success:      #10b981;
  --color-danger:       #ef4444;
  --color-warning:      #f59e0b;
  --color-text:         #f1f5f9;
  --color-text-muted:   #64748b;

  --radius-sm:  6px;
  --radius-md:  12px;
  --radius-lg:  20px;

  --font-display: 'Inter', system-ui, sans-serif;
  --font-mono:    'JetBrains Mono', monospace; /* für Timer */
}
```

### Timer-Visualisierung
- 0:00 bis 2:00 verbleibend: Grün (`--color-success`)
- 2:00 bis 0:00 verbleibend: Gelb/Amber (`--color-warning`)
- Unter 0:00 (negativ): Rot pulsierend (`--color-danger` + CSS animation pulse)
- Schriftart: Monospace für gleichmäßige Breite

### Status-Badges

```css
.badge-pending  { background: var(--color-surface-2); color: var(--color-text-muted); }
.badge-active   { background: #1a3320; color: var(--color-success); }
.badge-scoring  { background: #3d2310; color: var(--color-warning); }
.badge-finished { background: #1a2a3d; color: #60a5fa; }
```

---

## 11. Gruppen-Generierungsalgorithmus

```php
// Pseudocode für AdminSetup::generateGroups()

public function generateGroups(Tournament $tournament): void
{
    $teams = $tournament->teams->shuffle(); // Zufällige Verteilung
    $tables = $tournament->tables;

    // Teams gleichmäßig auf Tische verteilen (Round Robin)
    foreach ($teams as $index => $team) {
        $table = $tables[$index % $tables->count()];
        // Gruppe für diesen Tisch holen oder anlegen
        $group = Group::firstOrCreate([
            'tournament_id' => $tournament->id,
            'table_id'      => $table->id,
        ], ['name' => 'Gruppe ' . chr(65 + $tables->search(fn($t) => $t->id === $table->id))]);

        $group->teams()->attach($team->id);
    }

    // Matches generieren: Jeder gegen Jeden pro Gruppe
    foreach ($tournament->groups as $group) {
        $groupTeams = $group->teams->values();
        for ($i = 0; $i < $groupTeams->count(); $i++) {
            for ($j = $i + 1; $j < $groupTeams->count(); $j++) {
                Match::create([
                    'tournament_id' => $tournament->id,
                    'phase'         => 'group',
                    'group_id'      => $group->id,
                    'table_id'      => $group->table_id,
                    'home_team_id'  => $groupTeams[$i]->id,
                    'away_team_id'  => $groupTeams[$j]->id,
                    'status'        => 'pending',
                ]);
            }
        }
    }

    $tournament->update(['status' => 'group']);
}
```

---

## 12. KO-Bracket-Generierungsalgorithmus

```php
// Pseudocode für AdminSetup::startKoPhase()

public function startKoPhase(Tournament $tournament): void
{
    // 1. Gruppensieger und Zweite ermitteln
    $ranked = [];
    foreach ($tournament->groups as $group) {
        $standings = $group->teams()
            ->withPivot('points', 'wins', 'cups_scored_total', 'cups_conceded_total')
            ->orderByPivot('points', 'desc')
            ->orderByRaw('(group_team.cups_scored_total - group_team.cups_conceded_total) DESC')
            ->orderByPivot('cups_scored_total', 'desc')
            ->get();

        $ranked[] = ['rank' => 1, 'team' => $standings[0], 'group' => $group];
        if ($standings->count() > 1) {
            $ranked[] = ['rank' => 2, 'team' => $standings[1], 'group' => $group];
        }
    }

    // 2. Seeding: Sieger spielen gegen Zweite aus anderer Gruppe
    // Erste Runde: ko_round = Anzahl Matches (aufgerundet auf 2er-Potenz)
    $participants = collect($ranked)->sortBy('rank')->values();
    $roundSize = pow(2, ceil(log($participants->count(), 2)));
    $koRound = $roundSize / 2; // Erste KO-Runde

    // 3. Cross-Bracket Matchups (Sieger 1 vs Zweiter 2, Sieger 2 vs Zweiter 1, ...)
    $winners = $participants->where('rank', 1)->values();
    $runners = $participants->where('rank', 2)->values();

    // Tische für KO-Phase: alle verfügbaren Tische round-robin
    $tables = $tournament->tables->values();
    $matchIndex = 0;

    foreach ($winners as $i => $winner) {
        $opponent = $runners[$winners->count() - 1 - $i] ?? null; // Cross-Bracket
        if ($opponent) {
            Match::create([
                'tournament_id' => $tournament->id,
                'phase'         => 'ko',
                'ko_round'      => $koRound,
                'ko_position'   => $matchIndex,
                'table_id'      => $tables[$matchIndex % $tables->count()]->id,
                'home_team_id'  => $winner['team']->id,
                'away_team_id'  => $opponent['team']->id,
                'status'        => 'pending',
            ]);
            $matchIndex++;
        }
    }

    $tournament->update(['status' => 'ko']);
}

// Nach jedem KO-Match: Gewinner ins nächste Match einsetzen
public function advanceKoWinner(Match $match): void
{
    $nextRound = $match->ko_round / 2;
    $nextPosition = (int) floor($match->ko_position / 2);

    if ($nextRound < 1) {
        // Turnier beendet
        $match->tournament->update(['status' => 'finished']);
        return;
    }

    $nextMatch = Match::firstOrCreate([
        'tournament_id' => $match->tournament_id,
        'phase'         => 'ko',
        'ko_round'      => $nextRound,
        'ko_position'   => $nextPosition,
    ], [
        'table_id' => $match->table_id,
        'status'   => 'pending',
    ]);

    // Gewinner als home oder away eintragen (je nach Position)
    if ($match->ko_position % 2 === 0) {
        $nextMatch->update(['home_team_id' => $match->winner_team_id]);
    } else {
        $nextMatch->update(['away_team_id' => $match->winner_team_id]);
    }
}
```

---

## 13. Punkteberechnung & Tiebreaker

```
Sieg:       3 Punkte
Niederlage: 0 Punkte
(Kein Unentschieden — bei Gleichstand: weniger Würfe gewinnt)

Tiebreaker-Reihenfolge in der Gruppenrangliste:
1. Punkte (höher = besser)
2. Cup-Differenz (cups_scored_total - cups_conceded_total, höher = besser)
3. Cups geschossen gesamt (höher = besser)
4. Direkter Vergleich (falls implementiert, optional)
```

---

## 14. Authentifizierung

- Kein E-Mail-Verify erforderlich.
- Registrierung: Name + Passwort (kein E-Mail-Pflichtfeld — Passwort-Reset nicht nötig).
- Erster User → Rolle `admin` (via `User::creating` Observer oder Boot-Methode).
- Alle weiteren → Rolle `referee`.
- Admin kann Rollen nachträglich ändern.
- Middleware `role:admin` für Admin-Routen.

```php
// In User Model
protected static function booted(): void
{
    static::creating(function (User $user) {
        $user->role = User::count() === 0 ? 'admin' : 'referee';
    });
}
```

---

## 15. Seeder (Entwicklung & Demo)

```php
// DatabaseSeeder erstellt:
// - 1 Turnier: "Hackerspace Cup 2025"
// - 2 Tische: "Tisch 1", "Tisch 2"
// - 8 Teams mit Farben:
//   Team Shotgun (#f59e0b), Team Bierherz (#ef4444),
//   Team NullPointer (#3b82f6), Team 404 (#8b5cf6),
//   Team Overflowz (#10b981), Team Syntax Error (#f97316),
//   Team Legacy Code (#64748b), Team Hot Fix (#ec4899)
// - 1 Admin-User: admin / password
// - 2 Referee-User: ref1 / password, ref2 / password
// - Gruppen werden NICHT automatisch generiert (das macht der Admin manuell)
```

---

## 16. Deployment auf Laravel Cloud

```yaml
# laravel-cloud.yml (Referenz)
services:
  web:
    runtime: php84
    build:
      commands:
        - composer install --no-dev --optimize-autoloader
        - npm ci && npm run build
        - php artisan migrate --force
        - php artisan config:cache
        - php artisan route:cache
        - php artisan view:cache

  reverb:
    runtime: php84
    command: php artisan reverb:start

environment:
  APP_ENV: production
  BROADCAST_DRIVER: reverb
  QUEUE_CONNECTION: database
  DB_CONNECTION: mysql
```

---

## 17. Verzeichnisstruktur (nach Fertigstellung)

```
app/
  Events/
    MatchStarted.php
    MatchEnded.php
    MatchResultSaved.php
    TournamentPhaseChanged.php
  Http/
    Controllers/
      PublicTournamentController.php
      DashboardController.php
      MatchScoreController.php
      StatisticsController.php
    Middleware/
      EnsureRole.php
  Livewire/
    AdminSetup.php
    MatchList.php
    MatchScore.php
    TournamentBracket.php
    Leaderboard.php
    StatisticsPage.php
  Models/
    Group.php
    Match.php
    MatchStat.php
    Table.php
    Team.php
    Tournament.php
    User.php
  Services/
    GroupGeneratorService.php
    KoBracketService.php
    StatisticsService.php
    MatchResultService.php

database/
  migrations/
    ..._create_tournaments_table.php
    ..._create_tables_table.php
    ..._create_teams_table.php
    ..._create_groups_table.php
    ..._create_group_team_table.php
    ..._create_matches_table.php
    ..._create_match_stats_table.php
    ..._add_role_to_users_table.php
  seeders/
    DatabaseSeeder.php

resources/
  views/
    layouts/
      app.blade.php       (mit Reverb-JS eingebunden)
      public.blade.php    (minimales Layout für /t/{token})
    livewire/
      admin-setup.blade.php
      match-list.blade.php
      match-score.blade.php
      tournament-bracket.blade.php
      leaderboard.blade.php
      statistics-page.blade.php
  css/
    app.css               (Lightning CSS, Custom Properties)

routes/
  web.php
  channels.php            (Reverb Channel-Authorisierung)
```

---

## 18. Starter-Prompt für Claude Code

```
Ich möchte ein Laravel 11 Projekt namens "pongtable" für Bierpong-Turnierverwaltung 
aufbauen. Bitte lies zunächst die gesamte SPEC.md Datei (diese Datei) und bestätige 
das Verständnis, bevor du mit der Implementierung beginnst.

Gehe dann schrittweise vor:

SCHRITT 1 - Projekt-Grundgerüst:
- Neues Laravel 11 Projekt erstellen
- Livewire 3 installieren und konfigurieren
- Laravel Reverb installieren und konfigurieren
- Lightning CSS via Vite-Plugin einrichten
- SQLite als Standard-Datenbank konfigurieren
- Alle Migrations aus Abschnitt 4 erstellen und ausführen
- Alle Models aus Abschnitt 4.2 mit Relationships erstellen
- Seeder aus Abschnitt 15 erstellen
- EnsureRole-Middleware erstellen
- Routen aus Abschnitt 5 anlegen

SCHRITT 2 - Authentifizierung:
- Minimales Register/Login ohne E-Mail-Verify
- Automatische Rollenzuweisung (erster User = admin, Rest = referee)
- Auth-Views im Dark-Theme aus Abschnitt 10

SCHRITT 3 - Admin-Setup (Livewire):
- AdminSetup-Komponente für Teams, Tische, Turnier-Einstellungen
- GroupGeneratorService mit Algorithmus aus Abschnitt 11
- "Gruppen generieren"-Preview und Bestätigung

SCHRITT 4 - Match-Flow (Kern):
- MatchScore-Livewire-Komponente mit allen 4 Zuständen aus Abschnitt 7.3
- Timer-Implementierung mit Reverb-Broadcasting
- MatchResultService für Punkte-Berechnung und group_team-Update
- KoBracketService aus Abschnitt 12

SCHRITT 5 - Öffentliche Ansichten:
- TournamentBracket-Komponente (Gruppen + KO)
- Leaderboard-Komponente
- Public-Route /t/{token}

SCHRITT 6 - Statistiken:
- StatisticsService mit allen Fun-Stats aus Abschnitt 9
- StatisticsPage-Livewire-Komponente

SCHRITT 7 - UI-Polish:
- CSS Custom Properties aus Abschnitt 10 anwenden
- Mobile-Optimierung der Schiri-Ansicht
- Status-Badges und Timer-Visualisierung

Bitte fang mit Schritt 1 an und warte auf meine Bestätigung bevor du weitermachst.
Wenn du Entscheidungen treffen musst, die nicht in der SPEC stehen, frag kurz nach.
```

---

## 19. Bekannte Offene Punkte / Erweiterungsideen

- [ ] Freilos-Logik bei ungerader Teamanzahl in der KO-Phase
- [ ] Direkter Vergleich als 4. Tiebreaker-Kriterium
- [ ] Export als PDF / CSV nach Turnierend
- [ ] Mehrsprachigkeit (DE/EN)
- [ ] Mehrere gleichzeitige Turniere pro Instanz
- [ ] Rematch-Button im Finale
- [ ] Live-Kommentar-Feed (Schiri kann kurze Notizen pro Match hinterlassen)
- [ ] QR-Code für den öffentlichen Link (auf Dashboard anzeigen)
- [ ] Push-Notifications wenn ein Match startet (Web Push API)
