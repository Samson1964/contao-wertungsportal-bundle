# Turnierdaten nachts vorladen

Ein Cronjob füllt jede Nacht den Zwischenspeicher — mit den Turnierdaten des
gesamten örtlichen Bestands und mit den Karteikarten aller Personen. Ohne ihn
wartet der **erste** Besucher einer Turnierseite auf die Schnittstelle — und das
ist bei frischen Turnieren fast jeder Besucher: Gemessen kamen
Turnierauswertungen zu 4 %, Turnierergebnisse zu 5 % und Spielberichtsbögen zu
3 % aus dem Zwischenspeicher.

Vorgeladen wird nur, was **fehlt**. Ein vorhandener Eintrag wird nie ersetzt,
auch kein abgelaufener: Der ist die Notreserve, falls die Schnittstelle
ausfällt, und der nächste Seitenaufruf frischt ihn ohnehin auf.

## Der Ablauf einer Nacht

| Uhrzeit | Was passiert |
|---|---|
| 1:00 | Turnierliste der letzten 30 Tage abrufen, dann mit dem ersten Durchgang beginnen |
| 1:10 … 2:50 | alle 10 Minuten weitermachen, wo der vorige Lauf aufgehört hat |
| 3:00 | letzter Lauf der Nacht |
| 3:10 … 3:50 | die Termine bestehen, arbeiten aber nicht mehr |

Das sind **13 Läufe zu je 180 Sekunden**, zusammen also gut 39 Minuten
Abrufzeit. Zwischen zwei Läufen liegen rund sieben Minuten Ruhe.

Am nächsten Abend beginnt alles von vorn — mit einem **frischen Abruf der
Turnierliste** über die letzten 30 Tage, damit die inzwischen dazugekommenen
Turniere dabei sind.

**Die 30 Tage begrenzen nicht, was vorgeladen wird.** Sie gelten nur für diesen
einen Abruf: Neue Turniere entstehen naturgemäß in den letzten Wochen.
Vorgeladen wird anschließend der **gesamte** Bestand der Tabelle
`tl_wertungsportal_tournaments`. Ältere Turniere kommen über die Turniersuche
der Besucher hinein und werden von da an mit vorgeladen.

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
Hand löscht.

Damit das bei Hunderttausenden Einträgen nicht selbst zum Kostenfaktor wird,
prüft der Vorlader **nur, ob die Cache-Datei da ist** — statt sie zu lesen und
zu entpacken. Gemessen: **0,014 ms statt 0,19 ms** je Eintrag; auf 200.000
Einträge hochgerechnet 2,8 Sekunden statt 38. Inhaltlich ist beides
gleichwertig, gefragt ist ja „liegt etwas vor" und nicht „ist es noch gültig".

Der Dateiname ist der SHA1-Wert des **bereinigten** Schlüssels
(kleingeschrieben!) — ein naives `sha1('NU4093214')` wäre falsch. Der Vorlader
prüft deshalb einmal je Funktion, ob seine eigene Rechnung mit der des
Helper-Bundles übereinstimmt, und fällt sonst auf den langsamen Weg zurück.
Ohne diese Prüfung hielte er nach einer Änderung dort jeden Eintrag für fehlend
und holte jede Nacht den gesamten Bestand neu.

## Abschalten

Einstellungen → Bereich Wertungsportal → **Nächtliches Vorladen abschalten**.

## Fünf Durchgänge

Ein Lauf arbeitet fünf Durchgänge ab, nach Wichtigkeit:

1. **Turnierauswertungen** aller Turniere
2. **Turnierergebnisse** aller Turniere
3. **Karteikarten** aller Personen (`/dwz/dwzliste/persons/{id}`)
4. **Turnierhistorien** aller Personen (`/dwz/persons/{id}/history`)
5. **Spielberichtsbögen** — einer je Spieler und Turnier, also schnell hundert
   je Turnier

