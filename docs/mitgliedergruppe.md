# Mitgliedergruppe für DSB-Mitglieder

Contao-Mitglieder (Frontend-Konten), die im Spielerbestand des Wertungsportals
stehen, bekommen automatisch eine Mitgliedergruppe — etwa um ihnen Seiten oder
Inhalte freizugeben, die nur DSB-Mitglieder sehen sollen (ab 1.54.0).

## Einrichten

**Wertungsportal → Einstellungen → Mitgliedergruppe → Mitgliedergruppe für
DSB-Mitglieder:** eine vorhandene Mitgliedergruppe auswählen. Ohne Auswahl ist
die Funktion aus.

## Regel

| | |
|---|---|
| Die E-Mail-Adresse des Mitglieds (`tl_member.email`) kommt im Spielerbestand vor (`tl_wertungsportal_persons.email1` oder `email2`) | das Mitglied **bekommt** die Gruppe |
| Sie kommt nicht vor | die Gruppe wird dem Mitglied **genommen** |

- Groß- und Kleinschreibung und Leerraum am Rand zählen nicht.
- Andere Gruppen des Mitglieds bleiben, wie sie sind.
- Ein Mitglied ohne E-Mail-Adresse gehört nie dazu.
- Maßgeblich ist allein die E-Mail-Adresse — nicht, ob der Spieler aktiv
  gemeldet ist. Der Spielerbestand enthält auch abgemeldete Spieler.

## Wann

- **Monatlich** über Contaos Cron (`Cron\Mitgliedergruppenabgleich`). Das
  Ergebnis steht mit einer Zeile im Systemprotokoll.
- **Von Hand:**

  ```
  php vendor/bin/contao-console wertungsportal:mitgliedergruppe --dry-run
  php vendor/bin/contao-console wertungsportal:mitgliedergruppe --dry-run --ids
  php vendor/bin/contao-console wertungsportal:mitgliedergruppe
  ```

  `--dry-run` rechnet und zeigt, wie viele Mitglieder die Gruppe bekämen oder
  verlören, schreibt aber nichts; `--ids` nennt dazu die IDs der Mitglieder.
  **Vor dem ersten echten Lauf empfohlen.**

## Sicherungen

- **Leerer Spielerbestand:** Enthält `tl_wertungsportal_persons` keine einzige
  E-Mail-Adresse (etwa nach einem missglückten Import), wird nichts geändert —
  sonst verlöre mit einem Lauf jedes Mitglied die Gruppe.
- **Gruppe gelöscht:** Gibt es die eingestellte Gruppe nicht mehr, geschieht
  nichts; das Systemprotokoll bekommt einen Fehlereintrag.
- Geschrieben wird nur die Gruppenliste der betroffenen Mitglieder. Der
  Änderungszeitpunkt des Kontos bleibt, wie er ist.

Der Abgleich richtet sich nach dem **monatlichen Spieler-Import** (siehe
Hinweis auf der Backend-Startseite): Erst nach dem Import kennt das
Wertungsportal neue und geänderte Adressen.

## Einspielen

`cache:clear` (neuer Cronjob, neuer Befehl). Danach die Gruppe in den
Einstellungen wählen und einmal `--dry-run` laufen lassen.
