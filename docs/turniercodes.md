# Alte Turniercodes aus DeWIS (ab 1.58.0)

Bis zum Umzug ins Wertungsportal von nu hießen die Turnierseiten nach dem
DeWIS-Code, etwa `dwz-turniere/C619-C05-WAM.html`. Seitdem tragen sie die UUID
von nu (`dwz-turniere/019f75b9-af1a-7c34-ae8f-b3f278013b38.html`). Die
Verweise, die Verbände und Vereine über Jahrzehnte auf ihre Auswertungen
gesetzt haben, endeten auf einer Fehlermeldung.

Andreas Filmann (Hessischer Schachverband) hat aus portal64 die Zuordnung aller
übernommenen Turniere exportiert: 66.685 Turniere mit altem Code, UUID und den
Daten aus DeWIS. Das Bundle legt sie in der Tabelle
`tl_wertungsportal_altcodes` ab und nutzt sie für drei Dinge.

## Was damit geschieht

| | |
|---|---|
| **Weiterleitung** | `…/<alter Code>.html` → Auswertung, `…/<alter Code>/Ergebnisse.html` → Ergebnisse, `…/<alter Code>/<DeWIS-Spielernummer>.html` → Auswertung (die alten Spielernummern kennt nu nicht). Dauerhaft (301), damit auch Suchmaschinen umlernen. |
| **Mehrfach vergebene Codes** | 73 Codes tragen mehrere Turniere (157 Turniere). Statt einer Weiterleitung zeigt die Seite eine Auswahl mit Name, Verband, Turnierende, Runden und Tag der ersten Berechnung. |
| **Kopf der Turnierseiten** | „Alter Turniercode" und „Erste Berechnung am" (aus `computedOn`) — nu liefert beides nicht. Bei Turnieren, die erst im Wertungsportal entstanden sind, fehlen beide Zeilen. |

Turnierende und letzte Berechnung liefert nu für die übernommenen Turniere
selbst; im Testbestand stimmten sie bei allen 5.163 übernommenen Turnieren mit
dem Export überein (die letzte Berechnung bei 286 um einen Tag versetzt,
vermutlich Zeitzone). Die Seiten zeigen deshalb weiter die Werte von nu; die
Spalten aus DeWIS stehen nur in der Tabelle.

## Übernehmen

1. Bundle einspielen, dann `contao:migrate` — legt die Tabelle an.
2. Die Datei `tcode_uui.csv` auf den Server legen, etwa nach `files/`.
3. Probelauf, dann übernehmen:

```bash
vendor/bin/contao-console wertungsportal:turniercodes files/tcode_uui.csv --dry-run
```

```bash
vendor/bin/contao-console wertungsportal:turniercodes files/tcode_uui.csv
```

Der Befehl liest latin-1 (wie der Export) und UTF-8, gleicht über die UUID ab
und meldet neue, geänderte und unveränderte Zeilen. Er darf beliebig oft
laufen. Gelöscht wird nichts; eine im Backend berichtigte Zeile überschreibt
ein erneuter Lauf allerdings mit dem Wert aus der Datei. Danach kann die Datei
vom Server.

Die Tabelle steht im Backend unter **Wertungsportal → Alte Turniercodes**:
suchen nach Code, Name oder UUID, filtern nach VKZ, Zeilen bearbeiten oder
löschen.

## Voraussetzung: die alte Seite

Die Weiterleitung greift nur, wenn die alte Adresse beim Turniermodul
ankommt, also unter dem Seitenalias, das die Turnierseite heute hat. Hieß die
Turnierseite zu DeWIS-Zeiten anders, braucht das alte Alias eine
Weiterleitungsseite in Contao auf die heutige Turnierseite — sonst antwortet
Contao mit 404, bevor das Bundle den Code sieht.

## Für Entwickler

- `Helper\Altcodes`: Einlesen (`leseDatei()`, `zeile()`), Abgleich
  (`importiere()`), Nachschlagen (`nachCode()` vergleicht genau, damit
  „BÖ1" und „BO1" verschieden bleiben; `nachUuid()` merkt sich Treffer je
  Seitenaufruf), Zieladresse (`ziel()`). Fehlt die Tabelle, finden alle
  Abfragen nichts — die Seiten arbeiten wie vorher.
- `Turnier::alterCode()` leitet weiter oder baut die Auswahl; die Adresse
  wird aus der laufenden Anfrage absolut gemacht (unter 4.13 ist die
  Seitenadresse relativ).
- Contao 4.13: Der Hook `getPageIdFromUrl` legt jeden ersten Teil als `code`
  ab. Contao 5: `Helper::turnierParameterAusUrl()` übersetzt `<code>/…` nur,
  wenn der Code in der Tabelle steht.
- Tests: `tests/Helper/AltcodesTest.php`.
