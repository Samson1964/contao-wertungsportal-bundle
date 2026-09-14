# Insert-Tags mit Wertungsdaten

Vier Insert-Tags setzen Wertungsdaten einer Person in beliebige Inhalte —
Artikel, Nachrichten, Mannschaftsaufstellungen. Der Wert nach `::` ist die
**NU-Nummer** der Person: `NU` und Ziffern, so wie sie auf der Karteikarte und
unter WP | Personen steht.

| Tag | Ausgabe | Beispiel |
|---|---|---|
| `{{dwz::NU1234567}}` | aktuelle DWZ als reine Zahl | `1876` |
| `{{elo::NU1234567}}` | Elo als Verweis auf das FIDE-Profil | `<a href="https://ratings.fide.com/profile/12345678" target="_blank">2451</a>` |
| `{{ftitel::NU1234567}}` | FIDE-Titel als Kürzel | `IM` |
| `{{ftitel::NU1234567::lang}}` | FIDE-Titel ausgeschrieben | `Internationaler Meister` |
| `{{verein::NU1234567}}` | Vereinsname nach den Ersetzungen | `SF Königsspringer Süd` |
| `{{verein::NU1234567::12}}` | dasselbe, höchstens 12 Zeichen | `SF Königsspr` |

Jedes Tag gibt es auch mit dem Präfix `cache_` (`{{cache_dwz::NU1234567}}` und
so fort), mit derselben Ausgabe — siehe [unten](#präfix-cache_).

Eine kleingeschriebene Nummer (`nu1234567`) wird angenommen. Ausgeschrieben
werden diese Titel, alle anderen bleiben als Kürzel stehen:

| Kürzel | ausgeschrieben | Kürzel | ausgeschrieben |
|---|---|---|---|
| GM | Großmeister | WGM | Großmeisterin |
| IM | Internationaler Meister | WIM | Internationale Meisterin |
| FM | FIDE-Meister | WFM | FIDE-Meisterin |
| CM | Kandidatenmeister | WCM | Kandidatenmeisterin |

## Umstellung: NU-Nummer statt DeWIS-ID

Bis zum Helper-Bundle 2.x gab es dieselben Tags dort. Sie erwarteten die
**DeWIS-ID** und fragten die inzwischen abgeschaltete DeWIS-Schnittstelle ab.
Mit Helper-Bundle 3.0.0 sind sie dort entfallen und hier neu entstanden.

**Ein Tag mit einer alten DeWIS-ID bleibt leer.** Die ID wird bewusst nicht
umgedeutet: Reine Ziffern könnten eine andere Person treffen. Die Seiten
müssen deshalb auf NU-Nummern umgestellt werden.

Betroffene Inhaltselemente findet diese Abfrage:

```sql
SELECT id, pid, ptable, type FROM tl_content
WHERE CONCAT(IFNULL(text, ''), IFNULL(html, '')) REGEXP '\\{\\{(cache_)?(dwz|elo|ftitel|verein)::[0-9]';
```

Tags können auch in Teasern von Nachrichten und Veranstaltungen
(`tl_news.teaser`, `tl_calendar_events.teaser`) oder in HTML-Modulen
(`tl_module.html`) stehen — dort mit derselben Bedingung suchen.

Zur NU-Nummer führt die Karteikarte. Die Spalte `externeNr` der Personen trägt
meist die alte DeWIS-ID; den Namen dabei gegenprüfen:

```sql
SELECT nuLigaPersonId, firstname, lastname FROM tl_wertungsportal_persons WHERE externeNr = '10012345';
```

## Woher die Werte kommen

Aus den Spiegeltabellen dieses Bundles, **nicht** live von der Schnittstelle:

| Wert | Quelle |
|---|---|
| DWZ | `tl_wertungsportal_persons.rating` |
| Verein | `tl_wertungsportal_persons_memberships`: die erste aktive Mitgliedschaft mit laufender Spielgenehmigung, sonst die erste überhaupt; Platzhalter mit der Mitgliedsnummer 0000 zählen nicht |
| Elo, FIDE-Titel | `tl_wertungsportal_elo` über die FIDE-ID der Person |

Über die Schnittstelle hätte eine Seite mit 40 Spielern nach Ablauf des
Zwischenspeichers bis zu 40 Abrufe bei nu ausgelöst — nacheinander, jeder bis
zur eingestellten Wartezeit und jeder auf das Tokenkontingent. Elo und Titel
kämen ohnehin aus der Elo-Tabelle; auch die Karteikarte reichert die Antwort
von nu daraus an.

**Wie aktuell:** Die Personen werden bei jedem Abruf einer Karteikarte,
Spieler-, Vereins- oder Verbandsliste abgeglichen, und der
[Vorlader](vorladen.md) zieht nachts Karteikarten nach. Alle Mitglieder kommen
über den CSV-Import in den Bestand. Elo und Titel sind so aktuell wie der
letzte FIDE-Import. Wer im Spiegel fehlt, bekommt keine Ausgabe.

**Was es kostet:** Je Seitenaufruf wird jede Person einmal gelesen — alle Tags
derselben Person teilen sich eine Sperrprüfung und drei kurze Abfragen. In der
Testinstallation (Contao 4.13) brauchten 40 Personen mit je vier Tags 22 bis
27 ms.

**Besucherbremse, Statistik, Zugriffs-Log:** Die Tags zählen bei keinem davon.
Die [Besucherbremse](besucherbremse.md) zählt zwar ohnehin nur einmal je
Seitenaufruf (nachgemessen: fünf Prüfungen in einem Aufruf ergeben einen
Abruf), sie wird hier aber gar nicht gebraucht — welche Person ein Tag zeigt,
bestimmt der Redakteur, nicht der Besucher.

## Wann die Ausgabe leer bleibt

* kein Wert, oder der Wert ist keine NU-Nummer (auch eine alte DeWIS-ID)
* die Person ist unbekannt oder unveröffentlicht
* die Person ist **gesperrt** (WP | Personen) — dann bei allen vier Tags
* es gibt den Wert nicht: keine DWZ, keine Elo oder FIDE-ID, kein Titel, keine
  laufende Mitgliedschaft
* ein Fehler, etwa fehlende Tabellen vor `contao:migrate`. Die Seite bleibt
  davon unberührt; mit eingeschaltetem **Debug-Log** steht der Grund in
  `var/logs/wertungsportal_inserttags.log`

## Ersetzungen im Vereinsnamen

Wertungsportal → **Einstellungen** → Gruppe „Insert-Tags" → „Ersetzungen im
Vereinsnamen". Voreinstellung, Zeichen für Zeichen wie im Helper-Bundle 2.x:

| Suchen nach | Ersetzen durch |
|---|---|
| `Schachverein` | `SV` |
| `SABT+` | *(leer)* |
| `Schachclub` | `SC` |
| `Schachklub` | `SK` |
| `Schachfreunde` | `SF` |
| `+e.V.` | *(leer)* |
| `+eV` | *(leer)* |

Ein schon gespeicherter Wert gilt ohne Übernahme weiter — auch einer aus dem
Helper-Bundle 2.x, der Schlüssel `insert_verein_replaces` ist derselbe.

So wird ersetzt:

1. `+` steht in beiden Spalten für ein Leerzeichen, weil Contao Leerzeichen am
   Rand einer Eingabe abschneidet.
2. Die Zeilen wirken nacheinander von oben nach unten, ohne Rücksicht auf Groß-
   und Kleinschreibung (Umlaute nur in genau dieser Schreibweise).
3. Zeilen ohne Suchbegriff werden übersprungen.
4. Erst danach wird gekürzt — nach Zeichen, ein Umlaut zählt einfach. Eine
   Länge, die keine ganze Zahl über 0 ist, bleibt ohne Wirkung.
5. Sonderzeichen werden für HTML maskiert (`&` wird zu `&amp;`).

### Falle: Treffer mitten im Wort

Ein Suchbegriff trifft auch innerhalb eines Wortes. Im Testbestand (Auszug aus
schachbund.de, 1.158 Vereinsnamen) verändert die Voreinstellung 424 Namen,
sechs davon falsch:

| Vereinsname | Ergebnis | Ursache |
|---|---|---|
| Schachvereinigung Weilerbach, dazu vier weitere | SVigung Weilerbach | „Schachverein" steckt in „Schachvereinigung" |
| SV Rochade Eving 25/64 | SV Rochadeing 25/64 | `+eV` trifft das „ Ev" von „Eving" |

Den ersten Fall behebt eine Zeile **über** „Schachverein", etwa
`Schachvereinigung` → `SVg`. Den zweiten fängt die Liste nicht ab: `+eV` trifft
jedes Wort, das mit „ev" beginnt. Wer das vermeiden will, streicht die Zeile —
Namen auf „eV" behalten dann den Zusatz.

## Präfix `cache_`

Das Präfix wertet der Contao-Kern aus. Wird eine Seite zwischengespeichert,
speichert er ein `cache_`-Tag nicht mit, sondern setzt es bei jedem Aufruf als
Fragment neu ein. Ohne Seiten-Cache gibt es keinen Unterschied. In Contao 5 ist
die Schreibweise veraltet und entfällt mit Contao 6; Contao empfiehlt dort das
Tag `fragment`, und zwar **verschachtelt**: `{{fragment::{{dwz::NU1234567}}}}`.
Die Kurzform `{{fragment::dwz::NU1234567}}` gibt nur den Text
„dwz::NU1234567" aus — in Contao 4.13 und 5.7 gleichermaßen nachgesehen.

## Zusammenspiel mit dem Helper-Bundle

Der Hook dieses Bundles steht **vor** allen anderen `replaceInsertTags`-Hooks.
Das Helper-Bundle 2.x beantwortet die vier Tags noch selbst und gibt dabei eine
leere Ausgabe zurück; stünde es davor, blieben die Tags leer, solange es
installiert ist. Fremde Tags reicht dieses Bundle weiter, der Platz vorne nimmt
also keinem anderen Tag etwas weg. Ab Helper-Bundle 3.0.0 gibt es den Konflikt
nicht mehr; die `composer.json` erlaubt beide Fassungen.

Der Hook `replaceInsertTags` ist seit Contao 5.2 als veraltet markiert und
läuft bis Contao 6. Das Attribut `AsInsertTag`, das ihn ablöst, gibt es in
Contao 4.13 noch nicht.

## Geprüft

* `tests/Classes/InsertTagsTest.php`: Zerlegung, Langformen, Ersetzung mit `+`,
  Kürzung mit Umlauten, Maskierung, fremde Tags, gesperrte und unbekannte
  Personen, Fehler, eine Abfrage je Person
* in Contao 4.13.58 und 5.7.7 (PHP 8.4) gegen Testzeilen und den echten
  Bestand: Ausgabe aller Tags über den Insert-Tag-Parser des Kerns,
  Hook-Reihenfolge, Voreinstellung, eigene Ersetzungen, Besucherbremse,
  Einstellungsformular in Deutsch und Englisch
