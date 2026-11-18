<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;

/**
 * Verbindung zur Datenbank des Wertungsarchivs (ab 2.0.0).
 *
 * Das Archiv (ELOBASE, DeWIS, Wertungsportal: rund 1,9 Mio. Personen und
 * 10,9 Mio. Karteizeilen) liegt in einer EIGENEN Datenbank, nicht in der von
 * Contao (Festlegung Frank Binding, 17.11.2026). Deshalb ist es auch keine
 * Verbindung im Doctrine-Bundle: Eine zweite dort angemeldete Verbindung
 * müsste die Konfiguration von Contao umbauen, und `contao:migrate` soll von
 * den Archivtabellen nichts wissen. Die Verbindung entsteht hier, erst beim
 * ersten Gebrauch, aus zwei Umgebungsvariablen in `.env.local`:
 *
 *     WERTUNGSARCHIV_DATABASE_URL="mysql://benutzer:passwort@host:3306/datenbank"
 *     WERTUNGSARCHIV_PFAD="/pfad/zum/ordner/wertungsarchiv"
 *
 * Fehlt die Adresse, ist das Archiv abgeschaltet: verfuegbar() liefert false,
 * und die Seiten arbeiten wie in 1.x.
 */
class Verbindung
{
    /**
     * @var string Adresse der Datenbank (DSN), '' = Archiv abgeschaltet
     */
    private $adresse;

    /**
     * @var string Ordner mit der Übergabe (je Archiv ein Unterordner)
     */
    private $pfad;

    /**
     * @var Connection|null Einmal geöffnete Verbindung
     */
    private $verbindung;

    /**
     * Nimmt Adresse und Ordner aus der Dienstkonfiguration entgegen
     * (`%env(default::WERTUNGSARCHIV_DATABASE_URL)%` u. a.).
     *
     * @param string|null $adresse DSN der Archivdatenbank, leer = abgeschaltet
     * @param string|null $pfad    Ordner der Übergabe, leer = nicht eingerichtet
     */
    public function __construct(?string $adresse = '', ?string $pfad = '')
    {
        $this->adresse = trim((string) $adresse);
        $this->pfad = rtrim(str_replace('\\', '/', trim((string) $pfad)), '/');
    }

    /**
     * Ob das Archiv eingerichtet ist (Adresse gesetzt). Eine Verbindung wird
     * dabei nicht geöffnet.
     *
     * @return bool true, wenn WERTUNGSARCHIV_DATABASE_URL gesetzt ist
     */
    public function verfuegbar(): bool
    {
        return '' !== $this->adresse;
    }

    /**
     * Ordner der Übergabe, ohne Schrägstrich am Ende.
     *
     * @return string Pfad, '' wenn WERTUNGSARCHIV_PFAD fehlt
     */
    public function pfad(): string
    {
        return $this->pfad;
    }

    /**
     * Öffnet die Verbindung beim ersten Aufruf und gibt sie danach wieder.
     *
     * Die Adresse wird mit dem DsnParser zerlegt: DBAL 4 (Contao 5) kennt den
     * Parameter `url` nicht mehr; DBAL 3 vor 3.6 hat keinen DsnParser und
     * bekommt die Adresse deshalb als `url`.
     *
     * @throws \RuntimeException wenn das Archiv nicht eingerichtet ist
     *
     * @return Connection Verbindung zur Archivdatenbank (utf8mb4)
     */
    public function db(): Connection
    {
        if (null !== $this->verbindung) {
            return $this->verbindung;
        }

        if (!$this->verfuegbar()) {
            throw new \RuntimeException('Das Wertungsarchiv ist nicht eingerichtet: WERTUNGSARCHIV_DATABASE_URL fehlt in .env.local.');
        }

        if (class_exists(DsnParser::class)) {
            $parameter = (new DsnParser(array('mysql' => 'pdo_mysql', 'mariadb' => 'pdo_mysql')))->parse($this->adresse);
        } else {
            $parameter = array('url' => $this->adresse);
        }

        $parameter['charset'] = 'utf8mb4';

        return $this->verbindung = DriverManager::getConnection($parameter);
    }
}
