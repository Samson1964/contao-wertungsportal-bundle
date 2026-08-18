<?php

namespace Schachbulle\ContaoWertungsportalBundle\Classes;

/**
 * Sorgt dafür, dass das Backend-Modul „Einstellungen" nur die Einstellungen
 * dieses Bundles zeigt.
 *
 * **Warum ein Hook und nicht die DCA-Datei?** DCA-Dateien werden alphabetisch
 * nach Paketnamen geladen, und jedes Bundle hängt seine Felder mit `.=` an
 * `palettes['default']` an. Wer die Palette in seiner eigenen DCA-Datei setzt,
 * bekommt anschließend die Felder aller später geladenen Bundles wieder
 * dazugeschrieben — gemessen waren es 80 fremde Felder. Der Hook
 * `loadDataContainer` läuft NACH allen DCA-Dateien und ist damit die einzige
 * Stelle, an der die Palette wirklich steht.
 */
class Einstellungen
{
	/**
	 * Ersetzt im eigenen Backend-Modul die Palette von tl_settings.
	 *
	 * Gespeichert wird weiterhin in `tl_settings` (also in der
	 * `localconfig.php`); DC_File schreibt ausschließlich die Felder der
	 * Palette, die übrigen Contao-Einstellungen bleiben also unangetastet.
	 *
	 * @param  string $strTable Tabelle, deren DCA gerade geladen wurde
	 * @return void
	 */
	public function loadDataContainer($strTable)
	{
		if('tl_settings' !== $strTable) return;
		if('wp-settings' !== \Input::get('do')) return;
		if(empty($GLOBALS['TL_DCA']['tl_settings']['palettes']['wertungsportal'])) return;

		$GLOBALS['TL_DCA']['tl_settings']['palettes']['default'] = $GLOBALS['TL_DCA']['tl_settings']['palettes']['wertungsportal'];
	}
}
