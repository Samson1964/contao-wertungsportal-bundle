# Reklamationen zu den DWZ-Daten

Angemeldete Mitglieder können zu den angezeigten Daten eine Reklamation
schicken — an den Wertungsreferenten, der die Daten verantwortet, oder an den
DSB. Das Formular öffnet sich in einer Lightbox und ist schon mit allem
vorbelegt, was der Empfänger zur Einordnung braucht. Jede Reklamation landet
außerdem im Backend-Modul **Wertungsportal → Reklamationen**.

## Wo der Link steht

| Ansicht | Stelle | Empfänger |
|---|---|---|
| Turnierauswertung | neben dem Auswerter im Turnierkopf | der Auswerter |
| Ergebnisliste | neben dem Auswerter | der Auswerter |
| Spielberichtsbogen | neben dem Auswerter | der Auswerter |
| DWZ-Karteikarte | neben „Zuständiger Wertungsreferent" | DSB-Admin |
| DWZ-Liste eines Vereins | unter dem Vereinskopf | DSB-Admin |
| Rangliste eines Verbandes | unter der Rangliste | die zuständigen Referenten aus **Wertungsportal → Referenten**, sonst DSB-Admin |
| Spieler-, Vereins- und Turniersuche | unter der Trefferliste | DSB-Admin |

Die Regel dahinter: Der Link steht neben dem Wertungsreferenten der Ansicht, und
an ihn geht die Nachricht. **Ist kein Name vorhanden, bekommt sie der
DSB-Admin.** Bei Turnieren gilt das, wenn nu keinen Nachnamen oder keine
gültige Adresse des Auswerters liefert.

Solange unter **Referenten** niemand eingetragen ist, gehen auch Reklamationen
zu Ranglisten an den DSB-Admin. Sobald dort Referenten stehen, bekommen sie die
Reklamationen zu „ihren" Verbänden — ohne weitere Einstellung, siehe
[Wertungsreferenten](referenten.md). Empfänger ist die **Funktions-E-Mail** des
Referenten; fehlt sie, die erste E-Mail-Adresse seiner Adresse in der
Adressverwaltung — auch dann, wenn sie dort nicht öffentlich ist; zu sehen ist
sie dabei nirgends.

Der Link erscheint nur,

- wenn ein Mitglied angemeldet ist **und** in seinem Konto eine gültige
  E-Mail-Adresse steht (an sie gehen die Antworten), und
- wenn in den Einstellungen eine Adresse für den DSB-Admin eingetragen ist.

## Einrichten

**Wertungsportal → Einstellungen → Reklamationen**

| Feld | Bedeutung |
|---|---|
| E-Mail-Adresse des DSB-Admins | Bekommt jede Reklamation als Blindkopie und ist Empfänger, wo kein Referent genannt ist. **Ohne Eintrag erscheint der Link nirgends.** |
| Name des DSB-Admins | Erscheint in Anrede und Empfängerzeile. Ohne Eintrag der Absendername. |

Absender der Nachrichten ist die Adresse unter **E-Mail-Versand →
Absenderadresse** — dieselbe wie bei der Schlüssel-E-Mail der
Vereinslisten-Schnittstelle. Sie muß zur Domain der Website passen, sonst
stufen viele Postfächer die Nachricht als Fälschung ein.

Nach dem Einspielen:

1. `contao:migrate` — legt die Tabelle `tl_wertungsportal_reklamationen` an.
2. `contao:assets:install` — kopiert Skript und Stylesheet nach `public/bundles/`.
3. Die Admin-Adresse in den Einstellungen eintragen.

## Die Nachricht

| Kopffeld | Inhalt |
|---|---|
| Von | Absenderadresse der Bundle-E-Mails |
| An | Wertungsreferent bzw. DSB-Admin |
| Bcc | DSB-Admin — außer er ist selbst Empfänger |
| Antwort an | das Mitglied, mit der Adresse aus seinem Konto |
| Betreff | vorbelegt mit „Reklamation zu …", änderbar |

Der Text ist vorbelegt mit Anrede, allen Angaben zur Ansicht (Turnier,
Zeitraum, Turniercode, Spieler mit NU-Nummer, Verein, Rangliste …), der
Adresse der Seite und dem Gruß. Die Schreibmarke steht beim Öffnen unter der
Zeile „Was stimmt nicht?". Das Mitglied darf alles überschreiben.

