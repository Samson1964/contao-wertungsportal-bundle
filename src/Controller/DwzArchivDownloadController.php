<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Controller;

use Schachbulle\ContaoWertungsportalBundle\DwzArchiv\Archivindex;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Liefert eine Datei der DWZ-Archivsuche aus (ab 1.61.0).
 *
 * Aufruf: GET /wertungsportal-api/dwz-archiv/<quelle>/<JJJJ>/<ordner>/<datei>.zip
 *
 * Nötig, weil Contaos `?file=` nur Dateien ausliefert, die in einem
 * Download-Element auf derselben Seite stehen — genau diese Elemente ersetzt
 * die Suche. Der Weg über den Controller hängt außerdem nicht davon ab, ob die
 * Ordner in der Dateiverwaltung öffentlich sind und ob der Web-Ordner `web/`
 * (4.13) oder `public/` (5) heißt.
 *
 * Welche Angaben zu einer Datei führen, entscheidet allein
 * `Archivindex::aufloesen()`; alles andere ergibt 404.
 */
class DwzArchivDownloadController
{
	/** @var Archivindex */
	private $index;

	/**
	 * @param Archivindex $index Verzeichnis der Archivdateien (Dienst aus services.yml)
	 */
	public function __construct(Archivindex $index)
	{
		$this->index = $index;
	}

	/**
	 * Gibt die Datei als Anhang zurück.
	 *
	 * Die Dateien ändern sich nach dem Anlegen nicht mehr (das Datum steht im
	 * Namen); die Antwort trägt deshalb ETag und Last-Modified und darf
	 * öffentlich zwischengespeichert werden.
	 *
	 * @param string $pfad Pfad aus der Adresse, z. B. `wertungsportal/2026/csv/LV-0-csv_20261125.zip`
	 *
	 * @throws NotFoundHttpException Wenn die Angabe nicht zu einer erlaubten, vorhandenen Datei führt
	 *
	 * @return BinaryFileResponse Die Zip-Datei mit `Content-Disposition: attachment`
	 */
	public function __invoke(string $pfad): BinaryFileResponse
	{
		$datei = $this->index->aufloesen($pfad);

		if (null === $datei) {
			throw new NotFoundHttpException('Datei nicht gefunden');
		}

		return new BinaryFileResponse($datei, 200, array('Content-Type' => 'application/zip'), true, ResponseHeaderBag::DISPOSITION_ATTACHMENT, true, true);
	}
}
