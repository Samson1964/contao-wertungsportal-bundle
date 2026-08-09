<?php

namespace Schachbulle\ContaoWertungsportalBundle\Cron;

/**
 * Täglicher Cronjob, der die Arbeitsablagen des Bundles leert.
 *
 * Bisher nur die Zählertabelle der Besucherbremse: Sie bekommt eine Zeile je
 * IP-Adresse und wüchse sonst mit jeder je gesehenen Adresse weiter, obwohl
 * die Zähler nach einem Tag ohnehin bedeutungslos sind.
 *
 * Bewusst NICHT im Vorlade-Cronjob mituntergebracht: Der lässt sich
 * abschalten, und dann bliebe das Aufräumen mit aus. Aufräumen ist aber keine
 * Frage der Einstellung.
 */
class Aufraeumer
{
	/**
	 * Führt den Lauf aus.
	 *
	 * Contao ruft die Methode über den Dienst-Tag contao.cronjob auf.
	 *
	 * @param  string $scope Aufrufbereich ('cli' oder 'web'), nur fürs Protokoll
	 * @return void
	 */
	public function __invoke($scope = 'cli')
	{
		$zeilen = \Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalBesucherModel::aufraeumen();

		if($zeilen < 1) return;

		try
		{
			\System::log(
				'Wertungsportal: '.$zeilen.' verwaiste Besucherzähler entfernt ('.$scope.')',
				__METHOD__,
				defined('TL_CRON') ? TL_CRON : 'CRON'
			);
		}
		catch(\Throwable $e)
		{
			// Protokoll ist Beiwerk
		}
	}
}
