# Verbände: Landesverbände, Bezirke, Kreise

Das Frontend braucht die Verbände für die Verbandsnavigation, die
Verbandsauswahl der Turniersuche, die Vereinssuche und die Überschrift der
Verbandsranglisten; das Backend für die Zuständigkeit der Wertungsreferenten.

## Woher sie kommen

| Zeitraum | Quelle |
|---|---|
| bis 06.10.2026 | Die Vereinsliste von nu (`/dwz/dwzliste/clubs`) führte Verbände als „Vereine" mit einer Kennziffer auf `00`. 14 Landesverbände fehlten dort immer und kamen aus einer festen Liste im Code. |
| 07.10. bis zur Fassung 1.55.0 | Seit dem Release von nu am 07.10.2026 liefert die Vereinsliste **ausschließlich Vereine**. Das Bundle behalf sich mit dem örtlichen Vereinsbestand (1.54.1). |
| ab 1.55.0 | nu stellt die Verbände aller Ebenen unter einem eigenen Endpunkt bereit: `/dwz/dwzliste/federations` (Auskunft von nu vom 12.10.2026, Vorgang SPS-34590). |

Gemessen am 13.10.2026: Die Vereinsliste hat 2.190 Einträge, keiner mit einer
Kennziffer auf `00`. Die Verbandsstruktur hat 218 Einträge; daraus werden 196
Verbände — alle 195 des früheren Bestands und ein neuer (`61600`).

## Was nu liefert

Jeder Eintrag der Verbandsstruktur hat

| Feld | Bedeutung | Beispiel |
|---|---|---|
| `federationNumber` | dreistellige Nummer des Verbandes | `F11` |
| `federation` | Kennbuchstabe des Landesverbandes | `F` |
| `parentFederation` | Nummer des übergeordneten Verbandes | `F10` |
| `federationName` | Name | `Landkreis Delitzsch` |

Der Wert `parentFederation` eines **Vereins** in der Vereinsliste ist die
`federationNumber` seines Verbandes.

## Was das Bundle daraus macht

`API::BugfixVerbaende()` ergänzt die Vereinsliste unmittelbar nach dem Abruf um
die Verbände — vor dem Abgleich mit dem Vereinsbestand und vor dem
Zwischenspeichern. Vereinsbestand, Zwischenspeicher und Frontend kennen damit
dieselben Verbände, und alle übrigen Stellen des Bundles arbeiten wie bisher
mit fünfstelligen Kennziffern.

Aus einem Eintrag der Struktur wird: Kennziffer = Nummer + `00`, Name wie von
nu geliefert.

**Die Namen sind die von nu.** Die Landesverbände heißen damit „Baden",
„Bayern", „Sachsen" … (bis 1.54.1: „Badischer Schachverband e.V." usw.) — in
der Navigation, in den Überschriften der Ranglisten und in den Listen der
Wertungsreferenten. Umbenennungen bei nu kommen von selbst an.

Nicht übernommen wird:

| Einträge | Warum |
|---|---|
| `000` Deutschland, `001` Deutsche Schachjugend | Der DSB ist im Bundle fest eingebaut (`00000`); die Schachjugend hatte nie einen Eintrag. |
| `K00` Ausland, `L00` DBSB, `M00` Schwalbe | Gehören nicht zu den 17 Landesverbänden. DBSB und Schwalbe laufen wie bisher über ihre Vereinseinträge `L0001` und `M0001`. |
| die 17 Kreise in Württemberg | Sie haben laut DSB-Orga-Struktur keine eigene Kennziffer und tragen die ihres Bezirks (`C01` bis `C06` kommen in der Struktur mehrfach vor). Unter einer Kennziffer kann nur ein Verband stehen — das ist der Bezirk. |

Die Württemberger Kreise standen bis zum 06.10.2026 mit vierstelligen Nummern
(`4156` bis `4171`) in der Vereinsliste und zählten deshalb als Vereine. Diese
Nummern gibt es bei nu nicht mehr.

## Rangfolge der Quellen

1. **Die Verbandsstruktur von nu**, wenn sie vollständig ist — also alle 17
   Landesverbände enthält. Dann gilt nur sie.
2. Sonst der **örtliche Vereinsbestand** (Backend **Wertungsportal →
   Vereine**): veröffentlichte, nicht gelöschte Einträge mit Kennziffer auf
   `00`, nie `00000`.
3. Darunter die **feste Liste der 17 Landesverbände** im Code.

Die Stufen 2 und 3 greifen, wenn die Struktur nicht zu bekommen ist
(Schnittstelle gestört, Zugang nicht eingerichtet) oder Lücken hat. Eine
lückenhafte Struktur wird bewußt verworfen: Die ergänzte Liste liegt bis zu
einer Woche im Zwischenspeicher, und eine abgebrochene Antwort nähme dem
Frontend so lange die Verbände.

Was nu in der Vereinsliste selbst liefert, bleibt unangetastet.

## Was das für die Pflege heißt

Verbände werden **bei nu gepflegt**, nicht mehr im Backend:

| Fall | Was geschieht |
|---|---|
| Verband umbenannt | Der neue Name erscheint nach Ablauf des Zwischenspeichers; der Vereinsbestand wird beim Abgleich mitgeändert. |
| neuer Verband | Erscheint von selbst und wird im Vereinsbestand angelegt. |
| Verband aufgelöst | Verschwindet aus Navigation und Auswahl. **Im Vereinsbestand bleibt er stehen** — und damit in der Auswahl „Zuständig für" und in den Listen der Wertungsreferenten —, bis er unter **Wertungsportal → Vereine** abgeschaltet wird. Das Bundle löscht dort nichts. |

Ein im Backend geänderter Verbandsname hält nur, solange die Struktur von nu
nicht zu bekommen ist; der nächste Abgleich setzt den Namen von nu.

## Zwischenspeicher

Die Verbandsstruktur und die Liste aller Vereine und Verbände liegen bis zur
eingestellten „Cachezeit Verbände" im Zwischenspeicher (üblich: eine Woche),
der Name eines einzelnen Verbandes bis zur „Cachezeit Vereine" (ohne Auswahl:
ein Tag).

