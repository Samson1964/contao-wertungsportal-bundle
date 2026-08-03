<?php

/**
 * Contao Open Source CMS
 *
 * @package   Wertungsportal
 * @file      TokenRegistrierung
 * @author    Frank Binding
 * @license   GNU/LGPL
 * @copyright Frank Binding 2026
 *
 * Version 1.0 - 2026 - Frank Binding
 * --------------------------------------
 * Frontendmodul: Anforderung eines Zugangsschlüssels für die örtliche
 * Vereinslisten-Schnittstelle (/wertungsportal-api/vereinsliste).
 *
 * Der Antragsteller gibt Vereinskennziffer, Vor- und Nachname sowie seine
 * E-Mail-Adresse an; der Schlüssel geht anschließend an diese Adresse — mit
 * einem PHP-Beispielskript, das sich unverändert verwenden lässt.
 */

namespace Schachbulle\ContaoWertungsportalBundle\Classes;

class TokenRegistrierung extends \Module
{

	/**
	 * Template
	 * @var string
	 */
	protected $strTemplate = 'wertungsportal_token';

	/**
	 * Zahl der Schlüsselanforderungen, die je E-Mail-Adresse und je
	 * IP-Adresse am Tag angenommen werden.
	 *
	 * Das Formular verschickt E-Mails an eine frei wählbare Adresse und wäre
	 * ohne Bremse ein bequemes Werkzeug, um fremde Postfächer zu fluten.
	 */
	const ANFORDERUNGEN_JE_TAG = 5;

	/**
	 * Zeigt im Backend den Platzhalter statt des Formulars.
	 *
	 * @return string
	 */
	public function generate()
	{
		if (TL_MODE == 'BE')
		{
			$objTemplate = new \BackendTemplate('be_wertungsportal');

			$objTemplate->wildcard = '### WERTUNGSPORTAL SCHNITTSTELLEN-REGISTRIERUNG ###';
			$objTemplate->title = $this->name;
			$objTemplate->id = $this->id;

			return $objTemplate->parse();
		}

		return parent::generate();
	}

	/**
	 * Baut das Formular auf und verarbeitet die Absendung.
	 *
	 * Bei erfolgreicher Anforderung wird das Formular durch eine Bestätigung
	 * ersetzt; bei Fehlern bleiben die Eingaben stehen und werden erläutert.
	 *
	 * @return void
	 */
	protected function compile()
	{
		$formId = 'wp_token_'.$this->id;

		$this->Template->formId = $formId;
		$this->Template->action = \Environment::get('indexFreeRequest');
		$this->Template->adresse = self::schnittstellenUrl();
		$this->Template->eingaben = array('vkz' => '', 'vorname' => '', 'nachname' => '', 'email' => '');
		$this->Template->fehler = array();
		$this->Template->bestaetigung = '';

		if(\Input::post('FORM_SUBMIT') != $formId) return;

		$eingaben = array
		(
			'vkz'      => strtoupper(trim((string) \Input::post('vkz'))),
			'vorname'  => trim((string) \Input::post('vorname')),
			'nachname' => trim((string) \Input::post('nachname')),
			'email'    => strtolower(trim((string) \Input::post('email'))),
		);

		$this->Template->eingaben = $eingaben;

		$fehler = $this->pruefe($eingaben);

		if(count($fehler))
		{
			$this->Template->fehler = $fehler;

			return;
		}

		// Schlüssel holen oder anlegen. Eine zweite Anforderung derselben
		// Person für denselben Verein liefert den vorhandenen Schlüssel,
		// damit nicht mit jeder vergessenen E-Mail ein weiterer gültig bleibt
		list($objToken, $neu) = \Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalTokensModel::registriere($eingaben);

		if($neu)
		{
			// Herkunft der Anforderung festhalten (Missbrauchserkennung)
			$objToken->ip = (string) \Environment::get('ip');
			$objToken->save();
		}

		if(!$this->verschicke($objToken))
		{
			// Der Schlüssel ist an dieser Stelle bereits angelegt — es hat nur
			// der Versand nicht geklappt. Das ausdrücklich so zu sagen, erspart
			// dem Antragsteller vergebliche Wiederholungen (bei einem
			// klemmenden Mailversand ändert sich daran nichts) und dem
			// Betreiber die Frage, warum ein Datensatz ohne Empfänger dasteht.
			// Der Grund steht im Systemprotokoll
			$this->Template->fehler = array('Der Zugangsschlüssel wurde angelegt, die E-Mail konnte aber nicht zugestellt werden. Bitte wenden Sie sich an den Betreiber dieser Website — eine erneute Anforderung ändert daran nichts.');

			return;
		}

		$this->Template->bestaetigung = $neu
			? 'Der Zugangsschlüssel wurde erzeugt und an '.\StringUtil::specialchars($eingaben['email']).' geschickt.'
			: 'Für diesen Verein und diese E-Mail-Adresse besteht bereits ein Zugangsschlüssel. Er wurde erneut an '.\StringUtil::specialchars($eingaben['email']).' geschickt.';
	}

