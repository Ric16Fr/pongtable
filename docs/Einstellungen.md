# Einstellungen

Die Einstellungsseite (`/settings`) ist über das Profil-Dropdown unten
links in der Sidebar erreichbar — _nicht_ über einen eigenen Sidebar-
Eintrag. Sie ist für alle eingeloggten Rollen sichtbar, hat aber je
nach Rolle unterschiedlichen Funktionsumfang.

## Eigenes Passwort ändern (alle Rollen)

Drei Felder:

- **Aktuelles Passwort** — wird gegen den Hash des eingeloggten Users
  geprüft (Laravel-Validation `current_password`).
- **Neues Passwort** — `Password::min(8)`, also mindestens 8 Zeichen.
- **Neues Passwort (Wiederholung)** — muss mit dem ersten neuen Feld
  übereinstimmen (`confirmed:newPasswordConfirmation`).

Bei Erfolg wird das Passwort gesetzt (`password` ist im User-Model auf
`'hashed'` gecastet, also wird automatisch gehasht) und ein Erfolgs-
Toast erscheint. Die Felder werden zurückgesetzt — der User bleibt
eingeloggt.

> Wer sein Passwort vergisst, ist auf den Admin angewiesen: es gibt
> keinen Self-Service-Reset und keinen E-Mail-Flow (die User haben
> nur einen `name`, keine Mail-Adresse). Der Admin setzt das Passwort
> über die unten beschriebene Funktion zurück.

## Theme

Oben in der Sidebar und im Header steht der **Appearance-Toggle**
(`x-appearance-toggle`). Er schaltet zwischen hellem und dunklem
Modus um. Die Auswahl wird lokal pro Browser gespeichert.

> Die Anpassung wirkt sich auf alle Seiten der App aus (auch die
> öffentliche Turnier-Ansicht).

## Sprache

Aktuell ist die App durchgängig auf Deutsch. Es gibt keinen UI-Switch
für Sprachen. Wenn ein anderer Locale gesetzt werden soll, geht das
nur über `config/app.php` (`locale`) bzw. ENV.

---

## Schiris anlegen und verwalten (Admin)

Die folgenden Sektionen erscheinen **nur**, wenn der eingeloggte User
`role = admin` hat. Hinter jedem Server-Handler steht zusätzlich ein
`abort_unless(auth()->user()->isAdmin(), 403)`, d. h. das ist nicht
nur ein UI-Schutz.

### Schiri anlegen

Formular mit zwei Feldern:

- **Benutzername** – unique in `users.name`, max. 255 Zeichen. Diesen
  Namen tippt der Schiri später beim Login ein. Mail-Adresse gibt es
  nicht, das ist das primäre Identifikationsmerkmal.
- **Passwort** – `Password::min(8)`. Der Admin denkt sich eines aus
  und gibt es dem Schiri persönlich weiter; danach kann der Schiri es
  selbst über *„Passwort ändern"* erneuern.

Bei Erfolg wird ein `users`-Eintrag mit `role = referee` angelegt.

### Schiri-Liste

Unter dem Anlege-Formular steht die Liste aller User mit
`role = referee`, sortiert nach Name. Pro Eintrag gibt es zwei
Aktionen:

#### Passwort zurücksetzen

Öffnet eine Modal mit einem einzelnen Passwort-Feld (gleiche Regeln
wie bei Anlage: mindestens 8 Zeichen). Beim Bestätigen wird das
Passwort des ausgewählten Schiris überschrieben.

> Sicherheitshalber lehnt der Server ein Reset auf Admin-Accounts ab
> (`abort_if($user->isAdmin(), 422)`). Das gilt auch für „selbst", weil
> Admin-Accounts hier gar nicht erst in der Liste auftauchen.

#### Entfernen

Löscht den Schiri komplett aus `users`. Vorher gibt es einen
`wire:confirm`-Dialog mit „Schiri wirklich löschen?".

> Auch hier blockiert der Server das Löschen von Admins (`isAdmin()`
> → `abort_if(..., 422)`). Damit ist insbesondere der initiale
> Admin-Account (siehe unten) nicht versehentlich löschbar.

---

## Rollen-Modell

Die App kennt zwei Rollen, gespeichert in `users.role`:

- `admin` – sieht alle Sidebar-Punkte (Dashboard, Matches, Setup,
  Statistik) und darf Schiris anlegen/zurücksetzen/löschen.
- `referee` – sieht nur Dashboard und Matches. Hat keinen Zugriff auf
  `/setup` und `/statistics` (HTTP 403 über `EnsureRole`-Middleware).

Es gibt _keine_ Selbst-Registrierung. Schiri-Accounts werden vom Admin
angelegt; Admins werden in der DB oder über eine Migration erzeugt
(siehe nächster Abschnitt).

### Initialer Admin

Beim ersten `php artisan migrate` läuft die Migration
`2026_05_31_211245_create_initial_admin_user`. Sie legt — falls er
noch nicht existiert — folgenden User an:

```
name     = admin
password = password
role     = admin
```

> 🔐 Direkt nach dem ersten Login als `admin` solltest du das Passwort
> über die Einstellungsseite ändern. Die Migration ist idempotent, sie
> überschreibt also nichts mehr, sobald der Account einmal existiert.

### Weitere Admins anlegen

Es gibt keine UI-Funktion, einen weiteren Admin anzulegen — das ist
bewusst, weil Admin-Rechte sehr weitreichend sind (Turnier
zurücksetzen, Schiris löschen). Wer einen zweiten Admin braucht:

```bash
php artisan tinker --execute 'App\Models\User::create(["name" => "<name>", "password" => "<pw>", "role" => "admin"]);'
```

oder direkt in der DB via `users.role = "admin"` upgraden.

---

## Rate-Limit beim Login

Login ist auf **5 Versuche pro Minute pro `<Benutzername>|IP`**
limitiert (`FortifyServiceProvider`). Bei Überschreitung antwortet
Fortify mit HTTP 429. Diese Schwelle ist nicht über die UI änderbar
— sie steht hart-codiert im Provider.

## Logout

Der **Abmelden**-Eintrag im Profil-Dropdown postet ein klassisches
Logout-Form an die Fortify-Route `/logout`. Nach Logout landet der
User auf `/` (Startseite).

---

## Was hier _nicht_ konfigurierbar ist

- E-Mail-Adresse / E-Mail-Versand. User haben nur Namen.
- Zwei-Faktor-Authentifizierung. Fortify trägt die Spalten
  (`two_factor_secret`, `two_factor_recovery_codes`) in der
  Users-Tabelle, aber 2FA ist im UI nicht eingebaut.
- Avatar-Upload. Es wird nur das `initials()` aus dem Namen abgeleitet.
- Sprach- oder Zeitformat-Einstellung pro User.
