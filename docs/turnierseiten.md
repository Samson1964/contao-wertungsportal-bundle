# Turnierseiten: Nichtmitglieder und Erwartungswerte

Turnierauswertung, Turnierergebnisse und Spielberichtsbogen zeigen dieselben
Angaben eines Spielers — alte DWZ, neue DWZ, Erwartungswert. Für
**Nichtmitglieder** („Textuelle": Teilnehmer, die im Wertungsportal nur als
Text erfasst und mit keiner Person verknüpft sind, etwa Gäste aus dem Ausland)
gelten dabei eigene Regeln. Sie stehen seit Fassung 1.45.0 an einer Stelle:
`Helper\Spielerwertung`.

## Wer ein Nichtmitglied ist

Maßgeblich ist das Feld **`member`** der Schnittstelle. Nach Auskunft des
DSB-Wertungsreferats reicht `member = false` aus — auch künftig, wenn
ausgetretene Mitglieder noch fünf Jahre mit ihrer Person verknüpft bleiben:
Unmittelbar nach dem Austritt soll keine neue DWZ mehr veröffentlicht werden.

Fehlt das Feld (Zeilen der Spiegeltabelle aus der Zeit vor 1.45.0), entscheidet
die nuLiga-Personennummer: Ein textuell erfasster Teilnehmer hat keine.

## Was für Nichtmitglieder gezeigt wird

| Angabe | Mitglied | Nichtmitglied |
|---|---|---|
| DWZ alt | `ratingOld` und `indexOld`, „1887 - 44" | der Anzeigetext `ratingOldDisplayString`, so wie nu ihn formt: „1905" (Eingangswertung ohne Index, meist eine Elo) oder „(1537)" (frühere DWZ eines Ausgetretenen) |
| DWZ neu, DWZ ± | `ratingNew`, `indexNew`, Differenz | **immer leer** — auch wenn die Schnittstelle `ratingNew` liefert |
| K, We, Leistung, Niveau | die Zahlenfelder | die Zahlenfelder; fehlen sie, die Anzeigetexte `factorKDisplayString` und `winsExpectedDisplayString` |

Der Grund für die leere neue DWZ ist die Wertungsordnung, Ziffer 3.4.3:

> Spieler, die nicht Mitglied eines DSB-Vereins sind (Vereinslose), werden zwar
> in die Berechnungen einbezogen, für diese werden aber keine DWZ und keine
> Restpartien ausgewiesen oder gespeichert.

Die Eingangswertung dagegen **muss** zu sehen sein: Mit ihr zählen die
Nichtmitglieder für ihre Gegner. Ohne sie lässt sich die Auswertung eines
Mitglieds nicht nachvollziehen.

Ein Teilnehmer ganz ohne Wertung hat auch keine Eingangswertung. nu setzt für
ihn eine errechnete Zahl ein und liefert sie als `ratingNewDisplayString`, etwa
„(1318)". Das ist eine neue Wertung eines Nichtmitglieds und wird nicht
gezeigt; seine Zeile bleibt in den Wertungsspalten leer. Die Partien gegen ihn
bekommen trotzdem ihren Erwartungswert — siehe unten.

Die Regeln gelten in allen drei Ansichten: In den **Turnierergebnissen** und im
**Spielberichtsbogen** steht die Eingangswertung in der Spalte „DWZ". Über dem
Bogen eines Nichtmitglieds heißt es „Eingangswertung 1554" statt „DWZ 1554".

## Erwartungswert je Partie im Spielberichtsbogen

Die Schnittstelle gibt zu jeder Partie das Feld **`expected`** mit — und zwar
**immer aus Sicht von Weiß**, gleichgültig, wessen Bogen abgerufen wurde. Hatte
der Spieler Schwarz, gilt `1 − expected`.