	/**
	 * Prüft die Eingaben des Formulars.
	 *
	 * Geprüft wird nicht nur die Form, sondern auch, ob es die Vereinskennziffer
	 * überhaupt gibt — sonst bekäme der Antragsteller einen Schlüssel, mit dem
	 * die Schnittstelle ihm dauerhaft nur Fehler liefert.
	 *
	 * @param  array $eingaben vkz, vorname, nachname, email
	 * @return array Liste der Fehlermeldungen, leer wenn alles stimmt
	 */
	protected function pruefe($eingaben)
	{
		$fehler = array();

		// Honigtopf: Ein für Menschen unsichtbares Feld, das nur ausgefüllt
		// wird, wenn ein Skript blind alle Felder befüllt
		if(trim((string) \Input::post('wp_homepage')) !== '')
		{
			return array('Die Anforderung konnte nicht verarbeitet werden.');
		}

		if(!preg_match('/^[0-9A-Z]{5}$/', $eingaben['vkz']))
		{
			$fehler[] = 'Die Vereinskennziffer besteht aus fünf Zeichen (Ziffern und Großbuchstaben), zum Beispiel 30052.';
		}
		elseif(\Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalClubsModel::findByVkz($eingaben['vkz']) === null)
		{
			$fehler[] = 'Die Vereinskennziffer '.\StringUtil::specialchars($eingaben['vkz']).' ist unbekannt.';
		}

		if($eingaben['vorname'] === '' || $eingaben['nachname'] === '')
		{
			$fehler[] = 'Bitte geben Sie Vor- und Nachnamen an.';
		}

		if(!\Validator::isEmail($eingaben['email']))
		{
			$fehler[] = 'Bitte geben Sie eine gültige E-Mail-Adresse an.';
		}
		else
		{
			// Bremse: je Adresse und je Herkunft nur wenige Anforderungen am Tag
			$seit = time() - 86400;

			if(\Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalTokensModel::zaehleRegistrierungen($eingaben['email'], $seit) >= self::ANFORDERUNGEN_JE_TAG
				|| \Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalTokensModel::zaehleRegistrierungenIp((string) \Environment::get('ip'), $seit) >= self::ANFORDERUNGEN_JE_TAG)
			{
				$fehler[] = 'Es wurden zu viele Schlüssel angefordert. Bitte versuchen Sie es morgen noch einmal.';
			}
		}

		return $fehler;
	}

