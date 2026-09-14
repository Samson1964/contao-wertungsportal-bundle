# Rohdaten der Schnittstelle herunterladen

Backend-Modul **WP | Rohdaten**. Es ruft eine Funktion der nu-Schnittstelle
auf und liefert die Antwort als JSON-Datei — so, wie nu sie schickt.

Gedacht ist das für Rückfragen, bei denen jemand die Originalantwort sehen
muss: nu selbst, wenn eine Auswertung strittig ist, oder ein Wertungsreferent,
der einen Spielberichtsbogen nachprüfen will. Das Frontend zeigt nur eine
aufbereitete Fassung, und der Zwischenspeicher hält die Antwort bereits
dekodiert — den Originaltext gab es bis Fassung 1.41.0 nirgends.

## Bedienung

1. **Funktion** wählen — die Felder darunter passen sich an
2. Die Angaben eintragen; Pflichtfelder sind mit * markiert
3. **Abrufen und herunterladen**

Antwortet nu mit HTTP 200, lädt der Browser die Datei herunter. Jede andere
Antwort erscheint auf der Seite: Status, aufgerufene Adresse, Meldung, ein
Hinweis zur wahrscheinlichen Ursache und der Anfang des Antworttexts. Eine
Fehlerseite wird also nicht als „Rohdaten" gespeichert.

Der Dateiname nennt Funktion, Angaben und Abrufzeitpunkt, etwa
`nu_Turnierauswertung_3fb73b0d-85cd-421c-b441-d85c44207115_20260914-1012.json`.
So lässt sich eine weitergereichte Datei auch Wochen später noch zuordnen.

## Beispiel: Auswertung und Spielberichtsbogen eines Teilnehmers

| Schritt | Funktion | Angabe |
|---|---|---|
| 1 | Turnierauswertung | Turnier-UUID |
| 2 | — | in der Datei den Teilnehmer suchen und seine `playerUuid` ablesen |
| 3 | Spielberichtsbogen eines Teilnehmers | Turnier-UUID und diese `playerUuid` |

