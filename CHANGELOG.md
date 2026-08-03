# Wertungsportal Changelog

## Version 1.13.1 (2026-08-03)

* Change: **Das Beispielskript sagt jetzt, wenn gar nicht die Schnittstelle geantwortet hat.**
  Bisher gab es in diesem Fall nur „Fehler 401: unbekannt" — der Aufrufer stand damit im
  Dunkeln. Kommt kein JSON zurück, nennt das Skript den HTTP-Status, erklärt den häufigsten
  Grund (eine Bot-Sperre oder ein Zugriffsschutz des Webservers fängt den Aufruf ab, bevor er
  die Schnittstelle erreicht) und gibt die ersten 400 Zeichen der Antwort aus — dort steht
  dann zum Beispiel „Bot check"
* Das Skript schickt außerdem eine erkennbare Kennung mit (`Vereinsliste/1.0 (VKZ 30052)`),
  folgt Weiterleitungen und gibt bei einem Verbindungsfehler den cURL-Text aus statt nur
  „nicht erreichbar"
* Doc: Neuer Abschnitt „Wenn statt JSON etwas anderes kommt" in `docs/vereinslisten-api.md`.
  Wichtigstes Erkennungsmerkmal: **Steht im Backend unter WP | Zugangsschlüssel → Zugriffe
  nichts zu den Fehlversuchen, ist die Anfrage nie bei Contao angekommen** — dann liegt es am
  Webserver davor, und der Pfad `/wertungsportal-api/` muss dort ausgenommen werden

## Version 1.13.0 (2026-08-03)

**Beim Aktualisieren:** Die Schlüssel-E-Mail geht ab jetzt als HTML-Post mit Textteil hinaus.
Wer das nicht möchte, wählt in den Einstellungen bei „Vorlage der Schlüssel-E-Mail" die leere
Option — dann bleibt es beim reinen Text. **Bitte außerdem die neue Einstellung „Erlaubte
Abrufe je Tag" ansehen:** Ohne Eintrag gelten 24 Abrufe je Schlüssel und Tag; eine `0` hebt
die Grenze auf.

* Add: **Absenderadresse und Absendername** für die E-Mails des Bundles (Einstellungen,
  Bereich Wertungsportal). Ohne Eintrag gilt wie bisher die Adresse des Administrators und
  der Name der Website. Die Adresse sollte zur Domain der Website passen, sonst stufen viele
  Postfächer die Nachricht als Fälschung ein
* Add: **HTML-Vorlage für die Schlüssel-E-Mail**, auswählbar in den Einstellungen. Mitgeliefert
  wird `wp_mail_token.html5`; eigene Fassungen legt man als Kopie unter `templates/` an, der
  Dateiname muss mit `wp_mail_token` beginnen. Die Nachricht geht zweiteilig hinaus — HTML aus
  der Vorlage plus Textteil als Rückfallebene. Der Textteil bleibt wichtig: Manche Postfächer
  zeigen nur Text, und zum Herauskopieren des Beispielskripts ist er die verlässlichere Fassung
* Platzhalter der Vorlage: `token`, `vkz`, `verein`, `adresse`, `aufruf`, `email`, `vorname`,
  `nachname`, `name`, `abrufe`, `abrufetext`, `beispiel`, `freigabe`, `absender`. Sie sind
  im Kopf der Vorlage und in `docs/vereinslisten-api.md` beschrieben. Alle Werte sind roh und
  gehören im HTML durch `StringUtil::specialchars()` — sie stammen aus einem Formular, das im
  offenen Internet steht
* Add: Einstellung **„Erlaubte Abrufe je Tag"**. Sie ist nicht nur Text in der E-Mail, sondern
  wird auch durchgesetzt: je Zugangsschlüssel und Tag, gezählt werden dabei nur erfolgreiche
  Abrufe — wer eine Abfuhr bekommt, soll dafür nicht auch noch sein Kontingent verlieren.
  Anders als die bestehende Stundenbremse hängt die Grenze am Schlüssel und nicht an der
  IP-Adresse; sie greift also auch bei wechselnden Adressen. Ohne Eintrag 24, `0` = unbegrenzt
* Text- und HTML-Fassung der E-Mail beziehen ihre Werte aus derselben Stelle
  (`mailwerte()`), damit sie nicht auseinanderlaufen. Der Textteil nennt jetzt zusätzlich die
  Empfängeradresse und die erlaubten Abrufe je Tag
* Verifiziert mit 450 Tests (30 neue): Tagesgrenze samt Zählweise, Tageswechsel und
  Protokollierung, Absender-Rückgriffe, die Werte in der verschickten Nachricht sowie die
  HTML-Vorlage gegen das echte Contao gerendert — alle Platzhalter kommen an, der Vereinsname
  wird maskiert, das Beispielskript steht maskiert in einem `<pre>`-Block und wird nicht
  ausgeführt. Zusätzlich in der echten Installation geprüft, dass die vier neuen Felder in
  der Palette stehen und die Vorlage in der Auswahlliste erscheint

## Version 1.12.5 (2026-08-03)

**Beim Aktualisieren:** `contao:assets:install` (geändertes Stylesheet).

* **Fix: Das Registrierungsformular war vollständig ungestylt.** Die Ursache war schlicht: Das
  Template band weder `default.css` ein noch trug es die Klasse `dewis` am Rahmen — sämtliche
  Formularregeln des Bundles hängen aber daran. Beides ist ergänzt, damit greift dieselbe
  Gestaltung wie bei den Suchformularen
* Change: Die Felder stehen jetzt in **zwei Spalten** — links die Beschriftungen, rechts die
  Eingabefelder, beide jeweils bündig untereinander. Die linke Spalte wächst mit der längsten
  Beschriftung mit, hat aber eine Mindestbreite, damit die Felder nicht springen. Unter 620 px
  Fensterbreite klappt alles untereinander, die Beschriftung über ihr Feld
