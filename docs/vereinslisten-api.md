# Vereinslisten-Schnittstelle

Die Schnittstelle liefert die Mitgliederliste eines Vereins als JSON. Sie ist
für Vereinswebsites gedacht, die ihre Spielerliste selbst ausgeben wollen.

Gegenüber einem unmittelbaren Zugriff auf die nu-Schnittstelle des
Wertungsportals hat sie zwei Vorteile: Die FIDE-Daten (Elo, Titel, Nation) sind
hier aktuell, weil sie beim Abruf aus der eigenen Elo-Tabelle ergänzt werden,
und die Antworten kommen aus dem Zwischenspeicher, belasten die nu-Schnittstelle
also nicht bei jedem Aufruf.

## Für Vereine: so wird die Liste abgerufen

### 1. Zugangsschlüssel anfordern

Über das Registrierungsformular auf der Website (Frontend-Modul
„Schnittstellen-Registrierung"). Abgefragt werden die fünfstellige
Vereinskennziffer, Vor- und Nachname sowie eine E-Mail-Adresse. Der Schlüssel
kommt an diese Adresse — zusammen mit einem PHP-Beispielskript, das sich
unverändert übernehmen läßt.

Der Schlüssel gilt für **genau einen Verein**. Wer Listen mehrerer Vereine
braucht, fordert je Verein einen eigenen Schlüssel an.

### 2. Aufruf

```
https://<Website>/wertungsportal-api/vereinsliste?token=<Schlüssel>&vkz=<VKZ>
```

Beide Parameter sind Pflicht. Erlaubt ist nur GET.

### 3. Antwort

```json
{
  "vkz": "A0001",
  "verein": "SC Musterstadt",
  "quelle": "cache",
  "stand": "2026-08-03T11:20:00+02:00",
  "anzahl": 2,
  "spieler": [
    {
      "id": "1234567",
      "nachname": "Bauer",
      "vorname": "Bernd",
      "geburtsjahr": "1980",
      "geschlecht": "M",
      "mitgliedsnummer": "77",
      "status": "A",
      "dwz": 1899,
      "dwzIndex": 12,
      "letzteAuswertung": "2026-20",
      "fideId": 4611111,
      "elo": 1950,
      "titel": "FM",
      "nation": "GER"
    }
  ]
}
```

| Feld | Bedeutung |
|---|---|
| `id` | Personenkennung des Wertungsportals (nuLigaPersonId) |
| `geschlecht` | `M`, `W`, `D` oder leer |
| `status` | `A` = aktiv, `P` = passiv; andere Werte der Schnittstelle werden unverändert durchgereicht |
| `dwz` / `dwzIndex` | DWZ und Index, `null` wenn keine DWZ vorliegt |
| `letzteAuswertung` | Kalenderwoche der letzten Auswertung (JJJJ-WW) |
| `elo` / `titel` / `nation` | FIDE-Daten aus der örtlichen Elo-Tabelle |
| `quelle` | `api`, `cache` oder `lokal` — woher die Daten stammen |

Die Liste ist nach Nachname und Vorname sortiert (umlautsicher: Ä wird wie Ae
einsortiert). Mitgliedschaften in **anderen** Vereinen erscheinen nicht;
Personen, die im Backend gesperrt sind, fehlen in der Liste.

### 4. Fehler

| Status | Bedeutung |
|---|---|
| 400 | Parameter fehlen oder die VKZ hat nicht fünf Zeichen |
| 403 | Schlüssel unbekannt, gesperrt oder für einen anderen Verein ausgestellt; IP gesperrt |
| 429 | Zu viele Anfragen: Tageskontingent des Schlüssels erschöpft, oder mehr als 120 Anfragen je Stunde von derselben IP-Adresse |
| 502 | Der Verein ist unbekannt oder die Daten sind zurzeit nicht abrufbar |

Der Rumpf enthält dann `{"fehler": true, "meldung": "..."}`.

### Wenn statt JSON etwas anderes kommt

Kommt eine HTML-Seite zurück — häufig mit **HTTP 401 und der Überschrift „Bot
check"** —, dann hat nicht die Schnittstelle geantwortet, sondern ein
Schutzmechanismus des Webservers davor. Solche Sperren lassen einen Browser
durch (er löst die JavaScript-Aufgabe und bekommt ein Cookie), einen Aufruf
von Server zu Server aber nicht — und genau der ist hier der Normalfall.

Erkennungsmerkmal: **Im Backend unter WP | Zugangsschlüssel → Zugriffe steht
zu diesen Versuchen nichts.** Wäre die Anfrage bei Contao angekommen, hätte
sie eine Zeile hinterlassen, und sei es eine abgewiesene.

Abhilfe gibt es nur auf der Serverseite: Der Pfad `/wertungsportal-api/` muß
von der Bot-Sperre ausgenommen werden. Am Bundle liegt es nicht, die Anfrage
erreicht es gar nicht.

**So findet man heraus, wo die Sperre sitzt.** Ohne Browser abrufen und
vergleichen:

| Abruf | Sperre in der `.htaccess` | Sperre auf PHP-Ebene |
|---|---|---|
| `/robots.txt`, `/favicon.ico` (statisch) | betroffen oder nicht, je nach Regel | kommt durch |
| `/impressum.html` (normale Seite) | je nach Regel | abgewiesen |
| `/contao` (Backend) | in aller Regel ausgenommen | abgewiesen |

Werden **alle** statischen Dateien ausgeliefert, aber **jede** Adresse
abgewiesen, die durch PHP läuft — bis hin zum Backend und zu nicht
vorhandenen Seiten —, dann steht die Sperre nicht im Webserver, sondern vor
der Anwendung. Häufig richtet der Hoster so etwas über `auto_prepend_file`
ein. Nachsehen läßt sich das mit einer kleinen Datei im Webverzeichnis, die
man **im Browser** aufruft (dort löst sich die Sperre selbst auf):

```php
<?php echo ini_get('auto_prepend_file') ?: 'nichts eingetragen';
```

Steht dort ein Pfad, kommt die Sperre von dort — und nur dort (oder beim
Hoster) läßt sie sich für `/wertungsportal-api/` abschalten. Kennzeichen
dieser Bauart: Jede PHP-Adresse beantwortet zusätzlich `?create_challenge`
mit einer JSON-Rechenaufgabe.

### Bitte beachten

* **Ein Abruf am Tag genügt.** Die Daten ändern sich seltener, und die Antwort
  kommt ohnehin aus dem Zwischenspeicher.
* **Der Schlüssel gehört nicht in öffentlich lesbaren Quelltext** — also nicht
  in Javascript auf der eigenen Seite. Der Abruf gehört auf den Server
  (PHP-Skript), das Ergebnis auf die Seite.

## Für Betreiber: Einrichtung und Verwaltung

### Seite und Modul

Das Frontend-Modul **Schnittstellen-Registrierung**
(`wertungsportal_token`) auf eine Seite legen; die Adresse der Schnittstelle
selbst braucht keine Contao-Seite, sie liegt fest unter
`/wertungsportal-api/vereinsliste` (Route aus `Resources/config/routing.yml`).

### Einstellungen (System → Einstellungen, Bereich Wertungsportal)

| Einstellung | Wirkung |
|---|---|
| **Absenderadresse** | Absender der Schlüssel-E-Mail. Ohne Eintrag die Adresse des Administrators. Sie muß zur Domain der Website passen, sonst stufen viele Postfächer die Nachricht als Fälschung ein. |
| **Absendername** | Name, der beim Empfänger als Absender erscheint. Ohne Eintrag der Name der Website. |
| **Vorlage der Schlüssel-E-Mail** | HTML-Vorlage der Nachricht (siehe unten). Ohne Auswahl geht sie als reiner Text hinaus. |
| **Erlaubte Abrufe je Tag** | Höchstzahl je Schlüssel und Tag; gezählt werden nur erfolgreiche Abrufe. Ohne Eintrag 24, eine `0` hebt die Grenze auf. |
| **Neue Schlüssel erst nach Freigabe** | Angeforderte Schlüssel werden unveröffentlicht angelegt und liefern erst nach Freischaltung Daten. Die Bestätigungsmail weist darauf hin. |
| **Gesperrte IP-Adressen** | Eine Adresse je Zeile, Zeilen mit `#` sind Kommentare. Anfragen von dort werden abgewiesen. |

### Die Schlüssel-E-Mail anpassen

Die Nachricht geht immer zweiteilig hinaus: ein HTML-Teil aus der gewählten
Vorlage und ein Textteil als Rückfallebene. Der Textteil steckt im Code
(`Classes\TokenRegistrierung::mailtext()`), der HTML-Teil in der Vorlage.

Eine eigene Fassung legt man als Kopie von
`templates/wp_mail_token.html5` an — der Dateiname muß mit `wp_mail_token`
beginnen, sonst erscheint sie nicht in der Auswahlliste. Verfügbare
Platzhalter:

| Platzhalter | Inhalt |
|---|---|
| `$this->token` | Zugangsschlüssel |
| `$this->vkz` | Vereinskennziffer |
| `$this->verein` | Name des Vereins (leer, wenn unbekannt) |
| `$this->adresse` | Adresse der Schnittstelle ohne Parameter |
| `$this->aufruf` | Fertiger Aufruf: Adresse + Schlüssel + VKZ |
| `$this->email` | E-Mail-Adresse des Antragstellers |
| `$this->vorname` / `$this->nachname` | Name des Antragstellers |
| `$this->name` | Vor- und Nachname zusammen, für die Anrede |
| `$this->abrufe` | Erlaubte Abrufe je Tag als Zahl (0 = unbegrenzt) |
| `$this->abrufetext` | Derselbe Wert als Satzbaustein: „12 Abrufe" bzw. „beliebig viele Abrufe" |
| `$this->beispiel` | Vollständiges PHP-Beispielskript mit eingesetzten Werten |
| `$this->freigabe` | `true`, wenn der Schlüssel noch freigeschaltet werden muß |
| `$this->absender` | Absendername aus den Einstellungen |

Alle Werte sind **roh** — im HTML also durch `StringUtil::specialchars()`
schicken, wie in der mitgelieferten Vorlage. Sie stammen aus einem Formular,
das im offenen Internet steht.

Ohne Freigabepflicht bekommt jeder, der eine Vereinskennziffer kennt, sofort
Zugriff auf die Mitgliederliste dieses Vereins. Das ist so gewollt (der Abruf
ist niederschwellig), sollte aber eine bewußte Entscheidung sein.

### Backend-Modul „WP | Zugangsschlüssel"

* **Liste der Schlüssel** mit Verein, Inhaber, Zugriffszahl und letztem
  Zugriff. Gesperrte Schlüssel stehen durchgestrichen.
* **Sperren** geschieht am Datensatz (Haken „Schlüssel sperren" plus Grund).
  Der Schlüssel bleibt erhalten, Anfragen werden mit HTTP 403 abgewiesen.
  Alternativ läßt sich der Schlüssel über das Auge-Symbol unveröffentlichen —
  das wirkt genauso.
* **Zugriffe** (Symbol „Bearbeiten") zeigt je Anfrage eine Zeile mit Zeitpunkt,
  Quelle, Trefferzahl, Dauer und IP-Adresse.
* **Alte Zugriffe löschen** entfernt alles, was älter als 90 Tage ist.

### Auswertung

Im Modul **WP | Statistik** steht unter „Vereinslisten-Schnittstelle" für den
gewählten Zeitraum:

* Zugriffe je Schlüssel und Verein, getrennt nach Anfragen insgesamt und
  erfolgreichen Anfragen
* abgewiesene Anfragen je IP-Adresse samt Grund — die Grundlage für die
  Sperrliste in den Einstellungen

### Bremsen

| Grenze | Wert | Konstante |
|---|---|---|
| Abrufe je Schlüssel und Tag | Einstellung, Vorgabe 24 | `Helper\VereinslisteApi::ABRUFE_JE_TAG` |
| Anfragen an die Schnittstelle | 120 je Stunde und IP | `Helper\VereinslisteApi::ANFRAGEN_JE_STUNDE` |
| Schlüsselanforderungen | 5 je Tag, je E-Mail-Adresse und je IP | `Classes\TokenRegistrierung::ANFORDERUNGEN_JE_TAG` |
| Aufbewahrung der Zugriffe | 90 Tage | `Models\WertungsportalTokensAccessModel::AUFBEWAHRUNG` |

Das Registrierungsformular hat außerdem ein für Besucher unsichtbares Feld
(Honigtopf); wird es ausgefüllt, bricht die Verarbeitung ab.

### Datenschutz

Zwei Stellen speichern IP-Adressen, beide zur Erkennung von Mißbrauch:

* `tl_wertungsportal_tokens_access.ip` — je Anfrage eine Zeile, Aufbewahrung
  90 Tage (Operation „Alte Zugriffe löschen")
* `tl_wertungsportal_tokens.ip` — die Adresse, von der ein Schlüssel
  angefordert wurde

Beides gehört in die Datenschutzerklärung. Die Mitgliederliste selbst enthält
Namen, Geburtsjahre und Mitgliedsnummern — sie wird nur mit gültigem Schlüssel
und nur für den zugehörigen Verein herausgegeben.

## Technischer Aufbau

| Datei | Aufgabe |
|---|---|
| `Resources/config/routing.yml` | Route `/wertungsportal-api/vereinsliste` |
| `ContaoManager/Plugin.php` | lädt die Route (`RoutingPluginInterface`) |
| `Controller/VereinslisteController.php` | startet das Framework, verpackt die Antwort als JSON |
| `Helper/VereinslisteApi.php` | Prüfungen, Datenbeschaffung, Aufbereitung, Protokoll |
| `Classes/TokenRegistrierung.php` | Frontend-Modul samt Schlüssel-E-Mail und Beispielskript |
| `Classes/TokenVerwaltung.php` | Rückrufe des Backend-Moduls |
| `Models/WertungsportalTokensModel.php` | Schlüssel, Erzeugung und Bremse |
| `Models/WertungsportalTokensAccessModel.php` | Zugriffe, Auswertung, Aufräumen |

Die Daten holt die Schnittstelle über `Helper\API::autoQuery()` — also über
denselben Weg wie das Frontend: gültiger Zwischenspeicher, dann nu, im Notfall
der örtliche Datenbestand. Ein eigener Zwischenspeicher entsteht dadurch nicht.

**Beim Deployment:** `contao:migrate` (zwei neue Tabellen) und
`contao:assets:install` (geänderte `backend.css`). Da eine neue Route
hinzukommt, muß außerdem der Produktions-Cache neu gebaut werden.
