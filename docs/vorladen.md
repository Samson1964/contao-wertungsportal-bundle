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

Der Lauf endet nach **120 Sekunden**, auch mitten in einem Durchgang. Was liegen
bleibt, holt der nächste Lauf fünf Minuten später. Gemessen in der
Testinstallation: rund **80 Abrufe je Lauf**, 212 Turniere waren nach drei
Läufen mit Auswertungen versorgt.

Ist die **Laufzeit des Skripts begrenzt**, fällt das Budget kleiner aus — die
120 Sekunden wirken sich also nur dort voll aus, wo keine Grenze gilt,
praktisch auf der Kommandozeile. Denn nach dem letzten Budgettest läuft ein
begonnener Abruf noch bis zu seiner Wartezeit weiter, im ungünstigen Fall
zweimal (bei abgelaufenem Zugangstoken kommt dessen Erneuerung dazu). Bei einer
30-Sekunden-Grenze bleiben deshalb 13 Sekunden Budget, bei 60 Sekunden sind es
43.

Das ist Absicht: Die Last soll möglichst vollständig in die Nacht wandern,
damit tagsüber niemand mehr wartet. Bei 25 Terminen bedeuten volle 120 Sekunden
allerdings bis zu **50 Minuten Abrufzeit je Nacht**. Wer das enger halten will,
verkleinert `ZEITBUDGET` oder das Fenster im `interval` der `services.yml` (und
passt dann `STUNDE_ENDE` in der Klasse an).

Aus demselben Grund setzt der Lauf die **Wartezeit der Schnittstelle** für sich
auf 8 Sekunden herunter — eine kürzere Einstellung bleibt unangetastet.
Danach gilt wieder der eingestellte Wert. Der Cronjob hat es nicht eilig, darf
aber an einer klemmenden Schnittstelle nicht die Laufzeitgrenze reißen: Ein
mittendrin abgeschossener Lauf könnte einen halb geschriebenen Cache-Eintrag
hinterlassen.

## Nachsehen, ob er läuft

Hat ein Lauf etwas geholt, steht im **Systemlog** eine Zeile:

```
Wertungsportal: 79 Turnierabrufe vorgeladen (79× Turnierauswertung, 20.1 s von 120 s, cli)
```

**Gezählt wird, was danach wirklich im Zwischenspeicher liegt** — nicht, wie oft
es versucht wurde. Fehlgeschlagene Abrufe legt das Bundle bewusst nicht ab; wer
nur die Versuche zählte, meldete auch dann Vollzug, wenn die Schnittstelle
durchgehend mit HTTP 403 antwortet. Fehlschläge stehen mit in der Zeile:

```
Wertungsportal: 0 Turnierabrufe vorgeladen (5 Fehlschläge, Lauf abgebrochen, 0.4 s von 120 s, cli)
```

Nach **fünf Fehlschlägen hintereinander** bricht der Lauf ab. Antwortet die
Schnittstelle nicht mehr, bringt Weitermachen nichts — der Lauf würde nur sein
Budget gegen die Wand rennen und die Statistik mit Fehlversuchen fluten. Ein
einzelner Fehlschlag ist dagegen normal: Nicht jedes Turnier hat zu jeder
Funktion Daten.

Ein Lauf ohne Abrufe **und ohne Fehlschläge** schreibt nichts — sonst stünde
dort jeden Tag eine Nullmeldung. **Kein Eintrag heißt also: alles war schon da**
(oder der Cronjob ist abgeschaltet).

Der Lauf tut außerdem nichts, wenn der Live-Abruf abgeschaltet ist, keine
Zugangsdaten hinterlegt sind oder der Zwischenspeicher für Turnierauswertungen
auf „aus" steht — vorzuladen gäbe es dann nichts.

## In der Statistik

Der Vorlader holt über denselben Weg wie das Frontend (`API::autoQuery`) und
wird deshalb mitgezählt — aber unter einer **eigenen Quelle „vorlader"** neben
api, cache und lokal.

Das ist kein Schönheitsfehler, sondern nötig: Der Vorlader macht in einer Nacht
ein Vielfaches dessen, was Besucher an einem Tag auslösen. Zusammengezählt wäre
nicht mehr zu erkennen, wie gut der Zwischenspeicher die **Besucher** bedient —
und genau diese Zahl war der Anlass für den Vorlader. Deshalb:

* Die Spalten „gesamt" und „ohne API" im Backend-Modul zählen weiter nur die
  Besucherabrufe. Der Vorlader steht abgesetzt in einer eigenen Spalte.
* Im Diagramm sitzt er als grauer Abschnitt oben auf dem Balken, außerhalb der
  blauen Reihe der Besucherquellen.

**Bei der Belastung der Schnittstelle zählt er dagegen mit.** Über dem Diagramm
steht dafür eine eigene Kennzahl: Anteil aller Abrufe, die tatsächlich bei nu
gelandet sind (`api` + `vorlader`), und wie viel davon auf das Vorladen
entfällt. Denn ein Vorlade-Abruf belastet den Server des DSB genauso wie der
eines Besuchers. Das Ziel: **unter 10 %**, und diese Last möglichst vollständig
aus dem Vorladen — dann wartet tagsüber niemand mehr.

Gezählt wird dort der **Versuch**, nicht der Erfolg: Ein Abruf, den die
Schnittstelle mit einem Fehler beantwortet, hat sie trotzdem beschäftigt. Das
Systemlog zählt umgekehrt nur, was ankam (siehe oben) — beide Zahlen
beantworten verschiedene Fragen.

Umgeschaltet wird über `API::vorladen(true|false)`, gesetzt vom Cronjob in
einem `try`/`finally`. Bleibt der Schalter stehen, würden im Web-Betrieb die
Abrufe des restlichen Seitenaufrufs falsch verbucht — deshalb dort niemals ohne
`finally` arbeiten.

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
der Vorlader bekommt dort seine vollen 120 Sekunden statt 13.

**Nicht** per Curl auf eine URL: Ein solcher Aufruf geht durch den Webserver
und scheitert an einem aktiven Bot-Schutz (bei Hetzner der Under-Attack-Modus,
der jede PHP-Adresse mit HTTP 401 beantwortet).

Damit ein verspäteter Lauf nicht ganz ausfällt, arbeitet die Klasse auch
außerhalb des Fensters — abgewiesen werden nur die Termine zwischen 3:05 und
3:55, also genau die nach dem Abschlusslauf. Ein Lauf, der erst um 8 Uhr
ausgelöst wird, holt seine 120 Sekunden Daten.

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