	/**
	 * Verschickt den Schlüssel an die hinterlegte Adresse.
	 *
	 * @param  \Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalTokensModel $objToken
	 * @return bool true, wenn die E-Mail abgeschickt werden konnte
	 */
	protected function verschicke($objToken)
	{
		$verein = \Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalClubsModel::findByVkz((string) $objToken->vkz);
		$vereinsname = $verein !== null ? (string) $verein->clubName : '';

		try
		{
			$objEmail = new \Email();
			$objEmail->from = self::absenderadresse();
			$objEmail->fromName = self::absendername();
			$objEmail->subject = 'Ihr Zugangsschlüssel für die Vereinslisten-Schnittstelle';

			// Beide Teile: Der HTML-Teil ist die Vorlage aus den Einstellungen,
			// der Textteil die Rückfallebene. Manche Postfächer zeigen nur
			// Text an, und zum Herauskopieren des Beispielskripts ist er
			// ohnehin die verlässlichere Fassung
			$objEmail->text = $this->mailtext($objToken, $vereinsname);
			$html = $this->mailhtml($objToken, $vereinsname);

			if($html !== '') $objEmail->html = $html;

			$objEmail->sendTo((string) $objToken->email);
		}
		catch(\Throwable $e)
		{
			\System::log('Zugangsschlüssel konnte nicht verschickt werden: '.$e->getMessage(), __METHOD__, TL_ERROR);

			return false;
		}

		return true;
	}

	/**
	 * Liefert die Absenderadresse der Bundle-E-Mails.
	 *
	 * Vorrang hat die eigene Einstellung; ohne sie die Adresse des
	 * Administrators aus den allgemeinen Contao-Einstellungen. Der Rückgriff
	 * ist wichtig: Ohne gültige Absenderadresse nimmt kein Mailserver die
	 * Nachricht an.
	 *
	 * @return string
	 */
	public static function absenderadresse()
	{
		$adresse = trim((string) ($GLOBALS['TL_CONFIG']['wertungsportal_mail_absender'] ?? ''));

		return $adresse !== '' ? $adresse : (string) \Config::get('adminEmail');
	}

	/**
	 * Liefert den Absendernamen der Bundle-E-Mails.
	 *
	 * Vorrang hat die eigene Einstellung, danach der Name der Website.
	 *
	 * @return string
	 */
	public static function absendername()
	{
		$name = trim((string) ($GLOBALS['TL_CONFIG']['wertungsportal_mail_absendername'] ?? ''));

		if($name !== '') return $name;

		return (string) \Config::get('websiteTitle') ?: 'Wertungsportal';
	}

	/**
	 * Stellt die Werte zusammen, die Text- und HTML-Fassung der E-Mail
	 * gleichermaßen brauchen.
	 *
	 * An einer Stelle gebündelt, damit die beiden Fassungen nicht
	 * auseinanderlaufen — sie sollen dasselbe sagen.
	 *
	 * @param  \Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalTokensModel $objToken
	 * @param  string $vereinsname Klartextname des Vereins, darf leer sein
	 * @return array
	 */
	protected function mailwerte($objToken, $vereinsname)
	{
		$adresse = self::schnittstellenUrl();
		$abrufe = \Schachbulle\ContaoWertungsportalBundle\Helper\VereinslisteApi::abrufeJeTag();

		return array
		(
			'token'      => (string) $objToken->token,
			'vkz'        => (string) $objToken->vkz,
			'verein'     => $vereinsname,
			'adresse'    => $adresse,
			'aufruf'     => $adresse.'?token='.$objToken->token.'&vkz='.$objToken->vkz,
			'email'      => (string) $objToken->email,
			'vorname'    => (string) $objToken->vorname,
			'nachname'   => (string) $objToken->nachname,
			'name'       => trim($objToken->vorname.' '.$objToken->nachname),
			'abrufe'     => $abrufe,
			'abrufetext' => $abrufe > 0 ? $abrufe.' Abrufe' : 'beliebig viele Abrufe',
			'beispiel'   => self::beispielskript((string) $objToken->token, (string) $objToken->vkz, $adresse),
			'freigabe'   => (!$objToken->published || $objToken->gesperrt),
			'absender'   => self::absendername(),
		);
	}

