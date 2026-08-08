# Turnierdaten nachts vorladen

Ein Cronjob füllt jede Nacht den Zwischenspeicher mit den Turnierdaten der
letzten 30 Tage. Ohne ihn wartet der **erste** Besucher einer Turnierseite auf
die Schnittstelle — und das ist bei frischen Turnieren fast jeder Besucher:
Gemessen kamen Turnierauswertungen zu 4 %, Turnierergebnisse zu 5 % und
Spielberichtsbögen zu 3 % aus dem Zwischenspeicher.

Vorgeladen wird nur, was **fehlt**. Ein vorhandener Eintrag wird nie ersetzt,
auch kein abgelaufener: Der ist die Notreserve, falls die Schnittstelle
ausfällt, und der nächste Seitenaufruf frischt ihn ohnehin auf.

## Der Ablauf einer Nacht

| Uhrzeit | Was passiert |
|---|---|
| 1:00 | Turnierliste der letzten 30 Tage abrufen, dann mit dem ersten Durchgang beginnen |
| 1:05 … 2:55 | alle 5 Minuten weitermachen, wo der vorige Lauf aufgehört hat |
| 3:00 | letzter Lauf der Nacht |
| 3:05 … 3:55 | die Termine bestehen, arbeiten aber nicht mehr |

Am nächsten Abend beginnt alles von vorn — mit einem **frischen Abruf der
Turnierliste**, damit die inzwischen dazugekommenen Turniere dabei sind.

Der Abruf der Liste kostet genau **einen** Aufruf je Nacht, ohne dass dafür
etwas gespeichert werden müsste: Der Cache-Schlüssel enthält das Datum
(`vorlader-JJJJ-MM-TT`), also holt der erste Lauf sie von der Schnittstelle und
die 24 folgenden aus dem Zwischenspeicher. Am nächsten Tag lautet der Schlüssel
anders.

Dieser Abruf ist wichtiger, als er aussieht: Der örtliche Bestand
(`tl_wertungsportal_tournaments`) kennt sonst nur die Turniere, nach denen
zufällig jemand gesucht hat. Beim ersten Lauf mit Nachtliste in der
Testinstallation kamen dadurch auf einen Schlag 13 Turniere hinzu, die der
Bestand nicht hatte.

**Fortgesetzt wird ohne gespeicherte Position.** Jeder Lauf geht die Liste von
vorn durch und überspringt, was schon da ist — das ist ohne Buchführung immer
richtig, auch wenn ein Lauf mittendrin abbricht oder jemand einen Eintrag von
Hand löscht. Gemessen kostet ein übersprungener Eintrag 0,19 ms; selbst bei
6.000 Spielberichtsbögen ist das gut eine Sekunde je Lauf.

## Abschalten

Einstellungen → Bereich Wertungsportal → **Nächtliches Vorladen abschalten**.

## Drei Durchgänge

Ein Lauf arbeitet die Turniere dreimal durch, nach Wichtigkeit:

1. **Turnierauswertungen** aller Turniere
2. **Turnierergebnisse** aller Turniere
3. **Spielberichtsbögen** — einer je Spieler, also schnell hundert je Turnier

Bewusst nacheinander und nicht je Turnier alles: Reicht die Zeit nicht, haben
so mehr Turniere wenigstens ihre Auswertung, statt dass sich ein einzelnes
Turnier das ganze Budget nimmt. Der dritte Durchgang beginnt nur, wenn noch
mindestens 5 Sekunden übrig sind.

Die Reihenfolge der Turniere: **gewertete zuerst** (ein noch nicht gewertetes
liefert nur eine Fehlanzeige), darin die **jüngsten zuerst**.

Die Fehlanzeige selbst wird übrigens auch vorgeladen: Antwortet die
Schnittstelle für ein ungewertetes Turnier mit „No evaluation found", landet
das als Negativ-Eintrag im Zwischenspeicher. Auch dieser Besucher wartet dann
nicht.

Die Spielerliste des dritten Durchgangs kommt aus der örtlichen
Auswertungstabelle, kostet also keinen Abruf. Turniere ohne gespeicherte
Auswertung bleiben außen vor — für sie ist nicht bekannt, welche Bögen es gibt.

## Zeitbudget

Der Lauf endet nach **20 Sekunden**, auch mitten in einem Durchgang. Was liegen
bleibt, holt der nächste Lauf fünf Minuten später. Gemessen in der
Testinstallation: rund **80 Abrufe je Lauf**, 212 Turniere waren nach drei
Läufen mit Auswertungen versorgt.

