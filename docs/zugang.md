# Zugang zur Schnittstelle: zwei Kennungen

Die Schnittstelle von nu verlangt eine Anmeldung per OAuth2 (Client-Credentials
mit Refresh-Token). Lange galt das nur für Turniere und Personen; die
**DWZ-Liste** (`/dwz/dwzliste/…`) war frei abrufbar. Im September 2026 hat nu
angekündigt, auch sie zu schützen, und dem DSB dafür eine **eigene Kennung**
gegeben. Seit Fassung 1.46.0 kann das Bundle beide Kennungen führen.

Stand 22.09.2026: nu liefert die DWZ-Liste noch ohne Anmeldung aus. Das Bundle
funktioniert in beiden Zuständen — mit und ohne eingetragene Zugangsdaten,
vor und nach der Umstellung bei nu.

## Wer was braucht

| | Turniere und Personen | DWZ-Liste |
|---|---|---|
| Adressen | `/dwz/tournaments/…`, `/dwz/persons/…` | `/dwz/dwzliste/…` |
| Im Frontend | Turniersuche, Auswertung, Ergebnisse, Spielberichtsbogen, Turnierhistorie der Karteikarte | Spielersuche, Karteikarte, Vereins- und Verbandslisten, Ranglisten, Vereinslisten-Schnittstelle |
| Konsolenbefehle | Vorladen der Turniere | Vorladen der Karteikarten, `wertungsportal:download`, `wertungsportal:converter` |
| Einstellungen | Client-ID, Client Secret, Scope (`dsb_tournament`) | Client-ID, Client Secret; Scope `dwz_liste` (Vorgabe, Feld kann leer bleiben) |
| Tokendatei | `system/tmp/wertungsportal-token.json` | `system/tmp/wertungsportal-token-dwzliste.json` |
| Tokenprotokoll | `var/logs/wertungsportal-token-JJJJ-MM.log` | `var/logs/wertungsportal-token-dwzliste-JJJJ-MM.log` |

Basis- und Token-Adresse gelten für beide Kennungen — sie stammen aus demselben
Portal von nu.

