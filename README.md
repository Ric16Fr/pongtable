# BierpongWM
Eine Turniersoftware für unsere Bierpong WM, OpenSource und selbst hostbar über LaravelCloud o.ä.
Aufgrund fehlender Alternativen selbst gebaut und stetig erweitert.

## Screenshots


## Features

- beliebig viele Teams und Spieltische erstellbar
- automatischer Gruppenmodus (per Auslosung) oder Festlegen von Gruppen, falls bereits gelost wurde
- ernennung von Schiris, welche die Spielergebnisse eintragen
- Eintragungsmöglichkeit für die Anzahl an Würfen pro Team und den Strafbechern (nicht notwenig, aber für die Statistike nett)
- öffentliches Dashboard für das aktuelle Turnier
- diverse lustige Statistiken
- Diagramm für die KO Runden
- Dark und Lightmode

## Was aktuell nicht geht
- mehrere getrennte Accounts, dies ist primär ein Tool für den eigenen Gebrauch
- mehrere verschiedene Turniere zeitgleich, da mein Fokus auf einem Turnier lag
- Docker-Unterstützung, bei Bedarf gerne schreiben
- QR-Codes für das Leaderboard, bei Interesse einfach mit der eigenen URL erstellen


## Installation
Man kann das Projekt mit Herd lokal laufen lassen (dafür einfach klonen und der Anleitung von Herd zum verbinden eines existierenden 
Projektes folgen) oder einen Server mit NGIX oder so aufsetzen. Zur Installation der benötigten Abhänigkeiten gibt es unter `/scripts` ein 
vorgefertigtes Skript für das jew. Betriebssystem.
Um den Adminaccount anzulegen und einen Key von Laravel zu generieren muss nach dem Skript einmalig
``` shell
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
```
aufgerufen werden.

Dann hat man automatisch den Adminaccount mit dem Nutzernamen _admin_ und dem Passwort _password_ (dieses sollte man unten links über 
Account->Einstellungen dann natürlich ändern) und kann dort in den Einstellungen auch beliebig viele Schiedsrichter-Accounts anlegen. 
Diese Accounts können (anders als der Admin) die Turniereinstellungen nicht ändern, sondern nur Ergebnisse eintragen.

## Doku
Für (unsere) Bierpongregeln und eine, teilweise technische, detaillierte Beschreibung aller Features (organisiert nach den Elementen in der 
Sidebar) bitte im 
`docs`-Ordner nachschauen