Bewusst nacheinander und nicht alles je Turnier: Reicht die Zeit nicht, haben
so mehr Turniere wenigstens ihre Auswertung, statt dass sich ein einzelnes
Turnier das ganze Budget nimmt.

Die **Bögen stehen zuletzt**, auch hinter den Karteikarten: Von ihnen gibt es
ein Vielfaches, und eine Karteikarte wird weit häufiger aufgerufen als ein
einzelner Spielberichtsbogen. Der fünfte Durchgang beginnt nur, wenn noch
mindestens 5 Sekunden übrig sind.

Die Reihenfolge der Turniere: **gewertete zuerst** (ein noch nicht gewertetes
liefert nur eine Fehlanzeige), darin die **jüngsten zuerst**.

Die Fehlanzeige selbst wird übrigens auch vorgeladen: Antwortet die
Schnittstelle für ein ungewertetes Turnier mit „No evaluation found", landet
das als Negativ-Eintrag im Zwischenspeicher. Auch dieser Besucher wartet dann
nicht.

Die Personen kommen **nach DWZ absteigend** dran. Bei rund 95.000 veröffentlichten
Personen dauert ein vollständiger Durchgang mehrere Nächte — und in dieser Zeit
soll das da sein, was am häufigsten nachgeschlagen wird.

**Gesperrte Personen bleiben außen vor** (Blacklist): Ihre Karteikarte zeigt das
Frontend ohnehin nicht, der Abruf wäre also verschwendet — und die Daten von
jemandem, der der Veröffentlichung widersprochen hat, haben im Zwischenspeicher
nichts zu suchen.

Die Spielerliste des fünften Durchgangs kommt aus der örtlichen
Auswertungstabelle, kostet also keinen Abruf. Turniere ohne gespeicherte
Auswertung bleiben außen vor — für sie ist nicht bekannt, welche Bögen es gibt.

## Zeitbudget

Der Lauf endet nach **180 Sekunden**, auch mitten in einem Durchgang. Was liegen
bleibt, holt der nächste Lauf zehn Minuten später. Gemessen in der
Testinstallation: **714 Abrufe in einem Lauf** (101 Auswertungen, 97 Ergebnisse,
516 Karteikarten).

Ist die **Laufzeit des Skripts begrenzt**, hebt der Lauf die Grenze zuerst
selbst an, soweit er sie braucht (`set_time_limit`). Erst wenn der Hoster das
verbietet, fällt das Budget kleiner aus — und zwar deutlich: Nach dem letzten
Budgettest läuft ein begonnener Abruf noch bis zu seiner Wartezeit weiter, im
ungünstigen Fall zweimal (bei abgelaufenem Zugangstoken kommt dessen Erneuerung
dazu). Bei einer nicht anhebbaren 30-Sekunden-Grenze bleiben deshalb 13 Sekunden
Budget, bei 60 Sekunden sind es 43.

Das ist Absicht: Die Last soll möglichst vollständig in die Nacht wandern,
damit tagsüber niemand mehr wartet. Bei 13 Terminen bedeuten volle 180 Sekunden
bis zu **39 Minuten Abrufzeit je Nacht**. Wer das enger halten will, verkleinert
`ZEITBUDGET` oder das Fenster im `interval` der `services.yml` (und passt dann
`STUNDE_ENDE` in der Klasse an).

Aus demselben Grund setzt der Lauf die **Wartezeit der Schnittstelle** für sich
auf 8 Sekunden herunter — eine kürzere Einstellung bleibt unangetastet.
Danach gilt wieder der eingestellte Wert. Der Cronjob hat es nicht eilig, darf
aber an einer klemmenden Schnittstelle nicht die Laufzeitgrenze reißen: Ein
mittendrin abgeschossener Lauf könnte einen halb geschriebenen Cache-Eintrag
hinterlassen.

## Platzbedarf