* Change: Die Hilfetexte sind als `tl_help tl_tip` ausgezeichnet und übernehmen die Werte aus
  dem Contao-Backend (0,75 rem, Zeilenhöhe 1,2, Farbe #808080). Die dortige Regel
  `height:15px; overflow:hidden` von `.tl_tip` bleibt bewusst außen vor — sie gilt im Backend
  einem einzeiligen Hinweis und würde die mehrzeiligen Texte hier abschneiden. Die eigenen
  Hilfetexte der Suchformulare (`wp-hilfe`) sehen genauso aus, damit im Bundle nicht zwei
  Stile nebeneinanderstehen
* Kleinigkeiten: Das Feld für die Vereinskennziffer läuft nicht mehr über die volle Breite
  (fünf Zeichen brauchen keine 600 px), Schaltfläche und Fehlermeldung stehen unter der
  Feldspalte statt unter den Beschriftungen, und die Beispieladresse bricht in schmalen
  Fenstern um, statt die Seite waagerecht scrollen zu lassen

## Version 1.12.4 (2026-08-03)

**Beim Aktualisieren:** `contao:assets:install` — es kommen zwei neue Bilddateien dazu.

* Add: **Das Bundle bringt jetzt eigene Platzhalterbilder mit** (`standard-verein.svg` und
  `standard-spieler.svg`). Ist in den Einstellungen kein Standardlogo bzw. Standardbild
  ausgewählt, zeigt die Vereinsseite bzw. die Karteikarte diese SVG-Dateien statt gar
  nichts. Damit sieht eine frische Installation auf Anhieb vernünftig aus — bisher musste
  man erst eine Datei in die Dateiverwaltung hochladen und in den Einstellungen auswählen
* Die Dateien liegen im Bundle und damit außerhalb der Dateiverwaltung. Sie laufen deshalb
  bewusst an der Bilderzeugung vorbei und werden im Template unmittelbar als `<img>`
  eingebunden. Die Adresse wird absolut gebildet (`Environment::get('path')`): Die
  Vereinsseite wird unter `/vereine/30066.html` ausgeliefert, ein relativer Pfad landete
  dort im Unterverzeichnis. Dass die Contao-Layouts ein `<base>` mitgeben, ist kein
  Verlass — es lässt sich abschalten
* **Fix: „Undefined array key wertungsportal_clubDefaultImage"** auf der Vereinsseite und
  dieselbe Warnung für `wertungsportal_playerDefaultImage` auf der Karteikarte, solange die
  Einstellung nie gespeichert wurde
* Fix: Die Bildgrößen-Einstellungen wurden mit `unserialize()` ohne Prüfung gelesen. Ohne
  gespeicherten Wert ergab das eine weitere Warnung und ein `false`, mit dem der
  Bild-Erzeuger nichts anfangen kann. Neue `Helper::bildgroesse()` liefert entweder eine
  gültige Größenangabe oder `null`
* Verifiziert mit 420 Tests (15 neue) und in der echten Contao-4.13-Installation: Vereins-
  und Karteikartenseite ohne Warnung, Platzhalter mit absoluter Adresse im Quelltext, beide
  SVG-Dateien werden mit HTTP 200 als `image/svg+xml` ausgeliefert

## Version 1.12.3 (2026-08-03)

* **Fix: Die Vereinsseite brach mit einem SQL-Fehler ab, wenn das alte DeWIS-Bundle nicht
  installiert ist** („Base table or view not found: tl_dwz_ver doesn't exist"). Das Modul las
  Logo, Homepage, Info und Alternativname zuerst aus `tl_wertungsportal_clubs` und fiel dann
  auf die Alttabelle zurück — und zwar bedingungslos, also auch dort, wo es sie gar nicht
  gibt. Dieselbe Falle steckte in der Karteikarte (`tl_dwz_spi` für das Spielerbild)
* Die Rückgriffe auf `tl_dwz_ver` und `tl_dwz_spi` sind aus dem Frontend **entfernt**. Das
  Wertungsportal hat DeWIS abgelöst und setzt dessen Tabellen nicht mehr voraus; die Daten
  stehen in den eigenen Tabellen, und wo sie fehlen, greift das Standardbild. Bestandsdaten
  holt man weiterhin einmalig über „Altdaten übernehmen" (WP | Vereine) und „Bilder
  übernehmen" (WP | Personen) herüber. **Wer diese beiden Übernahmen noch nicht ausgeführt
  hat, sollte das vor dem Update tun** — sonst verschwinden Vereinslogos und Spielerbilder,
  die bisher nur in den DeWIS-Tabellen lagen
* Die beiden Übernahmen selbst melden jetzt sauber „Die Tabelle … ist in dieser Installation
  nicht vorhanden", statt mit einem SQL-Fehler abzubrechen
* Entfernt: `Helper::Karteizuweisung()` — las ebenfalls `tl_dwz_spi` und wurde von nirgendwo
  aufgerufen
* Change: Kann der Zugangsschlüssel nicht per E-Mail zugestellt werden, sagt das Formular
  jetzt, dass der Schlüssel **angelegt wurde** und eine erneute Anforderung nichts ändert.
  Bisher stand dort nur „konnte nicht verschickt werden … später noch einmal versuchen" —
  das las sich, als sei gar nichts passiert, während im Backend sehr wohl ein Datensatz lag
* Verifiziert mit 405 Tests sowie in der echten Contao-4.13-Installation gegen die
  Live-Schnittstelle: Vereinsseite mit 112 Spielern, Karteikarte samt DWZ-Diagramm,
  Verbands- und Turnierseite — alle ohne DeWIS-Tabellen im System

## Version 1.12.2 (2026-08-03)

* Add: **Scheitert die Verbindung zur Schnittstelle, steht der Grund jetzt im Systemprotokoll**
  (System → Systemlog). Im Frontend erscheint weiterhin nur „Der Abruf von Live-Daten ist
  z.Z. nicht möglich" — richtig für Besucher, für den Betreiber aber wertlos: Ob die
  Schnittstelle streikt, die eingestellte Wartezeit zu knapp ist oder dem Server schlicht die
  Wurzelzertifikate fehlen, macht einen erheblichen Unterschied. Protokolliert wird der
  cURL-Fehlertext, und zwar höchstens einmal je Seitenaufruf — eine Seite setzt mehrere
  Abfragen ab, bei einer Störung scheitern sie alle, und das Protokoll soll den Grund nennen
  statt zuzulaufen
* Anlass war ein Fall aus der Praxis: Zugangsdaten korrekt eingetragen, Live-Abruf
  eingeschaltet, trotzdem kamen keine Daten. Ursache war ein PHP ohne konfigurierte
  Wurzelzertifikate (`curl.cainfo`), das jede HTTPS-Verbindung mit „self-signed certificate in
  certificate chain" abwies. Das Bundle verhielt sich korrekt — es war nur nicht erkennbar,
  woran es lag
* Verifiziert mit 403 Tests (drei neue: der cURL-Text landet im Protokoll, genau eine Zeile
  je Seitenaufruf, weitere Fehlschläge schreiben nicht noch einmal)

## Version 1.12.1 (2026-08-03)

Beim Einrichten des Bundles in einer frischen Contao-4.13-Installation gefunden.

* **Fix: Eine noch nicht eingerichtete Installation lieferte HTTP 500.** Sobald die
  Zugangsdaten der Schnittstelle in den Einstellungen fehlten, bekamen die getypten
  Eigenschaften des OAuth2-Clients `null` und PHP brach mit einem TypeError ab
  (`Cannot assign null to property … $apiBaseUrl of type string`). Betroffen war jede Seite,
  die ein Modul einbindet, das beim Aufbau Daten braucht — Verbands- und Turnierseite fielen
  sofort aus, noch bevor jemand etwas eingegeben hatte. Die Werte werden jetzt ausdrücklich
  in Zeichenketten gewandelt
* Zusätzlich prüft `autoQuery()` vor jedem Abruf, ob Basisadresse, Kennung, Geheimnis und
  Token-Adresse überhaupt gepflegt sind. Fehlt eine davon, wird gar nicht erst verbunden,
  sondern derselbe Weg wie bei abgeschalteter Schnittstelle genommen: örtlicher Bestand plus
  Hinweis. Eine frisch installierte Erweiterung verhält sich damit wie eine mit gestörter
  Schnittstelle statt wie eine kaputte
* Fix: 25 Abfragen der Einstellung `wertungsportal_debuglog` im OAuth2-Client lösten in einer
  Installation ohne gespeicherte Einstellungen jeweils eine „Undefined array key"-Warnung aus
* **Fix: Contao löschte die Aufzeichnung abgewiesener Anfragen.** Anfragen ohne gültigen
  Zugangsschlüssel werden mit `pid = 0` festgehalten; `DC_Table::reviseTable()` hält solche
  Zeilen für verwaiste Kinddatensätze und räumt sie beim Öffnen des Backend-Moduls weg — also
  ausgerechnet die Zeilen, wegen denen die Aufzeichnung existiert. Die Auswertung „Abgewiesene
  Anfragen je IP-Adresse" wäre damit nach jedem Blick ins Backend leer gewesen. Behoben mit
  `doNotDeleteRecords` in der Konfiguration der Zugriffstabelle
* Verifiziert mit 393 Tests, davon 4 neue gegen die echte Contao-4.13-Installation: Sie legen
  Zeilen ohne Schlüssel an, rufen `reviseTable()` über Reflexion genauso auf wie das
  Backend und weisen nach, dass die Zeilen stehen bleiben — samt Gegenprobe, dass Contao sie
  ohne den Schalter für verwaist hielte. Dazu sieben Tests für den nicht eingerichteten Fall
  (keine Verbindung, Hinweis statt Fehler, Client ohne Absturz erzeugbar, ein leeres Geheimnis
  genügt nicht). Die älteren Prüfstände setzen jetzt Zugangsdaten, weil sie sonst im neuen
  Notbetrieb landen statt im gemessenen Fall

## Version 1.12.0 (2026-08-03)

**Beim Aktualisieren:** `contao:migrate` (zwei neue Tabellen), `contao:assets:install`
(geänderte backend.css) und ein Neuaufbau des Produktions-Caches — es kommt eine neue
Route hinzu, die sonst nicht bekannt wird.

* Add: **Vereinslisten-Schnittstelle.** Unter `/wertungsportal-api/vereinsliste?token=…&vkz=…`
  liefert die Website die Mitgliederliste eines Vereins als JSON. Gedacht ist sie für
  Vereinswebsites, die ihre Spielerliste selbst ausgeben wollen. Gegenüber dem unmittelbaren
  Zugriff auf nu hat sie zwei Vorteile: Die FIDE-Daten (Elo, Titel, Nation) sind hier
  aktuell, weil sie beim Abruf aus der eigenen Elo-Tabelle ergänzt werden, und die Antworten
  laufen über denselben Zwischenspeicher wie das Frontend — die nu-Schnittstelle wird also
  nicht bei jedem Aufruf belastet. Vollständige Beschreibung in `docs/vereinslisten-api.md`
* Ausgegeben werden je Spieler nur die Angaben zur Person; der Ballast der nu-Antwort
  entfällt. Der Mitgliedsstatus (A/P) und die Mitgliedsnummer stammen ausschließlich aus der
  Mitgliedschaft **im angefragten Verein** — welchen anderen Vereinen jemand angehört, geht
  den Abrufer nichts an. Gesperrte Personen (Blacklist) fehlen wie im Frontend; sortiert wird
  umlautsicher nach derselben Regel wie die Aliasfelder
* Add: **Frontend-Modul „Schnittstellen-Registrierung"**. Formular mit Vereinskennziffer,
  Vor- und Nachname sowie E-Mail-Adresse; der Schlüssel geht sofort an diese Adresse — mit
  einem vollständigen PHP-Beispielskript, in dem Schlüssel, Vereinskennziffer und Adresse
  bereits eingesetzt sind. Die Vereinskennziffer wird gegen den örtlichen Vereinsbestand
  geprüft, sonst bekäme der Antragsteller einen Schlüssel, der ihm dauerhaft nur Fehler
  liefert. Eine zweite Anforderung derselben Person für denselben Verein verschickt den
  vorhandenen Schlüssel erneut, statt einen weiteren anzulegen
* Add: **Backend-Modul „WP | Zugangsschlüssel"** mit der Schlüsseltabelle und den Zugriffen
  als Kindtabelle (ein Datensatz je Anfrage mit Zeitpunkt, Quelle, Trefferzahl, Dauer und
  IP-Adresse). Schlüssel lassen sich am Datensatz sperren (mit Begründung) oder über das
  Auge-Symbol unveröffentlichen; gesperrte Schlüssel stehen in der Liste durchgestrichen.
  Die globale Operation „Alte Zugriffe löschen" entfernt alles, was älter als 90 Tage ist
* Add: Das Statistik-Modul zeigt für den gewählten Zeitraum zusätzlich die Zugriffe je
  Schlüssel und Verein (Anfragen insgesamt und davon erfolgreich) sowie die abgewiesenen
  Anfragen je IP-Adresse samt Grund — daraus lässt sich die Sperrliste befüllen
* Add: Zwei Einstellungen im Bereich Wertungsportal. **Gesperrte IP-Adressen** (eine je
  Zeile, `#` leitet einen Kommentar ein) weist Anfragen von dort ab. **Neue Schlüssel erst
  nach Freigabe** legt angeforderte Schlüssel unveröffentlicht an; sie liefern erst nach
  Freischaltung Daten, und die Bestätigungsmail sagt das auch. Ohne diesen Haken bekommt
  jeder, der eine Vereinskennziffer kennt, sofort Zugriff auf die Mitgliederliste dieses
  Vereins — das ist so gewollt, sollte aber eine bewusste Entscheidung sein
* Bremsen gegen Missbrauch: 120 Anfragen je Stunde und IP-Adresse an die Schnittstelle,
  fünf Schlüsselanforderungen am Tag je E-Mail-Adresse und je IP, dazu ein für Besucher
  unsichtbares Feld im Formular (Honigtopf). Die Prüfung der IP-Sperre und die Bremse stehen
  bewusst **vor** der Parameterprüfung — sonst ließe sich beides mit fehlerhaften Aufrufen
  umgehen. Abgewiesene Anfragen werden mitgeschrieben, auch die ohne gültigen Schlüssel:
  sonst bliebe systematisches Raten unsichtbar
* **Datenschutz:** Die Zugriffstabelle speichert IP-Adressen (Aufbewahrung 90 Tage), der
  Schlüssel merkt sich die Adresse, von der er angefordert wurde. Beides dient allein der
  Missbrauchserkennung und gehört in die Datenschutzerklärung
* Geprüft: Punkt 1 der Aufgabenliste erforderte keine Änderung — die FIDE-Anreicherung läuft
  in `API::autoQueryIntern()` bereits vor `$cache->store()`, im Zwischenspeicher liegen also
  angereicherte Antworten
* Verifiziert mit 147 Tests. 105 davon gegen echtes MySQL (Zugangsprüfung mit allen fünf
  Abweisungsgründen, Sperre von Schlüssel und IP, Bremse mit 120 Vorbelegungen, Aufbereitung
  der Spielerliste einschließlich Feldbestand, Statusumsetzung und Sortierung, Protokoll und
  Zähler, Schlüsselverwaltung, Auswertung, Registrierungsformular mit Honigtopf,
  Wiederanforderung, Freigabepflicht und gescheitertem Versand); 42 gegen die echte
  Contao-4.13-Installation (Routenladung, Controller-Antwort, DCA-Struktur, Sprachdateien,
  Templates). Dabei gefunden: Das ContaoManager-Plugin hatte `LoaderInterface` statt
  `LoaderResolverInterface` in der Signatur — PHP hätte die Klasse gar nicht erst geladen,
  und weil das Plugin bei jedem Aufruf geladen wird, hätte das die ganze Seite lahmgelegt.
  Ebenfalls gefunden: Dem Feld `published` der Schlüsseltabelle fehlte `toggle => true`,
  ohne das DC_Table den Schalter in der Liste abweist

## Version 1.11.3 (2026-08-03)

**Wichtig beim Aktualisieren:** Das Standardbild für Spieler und das für Vereine müssen in
den Einstellungen einmal neu ausgewählt und gespeichert werden. Die bisher gespeicherten
Werte sind beschädigt und werden durch das Update nicht repariert.

* Fix: Die beiden Standardbilder (Einstellungen, Bereich Wertungsportal) blieben in der
  Karteikarte und auf der Vereinsseite wirkungslos. Der Dateibaum liefert die Kennung der
  Datei als 16 Byte langen Binärwert; die Einstellungen landen aber in
  `system/config/localconfig.php`, also in einer PHP-Datei mit einfach gequoteten
  Zeichenketten. Nullbytes und Backslashes überleben das nicht — aus 16 Byte wurden beim
  Zurücklesen 19, und `FilesModel::findByUuid()` fand die Datei nie. Ein `save_callback`
  legt die Kennung jetzt in der lesbaren Schreibweise ab, die dieselbe Methode ebenso
  versteht. Der Fehler fiel nicht auf, weil im Backend weiterhin ein Bild ausgewählt aussah.

## Version 1.11.2 (2026-08-02)

* Change: Die beiden Auswahllisten der Bildgrößen in den Einstellungen (Spieler- und
  Vereinsbild) holen den Dienst jetzt unter seinem aktuellen Namen `contao.image.sizes`.
  Der bisher benutzte Name `contao.image.image_sizes` ist unter Contao 4.13 nur ein
  veralteter Alias auf denselben Dienst und in Contao 5 entfernt — dort bräche die
  Einstellungsseite mit „You have requested a non-existent service“ ab.

## Version 1.11.1 (2026-08-02)

* **Fix: Das Bundle ließ sich neben dem Helper-Bundle 2.0.0 nicht mehr installieren — mit der Folge, dass Composer stillschweigend auf Version 1.0.9 zurückfiel.** Die Anforderung lautete `^1.8.10` und schließt damit alles ab 2.0 aus. Weil 1.0.9 die letzte Fassung ist, die das Helper-Bundle noch als `*` fordert, war sie nach dem Erscheinen der 2.0.0 plötzlich die einzige installierbare — ein Update wurde so zum Downgrade über 30 Versionen hinweg, ohne Fehlermeldung
* Erkennbar war das an `contao:migrate`: Es schlug vor, die Tabelle tl_wertungsportal_stats sowie die Felder clubNameAlias, firstnameAlias, lastnameAlias und labelAlias zu löschen — genau der Schemastand, der in 1.3.0 und 1.6.0 dazugekommen war. Im Backend fehlte entsprechend das Modul „Statistik". **Wer diese Vorschläge ausgeführt hat, verliert die gesamte Abrufhistorie und muss die Aliase anschließend über contao:migrate neu erzeugen lassen**
* Die Anforderung lautet jetzt `^1.8.10 || ^2.0`. Aus dem Helper-Bundle nutzt das Wertungsportal ausschließlich die Cache-Klasse; deren Methoden sind in 2.0.0 unverändert vorhanden (isCached und retrieve mit dem Schalter für abgelaufene Einträge, store, getExpiration, getStoreTime). Der Cache-Pfad wird dort über kernel.project_dir statt über DOCUMENT_ROOT ermittelt und zeigt auf dasselbe Verzeichnis
* Lehre für die übrigen Bundles: Eine Anforderung wie `^1.8.10` verhindert nicht, dass installiert wird — sie sorgt dafür, dass eine ALTE Version installiert wird, sobald die Abhängigkeit einen Hauptversionssprung macht. Sichtbar wird das nur an dem, was danach fehlt

## Version 1.11.0 (2026-08-02)

* **Add: „Gibt es nicht"-Antworten (HTTP 404) werden 10 Minuten gemerkt.** Bisher holte JEDER Besucher dieselbe Fehlanzeige einzeln bei der Schnittstelle ab — im Zugriffs-Log vom 30.07.2026 fragten sechs verschiedene Besucher binnen drei Minuten dasselbe Turnier ohne Auswertung ab (85 solcher Abrufe an dem Tag). Bewusst kurz, denn eine Auswertung kann jederzeit nachgereicht werden; nach Ablauf der Frist wird sofort wieder nachgefragt
* Nur 404 wird gemerkt: Ein 401/403 ist ein Zugangsproblem und ein 5xx eine Störung — beides darf sich nicht festsetzen. Die gemerkte Fehlanzeige taugt außerdem NICHT als Notreserve: Fällt die Schnittstelle später aus, wird sie übersprungen und stattdessen im örtlichen Bestand gesucht, denn eine Fehlanzeige als „zwischengespeicherte Daten" auszugeben wäre irreführend. Über der Ausgabe erscheint deshalb auch kein Cache-Hinweis, sondern nur die Meldung der Schnittstelle
* **Change: Die Trefferliste der Spielersuche sortiert jetzt nach derselben Regel wie die Aliasfelder der Datenbank** (ä → ae, ö → oe, ü → ue, ß → ss). Vorher folgte sie der anderen deutschen Sortiernorm (ä wie a), sodass die angezeigte Reihenfolge von der Reihenfolge abweichen konnte, in der die örtliche Suche ihre Treffer liefert (ORDER BY lastnameAlias, firstnameAlias). Beide stimmen jetzt überein. Das Aliasfeld selbst lässt sich dafür nicht verwenden: Es gibt es nur in den örtlichen Spiegeltabellen, die Treffer der Schnittstelle bringen keines mit, und beide stehen in derselben Liste
* Verifiziert mit 22 Tests: Merken und Ablauf der Fehlanzeige, keine Anfrage solange sie gilt, nachgereichte Auswertung kommt an, 500 und 403 werden weiterhin bei jedem Aufruf versucht, abgelaufene Fehlanzeige wird nicht als Notreserve wiederbelebt, und der Sortierschlüssel erzeugt für zwölf Beispielnamen dieselbe Reihenfolge wie eine Sortierung über die Aliasfelder

## Version 1.10.0 (2026-07-30)

* **Fix: Die Spielersuche durchsucht jetzt IMMER auch den örtlichen Datenbestand**, nicht mehr nur dann, wenn die Schnittstelle gar nichts liefert. Der gemeldete Fall zeigt, warum: Eine Suche nach „Eschen" fand dort „Eschen, Alexander", aber nicht „Eschenauer, Frank" — und „Esch" fand wiederum drei ganz andere Spieler, ohne die beiden. Sobald die Schnittstelle irgendetwas zurückgab, unterblieb die örtliche Suche und die Trefferliste blieb unvollständig. Beide Ergebnisse werden nun zusammengeführt
* Add: Neue Spalte **„Quelle"** hinter „Verein" mit dem Wert „API" oder „Lokal". Bei Dubletten gewinnt der Datensatz der Schnittstelle — er ist der aktuellere; erkannt werden sie über die nuLigaPersonId. Datensätze ohne diese Nummer werden nicht zusammengelegt, sonst fielen sie alle auf einen zusammen
* Add: **Die Trefferliste der Spielersuche lässt sich über die Spaltenköpfe sortieren**, wie die übrigen Listen (Kalenderwoche, DWZ, Elo und FIDE-Titel mit den passenden Sortierregeln)
* Der Hinweis über der Liste erklärt jetzt den Regelfall statt eines Ausnahmefalls: Er erscheint, sobald örtliche Treffer dabei sind, und weist darauf hin, dass deren DWZ und letzte Auswertung dem zuletzt übernommenen Stand entsprechen
* Fix (Nebenbefund): Die Trefferliste sortierte Namen mit Umlauten ans Ende, wenn auf dem Server das Locale de_DE.UTF-8 fehlt. `setlocale` fällt in dem Fall stillschweigend auf „C" zurück, und `strcoll` vergleicht dann Bytes — „Ärmel" landete hinter „Zander". Sortiert wird jetzt über einen eigenen Schlüssel nach deutscher Namensregel (Ä wie A, Ö wie O, Ü wie U, ß wie ss), unabhängig von der Ausstattung des Servers
* Zum Aufwand: Die örtliche Suche kostet eine indizierte Abfrage über die Aliasfelder plus das Nachladen der Mitgliedschaften — gemessen 8,5 ms bei 300 Treffern. Sie läuft jetzt bei jeder Namenssuche mit
* Verifiziert mit 16 Tests: Quellenkennzeichnung, Zusammenführung samt Dubletten (Schnittstelle gewinnt, mit ihren Werten), Datensätze ohne nu-Nummer, Sortierung mit Umlauten, leere Fälle sowie der gemeldete Fall selbst — die örtliche Suche nach „Eschen" und nach „Esch" findet beide Spieler
* ACHTUNG: contao:assets:install (geänderte CSS)

## Version 1.9.0 (2026-07-30)

* **Add: Cachezeit der Turnierdaten nach Alter des Turniers gestaffelt.** Daten, die sich auf genau EIN Turnier beziehen, ändern sich nach der Erstauswertung im Wesentlichen nur im ersten Jahr. Es gibt deshalb zwei Einstellungen: „Cachezeit Turnierdaten (bis zu 1 Jahr alt)" — die bisherige, nur umbenannt — und neu „Cachezeit Turnierdaten (über 1 Jahr alt)", die bis „Unbegrenzt" reichen kann. Ohne eigene Auswahl gilt für alte Turniere dieselbe Zeit wie für junge, das bisherige Verhalten bleibt also unverändert
* Die Entscheidung fällt beim SPEICHERN, nicht beim Lesen: Das Turnierende geht erst aus der Antwort hervor. Turnier-Kopfdaten und Spielberichtsbogen führen es flach unter body, die DWZ-Auswertung im tournament-Knoten; die Ergebnisliste enthält nur Partien — dort kommt das Datum aus dem örtlichen Turnierbestand, den der Abgleich kurz zuvor gefüllt hat. Ist das Turnierende nirgends zu ermitteln, bleibt es bei der normalen Cachezeit
* Change: **Turnier-Kopfdaten (Turnierinfo) zählen jetzt zu den Turnierdaten** statt zur Turniersuche. Es sind die Kopfdaten eines einzelnen Turniers und damit genauso stabil wie Auswertung, Ergebnisse und Spielberichtsbogen. Wer für beide Gruppen unterschiedliche Zeiten eingestellt hat, sollte die Einstellungen einmal durchsehen
* **Add: Längere Cachezeiten überall wählbar** — 2, 3, 4 und 6 Monate, 1 Jahr sowie „Unbegrenzt". Für „Unbegrenzt" war ein eigener Wert nötig: Die 0 bedeutet in diesen Einstellungen seit jeher „gar nicht cachen" und stand deshalb nicht zur Verfügung. Ein unbegrenzter Eintrag läuft nie ab, der Hinweis über der Ausgabe nennt dann folgerichtig keinen Erneuerungszeitpunkt
* ACHTUNG bei „Unbegrenzt": Sollte nu ein altes Turnier doch noch einmal nachberechnen, bleibt der gespeicherte Stand stehen, bis der Cache über die Systemwartung geleert wird. Der Hilfetext in den Einstellungen weist darauf hin
* **Add: Die Karteikarte nennt das Datum einer DWZ-Umstufung.** Der Name führt oft nur das Jahr („Umstufung 2026"), das Stichdatum steht im referenceDate — es erscheint jetzt in Klammern dahinter (TT.MM.JJJJ). Fehlt das Datum, bleibt der Name für sich stehen, es entsteht keine leere Klammer
* Verifiziert mit 26 Tests gegen echte Cache-Dateien: alle neuen Stufen, „Unbegrenzt" als eigener Wert und seine Abgrenzung zu „kein Cache", die Staffelung für alle vier Turnierfunktionen samt Grenzfall (11 Monate gilt als jung, 13 als alt), das Nachschlagen des Turnierendes im örtlichen Bestand, der Sonderfall „junge Turniere nicht cachen, alte schon", sowie ein unbegrenzter Eintrag, der nach 400 Tagen weiterhin ausgeliefert wird

## Version 1.8.1 (2026-07-30)

* **Fix: HTTP 500 auf den Turnierergebnissen (Kreuztabelle), sobald der Notbetrieb griff.** Die örtliche Partienabfrage aus 1.8.0 las die Felder whitePlayerName und blackPlayerName mit — die sind aber **keine Datenbankspalten**, sondern reine Anzeigefelder des DCA (input_field_callback, der den Namen zur Laufzeit aus der Auswertungstabelle holt). Bei der Messung auf schachbund.de am 30.07.2026 fiel das auf: Die Schnittstelle antwortete für ein Turnier nicht (der bekannte CANCEL-Fehler von nu), der Notbetrieb sprang ein und lief in „Unknown column 'whitePlayerName'". Die Spalten stehen nicht mehr im SELECT; fehlt ein Spieler in der Auswertungstabelle, bleibt ein Datensatz mit UUID und leeren Namen
* **Fix (die eigentliche Lehre): Der Notbetrieb kann keinen Fehler mehr durchlassen.** Lokal::abfrage() fängt jetzt jeden Fehler der örtlichen Abfrage ab und behandelt ihn wie „örtlich nichts gefunden" — die Ausgabe zeigt dann die Meldung, dass keine Live-Daten verfügbar sind. Der Notbetrieb springt ein, wenn die Schnittstelle schon versagt hat; er darf die Lage nicht verschlimmern. Vorher wurde aus einer sauber behandelten Fehlermeldung ein HTTP 500. Dieselbe Absicherung hatte die Statistikzählung von Anfang an — bei der örtlichen Abfrage fehlte sie
* Verifiziert mit 51 Tests (zwei neue): Ein Prüfer vergleicht die DCA-Felder ohne sql-Definition gegen den Code von Lokal.php und schlägt an, sobald ein Anzeigefeld als Spalte gelesen wird; ein zweiter benennt die Partientabelle um und erwartet die Meldung statt eines Absturzes. Das Testschema führte dieselben Phantomspalten wie der Code und konnte den Fehler deshalb nicht finden — es entspricht jetzt der echten Tabelle

## Version 1.8.0 (2026-07-29)

* **Add: Örtlicher Datenbestand als dritte Auslieferungsstufe.** Steht die Schnittstelle nicht zur Verfügung (abgeschaltet oder ohne Antwort) und liegt auch im Zwischenspeicher nichts vor, werden die eigenen Tabellen abgefragt. Umgesetzt für ALLE zwölf Funktionen: Spielersuche, Karteikarte, Turnierhistorie samt DWZ-Umstufungen, Vereins- und Verbandsliste, Vereinsname, Verbändeliste, Turniersuche, Turnier-Kopfdaten, DWZ-Auswertung, Turnierergebnisse und Spielberichtsbogen. Jede Antwort wird in der Form der Schnittstelle gebaut, die Aufbereitung im Frontend bleibt dadurch unverändert
* Bemerkenswert dabei: Die Partien liegen örtlich redundanzfrei (nur mit den Spieler-UUIDs des Turniers), die Spielerdaten in der Auswertungstabelle. Für Turnierergebnisse und Spielberichtsbogen werden beide wieder zusammengeführt; fehlt ein Spieler in der Auswertung, bleibt wenigstens der in der Partie gespeicherte Name stehen, und eine Partie ohne Gegner (kampflos) bleibt eine Partie ohne Gegner
* **Add: Hinweis mit Stand der Daten.** „Der Abruf von Live-Daten ist z.Z. nicht möglich. Angezeigt werden Daten aus dem örtlichen Datenbestand (letzte Aktualisierung am TT.MM.JJJJ HH:MM Uhr)." Kommen auf einer Seite beide Rückfallebenen vor, nennt der Hinweis sie in einem Satz
* **Add: Örtliche Abfragen in der Statistik** als dritte Quelle neben API und Cache — eigene Spalte in der Übersicht, eigene Farbe im gestapelten Diagramm, eigener Legendeneintrag. Die frühere Spalte „Cache-Anteil" heißt jetzt „ohne API" und zählt Cache und örtlichen Bestand zusammen
* **Add: Zugriffs-Log** (System → Einstellungen → Wertungsportal, „Zugriffs-Log"). Schreibt je Abfrage eine Zeile nach `var/logs`: Zeitpunkt, Quelle, Funktion, Endpunkt, Cacheschlüssel, Gesamtdauer in ms, Dauer des reinen Schnittstellenaufrufs, HTTP-Code, Trefferzahl, IP-Adresse, Browser, Seite, Herkunft und ob ein Mitglied angemeldet ist. Semikolon-getrennt mit Kopfzeile, also sowohl lesbar als auch direkt in einer Tabellenkalkulation zu öffnen. **Eine Datei je Tag** — eine einzige, endlos wachsende Datei wäre auf einer besuchten Website unbrauchbar. **DATENSCHUTZ:** Die IP-Adresse ist ein personenbezogenes Datum; dauerhafter Betrieb gehört in die Datenschutzerklärung, und die Dateien sollten regelmäßig gelöscht werden. Der Name eines angemeldeten Mitglieds wird bewusst NICHT protokolliert
* **Fix (Laufzeit): Die Verbandsrangliste aus dem örtlichen Bestand brauchte 410 ms für einen Landesverband und 972 ms für die DSB-Topliste.** Zwei Ursachen: Der Verbund über die Mitgliedschaften mit DISTINCT zwang MySQL in eine Zwischentabelle, und der zweite Sortierschlüssel (Wertungsindex) machte die Indexordnung unbrauchbar, sodass 95.000 Zeilen nachsortiert wurden. Jetzt prüft die Abfrage die Mitgliedschaft per EXISTS und sortiert nur nach DWZ — bei gleicher DWZ entscheidet die Nachsortierung in PHP, was bei wenigen hundert Zeilen nichts kostet. Dazu ein neuer Index (published, rating). **Gemessen an 95.000 Personen: 12,8 ms statt 410 ms (Landesverband mit 8.000 Mitgliedern) und 72 ms statt 972 ms (ganzer DSB).** Der Wertungsindex kann NICHT in den Index aufgenommen werden — `index` ist ein reserviertes MySQL-Wort
* **ACHTUNG contao:migrate nötig** (neuer Index auf tl_wertungsportal_persons). Ohne ihn läuft die Rangliste im Notbetrieb in die alten Laufzeiten
* **Zur Frage, ob die örtlichen Abfragen zusätzlich gecacht werden sollten (Vorschlag Präfix „lokal_"): nein, das lohnt nicht.** Gemessen an 95.000 Personen, 8.000 Mitgliedschaften im Landesverband: Verbändeliste 0,2 ms, Karteikarte 0,5 ms, Turnierauswertung 0,4 ms, Turnierergebnisse 0,5 ms, Vereinsliste (160 Mitglieder) 3,1 ms, Spielersuche (300 Treffer) 8,5 ms, Verbandsrangliste 12,8 ms, DSB-Topliste 72 ms — je Abfrage zwei bis drei Datenbankzugriffe. Ein eigener Zwischenspeicher würde dem ohnehin veralteten Spiegel eine zweite Veralterungsschicht aufsetzen: Nach einem CSV-Import wäre der gecachte Stand falsch, während die direkte Abfrage immer den aktuellen Bestand zeigt. Das Trennen per Präfix wäre richtig gedacht, aber der Nutzen rechtfertigt die zusätzliche Fehlerquelle nicht
* Zur Reihenfolge: gültiger Zwischenspeicher → abgelaufener Zwischenspeicher (Notreserve) → örtlicher Bestand → Meldung. Der Zwischenspeicher geht vor, weil er die letzte echte Antwort der Schnittstelle enthält; der örtliche Bestand ist ein Spiegel und kann Lücken haben
* Fix: Zwei Stellen lasen die erste Zeile eines Ergebnisses über row(), ohne next() aufzurufen. Contao lädt sie zwar von selbst nach, aber auf solche Feinheiten soll sich der Code nicht verlassen
* Verifiziert mit 48 Tests gegen echtes MySQL (alle zwölf Funktionen inhaltlich, Reihenfolge der Auslieferungsstufen, Hinweistext mit Stand, Statistikzählung, Zugriffs-Log samt Semikolon im Browserkennzeichen) sowie den Laufzeitmessungen oben. Dabei gefunden und behoben: In der Verbandsrangliste standen die Werte für Geschlechts- und Altersfilter an der falschen Platzhalterstelle, weil das Vergleichsdatum der Mitgliedschaftsprüfung vorangestellt statt angehängt wurde — der Altersfilter wirkte dadurch nicht

## Version 1.7.0 (2026-07-29)

* **Add: Live-Abruf abschaltbar** (System → Einstellungen → Wertungsportal, „Live-Abruf abschalten"). Es wird dann keine Verbindung zur Schnittstelle mehr aufgebaut; ausgeliefert wird nur noch, was im Zwischenspeicher liegt — **und zwar ohne Rücksicht auf dessen Ablaufzeit**, sonst stünde nach kurzer Zeit überhaupt nichts mehr zur Verfügung. Über der Ausgabe steht: „Der Abruf von Live-Daten ist z.Z. nicht möglich. Angezeigt werden zwischengespeicherte Daten vom TT.MM.JJJJ HH:MM Uhr." Liegt zu einer Abfrage gar nichts im Zwischenspeicher, erscheint die Meldung allein. Gedacht für angekündigte Wartungen von nu
* **Add: Wartezeit der Schnittstelle einstellbar** (5 bis 60 Sekunden, Voreinstellung 30). Kommt in dieser Zeit keine Antwort, wird der Abruf abgebrochen und wie oben auf den Zwischenspeicher zurückgegriffen — ebenfalls ohne Rücksicht auf die Ablaufzeit. Zusätzlich gilt die Wartezeit jetzt auch für den Verbindungsaufbau (bisher hing der Seitenaufbau an einem nicht erreichbaren Server erheblich länger fest, und der Token-Abruf hatte eine eigene, fest verdrahtete Grenze von 15 Sekunden)
* **Notfrist gegen wiederholtes Warten:** Nach einem gescheiterten Abruf gilt der ausgelieferte Eintrag für 5 Minuten wieder als gültig. Ohne das liefe JEDER Seitenaufruf erneut in die volle Wartezeit — bei 30 Sekunden wäre die Website unbenutzbar, solange die Schnittstelle klemmt. Nach Ablauf der Frist wird die Schnittstelle wieder versucht. Der ursprüngliche Speicherzeitpunkt wandert dabei mit, der Hinweis nennt also weiterhin das echte Alter der Daten und nicht den Zeitpunkt der Notfrist
* **Wichtig zur Abgrenzung:** Nur Abrufe ganz ohne Antwort (Zeitüberschreitung, gescheiterte Verbindung, fehlgeschlagene Namensauflösung) lösen den Notbetrieb aus. Eine inhaltliche Fehlermeldung der Schnittstelle bleibt unberührt — ein „Person not found" ist eine gültige Antwort und darf keine veralteten Daten wecken
* Change: Der Zwischenspeicher wird beim Lesen nicht mehr von abgelaufenen Einträgen bereinigt — genau die sind die Notreserve. Nötig war das Aufräumen ohnehin nicht mehr (die Ablaufprüfung sitzt seit Helper-Bundle 1.8.8 im Lesezugriff selbst), und je Schlüssel liegt genau ein Eintrag in genau einer Datei; der Zwischenspeicher wächst dadurch nicht an. Aufgeräumt wird weiterhin über „Cache leeren" im Backend. Nebeneffekt: ein Schreibvorgang weniger je Seitenaufruf
* Change: Die Fehlerausgabe der Module läuft über eine gemeinsame Stelle (Helper::apiFehler). Bei nicht verfügbarer Schnittstelle erscheint dort die schlichte Meldung statt „Die Wertungsportal-API meldet einen Fehler (HTTP-Code 0): cURL-Fehler …"
* Fix: Zwei Stellen brachen bei einer Fehlerantwort ohne body mit PHP-Warnungen ab (API::Verbandsliste lief mit foreach auf null, die Turniersuche griff ungeprüft auf body.data zu). Vorher fiel das kaum auf, mit abschaltbarer Schnittstelle wäre es der Regelfall geworden
* Setzt Helper-Bundle **1.8.10** voraus (Cache::isCached/retrieve mit Schalter für abgelaufene Einträge, Cache::getStoreTime) — muss mit hochgeladen werden
* Verifiziert mit 40 Tests: Normalbetrieb unverändert; abgeschaltete Schnittstelle liefert abgelaufene Daten samt Hinweis und verbindet sich nachweislich nicht; Timeout-Fall mit Notfrist, erhaltenem Datenalter und erneutem Versuch nach Fristablauf; Fehlerantwort ohne jede Reserve; 404 weckt keine Notdaten; die eingestellte Wartezeit mit echtem cURL-Aufruf gemessen (5,0 s und 10,0 s bei einer nicht erreichbaren Adresse)

## Version 1.6.0 (2026-07-29)

* **Add: Suche unabhängig von der Umlautschreibweise.** Eine Turniersuche nach „büchenbach" fand bisher nichts, wenn das Turnier bei nu als „Buechenbach" geführt wird. Dafür haben die Tabellen jetzt Aliasfelder (tl_wertungsportal_persons: firstnameAlias/lastnameAlias, tl_wertungsportal_clubs: clubNameAlias, tl_wertungsportal_tournaments: labelAlias). Sie enthalten den Namen kleingeschrieben und ohne Umlaute, erzeugt vom Slug-Generator mit deutschem Sprachraum — der schreibt ü als ue und ß als ss, also genauso, wie es von Hand geschrieben wird. Suchbegriff und gespeicherter Name laufen beide durch dieselbe Umwandlung, damit ist die Schreibweise gleichgültig: „büchenbach"/„buechenbach", „Königsspringer"/„Koenigsspringer", „Groß-Gerau"/„Gross-Gerau" finden sich gegenseitig. Akzente werden mit abgelegt („Café" findet „Cafe"). Umgestellt sind Spielersuche, Vereinssuche und Turniersuche
* **Add: Lokale Turniersuche als Fallback.** Liefert die Schnittstelle keinen Treffer, wird in tl_wertungsportal_tournaments weitergesucht — mit Zeitraum- und Verbandsfilter wie an der Schnittstelle. Das hilft doppelt: bei abweichender Umlautschreibweise und bei Suchbegriffen mitten in der Bezeichnung (die label-Abfrage von nu vergleicht nur den Anfang, ein bekannter Mangel der Schnittstelle). Über der Trefferliste steht ein Hinweis, denn die lokale Tabelle spiegelt nur Turniere, die über frühere Abfragen bereits erfasst wurden, und ist damit nicht vollständig
* **ACHTUNG contao:migrate nötig** (vier neue Spalten und zwei Indizes). Die mitgelieferte Migration füllt anschließend die Aliase des vorhandenen Bestands — sie arbeitet blockweise mit 20 Sekunden Zeitbudget und meldet, wenn sie nicht fertig wurde; dann bringt ein weiterer Aufruf von contao:migrate den Rest. Gemessen: 95.000 Personen (zwei Aliasfelder) in 14,9 Sekunden. Ohne diesen Lauf findet die lokale Suche die Altbestände nicht
* Die Aliase werden ab sofort auf allen Schreibwegen mitgeführt (API-Sync, CSV-Importe, Einzel-Upserts) und beim nächsten Abgleich auch für Datensätze nachgezogen, deren Alias noch fehlt. Neu erzeugt wird ein Alias nur bei geändertem Namen — der Abgleich unveränderter Datensätze bleibt dadurch unbelastet (gemessen: 800 unveränderte Vereine in 2,1 ms; die Alias-Erzeugung allein würde 29 ms kosten)
* Die Spielersuche nutzt einen eigenen Index (published, lastnameAlias, firstnameAlias); der Klarnamen-Index bleibt für Backend-Liste und -Sortierung erhalten. Nachgemessen an 95.000 Personen: Index greift (range scan, kein Nachsortieren), 2,2 ms bei einem häufigen Namen, 0,1 ms ohne Treffer — der Stand von Version 1.1.1 bleibt damit erhalten
* Fix: Der Hinweis über der Trefferliste einer Ersatzsuche („Das Wertungsportal lieferte keine Treffer …") war seit Einführung der lokalen Spielersuche in 1.0.7 überhaupt nicht gestaltet und ging als gewöhnlicher Absatz unter. Er ist jetzt als abgesetzter Hinweis erkennbar
* Verifiziert mit 74 Tests gegen echtes MySQL (54 zur Suche und Alias-Pflege, 20 zur Migration): Umwandlung von Umlauten, ß, Akzenten und Sonderfällen; Spielersuche über beide Schreibweisen inkl. Ausschluss Abgemeldeter; Turniersuche mit Zeitraum, Verbandsfilter, Sortierung und Veröffentlichungsstatus; Vereins- und Verbandssuche; Alias-Pflege beim Anlegen, Ändern und Nachziehen; Idempotenz der Syncs; Migration mit fehlenden Spalten, Wiederholung, blockweisem Arbeiten und Volllauf über 95.000 Personen

## Version 1.5.0 (2026-07-29)

* **Add: Hinweis auf zwischengespeicherte Daten.** Wurde eine Ausgabe ganz oder teilweise aus dem Cache bedient, steht über der Seite eine dezente Zeile: „Diese Daten stammen aus dem Zwischenspeicher und werden am TT.MM.JJJJ HH:MM Uhr erneuert." Kamen mehrere Antworten aus dem Cache, nennt der Hinweis den frühesten Ablauf — also den Zeitpunkt, ab dem die Seite wieder frische Daten zeigt. Bei frisch geholten Daten erscheint nichts. Eingebaut in alle acht Frontend-Ausgaben (Spieler, Karteikarte, Verein, Verband, Verbandsliste, Turniersuche, Turnierauswertung, Turnierergebnisse, Spielberichtsbogen)
* Dafür nötig: Das Helper-Bundle liefert als **1.8.9** die neue Methode Cache::getExpiration() — bisher ließ sich der Ablaufzeitpunkt eines Cache-Eintrags gar nicht auslesen (retrieve($key, true) gibt den Speicherzeitpunkt durch unserialize() zurück, obwohl er als blanke Zahl abgelegt ist, und liefert deshalb immer false). Die composer.json fordert jetzt ^1.8.9
* **Fix: Die fehlenden Verbände werden jetzt sofort ergänzt**, direkt nach der Antwort von /dwz/dwzliste/clubs und noch VOR dem Abgleich mit der Datenbank. Bisher lief die Ergänzung erst danach in der Aufbereitung für die Anzeige — die 14 Verbände, die nu auf oberster Ebene nicht mitliefert, fehlten deshalb im lokalen Datenbestand und im Cache; sie standen nur in der gerade gerenderten Seite. Damit sind sie ab sofort überall verfügbar: im Cache, in tl_wertungsportal_clubs und im Frontend. Bei der Abfrage eines einzelnen Verbands wird nur dieser eine ergänzt statt aller 14
* Fix: Das Diagramm der Abrufstatistik konnte sich neben die Legende schieben statt darunter zu bleiben. Ursache: Ein SVG ist von Haus aus ein Zeilenelement (display:inline) und ordnet sich damit wie Text neben umflossenen Inhalt ein. Das Diagramm ist jetzt ein Blockelement, Legende und Scrollbereich beginnen ausdrücklich unterhalb des Vorherigen, und die Mindestbreite des Diagramms richtet sich nach seiner tatsächlichen Breite statt pauschal 640 px zu fordern
* Verifiziert mit 14 Tests (Hinweistext mit und ohne Ablaufzeit, frühester Ablauf bei mehreren Cache-Treffern, kein Hinweis bei frischen Daten; Verbandsergänzung vollständig, gefiltert, ohne Dubletten und bei Fehlerantworten)

## Version 1.4.2 (2026-07-29)

* Change: Beschreibung, Keywords und Homepage in der composer.json ergänzt, damit Packagist das Paket verständlich darstellt und über die Suche auffindbar macht

## Version 1.4.1 (2026-07-28)

* Fix: Die Tabelle der Abrufstatistik ließ sich nicht waagerecht scrollen — sie stand als einzige außerhalb des Scrollbereichs, den das Diagramm schon hatte. Im schmalen Backend-Fenster quetschte sie sich stattdessen zusammen und die langen Schnittstellenpfade brachen unleserlich um. Sie liegt jetzt im selben Scrollbereich, hat eine Mindestbreite und lässt die Pfade nicht mehr umbrechen; die Seite selbst scrollt dabei nicht mit
* Fix: Auch das Diagramm staucht sich nicht mehr auf schmalen Fenstern, sondern scrollt ab einer Mindestbreite in seinem Bereich
* Verifiziert bei 753 px Fensterbreite: Tabelle scrollt in ihrem Bereich (Inhalt 760 px, Bereich 679 px), die Seite bleibt ohne waagerechte Scrollleiste

## Version 1.4.0 (2026-07-28)

* **Add: Statistik mit verschiebbarem Zeitraum.** Neben 30 Tagen bis 1 Jahr gibt es jetzt auch **1 Tag** und **1 Woche**, und der Zeitraum lässt sich mit „◀ zurück" und „vor ▶" um jeweils seine eigene Länge verschieben (bei 1 Tag also tageweise, bei 1 Woche wochenweise, bei 30 Tagen um 30 Tage). „bis heute" springt zurück auf den aktuellen Rand; über das heutige Datum hinaus geht es nicht — dort ist die Schaltfläche gesperrt statt ins Leere zu führen
* Add: Drittes Diagrammraster **nach Tag** (neben Woche und Monat) mit durchgehender Achse — Tage ohne Abrufe erscheinen als Lücke statt zu fehlen. Ohne ausdrückliche Wahl passt sich das Raster der Länge des Zeitraums an (bis 31 Tage: Tag, bis 180: Woche, darüber: Monat)
* Fix: Die Sortierpfeile rutschten bei schmalen Spalten mit zweizeiliger Überschrift („Mgl-/Nr.", „Letzte/Ausw.") nach unten bis in die erste Datenzeile. Ursache: Das Zeichen war zwar absolut positioniert, aber ohne senkrechte Verankerung — es saß dadurch auf der Grundlinie der letzten Kopfzeile. Jetzt ist es mittig in der Kopfzelle verankert; das gilt für die Pfeile beim Überfahren wie für den dauerhaften Pfeil der aktiven Spalte
* Fix: Der Fokusrahmen der Suchfelder erschien als hellblauer Schein um das Feld herum (nach außen versetzter Fokusring). Er ist durch einen sauber anliegenden Rahmen in der Akzentfarbe ersetzt — gleich gut erkennbar, ohne Leuchtrand. Kontrollkästchen und Radios behalten ihren nativen Ring, weil ein Rahmen an so kleinen Bedienelementen kaum sichtbar wäre
* Change: Hilfetext der Spielersuche nennt „mann" statt „müll" als Beispiel für den Namensanfang
* Verifiziert: Navigation mit 20 Tests (Zeitraumgrenzen, Sprünge vorwärts/rückwärts, Sperre gegen Zukunftsdaten, automatische Rasterwahl, ungültige Eingaben); Pfeilposition und Fokusrahmen im Browser an schmalen, zweizeiligen Spaltenköpfen bzw. am echten Eingabefeld geprüft

## Version 1.3.0 (2026-07-28)

* **Add: Abrufstatistik der Schnittstelle.** Jeder Zugriff auf eine Wertungsportal-Funktion wird gezählt — getrennt danach, ob die Antwort aus dem lokalen Cache kam oder tatsächlich bei der Schnittstelle geholt wurde. Gespeichert wird tagesweise in der neuen Tabelle tl_wertungsportal_stats: je Tag, Funktion und Quelle genau ein Datensatz mit Zähler (INSERT … ON DUPLICATE KEY UPDATE). Die Zählung kostet damit eine Abfrage je Seitenaufruf und geht auch bei gleichzeitigen Zugriffen nicht verloren
* Add: Alle zwölf internen Funktionen sind den Pfaden der Schnittstelle zugeordnet (API::endpunkte) — /dwz/tournaments mit seinen fünf Funktionen, /dwz/persons/{id}/history sowie /dwz/dwzliste/persons, /persons/{id} und /clubs
* **Add: Backend-Modul „Statistik"** (Wertungsportal → Statistik): Übersichtstabelle aller Funktionen mit Abrufen von der API, aus dem Cache, Gesamtzahl und Cache-Anteil je Funktion sowie Gesamtsumme. Der Verlauf wird als gestapeltes Balkendiagramm dargestellt (unten Cache, oben API), umschaltbar **nach Woche oder nach Monat**, Zeitraum wählbar von 30 Tagen bis einem Jahr. Ein Klick auf eine Funktion zeigt deren Verlauf allein
* Die Diagramme sind serverseitig erzeugtes SVG (wie das DWZ-Diagramm der Karteikarte) — ohne zusätzliche Javascript-Bibliothek, mit Werten in den Tooltips und mitwachsender Balkenbreite
* Die Zählung ist gegen Fehler abgeschottet: Fehlt die Tabelle (vor contao:migrate), läuft das Frontend unverändert weiter, statt mit einem Fehler abzubrechen
* ACHTUNG: contao:migrate bzw. Install-Tool nötig (neue Tabelle tl_wertungsportal_stats) sowie contao:assets:install (Backend-CSS)
* Verifiziert mit echtem MySQL (19 Tests): Hochzählen statt Neuanlage, getrennte Zählung von Cache und API, Auswertung je Funktion und Zeitraum, Bündelung nach ISO-Kalenderwoche und Monat, Filter je Funktion, Verhalten bei fehlender Tabelle. Backend-Ansicht mit dem echten Template gerendert und geprüft (Diagramm, Tabelle mit Summenzeile, Funktionsauswahl, Hinweis bei noch leerer Statistik)

## Version 1.2.1 (2026-07-28)

* **Fix: Die Sortierpfeile blieben unsichtbar — jetzt behoben.** Ursache: Es sind zwei tablesorter-Fassungen im Spiel. Die im Bundle mitgelieferte schreibt die Klassen `header` / `headerSortUp` / `headerSortDown` an die Spaltenköpfe, Contaos eigene Fassung unter assets/tablesorter (2.31) dagegen `tablesorter-header` / `tablesorter-headerAsc` / `tablesorter-headerDesc`. Im Livesystem gewinnt Contaos Fassung, weil das Layout sie zuletzt lädt — das CSS sprach aber nur den Namenssatz der mitgelieferten Fassung an und fasste damit ins Leere. Alle Regeln nennen jetzt BEIDE Namenssätze
* Fix: Hintergrundbilder der Fremdfassung werden unterdrückt, damit deren (im Bundle nicht vorhandene) Pfeilgrafiken nicht neben den Zeichen stehen; als nicht sortierbar markierte Spalten (sorter-false) bekommen keinen Pfeil
* Verifiziert mit Contaos echter tablesorter-Fassung 2.31 (vom Livesystem geladen, in der Live-Reihenfolge eingebunden): Ruhezustand ⇅ unsichtbar, nach dem ersten Klick ▲, nach dem zweiten ▼, Symbolspalte ohne Pfeil, Sortierung numerisch korrekt

## Version 1.2.0 (2026-07-27)

* **Add: Die Suchformulare stehen jetzt auch auf den Ergebnisseiten** — bei der Turniersuche und der Verbandsrangliste. Sie sind dort mit den Werten der laufenden Suche vorbelegt und eingeklappt („Suche ändern" bzw. „Liste anpassen"), damit die Trefferliste im Vordergrund bleibt. Eine Suche lässt sich damit direkt nachjustieren, statt über den Zurück-Weg zur Suchseite
* **Change: Turnier- und Verbandsformular neu aufgebaut.** Bisher waren es Contao-Backend-Bausteine im Frontend (`fieldset.tl_box` mit Legende, feste w50-Spalten, gestapelte Monat/Jahr-Felder, Layout per Tabelle). Jetzt: ein mitwachsendes Raster ohne Breakpoints, Monat und Jahr nebeneinander, sprechende Beschriftungen, Platzhaltertexte und Hilfetexte unter dem jeweiligen Feld. Die Eingabefelder behalten ihre tl_*-Klassen, damit die Grundstile des Themes weiter greifen
* Change: Beide Formulare liegen als eigene Templates vor (wertungsportal_form_turniersuche, wertungsportal_form_verbandsliste) und werden von Such- und Ergebnisseite gemeinsam genutzt — eine Änderung wirkt an beiden Stellen
* Change: Auch die Spieler- und Vereinssuche nutzen dieses Formularvokabular, damit alle vier Suchen gleich aussehen und sich gleich bedienen
* Fix: Die Monatsauswahl lieferte für „von" und „bis" dieselbe Liste mit demselben ausgewählten Monat — beide sind jetzt getrennt und unabhängig vorbelegbar
* Fix: Die Altersfelder der Verbandsrangliste sind Zahlenfelder (min/max 0–140) statt Textfelder; die Auswahl „Letzte x Monate" wird aus einer Liste erzeugt statt zwölf Optionen von Hand zu pflegen
* Add: Sichtbarer Tastaturfokus auf allen Bedienelementen der Formulare, Radiogruppen und Kontrollkästchen mit klickbarer Beschriftung, Fehlermeldungen mit `role="alert"`, Beschriftung der zusammengesetzten Felder über `aria-labelledby`
* Verifiziert: Beide Formulare in allen fünf Zuständen im Browser gerendert (offen mit Standardwerten, eingeklappt mit Vorbelegung, mit Fehlermeldung) — Vorbelegung stimmt in jedem Feld; kein waagerechtes Scrollen auf 375 px, Felder stapeln sich sauber, Monat und Jahr bleiben nebeneinander
* Hinweis: Die Ergebnisseite der Turniersuche lädt für die Verbandsauswahl zusätzlich die Verbandsliste. Sie kommt aus dem Cache — wer die Cachezeit für Verbände (Einstellungen, seit 1.1.0) auf eine Woche stellt, hat dadurch praktisch keine Zusatzlast

## Version 1.1.3 (2026-07-27)

* **Fix: Die Sortierung funktionierte im Livesystem auf keiner Seite** — auch dort nicht, wo sie eingebaut war. Ursache (am Livesystem nachgewiesen): Contao bringt unter assets/tablesorter eine eigene tablesorter-Fassung mit, die das Layout NACH den Bundle-Dateien lädt und dabei jQuery.tablesorter samt der beim Laden registrierten Parser überschreibt. Die Initialisierung lief dadurch ins Leere: keine Sortierung, keine Spaltenköpfe, keine Pfeile. Parser-Registrierung und Initialisierung laufen jetzt vollständig in $(document).ready() und sind damit unabhängig von der Ladereihenfolge; scheitert eine Tabelle trotzdem, bleibt die Seite dank Fehlerabfangung bedienbar
* **Fix: Die Trefferliste der Turniersuche hatte keine Sortierung** — die Änderung aus 1.1.2 lag in wertungsportal_sub_turniersuche.html5, das gar nicht gerendert wird: Turnier.php baut die Trefferliste über das eigenständige Template wertungsportal_turniersuche.html5. Die Sortierung sitzt jetzt dort; die ungenutzte Datei ist als solche gekennzeichnet
* Fix: Die aktive Sortierspalte zeigte ihren Richtungspfeil nicht an (die Deckkraft blieb auf 0, weil der gleichzeitige Wechsel des Pfeilzeichens die Einblendung störte) — die Regeln nennen jetzt beide Klassen und kommen ohne Überblendung aus
* Add: Spaltensortierung zusätzlich in der Verbandsrangliste (beide Templates) und in den Turnier-Trefferlisten des Turnier-Templates. In der Verbandsrangliste lud die Tabelle die Sortierdateien nie, obwohl sie die Klasse dafür trug
* Fix: Die Verbandsrangliste ordnete ihre Sortierparser über feste Spaltennummern zu (2, 3, 5). Die stimmten nur, solange Geschlecht und Status eingeblendet waren — sonst landeten sie auf den falschen Spalten. Die Zuordnung läuft jetzt über data-sort am Spaltenkopf und ist damit unabhängig von ein- oder ausgeblendeten Spalten
* Fix: Das Turnier-Template lud die Sortierdateien aus dem alten contao-dewis-bundle (Pfad existiert hier nicht); jetzt aus dem eigenen Bundle
* Add: Zwei weitere Sortierparser — „woche" für die letzte Auswertung als Kalenderwoche (WW/JJJJ, sortiert chronologisch statt alphabetisch) und „titel" für FIDE-Titel nach Wertigkeit (GM vor IM vor WGM …)
* **Fix: Die Platzhalter-Mitgliedsnummer 0000 erschien weiterhin in Listen** (Vereinsliste, Spielersuche, Verbandsrangliste), weil der Filter aus 1.1.2 nur in der Karteikarte und beim Sync griff. Er läuft jetzt zentral in API::autoQuery und erfasst alle Antwortformen der Schnittstelle (Listen, Karteikarte, Turnierhistorie) — auch bei Cache-Treffern, also ohne Warten auf den Cachelauf. Damit zeigen auch Mitgliedsnummer und Status in den Listen die echte Mitgliedschaft
* Verifiziert: Sortierung im Browser gegen eine nachgeladene fremde tablesorter-Fassung (Kalenderwoche 05/2024 → 16/2026 chronologisch, FIDE-Titel WIM → GM nach Wertigkeit, DWZ 986 → 2050 numerisch, Datum chronologisch, Ergebnis mit Dezimalkomma, Pfeile ⇅ im Ruhezustand unsichtbar und ▲/▼ auf der aktiven Spalte); Mitgliedsnummer-Filter mit 10 Tests über alle Antwortformen

## Version 1.1.2 (2026-07-27)

* Change: Kreuztabelle — die Schwarz-Partien (kreuz-s) stehen jetzt auf hellgrauem Grund (#D9D9D9) mit schwarzer Schrift statt dunkelgrau mit heller Schrift; der Rahmen um die Ergebnisse ist bei beiden Farben entfernt
* Add: Sortierbare Spalten zeigen ihre Sortierbarkeit an — neben dem Spaltentitel erscheint ein Doppelpfeil, sobald die Maus über der Kopfzeile steht (die Spalte unter dem Zeiger deutlicher, die übrigen dezent). Die aktive Sortierspalte zeigt dauerhaft ihre Richtung als ▲ bzw. ▼. Auf Touchgeräten, die kein Hover kennen, sind die Pfeile dauerhaft sichtbar
* Fix: Die Sortierpfeile waren bisher überhaupt nicht zu sehen — die mitgelieferte tablesorter-CSS lud drei GIF-Dateien, die es in diesem Bundle nie gab. Sie sind jetzt durch reine CSS-Zeichen ersetzt (keine Bilddateien mehr nötig)
* Add: Spaltensortierung auch in der Trefferliste der Turniersuche und in der Turnierauswertung
* Fix: Die Turniersuche band tablesorter aus dem alten contao-dewis-bundle ein (Pfad existiert hier nicht) und wies den Datumsparser der Spalte „Region" statt „Turnierende" zu — die Sortierung lief dort nie
* Change: Die Sortierung ist in eine gemeinsame Datei ausgelagert (public/js/wertungsportal_sort.js) und wird pro Spalte über `data-sort` im Spaltenkopf gesteuert. Neue Parser: „zahl" liest die erste Zahl aus dem Zellentext (DWZ „1234 - 45" → 1234, Ergebnis „3,5 / 7" → 3,5, Differenz „+12"/„−8") und „datum" für TT.MM.JJJJ. Bisher wurden DWZ-, Ergebnis- und Datumsspalten als Text sortiert und damit falsch geordnet
* Fix: Mitgliedschaften mit der Platzhalter-Nummer 0000 werden ausgeblendet, wenn für denselben Verein die endgültige Mitgliedsnummer vorliegt (nu liefert die beim Anlegen vergebene 0000 weiterhin mit, der Spieler erschien dadurch doppelt beim selben Verein). Der Filter greift in der Karteikarte und beim Sync; ist die 0 die einzige Angabe, bleibt der Eintrag erhalten. Die Operation „Dubletten bereinigen" räumt solche Platzhalter zusätzlich aus dem vorhandenen Bestand
* Verifiziert: Sortierung im Browser mit echten Assets (DWZ 986→2050 numerisch statt alphabetisch, Ergebnis 0,5→6 mit Dezimalkomma, Datum chronologisch, Namen mit Umlauten korrekt, Umkehrung beim zweiten Klick, Symbolspalte bleibt gesperrt); 0000-Filter mit 6 Tests gegen echtes MySQL

## Version 1.1.1 (2026-07-27)

* **Fix (Performance): Die lokale Fallback-Spielersuche aus 1.0.7 brauchte am Livesystem 5,4 Sekunden je erfolgloser Suche.** Am Symfony-Profiler gemessen: Eine Suche ohne Treffer („Zaunbrecher") kostete 11,3 s Gesamtzeit, davon 7,1 s Datenbankzeit — praktisch vollständig in dieser einen Abfrage. Ursache waren zwei nicht indexierbare Konstruktionen: die Suche mit führendem Platzhalter (LIKE '%x%') und die Prüfung der laufenden Mitgliedschaft als EXISTS-Unterabfrage in derselben WHERE-Klausel, die für JEDE Zeile der Personentabelle ausgewertet wurde. Behoben durch: Suche am Namensanfang (LIKE 'x%'), Mitgliedschaftsprüfung nachgelagert nur für die Kandidaten, Laden nur der benötigten Spalten (statt aller ~40) und einen Mindestumfang von drei Zeichen im Nachnamen. Die Suche greift jetzt nur noch, wenn ein Nachname eingegeben wurde — eine Suche allein über den Vornamen könnte den Namensindex nicht nutzen
* **Add: Namensindizes für tl_wertungsportal_persons** — zusammengesetzt über published, lastname, firstname sowie einzeln über lastname. Ohne sie wählt MySQL den unselektiven published-Index (zwei mögliche Werte) und sortiert das Ergebnis nach; mit ihnen entfällt die Sortierung komplett. ACHTUNG: contao:migrate bzw. Install-Tool nötig!
* Messung mit 95.000 Testpersonen und 95.000 Mitgliedschaften: Suche „Sch" 298 ms → 6,3 ms, „Müll" 88 ms → 6,1 ms, erfolglose Suche 5405 ms (live) bzw. 190 ms (lokal) → 0,5 ms. Verifiziert mit 13 Tests (Namensanfang-Logik, Ausschluss von Verstorbenen/Abgemeldeten/Blacklist, Platzhalter-Behandlung, Mindestlänge, Laufzeit)
* Change: Der Hinweis über der Trefferliste spricht jetzt von Namen, die mit dem Suchbegriff beginnen (statt „enthält")

## Version 1.1.0 (2026-07-27)

* Add: Die Cachezeiten sind in den System-Einstellungen je Funktionsgruppe einstellbar (Spieler, Vereine, Verbände, Turniersuche, Turnierdaten) — Auswahl von „Kein Cache" bis 30 Tage, ohne Auswahl gilt wie bisher 1 Tag. Empfehlung für die Verbändeliste: 1 Woche, weil sich Verbands- und Vereinsstammdaten kaum ändern; „Kein Cache" schaltet den Cache gezielt für einzelne Bereiche ab
* Change: Jeder Cache-Eintrag liegt jetzt in einer eigenen Datei innerhalb eines Verzeichnisses je Funktion. Bisher sammelten sich ALLE Einträge einer Funktion in einer einzigen Datei, die bei jedem Zugriff komplett gelesen, dekodiert und neu geschrieben wurde — bei Vereinslisten mit hunderten Spielern wuchs sie auf viele Megabyte und der Cache wurde langsamer als die Abfrage, die er einsparen sollte
* Change: Die Systemwartung zeigt je Cache-Speicher zusätzlich die eingestellte Cachezeit an; „Wertungsportal-Cache leeren" räumt das neue Verzeichnis-Layout und den Altbestand auf
* Change: Der Sync der Karteikarten-Historie läuft als Bulk (eine Bestandsabfrage plus Batch-INSERT statt je Turnier eine Einzelabfrage) — bei einem Spieler mit 72 Turnieren waren das bisher rund 150 zusätzliche Abfragen pro Karteikartenaufruf; die Turniere selbst werden gesammelt über syncList abgeglichen
* Change: Auch der Sync der DWZ-Hochstufungen läuft als Bulk statt mit einer Abfrage je Hochstufung
* Fix: Die Syncs von Turnierhistorie und Hochstufungen löschen keine Einträge mehr, die die Schnittstelle gerade nicht meldet. Die Turnierhistorie wird auch aus Turnierauswertung, Partien und Spielberichtsbogen gefüllt — die Löschung räumte diese Einträge beim nächsten Karteikartenaufruf wieder ab (gleiche Fehlerklasse wie der Mitgliedschafts-Bugfix in 1.0.9). Das Frontend zeigt ohnehin die API-Antwort, die lokale Tabelle ist Spiegel und Archiv
* Setzt contao-helper-bundle 1.8.8 voraus (Cache-Korrekturen: Ablaufprüfung, Zwischenspeicher, Locking)

## Version 1.0.9 (2026-07-27)

* **Fix (Datenverlust): Der API-Abgleich löscht keine Mitgliedschaften mehr.** `syncForPersons()` entfernte bisher alle Mitgliedschaften, die die nu-Schnittstelle nicht meldet. Die Schnittstelle liefert aber ausschließlich die AKTUELLEN Mitgliedschaften einer Person, keine Historie — dadurch hat jeder Frontend-Seitenaufruf (Spielersuche, Karteikarte, Vereins- und Verbandsliste) die per CSV importierten früheren Mitgliedschaften nach und nach abgeräumt. Es wird jetzt nur noch angelegt und aktualisiert, nie gelöscht
* **Fix (Datenverlust): Der Import behält alle Zeiträume.** Schlüssel einer Mitgliedschaft war bisher nur VKZ + Mitgliedsnummer. Da eine Person im selben Verein unter derselben Mitgliedsnummer mehrere aufeinander folgende Mitgliedschaften hat (aktiv/passiv, verschiedene Zeiträume), fielen beim Import der Spielgenehmigungen alle Zeiträume auf einen einzigen Datensatz zusammen. Der Schlüssel umfasst jetzt zusätzlich Lizenzstatus und Beginn der Spielgenehmigung — sowohl beim Einsammeln der CSV-Zeilen als auch beim Abgleich mit dem Bestand
* **Fix (Datenverfälschung): Der API-Abgleich trifft nur noch laufende Mitgliedschaften** (Ende leer). Bisher wurde per Person + VKZ gematcht, wodurch bei mehreren Mitgliedschaften im selben Verein willkürlich ein historischer Datensatz erwischt und mit den aktuellen Werten überschrieben wurde (falsche Zeiträume, falscher Status, Dubletten)
* Fix: Leere Importwerte überschreiben keine gefüllten Bestandsfelder mehr (Personen, Mitgliedschaften, Vereine) — ein Teilimport wie die Spielgenehmigungs-Exporte darf vorhandene Daten (Adresse, Geburtsdatum, Datenschutzfelder, FIDE-ID) nicht leeren
* Add: Globale Operation „Dubletten bereinigen" unter Personen (key=entdoppeln): führt doppelte Mitgliedschaften zusammen (gleiche Person, VKZ, Mitgliedsnummer, Lizenzstatus und Zeitraum), behält den ältesten Datensatz und ergänzt darin fehlende Feldwerte aus den Dubletten. Die Mitgliedsnummer wird dabei normalisiert, sodass auch unterschiedlich formatierte Nummern (1083 / 01083) als derselbe Eintrag erkannt werden. Für Bestände nötig, die vor diesem Bugfix entstanden sind
* Change: Die Mitgliedschaftsliste im Backend zeigt VKZ mit Mitgliedsnummer (z. B. 30052-1083) statt nur der VKZ
* Verifiziert mit echtem MySQL an der vollständigen Mitgliedschaftshistorie eines Spielers (11 Genehmigungen, davon 6 im selben Verein unter derselben Mitgliedsnummer): Import legt alle 11 Zeiträume getrennt an, Wiederholung ist idempotent (0 Schreibvorgänge), sechs aufeinander folgende API-Syncs lassen den Bestand unverändert, historische Einträge bleiben unverfälscht, ein vom API-Sync angelegter Datensatz ohne Zeitraum wird vom Import vervollständigt statt verdoppelt, und die Bereinigung entfernt echte Dubletten ohne die verschiedenen Zeiträume zusammenzuwerfen (16 Prüfungen)

## Version 1.0.8 (2026-07-27)

* Fix: Die lokale Teilstring-Suche (Fallback der Spielersuche aus 1.0.7) schließt abgemeldete Spieler aus: Gefunden werden nur Personen mit mindestens einer laufenden Mitgliedschaft (spielgenehmigungBis leer oder in der Zukunft, Datumsumstellung TT.MM.JJJJ → JJJJMMTT wie in der Mitgliedschafts-Sortierung); Verstorbene und Blacklist-Personen sind wie in der Bestenliste bereits im SQL ausgeschlossen. Auch die Vereinsanzeige der Treffer ignoriert beendete Mitgliedschaften, damit bei Vereinswechslern nicht der alte Verein erscheint. Hinweis: Passive Spieler sind gemeldet und werden weiterhin gefunden
* Add: Der Personen-Import speichert beim Abschluss den Datenstand des Mitgliederportal-Abgleichs (Exportdatum aus dem Dateinamen, Fallback Importzeitpunkt) in der Einstellung wertungsportal_personimport
* Add: Systemmeldung auf der Backend-Startseite (Hook getSystemMessages, neue Klasse Classes/Systemmeldungen.php): Liegt der letzte Personen-Import mehr als 31 Tage zurück oder wurde noch keiner erfasst, erscheint eine Warnung mit Datenstand und Handlungsaufforderung — die nu-Schnittstelle liefert abgemeldete Spieler nicht mehr, Abmeldungen kommen also nur über die monatlichen CSV-Importe an

## Version 1.0.7 (2026-07-27)

* Fix: Spielersuche mit Leerzeichen im Nachnamen ("von Dissen") lieferte keine Treffer — der Suchbegriff wurde als Ganzes geslugt, aus dem Leerzeichen wurde dabei ein Bindestrich ("von-dissen") und der ging so an die API. Neue Helper::slugName() sluggt jeden Namensteil einzeln und erhält die Leerzeichen; der Cachekey ersetzt Leerzeichen durch Unterstriche, damit er nicht mit echten Bindestrich-Namen kollidiert
* Add: Lokale Teilstring-Suche als Fallback der Spielersuche (neue Helper::lokaleSpielersuche()): Liefert die Wertungsportal-API keine Treffer (sie vergleicht nur komplette Felder — "müll" findet dort kein "müller"), wird tl_wertungsportal_persons per LIKE über Nachname/Vorname durchsucht (Rohstring mit Umlauten, maximal 300 Treffer, published-Personen, Blacklist-Filterung greift wie bisher in der Aufbereitung). Die Treffer durchlaufen als API-förmiges Ergebnis die normale Aufbereitung inklusive FIDE-Anreicherung und Kartei-Verlinkung; bei Eingaben ohne Komma wird zusätzlich die gedrehte Deutung "Vorname Nachname" probiert. Oberhalb der Trefferliste erscheint ein Hinweis, dass DWZ/letzte Auswertung aus dem lokalen Datenbestand stammen
* Fix: Vereinssuche findet jetzt Vereine mit Umlauten im Namen ("königsspringer", "großauheim"): Der Suchbegriff wurde bisher geslugt (königsspringer → koenigsspringer) und konnte so beim Vergleich mit den echten Vereinsnamen nie treffen; außerdem verglich stripos nicht multibyte-sicher — jetzt bleibt der Suchbegriff roh und der Vergleich läuft über mb_stripos
* Add: Die Spielerlisten der Vereinsseite (Rang-/Alphaliste) sind per Klick auf die Spaltenköpfe sortierbar (nochmaliger Klick dreht die Richtung um) — wie im DeWIS-Vorbild über tablesorter; die Tabelle hatte die Sortier-Klassen schon, es fehlte die Einbindung der Assets
* Change: tablesorter (JS + CSS) liegt jetzt im eigenen Bundle (bundles/contaowertungsportal) statt im alten contao-dewis-bundle — das Karteikarten-Template lädt die Assets aus dem eigenen Bundle, damit ist die letzte Abhängigkeit zum dewis-Bundle im Frontend beseitigt. ACHTUNG: Nach dem Upload contao:assets:install ausführen
* Add: Karteikarte verlinkt in der Zeile "Historie" wieder die alte EloBase-Karteikarte (altdwz) samt Zugangshinweis (Benutzer/Passwort: dwz): neue Einstellungen "Historie EloBase anzeigen" (Checkbox) und "EloBase-URL" (leer = http://altdwz.schachbund.net/db/spieler.html?zps=) im Bereich Wertungsportal; als zps wird VKZ-Mitgliedsnummer der aktiven (sonst ersten) Mitgliedschaft übergeben

## Version 1.0.6 (2026-07-26)

* Change: Verband-Anzeige der Karteikarte wählt jetzt den spezifischsten tatsächlich existierenden übergeordneten Verband über eine Fallback-Kaskade (Beispiel Verein 55223: Kreis 552 → Bezirk 550 → Landesverband 500 → DSB 000). Angezeigt und verlinkt wird der erste lokal (tl_wertungsportal_clubs) vorhandene Verband; existiert keiner, greift der Deutsche Schachbund als harter Fallback (Helper::getVerbandName durch getVerband ersetzt, liefert VKZ + Name). Mit echtem MySQL für alle Kaskadenstufen verifiziert
* Change: Karteikarten-Nummerierung zählt die DWZ-Umstufungen (upgrades) mit — die laufende Nummer bzw. AKT wird über Turniere UND Umstufungen gemeinsam vergeben; der chronologisch neueste Eintrag (egal ob Turnier oder Umstufung) bekommt AKT, die übrigen absteigende Nummern
* Add: Die DWZ-Umstufungen erscheinen jetzt auch im DWZ/Leistungs-Diagramm als DWZ-Punkte (mit ihrer Eintragsnummer im Tooltip). Der Tooltip aller Diagrammpunkte nennt zusätzlich die Nummer des Eintrags. Turniere ohne Auswertung (0 Partien) werden im Diagramm übersprungen
* Change: Diagramm — Jahreszahlen der X-Achse jetzt senkrecht (90° statt 60°)
* Fix: Verbandsranglisten zeigen pro Spieler den Verein, der zum abgefragten Verband gehört (statt der ersten Mitgliedschaft): die Verein-VKZ muss zum VKZ-Präfix des Verbands passen, dessen Länge sich aus der Verbandsebene ergibt (Landesverband X00 → 1 Stelle, Bezirk XY0 → 2, Kreis XYZ → 3, DSB 000 → alle); aktive Mitgliedschaft bevorzugt, Fallback auf die erste
* Fix: Karteikarten-Spielerbild oben bündig mit der Datentabelle (Browser-Default-Margin des figure entfernt)

## Version 1.0.5 (2026-07-26)

* Add: DWZ-Umstufungen (upgrades-Array parallel zu entries in der history-Antwort) werden in die Karteikarten-Turnierliste eingearbeitet — chronologisch zwischen die Turniere einsortiert (Turnier: enddate, Umstufung: referenceDate; bei gleichem Datum gewinnt das Turnier) und farblich hervorgehoben (heller Hintergrund, kursiv). Die Turnier-Nummerierung (AKT/laufende Nummer) bleibt den Turnieren vorbehalten
* Change: Karteikarten-Kopf zeigt hinter "Verband:" (statt "Verbände:") nur noch den direkt übergeordneten Verband — dreistellige VKZ aus den ersten drei Stellen der Vereins-VKZ (kann Landesverband, Bezirk oder Kreis sein), Name lokal aus tl_wertungsportal_clubs, Fallback federationName der API. Die frühere Verbandskette zeigte durch fehlende federation-Verknüpfungen praktisch immer nur "Deutscher Schachbund" (Helper::getVerbandskette durch das schlankere getVerbandName ersetzt)
* Add: Karteikarten-Kopf hat unter dem Namen eine Zeile "Aktuelle DWZ:"
* Fix: Karteikarten-Kopf als Flex-Layout (Bild links, Datentabelle rechts) statt fester rowspan="6"-Bildzelle — die Datenspalten rutschen bei vielen Vereinsmitgliedschaften nicht mehr unter das Spielerbild
* Change: FIDE-Nation-Link auf der Karteikarte korrigiert auf https://ratings.fide.com/rankings.phtml?country=XXX
* Add: In der Turnierergebnis-Kreuztabelle wird die Partiefarbe hervorgehoben — nur das Ergebnis (nicht die ganze Zelle, damit es sich nicht mit den grauen Blindfeldern beisst): Weiß auf weißem Grund fett, Schwarz auf dunklem Grund mit heller Schrift
* Add: Turnierergebnisse werden bei Rundenturnieren (Round-Robin) als Kreuztabelle Spieler × Spieler dargestellt statt in Rundenspalten. Erkennung: gerade Spielerzahl mit Spielerzahl−1 Runden oder ungerade Spielerzahl mit Spielerzahl Runden (Freilos); sonst weiterhin die Rundendarstellung. Die Diagonale ist grau hinterlegt, jede Zelle zeigt das Ergebnis aus Sicht des Zeilenspielers (Round-Robin-Erkennung mit 11 Fällen verifiziert)
* Add: Turnierergebnis-/Kreuztabellen sind horizontal scrollbar mit einer zusätzlichen oberen Scrollleiste (per JS mit der Tabelle synchronisiert), damit breite Tabellen auch ohne Scrollen bis zum Seitenende bedienbar sind
* Fix: Karteikartensperre (wertungsportal_karteisperre_gaeste) wirkte nicht — eine spätere Zuweisung setzte sichtbar wieder auf true und überschrieb die Sperrentscheidung. Für Gäste bleibt die Karteikarte jetzt gesperrt, wenn die Option aktiv ist
* Add: FIDE-Nation auf der Karteikarte ist mit der FIDE-Länderrangliste verlinkt
* Change: Diagramm DWZ/Leistung — Jahreszahlen der X-Achse steiler gedreht (60° statt 45°, fast senkrecht); die Legende sitzt jetzt oberhalb der Skala im freien Rand, damit hohe Leistungswerte sie nicht mehr überschreiben

## Version 1.0.4 (2026-07-24)

* Change: Download-Ordner in public Skripten ergänzt

## Version 1.0.3 (2026-07-21)

* Fix: Spielerbild-Übernahme brach mit SQL-Fehler ab (Unknown column 'nuLigaPersonId'), weil das Feld in tl_dwz_spi nicht mehr existiert (in allen Installationen entfernt). Die gesamte nuLigaPersonId-basierte Zuordnungs- und Neuanlage-Logik wurde entfernt — der Bild-Import legt keine Personen mehr an (die kommen über die CSV-Importe), sondern ordnet die Fotos den vorhandenen Personen dreistufig zu: 1. externe Nummer = DeWIS-Spielernummer (Regelfall, die dewisID wurde von nu als externeNr übernommen); 2. FIDE-ID (falls nu die externeNr auf einen "C"-Präfix mit abweichender Nummer geändert hat — die FIDE-ID ist in beiden Tabellen dieselbe); 3. Nachname + Vorname + Geburtsjahr, aber nur bei beidseitiger Eindeutigkeit (schützt vor Fehlzuordnung bei Namensgleichheit). Wer sich nicht zuordnen lässt, wird ins System-Log geschrieben und auf der Ergebnisseite aufgelistet. Match-Schlüssel jeweils blockweise/indexgestützt geladen. Am Fall Ledyankina verifiziert (externeNr CO3331587 ≠ dewisID 10915863, aber FIDE-ID 55644155 identisch → Bild wird über die FIDE-ID zugeordnet)

## Version 1.0.2 (2026-07-21)

* Add: Vereins-Import aus der Stammdaten-Exportdatei (Vereine__Stammdaten__Adressen__Sportstaetten__JJJJMMTTHHIISS.csv) als globale Operation "CSV-Import" unter Vereine (key=importClubs, Classes/VereineImport.php, Template be_wp_vereineimport): Chunk-Upload + Import in Paketen à 500 Zeilen, Vereine werden per VKZ (VereinNr) angelegt bzw. nur bei Änderungen aktualisiert (WertungsportalClubsModel::importCsvRows), Status "Archiv" wird auf das Löschkennzeichen DELETE_STATE_TRUE abgebildet, Datum/Uhrzeit aus dem Dateinamen wird als tstamp gesetzt. Dafür 48 neue Felder in tl_wertungsportal_clubs (Kurz-/Druckname, Verbands-/Regionsname, Debitoren-/LSB-/Vereinsregisternummer, Gründungsjahr — Jahr ODER vollständiges Datum, Ein-/Austrittsdatum, Zahlungsart, 9 ja/nein-Kennzeichen, Bankverbindung, Adresse/Kontakt, Sportstätten 1-3, Bemerkung) — DB-Update erforderlich. Mapping mit der echten Exportdatei verifiziert (4.497 Zeilen, 4.496 Vereine, 0 übersprungen, Feldlängen geprüft — PLZ-Felder wegen Mehrfach-PLZ auf 32 Zeichen ausgelegt)
* Change: Mitgliedschaftsliste einer Person zeigt jetzt den Genehmigungszeitraum (Von – Bis bzw. "laufend") und sortiert die unbeendeten Mitgliedschaften zuerst, danach nach Mitgliedschaftsende absteigend (SQL-Datumsumstellung TT.MM.JJJJ → JJJJMMTT)
* Add: Import-Dialog weist auf die empfohlene Reihenfolge hin (Spielgenehmigungen: zuerst Abmeldungen, dann Anmeldungen)
* Add: Personen-Import um die Spielgenehmigungen-Exportdateien erweitert (Spielgenehmigungen__Angemeldete_im_Zeitraum__JJJJMMTTHHIISS.csv und Spielgenehmigungen__Abgemeldete_im_Zeitraum__JJJJMMTTHHIISS.csv): Der Dateityp wird automatisch aus dem Dateinamen erkannt (manuelle Auswahl möglich), das Erstellungsdatum aus dem Dateinamen wird wie beim Vereinsmitglieder-Import als tstamp gesetzt. Der Import VERVOLLSTÄNDIGT bestehende Personen (externe Nummer, Name, Geburtsdatum — die übrigen Felder bleiben unangetastet) und legt fehlende Personen neu an (damit sind auch alle derzeit abgemeldeten Spieler enthalten und die externen Nummern für die Spielerbild-Übernahme zuordenbar); je Genehmigung werden Verein, Lizenzstatus, Zeitraum (SpielgenehmigungAb/Bis) und die neuen Antragsfelder in den Mitgliedschaften gespeichert (Match per VKZ + Mitgliedernummer; bei mehreren Anträgen zur selben Nummer gewinnt die letzte Zeile). Neue Felder antragstyp/antragszeitpunkt/antragsteller in tl_wertungsportal_persons_memberships — DB-Update erforderlich (contao:migrate/Install-Tool). Mapping mit den echten Exportdateien verifiziert (950.163 Zeilen, 408.129 eindeutige Personen, 587.531 Genehmigungen, 0 übersprungen)
* Add: Spielerbild-Übernahme behandelt Quellspieler ohne externeNr-Treffer (z. B. abgemeldete Personen, die nicht im Vereinsmitglieder-CSV stehen) jetzt dreistufig: 1. Existiert die Person bereits unter ihrer nuLiga-ID, wird sie zugeordnet (externe Nummer ergänzt) und das Bild übernommen; 2. sonst wird die Person mit den Stammdaten aus tl_dwz_spi neu angelegt (Name, Geschlecht, Geburtsdatum, FIDE-ID, Verstorben-Kennzeichen, Bild); 3. hat tl_dwz_spi keine nuLiga-ID, wird NICHT angelegt (ein späterer CSV-Import würde dieselbe Person über die InterneNr doppelt anlegen) — diese Spieler werden ins Contao-System-Log geschrieben und auf der Ergebnisseite einzeln aufgelistet. nuLiga-IDs aus tl_dwz_spi werden normalisiert (rein numerische Werte erhalten das NU-Präfix der Personentabelle)

## Version 1.0.1 (2026-07-20)

* Add: Frontend-Modul wertungsportal_bestenliste (DWZ-Bestenliste, Top-x alle Spieler oder nur Frauen; Felder dwz_topcount/dwz_gender wie im DeWIS-Vorbild). Optimierung gegenüber dem contao-dewis-bundle: Die Liste kommt aus einer einzigen SQL-Abfrage auf die lokale Personentabelle (nur Deutsche mit aktiver Mitgliedschaft, ohne Blacklist/Verstorbene, FIDE-Titel gebündelt aus tl_wertungsportal_elo) statt aus der nu-API mit Überabruf und einem FIDE-Nation-Einzelabruf je Spieler — dadurch entfällt das bisher nötige Langzeit-Caching komplett, die Ausgabe ist sofort da und immer aktuell
* Add: Wertungsportal-Cache wird nach jedem FIDE-Elo-Import automatisch geleert (gecachte Seiten tragen die FIDE-Anreicherung eingebacken und würden sonst bis zu 24 h alte Elo/Titel zeigen); neue zentrale Funktionen API::purgeCache()/calcCache() und neuer Purge-Job "Wertungsportal-Cache leeren" in der Systemwartung (TL_PURGE, Sprachdateien tl_maintenance de/en, mit Anzeige der Einträge je Cache-Speicher)
* Change: Backend-Modultitel ohne "WP | "-Prefix (Vereine, Personen, Turniere, FIDE-Elo; de + en)
* Add: Die Vereine-Altdatenübernahme legt Vereine mit unbekannter VKZ jetzt automatisch in tl_wertungsportal_clubs an (Name und Status aus tl_dwz_ver; abgemeldete Vereine erhalten das Löschkennzeichen DELETE_STATE_TRUE), inklusive der übernommenen Felder Logo/Info/Homepage/Alternativname
* Fix: Die globale Operation "Bilder übernehmen" (key=importPhotos) war nur in der config.php registriert, der Button fehlte in der Personen-DCA — Spielerbild-Übernahme war dadurch im Backend nicht aufrufbar
* Add: Englische Sprachlabels (FMD) für die Frontend-Module und englische tl_module-Sprachdatei
* Add: Neues Backend-Modul FIDE-Elo (tl_wertungsportal_elo, Feldbestand wie tl_dwz_elo, Indizes nur fideid/surname/published) mit XML-Import der FIDE-Ratingliste als globale Operation (key=importElo): Chunk-Upload à 2 MB, Zip wird per Magic-Bytes erkannt und serverseitig streamend entpackt, Import in Paketen à 2000 Spielern per Byte-Offset (Puffer-Parser); Upsert per FIDE-ID, geschrieben wird nur bei Änderungen, elodate wandert nur bei echten Änderungen mit (sonst würde jeder Folgelauf alle ~1,9 Mio. Zeilen schreiben). Mit der echten players_list_xml.zip verifiziert (835 MB XML, 1.887.095 Spieler fehlerfrei geparst)
* Change: FIDE-Anreicherung (Helper::getFIDEDatenLokal/getFIDEDatenListe, API::getFIDE, Wertungsportal_Converter.php) liest jetzt tl_wertungsportal_elo statt tl_dwz_elo
* Change: Alle tl_dwz-DCA- und -Sprachdateien sowie die DeWIS-Backend-Module aus dem Bundle entfernt — neue Strategie: Das contao-dewis-bundle läuft übergangsweise parallel und verwaltet die tl_dwz_*-Tabellen selbst; die DwzSpi-/DwzVer-Models bleiben für Altdaten-Übernahmen und Foto-/Logo-Fallbacks registriert (tote Helper::Blacklist() entfernt)
* Add: Blacklist in tl_wertungsportal_persons (Felder blocked/grund/melder analog tl_dwz_spi): Gesperrte Personen erscheinen in keiner Ausgabe mehr — Spielersuche/Vereinsliste/Verbandsrangliste lassen die Zeile weg, Karteikarte und eigener Spielberichtsbogen zeigen eine neutrale Meldung, in Turnierauswertung/Kreuztabelle/Scoresheet-Gegnerliste bleibt die Zeile wegen der Querbezüge erhalten, aber ohne Personenbezug (Name "gesperrt", keine Links, keine Vereins-/FIDE-Daten). Zentrale Bulk-Prüfung Helper::getBlacklist() mit Request-Cache und Spalten-Guard (kein Fehler vor contao:migrate); Filterung greift zur Renderzeit, also auch bei Cache-Treffern sofort
* Add: Altdaten-Übernahmen als globale Operationen (Classes/AltdatenImport.php, Ergebnis-Template be_wp_altdaten): Vereine → "Altdaten übernehmen" (Logo/Homepage/Info/Altname aus tl_dwz_ver per VKZ), Personen → "Bilder übernehmen" (Spielerbild aus tl_dwz_spi per externeNr=dewisID); es werden nur leere Zielfelder befüllt, die Läufe sind idempotent
* Add: tl_wertungsportal_clubs um altname/addImage/singleSRC/info/homepage erweitert; das Vereins-Frontend liest die neuen Felder mit Fallback auf tl_dwz_ver
* Add: Karteikarte zeigt die Verbandszugehörigkeiten je Mitgliedschaft (Kette DSB → ... → Verband über clubs.federation, Helper::getVerbandskette())
* Add: Diagramm "DWZ und Leistung" in der Karteikarte als serverseitiges SVG (kein Chart.js): fehlende Leistungen (unter 5 Partien) werden geschätzt (Gegnerschnitt + 800 × Score-Anteil − 400, Rest linear interpoliert; hohle Punkte mit "(geschätzt)"-Tooltip), Jahreslabels um 45° gedreht; bei mehr als 50 Turnieren zeigt die Karte die letzten 50, das Komplettdiagramm öffnet in einem Overlay (CSS in default.css → assets:install). Toter Chart.js-Verweis auf das alte contaodewis-Bundle aus dem Template entfernt
* Change: Karteikarten-Spielerbild: zuerst das eigene Bild der Person (neue Felder addImage/singleSRC), dann tl_dwz_spi über die externe Nummer (der alte dewisID=nu-ID-Match konnte nie treffen), sonst Standardbild
* Change: Turnierergebnisse: Die Rundenzahl wird als höchste Rundennummer aus den Partien ermittelt (der Metadaten-Wert "rounds" der nu-Schnittstelle ist falsch); mehrere Ergebnisse je Runde werden angehängt statt überschrieben und im Template vollständig gerendert
* Add: Personen-Import: Datum/Uhrzeit aus dem Dateinamen (Vereine__Vereinsmitglieder__JJJJMMTTHHIISS) wird als tstamp der importierten Datensätze gesetzt; Importregel dokumentiert: Die CSV-Daten überschreiben Bestandsdaten immer, unabhängig vom vorhandenen tstamp

## Version 1.0.0 (2026-07-20)

* Fix: Personen-Import: Die AJAX-Aufrufe dürfen KEINEN X-Requested-With-Header senden, sonst fängt Contaos eigenes Ajax-System die Anfrage mit "Missing Ajax action" ab, bevor der key-Callback läuft (im Livetest gefunden, Template korrigiert)
* Add: Personen-Import aus der Vereinsmitglieder-CSV als globale Operation unter WP | Personen (key=importPersons). Upload und Import laufen in AJAX-Schritten (Chunk-Upload à 2 MB nach system/tmp, Import in Paketen à 1000 Zeilen per Byte-Offset), damit auch ~100.000 Zeilen ohne Timeout durchlaufen; Fortschrittsanzeige und Ergebnisstatistik im Backend-Template be_wp_personenimport. Spaltenzuordnung erfolgt über die Kopfzeile (Pflichtspalten InterneNr/ExterneNr/Nachname/Vorname), Personen werden per Bulk-Upsert über die InterneNr (nuLigaPersonId) angelegt bzw. nur bei Änderungen aktualisiert (WertungsportalPersonsModel::importCsvRows)
* Add: Der Personen-Import übernimmt auch die Vereinsmitgliedschaften in die Kindtabelle tl_wertungsportal_persons_memberships – Match über VKZ + Mitgliedsnummer (neuer Verbund-Index; laut Datei-Analyse kollisionsfrei), bestehende Datensätze werden nur bei Änderungen aktualisiert (inkl. pid-Verknüpfung zur Person), neue per Batch-INSERT angelegt, nichts wird gelöscht; die Vereine der Mitgliedschaften werden über ClubsModel::syncList mit angelegt. Neue Felder spielgenehmigungVon/spielgenehmigungBis; Spielgenehmigung wird auf den Lizenzstatus gemappt (Aktiv/Passiv/Sondermitgliedschaft/ohne Spielgenehmigung → ACTIVE/PASSIVE/SONDER/OHNE, neue Optionen inkl. Sprachdateien)
* Add: 29 neue Felder in tl_wertungsportal_persons für den CSV-Import, u. a. externeNr (entspricht größtenteils der alten dewisID aus tl_dwz_spi, mit Index für das spätere Matching), Anrede/Titel/Geburtsname/-ort, Verstorben(-Am), Nation/FIDE-Nation, Geschlecht im Spielbetrieb, Adresse und Kontaktdaten sowie Datenschutzfelder; neue Indizes auf nuLigaPersonId und externeNr — DB-Update erforderlich (contao:migrate/Install-Tool)
* Umfangreiche Programmierungen außer bei den Downloads, Caching und lokaler Sicherung
* Change: Korrekturen Datensatzanzeige mit Hilfe von Claude
* Add: tl_dwz_spiver.bearbeiter (kommt aus MIVIS)
* Add: Tabelle tl_wertungsportal_clubs (Felder aus /dwz/dwzliste/clubs) mit DCA und deutscher Sprachdatei, mit Claude erstellt
* Add: Model WertungsportalClubsModel mit Findern (findByVkz, findPublished, findActive, searchByName) und Upsert-Funktion, mit Claude erstellt
* Add: Backend-Modul wp-clubs zur Verwaltung der Vereine
* Add: API::syncClubs() – Abfragen von /dwz/dwzliste/clubs (Vereinsname, Verbaende) gleichen die Rückgabe automatisch mit tl_wertungsportal_clubs ab
* Change: Reihenfolge der Backend-Module geändert (wp-clubs vor den tl_dwz-Modulen)
* Add: Sortierung in tl_wertungsportal_clubs nach clubVkz (Standard) und clubName, jeweils alphabetisch aufsteigend
* Add: Unveröffentlichte Vereine werden in der Backendliste rot dargestellt (auch direkt beim Betätigen des Togglers)
* Add: Tabellen tl_wertungsportal_persons und tl_wertungsportal_persons_memberships (Felder aus /dwz/dwzliste/persons inkl. memberships) mit DCA und deutschen Sprachdateien, mit Claude erstellt
* Add: Models WertungsportalPersonsModel und WertungsportalPersonsMembershipsModel mit Findern und Upsert-/Sync-Funktionen, mit Claude erstellt
* Add: Backend-Modul wp-persons zur Verwaltung der Personen und ihrer Mitgliedschaften
* Add: API::syncPersons() – Abfragen von /dwz/dwzliste/persons (Spielerliste, Karteikarte, Verbandsliste) gleichen die Rückgabe automatisch mit tl_wertungsportal_persons und der Kindtabelle ab
* Add: Unveröffentlichte Personen werden in der Backendliste rot dargestellt
* Add: Englische Sprachdateien (Fallback) für tl_wertungsportal_clubs, tl_wertungsportal_persons, tl_wertungsportal_persons_memberships und die neuen Backend-Module
* Add: Label für Backend-Modul wp-persons in der deutschen modules.php
* Fix: Operationen in tl_wertungsportal_persons: edit öffnet die Mitgliedschaften, editheader bearbeitet die Person
* Add: Unveröffentlichte Mitgliedschaften werden in der Elternansicht rot dargestellt (auch direkt beim Betätigen des Togglers)
* Change: Zwischenüberschriften in der Mitgliedschaftsliste deaktiviert (disableGrouping), Kopfdaten der Person bleiben erhalten
* Add: Tabelle tl_wertungsportal_tournaments (Felder aus entries.tournament von /dwz/persons/{id}/history) mit DCA, Sprachdateien (de/en) und Backend-Modul wp-tournaments, mit Claude erstellt
* Add: Kindtabellen tl_wertungsportal_persons_tournaments (Turnierhistorie, Felder aus entries.player plus tournamentUuid als redundanzfreie Referenz) und tl_wertungsportal_persons_upgrades (Felder aus upgrades) mit DCAs und Sprachdateien (de/en), mit Claude erstellt
* Add: Models WertungsportalTournamentsModel, WertungsportalPersonsTournamentsModel und WertungsportalPersonsUpgradesModel mit Upsert-/Sync-Funktionen, mit Claude erstellt
* Add: Operationen in tl_wertungsportal_persons zum Öffnen der Turnierhistorie und der Hochstufungen (Bundle-Icons icon_turniere.png und rating.png)
* Add: API::syncPersonHistory() – die Abfrage Karteikarte_Turniere (/dwz/persons/{id}/history) gleicht Person, Mitgliedschaften, Turnierhistorie, Turniere und Hochstufungen automatisch ab
* Add: Unveröffentlichte Turniere werden in der Backendliste rot dargestellt
* Add: tl_wertungsportal_tournaments um playerCount und matchCount ergänzt (Felder aus /dwz/tournaments/{uuid})
* Add: Kindtabellen tl_wertungsportal_tournaments_evaluation (DWZ-Auswertung, Felder aus evaluation.players) und tl_wertungsportal_tournaments_matches (Partien, Felder aus matches.data; Spieler redundanzfrei über whitePlayerUuid/blackPlayerUuid referenziert) mit DCAs und Sprachdateien (de/en), mit Claude erstellt
* Add: Models WertungsportalTournamentsEvaluationModel und WertungsportalTournamentsMatchesModel mit Upsert-/Sync-Funktionen, mit Claude erstellt
* Add: Operationen in tl_wertungsportal_tournaments zum Öffnen der Auswertung (edit) und der Partien (matches), editheader bearbeitet das Turnier
* Add: API-Abgleich für Turnierinfo, Turnierliste (syncTournaments), Turnierauswertung (syncTournamentEvaluation), Turnierergebnisse (syncTournamentMatches) und Spielberichtsbogen (syncScoresheet – aktualisiert Turnier, Partien und Spielerdaten, keine eigene Tabelle)
* Add: Unveröffentlichte Auswertungseinträge und Partien werden in der Elternansicht rot dargestellt
* Change: Turnierliste: Werkzeugsymbol (Turnier bearbeiten) an erster Stelle, Auswertungs-Button mit Rating-Icon statt Bleistift
* Add: Anzeige der Spielernamen (nicht editierbar) unter den UUID-Feldern im Bearbeitungsformular von tl_wertungsportal_tournaments_matches
* Add: Einlesen von evaluation, matches und scoresheet aktualisiert auch tl_wertungsportal_persons (Referenz nuLigaPersonId, nur Identitätsfelder)
* Fix: upsertByUuid in WertungsportalPersonsModel mit Fallback auf nuLigaPersonId, um Duplikate bei zuvor ohne UUID angelegten Personen zu vermeiden
* Add: Turnierauswertung, Turnierergebnisse und Spielberichtsbogen aktualisieren auch die Turnierhistorie tl_wertungsportal_persons_tournaments (upsertEntry aus syncForPerson extrahiert)
* Fix: Abruf Vereinsliste synchronisiert jetzt Personen (syncPersons) – Vereine werden dabei über die Mitgliedschaften in tl_wertungsportal_clubs abgelegt (VKZ und Vereinsname, gilt für alle Personen-Abfragen)
* Change: Personenliste: Werkzeugsymbol (Person bearbeiten) an erster Stelle, Mitgliedschaften-Button mit Vereins-Icon statt Bleistift
* Change: Neue Icons für die Operationen: history.png (Turnierhistorie), chart.png (DWZ-Hochstufungen), rating2.png (DWZ-Auswertung), games.png (Partien) – keine Doppelbelegungen mehr
* Change: Neue Operations-Icons auf 16x16 Pixel verkleinert
* Change: tl_wertungsportal_persons.birthyear von int auf varchar(10) umgestellt – erlaubt Geburtsjahr (JJJJ) oder vollständiges Geburtsdatum; API-Abgleich überschreibt ein manuell gepflegtes Datum nicht, solange das Jahr übereinstimmt
* Fix: Spieler.php: fehlende Absicherung der optionalen API-Felder wins und numberOfGames in der Karteikarte (500er bei Historien-Einträgen ohne diese Felder)
* Change: Turnier.php: API-Fehler (z. B. "No evaluation found") werden bei Turnierauswertung, Turnierergebnissen und Spielberichtsbogen im Template ausgegeben statt auf die 404-Seite umzuleiten (neue Methode templateFehler, Templates um Fehlerausgabe ergänzt)
* Fix: Fehlertolerante Datumsformatierung Helper::ApiDatum() – fehlendes enddate/lastCalculated (z. B. bei nie berechneten Turnieren) führte in Turnierergebnisse, Turnierauswertung, Scoresheet und Turniersuche zu einem 500er
* Fix: Helper::PlayerDefaults() füllt fehlende Felder der Spieler-DTOs auf – ersetzt die unvollständigen array_key_exists-Blöcke in Turnierergebnisse, Scoresheet und Turnierauswertung (500er bei fehlendem wins u. a.)
* Add: CLAUDE.md mit Projektkontext, Sync-Architektur, Fallstricken und offenen Punkten
* Change: Sync-Performance: Alle API-Abgleiche schreiben nur noch bei tatsächlichen Änderungen (neuer ApiSyncTrait::applyApiFields in allen Wertungsportal-Models) – unveränderte Datensätze erzeugen keine UPDATE-Queries mehr
* Change: Sync-Performance: syncClubs und syncTournaments nutzen neue Bulk-Methoden (WertungsportalClubsModel::syncList, WertungsportalTournamentsModel::syncList) – Bestand wird mit einer Abfrage geladen, neue Datensätze werden blockweise per Batch-INSERT angelegt (statt SELECT+UPDATE je Datensatz, vorher ~4800 Queries pro Seitenaufruf bei ~2400 Vereinen)
* Change: Der Veröffentlichungsstatus wird beim API-Abgleich nur noch beim Anlegen gesetzt – manuell deaktivierte Datensätze werden durch einen Sync nicht mehr wieder veröffentlicht
* Add: Token-Schutz für die Cron-Download-Skripte Wertungsportal_Download.php und Wertungsportal_Converter.php – Aufruf nur noch mit ?key=SCHLÜSSEL (neue Einstellung wertungsportal_crontoken, ohne Eintrag sind die Skripte gesperrt)
* Add: Download-Absicherung für die Cron-Skripte – neue Helper::DownloadDatei() prüft Curl-Fehler, HTTP-Status und Zip-Konsistenz, wiederholt fehlgeschlagene Downloads bis zu 3-mal (5 s Pause) und löscht defekte Dateien statt sie zu archivieren (der nu-Server lieferte am 17.07.2026 zeitweise abgeschnittene Zips); Download-Skript meldet Fehlschläge im Abschlusstext, Converter bricht bei endgültigem Fehlschlag mit FEHLER-Meldung ab
* Change: Sync-Performance Stufe 2 – auch Personen-, Mitgliedschafts-, Turnierhistorien-, Auswertungs- und Partien-Syncs arbeiten jetzt als Bulk-Verarbeitung (Bestand per IN-Liste laden, Batch-INSERTs, Updates nur bei Änderungen, gesammelte DELETEs): neue Methoden WertungsportalPersonsModel::syncList()/syncFromPlayerDtos(), WertungsportalPersonsMembershipsModel::syncForPersons(), WertungsportalPersonsTournamentsModel::syncEntries(), WertungsportalTournamentsEvaluationModel::syncPlayers(); MatchesModel::syncForTournament komplett auf Bulk umgestellt. Betroffene Seiten: Vereins-/Verbands-/Spielerlisten (vorher ~7500 Queries bei 765 Mitgliedern) und Turnierergebnisse/Auswertung/Scoresheet (vorher ~2800 Queries bei 266 Partien)
* Fix: Modul-Links und Suchformular-Actions verlassen den Contao-Vorschaumodus nicht mehr – neue Helper-Methoden getSpielerseiteUrl/getTurnierseiteUrl/getVereinseiteUrl/getVerbandseiteUrl liefern die per Router generierte Seiten-URL (im Vorschaumodus inkl. preview.php-Präfix) statt des nackten Alias; alle Link-Bauer in den Formatter-Klassen, Helper::Spielername, der Listenlink in Verein.php und die vier Formular-Actions in den Templates umgestellt. Die Alias-Getter bleiben für die URL-Fragment-Vergleiche und Location-Redirects in API.php erhalten
* Change: Tote Archiv-Kopien src/Helper/OAuth2Client_v1.php und _v2.php gelöscht (deklarierten dieselbe Klasse wie die aktive OAuth2Client.php, v2 mit Parse-Fehler, beide mit hartkodierten Demo-Zugangsdaten) – beim Deployment auch auf dem Server entfernen
* Change: Frontend-Module restrukturiert – die Datenaufbereitung der vier Module in src/Classes ist nach dem Muster von Turniersuche/Scoresheet in eigene Helper-Klassen ausgelagert: Spielersuche, Karteikarte (Spieler.php), Vereinssuche, Vereinsliste (Verein.php), Verbandsnavigation, Verbandsrangliste (Verband.php), Turnierformular (Turnier.php). Die Module enthalten nur noch Parameterverarbeitung, API-Aufrufe, Fehlerbehandlung, Bilder und Template-Zuweisungen
* Fix: Vereinssuche zeigte den Hinweis „Der Suchbegriff darf nur Buchstaben…" schon beim leeren Suchformular – Validierung greift jetzt nur noch bei tatsächlicher Eingabe
* Fix: Karteikarte mit API-Fehler (z. B. nu-Timeout) führte zu einem PHP-Fehler – jetzt saubere Fehlermeldung im Template wie bei den Turnierseiten
* Change: Turnier.php protokolliert die GET-Parameter nur noch bei aktiviertem Debug-Log (wertungsportal_debuglog) statt bei jedem Seitenaufruf; die Verbands-/Vereinsliste wird nur noch für das Suchformular geladen statt bei allen Turnier-Ansichten
* Change: Ungenutzte Blacklist-Abfragen in Spieler.php, Verband.php und Turnier.php entfernt (je 1 überflüssige DB-Query pro Seitenaufruf) sowie weiterer toter Code (ungenutzte $gesperrt/$mitglied/$dewis-Variablen)
* Change: FIDE-Anreicherung gebündelt – neue Helper::getFIDEDatenListe() lädt die FIDE-Daten (Elo/Titel/Nation) aller Spieler einer Liste mit einer IN-Abfrage aus tl_dwz_elo statt je Spieler einzeln; umgestellt in setFIDEDaten (Spieler-/Vereinsliste), Verband.php (Verbandsrangliste) und Turnierauswertung.php (vorher z. B. ~450 Einzelabfragen bei der Vereinsliste des Hamburger SK)
* Fix: Spielersuche mit Mitglieds- oder ZPS-Nummer führte zu einem 500er (Undefined variable $param in Spieler.php) – numerische bzw. ZPS-Eingaben zeigen jetzt einen Hinweis im Suchformular statt abzustürzen
* Fix: Spielersuche „Nachname, Vorname" lieferte 0 Treffer, obwohl der Spieler existierte (z. B. „Müller, Karsten"). Ursache: Spieler.php sluggte den Suchstring (contao.slug) VOR der Komma-Trennung, wodurch das Komma zu „-" wurde und die Nachname/Vorname-Trennung fehlschlug. Jetzt wird zuerst getrennt und erst danach werden Nachname und Vorname einzeln geslugt; Helper::checkSearchstringPlayer belegt seine Rückgabefelder vor (keine undefinierten Variablen im Komma-/pkz-/zps-Zweig)
* Fix: Wertungsportal_Converter.php: Die Verbands-Zips (LV-1 bis LV-M) enthielten immer die README.txt aus Gesamtdeutschland – neue Methode writeReadme() passt Überschrift (Landesverband: X - Verbandsname aus verbaende.csv) und Spieler-/Vereinszahlen an den jeweiligen Verband an; die LV-0-README bleibt unverändert

## Version 0.0.3 (2026-07-07)

* Change: Klasse Wertungsportal durch OAuth2Client ersetzt, mit Claude Code korrigiert
* Add: Klassen für Spieler, Verein, Turnier und Verband
* Add: Helper-Klasse aus DeWIS modifiziert übernommen
* Add: API-Klasse
* Add: Cachefunktion
* Add: Abhängigkeit schachbulle/contao-helper-bundle
* Add: Weitere Einstellungen wie Spieler- und Vereinsbild
* Add: Übernahme Tabellen mit Prefix tl_dwz
* Change: OAuth2Client mit Claude geändert um RuntimeException zu umgehen
* Add: tl_dwz_spi.nuLigaPersonId
* Umfangreiche Programmierungen außer bei den Downloads, Caching und lokaler Sicherung

## Version 0.0.2 (2026-06-01)

* Add: System-Einstellungen
* Add: Klasse Wertungsportal für den Zugriff auf die neue API
* Add: api.php -> öffentlicher Aufruf der API für Testzwecke

## Version 0.0.1 (2026-05-29)

* Erste Alphaversion
