# Wertungsreferenten

Das Backend-Modul **Wertungsportal → Referenten** hält fest, wer für welchen
Verband die DWZ-Auswertung verantwortet. Die Kontaktdaten kommen aus der
**Adressverwaltung** (Paket `schachbulle/contao-adressen-bundle`) — sie ist die
einzige Quelle dafür. Aus den Referenten entstehen

- die Tabelle **Wertungsreferenten (Tabelle)** für allgemeine Seiten (ab
  1.52.0, Ersatz für das Modul der Adressverwaltung auf
  `adressen_wertungen.html`),
- die Gliederung **Wertungsreferenten** im Bereich des Wertungsportals,
- der Hinweis „Wertungsreferent" unter den Ranglisten eines Verbandes und
- die Empfänger der [Reklamationen](reklamationen.md) zu diesen Ranglisten.

Ausgegeben werden nur **veröffentlichte** Referenten.

## Was im Referenten steht

| Feld | Bedeutung |
|---|---|
| Adresse aus der Adressverwaltung | Die Person in der Adressverwaltung (Auswahl mit Suche; das Stift-Symbol öffnet sie zum Nachsehen oder Berichtigen) |
| Nachname, Vorname | Nur nötig ohne Adresse; mit Adresse wird beim Speichern deren Name eingetragen |
| nu-ID | Kennung der Person bei nu |
| Funktions-E-Mail | Adresse des Wertungsreferats, z. B. `dwz@verband.de` |
| Zuständig für | Die Verbände (siehe unten) |
| Veröffentlichen | Nur veröffentlichte Referenten erscheinen |

Telefon, Straße, PLZ und Ort gibt es seit 1.52.0 nicht mehr als eigene Felder.
Die Spalten stehen noch in der Datenbank (samt alter Werte), werden aber
nirgends mehr angezeigt oder ausgegeben.

## Was ausgegeben wird

| Angabe | Herkunft |
|---|---|
| Name | Titel, Vorname, Nachname der Adresse; ohne Adresse der eigene Name |
| E-Mail | zuerst die **Funktions-E-Mail**, danach **alle** E-Mail-Adressen der Adresse (E-Mail 1 bis 6), doppelte nur einmal |
| Anschrift | Straße, PLZ und Ort der Adresse |
| Telefon | **alle** Telefonnummern der Adresse (Telefon 1 bis 4) |

Es gilt, was in der Adressverwaltung **als öffentlich markiert** ist (die
Häkchen „anzeigen" bei E-Mail, Telefon, Ort und Straße). Die Straße erscheint
nur, wenn auch PLZ und Ort öffentlich sind. Die Funktions-E-Mail erscheint
immer.

Ist die Adresse **nicht aktiv** oder gelöscht, erscheinen nur der eigene Name
und die Funktions-E-Mail. Die Liste im Backend zeigt das an:

| Hinweis in der Liste | Bedeutung |
|---|---|
| `[Adressverwaltung]` (grau) | Daten kommen aus der Adresse |
| `[Adresse inaktiv]` (rot) | Adresse abgeschaltet — nur Name und Funktions-E-Mail |
| `[Adresse fehlt]` (rot) | Adresse gelöscht — nur Name und Funktions-E-Mail |

**Reklamationen** gehen an die Funktions-E-Mail. Fehlt sie, gehen sie an die
erste E-Mail-Adresse der Adresse — auch an eine nicht öffentliche; zu sehen
ist sie dabei nirgends.

Ohne Adressen-Bundle fehlt das Feld „Adresse"; die Referenten erscheinen dann
mit Name und Funktions-E-Mail.

## Zuständigkeit

Im Feld **Zuständig für** stehen alle Verbände aus dem Vereinsbestand — alles,
dessen Kennziffer auf `00` endet, also Landesverbände wie Bezirke — und ganz
oben der **Deutsche Schachbund** selbst (`00000`).

Ist für einen Bezirk niemand eingetragen, gilt der Referent des
Landesverbands, danach der des DSB. Unter der Rangliste steht dann „Für diesen
Verband ist niemand gesondert eingetragen; zuständig ist …".

Die Zuständigkeit wird **nur hier** gepflegt. Das Feld „Wertungsreferent" der
Adressverwaltung wird nicht gelesen.

## Frontend-Modul „Wertungsreferenten (Tabelle)"

Für allgemeine Seiten wie `adressen_wertungen.html`: eine Tabelle mit den
Spalten **Verband/Bezirk**, **Referent** und **Kontakt**, die Verbände in der
Reihenfolge ihrer Kennziffer (der DSB zuerst). Aufgeführt sind nur Verbände,
unter denen ein veröffentlichter Referent steht. Stehen mehrere unter einem
Verband, bekommt jeder eine Zeile; die Verbandszelle reicht über alle.

Einstellungen im Modul: Name, **Überschrift** (erscheint so, wie sie im Modul
steht; leer = keine), geschützt, CSS-ID/Klasse. Es gibt keine Linkleiste des
Wertungsportals. Die Tabelle trägt die Klassen `ce_table wp-referententabelle`
und übernimmt Schrift und Rahmen vom Theme.

Umstellen auf schachbund.de:

1. Unter **Themes → Frontend-Module** ein Modul vom Typ **Wertungsportal →
   Wertungsreferenten (Tabelle)** anlegen, Überschrift eintragen.
2. Auf der Seite `adressen_wertungen.html` das Modul der Adressverwaltung durch
   das neue ersetzen.
3. Erst danach kann das Modul der Adressverwaltung entfernt werden.

Das Modul **Wertungsreferenten** (Gliederung mit Einrückung, Linkleiste des
Wertungsportals) bleibt für den Bereich des Wertungsportals bestehen.

## Geschichte

- 1.51.0: Zuordnung einer Adresse; Name und Kontakt aus der Adresse, eigene
  Felder als Ersatz. Knopf „Aus der Adressverwaltung übernehmen", der die
  Referenten einmalig aus dem Feld „Wertungsreferent" der Adressverwaltung
  anlegte (auf schachbund.de am 30.09.2026 ausgeführt).
- 1.52.0: Kontaktdaten nur noch aus der Adresse, dafür vollständig; die
  E-Mail des Referenten ist jetzt die Funktions-E-Mail. Der Übernahme-Knopf
  ist entfernt, weil das Adressen-Bundle das Feld „Wertungsreferent" verliert.
  Neues Modul „Wertungsreferenten (Tabelle)".
