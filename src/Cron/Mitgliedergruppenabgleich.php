<?php

namespace Schachbulle\ContaoWertungsportalBundle\Cron;

use Schachbulle\ContaoWertungsportalBundle\Helper\Helper;
use Schachbulle\ContaoWertungsportalBundle\Helper\Mitgliedergruppe;

/**
 * Monatlicher Cronjob: ordnet Contao-Mitgliedern die „Mitgliedergruppe für
 * DSB-Mitglieder" zu oder nimmt sie ihnen (ab 1.54.0).
 *
 * Die Regel steht in Helper\Mitgliedergruppe: Wessen E-Mail-Adresse im
 * Spielerbestand vorkommt (email1 oder email2), bekommt die Gruppe; wer nicht
 * mehr vorkommt, verliert sie. Monatlich, weil auch der Spieler-Import
 * monatlich läuft — öfter änderte sich nichts.
 *
 * Der Lauf schweigt und schreibt eine Zeile ins Systemprotokoll. Zum Zusehen
 * und für einen Probelauf gibt es `wertungsportal:mitgliedergruppe`.
 */
class Mitgliedergruppenabgleich
{
	/**
	 * Führt den Abgleich aus.
	 *
	 * Contao ruft die Methode über den Dienst-Tag contao.cronjob auf. Ist
	 * keine Gruppe eingestellt, geschieht nichts und es wird auch nichts
	 * protokolliert — die Funktion ist dann schlicht nicht in Gebrauch.
	 *
	 * @param  string $scope Aufrufbereich ('cli' oder 'web'), nur fürs Protokoll
	 * @return void
	 */
	public function __invoke($scope = 'cli')
	{
		try
		{
			$ergebnis = Mitgliedergruppe::abgleichen();

			if($ergebnis['status'] === 'aus') return;

			Helper::systemlog(
				'Wertungsportal: '.Mitgliedergruppe::meldung($ergebnis).' ('.$scope.')',
				__METHOD__,
				$ergebnis['status'] === 'ok' ? 'CRON' : 'ERROR'
			);
		}
		catch(\Throwable $e)
		{
			// Ein Fehler hier darf die übrigen Cronjobs nicht aufhalten
		}
	}
}
