# Wertungsreferenten

Das Backend-Modul **Wertungsportal → Referenten** hält fest, wer für welchen
Verband die DWZ-Auswertung verantwortet. Daraus entstehen

- die Referentenliste im Frontend (Modul „Wertungsreferenten"),
- der Hinweis „Zuständiger Wertungsreferent" unter den Ranglisten eines
  Verbandes und
- die Empfänger der [Reklamationen](reklamationen.md) zu diesen Ranglisten.

Ausgegeben werden nur **veröffentlichte** Referenten.

## Zuständigkeit

Im Feld **Zuständig für** stehen alle Verbände aus dem Vereinsbestand — alles,
dessen Kennziffer auf `00` endet, also Landesverbände wie Bezirke — und ganz
oben der **Deutsche Schachbund** selbst (`00000`, ab 1.51.0).

Ist für einen Bezirk niemand eingetragen, gilt der Referent des
Landesverbands, danach der des DSB. Unter der Rangliste steht dann „Für diesen
Verband ist niemand gesondert eingetragen; zuständig ist …".

Die Zuständigkeit wird **nur hier** gepflegt. Das Feld „Wertungsreferent" und
das Frontend-Modul „Wertungsreferenten" des Adressen-Bundles sind veraltet.

## Name und Kontaktdaten aus der Adressverwaltung (ab 1.51.0)

Ist das Paket `schachbulle/contao-adressen-bundle` installiert, steht in der
Eingabemaske oben das Feld **Adresse aus der Adressverwaltung** (mit Suche).
Ist dort eine Adresse gewählt, kommen bei jeder Ausgabe frisch von dort:

| Angabe | Regel |
|---|---|
| Name | Titel, Vorname, Nachname der Adresse |
| E-Mail | erste belegte Adresse (E-Mail 1 bis 6) |
| Telefon | erste belegte Nummer (Telefon 1 bis 4) |
| Anschrift | Straße, PLZ und Ort — immer alle drei aus derselben Quelle |
| nu-ID | bleibt die eigene |

Die Felder des Referenten selbst springen nur ein, **wo die Adresse gar nichts
hat**. Eine Änderung in der Adressverwaltung wirkt sofort, nichts muss doppelt
gepflegt werden.

**Was in der Adressverwaltung als nicht öffentlich markiert ist** (die
Häkchen „anzeigen" bei E-Mail, Telefon, Ort und Straße), erscheint auch hier
nicht — auch dann nicht, wenn im Referenten selbst eine Nummer stünde. Wer
seine Nummer dort verbirgt, soll sie nicht über einen Umweg veröffentlicht
finden. **Reklamationen erreichen den Referenten trotzdem:** Für den Versand
gilt die erste E-Mail-Adresse der Adresse, auch eine nicht öffentliche; sie
erscheint dabei nirgends auf der Seite.

Ist die Adresse **nicht aktiv** oder gelöscht, gelten wieder die eigenen
Angaben des Referenten. Die Liste im Backend zeigt das an:

| Hinweis in der Liste | Bedeutung |
|---|---|
| `[Adressverwaltung]` (grau) | Daten kommen aus der Adresse |
| `[Adresse inaktiv]` (rot) | Adresse abgeschaltet — es gelten die eigenen Angaben |
| `[Adresse fehlt]` (rot) | Adresse gelöscht — es gelten die eigenen Angaben |

Mit gewählter Adresse darf der Nachname leer bleiben; beim Speichern wird der
Name der Adresse eingetragen, damit die Liste richtig sortiert und die Suche
ihn findet. Das Stift-Symbol neben der Auswahl öffnet die Adresse zum
Nachsehen oder Berichtigen in einem Fenster.

Ohne Adressen-Bundle fehlen Feld und Knopf; alles läuft mit den eigenen
Feldern wie bisher.

## Übernahme aus der Adressverwaltung

Der Knopf **Aus der Adressverwaltung übernehmen** über der Liste legt alle
Personen, die in der Adressverwaltung als Wertungsreferent eines Verbandes
eingetragen sind, hier als Referenten an — mit ihrer Adresse verknüpft und mit
ihren Verbänden.

1. Der Knopf zeigt zuerst eine **Vorschau**: wer angelegt wird und für welche
   Verbände, wessen Schlüssel es im Wertungsportal nicht gibt, wer schon
   vorhanden ist.
2. Erst **„… Referenten übernehmen"** legt an. Mit dem Häkchen „gleich
   veröffentlichen" erscheinen sie sofort in den Ausgaben und bekommen
   Reklamationen; ohne bleiben sie unsichtbar, bis sie veröffentlicht werden
   (einzeln oder über „Mehrere bearbeiten").

Regeln:

- Übernommen werden nur **aktive** Adressen.
- Eine Adresse, die schon einem Referenten zugeordnet ist, wird übergangen —
  ein zweiter Klick legt niemanden doppelt an und ändert keinen bestehenden
  Referenten.
- Verbandsschlüssel der Adressverwaltung, die es im Wertungsportal nicht gibt,
  werden gemeldet. Bleibt einer Person dadurch kein Verband, steht sie unter
  „Ohne passenden Verband — bitte von Hand anlegen".
- Voraussetzung ist ein abgeglichener Vereinsbestand: Aus ihm kommen die
  bekannten Verbände.

Stand der Testdatenbank (Abzug von schachbund.de, 30.09.2026): 104 aktive
Personen mit Wertungsreferat; 103 werden übernommen, eine hat nur den
Schlüssel `61600`, den es bei nu nicht gibt. Ein zweiter Schlüssel `L0000`
fällt bei einer sonst übernommenen Person weg.