**Warum getrennt?** nu gibt je Kennung nur eine begrenzte Zahl Token aus
(„Too much access tokens", siehe [Vorladen](vorladen.md)). Jede Kennung braucht
deshalb ihr eigenes Token, ihre eigene Wartezeit nach einem Fehlschlag und ihre
eigene Zählung — ein Engpass der einen darf die andere nicht mitreißen, und wer
mit nu über das Kontingent spricht, braucht die Zahl je Kennung.

## Einrichten

1. Im Backend unter **Wertungsportal → Einstellungen**, Gruppe **„Zugang zur
   DWZ-Liste"**, Client-ID und Client Secret eintragen. Das Feld **Scope bleibt
   leer** — dann fordert das Bundle `dwz_liste` an, so wie nu es verlangt
   (siehe unten).
2. Prüfen, auf der Kommandozeile:

   ```bash
   php vendor/bin/contao-console wertungsportal:token --pruefen
   ```

   Das ruft je Kennung einen Endpunkt ab (kostet im ungünstigen Fall je ein
   Token) und sagt, auf welchem Weg die DWZ-Liste lief — mit ihrem Token, mit
   dem gemeinsamen oder ohne Anmeldung. Kam sie **trotz eingetragener
   Zugangsdaten ohne Token**, meldet der Befehl das als Warnung samt Grund von
   nu: Die Zugangsdaten stimmen dann nicht, und das fiele sonst erst auf, wenn
   nu umstellt. Ohne `--pruefen` zeigt der Befehl nur den Zustand beider
   Tokendateien und fragt nichts an.

   Ohne Kommandozeile: Nach dem Speichern eine Seite mit Spielersuche oder
   Karteikarte aufrufen und unter **System → Systemprotokoll** nachsehen. Ein
   gescheiterter Tokenabruf steht dort als „Zugangstoken (DWZ-Liste) nicht zu
   bekommen — …". Das Backend-Modul **Rohdaten** taugt für diese Prüfung erst,
   wenn nu die Anmeldung verlangt — bis dahin kommt die Datei auch ohne gültiges
   Token.

**Die Zugangsdaten gehören nur in die Einstellungen** (sie landen in der
`system/config/localconfig.php`) — nicht in den Code, nicht in die Doku, nicht
in ein Ticket. Die Prüfungen des Bundles kommen ohne sie aus.

Trägt man bei der DWZ-Liste **dieselbe Client-ID** ein wie bei Turnieren und
Personen, benutzt das Bundle ein gemeinsames Token. Zwei Token-Familien
derselben Kennung würden das Kontingent doppelt belasten.

## Was in welchem Zustand passiert

| Zugangsdaten der DWZ-Liste | nu liefert noch frei aus | nu verlangt die Anmeldung |
|---|---|---|
| nicht eingetragen | Abruf ohne Anmeldung, wie bis 1.45.1 | **Notbetrieb** mit Hinweis, im Systemprotokoll: „Die DWZ-Liste verlangt eine Anmeldung (HTTP 401), … keine Zugangsdaten eingetragen" |
| eingetragen und gültig | Abruf mit Token | Abruf mit Token |
| eingetragen, aber kein Token zu bekommen (falsches Geheimnis, falscher Scope, Kontingent) | Abruf **ohne** Token — die Besucher merken nichts; im Systemprotokoll steht der Tokenfehler, und für 300 Sekunden wird kein weiteres Token angefragt | **Notbetrieb** mit Hinweis, im Systemprotokoll der Grund von nu |

„Notbetrieb" heißt wie bei jedem Tokenfehler: Die Besucher bekommen die Daten
aus dem Zwischenspeicher oder dem örtlichen Bestand samt Hinweis auf deren
Alter — keine Fehlerseite.

Der Abruf ohne Token nach einem gescheiterten Tokenabruf ist eine
**Übergangsregel**: Er hält die Website am Laufen, falls die Zugangsdaten noch
nicht stimmen, solange nu die Liste frei ausliefert. Nach der Umstellung kostet
er je Abruf eine kurze, vergebliche Anfrage und schadet sonst nicht.

## Der Scope

Für die Turniere verlangt nu den Scope `dsb_tournament`, für die DWZ-Liste
**`dwz_liste`** (Auskunft des nu-Supports; in der ersten Mitteilung an die
Umsysteme fehlte sie, nachgereicht am 23.09.2026). Ohne den richtigen Scope gibt
der Token-Endpunkt kein Token aus, sondern meldet „Wrong or no scope(s)
provided".

Das Bundle fordert `dwz_liste` **von sich aus** an, solange im Feld „Scope der
DWZ-Liste" nichts steht (`OAuth2Client::SCOPE_DWZLISTE`). Ein Eintrag geht vor —
nennt nu einer anderen Anlage einen anderen Scope, trägt sie ihn dort ein. Einen
**leeren** Scope schickt das Bundle nicht mehr mit (`scope=`, so bis 1.45.1);
steht bei einem Zugang ohne Vorgabe nichts, bleibt der Parameter ganz weg, und
der Server nimmt nach RFC 6749 (Abschnitt 3.3) den der Kennung zugedachten.

Der Token-Endpunkt ist für beide Kennungen derselbe:
`https://schachde-portal.liga.nu/rs/auth/token`.

Der Scope geht **in jede Tokenanfrage** mit — auch in die Erneuerung über das
Refresh-Token. So führt ihn die Anleitung von nu auf. Bis 1.46.1 ging er nur
beim ersten Abruf mit; nu hat das hingenommen, zugesichert ist der
Geltungsbereich eines so erneuerten Tokens aber nicht.

`wertungsportal:token` zeigt in der Zeile „Scope", was tatsächlich angefordert
wird, und vermerkt, wenn es die Vorgabe ist.

### Eine Kennung für beides

Steht in beiden Feldern dieselbe Client-ID, holt das Bundle **ein** gemeinsames
Token — zwei Token-Familien derselben Kennung würden sich gegenseitig die
Refresh-Token entwerten. Geholt wird es mit dem Scope des Turnierzugangs, und
ein Token gilt nur für die Scopes, mit denen es geholt wurde. Dort gehören dann
**beide** hinein, durch ein Leerzeichen getrennt:

```
dsb_tournament dwz_liste
```

Das erlaubt OAuth 2.0 ausdrücklich, aber ein Token bekommt nur, wer für **alle**
angeforderten Scopes freigeschaltet ist — sonst wird die ganze Anfrage
abgelehnt, und damit stünde auch der Turnierzugang still. Ist die Kennung nicht
für beides freigeschaltet, bleibt der Weg über zwei getrennte Kennungen.
`wertungsportal:token` warnt, wenn der gemeinsame Scope `dwz_liste` nicht
abdeckt.

## Die Zip-Downloads

`wertungsportal:download` und `wertungsportal:converter` holen die Zip-Dateien
unter `/dwz/dwzliste/download/…` — auch sie gehören zur DWZ-Liste und bekommen
deren Token (`OAuth2Client::herunterladen()`, früher `Helper::DownloadDatei()`).

* Die Adresse folgt der **Basisadresse aus den Einstellungen**
  (`OAuth2Client::downloadAdresse()`); ist keine eingetragen, bleibt es bei der
  Produktivschnittstelle wie bisher. Bis 1.45.1 stand die Adresse fest im Code —
  mit Anmeldung ginge das nicht mehr gut, weil ein Token der Demo-Umgebung nicht
  für die Produktivschnittstelle taugt.
* Das Token wird **je Datei** erfragt. Ein Lauf über zwanzig Dateien dauert
  länger, als ein Token lebt; solange es gilt, kostet das keine Anfrage.
* Bei **HTTP 401** wird das Token einmal erneuert und der Download wiederholt —
  so sieht es die Anleitung von nu vor, denn ein Token kann widerrufen worden
  sein. Erneuert wird über das Refresh-Token (nicht über eine neue Anfrage,
  siehe unten), und zwar ohne die sonst übliche Pause. Bleibt es beim 401, wird
  gemeldet statt weiterversucht — mit Grund, etwa „für die DWZ-Liste sind aber
  keine Zugangsdaten eingetragen".
* Die **Zertifikatsprüfung** ist jetzt eingeschaltet. Bis 1.45.1 war sie bei den
  Downloads abgeschaltet; mit einem Token in der Anfrage darf sie das nicht sein.
  Die übrigen Abrufe prüfen seit jeher.

## Was nu vorgibt

Aus der Anleitung „OAuth2-Zugriff auf das DSB-Wertungsportal (DWZ-System)",
Stand September 2026. Die Zahlen stehen hier, weil das Bundle sich danach
richtet:

| Vorgabe | Wert | Was das Bundle daraus macht |
| --- | --- | --- |
| Lebensdauer eines Access Tokens | 5 Minuten | Wird erneuert, sobald weniger als 30 Sekunden bleiben. Nennt die Antwort keine Dauer, gilt `TOKEN_LEBENSDAUER` = 300 s |
| Neue Token je Kennung | höchstens **5 in 30 Minuten** | Ein Lauf kommt mit einer einzigen Anfrage aus und erneuert danach nur noch. Die Dateisperre verhindert, daß mehrere Vorgänge gleichzeitig erneuern |
| Refresh-Token | nur der **jüngste** gilt | Wird bei jeder Erneuerung mitgeschrieben. Fehlt er in einer Antwort, bleibt der bisherige stehen (RFC 6749 §6) |
| Abgelaufene Autorisierung | HTTP 401 | Token erneuern, Abruf einmal wiederholen — in `callApiIntern()` wie beim Zip-Download |
| Parameter des Tokenabrufs | im **Body**, nicht in der Adresse | `application/x-www-form-urlencoded`, so verlangt es RFC 6749 |

Abgewiesen wird ein Tokenabruf mit **HTTP 403** und immer derselben
Sammelmeldung: „1. No accesses for this client-id or 2. Too much access tokens
for the requested client-id or 3. Wrong or no scope(s) provided". Welche der
drei Ursachen vorliegt, steht nicht dabei. Deshalb wartet das Bundle danach
**30 Minuten** (`TOKENSPERRE_KONTINGENT`) statt der sonst üblichen fünf: So lang
ist das Fenster des Kontingents, und bei den beiden anderen Ursachen hilft
schnelles Nachfassen ohnehin nicht. Im Protokoll des Livesystems vom 13.08.2026
stehen dafür vier abgewiesene Abrufe in zwölf Minuten.

Wie oft die Anlage tatsächlich anfragt, steht in
`var/logs/wertungsportal-token-JJJJ-MM.log` (je Zugang eine Datei);
`wertungsportal:token --auswertung` fasst es zusammen.

## Für Entwickler

Die Weiche ist `OAuth2Client::zugangFuer($adresse)`: Sie liefert
`ZUGANG_TURNIERE`, `ZUGANG_DWZLISTE` oder `null` (ohne Anmeldung). Die
Einstellungen je Zugang stehen in `OAuth2Client::EINSTELLUNGEN`; Tokendatei,
Prozessspeicher, Wartezeit und Protokoll sind nach Zugang getrennt.
`new OAuth2Client()` ohne Angabe ist wie vor 1.46.0 der Turnierzugang, und
`callApiWithRefresh()` wählt das passende Token selbst — Aufrufer müssen nichts
wissen.

`tests/Helper/OAuth2ClientTest.php` prüft das alles gegen einen Nachbau der
Schnittstelle (`tests/Helper/NuSchnittstelleAttrappe.php`, gestartet mit dem
eingebauten PHP-Server) — mit erfundenen Kennungen, nie gegen nu.
