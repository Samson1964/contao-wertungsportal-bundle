<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Controller;

use Contao\CoreBundle\Framework\ContaoFramework;
use Schachbulle\ContaoWertungsportalBundle\Helper\Reklamation;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Nimmt Reklamationen aus dem Formular im Frontend entgegen.
 *
 * Aufruf: POST /wertungsportal-api/reklamation, vom Skript reklamation.js
 * mit den Feldern REQUEST_TOKEN, kontext, signatur, betreff und text.
 *
 * Die Route läuft im Frontend-Bereich von Contao (`_scope: frontend`) — nur
 * so steht die Anmeldung des Mitglieds zur Verfügung — und mit
 * Tokenprüfung (`_token_check: true`): Contao weist ein Absenden ohne
 * gültiges REQUEST_TOKEN ab, bevor dieser Controller läuft. Die Antwort ist
 * immer JSON; das Skript zeigt die Meldung im Dialog an.
 */
class ReklamationController
{
    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @param ContaoFramework $framework Wird vor jedem Zugriff auf Contao
     *                                   gestartet (Datenbank, Einstellungen,
     *                                   Mitglied, Mailversand)
     */
    public function __construct(ContaoFramework $framework)
    {
        $this->framework = $framework;
    }

    /**
     * Beantwortet die Anfrage.
     *
     * Ohne angemeldetes Mitglied gibt es 403 — der Link erscheint zwar nur
     * für Angemeldete, aber die Adresse ist öffentlich. Alles Weitere prüft
     * Helper\Reklamation::einreichen().
     *
     * Die Antwort wird nie zwischengespeichert: Sie hängt vom Mitglied ab.
     *
     * @param Request $request Die POST-Anfrage des Formulars
     *
     * @return JsonResponse {ok: true, meldung} oder {ok: false, fehler}
     */
    public function __invoke(Request $request): JsonResponse
    {
        $this->framework->initialize();

        $mitglied = Reklamation::mitglied();

        if (null === $mitglied) {
            $ergebnis = ['status' => 403, 'daten' => ['ok' => false, 'fehler' => 'Bitte melden Sie sich an, um eine Reklamation zu schicken.']];
        } else {
            $ergebnis = Reklamation::einreichen(
                [
                    'kontext'  => (string) $request->request->get('kontext', ''),
                    'signatur' => (string) $request->request->get('signatur', ''),
                    'betreff'  => (string) $request->request->get('betreff', ''),
                    'text'     => (string) $request->request->get('text', ''),
                ],
                $mitglied,
                $request->getHost()
            );
        }

        $objResponse = new JsonResponse($ergebnis['daten'], $ergebnis['status']);
        $objResponse->setPrivate();
        $objResponse->headers->addCacheControlDirective('no-store');

        return $objResponse;
    }
}