Gezielt verwerfen unter **Wertungsportal → Zwischenspeicher**, Suchart
**Verein**:

| Wert | Verworfen wird |
|---|---|
| `00000` | die Liste aller Vereine und Verbände **und** die Verbandsstruktur |
| `10100` | Name und Mitgliederliste dieses einen Verbandes oder Vereins |

Siehe [Zwischenspeicher gezielt leeren](zwischenspeicher.md).

## Prüfen

Was nu tatsächlich liefert, zeigt der [Rohdaten-Download](rohdaten.md):
„Alle Verbände (Landesverbände, Bezirke, Kreise)" für die Struktur, „Alle
Vereine" für die Vereinsliste. Dort wird nichts ergänzt.

Wie viele Verbände der Vereinsbestand kennt:

```sql
SELECT COUNT(*) FROM tl_wertungsportal_clubs
WHERE clubVkz LIKE '%00' AND clubVkz <> '00000' AND published = '1';
```

Nach dem ersten frischen Abruf der Vereinsliste sind es 196 (Stand 13.10.2026).

## Für Entwickler

| Teil | Datei |
|---|---|
| Abfrage `Verbandsstruktur` (Adresse, Zwischenspeicher, Statistik) | `Helper/API.php`: `adresse()`, `endpunkte()`, `cacheGruppen()`, `verbandsstruktur()` |
| Umwandlung der Struktur in Verbandseinträge | `API::verbaendeAusStruktur()` |
| Ergänzung der Vereinsliste, Rangfolge der Quellen | `API::BugfixVerbaende()` (lädt die Quellen), `API::ergaenzeVerbaende()` (rechnet) |
| feste Liste der Landesverbände | `API::landesverbaende()` |
| Verbandseinträge des Vereinsbestands | `Lokal::verbandseintraege()` |
| Prüfungen | `tests/Helper/ApiVerbaendeTest.php`, Rohantwort vom 13.10.2026 in `tests/fixtures/` |

- Die Struktur wird über `API::autoQuery()` geholt (Funktion
  `Verbandsstruktur`, Schlüssel `00000`) und je Seitenaufruf höchstens einmal.
  Einen örtlichen Ersatz für diese Abfrage gibt es nicht — dafür ist die
  Rangfolge oben da.
- Geladen wird sie nur, wenn ein Verband gefragt ist: Bei der Einzelabfrage
  eines Vereins oder des DSB faßt `BugfixVerbaende()` keine Quelle an.
- Ein ergänzter Eintrag trägt als `parentFederation` wie früher seine eigenen
  drei Stellen. Den übergeordneten Verband errechnen Verbandsnavigation und
  Verbandskette weiterhin aus der Kennziffer; das Feld `parentFederation` der
  Struktur wird nur gebraucht, um die Württemberger Kreise zu erkennen (sie
  nennen sich selbst).
- Im Notbetrieb (Schnittstelle nicht erreichbar) kommt die ganze Vereinsliste
  aus dem Vereinsbestand (`Lokal::abfrage()`); die Ergänzung läuft dann nicht.
