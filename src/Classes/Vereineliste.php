<?php

namespace Schachbulle\ContaoWertungsportalBundle\Classes;

/**
 * Aufbereitung der Zeilen im Backend-Modul „Vereine".
 *
 * Die Liste zeigt vier Spalten: Kennziffer, Name, Status und das Vereinslogo.
 * Zwei davon lassen sich nicht aus dem Datenbankwert allein bilden — dafür gibt
 * es diese Klasse.
 */
class Vereineliste
{
	/**
	 * Baut eine Zeile der Vereinsliste.
	 *
	 * Zwei Dinge passieren hier:
	 *
	 * 1. **Das Logo** wird auf 16 Pixel gerechnet. Ohne eigene Datei bleibt die
	 *    Spalte leer — ein Platzhalterbild in jeder Zeile wäre nur Rauschen.
	 * 2. **Abgemeldete Vereine** bekommen einen unsichtbaren Anker
	 *    `<span class="wp-abgemeldet">`, an dem das Backend-CSS die ganze Zeile
	 *    grau färbt. Die Zeilenklasse selbst gibt Contao im Spaltenmodus nicht
	 *    her; über `:has()` geht es trotzdem — dasselbe Verfahren wie bei den
	 *    unveröffentlichten Datensätzen.
	 *
	 * @param  array  $row   Datensatz der Zeile
	 * @param  string $label Von Contao vorbereitete Beschriftung (ungenutzt im
	 *                       Spaltenmodus)
	 * @param  object $dc    DataContainer
	 * @param  array  $args  Die Spaltenwerte in der Reihenfolge von label.fields
	 * @return array         Die bearbeiteten Spaltenwerte
	 */
	public function zeile($row, $label, $dc = null, $args = array())
	{
		// Spalte 4: Vereinslogo, auf 16 Pixel gerechnet
		$args[3] = '';

		if(!empty($row['addImage']) && !empty($row['singleSRC']))
		{
			$args[3] = $this->logo($row['singleSRC']);
		}

		// Abgemeldete Vereine grau: unsichtbarer Anker für das Backend-CSS
		if('DELETE_STATE_TRUE' === ($row['state'] ?? ''))
		{
			$args[0] = '<span class="wp-abgemeldet"></span>'.$args[0];
		}

		return $args;
	}

	/**
	 * Erzeugt das verkleinerte Vereinslogo.
	 *
	 * Gibt bei jedem Fehler eine leere Zeichenkette zurück: Eine gelöschte oder
	 * unlesbare Datei darf die ganze Liste nicht zum Stehen bringen — und in
	 * einer Übersicht mit 2400 Vereinen fiele so ein Absturz erst auf, wenn
	 * niemand mehr an die Daten kommt.
	 *
	 * @param  string $uuid Binäre UUID der Datei aus dem fileTree-Feld
	 * @return string HTML des Bildes, leer wenn nicht darstellbar
	 */
	protected function logo($uuid)
	{
		try
		{
			$objFile = \FilesModel::findByUuid($uuid);

			if($objFile === null) return '';

			$wurzel = \System::getContainer()->getParameter('kernel.project_dir');

			if(!is_file($wurzel.'/'.$objFile->path)) return '';

			$bild = \System::getContainer()
				->get('contao.image.factory')
				->create($wurzel.'/'.$objFile->path, array(16, 16, 'box'))
				->getUrl($wurzel);

			return \Contao\Image::getHtml($bild, '', 'style="vertical-align:middle"');
		}
		catch(\Throwable $e)
		{
			return '';
		}
	}
}
