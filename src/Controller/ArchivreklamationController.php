<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Controller;

use Contao\CoreBundle\Framework\ContaoFramework;
use Schachbulle\ContaoWertungsportalBundle\Helper\Reklamation;
use Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv\Archivreklamation;
use Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv\Zugriff;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Nimmt Reklamationen zu Einträgen des Wertungsarchivs entgegen (ab 2.0.0).
 *
 * Aufruf: POST /wertungsportal-api/archiv-reklamation, vom Skript
 * archivreklamation.js mit REQUEST_TOKEN, kontext, signatur, gruende[],
 * felder[…] und text. Wie die Reklamation der nu-Daten im Frontend-Bereich
 * (Anmeldung des Mitglieds) und mit Tokenprüfung. Die Meldung wird nur in
 * die Logdatei geschrieben (Wertungsarchiv\Archivreklamation).
 */
class ArchivreklamationController
{
    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @param ContaoFramework $framework Wird vor jedem Zugriff auf Contao
     *                                   gestartet (Mitglied, Einstellungen)
     */
    public function __construct(ContaoFramework $framework)
    {
        $this->framework = $framework;
    }

    /**
     * Beantwortet die Anfrage.
     *
     * Ohne angemeldetes Mitglied oder ohne Zugriff auf das Wertungsarchiv
     * gibt es 403 — die Knöpfe erscheinen nur dann, aber die Adresse ist
     * öffentlich. Alles Weitere prüft Archivreklamation::einreichen().
     *
     * @param Request $request Die POST-Anfrage des Formulars
     *
     * @return JsonResponse {ok: true, meldung, satz} oder {ok: false, fehler}; nie zwischengespeichert
     */
    public function __invoke(Request $request): JsonResponse
    {
        $this->framework->initialize();

        $mitglied = Reklamation::mitglied();

        if (null === $mitglied || !Zugriff::erlaubt()) {
            $ergebnis = ['status' => 403, 'daten' => ['ok' => false, 'fehler' => 'Bitte melden Sie sich mit einem Konto an, das das Wertungsarchiv sehen darf.']];
        } else {
            $ergebnis = Archivreklamation::einreichen(
                [
                    'kontext'  => (string) $request->request->get('kontext', ''),
                    'signatur' => (string) $request->request->get('signatur', ''),
                    'gruende'  => (array) ($request->request->all()['gruende'] ?? []),
                    'felder'   => (array) ($request->request->all()['felder'] ?? []),
                    'text'     => (string) $request->request->get('text', ''),
                ],
                $mitglied
            );
        }

        $objResponse = new JsonResponse($ergebnis['daten'], $ergebnis['status']);
        $objResponse->setPrivate();
        $objResponse->headers->addCacheControlDirective('no-store');

        return $objResponse;
    }
}
