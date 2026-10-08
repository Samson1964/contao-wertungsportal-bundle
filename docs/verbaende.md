# Verbände, die nu nicht liefert

Landesverbände, Bezirke und Kreise sind in nu keine eigene Art von Datensatz —
sie standen als „Vereine" mit einer Kennziffer auf `00` in der Vereinsliste
(`/dwz/dwzliste/clubs`). Das Frontend braucht sie für die Verbandsnavigation,
die Verbandsauswahl der Turniersuche, die Vereinssuche und die Überschrift
der Verbandsranglisten.

## Was sich geändert hat

| Zeitraum | Was nu lieferte |
|---|---|
| bis 06.10.2026 | Baden (10000), Hessen (50000) und Sachsen (F0000) samt aller Bezirke und Kreise; die übrigen 14 Landesverbände fehlten schon immer |
| seit 07.10.2026 | **keinen einzigen Verbandseintrag** mehr |

Gemessen am 08.10.2026: Die Vereinsliste hatte 2.190 Einträge, keiner mit
einer Kennziffer auf `00`. Gegenüber dem örtlichen Bestand fehlten 195
Einträge — die drei Landesverbände und sämtliche Bezirke und Kreise (10100
Mannheim, 21000 Mittelfranken, 27100 Augsburg …). Die Einzelabfrage
(`clubs?vkz=10100`) antwortet mit einer leeren Liste.

## Wie das Bundle ergänzt

`API::BugfixVerbaende()` ergänzt die Antwort unmittelbar nach dem Abruf — vor
dem Abgleich mit dem Vereinsbestand und vor dem Zwischenspeichern. Seit 1.54.1
aus zwei Quellen, in dieser Rangfolge:

1. **Der örtliche Vereinsbestand** (Backend **Wertungsportal → Vereine**,
   Tabelle `tl_wertungsportal_clubs`). Er trägt den letzten Stand, den nu
   geliefert hat. Von hier kommen die Bezirke und Kreise.
2. **Die feste Liste der 17 Landesverbände** im Code — das Netz für
   Installationen ohne Bestand.

Regeln:

- Was nu selbst liefert, bleibt unangetastet. Liefert nu die Verbände wieder,
  tut die Ergänzung nichts mehr.
- Aus dem Bestand kommt nur, was **veröffentlicht** ist, nicht das
  Löschkennzeichen trägt, einen Namen hat und dessen Kennziffer auf `00` endet.
- `00000` wird nie übernommen. Das ist der Deutsche Schachbund; auf
  schachbund.de stand unter dieser Kennziffer ein gewöhnlicher Verein.
- Bei der Einzelabfrage kommt nur der angefragte Verband dazu.

## Was das für die Pflege heißt

Die Bezirke und Kreise werden **nicht mehr von nu aktualisiert**. Was sich dort
ändert, muß im Backend unter **Wertungsportal → Vereine** nachgezogen werden:

| Fall | Was zu tun ist |
|---|---|
| Bezirk oder Kreis umbenannt | Den Vereinsnamen des Eintrags ändern |
| Bezirk oder Kreis aufgelöst | Den Eintrag abschalten (Veröffentlichen aus) |
| neuer Bezirk oder Kreis | Einen Eintrag anlegen: VKZ auf `00` (z. B. `10C00`), Name, „Verband (VKZ)" = erstes Zeichen (`1`), „Übergeordneter Verband (VKZ)" = die ersten drei Zeichen (`10C`), veröffentlichen |

Ein **Landesverband** steht durch die feste Liste immer in der Ausgabe, auch
wenn sein Eintrag im Backend abgeschaltet ist. Sein Name läßt sich im Backend
ändern — der Bestand geht der festen Liste vor.

Danach den Zwischenspeicher der Verbandsliste verwerfen (nächster Abschnitt),
sonst zeigt das Frontend bis zu einer Woche den alten Stand.

## Zwischenspeicher

Die Liste aller Vereine und Verbände liegt bis zur eingestellten „Cachezeit
Verbände" im Zwischenspeicher (üblich: eine Woche), der Name eines einzelnen
Verbandes bis zur „Cachezeit Vereine" (ohne Auswahl: ein Tag).

Gezielt verwerfen unter **Wertungsportal → Zwischenspeicher**, Suchart
**Verein**:

| Wert | Verworfen wird |
|---|---|
| `00000` | die Liste aller Vereine und Verbände (ab 1.54.1) |
| `10100` | Name und Mitgliederliste dieses einen Verbandes oder Vereins |

Siehe [Zwischenspeicher gezielt leeren](zwischenspeicher.md).

## Prüfen

Wie viele Verbandseinträge der Bestand ergänzen kann:

```sql
SELECT COUNT(*) FROM tl_wertungsportal_clubs
WHERE clubVkz LIKE '%00' AND clubVkz <> '00000'
  AND published = '1' AND state <> 'DELETE_STATE_TRUE' AND clubName <> '';
```

Auf einer Installation, die bis zum 06.10.2026 lief, sind es rund 195. Bei 0
gibt es nur die 17 Landesverbände — Bezirke und Kreise müßten dann von Hand
angelegt oder aus einer Sicherung geholt werden.

Was nu tatsächlich liefert, zeigt der [Rohdaten-Download](rohdaten.md)
(„Alle Vereine und Verbände"): Dort wird nichts ergänzt.

## Für Entwickler

| Teil | Datei |
|---|---|
| Ergänzung, feste Liste der Landesverbände | `Helper/API.php`, `BugfixVerbaende()` |
| Verbandseinträge des Bestands | `Helper/Lokal.php`, `verbandseintraege()` |
| Aufrufe | `API::getAPI()`, Zweige `Verbaende` und `Vereinsname` |
| Prüfungen | `tests/Helper/ApiVerbaendeTest.php` |

`BugfixVerbaende()` nimmt die Einträge des Bestands als drittes Argument; ohne
Angabe liest es sie selbst. Scheitert das Lesen (Tabelle fehlt vor dem ersten
`contao:migrate`), bleibt es bei der festen Liste. Der Abgleich nach der
Ergänzung (`syncClubs`) schreibt für die ergänzten Einträge nichts — sie
stammen aus derselben Tabelle.

Im Notbetrieb (Schnittstelle nicht erreichbar) kommt die ganze Liste ohnehin
aus dem Bestand (`Lokal::abfrage()`); die Ergänzung läuft dann nicht.
