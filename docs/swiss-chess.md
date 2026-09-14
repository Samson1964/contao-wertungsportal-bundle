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
überschreiben, wenn jemand beide Archive in denselben Ordner entpackt. Die
Fassung mit nuLiga-ID braucht laut swiss-chess.de **Swiss-Chess ab 10.0**.

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
> entstehen die Dateien trotzdem, dann eben nur mit den DSB-Mitgliedern. Der
> Erzeuger prüft das selbst und arbeitet ohne Datenbank allein mit der CSV;
> Frauentitel, Schnell- und Blitzwertung kommen dann aus deren eigenen
> Spalten. Zum Index siehe den Grenzfall unter „Aufbau der SWX".

### Die Spalten der spieler.csv werden über ihre Namen gelesen

**Nicht über feste Nummern.** Am 10.09.2026 hat nu drei Spalten angehängt —
`FIDE-Frauentitel`, `FIDE-Elozahl-Schnellschach` und `FIDE-Elozahl-Blitz` —,
und der Erzeuger brach ab: Er verglich die Kopfzeile bis dahin Zeichen für
Zeichen. Auf dem Livesystem geschah das mitten im Cronjob.

Über die Namen ist eine angehängte oder umgestellte Spalte gleichgültig. Die
Prüfung wird dadurch nicht schwächer, sondern schärfer: Jede gebrauchte Spalte
muß namentlich dastehen, sonst bricht der Lauf ab und nennt die fehlende beim
Namen. Eine stillschweigend verrutschte Zuordnung — die DWZ im Elo-Feld —
fiele erst im Turniersaal auf, und das ist schlimmer als gar keine Datei.

Pflicht sind zwölf Spalten (`SwissChess::CSV_PFLICHT`); die drei neuen sind
freiwillig (`CSV_KUER`), damit sich auch eine ältere Datei noch verarbeiten
läßt.

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
Zeichensatz **DOS-Codepage 437** (siehe unten).

| Feld | Inhalt | Quelle in der spieler.csv |
| --- | --- | --- |
| 0 | Name,Vorname | `Name,Vorname` |
| 1 | Vereinsname | über die ZPS aus `vereine.csv` |
| 2 | Nation | `FIDE-Land`, sonst die Föderation aus `tl_wertungsportal_elo`; **leer ohne FIDE-Kennung** |
| 3 | FIDE-Elo | `FIDE-Elozahl` |
| 4 | DWZ | `DWZ` |
| 5 | Titelcode | aus `FIDE-Titel`, siehe unten |
| 6 | Geburtsjahr | `Geburtsjahr` |
| 7 | Spielerkennung | `ID` (`NU4005017`); in der alten Fassung leer |
| 8 | FIDE-Kennung | `FIDE-ID` |
| 9 | Mitgliedsnummer | `Mitgliedsnummer` |
| 10 | Geschlecht | `Geschlecht` |
| 11 | Vereinskennziffer | `ZPS` |
| 12 | Landesverband | erste Stelle der ZPS |
| 13 | Mitgliedsnummer | wie Feld 9, aber immer im Klartext |
| 14 | Status | `Status` (A/P) |
| 15–27 | FIDE-Angaben in Anführungszeichen | siehe unten |
| 28 | einzelnes Anführungszeichen | — |

**Titelcode (Feld 5):** GM=1, IM=2, FM=3, CM=4, WGM=6, WIM=7, WFM=8, WCM=9.
Die 5 kommt im ganzen Bestand nicht vor.

**Felder 15–27** stehen in Anführungszeichen und entsprechen genau den
dreizehn Angaben, die das FIDE-XML je Spieler außer der Kennung führt. Quelle
ist `tl_wertungsportal_elo`:

