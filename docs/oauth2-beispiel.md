# Beispielskript: OAuth2-Zugriff auf das DSB-Wertungsportal

Ein eigenständiges PHP-Beispiel für Vereine, Verbände und Programmierer, die
Daten des DWZ-Systems in ihre eigene Anwendung holen wollen. Es liegt in
[`docs/oauth2-beispiel/`](oauth2-beispiel/) und gehört **nicht** zum Bundle —
es läuft ohne Contao, ohne Composer und ohne dieses Repository.

Gedacht ist es zur Veröffentlichung auf der DSB-Website: Der Ordner kann als
Ganzes weitergegeben werden.

## Was drin ist

| Datei | Zweck |
|---|---|
| `DsbOAuth2Client.php` | Die Klasse. Eine Datei, keine Abhängigkeiten |
| `zugangsdaten.beispiel.php` | Vorlage für Client-ID und Client Secret |
| `start.php` | Gemeinsamer Anfang der Beispiele (Zugangsdaten lesen, Clients bauen) |
| `beispiel-1-turniere.php` | Turniere suchen, mit Filtern |
| `beispiel-2-turnier.php` | Ein Turnier: Kopfdaten, DWZ-Auswertung, Partien |
| `beispiel-3-spieler.php` | Spieler suchen und seine DWZ-Geschichte ausgeben |
| `beispiel-4-dwzliste.php` | DWZ-Liste seitenweise durchgehen |
| `beispiel-5-download.php` | Die DWZ-Liste als Zip-Datei holen |

Voraussetzungen: **PHP ab 7.4** mit den Erweiterungen `curl` und `json`, für
die Zip-Dateien zusätzlich `zip`.

## Loslegen

1. Eine API-Freischaltung beim Deutschen Schachbund beantragen. Sie bekommen
   eine E-Mail mit einem Link, legen dort ein Passwort fest — das ist Ihr
   **Client Secret** — und erhalten danach Ihre **Client-ID**.
2. `zugangsdaten.beispiel.php` nach `zugangsdaten.php` kopieren und beides
   eintragen. Diese Datei gehört nicht in die Versionsverwaltung und nicht in
   ein Verzeichnis, das der Webserver ausliefert.
3. Ein Beispiel starten:

```bash
php beispiel-1-turniere.php
```

In einer eigenen Anwendung genügt:

```php
require __DIR__.'/DsbOAuth2Client.php';

$client = new DsbOAuth2Client(CLIENT_ID, CLIENT_SECRET, DsbOAuth2Client::SCOPE_TURNIERE);

$antwort = $client->get('/dwz/tournaments', array('ratingState' => 'RATED', 'limit' => 10));

foreach ($antwort['data'] as $turnier) {
	echo $turnier['enddate'], '  ', $turnier['label'], "\n";
}
```

## Was die Klasse abnimmt

Die Anmeldung selbst ist in zwanzig Zeilen geschrieben. Was Mühe macht, ist der
Dauerbetrieb — und genau dafür ist die Klasse da. Die Zahlen stammen aus der
Schnittstellenanleitung von nu (Stand September 2026):

| Vorgabe | Wert | Was die Klasse tut |
|---|---|---|
| Lebensdauer eines Access Tokens | 5 Minuten | Hinterlegt es in einer Datei und erneuert, sobald weniger als 30 Sekunden bleiben |
| Neue Token je Client-ID | höchstens **5 in 30 Minuten** | Holt genau eines und erneuert danach nur noch über das Refresh-Token |
| Refresh-Token | nur der **jüngste** ist einlösbar | Schreibt bei jeder Erneuerung den neuen mit; erneuert unter einer Dateisperre, damit nicht zwei Vorgänge zugleich erneuern |
| Abgelaufene Autorisierung | HTTP 401 | Erneuert einmal und wiederholt den Abruf — auch beim Zip-Download |
| Abgewiesener Tokenabruf | HTTP 403 | Wartet 30 Minuten, statt weiter anzufragen |
| Parameter des Tokenabrufs | im Body | `application/x-www-form-urlencoded`, wie RFC 6749 es verlangt |
| Scope | in jeder Anfrage | Auch beim Erneuern |

Dazu kommen Kleinigkeiten, die sich im Betrieb als wichtig erwiesen haben: ein
zweiter Versuch bei abgerissener Verbindung, eingeschaltete Zertifikatsprüfung,
Downloads erst in eine Zwischendatei und die Prüfung des Zip-Archivs auf
Vollständigkeit.

## Die Fallstricke

**Die Tokendatei ist der wichtigste Punkt der Einrichtung.** Sie muß für jeden
Weg schreibbar sein, der die Schnittstelle benutzt — Webserver *und* Cronjob.
Läuft beides unter verschiedenen Benutzern und sieht jeder eine andere Datei,
holt sich jeder Weg ein eigenes Token, und nach wenigen Minuten ist das
Kontingent erschöpft. Das Systemverzeichnis für temporäre Dateien ist dafür ein
schlechter Ort: Es gehört nicht der Anwendung, ein Aufräumer kann jederzeit
dazwischenfahren, und manche Dienste bekommen sogar ein eigenes zu sehen
(systemd `PrivateTmp`). Besser ein eigenes Verzeichnis im Projekt.

**Ein HTTP 403 beim Tokenabruf sagt nicht, woran es liegt.** nu antwortet mit
einer Sammelmeldung für drei Ursachen: keine Freischaltung, erschöpftes
Kontingent oder falscher Scope. Deshalb wartet die Klasse danach eine halbe
Stunde — so lang ist das Fenster des Kontingents. Wer in dieser Zeit
weiterprobiert, verlängert das Problem nur.

