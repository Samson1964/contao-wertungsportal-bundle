# Adressen der Frontend-Seiten

Die vier Module hängen ihre Werte als Glieder an die eingestellte Seite an.
Das Suffix (hier `.html`) kommt seit 1.44.1 aus der Seiteneinstellung des
Startpunkts (`Helper::urlSuffix()`); eine Installation ohne Suffix bekommt
dieselben Adressen ohne `.html`.

| Seite | Adresse | Parameter im Modul |
| --- | --- | --- |
| Karteikarte | `spieler/NU4005017.html` | `id` |
| Spielersuche | `spieler.html?search=Muster` | `search` |
| Verein (Mitgliederliste) | `vereine/10614.html`, `…?order=alpha` | `zps`, `order` |
| Vereine eines Verbands | `vereine/300.html` | `zps` (Kennziffer auf 00) |
| Vereinssuche | `vereine.html?search=Zwickau` | `search` |
| Verband (Formular) | `verbaende/300.html` | `zps` |
| Verbandsrangliste | `verbaende/300.html?toplist=10&sex=&age_from=0&age_to=140&german=1` | `zps`, `toplist`, … |
| Turnierauswertung | `turniere/<code>.html` | `code` |
| Turnierergebnisse | `turniere/<code>/Ergebnisse.html` | `code`, `view=results` |
| Spielberichtsbogen | `turniere/<code>/<spieler-uuid>.html` | `code`, `id`, `view=results` |
| Turniersuche | `turniere.html?search=1&keyword=…&zps=300&last_months=6` | `search`, `keyword`, `zps`, … |

Der Turniercode ist die UUID des Turniers im Wertungsportal, die Spieler-UUID
die turnierbezogene `playerUuid` aus der Auswertung (siehe `docs/rohdaten.md`).

## Wie die Glieder zu Parametern werden

**Contao 4.13** ruft für jede Adresse den Hook `getPageIdFromUrl`
(`API::getParamsFromUrl()`). Das einzelne Glied hinter der Seite heißt dort
`auto_item`; der Hook benennt es in `id`, `zps` oder `code` um und übersetzt
die beiden Turnierformen mit zwei Gliedern in `code`, `view` und `id`.

**Contao 5** kennt diesen Hook nicht mehr. Das einzelne Glied bleibt
`auto_item`, zwei Glieder werden als Paar aus Schlüssel und Wert gelesen —
aus `<code>/Ergebnisse` wird ein Parameter namens `<code>` mit dem Wert
„Ergebnisse". Und jeder Parameter aus der Adresse, den kein Modul liest, führt
zur Fehlerseite 404 („Unused arguments"). Bis 1.44.0 war damit unter Contao 5
keine einzige Detailseite erreichbar; die Suche und die Formulare liefen.

Deshalb lesen die Module ihren Wert selbst:

* `Helper::urlParameter('id')` (Spieler), `urlParameter('zps')` (Verein,
  Verband): Fehlt der Parameter, gilt `auto_item`, und der Wert wird unter dem
  erwarteten Namen gesetzt.
* `Helper::turnierParameterAusUrl()` (Turnier): dazu die Paare, deren
  Schlüssel eine UUID ist — Wert „Ergebnisse" heißt Ergebnisse, ein Wert in
  UUID-Form heißt Spielberichtsbogen. Der Lesezugriff nimmt den Parameter aus
  der Liste der unbenutzten, die Fehlerseite bleibt aus.

Unter Contao 4.13 ist `auto_item` nach dem Hook nie gesetzt, und die Liste der
unbenutzten Parameter enthält nur die umbenannten Schlüssel — dort ändert sich
nichts. Die Adressen sind in beiden Fassungen dieselben, auch die von außen
gesetzten Verweise.

## Alte Verweise und der Sprung zum Verein

Verweise der Form `spieler.html?pkz=NU4005017` oder `?zps=…` aus der
DeWIS-Zeit leitet das Spielermodul auf die heutige Adresse um; die
Vereinssuche springt bei genau einem Treffer direkt zum Verein. Beides lief
bis 1.44.0 über `header('Location: …')` — wirkungslos, seit Contao seine
Antworten über Symfony ausgibt: Der Status wird danach wieder auf 200 gesetzt,
und einem Location-Header folgt ein Browser nur bei 3xx. Jetzt wirft
`Controller::redirect()` die `RedirectResponseException`, die beide Fassungen
in eine echte Weiterleitung (303) umsetzen.

## Geprüft

* Unter Contao 4.13 (Prüfinstallation mit `.html`) sind die Ausgaben von elf
  Seiten — Suche, Karteikarte, Verein, Verband, Rangliste, Turniersuche,
  Auswertung, Ergebnisse, Spielberichtsbogen — vor und nach 1.44.1 byteweise
  gleich; die Weiterleitungen antworten mit 303 und der richtigen Adresse.
* Unter Contao 5.7 (Prüfinstallation ohne Suffix) sind dieselben Adressen ohne
  `.html` erreichbar, die Verweise und Formularziele tragen kein Suffix.
* `tests/Contao5/EntfernteFunktionenTest.php` schlägt an, sobald wieder eine
  Weiterleitung per `header('Location')` im Code steht.