| Feld | Inhalt | Spalte | Ersatz aus der CSV |
| --- | --- | --- | --- |
| 15 | Frauentitel (WGM, WIM, WFM, WCM) | `w_title` | `FIDE-Frauentitel` |
| 16 | Schiedsrichter-/Trainertitel (NA, SI, FA, IA, FT …) | `o_title` | — |
| 17 | Kennzeichen (`i` inaktiv, `w` weiblich, `wi`) | `flag` | — |
| 18 | Arena-Titel der FIDE Online Arena (AGM, AIM, AFM, ACM) | `foa_title` | — |
| 19 / 20 / 21 | Standard-Elo / Partien / K-Faktor | `rating`, `games`, — | `FIDE-Elozahl` |
| 22 / 23 / 24 | dasselbe für Schnellschach | `rapid_rating`, `rapid_games`, — | `FIDE-Elozahl-Schnellschach` |
| 25 / 26 | Blitz-Elo / Partien | `blitz_rating`, `blitz_games` | `FIDE-Elozahl-Blitz` |
| 27 | **Wiederholung von Feld 26** | — | — |

Die letzte Spalte gilt nur, wenn zu einem Spieler **kein** Satz in der
Elo-Tabelle steht — etwa bei einer FIDE-Kennung, die seit dem letzten
XML-Import vergeben wurde. Liegt ein Elo-Satz vor, kommt der ganze Block von
dort: Er ist der vollständige FIDE-Datensatz, die CSV führt nur einen
Ausschnitt davon.

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
Spielern mit FIDE-Eintrag. Nennt die CSV trotz FIDE-Kennung kein Land, kommt
es aus der Elo-Tabelle — im LV-0-csv vom 09.09.2026 betrifft das 65
Mitgliedschaften, beim Swiss-Chess-Programmierer steht dort bei allen „GER".

### Zeichensatz

Swiss-Chess erwartet die Namen in der **DOS-Codepage 437**. Die spieler.csv und
die vereine.csv kommen in windows-1252, die Namen der Elo-Tabelle in UTF-8;
beides wandelt `SwissChess::nachCp437()` über eine feste Tabelle:

* Zeichen, die CP437 kennt — Umlaute, ß, é, á, ñ, ç … —, bekommen ihr Byte.
  Für diese Zeichen sind CP437 und CP850 gleich.
* Was CP437 nicht kennt, wird zum Grundbuchstaben oder zu einem Satzzeichen
  aus ASCII: „Ó" → „O", „š" → „s", „ø" → „o", „ý" → „y", „’" und „´" → „'".
  Aus „„" und „“" werden gerade Anführungszeichen, die danach wegfallen.
* Ein Zeichen, das in keiner der beiden Listen steht, fällt weg.

So hält es der Swiss-Chess-Programmierer. In seiner Datei vom 09.09.2026 steht
kein Byte, das in CP437 und CP850 Verschiedenes bedeutet, und die Namen der
100.370 Mitgliedschaften aus dem LV-0-csv vom selben Tag stimmen mit seinen
überein — auch alle mit Zeichen außerhalb von CP437 (š, Š, Á, ý, ø, ’, ´ und Ó
kommen darin vor). Die eine Ausnahme lautet schon in den Daten anders.