	/**
	 * Baut den HTML-Teil der E-Mail aus der in den Einstellungen gewählten
	 * Vorlage.
	 *
	 * Ist keine Vorlage gewählt, bleibt der HTML-Teil leer und es geht nur der
	 * Textteil hinaus — eine kaputte oder fehlende Vorlage darf den Versand
	 * nicht verhindern.
	 *
	 * @param  \Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalTokensModel $objToken
	 * @param  string $vereinsname Klartextname des Vereins
	 * @return string HTML, oder '' wenn keine Vorlage greift
	 */
	protected function mailhtml($objToken, $vereinsname)
	{
		$vorlage = trim((string) ($GLOBALS['TL_CONFIG']['wertungsportal_mail_token'] ?? ''));

		if($vorlage === '') return '';

		try
		{
			$objTemplate = new \FrontendTemplate($vorlage);

			foreach($this->mailwerte($objToken, $vereinsname) as $name => $wert)
			{
				$objTemplate->$name = $wert;
			}

			return $objTemplate->parse();
		}
		catch(\Throwable $e)
		{
			\System::log('Vorlage der Schlüssel-E-Mail konnte nicht erzeugt werden ('.$vorlage.'): '.$e->getMessage(), __METHOD__, TL_ERROR);

			return '';
		}
	}

	/**
	 * Baut den Textteil der Schlüssel-E-Mail einschließlich des Beispielskripts.
	 *
	 * Der Textteil geht IMMER mit hinaus, auch wenn eine HTML-Vorlage gewählt
	 * ist: Manche Postfächer zeigen nur Text, und zum Herauskopieren des
	 * Skripts ist er die verlässlichere Fassung — in HTML-Post gehen dabei
	 * regelmäßig Anführungszeichen und Zeilenumbrüche verloren.
	 *
	 * @param  \Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalTokensModel $objToken
	 * @param  string $vereinsname Klartextname des Vereins, darf leer sein
	 * @return string
	 */
	protected function mailtext($objToken, $vereinsname)
	{
		$w = $this->mailwerte($objToken, $vereinsname);

		$text = "Guten Tag ".$w['name'].",\n\n"
			."für den Verein ".($w['verein'] !== '' ? $w['verein'] : $w['vkz'])." (".$w['vkz'].") wurde ein Zugangsschlüssel\n"
			."für die Vereinslisten-Schnittstelle erzeugt.\n\n"
			."Schlüssel:         ".$w['token']."\n"
			."Vereinskennziffer: ".$w['vkz']."\n"
			."Adresse:           ".$w['adresse']."\n"
			."Empfänger:         ".$w['email']."\n\n"
			."Aufruf im Browser (liefert JSON):\n"
			.$w['aufruf']."\n\n";

		if($w['freigabe'])
		{
			$text .= "HINWEIS: Der Schlüssel ist noch nicht freigeschaltet. Sobald das geschehen\n"
				."ist, liefert der Aufruf die Mitgliederliste.\n\n";
		}

		$text .= "Bitte beachten Sie:\n"
			."- Der Schlüssel gilt nur für den Verein ".$w['vkz'].".\n"
			."- Geben Sie ihn nicht weiter und stellen Sie ihn nicht in öffentlich\n"
			."  lesbaren Quelltext (etwa in Javascript auf Ihrer Website).\n"
			."- Erlaubt sind ".$w['abrufetext']." am Tag. Die Daten stammen aus dem\n"
			."  Wertungsportal des Deutschen Schachbunds und werden zwischengespeichert;\n"
			."  ein Abruf am Tag genügt völlig.\n\n"
			."Das folgende PHP-Skript können Sie unverändert übernehmen. Speichern Sie es\n"
			."als vereinsliste.php auf Ihrem Webspace und rufen Sie es im Browser auf.\n\n"
			."------------------------------------------------------------------------\n"
			.$w['beispiel']
			."------------------------------------------------------------------------\n\n"
			."Mit freundlichen Grüßen\n"
			.$w['absender']."\n";

		return $text;
	}

