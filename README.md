# BierpongWM
Eine Turniersoftware für unsere Bierpong WM, OpenSource und selbst hostbar über LaravelCloud o.ä.

## Features

- beliebig viele Teams und Spieltische erstellbar
- automatischer Gruppenmodus
- ernennung von Schiris, welche die Spielergebnisse eintragen
- öffentliches Dashboard für das aktuelle Turnier
- diverse lustige Statistiken

## Was aktuell nicht geht
- mehrere getrennte Accounts, dies ist primär ein Tool für den eigenen Gebrauch
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
aufgerufen werden
