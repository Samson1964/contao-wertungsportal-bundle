# Foto ändern, Logo/Infos ändern

Angemeldete Mitglieder können dem DSB ein neues **Foto** für eine Karteikarte
schicken und für einen Verein **Logo, Homepage und Beschreibung** ändern
lassen. Beide Formulare öffnen sich wie die [Reklamation](reklamationen.md) in
einem Dialog und ersetzen die früheren mailto-Links „Foto senden" und „Logo
senden" (ab 1.54.0).

Der DSB-Admin bekommt eine E-Mail mit allem Nötigen und überträgt die Änderung
von Hand ins Backend. **Das Bundle ändert an Spielern und Vereinen nichts
selbst.**

## Wo die Links stehen

| Ansicht | Link | Stelle |
|---|---|---|
| DWZ-Karteikarte | **Foto ändern** | unter dem Spielerbild |
| DWZ-Liste eines Vereins | **Logo/Infos ändern** | unter dem Vereinslogo |

Die Links erscheinen nur,

- wenn ein Mitglied angemeldet ist **und** in seinem Konto eine gültige
  E-Mail-Adresse steht, und
- wenn unter **Wertungsportal → Einstellungen → Reklamationen** die Adresse des
  DSB-Admins eingetragen ist.

Gäste sehen an diesen Stellen nichts mehr.

## Die Formulare

**Foto ändern**

| Feld | |
|---|---|
| Betreff | vorbelegt: „Neues Foto für Vorname Nachname (NU…)", änderbar |
| Foto | Pflicht |
| Nachricht | freiwillig |
| Kopie an mich | Häkchen; die Kopie geht an die Adresse aus dem Mitgliedskonto |

**Logo/Infos ändern**

| Feld | |
|---|---|
| Betreff | vorbelegt: „Änderung der Vereinsdaten: Verein (Kennziffer)" |
| Neues Logo | freiwillig |
| Homepage | vorbelegt mit der hinterlegten Adresse; leeren = soll entfallen |
| Über den Verein | vorbelegt mit dem hinterlegten Text, im Editor (TinyMCE) |
| Nachricht | freiwillig |
| Kopie an mich | Häkchen |

**Wird nichts verändert, wird auch nichts abgeschickt:** Ohne neues Logo, mit
unveränderter Homepage und Beschreibung und ohne Nachricht meldet das Formular
„Sie haben noch nichts geändert".

## Dateien

- Erlaubt sind **JPEG, PNG, GIF und WebP**, höchstens **5 MB** (oder weniger,
  wenn der Server je Datei weniger zulässt, `upload_max_filesize`). SVG ist
  ausgeschlossen, weil es Skripte enthalten kann.
- Geprüft wird der **Inhalt** der Datei, nicht ihre Endung: Eine Textdatei mit
  dem Namen `bild.jpg` wird abgewiesen.
- Die Datei wird **nirgends auf dem Server abgelegt**. Sie hängt an der E-Mail
  — unter einem Namen, den das Bundle bildet (`Foto-NU4481210.jpg`,
  `Logo-64231.png`), nicht unter dem des Absenders.

## Die Nachricht

| Kopffeld | Inhalt |
|---|---|
| Von | Absender der Reklamationen (siehe [Reklamationen](reklamationen.md)) |
| An | DSB-Admin |
| Cc | das Mitglied — nur mit Häkchen „Kopie an mich" |
| Antwort an | das Mitglied, mit der Adresse aus seinem Konto |
| Anhang | das Foto bzw. Logo |

Reine Textmail. Bei den Vereinsdaten steht nur darin, **was sich ändern soll**:
die Homepage als „bisher/neu", die Beschreibung als **HTML-Quelltext** (zum
Einfügen in die Quelltextansicht des Editors im Backend) und darunter noch
einmal als lesbarer Text. Am Ende steht die Fußzeile mit Name, Adresse und
Mitgliedsnummer aus dem Konto.

Jede Meldung steht außerdem im Backend-Modul **Wertungsportal →
Reklamationen**, Ansicht „Foto ändern (Karteikarte)" bzw. „Logo/Infos ändern
(Verein)" — ohne die Datei.

## Sicherheit

- Spieler, Verein und Empfänger kommen nie aus dem Formular: Sie reisen
  signiert mit (HMAC-SHA256 mit dem Kernel-Secret), wie bei der Reklamation.
- Die Route `POST /wertungsportal-api/aenderung` prüft die Anmeldung selbst und
  läuft mit Contaos Anfragetoken.
- Höchstens 5 Nachrichten je Mitglied und Stunde — Reklamationen und
  Änderungsmeldungen zusammengezählt.
- Die Beschreibung aus dem Editor wird gesäubert: erlaubt sind Absätze,
  Zeilenumbrüche, fett, kursiv, unterstrichen, Listen, Zwischenüberschriften,
  Zitate und Verweise (nur `http`, `https`, `mailto`). Skripte, Stile, Bilder,
  Rahmen und Formulare werden entfernt, alle übrigen Attribute ebenso. Sie
  geht als Quelltext in eine Textmail und wird nirgends als HTML dargestellt.
- Die Kopie geht nur an die Adresse aus dem Mitgliedskonto.

## Technik

- Logik: `Helper\Aenderung` (erbt von `Helper\Reklamation`), Controller
  `Controller\AenderungController`, Skript `public/js/aenderung.js`.
- Der Dialog „Logo/Infos ändern" ist **nicht modal**: TinyMCE hängt seine
  Menüs und Fenster an das Ende der Seite, unter einem modalen Dialog lägen
  sie unsichtbar dahinter. Die Abdunklung macht ein eigenes Element.
- TinyMCE kommt aus Contaos `assets/tinymce4` (Contao 4.13: Fassung 5, Contao
  5: neuere) und wird erst beim Öffnen des Dialogs geladen. Fehlt es, bleibt
  ein Textfeld mit dem HTML-Quelltext.

## Einspielen

`contao:assets:install` (neues Skript, Stile) und `cache:clear` (neue Route,
neuer Dienst). Der DSB-Admin muss unter Einstellungen → Reklamationen
eingetragen sein.
