# Turnierdaten täglich vorladen

Ein Cronjob füllt jede Nacht den Zwischenspeicher mit den Turnierdaten der
letzten 30 Tage. Ohne ihn wartet der **erste** Besucher einer Turnierseite auf
die Schnittstelle — und das ist bei frischen Turnieren fast jeder Besucher:
Gemessen kamen Turnierauswertungen zu 4 %, Turnierergebnisse zu 5 % und
Spielberichtsbögen zu 3 % aus dem Zwischenspeicher.

Vorgeladen wird nur, was **fehlt**. Ein vorhandener Eintrag wird nie ersetzt,
auch kein abgelaufener: Der ist die Notreserve, falls die Schnittstelle
ausfällt, und der nächste Seitenaufruf frischt ihn ohnehin auf.

## Abschalten

Einstellungen → Bereich Wertungsportal → **Tägliches Vorladen abschalten**.

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

Die Spielerliste des dritten Durchgangs kommt aus der örtlichen
Auswertungstabelle, kostet also keinen Abruf. Turniere ohne gespeicherte
Auswertung bleiben außen vor — für sie ist nicht bekannt, welche Bögen es gibt.

## Zeitbudget

Der Lauf endet nach **20 Sekunden**, auch mitten in einem Durchgang. Was liegen
bleibt, holt der nächste Lauf; er überspringt, was inzwischen im
Zwischenspeicher liegt. Gemessen in der Testinstallation: rund **80 Abrufe je
Lauf**, 212 Turniere waren nach drei Läufen mit Auswertungen versorgt.

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

## Technisch

`Cron\TurnierVorlader`, angemeldet in `services.yml` mit
`tags: [{ name: contao.cronjob, interval: daily }]`. Contao führt ihn im
Web-Betrieb nach der Auslieferung einer Seite aus (`kernel.terminate`) oder,
wenn beim Hoster ein echter Cronjob eingerichtet ist, über
`vendor/bin/contao-console contao:cron`.

Geholt wird über `API::autoQuery()` und nicht über einen eigenen Abruf: So
gelten dieselben Cachezeiten, dieselbe Statistikzählung und derselbe Abgleich
mit den Spiegeltabellen wie im Frontend — und vor allem entstehen dieselben
Cache-Schlüssel (`<uuid>`, bei Bögen `<uuid>-<playerUuid>`). Ein abweichender
Schlüssel würde am Frontend vorbei laden.
