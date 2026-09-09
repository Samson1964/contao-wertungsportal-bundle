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

Die Archive werden in die Dateiverwaltung eingetragen und tauchen damit im
Backend auf — genauso wie die CSV-Pakete des Converters.

Quelle ist die `export/csv/LV-0-csv.zip`, die `wertungsportal:converter`
erzeugt. Mit `--quelle` läßt sich statt dessen ein bereits entpacktes
Verzeichnis angeben, mit `--ziel` das Archivverzeichnis.

## Was in den Dateien steht — und was nicht

Enthalten sind **alle DSB-Mitglieder, eine Zeile je Mitgliedschaft**. Wer in
zwei Vereinen gemeldet ist, steht zweimal in der Liste, jeweils mit seinem
Verein — genau so halten es die Originaldateien des DSB.

> **Zwei Unterschiede zu den Originaldateien des DSB.** Erstens führen jene
> zusätzlich **alle weltweit von der FIDE erfaßten Spieler**, auch die ohne
> DSB-Mitgliedschaft. Zweitens enthalten sie **Schnell- und Blitzwertungen,
> Partienzahlen, K-Faktoren und die FIDE-Kennzeichen**. Beides steht nicht in
> der LV-0-csv; die hier erzeugten Dateien haben diese Felder leer.
>
> Der Bestand für beides liegt im Bundle bereit (`tl_wertungsportal_elo`, aus
> dem monatlichen FIDE-XML-Import). Wer die Dateien vollständig haben will,
> müßte den Erzeuger daran anschließen — das ist bewußt nicht geschehen,
> weil die Aufgabe „aus der LV-0-csv" lautete.

Ebenfalls nicht ableitbar: der **Frauentitel**. Die CSV hat nur eine
Titelspalte; bei einer Spielerin mit offenem Titel (etwa GM) steht dort „GM",
und daß sie zusätzlich WGM führt, geht daraus nicht hervor. Betroffen sind 88
von 100.000 Sätzen.

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

**Felder 15–27:** 15 Frauentitel, 16 Schiedsrichter-/Trainertitel (NA, FA, IA,
FT, FS …), 17 Kennzeichen (`i` inaktiv, `w` weiblich), 19/20/21 Standard-Elo /
Partien / K-Faktor, 22/23/24 dasselbe für Schnellschach, 25/26/27 für Blitz.
Aus der CSV gefüllt werden nur 15 und 19.

**Zwei Formatregeln**, aus der Originaldatei abgelesen: Name und Vereinsname
sind auf **40 Zeichen** gekürzt und enthalten **keine Anführungszeichen**; eine
„0" bei Elo oder DWZ wird zum leeren Feld.

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

Namen, die nicht mit A–Z beginnen, kann der Index nicht führen; sie bleiben
weg (in der aktuellen Datei 152 von 100.370).

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

Die erzeugten Dateien sind gegen die Originaldatei vom 02.09.2026 abgeglichen.
Alle verbleibenden Abweichungen erklären sich aus der Woche, die zwischen den
beiden Datenständen liegt — Vereinswechsel, neue FIDE-Kennungen, geänderte
DWZ. Der Prüfstand steht unter `tests/`.