Der Zwischenspeicher legt je Eintrag eine Datei an. Gemessene Durchschnitte:

| Was | Größe je Eintrag |
|---|---|
| Karteikarte | ~4 KB |
| Turnierhistorie | ~32 KB |
| Turnierauswertung | ~19 KB |
| Spielberichtsbogen | ~12 KB |

**Das summiert sich.** Bei rund 95.000 Personen im Bestand kommen allein für
Karteikarten und Historien etwa **3,4 GB** zusammen, dazu die Turnierdaten. Wer
knapp bei Speicherplatz ist, sollte das im Auge behalten — der Zwischenspeicher
lässt sich jederzeit über System → Systemwartung leeren, und einzelne Einträge
über das Backend-Modul „Zwischenspeicher".

## Nachsehen, ob er läuft

Hat ein Lauf etwas geholt, steht im **Systemlog** eine Zeile:

```
Wertungsportal: 714 Turnierabrufe vorgeladen (101× Turnierauswertung, 97× Turnierergebnisse, 516× Karteikarte, 180.2 s von 180 s, cli)
```

**Gezählt wird, was danach wirklich im Zwischenspeicher liegt** — nicht, wie oft
es versucht wurde. Fehlgeschlagene Abrufe legt das Bundle bewusst nicht ab; wer
nur die Versuche zählte, meldete auch dann Vollzug, wenn die Schnittstelle
durchgehend mit HTTP 403 antwortet. Fehlschläge stehen mit in der Zeile:

```
Wertungsportal: 0 Turnierabrufe vorgeladen (5 Fehlschläge, Lauf abgebrochen, 0.4 s von 180 s, cli)
```

Nach **fünf Fehlschlägen hintereinander** bricht der Lauf ab. Antwortet die
Schnittstelle nicht mehr, bringt Weitermachen nichts — der Lauf würde nur sein
Budget gegen die Wand rennen und die Statistik mit Fehlversuchen fluten. Ein
einzelner Fehlschlag ist dagegen normal: Nicht jedes Turnier hat zu jeder
Funktion Daten.

**Der Grund steht hinter „zuletzt:"** in derselben Zeile:

```
Wertungsportal: 0 Turnierabrufe vorgeladen (5 Fehlschläge, Lauf abgebrochen, zuletzt: Turnierauswertung — Token-Anfrage fehlgeschlagen (HTTP 403): Too much access tokens for the requested client-id, 0.6 s von 180 s, cli)
```

Ohne ihn ist die Meldung wertlos. Am 11.08.2026 stand dort anderthalb Tage lang
nur „5 Fehlschläge, Lauf abgebrochen" — und nirgends, dass die Schnittstelle das
Zugangstoken verweigerte.

Ein Lauf ohne Abrufe **und ohne Fehlschläge** schreibt nichts — sonst stünde
dort jeden Tag eine Nullmeldung. **Kein Eintrag heißt also: alles war schon da**
(oder der Cronjob ist abgeschaltet).

Der Lauf tut außerdem nichts, wenn der Live-Abruf abgeschaltet ist, keine
Zugangsdaten hinterlegt sind oder der Zwischenspeicher für Turnierauswertungen
auf „aus" steht — vorzuladen gäbe es dann nichts.

## Wenn die Schnittstelle das Zugangstoken verweigert

Antwortet nu mit **„Too much access tokens for the requested client-id"**, sind
zu viele Zugangstoken auf einmal auf die Kennung ausgestellt. Betroffen sind nur
die **geschützten** Endpunkte — `/dwz/tournaments/…` und `/dwz/persons/…`. Der
öffentliche Teil (`/dwz/dwzliste/…`, also die Karteikarte selbst) antwortet
weiter normal.