`iconv(…//TRANSLIT)` scheidet aus: Sein Ergebnis hängt an der Bibliothek des
Servers. Unter glibc zählt die Sprachumgebung (im C-Locale wird aus „Ó" ein
„?"), libiconv macht aus „Ó" ein „'O".

Bis 1.43.2 ging die Wandlung nach CP850. Für die üblichen Zeichen ist das
dasselbe, „´" aber stand als 0xEF in der Datei, und das ist in CP437 „∩". Die
Namen reiner FIDE-Spieler liefen außerdem durch die Wandlung für windows-1252,
obwohl die Elo-Tabelle sie in UTF-8 führt — in der Datei des Programmierers
sind sie allerdings durchweg ASCII.

## Die alte Fassung: binär kodierte Zahlen

Vor Swiss-Chess 10 stehen die Zahlenfelder (3, 4, 6, 8, 9) nicht als Text,
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

Drei Felder folgen dabei eigenen Regeln, abgelesen an der alten Datei des DSB
vom 16.08.2023:

* **DWZ ohne Wert** ist nicht leer, sondern die kodierte „0000" — die Bytes
  `nn`. So steht es dort bei allen 1.284.415 Sätzen ohne DWZ. Bei der Elo
  bleibt das Feld leer.
* **Feld 7** (Spielerkennung) bleibt leer. Die alte Fassung erwartet hier eine
  kodierte Zahl; die nuLiga-ID `NU4005017` läßt sich so nicht schreiben. Bis
  1.43.2 stand sie dort im Klartext.
* **Feld 13** wiederholt die Mitgliedsnummer **im Klartext**, obwohl Feld 9
  kodiert ist. Bis 1.43.2 war auch Feld 13 kodiert.

> **Nur die Zahlenfelder dürfen so kodiert werden.** Wer die fertige Zeile
> nachträglich in eine andere Codepage wandelt, zerstört sie: 0x82 ist in
> windows-1252 ein Anführungszeichen und wird dabei zu 0x60. Der Erzeuger
> wandelt deshalb ausschließlich die Textfelder.

## Aufbau der SWX

702 Sätze à acht Byte, je zwei vorzeichenlose 32-Bit-Zahlen in
Intel-Reihenfolge:

```
Satz 0        Offset 0, Kennung 0xFFFFFE44
Satz 1..700   belegter Eimer:  Offset in der LST,
                               Satzzahl des VORIGEN BELEGTEN Eimers minus eins
              leerer Eimer:    0, 0
Satz 701      Größe der LST, Kennung der Fassung
```

Satz 0 steht zugleich für Eimer 0 („A" + kein Buchstabe), der damit am
Dateianfang beginnt. Die Größe eines Eimers steht also nicht bei ihm selbst,
sondern beim nächsten belegten Satz.

**Satz 701 trägt keine Anzahl, sondern die Kennung der Fassung:** 0x01000000 in
den beiden Dateien von 2026 (der des DSB vom 02.09. und der des
Swiss-Chess-Programmierers vom 09.09.), 0 in der alten Datei des DSB vom
16.08.2023. Der Erzeuger schreibt `SwissChess::KENNUNG_NEU` bzw. `KENNUNG_ALT`.

Bis 1.43.2 stand dort die Satzzahl des letzten Eimers, und der Zähler saß im
Satz direkt hinter jedem Eimer — nach einer Lücke also im leeren Satz. Ein
Anwender bekam damit in Swiss-Chess Name und Verein, aber mit beiden Fassungen
leere Felder für Elo, DWZ und Geburtsjahr. Das paßt zu einem Programm, das die
Fassung am Schlußsatz abliest und die Zahlenfelder deshalb falsch deutet; in
Swiss-Chess selbst ließ es sich hier nicht nachprüfen.

Die Satznummer eines Namens ergibt sich aus den ersten beiden Zeichen seines
**Suchschlüssels** (siehe unten):

```
Satznummer = (1. Buchstabe − A) × 27 + 2. Zeichen
             2. Zeichen: 0 = kein Buchstabe, sonst 1..26 für A..Z
```

`Baab` steht also in Satz 1×27+1 = 28, `B,A Raju` in Satz 27, „Böttcher"
(Suchschlüssel `BOETTCHER`) in Satz 42.

**Eimer 701** („Zz") hat keinen eigenen Indexsatz, weil Satz 701 der
Schlußsatz ist. Seine Namen stehen wie beim Programmierer hinter Eimer 700; bis
1.43.2 fielen sie weg.

**Grenzfall ohne FIDE-Daten:** Ist die Elo-Tabelle leer, bleibt Eimer 0 leer,
und der erste belegte Eimer beginnt ebenfalls bei Offset 0 — sein Satz sieht
dann aus wie ein leerer. Mit FIDE-Daten kommt das nicht vor; wie Swiss-Chess
damit umgeht, ist nicht bekannt.

## Eimer und Sortierung

Die LST muß so sortiert sein, daß jeder Eimer zusammenhängt: zuerst nach dem
Eimer, darin nach dem Namen. Beides folgt Regeln, die an der Datei des
Swiss-Chess-Programmierers vom 09.09.2026 ausgemessen sind — Zeichen für
Zeichen, an allen 1.976.362 Sätzen und ihren Nachbarn. Sie wirken stellenweise
willkürlich; geglättet wird trotzdem nichts.

### Suchschlüssel: der Eimer

`SwissChess::suchschluessel()` bildet ihn aus Feld 0, also aus dem gekürzten
Namen in CP437:

1. **Schnitt:** An der ersten Ziffer und an einer Klammer, die ein Wort
   beginnt, ist Schluß — „Muster 2016" und „Muster (PER)" zählen wie „Muster".
   Eine Klammer mitten im Wort trennt nur.
2. **Umschrift:** Umlaute und ß werden ausgeschrieben (Ä → AE, ß → SS); é, â,
   ë, ï, Å, ô, á, ó und ú werden zum Grundbuchstaben.
3. **Großschrift, alles andere trennt:** Was nicht A..Z ist — Komma, Punkt,
   Bindestrich, Apostroph, aber auch Ç, à, ç, í und ñ —, wird als Folge zu
   einem Leerzeichen und fällt am Rand weg.

| Name | Suchschlüssel | Eimer |
| --- | --- | --- |
| `Böttcher,Uwe` | `BOETTCHER UWE` | Bo (42) |
| `Özdemir,Ali` | `OEZDEMIR ALI` | Oe (383) |
| `Aßmann,Eva` | `ASSMANN EVA` | As (19) |
| `Çelik,Can` | `ELIK CAN` | El (120) |
| `D'Avola,Test` | `D AVOLA TEST` | D + kein Buchstabe (81) |

Beim Programmierer stehen alle 133 Namen mit „Ö" am Anfang im Eimer „Oe", die
16 mit „Ü" in „Ue" und der eine mit „Ç" in „El".

Bis 1.43.2 zählten das rohe erste und zweite Byte. Von den 100.370
Mitgliedschaften des LV-0-csv vom 09.09.2026 standen damit 6.576 im falschen
Eimer — „Böttcher" in „B + kein Buchstabe" statt in „Bo" —, und die 150 mit Ö,
Ü oder Ç am Anfang fehlten ganz.

Namen ohne Buchstaben vor dem Schnitt kann der Index nicht führen; sie bleiben
weg. Im LV-0-csv vom 09.09.2026 gibt es keinen.

**Eine bewußte Abweichung:** Bei 20 Namen seiner Datei besteht der
Suchschlüssel aus einem einzigen Buchstaben („B", „D", „O" …). 17 davon stellt
der Programmierer vor ihren Eimer, ohne sie mitzuzählen. Hier gehören alle in
den Eimer „Buchstabe + kein Buchstabe".

### Sortierschlüssel: die Reihenfolge im Eimer

`SwissChess::sortierschluessel()` ist der Suchschlüssel mit zwei Zusätzen:

* **SZ zählt wie SS** — „Kaszab" steht vor „Kassel". Für den Eimer gilt das
  nicht, „Szabo" bleibt in „Sz".
* **`` ` ``, `?` und `'` an einem Wortrand** — neben einem Leerzeichen, einem
  Komma oder am Namensende — sowie **É und ò** stehen vor dem Leerzeichen:
  „Muster\`,Nicola" vor „Muster,Anna", „Muster,Md? Zaki" vor „Muster,Md
  Adnan", „In 't Muster" vor „In,Anna". Mitten im Wort („D'Avola") trennen
  diese Zeichen nur. Im Schlüssel stehen sie als Byte 0x1F — unter dem
  Leerzeichen, aber über dem Tabulator, der in den Eimerdateien den Schlüssel
  abschließt.

Bei gleichem Schlüssel stehen reine FIDE-Sätze vor den Mitgliedschaften,
danach gilt die Reihenfolge des Einlesens.

Wie nah das an der Datei des Programmierers liegt, zeigt die Zahl der
Nachbarpaare, die dort in anderer Reihenfolge stehen:

| Regeln | Nachbarpaare anders | davon mit DSB-Mitglied |
| --- | ---: | ---: |
| bis 1.43.2: kleingeschriebener Name, Byte für Byte | 119.582 | — |
| Umschrift und Trenner, jede Klammer schneidet | 246 | 10 |
| Klammer schneidet nur am Wortanfang | 177 | 9 |
| dazu `` ` ``, `?`, `'` am Wortrand, É und ò vor dem Leerzeichen | **13** | **0** |

Die letzten 13 sind reine FIDE-Namen: acht, bei denen er „sy" vor „sz" stellt,
drei lange, bei denen der längere Name vor seinem Anfangsstück steht, und zwei
mit einer Null statt eines O. Die drei langen passen zu einem auf 30 Zeichen
gekürzten Schlüssel; übernommen ist das nicht, weil dann 49 weitere Paare nur
noch nach der Reihenfolge des Einlesens stünden. Verworfen sind außerdem SZ=SS
nur für Mitglieder (1.170 Paare anders) und ein Modell, das Folgen von
Trennern nicht zusammenfaßt (über 5.000).

Sortiert wird nicht im Speicher: Jeder Eimer bekommt während des Laufs eine
eigene Datei im Arbeitsverzeichnis, in die seine Zeilen samt Sortierschlüssel
geschrieben werden; zum Schluß werden die Dateien der Reihe nach sortiert,
aneinandergehängt und gelöscht. Bei knapp zwei Millionen Sätzen ginge es
anders nicht. Das Aufräumen steht in einem `finally` — bricht der Lauf ab,
bleibt kein Arbeitsverzeichnis liegen.

## Woher das Format stammt

Es ist nirgends dokumentiert. Abgeleitet ist es aus den beiden
Originaldateien des DSB (16.08.2023 und 02.09.2026, zusammen 3,2 Millionen
Datensätze) und — seit 1.43.3 — aus der Datei, die der Swiss-Chess-Programmierer
am 09.09.2026 selbst erzeugt hat, samt dem LV-0-csv vom selben Tag.

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

### Nachbau gegen die Datei des Programmierers

Alle 1.976.362 Zeilen seiner Datei laufen durch Eimer, Sortierung und Index des
Erzeugers:

* Die LST wird gleich groß (194.395.272 Byte), 1.970.030 Zeilen stehen an
  derselben Stelle. Die übrigen verschieben sich um wenige Plätze: um
  Gleichstände, die er anders ordnet, um die 13 Paare oben und um die 17 Namen
  aus einem einzigen Buchstaben.
* 690 der 702 Indexsätze sind gleich; die zwölf übrigen gehen auf die 17 Namen
  aus einem einzigen Buchstaben zurück.
* An der alten Datei des DSB von 2023 ergibt die Eimerregel bei 1.354.237
  Sätzen dieselben Eimer — bis auf Namen aus einem Buchstaben, einen ohne
  Buchstaben und einen „Zz"-Namen hinter Eimer 700.

Aus dem LV-0-csv vom 09.09.2026 entstehen 100.370 Mitgliedschaften, keine fällt
heraus. Gegen seine Datei:

* **DWZ, Geburtsjahr, Spielerkennung, FIDE-Kennung und ZPS** stimmen bei allen
  überein, der Name bei 100.369 — die eine Ausnahme lautet schon in den Daten
  anders.
* In der Reihenfolge weichen nur Mitgliedschaften mit gleichem Sortierschlüssel
  voneinander ab (3.578, fast immer dieselbe Person in zwei Vereinen).
* Beide SWX folgen der Zählregel in allen 391 belegten Sätzen, die 309 leeren
  tragen (0, 0), Satz 701 die Kennung der jeweiligen Fassung. Weil hier die
  FIDE-Daten fehlen, beginnt Eimer „Aa" bei Offset 0 — der Grenzfall oben.
* Alte Fassung: 26.323 Sätze ohne DWZ tragen `nn`, Feld 7 ist überall leer,
  Feld 13 überall Klartext.
* Die übrigen Unterschiede kommen aus den Daten, nicht aus dem Format: Elo,
  Titel, Geschlecht, Status, Mitgliedsnummer und vereinzelt Verein oder
  Landesverband führt er aus anderen Quellen oder mit anderem Stand.

**Was sich hier nicht prüfen läßt: ob Swiss-Chess die Dateien liest.** Die
Messungen belegen, daß sie der Datei des Programmierers gleichen. Die
Rückmeldung aus dem Turniersaal ersetzen sie nicht.

Die Unit-Tests stehen unter `tests/Classes/SwissChessTest.php` und laufen ohne
weitere Dateien. Die Skripte, mit denen die Nachweise oben geführt wurden,
liegen in `SwissChess-Dateien/pruefstand/` samt Anleitung — nicht versioniert,
weil sie die Originaldateien daneben brauchen.