Hochgerechnet sind das bei 25 Läufen **etwa 2.000 Abrufe je Nacht** — deutlich
mehr Last für die Schnittstelle als vorher, aber verteilt auf zwei Stunden und
nur so lange, bis der Zwischenspeicher voll ist. Wer das enger halten will,
verkleinert das Fenster im `interval` der `services.yml` und passt
`STUNDE_ENDE` in der Klasse entsprechend an.

Ist die **Laufzeit des Skripts begrenzt** — im Web-Betrieb üblicherweise auf
30 Sekunden —, fällt das Budget kleiner aus. Denn nach dem letzten Budgettest
läuft ein begonnener Abruf noch bis zu seiner Wartezeit weiter, im ungünstigen
Fall zweimal (bei abgelaufenem Zugangstoken kommt dessen Erneuerung dazu). Bei
30 Sekunden Grenze bleiben deshalb 13 Sekunden Budget; auf der Kommandozeile
(keine Grenze) sind es die vollen 20.

Aus demselben Grund setzt der Lauf die **Wartezeit der Schnittstelle** für sich
auf 8 Sekunden herunter — eine kürzere Einstellung bleibt unangetastet.
Danach gilt wieder der eingestellte Wert. Der Cronjob hat es nicht eilig, darf
aber an einer klemmenden Schnittstelle nicht die Laufzeitgrenze reißen: Ein
mittendrin abgeschossener Lauf könnte einen halb geschriebenen Cache-Eintrag
hinterlassen.

## Nachsehen, ob er läuft

Hat ein Lauf etwas geholt, steht im **Systemlog** eine Zeile:

```
Wertungsportal: 79 Turnierabrufe vorgeladen (79× Turnierauswertung, 20.1 s von 20 s, cli)
```

Ein Lauf ohne Abrufe schreibt nichts — sonst stünde dort jeden Tag eine
Nullmeldung. **Kein Eintrag heißt also: alles war schon da** (oder der Cronjob
ist abgeschaltet).

Der Lauf tut außerdem nichts, wenn der Live-Abruf abgeschaltet ist, keine
Zugangsdaten hinterlegt sind oder der Zwischenspeicher für Turnierauswertungen
auf „aus" steht — vorzuladen gäbe es dann nichts.

## Voraussetzung: ein echter Cronjob beim Hoster

**Ohne den bleibt es faktisch bei einem Lauf je Nacht.** Contao führt Cronjobs
im Web-Betrieb nach der Auslieferung einer Seite aus (`kernel.terminate`) — es
löst also nicht die Uhr aus, sondern ein Besucher. Auf einer nachts stillen
Website wird der um 1:00 fällige Termin erst am Morgen abgearbeitet, und
danach ist der nächste erst wieder in der Folgenacht fällig.

Richtig läuft es mit einem Cronjob beim Hoster, alle fünf Minuten:

```bash
php vendor/bin/contao-console contao:cron
```

Dann kann in den Contao-Einstellungen unter *Cron* zusätzlich „Cronjobs über
die Website ausführen" abgeschaltet werden (`disableCron`), damit nicht beides
nebeneinander läuft. Auf der Kommandozeile gilt außerdem keine Laufzeitgrenze —
der Vorlader bekommt dort seine vollen 20 Sekunden statt 13.

**Nicht** per Curl auf eine URL: Ein solcher Aufruf geht durch den Webserver
und scheitert an einem aktiven Bot-Schutz (bei Hetzner der Under-Attack-Modus,
der jede PHP-Adresse mit HTTP 401 beantwortet).

Damit ein verspäteter Lauf nicht ganz ausfällt, arbeitet die Klasse auch
außerhalb des Fensters — abgewiesen werden nur die Termine zwischen 3:05 und
3:55, also genau die nach dem Abschlusslauf. Ein Lauf, der erst um 8 Uhr
ausgelöst wird, holt seine 20 Sekunden Daten.

## Technisch

`Cron\TurnierVorlader`, angemeldet in `services.yml` mit
`tags: [{ name: contao.cronjob, interval: '*/5 1-3 * * *' }]`. Achtung bei
Änderungen am Intervall: Contao ersetzt die Schlagworte `daily`, `hourly` … per
`str_replace` — die @-Schreibweise (`'@daily'`) wird dabei zu `@@daily` und
lässt den Container nicht mehr bauen.

Geholt wird über `API::autoQuery()` und nicht über einen eigenen Abruf: So
gelten dieselben Cachezeiten, dieselbe Statistikzählung und derselbe Abgleich
mit den Spiegeltabellen wie im Frontend — und vor allem entstehen dieselben
Cache-Schlüssel (`<uuid>`, bei Bögen `<uuid>-<playerUuid>`). Ein abweichender
Schlüssel würde am Frontend vorbei laden.
