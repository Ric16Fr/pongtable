# Pongtable
Eine Turniersoftware für unsere Bierpong WM, mit Gruppenphase, KO-Bracket und Live-Dashboard, selbst hostbar über LaravelCloud, lokal, o.ä.
Aufgrund fehlender Alternativen selbst gebaut und stetig erweitert.
### Runs
[![Analyze Code (PhpStan)](https://github.com/Ric16Fr/pongtable/actions/workflows/analyzeCode.yml/badge.svg?branch=main)](https://github.com/Ric16Fr/pongtable/actions/workflows/analyzeCode.yml)
[![PHP Linting (Pint)](https://github.com/Ric16Fr/pongtable/actions/workflows/phpLinting.yml/badge.svg)](https://github.com/Ric16Fr/pongtable/actions/workflows/phpLinting.yml)
[![Webtests](https://github.com/Ric16Fr/pongtable/actions/workflows/runUnitFeatureAndBrowsertestWithPest.yml/badge.svg)](https://github.com/Ric16Fr/pongtable/actions/workflows/runUnitFeatureAndBrowsertestWithPest.yml) ==> i mobile nicht unterstützt auf Firefox, daher dort ein Fehlschlag. Noch fixen
[![Featuretests](https://github.com/Ric16Fr/pongtable/actions/workflows/runFeaturetest.yml/badge.svg)](https://github.com/Ric16Fr/pongtable/actions/workflows/runUnitFeatureAndBrowsertestWithPest.yml)


## Screenshots


## Features

- beliebig viele Teams und Spieltische erstellbar
- automatischer Gruppenmodus (per Auslosung) oder Festlegen von Gruppen, falls bereits gelost wurde
- ernennung von Schiris, welche die Spielergebnisse eintragen
- Eintragungsmöglichkeit für die Anzahl an Würfen pro Team und den Strafbechern (nicht notwenig, aber für die Statistiken nett)
- öffentliches Dashboard für das aktuelle Turnier
- diverse lustige Statistiken
- Diagramm für die KO Runden
- Dark und Lightmode

## Was aktuell nicht geht
- mehrere getrennte Accounts, dies ist primär ein Tool für den eigenen Gebrauch
- mehrere verschiedene Turniere zeitgleich, da mein Fokus auf einem Turnier lag
- Docker-Unterstützung, bei Bedarf gerne schreiben
- QR-Codes für das Leaderboard, bei Interesse einfach mit der eigenen URL erstellen

## Was ist noch geplant
- ein Archiv mit vergangenen Turnieren
- Download der Ergebnisse als CSV
- (wenn ich mal viel Zeit hab) Generierung von Zertifikaten
- Sonderregeln, etwa wie der Sieger in der KO-Phase bestimmt wird
- Platzierungsspiele nach Ende der Gruppenphase


## Installation
Man kann das Projekt mit Herd lokal laufen lassen (dafür einfach klonen, [Herd installieren](https://herd.laravel.com/docs/windows/getting-started/installation) (die Standardversion reicht völlig) und der [Anleitung von Herd](https://herd.laravel.com/docs/windows/getting-started/sites#linking-an-existing-site) zum verbinden eines existierenden 
Projektes folgen). Das geht super unter Windows und MacOS, unter Linux muss man sich beispielsweilse mit Sail selbst einen Server aufsetzen.

Anschließend müssen die bnötigten Abhänigkeiten installiert werden, dafür gibt es unter `/scripts` ein 
vorgefertigtes Skript für das jew. Betriebssystem (das nur composer und bun aufruft und dann den Application Key generiert, einfach reinschauen ;) ). Gemeint ist hier eines der Install* Skripte, die anderen sind zum Updaten gedacht und installieren auch beispielsweise die Binarys für die Webtests mit.

Dann müsste die Instanz erreichbar sein und man hat auch automatisch den Adminaccount mit dem Nutzernamen _admin_ und dem Passwort _password_ (dieses sollte man unten links über 
Account->Einstellungen dann natürlich ändern) und kann dort in den Einstellungen auch beliebig viele Schiedsrichter-Accounts anlegen. 
Diese Accounts können (anders als der Admin) die Turniereinstellungen nicht ändern, sondern nur Ergebnisse der Matches eintragen.

Demodaten können auf Wunsch über `php artisan db:seed` angelegt werden.

## Doku
Für (unsere) Bierpongregeln und eine, teilweise technische, detaillierte Beschreibung aller Features (organisiert nach den Elementen in der 
Sidebar) bitte im 
`docs`-Ordner nachschauen, empfohlener Start: [README](docs/README.md)

## Testsuite
Für den Fall dass, bei einer eigenen Weiterentwicklung oder so, die vorhandenen Unit, und Webtests ausgeführt werden sollen muss einmalig

Um den Adminaccount anzulegen und einen Key von Laravel zu generieren muss nach dem Skript einmalig
``` shell
composer install
bun install
bunx playwright install
```
aufgerufen werden, dies installiert dann alle Dependencies, anders als über das Skript, und die Browser für Playwright.
Dann können die Tests über `./vendor/bin/pest` gestartet werden.