Ein Token gilt eine Zeit lang und wird in `system/tmp/wertungsportal-token.json`
abgelegt, damit alle Seitenaufrufe und alle Cronläufe dasselbe benutzen. Lässt
sich diese Datei **nicht schreiben**, holt sich jeder Aufruf ein eigenes Token —
bei einem Vorladelauf Hunderte in Minuten, und das Kontingent ist erschöpft.
Genau das steht dann im Systemprotokoll:

```
Wertungsportal: Die Tokendatei … läßt sich nicht schreiben. … Bitte Schreibrechte prüfen.
```

Bis Fassung 1.26.0 lag die Datei in `sys_get_temp_dir()`. Das ist auf einem
gemieteten Server nicht verlässlich: Webserver und Kommandozeile laufen dort je
nach Hoster unter verschiedenen Benutzern oder in getrennten Namensräumen
(systemd `PrivateTmp`), und ein Aufräumdienst kann jederzeit dazwischenfahren.

**Nach einem abgelehnten Tokenabruf wartet das Bundle 300 Sekunden**, bevor es
erneut anfragt. Ohne diese Wartezeit wurde aus jedem abgelehnten Abruf sofort
der nächste — und bei einem Kontingentfehler fütterte das genau die Ursache: Die
Anlage kam aus dem Zustand nicht mehr heraus, weil sie ihn selbst am Leben hielt.

Für die Besucher wird ein Tokenausfall wie ein **Verbindungsausfall** behandelt:
Sie bekommen die Daten aus dem Zwischenspeicher oder dem örtlichen Bestand samt
Hinweis auf deren Alter — nicht eine Fehlermeldung.

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

Richtig läuft es mit einem Cronjob beim Hoster, alle zehn Minuten:

```bash
php vendor/bin/contao-console contao:cron
```

Dann kann in den Contao-Einstellungen unter *Cron* zusätzlich „Cronjobs über
die Website ausführen" abgeschaltet werden (`disableCron`), damit nicht beides
nebeneinander läuft. Auf der Kommandozeile gilt außerdem keine Laufzeitgrenze —
der Vorlader bekommt dort seine vollen 180 Sekunden.

**Nicht** per Curl auf eine URL: Ein solcher Aufruf geht durch den Webserver
und scheitert an einem aktiven Bot-Schutz (bei Hetzner der Under-Attack-Modus,
der jede PHP-Adresse mit HTTP 401 beantwortet).

Damit ein verspäteter Lauf nicht ganz ausfällt, arbeitet die Klasse auch
außerhalb des Fensters — abgewiesen werden nur die Termine zwischen 3:10 und
3:50, also genau die nach dem Abschlusslauf. Ein Lauf, der erst um 8 Uhr
ausgelöst wird, holt seine 180 Sekunden Daten.

## Technisch

`Cron\TurnierVorlader`, angemeldet in `services.yml` mit
`tags: [{ name: contao.cronjob, interval: '*/10 1-3 * * *' }]`. Achtung bei
Änderungen am Intervall: Contao ersetzt die Schlagworte `daily`, `hourly` … per
`str_replace` — die @-Schreibweise (`'@daily'`) wird dabei zu `@@daily` und
lässt den Container nicht mehr bauen.

Geholt wird über `API::autoQuery()` und nicht über einen eigenen Abruf: So
gelten dieselben Cachezeiten, dieselbe Statistikzählung und derselbe Abgleich
mit den Spiegeltabellen wie im Frontend — und vor allem entstehen dieselben
Cache-Schlüssel:

| Funktion | Schlüssel |
|---|---|
| Turnierauswertung, Turnierergebnisse | `<uuid>` des Turniers |
| Spielberichtsbogen | `<uuid>-<playerUuid>` |
| Karteikarte, Karteikarte_Turniere | `<nuLigaPersonId>`, z. B. `NU4093214` |

Ein abweichender Schlüssel würde am Frontend vorbei laden — die Aufrufe im
Vorlader sind deshalb Zeile für Zeile denen in `Classes/Spieler.php` und
`Classes/Turnier.php` nachgebildet.
