# Hintergrunddateien für Swiss-Chess

Swiss-Chess liest die Spielerdaten aus einem Dateipaar: einer Datenliste
(`.LST`) und einem Index über den Namensanfang (`.SWX`). Der Befehl

```bash
vendor/bin/contao-console wertungsportal:swisschess
```

legt daraus zwei Archive ins Jahresarchiv, aufgeteilt wie die CSV- und
DOS-Pakete daneben:

```
files/wertungsportal/downloads/JJJJ/
├── swiss10/dsb-swiss10_JJJJMMTT.zip    Swiss-Chess ab 10.0
└── swiss/dsb-swiss_JJJJMMTT.zip        ältere Fassungen
```

Jedes Archiv enthält das Dateipaar `fdsbJJMMTT.LST` und `.SWX`; die Dateien
der alten Fassung tragen ein „a" am Namensende, damit sie sich nicht
überschreiben, wenn jemand beide Archive in denselben Ordner entpackt.

Von beiden Archiven wandert zusätzlich eine Kopie **unter festem Namen** ins
Exportverzeichnis:

```
files/wertungsportal/downloads/export/
├── swiss10/dsb-swiss10.zip
└── swiss/dsb-swiss.zip
```

Das ist dieselbe Aufteilung, die der Converter für `export/csv/` und
`export/dos/` benutzt: Der Downloadlink auf der Website soll sich nicht jeden
Monat ändern, während das datierte Archiv daneben die Historie führt.

Alle vier Dateien werden in die Dateiverwaltung eingetragen und tauchen damit
im Backend auf. Bei den Exportkopien wird der bestehende Eintrag samt
Prüfsumme aufgefrischt statt ein zweiter angelegt — sonst zeigte Contao nach
dem Überschreiben weiter auf den alten Inhalt.

Quelle ist die `export/csv/LV-0-csv.zip`, die `wertungsportal:converter`
erzeugt. Mit `--quelle` läßt sich statt dessen ein bereits entpacktes
Verzeichnis angeben, mit `--ziel` das Archivverzeichnis.

## Was in den Dateien steht

Die Dateien kommen aus **zwei Quellen**:

* die **`spieler.csv`** der LV-0-csv liefert die DSB-Mitglieder, eine Zeile je
  Mitgliedschaft. Wer in zwei Vereinen gemeldet ist, steht zweimal in der
  Liste, jeweils mit seinem Verein — genau so halten es die Originaldateien
  des DSB;
* **`tl_wertungsportal_elo`** (aus dem monatlichen FIDE-XML-Import) liefert
  zweierlei: die FIDE-Angaben der Mitglieder — Schnell- und Blitzwertung,
  Partienzahlen, Frauen-, Amts- und Arena-Titel, Kennzeichen — **und alle
  übrigen weltweit von der FIDE geführten Spieler als eigene Sätze**.

Der zweite Teil macht den Löwenanteil aus. In der Originaldatei des DSB vom
02.09.2026 stehen 1.973.816 Sätze: 100.250 DSB-Mitgliedschaften und 1.873.566
reine FIDE-Spieler, von denen 1.334.966 nicht einmal eine Standardwertung
haben. Ein reiner FIDE-Satz hat weder Verein noch DWZ, weder Spieler- noch
Mitgliedsnummer, keine Vereinskennziffer und keinen Status.

> **Ist die Elo-Tabelle leer** — der XML-Import also noch nie gelaufen —,
> entstehen die Dateien trotzdem, dann eben nur mit den DSB-Mitgliedern und
> ohne die FIDE-Felder. Der Erzeuger prüft das selbst und arbeitet ohne
> Datenbank allein mit der CSV.

Weil dabei knapp zwei Millionen Sätze zu sortieren sind, laufen sie nicht
über den Speicher, sondern über **Eimerdateien**: Jeder der 702 Namenseimer
bekommt eine eigene Datei im Arbeitsverzeichnis, am Ende werden sie der Reihe
nach aneinandergehängt. Aus demselben Grund liest der Erzeuger die Elo-Tabelle
über `iterateAssociative()` und nicht über die Contao-Datenbankklasse — deren
Ergebnisobjekt behält jede gelesene Zeile.

### Was nicht drinsteht: die K-Faktoren

Die Felder 21 und 24 tragen im Original den K-Faktor (10, 20 oder 40). Das
FIDE-XML führt ihn, `tl_wertungsportal_elo` hat aber keine Spalte dafür — er
bleibt hier leer, sofern eine Wertung vorliegt. Ohne Wertung steht wie im
Original eine „0".

Geschätzt wird nichts. Über 2400 wäre der Faktor immer 10 — das ist an allen
3.060 Sätzen der Originaldatei nachgezählt —, darunter hängt er an der Zahl
der über die ganze Laufbahn gewerteten Partien, und die steht nirgends. Eine
Nachrechnung nach den bekannten Regeln trifft nur 59 % der Sätze; ein
falscher Faktor ginge in Swiss-Chess direkt in die Berechnung von
Wertungsänderungen ein. Ein leeres Feld ist an dieser Stelle unbedenklich:
Die Originaldatei selbst läßt es bei allen 57.369 Sätzen ohne FIDE-Kennung
leer.