Nachgemessen am Bogen, mit dem der Fehler gemeldet wurde: vier Partien mit
Weiß, drei mit Schwarz, Summe 3,890983 — geliefert wird `winsExpected` 3,89098,
und dieselben Einzelwerte zeigt das Wertungsportal von nu. Über 10.927 Spieler
im Bestand, deren Partien vollständig mit `expected` vorlagen, stimmte die
Summe bei 94 %. Bei den übrigen passen gespiegelte Partien und gespiegelte
Auswertung nicht zueinander — einzelne `expected` weichen dort auch von dem ab,
was die beiden alten Wertungen ergeben. Vermutlich sind es unterschiedliche
Berechnungsstände; mit der umgekehrten Lesart (Sicht des Bogeninhabers) gehen
sie jedenfalls auch nicht auf.

Drei Regeln folgen daraus:

1. **`expected` geht vor.** Nur damit ergeben die Zeilen die Summe darunter,
   und nur so bekommt eine Partie gegen einen Teilnehmer ohne Eingangswertung
   überhaupt einen Wert.
2. **Kampflose Partien bekommen keinen Wert** (`PLUS_MINUS`, `MINUS_PLUS`,
   `MINUS_MINUS`, `ZERO_MINUS`, `MINUS_ZERO`). Sie werden nicht ausgewertet; nu
   liefert für sie trotzdem ein `expected`. Mit diesen Werten stimmte die Summe
   bei 1.001 Spielern weniger.
3. **Fehlt `expected`, wird gerechnet** — aus den alten Wertungen beider
   Spieler, nach der Wertungsordnung (Normalverteilung über die Differenz,
   Streuung 200 × √2). nu liefert das Feld nicht für jedes Turnier, und die
   Ergebnisliste liefert es nie. Die Zelle trägt dann einen Hinweis im
   Tooltip. Die frühere Elo-Formel `1 / (1 + 10^(−D/400))` wich bei großen
   Differenzen sichtbar ab: 1887 gegen 1318 ergab 0,964 statt 0,978.

Bis 1.44.1 wurde **jede** Zeile mit der Elo-Formel geschätzt. Gegner ohne DWZ
blieben leer, und die Summe der Zeilen ergab nie den Wert darunter.

## Notbetrieb

Fällt die Schnittstelle aus, kommen die Seiten aus den Spiegeltabellen
(`Helper\Lokal`). Damit die Regeln dort genauso gelten, spiegelt
`tl_wertungsportal_tournaments_evaluation` seit 1.45.0 zwei weitere Felder:

| Spalte | Inhalt |
|---|---|
| `member` | `'1'` Mitglied, `'0'` Nichtmitglied, leer = unbekannt (Zeile aus der Zeit davor) |
| `ratingOldDisplayString` | Anzeigetext der alten Wertung — für Nichtmitglieder die einzige Quelle der Eingangswertung |

`member` ist mit Absicht dreiwertig. Ein Kontrollkästchen könnte „unbekannt"
nicht von „Nichtmitglied" unterscheiden, und dann verlören im Notbetrieb alle
älteren Zeilen ihre neue DWZ.

Die Spalten füllen sich mit jedem Abruf der Schnittstelle. **Nach dem
Einspielen ist `contao:migrate` nötig.** Solange die Spalten fehlen, läuft der
Abgleich im alten Umfang weiter, statt mit „Unknown column" abzubrechen.

Zwei Schwächen des Notbetriebs sind mit behoben: Ein nie gelieferter
Erwartungswert stand als „0,000" in der Auswertung (die Spalte ist `NOT NULL`
mit Vorgabe 0), und aus demselben Grund galt ein fehlendes `expected` als
Erwartung null.

## Nachprüfen

`tests/Helper/SpielerwertungTest.php` prüft die Regeln ohne Contao, mit den
Zahlen des gemeldeten Turniers. Die Rohantworten der Schnittstelle liefert das
Backend-Modul [Rohdaten](rohdaten.md) — Funktionen „Turnierauswertung" und
„Spielberichtsbogen".
