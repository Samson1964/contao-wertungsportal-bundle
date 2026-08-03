<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Controller;

use Contao\CoreBundle\Framework\ContaoFrameworkInterface;
use Schachbulle\ContaoWertungsportalBundle\Helper\VereinslisteApi;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Öffentlicher Einstiegspunkt der örtlichen Vereinslisten-Schnittstelle.
 *
 * Aufruf: /wertungsportal-api/vereinsliste?token=SCHLUESSEL&vkz=30052
 *
 * Der Controller hält sich bewusst kurz: Er startet das Contao-Framework
 * (ohne das gibt es weder Datenbank noch Einstellungen), reicht die beiden
 * Parameter weiter und macht aus dem Ergebnis eine JSON-Antwort. Die gesamte
 * Prüfung und Aufbereitung steckt in Helper\VereinslisteApi.
 */
class VereinslisteController
{
    /**
     * @var ContaoFrameworkInterface
     */
    private $framework;

    /**
     * Nimmt die Schnittstelle als Interface entgegen, nicht als konkrete
     * Klasse: So läßt sich der Controller ohne halbe Contao-Installation
     * prüfen, und der Dienst „contao.framework" paßt weiterhin.
     */
    public function __construct(ContaoFrameworkInterface $framework)
    {
        $this->framework = $framework;
    }

    /**
     * Beantwortet die Anfrage.
     *
     * Die Antwort wird ausdrücklich als „nicht zwischenspeichern" markiert:
     * Über die Gültigkeit der Daten entscheidet der Zwischenspeicher des
     * Bundles, nicht der Browser oder ein vorgelagerter Proxy — sonst
     * bekäme der Aufrufer alte Listen, ohne dass wir es merken.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $this->framework->initialize();

        $arrErgebnis = VereinslisteApi::anfrage(
            (string) $request->query->get('token', ''),
            (string) $request->query->get('vkz', ''),
            (string) $request->getClientIp()
        );

        $objResponse = new JsonResponse($arrErgebnis['daten'], $arrErgebnis['status']);
        $objResponse->setPrivate();
        $objResponse->headers->addCacheControlDirective('no-store');

        // Damit die Liste auch aus einer Vereinswebsite heraus per Javascript
        // gelesen werden kann. Die Daten sind ohnehin nur mit Schlüssel zu
        // bekommen, eine Einschränkung auf einzelne Herkünfte brächte nichts
        $objResponse->headers->set('Access-Control-Allow-Origin', '*');

        return $objResponse;
    }
}