**Zwei Bereiche, zwei Scopes.** Turniere und Personen brauchen
`dsb_tournament`, die DWZ-Liste `dwz_liste`. Ein Token gilt nur für die Scopes,
mit denen es geholt wurde. Deckt **eine** Kennung beides ab, können beide in
einer Anfrage stehen (`dsb_tournament dwz_liste`) — aber nur, wenn ihr wirklich
beide zugeteilt sind; sonst wird die ganze Anfrage abgelehnt, und auch die
Turniere stehen still. Sind es zwei Kennungen, braucht jede ihre eigene
Tokendatei.

Die Endpunkte der DWZ-Liste (`/dwz/dwzliste/…`) sind zur Zeit noch **ohne
Anmeldung** erreichbar. nu hat angekündigt, das umzustellen, und empfiehlt,
schon jetzt ein Token mitzusenden.

**Nicht jedes Feld ist immer da.** Die Antworten lassen Felder einfach weg,
statt sie auf 0 zu setzen: Ein Spieler ohne DWZ hat kein `rating` und kein
`index`, ein nie ausgewertetes Turnier kein `lastCalculated`. Immer mit `??`
absichern, nie auf einen Zahlenwert 0 prüfen.

**Nichtmitglieder bekommen keine neue DWZ.** In einer Auswertung stehen bei
ihnen (`member: false`) trotzdem Werte in `ratingNew` — die sind nach
Wertungsordnung 3.4.3 nicht gültig und dürfen nicht als DWZ angezeigt werden.

**Umstufungen können ganz ohne Werte kommen.** In der Turnierhistorie steht bei
einem Spieler ohne DWZ die jährliche Umstufung als `{"referenceDate": "…",
"name": "Umstufung 2026"}` — ohne Wertungsangabe. Wer sie ungeprüft anzeigt,
bekommt eine leere Zeile. Beispiel 3 zeigt, wie man sie aussortiert.

**Bei langen Listen seitenweise abrufen.** `limit` und `offset` steuern den
Abschnitt, `metadata.totalCount` nennt die Gesamtzahl. `alleSeiten()` erledigt
das und liefert die Datensätze einzeln, so daß immer nur eine Seite im Speicher
steht. Für ganze Landesverbände ist die Zip-Datei aber der bessere Weg.

## Die Endpunkte

Alles relativ zu `https://schachde-apps.liga.nu/dsbwertungsportal/rs`.

| Pfad | Scope | Inhalt |
|---|---|---|
| `/dwz/tournaments` | `dsb_tournament` | Turnierliste, filterbar nach Name, VKZ, Zeitraum, Ort, Status |
| `/dwz/tournaments/{uuid}` | `dsb_tournament` | Ein Turnier samt Teilnehmer- und Partienzahl |
| `/dwz/tournaments/{uuid}/evaluation` | `dsb_tournament` | DWZ-Auswertung |
| `/dwz/tournaments/{uuid}/matches` | `dsb_tournament` | Partien, filterbar nach Runde |
| `/dwz/tournaments/{t}/players/{p}/scoresheet` | `dsb_tournament` | Spielerkarte eines Teilnehmers |
| `/dwz/persons/{nuLigaId}/history` | `dsb_tournament` | Turnierhistorie samt Umstufungen |
| `/dwz/dwzliste/persons` | `dwz_liste` | Spielersuche mit aktueller DWZ |
| `/dwz/dwzliste/persons/{nuLigaId}` | `dwz_liste` | Ein Spieler |
| `/dwz/dwzliste/clubs` | `dwz_liste` | Vereine |
| `/dwz/dwzliste/federations` | `dwz_liste` | Verbände |
| `/dwz/dwzliste/download/{datei}` | `dwz_liste` | DWZ-Liste als Zip (`LV-0-dwzliste.zip` = alles, `LV-1` bis `LV-8` je Landesverband) |

Die vollständige Beschreibung aller Parameter steht in der API-Dokumentation:
<https://schachde-apps.liga.nu/dsbwertungsportal/apidocs/>

## Wenn etwas nicht geht

| Meldung | Bedeutung |
|---|---|
| `Bad client credentials` (401) | Client-ID oder Client Secret stimmen nicht |
| `Wrong or no scope(s) provided` (403) | Der Kennung ist der angeforderte Scope nicht zugeteilt — oder es ist einer der beiden anderen Fälle, siehe oben |
| `Too much access tokens` (403) | Zu viele neue Token. Eine halbe Stunde warten und prüfen, ob die Tokendatei wirklich geschrieben wird |
| `Refresh Token already used or invalid` (400) | Das Refresh-Token war nicht mehr das jüngste. Die Klasse fordert dann selbständig ein neues Token an |
| `HTTP 401` beim Abruf | Token abgelaufen oder widerrufen. Die Klasse erneuert und wiederholt einmal |

Eine Warnung „Die Tokendatei läßt sich nicht schreiben" sollte man nie
übergehen — sie ist die Vorstufe zum erschöpften Kontingent.

## Verhältnis zu diesem Bundle

Das Bundle macht dasselbe, nur eingebettet in Contao: `src/Helper/OAuth2Client.php`
kann zwei Kennungen führen, schreibt ein Tokenprotokoll, fällt bei Störungen auf
den Zwischenspeicher und die Spiegeltabellen zurück und hängt an den
Contao-Einstellungen. Siehe [`zugang.md`](zugang.md).

Das Beispiel ist die entkernte Fassung davon: dieselben Regeln, keine
Abhängigkeiten. Wer beides ändert, sollte beides ändern.