Über dem Formular nennt der Dialog den Empfänger und die Antwortadresse. Der
Satz „Eine Kopie erhält der Deutsche Schachbund" steht nur da, wenn wirklich
eine Blindkopie geht — also nicht, wenn die Reklamation ohnehin an den
DSB-Admin gerichtet ist.

Angehängt wird eine **Fußzeile**, die das Mitglied weder sieht noch ändern
kann: Name, Adresse und Mitgliedsnummer aus dem Konto, die Seite, Datum und
Uhrzeit. Weil Betreff und Text frei sind, ist sie der Teil, auf den sich der
Empfänger verlassen kann.

## Das Backend-Modul

**Wertungsportal → Reklamationen** zeigt jede Reklamation mit Datum, Absender,
Empfänger (mit „(Admin)", wenn der DSB-Admin einsprang), Betreff und Stand.
Gescheiterte Sendungen stehen rot als „nicht verschickt" da — mit der Meldung
des Mailservers im Tooltip. Sie werden trotzdem gespeichert, damit niemand
verlorengeht.

Bearbeitbar sind nur **Erledigt** und eine **Notiz**. Alles andere ist die
Aufzeichnung dessen, was verschickt wurde; die Operation „Details" zeigt sie
vollständig. Filter nach Ansicht, Empfängerart, Versand und Stand; Suche über
Namen, Betreff, Text, Turnier und NU-Nummer.

**Datenschutz:** Die Zeilen enthalten Namen und Adressen von Mitgliedern und
Referenten. Erledigte Reklamationen sollten nicht unbegrenzt liegen bleiben.

## Sicherheit

- **Der Empfänger kommt nie aus dem Formular.** Er steht beim Anzeigen der Seite
  fest und reist signiert durch den Browser (HMAC-SHA256 mit dem Kernel-Secret).
  Wer ihn verändert, bekommt „Das Formular ist ungültig". Sonst ließe sich über
  das Formular jede beliebige Adresse anschreiben — mit der Absenderadresse des
  DSB.
- **Nur angemeldete Mitglieder.** Der Controller prüft die Anmeldung selbst; der
  ausgeblendete Link allein wäre kein Schutz, die Adresse ist öffentlich.
- **Anfragetoken.** Die Route läuft mit Contaos Tokenprüfung; ein Absenden von
  fremden Seiten scheitert daran.
- **Bremse:** höchstens 5 Reklamationen je Mitglied und Stunde
  (`Reklamation::HOECHSTZAHL`, `ZEITFENSTER`).
- **Kopfzeilen:** Zeilenumbrüche im Betreff werden zu Leerzeichen — sonst ließen
  sich weitere Kopfzeilen („Bcc: …") unterschieben. Namen verlieren `<>",;`.
- Die Klartextadresse eines Referenten steht nur im signierten Kontext, und den
  bekommen nur angemeldete Mitglieder zu sehen.

## Für Entwickler

| Teil | Datei |
|---|---|
| Logik (Kontexte, Empfänger, Texte, Signatur, Versand, Speichern) | `src/Helper/Reklamation.php` |
| Route `POST /wertungsportal-api/reklamation` | `src/Controller/ReklamationController.php`, `config/routing.yml` |
| Lightbox | `public/js/reklamation.js` (natives `<dialog>`, ohne Bibliothek), Stile am Ende von `public/css/default.css` |
| Backend-Modul | `dca/tl_wertungsportal_reklamationen.php`, `Classes/Reklamationen.php`, `Models/WertungsportalReklamationenModel.php` |

Eine neue Ansicht bekommt ihren Link mit zwei Aufrufen im Modul:

```php
$this->Template->reklamation = Reklamation::link(Reklamation::fuerVerein($name, $vkz, (string) \Contao\Environment::get('uri')));
```

und im Template `<?= $this->reklamation ?>` an der Stelle des Referenten. Für
Ansichten ohne Referentenfeld gibt es die Zeile
`<p class="wp-reklamation-zeile">Stimmt hier etwas nicht?<?= $this->reklamation ?></p>`,
eingeschlossen in `<?php if($this->reklamation): ?>`.

`link()` gibt ohne Anmeldung oder ohne Admin-Adresse eine leere Zeichenkette
zurück; Dialog und Skript hängt es beim ersten Link der Seite an
(`$GLOBALS['TL_BODY']`, `$GLOBALS['TL_JAVASCRIPT']`).

Die reinen Teile prüft `tests/Helper/ReklamationTest.php`. Versand und
Speichern lassen sich im Prüfstand mit einem aufzeichnenden `mailer`-Dienst
testen (`$container->set('mailer', …)`) — `Contao\Email` holt genau diesen.
