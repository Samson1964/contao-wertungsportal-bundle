# DWZ-Daten in die eigene Seite holen — Schritt für Schritt

Diese Anleitung richtet sich an alle, die ein wenig PHP können, aber noch nie
mit einer Schnittstelle gearbeitet haben. Sie führt vom leeren Verzeichnis bis
zur fertigen Spielersuche auf der Vereinsseite.

Wenn Sie schon wissen, was OAuth2 ist, und nur die Regeln nachschlagen wollen:
Die Kurzfassung steht in der Datei `DsbOAuth2Client.php` ganz oben.

---

## 1. Worum geht es hier?

Der Deutsche Schachbund verwaltet die DWZ aller Mitglieder im **DWZ-System**
(auch „Wertungsportal"). Turniere, Auswertungen, Spielerdaten und die
DWZ-Liste stehen dort über eine **Schnittstelle** bereit: Ihr Programm fragt
eine Internetadresse ab und bekommt die Daten zurück — nicht als Webseite,
sondern als Text in einem festen Aufbau, den ein Programm auswerten kann
(JSON).

Damit nicht jeder beliebig Daten abrufen kann, ist die Schnittstelle
**geschützt**. Sie melden sich mit einer Kennung an und bekommen dafür einen
**Access Token** — man kann ihn sich wie eine Eintrittskarte vorstellen. Diese
Karte legen Sie bei jedem Abruf vor. Sie gilt nur fünf Minuten, danach holen
Sie sich eine neue.

Genau dieses Kartenholen ist der Teil, bei dem man viel falsch machen kann.
Deshalb gibt es hier eine fertige Klasse: **`DsbOAuth2Client`**. Sie kümmert
sich um die Karten, Sie kümmern sich um die Daten.

---

## 2. Was Sie brauchen

**PHP ab Version 7.4.** Ob und welches PHP Sie haben, verrät auf der
Kommandozeile:

```bash
php -v
```

Kommt eine Fehlermeldung statt einer Versionsnummer, ist PHP nicht installiert
oder nicht im Suchpfad. Unter Windows liegt es bei XAMPP zum Beispiel unter
`C:\xampp\php\php.exe`, dann rufen Sie es mit dem vollen Pfad auf.

Außerdem müssen zwei Erweiterungen vorhanden sein — `curl` (holt Daten aus dem
Netz) und `json` (versteht das Antwortformat). Bei fast jeder Installation sind
beide schon dabei. Prüfen:

```bash
php -m
```

In der Liste sollten `curl` und `json` stehen, für die Zip-Dateien aus
Beispiel 5 zusätzlich `zip`.

---

## 3. Den Zugang beantragen

Schreiben Sie den Deutschen Schachbund an und bitten Sie um eine
**API-Freischaltung** für das DWZ-System. Sie bekommen dann:

1. Eine **E-Mail mit einem Link**. Der Link ist 48 Stunden gültig.
2. Hinter dem Link legen Sie ein **Passwort** fest — das ist Ihr
   *Client Secret*. Mindestens zehn Zeichen, dazu Groß- und Kleinbuchstaben und
   eine Ziffer.
3. Danach erhalten Sie eine zweite E-Mail mit Ihrer **Client-ID**. Sie sieht
   aus wie `0f8e2c4a-1b3d-4e5f-9a7b-6c5d4e3f2a1b`.

Client-ID und Client Secret zusammen sind Ihr Zugang. **Geben Sie sie nicht
weiter** und legen Sie sie nicht in ein Verzeichnis, aus dem der Webserver
Dateien ausliefert.

> **Zwei Bereiche, zwei Freischaltungen.** Turniere und Spielerhistorien
> gehören zum einen Bereich, die DWZ-Liste (Spielersuche, Vereine, Verbände,
> Zip-Dateien) zum anderen. Es kann sein, daß Sie für beides eine eigene
> Kennung bekommen. Die DWZ-Liste ist im Moment noch ohne Anmeldung erreichbar
> — das wird sich ändern.

---

## 4. Die Dateien ablegen

Entpacken Sie den Ordner irgendwo, wo PHP ihn ausführen darf. Zum Ausprobieren
reicht ein beliebiges Verzeichnis auf Ihrem Rechner.

```
oauth2-beispiel/
├── DsbOAuth2Client.php          ← die Klasse, die alles erledigt
├── zugangsdaten.beispiel.php    ← Vorlage für Ihre Kennung
├── start.php                    ← gemeinsamer Anfang der Beispiele
├── beispiel-1-turniere.php
├── beispiel-2-turnier.php
├── beispiel-3-spieler.php
├── beispiel-4-dwzliste.php
├── beispiel-5-download.php
├── beispiel-6-webseite.php      ← eine fertige Seite mit Suchformular
└── README.md                    ← diese Anleitung
```

---

## 5. Zugangsdaten eintragen

Kopieren Sie `zugangsdaten.beispiel.php` nach **`zugangsdaten.php`** und öffnen
Sie die Kopie in einem Texteditor. Tragen Sie oben Ihre beiden Angaben ein:

```php
	'clientId'     => '0f8e2c4a-1b3d-4e5f-9a7b-6c5d4e3f2a1b',
	'clientSecret' => 'IhrPasswort123',
```

Haben Sie **keine** eigene Freischaltung für die DWZ-Liste, lassen Sie
`listeClientId` und `listeClientSecret` leer. Die Beispiele sagen Ihnen dann,
daß sie ohne Anmeldung arbeiten — das geht derzeit noch.

Speichern Sie die Datei als **UTF-8**. Fertig.

---

## 6. Der erste Start

Wechseln Sie auf der Kommandozeile in das Verzeichnis und starten Sie das erste
Beispiel:

```bash
php beispiel-1-turniere.php
```

Wenn alles stimmt, sehen Sie so etwas:

```
Turniere vom 2026-01-01 bis 2026-09-25: 3598 Treffer, hier die ersten 15

2026-09-22  Hamburger Pokal-Mannschaftsmeisterschaft 2026   400   5 Runden  27cc161f-…
2026-09-21  NDVM 2026 AK u12w + AK u16w in Arendsee         G00   5 Runden  9f0a4c34-…
…
```

**Das war's schon.** Die Klasse hat sich im Hintergrund eine Eintrittskarte
geholt, sie in der Datei `token/dsb-token-….json` abgelegt und den Abruf
gemacht. Beim nächsten Start nimmt sie dieselbe Karte, solange sie gilt.

Klappt es nicht, springen Sie zu Abschnitt 10 — dort stehen die üblichen
Meldungen und was sie bedeuten.

---

## 7. Die Beispiele der Reihe nach

Jedes Beispiel ist eine eigene Datei, die Sie lesen und verändern können. Oben
in jeder Datei steht, was sie tut.

| Datei | Was sie zeigt | Aufruf |
|---|---|---|
| `beispiel-1-turniere.php` | Turniere suchen und filtern | `php beispiel-1-turniere.php 2026-01-01 2026-12-31` |
| `beispiel-2-turnier.php` | Ein Turnier mit Auswertung und Partien — **mehrere Abrufe mit einer Eintrittskarte** | `php beispiel-2-turnier.php` |
| `beispiel-3-spieler.php` | Spieler suchen und seine DWZ-Geschichte ausgeben | `php beispiel-3-spieler.php Müller` |
| `beispiel-4-dwzliste.php` | Lange Listen in Abschnitten abrufen | `php beispiel-4-dwzliste.php 40023` |
| `beispiel-5-download.php` | Die komplette DWZ-Liste als Zip-Datei | `php beispiel-5-download.php` |
| `beispiel-6-webseite.php` | Eine Seite mit Suchformular, **mit Zwischenspeicher** | im Browser öffnen |

Beispiel 6 brauchen Sie dafür nicht auf einen Webserver zu legen — PHP bringt
einen kleinen eigenen mit. Im Verzeichnis dieser Dateien:

```bash
php -S localhost:8000
```

Dann im Browser <http://localhost:8000/beispiel-6-webseite.php> aufrufen.

---

## 8. Ein eigenes Programm schreiben

Sie brauchen nur zwei Zeilen, um loszulegen:

```php
require __DIR__.'/DsbOAuth2Client.php';

$client = new DsbOAuth2Client('IHRE-CLIENT-ID', 'IhrPasswort', DsbOAuth2Client::SCOPE_TURNIERE);
```

Danach steht Ihnen dreierlei zur Verfügung:

**Etwas abrufen** — `get()` liefert die Antwort als verschachteltes Array:

```php
$antwort = $client->get('/dwz/tournaments', array('ratingState' => 'RATED', 'limit' => 10));

foreach ($antwort['data'] as $turnier) {
	echo $turnier['enddate'], '  ', $turnier['label'], "\n";
}
```

**Eine lange Liste durchgehen** — `alleSeiten()` holt Abschnitt für Abschnitt
und gibt Ihnen einen Datensatz nach dem anderen:

```php
foreach ($client->alleSeiten('/dwz/dwzliste/persons', array('vkz' => '40023')) as $spieler) {
	echo $spieler['lastname'], "\n";
}
```

**Eine Datei holen** — `herunterladen()` speichert und prüft sie:

```php
$bytes = $client->herunterladen('LV-0-dwzliste.zip', __DIR__.'/liste.zip');
```

Geht etwas schief, wirft die Klasse eine `DsbOAuth2Exception`. Fangen Sie sie
ab, sonst bricht Ihr Programm mit einer Fehlerseite ab:

```php
try {
	$antwort = $client->get('/dwz/tournaments');
} catch (DsbOAuth2Exception $e) {
	echo 'Das hat nicht geklappt: ', $e->getMessage();
}
```

Welche Adressen es gibt, steht in der [Übersicht](../oauth2-beispiel.md#die-endpunkte)
und vollständig in der [API-Dokumentation von
nu](https://schachde-apps.liga.nu/dsbwertungsportal/apidocs/).

---

## 9. Die vier Regeln, die wirklich wichtig sind

### Regel 1: Nicht bei jedem Seitenaufruf abrufen

Das ist der häufigste Fehler. Wenn Ihre Vereinsseite die Mitgliederliste bei
jedem Besucher neu holt, fragen Sie bei hundert Besuchern hundertmal dasselbe.
Das ist langsam für Ihre Besucher und unnötige Last für den DSB.

Legen Sie die Antwort statt dessen zwischen. `beispiel-6-webseite.php` zeigt
die einfachste Form — eine Datei je Abfrage:

```php
$datei = __DIR__.'/zwischenspeicher/'.sha1($pfad.http_build_query($parameter)).'.json';

if (is_file($datei) && filemtime($datei) > time() - 3600) {
	$antwort = json_decode(file_get_contents($datei), true);   // aus dem Speicher
} else {
	$antwort = $client->get($pfad, $parameter);                // frisch holen
	file_put_contents($datei, json_encode($antwort));
}
```

Eine Stunde ist für DWZ-Daten völlig in Ordnung — sie ändern sich nicht im
Minutentakt. Für die DWZ-Liste eines ganzen Verbands nehmen Sie besser gleich
die Zip-Datei (Beispiel 5); die wird einmal am Tag neu erzeugt.

### Regel 2: Die Tokendatei muß schreibbar sein

Die Eintrittskarte wird in einer Datei abgelegt, damit der nächste Aufruf sie
wiederverwenden kann. Kann PHP diese Datei nicht schreiben, holt sich **jeder
einzelne Aufruf** eine neue — und davon sind nur **fünf in 30 Minuten**
erlaubt. Danach macht die Schnittstelle für eine Weile zu.

Die Klasse warnt Sie deutlich, wenn das passiert:

> Die Tokendatei … läßt sich nicht schreiben. Bitte die Schreibrechte prüfen …

Übergehen Sie diese Warnung nicht. Achten Sie besonders darauf, wenn Ihre Seite
**und** ein Cronjob dieselbe Schnittstelle benutzen: Beide laufen oft unter
verschiedenen Benutzerkonten und brauchen beide Zugriff auf dieselbe Datei.

### Regel 3: Zugangsdaten gehören nicht ins Web-Verzeichnis

Liegt `zugangsdaten.php` dort, wo der Webserver Dateien ausliefert, kann sie im
schlimmsten Fall jemand herunterladen. Legen Sie sie eine Ebene darüber oder
schützen Sie das Verzeichnis. Auf Linux-Servern zusätzlich:

```bash
chmod 600 zugangsdaten.php
```

Und: nicht in ein öffentliches Git-Verzeichnis einchecken. Die beiliegende
`.gitignore` hält sie bereits heraus.

### Regel 4: Felder können fehlen

Die Schnittstelle läßt Felder einfach weg, statt sie auf 0 zu setzen. Ein
Spieler ohne DWZ hat **kein** `rating` — nicht `rating = 0`. Fragen Sie deshalb
immer mit `??` ab:

```php
$dwz = $person['rating'] ?? null;     // richtig

if (null === $dwz) {
	echo 'ohne DWZ';
} else {
	echo $dwz;
}
```

Sonst bekommen Sie „Warning: Undefined array key" und im Ergebnis eine 0, die
aussieht wie eine echte Wertung.

---

## 10. Wenn eine Fehlermeldung kommt

| Meldung | Was zu tun ist |
|---|---|
| `Es fehlen die Zugangsdaten.` | `zugangsdaten.beispiel.php` nach `zugangsdaten.php` kopieren |
| `Tokenabruf abgewiesen (HTTP 401): Bad client credentials` | Client-ID oder Passwort stimmen nicht. Auf Leerzeichen am Anfang oder Ende achten |
| `Tokenabruf abgewiesen (HTTP 403): …` | Drei Möglichkeiten, die Meldung nennt alle drei: Ihre Kennung ist nicht freigeschaltet, Sie haben zu viele Karten geholt, oder der Scope paßt nicht. Eine halbe Stunde warten, dann nachsehen, ob die Tokendatei wirklich geschrieben wird |
| `Vor dem nächsten Versuch sind noch … Sekunden zu warten.` | Die Klasse bremst Sie absichtlich, damit es nicht schlimmer wird. Abwarten |
| `Verbindungsfehler: …` | Kein Netz, eine Firewall dazwischen oder die Schnittstelle ist gerade nicht erreichbar |
| `Die Zieldatei läßt sich nicht schreiben` | Schreibrechte im Zielverzeichnis fehlen |
| `Warning: Undefined array key "…"` | Ein Feld, das Sie erwarten, fehlt in der Antwort — siehe Regel 4 |

Zum Nachsehen, was die Schnittstelle wirklich geantwortet hat, hilft die
Ausnahme selbst weiter:

```php
try {
	$antwort = $client->get('/dwz/tournaments');
} catch (DsbOAuth2Exception $e) {
	echo 'HTTP ', $e->httpStatus(), "\n";   // der Status, etwa 403
	echo $e->antworttext(), "\n";           // die Antwort von nu im Original
}
```

---

## 11. Woran Sie denken sollten, bevor es live geht

- **Zwischenspeicher eingebaut?** (Regel 1)
- **Tokendatei liegt außerhalb des Web-Verzeichnisses und ist schreibbar?**
- **Zugangsdaten nicht öffentlich erreichbar, nicht im Git?**
- **Fehler abgefangen**, so daß Besucher keine PHP-Meldung sehen?
- **Ausgabe durch `htmlspecialchars()`**, damit fremde Daten Ihre Seite nicht
  zerlegen? (Beispiel 6 zeigt es)
- **Sinnvoller Umgang mit fehlenden Feldern?** (Regel 4)

---

## 12. Weiterlesen

- [Übersicht mit allen Endpunkten und Regeln](../oauth2-beispiel.md) — die
  kurze Fassung für Entwickler
- [API-Dokumentation von nu](https://schachde-apps.liga.nu/dsbwertungsportal/apidocs/)
  — alle Parameter und Felder im Einzelnen
- Die Datei `DsbOAuth2Client.php` selbst: Jede Methode ist ausführlich
  kommentiert, einschließlich der Gründe, warum sie so und nicht anders
  arbeitet

Fragen zum Zugang beantwortet der Deutsche Schachbund. Fehler in diesen
Beispielen dürfen Sie gern melden.