**Der Spielberichtsbogen will die `playerUuid`, nicht die NU-Nummer.** Diese
Kennung gilt nur innerhalb des einen Turniers (siehe `CLAUDE.md`, Abschnitt
„Fallstricke"). Auch die Personen-UUID aus der DWZ-Liste passt nicht — sie ist
eine dritte, eigene Kennung. Mit der falschen antwortet nu mit 404, und die
Seite sagt das dazu.

Groß- und Kleinschreibung einer UUID werden **unverändert** übernommen. Ob nu
Großbuchstaben annimmt, ist nicht belegt; eine stillschweigende Umschrift ließe
sich im Fehlerfall nicht nachvollziehen. Enthält eine UUID Großbuchstaben und
kommt ein 404 zurück, schlägt die Seite einen zweiten Versuch in
Kleinschreibung vor.

## Die Funktionen

| Funktion | Endpunkt | Angaben |
|---|---|---|
| Turnierauswertung | `/dwz/tournaments/{uuid}/evaluation` | Turnier-UUID |
| Spielberichtsbogen eines Teilnehmers | `/dwz/tournaments/{uuid}/players/{id}/scoresheet` | Turnier-UUID, Spieler-UUID im Turnier |
| Turnierergebnisse (Partien) | `/dwz/tournaments/{uuid}/matches` | Turnier-UUID |
| Turnier-Kopfdaten | `/dwz/tournaments/{uuid}` | Turnier-UUID |
| Turniersuche | `/dwz/tournaments` | Name, Zeitraum, VKZ — alles freiwillig |
| Karteikarte eines Spielers | `/dwz/dwzliste/persons/{id}` | NU-Nummer |
| Turnierhistorie eines Spielers | `/dwz/persons/{id}/history` | NU-Nummer |
| Spielersuche | `/dwz/dwzliste/persons` | Nach- oder Vorname |
| Mitgliederliste eines Vereins | `/dwz/dwzliste/persons` | VKZ |
| Rangliste eines Verbands | `/dwz/dwzliste/persons` | VKZ oder Höchstzahl, dazu Geschlecht und Alter |
| Vereinsdaten | `/dwz/dwzliste/clubs` | VKZ |
| Alle Vereine und Verbände | `/dwz/dwzliste/clubs` | — |

Die Turnierergebnisse liefert nu seitenweise. Heruntergeladen wird, was der
eine Aufruf zurückgibt — dasselbe, was auch das Frontend bekommt.

## Was die Datei enthält — und was nicht

**Ohne Häkchen bei „eingerückt" kommen die Bytes unverändert**, genau wie nu
sie geschickt hat. Nur diese Fassung ist mit der Originalantwort identisch und
taugt, wenn jemand bei nu die Datei mit der eigenen Ausgabe vergleicht.

**Mit Häkchen wird eingerückt.** Struktur und Werte bleiben erhalten — ein
leeres Objekt bleibt `{}`, `1.0` bleibt eine Kommazahl, die Reihenfolge der
Felder bleibt, sehr große Ganzzahlen werden nicht gerundet —, aber Leerraum
und die Schreibweise von Sonderzeichen ändern sich. Zum Lesen gut, zum
Vergleichen nicht.

Bewusst **anders als ein Abruf im Frontend**:

* **kein Zwischenspeicher** — abgerufen wird immer frisch;
* **kein Abgleich** mit den Spiegeltabellen — das Werkzeug verändert nichts;
* **keine Nachbesserung** — bei „Vereinsdaten" und „Alle Vereine und Verbände"
  ergänzt das Frontend Verbände, die nu nicht liefert (`BugfixVerbaende`); in
  der Rohdatei fehlen sie so, wie sie bei nu fehlen;
* **keine Zählung** in der Abrufstatistik.

## Voraussetzungen und Grenzen

* Die **Zugangsdaten** der Schnittstelle müssen in den Einstellungen stehen.
* Ist die Schnittstelle in den Einstellungen **abgeschaltet**, ruft auch
  dieses Modul nichts ab. Ein bewusster Stopp — etwa weil nu das
  Tokenkontingent drosselt — soll sich nicht über das Backend aushebeln lassen.
* Der Abruf benutzt **dasselbe Zugangstoken** wie das Frontend. Ein Klick kostet
  also kein zusätzliches Token, solange das gespeicherte gilt. Nach einem 401
  oder 403 einige Minuten warten und nicht wiederholt klicken — jeder neue
  Versuch verlängert eine Sperre.

## Datenschutz

Die Antworten enthalten Personendaten: Namen, Geburtsjahre, Vereine,
Wertungen. Deshalb:

* **Wer das Modul sieht, regelt die Benutzergruppe** (Zugriffsrechte →
  erlaubte Module). Administratoren sehen es immer; allen anderen muss es
  ausdrücklich freigegeben werden.
* **Jeder Abruf steht im Systemprotokoll** — mit Funktion, Angaben, Status und
  Größe.
* Die Datei geht mit `Cache-Control: no-store` an den Browser und bleibt in
  keinem Zwischenspeicher unterwegs liegen.

## Technisch

`Helper\Rohabfrage` erledigt die Arbeit — Funktionsliste, Eingabeprüfung,
Abruf, Dateiname und Aufbereitung — und kommt ohne Contao aus.
`Classes\Rohdaten` ist nur die Bedienoberfläche (Template
`be_wp_rohdaten.html5`).

Die Adressen baut **`API::adresse()`**, dieselbe Methode, die auch das Frontend
über `getAPI()` benutzt. Sie ist die einzige Stelle, an der die zwölf Adressen
entstehen; `tests/Helper/ApiAdresseTest.php` hält jede fest. Pfadteile werden
dort kodiert, damit eine Eingabe nicht aus dem Pfad ausbricht und mit dem
Zugangstoken einen anderen Endpunkt anspricht.

Den Originaltext liefert `OAuth2Client` nur auf Anforderung
(`$client->rohantwort = true`, Schlüssel `roh` in der Antwort). Für die
gewöhnlichen Abrufe bleibt er aus, sonst hielte jeder Abruf zwei Fassungen im
Speicher.

Die Eingaben werden streng geprüft, bevor sie in die Adresse gelangen: UUID,
NU-Nummer, VKZ, Datum und Zahlen nach Muster, freie Texte ohne Steuerzeichen
und mit höchstens 100 Zeichen. Gelesen werden sie roh aus dem Request, nicht
über `Input::post()` — das machte aus einem Turniernamen „Open (A)" ein
`Open &#40;A&#41;`, und nu fände nichts.

Wer eine Funktion ergänzt, trägt sie in `API::adresse()`, `API::endpunkte()`
und `Rohabfrage::FUNKTIONEN` ein. Vergisst er eine der drei Stellen, schlägt
eine Prüfung fehl.
