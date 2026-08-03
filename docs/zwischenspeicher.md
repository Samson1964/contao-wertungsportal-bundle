# Zwischenspeicher gezielt leeren

Backend-Modul **WP | Zwischenspeicher**. Es löscht die Cache-Einträge zu
*einem* Datensatz — statt den gesamten Zwischenspeicher zu leeren und damit
jede Seite wieder langsam zu machen.

Gedacht ist das für den Fall, dass nu einen einzelnen Datensatz nachträglich
korrigiert: ein nachberechnetes Turnier, eine geänderte Karteikarte, ein
Vereinswechsel.

## Bedienung

1. **Suchen nach** — Turnier, Spieler oder Verein
2. **Wert** eingeben
3. **Im Zwischenspeicher suchen** zeigt, was vorhanden ist (mit Speicherzeit,
   Gültigkeit und Größe), löscht aber nichts
4. **Gefundene Einträge löschen** wirft sie weg

| Suchart | Wert | Beispiel |
|---|---|---|
| Turnier | UUID aus der Adresse der Turnierseite | `381efcec-11f4-4fb5-b2d5-051bfcdbaf07` |
| Spieler | nu-Nummer | `NU4093214` |
| Verein | fünfstellige VKZ | `30052` |

Für einen **Verband** die dreistellige Nummer mit zwei Nullen eingeben, also
`40000` für 400 — so lautet dort der Schlüssel.

## Was jeweils betroffen ist

| Suchart | Gelöschte Funktionen |
|---|---|
| Turnier | Turnierinfo, Turnierauswertung, Turnierergebnisse und **alle** Spielberichtsbögen dieses Turniers |
| Spieler | Karteikarte, Turnierhistorie und die Spielberichtsbögen dieses Spielers |
| Verein | Mitgliederliste (auch die der Vereinslisten-Schnittstelle) und Vereinsname |

**Nicht betroffen sind die Suchen** — Spielerliste, Turnierliste und
Verbandsrangliste. Deren Schlüssel bestehen aus Suchbegriffen, Zeiträumen und
Filtern, nicht aus der Kennung eines Datensatzes; sie einem Turnier oder
Spieler zuzuordnen ginge nur durch Durchsehen aller Ergebnisse. Wer sie
loswerden will, leert den Zwischenspeicher über System → Systemwartung.

Abgelaufene Einträge werden mitgelöscht. Sie sind die Notreserve, falls die
Schnittstelle einmal nicht antwortet (siehe `CLAUDE.md`, Abschnitt
„Notbetrieb") — nach dem Löschen steht sie für diesen Datensatz nicht mehr
zur Verfügung, bis er wieder abgerufen wurde.

## Technisch

`Helper\Cachesuche` erledigt die Arbeit, `Classes\Cacheverwaltung` ist nur die
Bedienoberfläche (Template `be_wp_cache.html5`).

Der Zwischenspeicher liegt je Funktion in einem eigenen Verzeichnis
(`wp_Turnierinfo`, `wp_Karteikarte` …), je Eintrag eine Datei. **Der Dateiname
ist der SHA1-Wert des Schlüssels** — aus dem Dateinamen lässt sich der
Schlüssel also nicht zurückrechnen. Für die drei Sucharten heißt das:

* Wo der Wert der ganze Schlüssel ist (Turnierinfo, Karteikarte, Vereinsliste
  …), wird die Datei unmittelbar angesteuert.
* Wo er nur ein Namensteil ist, muss das Verzeichnis durchgesehen und in jeder
  Datei nachgeschaut werden. Das betrifft ausschließlich die
  Spielberichtsbögen, deren Schlüssel `<turnier>-<spieler>` lautet — beim
  Turnier über den Präfix, beim Spieler über den Suffix.

Wer eine weitere Suchart ergänzt, trägt sie in `Cachesuche::ARTEN` und
`Cachesuche::regeln()` ein; mehr ist nicht nötig.