Nachzuholen wäre das über drei Spalten `k`, `rapid_k` und `blitz_k` in
`tl_wertungsportal_elo` plus drei Zeilen in `Classes/EloImport.php`; siehe
`TODO.md`.

## Aufbau der LST

Ein Datensatz je Zeile, 29 Felder durch Semikolon getrennt, Zeilenende CRLF,
Zeichensatz **DOS-Codepage 850**.

| Feld | Inhalt | Quelle in der spieler.csv |
| --- | --- | --- |
| 0 | Name,Vorname | `Name,Vorname` |
| 1 | Vereinsname | über die ZPS aus `vereine.csv` |
| 2 | Nation | `FIDE-Land`; **leer ohne FIDE-Eintrag** |
| 3 | FIDE-Elo | `FIDE-Elozahl` |
| 4 | DWZ | `DWZ` |
| 5 | Titelcode | aus `FIDE-Titel`, siehe unten |
| 6 | Geburtsjahr | `Geburtsjahr` |
| 7 | Spielerkennung | `ID` (`NU4005017`) |
| 8 | FIDE-Kennung | `FIDE-ID` |
| 9 | Mitgliedsnummer | `Mitgliedsnummer` |
| 10 | Geschlecht | `Geschlecht` |
| 11 | Vereinskennziffer | `ZPS` |
| 12 | Landesverband | erste Stelle der ZPS |
| 13 | Mitgliedsnummer | wie Feld 9 |
| 14 | Status | `Status` (A/P) |
| 15–27 | FIDE-Angaben in Anführungszeichen | siehe unten |
| 28 | einzelnes Anführungszeichen | — |

**Titelcode (Feld 5):** GM=1, IM=2, FM=3, CM=4, WGM=6, WIM=7, WFM=8, WCM=9.
Die 5 kommt im ganzen Bestand nicht vor.

**Felder 15–27** stehen in Anführungszeichen und entsprechen genau den
dreizehn Angaben, die das FIDE-XML je Spieler außer der Kennung führt. Quelle
ist `tl_wertungsportal_elo`:

| Feld | Inhalt | Spalte |
| --- | --- | --- |
| 15 | Frauentitel (WGM, WIM, WFM, WCM) | `w_title` |
| 16 | Schiedsrichter-/Trainertitel (NA, SI, FA, IA, FT …) | `o_title` |
| 17 | Kennzeichen (`i` inaktiv, `w` weiblich, `wi`) | `flag` |
| 18 | Arena-Titel der FIDE Online Arena (AGM, AIM, AFM, ACM) | `foa_title` |
| 19 / 20 / 21 | Standard-Elo / Partien / K-Faktor | `rating`, `games`, — |
| 22 / 23 / 24 | dasselbe für Schnellschach | `rapid_rating`, `rapid_games`, — |
| 25 / 26 | Blitz-Elo / Partien | `blitz_rating`, `blitz_games` |
| 27 | **Wiederholung von Feld 26** | — |

Zwei Punkte daran sind leicht falsch zu raten:

* **Feld 16 und Feld 18 sind zwei verschiedene Felder.** Der Arena-Titel
  rückt nicht in Feld 16 nach, wenn kein Amtstitel vorliegt; beide kommen
  auch gleichzeitig vor.
* **Feld 27 ist kein Blitz-K-Faktor**, sondern eine Wiederholung der
  Blitzpartien. In allen 1.973.816 Sätzen der Originaldatei stehen in Feld 26
  und 27 dieselben Zeichen — ohne eine einzige Ausnahme.

Hat ein Satz **keine FIDE-Kennung**, bleiben alle dreizehn Felder leer; so
halten es alle 57.369 solchen Sätze der Originaldatei.

**Drei Formatregeln**, aus der Originaldatei abgelesen: Name und Vereinsname
sind auf **40 Zeichen** gekürzt und enthalten **keine Anführungszeichen**; eine
„0" bei Elo oder DWZ wird zum leeren Feld; die Nation steht **nur** bei
Spielern mit FIDE-Eintrag.

## Die alte Fassung: binär kodierte Zahlen

Vor Swiss-Chess 10 stehen die Zahlenfelder (3, 4, 6, 8, 9, 13) nicht als Text,
sondern als Bytefolge:

```
Byte 100..109 (0x64..0x6D)  =  EINE Ziffer  (Byte − 100)
Byte 110..209 (0x6E..0xD1)  =  ZWEI Ziffern (Byte − 110)
```

Die Ziffern werden von links nach rechts aneinandergehängt. Weil sich die
beiden Bytebereiche nicht überschneiden, weiß der Leser ohne weitere Angabe,
ob ein Byte für eine oder zwei Ziffern steht. Bei ungerader Ziffernzahl steht
die **letzte** Ziffer allein.

Beispiel — die FIDE-Kennung 7127030:

