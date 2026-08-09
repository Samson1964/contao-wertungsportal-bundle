# Massenabfragen bremsen

Die Wertungsportal-Seiten sind für Menschen gedacht. Wer sie seitenweise
abklappert, belastet die Schnittstelle des DSB und zieht sich nebenbei den
ganzen Personenbestand — dafür gibt es die
[Vereinslisten-Schnittstelle](vereinslisten-api.md) mit Zugangsschlüssel.

Die Bremse zählt die Abrufe je IP-Adresse in drei Fenstern und verweigert die
Daten, sobald eine Grenze gerissen ist.

## Einstellen

Einstellungen → Bereich Wertungsportal:

| Einstellung | Bedeutung |
|---|---|
| Höchstabrufe je Minute | Grenze für 60 Sekunden |
| Höchstabrufe je Stunde | Grenze für 60 Minuten |
| Höchstabrufe je Tag | Grenze für 24 Stunden |

**Leer oder 0 schaltet die jeweilige Grenze ab**; sind alle drei leer, ist die
Bremse aus und es wird auch nichts gezählt oder gespeichert.

Anhaltspunkt: Ein Mensch, der eine Vereinsliste durchsieht und ein paar
Karteikarten öffnet, kommt in der Stunde selten über einige Dutzend Aufrufe.
Ein Bot, der einen Landesverband abklappert, ist sofort im vierstelligen
Bereich.

## Was gezählt wird

**Ein Seitenaufruf zählt einmal — nicht jede Schnittstellenabfrage.** Eine
Turnierseite löst mehrere Abfragen aus (Kopfdaten, Ergebnisse, Auswertung).
Würde jede einzeln zählen, träfe die Bremse ausgerechnet die aufwendigen
Seiten zuerst, und die eingestellten Zahlen wären für einen Menschen nicht mehr
abschätzbar.

Der **nächtliche Vorlade-Cronjob** ist ausgenommen: Er ist kein Besucher und
hat keine sinnvolle Adresse.

Die Fenster laufen in festen Abschnitten ab dem ersten Abruf, nicht gleitend:
Wer um 10:00:30 anfängt, hat sein Minutenfenster bis 10:01:30. Das ist
ungenauer als ein gleitendes Fenster, kostet aber **eine Zeile je Adresse**
statt einer je Abruf.

## Was der Besucher sieht

Statt der Daten erscheint im Fehlerbereich des Moduls:

> Es wurden zu viele Abfragen von dieser Adresse gestellt. Bitte versuchen Sie
> es später erneut.

Das gilt bis zum Ende des überschrittenen Fensters. In dieser Zeit wird weder
die Schnittstelle noch der Zwischenspeicher noch die örtliche Datenbank
bemüht — die Bremse sitzt vor allem anderen.

## Wer gebremst wurde: WP | Sperren

Jeder Vorfall steht im Backend-Modul **WP | Sperren** mit:

* Zeitpunkt und überschrittenem Fenster (je Minute / Stunde / Tag)
* IP-Adresse, so wie Contao sie speichert — ist dort die Anonymisierung
  eingeschaltet, ist auch hier die gekürzte Fassung gespeichert
* Browserkennung
* **Mitglied mit Anmeldename UND Kennung** (`tl_member.id`), sofern angemeldet

Der Mitgliedsbezug ist der eigentliche Zweck: Manche Bots rufen angemeldet ab,
und dann sagt die Adresse allein wenig. Die Kennung steht neben dem Namen, weil
Namen doppelt vorkommen und sich ändern.

**Ein Vorfall ergibt einen Eintrag, nicht tausend.** Ein gebremster Bot rennt
weiter; solange dasselbe Fenster läuft, wächst nur der Zähler des vorhandenen
Eintrags. Mit „Alte Einträge löschen" verschwindet, was älter als 90 Tage ist.

## Datenschutz

Die Sperren enthalten IP-Adresse, Browserkennung und Mitgliedsbezug — also
personenbezogene Daten. Sie werden gebraucht, um Missbrauch zu erkennen und
gezielt sperren zu können, und gehören regelmäßig gelöscht.

Die Zählung selbst speichert **keine Zugriffshistorie**: In
`tl_wertungsportal_besucher` steht je Adresse nur, wie viele Abrufe im
laufenden Fenster kamen. Mit dem Fensterwechsel ist der alte Wert weg, und ein
täglicher Cronjob (4 Uhr) entfernt Adressen, die seit einem Tag nichts mehr
abgerufen haben.

## Technisch

`Helper\Besucherbremse` entscheidet, `Models\WertungsportalBesucherModel` zählt,
`Models\WertungsportalSperrenModel` protokolliert. Der Einstieg sitzt in
`API::autoQueryIntern()` ganz vorn; die Entscheidung fällt einmal je
Seitenaufruf und gilt dann für alle weiteren Abfragen desselben Aufrufs.

Beim Deployment sind **zwei neue Tabellen** anzulegen (`contao:migrate`).
