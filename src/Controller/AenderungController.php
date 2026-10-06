<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Controller;

use Contao\CoreBundle\Framework\ContaoFramework;
use Schachbulle\ContaoWertungsportalBundle\Helper\Aenderung;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Nimmt Änderungsmeldungen aus dem Frontend entgegen: „Foto ändern" auf der
 * Karteikarte und „Logo/Infos ändern" auf der Vereinsseite (ab 1.54.0).
 *
 * Aufruf: POST /wertungsportal-api/aenderung als multipart/form-data, vom
 * Skript aenderung.js mit den Feldern REQUEST_TOKEN, kontext, signatur,
 * betreff, nachricht, kopie, datei und — beim Verein — homepage, info und
 * info_angefasst.
 *
 * Wie bei den Reklamationen: Frontend-Bereich von Contao (`_scope:
 * frontend`), damit die Anmeldung des Mitglieds bekannt ist, und
 * Tokenprüfung (`_token_check: true`). Die Antwort ist immer JSON.
 */
class AenderungController
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
     * Ohne angemeldetes Mitglied gibt es 403 — die Links erscheinen zwar nur
     * für Angemeldete, aber die Adresse ist öffentlich. Alles Weitere prüft
     * Helper\Aenderung::melden().
     *
     * Von der hochgeladenen Datei gehen nur Pfad, Größe und Fehlercode
     * weiter. Dateiname und Typ, wie der Browser sie meldet, bleiben
     * unbeachtet: Beides bestimmt der Absender.
     *
     * @param Request $request Die POST-Anfrage des Formulars
     *
     * @return JsonResponse {ok: true, meldung} oder {ok: false, fehler}
     */
    public function __invoke(Request $request): JsonResponse
    {
        $this->framework->initialize();

        $mitglied = Aenderung::mitglied();

        if (null === $mitglied) {
            $ergebnis = ['status' => 403, 'daten' => ['ok' => false, 'fehler' => 'Bitte melden Sie sich an, um eine Änderung zu schicken.']];
        } else {
            $eingabe = [];

            foreach (['kontext', 'signatur', 'betreff', 'nachricht', 'kopie', 'homepage', 'info', 'info_angefasst'] as $feld) {
                $wert = $request->request->get($feld, '');
                $eingabe[$feld] = \is_scalar($wert) ? (string) $wert : '';
            }

            $upload = $request->files->get('datei');
            $datei = null;

            if ($upload instanceof UploadedFile) {
                $datei = [
                    'pfad'    => $upload->getPathname(),
                    'groesse' => UPLOAD_ERR_OK === $upload->getError() ? (int) $upload->getSize() : 0,
                    'fehler'  => $upload->getError(),
                ];
            }

            $ergebnis = Aenderung::melden($eingabe, $datei, $mitglied, $request->getHost());
        }

        $objResponse = new JsonResponse($ergebnis['daten'], $ergebnis['status']);
        $objResponse->setPrivate();
        $objResponse->headers->addCacheControlDirective('no-store');

        return $objResponse;
    }
}