	/**
	 * Liefert das PHP-Beispielskript mit eingesetztem Schlüssel und Verein.
	 *
	 * Das Skript ist absichtlich vollständig und ohne Abhängigkeiten: Es holt
	 * die Liste per cURL (statt file_get_contents, das auf vielen Webspaces
	 * abgeschaltet ist), fängt die Fehlerfälle ab und gibt eine Tabelle aus.
	 *
	 * @param  string $token   Zugangsschlüssel
	 * @param  string $vkz     Vereinskennziffer
	 * @param  string $adresse Adresse der Schnittstelle ohne Parameter
	 * @return string PHP-Quelltext
	 */
	public static function beispielskript($token, $vkz, $adresse)
	{
		return <<<PHPCODE
<?php
// Mitgliederliste des Vereins {$vkz} aus dem Wertungsportal des DSB.
// Der Schlüssel gehört NICHT in öffentlich lesbaren Quelltext.

\$token   = '{$token}';
\$vkz     = '{$vkz}';
\$adresse = '{$adresse}';

\$ch = curl_init(\$adresse . '?token=' . urlencode(\$token) . '&vkz=' . urlencode(\$vkz));
curl_setopt(\$ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt(\$ch, CURLOPT_TIMEOUT, 30);
\$antwort = curl_exec(\$ch);
\$status  = (int) curl_getinfo(\$ch, CURLINFO_HTTP_CODE);
curl_close(\$ch);

if (\$antwort === false) {
    die('Die Schnittstelle ist nicht erreichbar.');
}

\$daten = json_decode(\$antwort, true);

if (\$status !== 200) {
    die('Fehler ' . \$status . ': ' . (\$daten['meldung'] ?? 'unbekannt'));
}

echo '<h1>' . htmlspecialchars(\$daten['verein']) . ' (' . htmlspecialchars(\$daten['vkz']) . ')</h1>';
echo '<p>' . (int) \$daten['anzahl'] . ' Spieler, Stand: ' . htmlspecialchars(\$daten['stand']) . '</p>';
echo '<table border="1" cellpadding="4"><tr>'
   . '<th>Name</th><th>Jg.</th><th>Mgl.-Nr.</th><th>Status</th><th>DWZ</th><th>Elo</th><th>Titel</th></tr>';

foreach (\$daten['spieler'] as \$spieler) {
    echo '<tr>'
       . '<td>' . htmlspecialchars(\$spieler['nachname'] . ', ' . \$spieler['vorname']) . '</td>'
       . '<td>' . htmlspecialchars(\$spieler['geburtsjahr']) . '</td>'
       . '<td>' . htmlspecialchars(\$spieler['mitgliedsnummer']) . '</td>'
       . '<td>' . htmlspecialchars(\$spieler['status']) . '</td>'
       . '<td>' . (\$spieler['dwz'] ? (int) \$spieler['dwz'] : '') . '</td>'
       . '<td>' . (\$spieler['elo'] ? (int) \$spieler['elo'] : '') . '</td>'
       . '<td>' . htmlspecialchars(\$spieler['titel']) . '</td>'
       . '</tr>';
}

echo '</table>';

PHPCODE;
	}

	/**
	 * Liefert die vollständige Adresse der Schnittstelle.
	 *
	 * Der Pfad steht fest in routing.yml; er wird hier zusammengesetzt statt
	 * über den Router erzeugt, damit die Adresse auch dann stimmt, wenn das
	 * Beispielskript aus dem Backend heraus gebaut wird.
	 *
	 * @return string
	 */
	public static function schnittstellenUrl()
	{
		return rtrim((string) \Environment::get('base'), '/').'/wertungsportal-api/vereinsliste';
	}

}