```
71 | 27 | 03 | 0     →     B5 89 71 64
```

> **Nur die Zahlenfelder dürfen so kodiert werden.** Wer die fertige Zeile
> nachträglich nach CP850 wandelt, zerstört sie: 0x82 ist in windows-1252 ein
> Anführungszeichen und wird dabei zu 0x60. Der Erzeuger wandelt deshalb
> ausschließlich die Textfelder.

## Aufbau der SWX

702 Sätze à acht Byte, je zwei vorzeichenlose 32-Bit-Zahlen in
Intel-Reihenfolge:

```
Satz 0        Offset 0, Kennung 0xFFFFFE44
Satz 1..700   Offset des Eimers, Zähler des VORHERGEHENDEN Eimers minus eins
Satz 701      Dateigröße der LST
```

Die Satznummer ergibt sich aus den ersten beiden Zeichen des Namens:

```
Satznummer = (Index des 1. Buchstabens A..Z) × 27 + Index des 2. Zeichens
             Index des 2. Zeichens: 0 = kein a..z, sonst 1..26 für a..z
```

`Baab` steht also in Satz 1×27+1 = 28, `B,A Raju` in Satz 27. **Leere Eimer
tragen den Offset 0**; die belegten Offsets sind streng steigend.

Die Verschiebung des Zählers um einen Satz sieht nach einer Eigenheit des
ursprünglichen Programms aus. Sie ist in beiden Originaldateien gleich und
wird unverändert nachgebildet.

## Sortierung

Die LST muß so sortiert sein, daß jeder Eimer zusammenhängt. Sortiert wird
deshalb **zuerst nach der Satznummer des Index**, erst danach nach dem Namen.

Eine reine Namenssortierung reicht nicht: Ein Umlaut an zweiter Stelle —
„Bäcker" — steht in CP850 hinter dem „z" und landete damit hinter allen
„Bz"-Namen. Der Eimer für „B + kein Buchstabe" wäre zerrissen und sein Bereich
im Index unbrauchbar.

Sortiert wird nicht im Speicher: Jeder Eimer bekommt während des Laufs eine
eigene Datei im Arbeitsverzeichnis, in die seine Zeilen geschrieben werden;
zum Schluß werden die 702 Dateien der Reihe nach aneinandergehängt und
gelöscht. Bei knapp zwei Millionen Sätzen ginge es anders nicht. Das Aufräumen
steht in einem `finally` — bricht der Lauf ab, bleibt kein Arbeitsverzeichnis
liegen.

Namen, die nicht mit A–Z beginnen, kann der Index nicht führen; sie bleiben
weg (in der aktuellen Datei 152 von 100.370 Mitgliedschaften).

## Woher das Format stammt

Es ist nirgends dokumentiert. Der Aufbau wurde aus den beiden Originaldateien
des DSB abgeleitet (16.08.2023 und 02.09.2026, zusammen 3,2 Millionen
Datensätze) und gegengeprüft:

* **Zahlenkodierung:** 4.494.303 Felder der alten Datei kodiert und wieder
  entschlüsselt — keine einzige Abweichung. Zusätzlich 112.824 FIDE-Kennungen
  gegen die neue Datei geprüft, in der sie im Klartext stehen.
* **Indexformel:** an allen 702 Sätzen beider SWX-Dateien nachgerechnet, indem
  an jedem Offset in der LST nachgesehen wurde, welcher Name dort beginnt.
* **Feldbedeutung:** durch Verknüpfung von 99.990 Sätzen der Originaldatei mit
  ihrer Zeile in der spieler.csv.
* **FIDE-Felder:** Die Elo-Tabelle einer Prüfinstallation wurde mit allen
  1.913.201 FIDE-Kennungen der Originaldatei befüllt — aus der Datei selbst
  zurückgerechnet — und das Ergebnis danach Feld für Feld dagegen gehalten.
  **1.916.405 von 1.916.407 Sätzen stimmen in den Feldern 15–27 überein**,
  abgesehen von den K-Faktoren. Die zwei Ausreißer sind die einzigen
  vierstelligen Arena-Titel des Bestands (`AAFM`, `AAIM`); die Spalte
  `foa_title` ist drei Zeichen breit und kürzt sie.
* **Index:** Die erzeugte SWX belegt genau dieselben 616 der 702 Eimer wie das
  Original, mit identischem Kopfsatz.

Die erzeugten Dateien sind insgesamt gegen die Originaldatei vom 02.09.2026
abgeglichen. Alle verbleibenden Abweichungen erklären sich aus der Woche, die
zwischen den beiden Datenständen liegt — Vereinswechsel, neue FIDE-Kennungen,
geänderte DWZ.

Die Unit-Tests stehen unter `tests/Classes/SwissChessTest.php` und laufen ohne
weitere Dateien. Die Skripte, mit denen die Nachweise oben geführt wurden,
liegen in `SwissChess-Dateien/pruefstand/` samt Anleitung — nicht versioniert,
weil sie die Originaldateien des DSB daneben brauchen.
