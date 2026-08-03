<?php

/**
 * Contao Open Source CMS
 * Copyright (C) 2005-2024 Leo Feyer
 *
 * @link http://www.contao.org
 * @license http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */

namespace Schachbulle\ContaoWertungsportalBundle\Helper;

class Helper extends \Frontend
{
	/**
	 * Current object instance
	 * @var object
	 */
	protected static $instance = null;
	protected static $countries =
	[
		[ 'name' => 'Afghanistan', 'alpha2' => 'AF', 'alpha3' => 'AFG', 'numeric' => '004', 'tld' => '.af', 'ioc' => 'AFG' ],
		[ 'name' => 'Ägypten', 'alpha2' => 'EG', 'alpha3' => 'EGY', 'numeric' => '818', 'tld' => '.eg', 'ioc' => 'EGY' ],
		[ 'name' => 'Åland', 'alpha2' => 'AX', 'alpha3' => 'ALA', 'numeric' => '248', 'tld' => '.ax', 'ioc' => '' ],
		[ 'name' => 'Albanien', 'alpha2' => 'AL', 'alpha3' => 'ALB', 'numeric' => '008', 'tld' => '.al', 'ioc' => 'ALB' ],
		[ 'name' => 'Algerien', 'alpha2' => 'DZ', 'alpha3' => 'DZA', 'numeric' => '012', 'tld' => '.dz', 'ioc' => 'ALG' ],
		[ 'name' => 'Amerikanisch-Samoa', 'alpha2' => 'AS', 'alpha3' => 'ASM', 'numeric' => '016', 'tld' => '.as', 'ioc' => 'ASA' ],
		[ 'name' => 'Amerikanische Jungferninseln', 'alpha2' => 'VI', 'alpha3' => 'VIR', 'numeric' => '850', 'tld' => '.vi', 'ioc' => 'ISV' ],
		[ 'name' => 'Andorra', 'alpha2' => 'AD', 'alpha3' => 'AND', 'numeric' => '020', 'tld' => '.ad', 'ioc' => 'AND' ],
		[ 'name' => 'Angola', 'alpha2' => 'AO', 'alpha3' => 'AGO', 'numeric' => '024', 'tld' => '.ao', 'ioc' => 'ANG' ],
		[ 'name' => 'Anguilla', 'alpha2' => 'AI', 'alpha3' => 'AIA', 'numeric' => '660', 'tld' => '.ai', 'ioc' => '' ],
		[ 'name' => 'Antarktika', 'alpha2' => 'AQ', 'alpha3' => 'ATA', 'numeric' => '010', 'tld' => '.aq', 'ioc' => '' ],
		[ 'name' => 'Antigua und Barbuda', 'alpha2' => 'AG', 'alpha3' => 'ATG', 'numeric' => '028', 'tld' => '.ag', 'ioc' => 'ANT' ],
		[ 'name' => 'Äquatorialguinea', 'alpha2' => 'GQ', 'alpha3' => 'GNQ', 'numeric' => '226', 'tld' => '.gq', 'ioc' => 'GEQ' ],
		[ 'name' => 'Argentinien', 'alpha2' => 'AR', 'alpha3' => 'ARG', 'numeric' => '032', 'tld' => '.ar', 'ioc' => 'ARG' ],
		[ 'name' => 'Armenien', 'alpha2' => 'AM', 'alpha3' => 'ARM', 'numeric' => '051', 'tld' => '.am', 'ioc' => 'ARM' ],
		[ 'name' => 'Aruba', 'alpha2' => 'AW', 'alpha3' => 'ABW', 'numeric' => '533', 'tld' => '.aw', 'ioc' => 'ARU' ],
		[ 'name' => 'Ascension', 'alpha2' => 'AC', 'alpha3' => 'ASC', 'numeric' => '', 'tld' => '.ac', 'ioc' => '' ],
		[ 'name' => 'Aserbaidschan', 'alpha2' => 'AZ', 'alpha3' => 'AZE', 'numeric' => '031', 'tld' => '.az', 'ioc' => 'AZE' ],
		[ 'name' => 'Äthiopien', 'alpha2' => 'ET', 'alpha3' => 'ETH', 'numeric' => '231', 'tld' => '.et', 'ioc' => 'ETH' ],
		[ 'name' => 'Australien', 'alpha2' => 'AU', 'alpha3' => 'AUS', 'numeric' => '036', 'tld' => '.au', 'ioc' => 'AUS' ],
		[ 'name' => 'Bahamas', 'alpha2' => 'BS', 'alpha3' => 'BHS', 'numeric' => '044', 'tld' => '.bs', 'ioc' => 'BAH' ],
		[ 'name' => 'Bahrain', 'alpha2' => 'BH', 'alpha3' => 'BHR', 'numeric' => '048', 'tld' => '.bh', 'ioc' => 'BRN' ],
		[ 'name' => 'Bangladesch', 'alpha2' => 'BD', 'alpha3' => 'BGD', 'numeric' => '050', 'tld' => '.bd', 'ioc' => 'BAN' ],
		[ 'name' => 'Barbados', 'alpha2' => 'BB', 'alpha3' => 'BRB', 'numeric' => '052', 'tld' => '.bb', 'ioc' => 'BAR' ],
		[ 'name' => 'Weißrussland', 'alpha2' => 'BY', 'alpha3' => 'BLR', 'numeric' => '112', 'tld' => '.by', 'ioc' => 'BLR' ],
		[ 'name' => 'Belgien', 'alpha2' => 'BE', 'alpha3' => 'BEL', 'numeric' => '056', 'tld' => '.be', 'ioc' => 'BEL' ],
		[ 'name' => 'Belize', 'alpha2' => 'BZ', 'alpha3' => 'BLZ', 'numeric' => '084', 'tld' => '.bz', 'ioc' => 'BIZ' ],
		[ 'name' => 'Benin', 'alpha2' => 'BJ', 'alpha3' => 'BEN', 'numeric' => '204', 'tld' => '.bj', 'ioc' => 'BEN' ],
		[ 'name' => 'Bermuda', 'alpha2' => 'BM', 'alpha3' => 'BMU', 'numeric' => '060', 'tld' => '.bm', 'ioc' => 'BER' ],
		[ 'name' => 'Bhutan', 'alpha2' => 'BT', 'alpha3' => 'BTN', 'numeric' => '064', 'tld' => '.bt', 'ioc' => 'BHU' ],
		[ 'name' => 'Bolivien', 'alpha2' => 'BO', 'alpha3' => 'BOL', 'numeric' => '068', 'tld' => '.bo', 'ioc' => 'BOL' ],
		[ 'name' => 'Bonaire, Sint Eustatius und Saba', 'alpha2' => 'BQ', 'alpha3' => 'BES', 'numeric' => '535', 'tld' => '', 'ioc' => '' ],
		[ 'name' => 'Bosnien und Herzegowina', 'alpha2' => 'BA', 'alpha3' => 'BIH', 'numeric' => '070', 'tld' => '.ba', 'ioc' => 'BIH' ],
		[ 'name' => 'Botswana', 'alpha2' => 'BW', 'alpha3' => 'BWA', 'numeric' => '072', 'tld' => '.bw', 'ioc' => 'BOT' ],
		[ 'name' => 'Bouvetinsel', 'alpha2' => 'BV', 'alpha3' => 'BVT', 'numeric' => '074', 'tld' => '.bv', 'ioc' => '' ],
		[ 'name' => 'Brasilien', 'alpha2' => 'BR', 'alpha3' => 'BRA', 'numeric' => '076', 'tld' => '.br', 'ioc' => 'BRA' ],
		[ 'name' => 'Britische Jungferninseln', 'alpha2' => 'VG', 'alpha3' => 'VGB', 'numeric' => '092', 'tld' => '.vg', 'ioc' => 'IVB' ],
		[ 'name' => 'Britisches Territorium im Indischen Ozean', 'alpha2' => 'IO', 'alpha3' => 'IOT', 'numeric' => '086', 'tld' => '.io', 'ioc' => '' ],
		[ 'name' => 'Brunei Darussalam', 'alpha2' => 'BN', 'alpha3' => 'BRN', 'numeric' => '096', 'tld' => '.bn', 'ioc' => 'BRU' ],
		[ 'name' => 'Bulgarien', 'alpha2' => 'BG', 'alpha3' => 'BGR', 'numeric' => '100', 'tld' => '.bg', 'ioc' => 'BUL' ],
		[ 'name' => 'Burkina Faso', 'alpha2' => 'BF', 'alpha3' => 'BFA', 'numeric' => '854', 'tld' => '.bf', 'ioc' => 'BUR' ],
		[ 'name' => 'Burma', 'alpha2' => 'BU', 'alpha3' => 'BUR', 'numeric' => '104', 'tld' => '.mm', 'ioc' => '' ],
		[ 'name' => 'Burundi', 'alpha2' => 'BI', 'alpha3' => 'BDI', 'numeric' => '108', 'tld' => '.bi', 'ioc' => 'BDI' ],
		[ 'name' => 'Ceuta, Melilla', 'alpha2' => 'EA', 'alpha3' => '', 'numeric' => '', 'tld' => '', 'ioc' => '' ],
		[ 'name' => 'Chile', 'alpha2' => 'CL', 'alpha3' => 'CHL', 'numeric' => '152', 'tld' => '.cl', 'ioc' => 'CHI' ],
		[ 'name' => 'China', 'alpha2' => 'CN', 'alpha3' => 'CHN', 'numeric' => '156', 'tld' => '.cn', 'ioc' => 'CHN' ],
		[ 'name' => 'Clipperton', 'alpha2' => 'CP', 'alpha3' => 'CPT', 'numeric' => '', 'tld' => '', 'ioc' => '' ],
		[ 'name' => 'Cookinseln', 'alpha2' => 'CK', 'alpha3' => 'COK', 'numeric' => '184', 'tld' => '.ck', 'ioc' => 'COK' ],
		[ 'name' => 'Costa Rica', 'alpha2' => 'CR', 'alpha3' => 'CRI', 'numeric' => '188', 'tld' => '.cr', 'ioc' => 'CRC' ],
		[ 'name' => 'Elfenbeinküste', 'alpha2' => 'CI', 'alpha3' => 'CIV', 'numeric' => '384', 'tld' => '.ci', 'ioc' => 'CIV' ],
		[ 'name' => 'Curaçao', 'alpha2' => 'CW', 'alpha3' => 'CUW', 'numeric' => '531', 'tld' => '.cw', 'ioc' => '' ],
		[ 'name' => 'Dänemark', 'alpha2' => 'DK', 'alpha3' => 'DNK', 'numeric' => '208', 'tld' => '.dk', 'ioc' => 'DEN' ],
		[ 'name' => 'DDR', 'alpha2' => 'DD', 'alpha3' => '', 'numeric' => '', 'tld' => '.dd', 'ioc' => 'GDR' ],
		[ 'name' => 'BRD', 'alpha2' => 'DE', 'alpha3' => 'DEU', 'numeric' => '276', 'tld' => '.de', 'ioc' => 'FRG' ],
		[ 'name' => 'Deutschland', 'alpha2' => 'DE', 'alpha3' => 'DEU', 'numeric' => '276', 'tld' => '.de', 'ioc' => 'GER' ],
		[ 'name' => 'Diego Garcia', 'alpha2' => 'DG', 'alpha3' => 'DGA', 'numeric' => '', 'tld' => '', 'ioc' => '' ],
		[ 'name' => 'Dominica', 'alpha2' => 'DM', 'alpha3' => 'DMA', 'numeric' => '212', 'tld' => '.dm', 'ioc' => 'DMA' ],
		[ 'name' => 'Dominikanische Republik', 'alpha2' => 'DO', 'alpha3' => 'DOM', 'numeric' => '214', 'tld' => '.do', 'ioc' => 'DOM' ],
		[ 'name' => 'Dschibuti', 'alpha2' => 'DJ', 'alpha3' => 'DJI', 'numeric' => '262', 'tld' => '.dj', 'ioc' => 'DJI' ],
		[ 'name' => 'Ekuador', 'alpha2' => 'EC', 'alpha3' => 'ECU', 'numeric' => '218', 'tld' => '.ec', 'ioc' => 'ECU' ],
		[ 'name' => 'El Salvador', 'alpha2' => 'SV', 'alpha3' => 'SLV', 'numeric' => '222', 'tld' => '.sv', 'ioc' => 'ESA' ],
		[ 'name' => 'Eritrea', 'alpha2' => 'ER', 'alpha3' => 'ERI', 'numeric' => '232', 'tld' => '.er', 'ioc' => 'ERI' ],
		[ 'name' => 'Estland', 'alpha2' => 'EE', 'alpha3' => 'EST', 'numeric' => '233', 'tld' => '.ee', 'ioc' => 'EST' ],
		[ 'name' => 'Falklandinseln', 'alpha2' => 'FK', 'alpha3' => 'FLK', 'numeric' => '238', 'tld' => '.fk', 'ioc' => '' ],
		[ 'name' => 'Färöer', 'alpha2' => 'FO', 'alpha3' => 'FRO', 'numeric' => '234', 'tld' => '.fo', 'ioc' => 'FRO' ],
		[ 'name' => 'Fidschi', 'alpha2' => 'FJ', 'alpha3' => 'FJI', 'numeric' => '242', 'tld' => '.fj', 'ioc' => 'FIJ' ],
		[ 'name' => 'Finnland', 'alpha2' => 'FI', 'alpha3' => 'FIN', 'numeric' => '246', 'tld' => '.fi', 'ioc' => 'FIN' ],
		[ 'name' => 'Frankreich', 'alpha2' => 'FR', 'alpha3' => 'FRA', 'numeric' => '250', 'tld' => '.fr', 'ioc' => 'FRA' ],
		[ 'name' => 'Französisch-Guayana', 'alpha2' => 'GF', 'alpha3' => 'GUF', 'numeric' => '254', 'tld' => '.gf', 'ioc' => '' ],
		[ 'name' => 'Französisch-Polynesien', 'alpha2' => 'PF', 'alpha3' => 'PYF', 'numeric' => '258', 'tld' => '.pf', 'ioc' => '' ],
		[ 'name' => 'Französische Süd- und Antarktisgebiete', 'alpha2' => 'TF', 'alpha3' => 'ATF', 'numeric' => '260', 'tld' => '.tf', 'ioc' => '' ],
		[ 'name' => 'Gabun', 'alpha2' => 'GA', 'alpha3' => 'GAB', 'numeric' => '266', 'tld' => '.ga', 'ioc' => 'GAB' ],
		[ 'name' => 'Gambia', 'alpha2' => 'GM', 'alpha3' => 'GMB', 'numeric' => '270', 'tld' => '.gm', 'ioc' => 'GAM' ],
		[ 'name' => 'Georgien', 'alpha2' => 'GE', 'alpha3' => 'GEO', 'numeric' => '268', 'tld' => '.ge', 'ioc' => 'GEO' ],
		[ 'name' => 'Ghana', 'alpha2' => 'GH', 'alpha3' => 'GHA', 'numeric' => '288', 'tld' => '.gh', 'ioc' => 'GHA' ],
		[ 'name' => 'Gibraltar', 'alpha2' => 'GI', 'alpha3' => 'GIB', 'numeric' => '292', 'tld' => '.gi', 'ioc' => '' ],
		[ 'name' => 'Grenada', 'alpha2' => 'GD', 'alpha3' => 'GRD', 'numeric' => '308', 'tld' => '.gd', 'ioc' => 'GRN' ],
		[ 'name' => 'Griechenland', 'alpha2' => 'GR', 'alpha3' => 'GRC', 'numeric' => '300', 'tld' => '.gr', 'ioc' => 'GRE' ],
		[ 'name' => 'Grönland', 'alpha2' => 'GL', 'alpha3' => 'GRL', 'numeric' => '304', 'tld' => '.gl', 'ioc' => '' ],
		[ 'name' => 'Guadeloupe', 'alpha2' => 'GP', 'alpha3' => 'GLP', 'numeric' => '312', 'tld' => '.gp', 'ioc' => '' ],
		[ 'name' => 'Guam', 'alpha2' => 'GU', 'alpha3' => 'GUM', 'numeric' => '316', 'tld' => '.gu', 'ioc' => 'GUM' ],
		[ 'name' => 'Guatemala', 'alpha2' => 'GT', 'alpha3' => 'GTM', 'numeric' => '320', 'tld' => '.gt', 'ioc' => 'GUA' ],
		[ 'name' => 'Guernsey', 'alpha2' => 'GG', 'alpha3' => 'GGY', 'numeric' => '831', 'tld' => '.gg', 'ioc' => '' ],
		[ 'name' => 'Guinea', 'alpha2' => 'GN', 'alpha3' => 'GIN', 'numeric' => '324', 'tld' => '.gn', 'ioc' => 'GUI' ],
		[ 'name' => 'Guinea-Bissau', 'alpha2' => 'GW', 'alpha3' => 'GNB', 'numeric' => '624', 'tld' => '.gw', 'ioc' => 'GBS' ],
		[ 'name' => 'Guyana', 'alpha2' => 'GY', 'alpha3' => 'GUY', 'numeric' => '328', 'tld' => '.gy', 'ioc' => 'GUY' ],
		[ 'name' => 'Haiti', 'alpha2' => 'HT', 'alpha3' => 'HTI', 'numeric' => '332', 'tld' => '.ht', 'ioc' => 'HAI' ],
		[ 'name' => 'Heard und McDonaldinseln', 'alpha2' => 'HM', 'alpha3' => 'HMD', 'numeric' => '334', 'tld' => '.hm', 'ioc' => '' ],
		[ 'name' => 'Honduras', 'alpha2' => 'HN', 'alpha3' => 'HND', 'numeric' => '340', 'tld' => '.hn', 'ioc' => 'HON' ],
		[ 'name' => 'Hongkong', 'alpha2' => 'HK', 'alpha3' => 'HKG', 'numeric' => '344', 'tld' => '.hk', 'ioc' => 'HKG' ],
		[ 'name' => 'Indien', 'alpha2' => 'IN', 'alpha3' => 'IND', 'numeric' => '356', 'tld' => '.in', 'ioc' => 'IND' ],
		[ 'name' => 'Indonesien', 'alpha2' => 'ID', 'alpha3' => 'IDN', 'numeric' => '360', 'tld' => '.id', 'ioc' => 'INA' ],
		[ 'name' => 'Insel Man', 'alpha2' => 'IM', 'alpha3' => 'IMN', 'numeric' => '833', 'tld' => '.im', 'ioc' => '' ],
		[ 'name' => 'Irak', 'alpha2' => 'IQ', 'alpha3' => 'IRQ', 'numeric' => '368', 'tld' => '.iq', 'ioc' => 'IRQ' ],
		[ 'name' => 'Iran', 'alpha2' => 'IR', 'alpha3' => 'IRN', 'numeric' => '364', 'tld' => '.ir', 'ioc' => 'IRI' ],
		[ 'name' => 'Irland', 'alpha2' => 'IE', 'alpha3' => 'IRL', 'numeric' => '372', 'tld' => '.ie', 'ioc' => 'IRL' ],
		[ 'name' => 'Island', 'alpha2' => 'IS', 'alpha3' => 'ISL', 'numeric' => '352', 'tld' => '.is', 'ioc' => 'ISL' ],
		[ 'name' => 'Israel', 'alpha2' => 'IL', 'alpha3' => 'ISR', 'numeric' => '376', 'tld' => '.il', 'ioc' => 'ISR' ],
		[ 'name' => 'Italien', 'alpha2' => 'IT', 'alpha3' => 'ITA', 'numeric' => '380', 'tld' => '.it', 'ioc' => 'ITA' ],
		[ 'name' => 'Jamaika', 'alpha2' => 'JM', 'alpha3' => 'JAM', 'numeric' => '388', 'tld' => '.jm', 'ioc' => 'JAM' ],
		[ 'name' => 'Japan', 'alpha2' => 'JP', 'alpha3' => 'JPN', 'numeric' => '392', 'tld' => '.jp', 'ioc' => 'JPN' ],
		[ 'name' => 'Jemen', 'alpha2' => 'YE', 'alpha3' => 'YEM', 'numeric' => '887', 'tld' => '.ye', 'ioc' => 'YEM' ],
		[ 'name' => 'Jersey', 'alpha2' => 'JE', 'alpha3' => 'JEY', 'numeric' => '832', 'tld' => '.je', 'ioc' => '' ],
		[ 'name' => 'Jordanien', 'alpha2' => 'JO', 'alpha3' => 'JOR', 'numeric' => '400', 'tld' => '.jo', 'ioc' => 'JOR' ],
		[ 'name' => 'Jugoslawien', 'alpha2' => 'YU', 'alpha3' => 'YUG', 'numeric' => '891', 'tld' => '.yu', 'ioc' => 'YUG' ],
		[ 'name' => 'Kaimaninseln', 'alpha2' => 'KY', 'alpha3' => 'CYM', 'numeric' => '136', 'tld' => '.ky', 'ioc' => 'CAY' ],
		[ 'name' => 'Kambodscha', 'alpha2' => 'KH', 'alpha3' => 'KHM', 'numeric' => '116', 'tld' => '.kh', 'ioc' => 'CAM' ],
		[ 'name' => 'Kamerun', 'alpha2' => 'CM', 'alpha3' => 'CMR', 'numeric' => '120', 'tld' => '.cm', 'ioc' => 'CMR' ],
		[ 'name' => 'Kanada', 'alpha2' => 'CA', 'alpha3' => 'CAN', 'numeric' => '124', 'tld' => '.ca', 'ioc' => 'CAN' ],
		[ 'name' => 'Kanarische Inseln', 'alpha2' => 'IC', 'alpha3' => '', 'numeric' => '', 'tld' => '', 'ioc' => '' ],
		[ 'name' => 'Kap Verde', 'alpha2' => 'CV', 'alpha3' => 'CPV', 'numeric' => '132', 'tld' => '.cv', 'ioc' => 'CPV' ],
		[ 'name' => 'Kasachstan', 'alpha2' => 'KZ', 'alpha3' => 'KAZ', 'numeric' => '398', 'tld' => '.kz', 'ioc' => 'KAZ' ],
		[ 'name' => 'Katar', 'alpha2' => 'QA', 'alpha3' => 'QAT', 'numeric' => '634', 'tld' => '.qa', 'ioc' => 'QAT' ],
		[ 'name' => 'Kenia', 'alpha2' => 'KE', 'alpha3' => 'KEN', 'numeric' => '404', 'tld' => '.ke', 'ioc' => 'KEN' ],
		[ 'name' => 'Kirgisistan', 'alpha2' => 'KG', 'alpha3' => 'KGZ', 'numeric' => '417', 'tld' => '.kg', 'ioc' => 'KGZ' ],
		[ 'name' => 'Kiribati', 'alpha2' => 'KI', 'alpha3' => 'KIR', 'numeric' => '296', 'tld' => '.ki', 'ioc' => 'KIR' ],
		[ 'name' => 'Kokosinseln', 'alpha2' => 'CC', 'alpha3' => 'CCK', 'numeric' => '166', 'tld' => '.cc', 'ioc' => '' ],
		[ 'name' => 'Kolumbien', 'alpha2' => 'CO', 'alpha3' => 'COL', 'numeric' => '170', 'tld' => '.co', 'ioc' => 'COL' ],
		[ 'name' => 'Komoren', 'alpha2' => 'KM', 'alpha3' => 'COM', 'numeric' => '174', 'tld' => '.km', 'ioc' => 'COM' ],
		[ 'name' => 'Kongo, Demokratische Republik', 'alpha2' => 'CD', 'alpha3' => 'COD', 'numeric' => '180', 'tld' => '.cd', 'ioc' => 'COD' ],
		[ 'name' => 'Kongo, Republik', 'alpha2' => 'CG', 'alpha3' => 'COG', 'numeric' => '178', 'tld' => '.cg', 'ioc' => 'CGO' ],
		[ 'name' => 'Nordkorea', 'alpha2' => 'KP', 'alpha3' => 'PRK', 'numeric' => '408', 'tld' => '.kp', 'ioc' => 'PRK' ],
		[ 'name' => 'Südkorea', 'alpha2' => 'KR', 'alpha3' => 'KOR', 'numeric' => '410', 'tld' => '.kr', 'ioc' => 'KOR' ],
		[ 'name' => 'Kosovo', 'alpha2' => 'XK', 'alpha3' => 'XKX', 'numeric' => '', 'tld' => '', 'ioc' => 'KOS' ],
		[ 'name' => 'Kroatien', 'alpha2' => 'HR', 'alpha3' => 'HRV', 'numeric' => '191', 'tld' => '.hr', 'ioc' => 'CRO' ],
		[ 'name' => 'Kuba', 'alpha2' => 'CU', 'alpha3' => 'CUB', 'numeric' => '192', 'tld' => '.cu', 'ioc' => 'CUB' ],
		[ 'name' => 'Kuwait', 'alpha2' => 'KW', 'alpha3' => 'KWT', 'numeric' => '414', 'tld' => '.kw', 'ioc' => 'KUW' ],
		[ 'name' => 'Laos', 'alpha2' => 'LA', 'alpha3' => 'LAO', 'numeric' => '418', 'tld' => '.la', 'ioc' => 'LAO' ],
		[ 'name' => 'Lesotho', 'alpha2' => 'LS', 'alpha3' => 'LSO', 'numeric' => '426', 'tld' => '.ls', 'ioc' => 'LES' ],
		[ 'name' => 'Lettland', 'alpha2' => 'LV', 'alpha3' => 'LVA', 'numeric' => '428', 'tld' => '.lv', 'ioc' => 'LAT' ],
		[ 'name' => 'Libanon', 'alpha2' => 'LB', 'alpha3' => 'LBN', 'numeric' => '422', 'tld' => '.lb', 'ioc' => 'LIB' ],
		[ 'name' => 'Liberia', 'alpha2' => 'LR', 'alpha3' => 'LBR', 'numeric' => '430', 'tld' => '.lr', 'ioc' => 'LBR' ],
		[ 'name' => 'Libyen', 'alpha2' => 'LY', 'alpha3' => 'LBY', 'numeric' => '434', 'tld' => '.ly', 'ioc' => 'LBA' ],
		[ 'name' => 'Liechtenstein', 'alpha2' => 'LI', 'alpha3' => 'LIE', 'numeric' => '438', 'tld' => '.li', 'ioc' => 'LIE' ],
		[ 'name' => 'Litauen', 'alpha2' => 'LT', 'alpha3' => 'LTU', 'numeric' => '440', 'tld' => '.lt', 'ioc' => 'LTU' ],
		[ 'name' => 'Luxemburg', 'alpha2' => 'LU', 'alpha3' => 'LUX', 'numeric' => '442', 'tld' => '.lu', 'ioc' => 'LUX' ],
		[ 'name' => 'Macau', 'alpha2' => 'MO', 'alpha3' => 'MAC', 'numeric' => '446', 'tld' => '.mo', 'ioc' => '' ],
		[ 'name' => 'Madagaskar', 'alpha2' => 'MG', 'alpha3' => 'MDG', 'numeric' => '450', 'tld' => '.mg', 'ioc' => 'MAD' ],
		[ 'name' => 'Malawi', 'alpha2' => 'MW', 'alpha3' => 'MWI', 'numeric' => '454', 'tld' => '.mw', 'ioc' => 'MAW' ],
		[ 'name' => 'Malaysia', 'alpha2' => 'MY', 'alpha3' => 'MYS', 'numeric' => '458', 'tld' => '.my', 'ioc' => 'MAS' ],
		[ 'name' => 'Malediven', 'alpha2' => 'MV', 'alpha3' => 'MDV', 'numeric' => '462', 'tld' => '.mv', 'ioc' => 'MDV' ],
		[ 'name' => 'Mali', 'alpha2' => 'ML', 'alpha3' => 'MLI', 'numeric' => '466', 'tld' => '.ml', 'ioc' => 'MLI' ],
		[ 'name' => 'Malta', 'alpha2' => 'MT', 'alpha3' => 'MLT', 'numeric' => '470', 'tld' => '.mt', 'ioc' => 'MLT' ],
		[ 'name' => 'Marokko', 'alpha2' => 'MA', 'alpha3' => 'MAR', 'numeric' => '504', 'tld' => '.ma', 'ioc' => 'MAR' ],
		[ 'name' => 'Marshallinseln', 'alpha2' => 'MH', 'alpha3' => 'MHL', 'numeric' => '584', 'tld' => '.mh', 'ioc' => 'MHL' ],
		[ 'name' => 'Martinique', 'alpha2' => 'MQ', 'alpha3' => 'MTQ', 'numeric' => '474', 'tld' => '.mq', 'ioc' => '' ],
		[ 'name' => 'Mauretanien', 'alpha2' => 'MR', 'alpha3' => 'MRT', 'numeric' => '478', 'tld' => '.mr', 'ioc' => 'MTN' ],
		[ 'name' => 'Mauritius', 'alpha2' => 'MU', 'alpha3' => 'MUS', 'numeric' => '480', 'tld' => '.mu', 'ioc' => 'MRI' ],
		[ 'name' => 'Mayotte', 'alpha2' => 'YT', 'alpha3' => 'MYT', 'numeric' => '175', 'tld' => '.yt', 'ioc' => '' ],
		[ 'name' => 'Mexiko', 'alpha2' => 'MX', 'alpha3' => 'MEX', 'numeric' => '484', 'tld' => '.mx', 'ioc' => 'MEX' ],
		[ 'name' => 'Mikronesien', 'alpha2' => 'FM', 'alpha3' => 'FSM', 'numeric' => '583', 'tld' => '.fm', 'ioc' => 'FSM' ],
		[ 'name' => 'Moldawien', 'alpha2' => 'MD', 'alpha3' => 'MDA', 'numeric' => '498', 'tld' => '.md', 'ioc' => 'MDA' ],
		[ 'name' => 'Monaco', 'alpha2' => 'MC', 'alpha3' => 'MCO', 'numeric' => '492', 'tld' => '.mc', 'ioc' => 'MON' ],
		[ 'name' => 'Mongolei', 'alpha2' => 'MN', 'alpha3' => 'MNG', 'numeric' => '496', 'tld' => '.mn', 'ioc' => 'MGL' ],
		[ 'name' => 'Montenegro', 'alpha2' => 'ME', 'alpha3' => 'MNE', 'numeric' => '499', 'tld' => '.me', 'ioc' => 'MNE' ],
		[ 'name' => 'Montserrat', 'alpha2' => 'MS', 'alpha3' => 'MSR', 'numeric' => '500', 'tld' => '.ms', 'ioc' => '' ],
		[ 'name' => 'Mosambik', 'alpha2' => 'MZ', 'alpha3' => 'MOZ', 'numeric' => '508', 'tld' => '.mz', 'ioc' => 'MOZ' ],
		[ 'name' => 'Myanmar', 'alpha2' => 'MM', 'alpha3' => 'MMR', 'numeric' => '104', 'tld' => '.mm', 'ioc' => 'MYA' ],
		[ 'name' => 'Namibia', 'alpha2' => 'NA', 'alpha3' => 'NAM', 'numeric' => '516', 'tld' => '.na', 'ioc' => 'NAM' ],
		[ 'name' => 'Nauru', 'alpha2' => 'NR', 'alpha3' => 'NRU', 'numeric' => '520', 'tld' => '.nr', 'ioc' => 'NRU' ],
		[ 'name' => 'Nepal', 'alpha2' => 'NP', 'alpha3' => 'NPL', 'numeric' => '524', 'tld' => '.np', 'ioc' => 'NEP' ],
		[ 'name' => 'Neukaledonien', 'alpha2' => 'NC', 'alpha3' => 'NCL', 'numeric' => '540', 'tld' => '.nc', 'ioc' => '' ],
		[ 'name' => 'Neuseeland', 'alpha2' => 'NZ', 'alpha3' => 'NZL', 'numeric' => '554', 'tld' => '.nz', 'ioc' => 'NZL' ],
		[ 'name' => 'Neutrale Zone', 'alpha2' => 'NT', 'alpha3' => 'NTZ', 'numeric' => '536', 'tld' => '', 'ioc' => '' ],
		[ 'name' => 'Nicaragua', 'alpha2' => 'NI', 'alpha3' => 'NIC', 'numeric' => '558', 'tld' => '.ni', 'ioc' => 'NCA' ],
		[ 'name' => 'Niederlande', 'alpha2' => 'NL', 'alpha3' => 'NLD', 'numeric' => '528', 'tld' => '.nl', 'ioc' => 'NED' ],
		[ 'name' => 'Niederländische Antillen', 'alpha2' => 'AN', 'alpha3' => 'ANT', 'numeric' => '530', 'tld' => '.an', 'ioc' => 'AHO' ],
		[ 'name' => 'Niger', 'alpha2' => 'NE', 'alpha3' => 'NER', 'numeric' => '562', 'tld' => '.ne', 'ioc' => 'NIG' ],
		[ 'name' => 'Nigeria', 'alpha2' => 'NG', 'alpha3' => 'NGA', 'numeric' => '566', 'tld' => '.ng', 'ioc' => 'NGR' ],
		[ 'name' => 'Niue', 'alpha2' => 'NU', 'alpha3' => 'NIU', 'numeric' => '570', 'tld' => '.nu', 'ioc' => '' ],
		[ 'name' => 'Nördliche Marianen', 'alpha2' => 'MP', 'alpha3' => 'MNP', 'numeric' => '580', 'tld' => '.mp', 'ioc' => '' ],
		[ 'name' => 'Nordmazedonien', 'alpha2' => 'MK', 'alpha3' => 'MKD', 'numeric' => '807', 'tld' => '.mk', 'ioc' => 'MKD' ],
		[ 'name' => 'Norfolkinsel', 'alpha2' => 'NF', 'alpha3' => 'NFK', 'numeric' => '574', 'tld' => '.nf', 'ioc' => '' ],
		[ 'name' => 'Norwegen', 'alpha2' => 'NO', 'alpha3' => 'NOR', 'numeric' => '578', 'tld' => '.no', 'ioc' => 'NOR' ],
		[ 'name' => 'Oman', 'alpha2' => 'OM', 'alpha3' => 'OMN', 'numeric' => '512', 'tld' => '.om', 'ioc' => 'OMA' ],
		[ 'name' => 'Österreich', 'alpha2' => 'AT', 'alpha3' => 'AUT', 'numeric' => '040', 'tld' => '.at', 'ioc' => 'AUT' ],
		[ 'name' => 'Osttimor', 'alpha2' => 'TL    )', 'alpha3' => 'TLS', 'numeric' => '626', 'tld' => '.tl', 'ioc' => 'TLS' ],
		[ 'name' => 'Pakistan', 'alpha2' => 'PK', 'alpha3' => 'PAK', 'numeric' => '586', 'tld' => '.pk', 'ioc' => 'PAK' ],
		[ 'name' => 'Palästina', 'alpha2' => 'PS', 'alpha3' => 'PSE', 'numeric' => '275', 'tld' => '.ps', 'ioc' => 'PLE' ],
		[ 'name' => 'Palau', 'alpha2' => 'PW', 'alpha3' => 'PLW', 'numeric' => '585', 'tld' => '.pw', 'ioc' => 'PLW' ],
		[ 'name' => 'Panama', 'alpha2' => 'PA', 'alpha3' => 'PAN', 'numeric' => '591', 'tld' => '.pa', 'ioc' => 'PAN' ],
		[ 'name' => 'Papua-Neuguinea', 'alpha2' => 'PG', 'alpha3' => 'PNG', 'numeric' => '598', 'tld' => '.pg', 'ioc' => 'PNG' ],
		[ 'name' => 'Paraguay', 'alpha2' => 'PY', 'alpha3' => 'PRY', 'numeric' => '600', 'tld' => '.py', 'ioc' => 'PAR' ],
		[ 'name' => 'Peru', 'alpha2' => 'PE', 'alpha3' => 'PER', 'numeric' => '604', 'tld' => '.pe', 'ioc' => 'PER' ],
		[ 'name' => 'Philippinen', 'alpha2' => 'PH', 'alpha3' => 'PHL', 'numeric' => '608', 'tld' => '.ph', 'ioc' => 'PHI' ],
		[ 'name' => 'Pitcairninseln', 'alpha2' => 'PN', 'alpha3' => 'PCN', 'numeric' => '612', 'tld' => '.pn', 'ioc' => '' ],
		[ 'name' => 'Polen', 'alpha2' => 'PL', 'alpha3' => 'POL', 'numeric' => '616', 'tld' => '.pl', 'ioc' => 'POL' ],
		[ 'name' => 'Portugal', 'alpha2' => 'PT', 'alpha3' => 'PRT', 'numeric' => '620', 'tld' => '.pt', 'ioc' => 'POR' ],
		[ 'name' => 'Puerto Rico', 'alpha2' => 'PR', 'alpha3' => 'PRI', 'numeric' => '630', 'tld' => '.pr', 'ioc' => 'PUR' ],
		[ 'name' => 'Réunion', 'alpha2' => 'RE', 'alpha3' => 'REU', 'numeric' => '638', 'tld' => '.re', 'ioc' => '' ],
		[ 'name' => 'Ruanda', 'alpha2' => 'RW', 'alpha3' => 'RWA', 'numeric' => '646', 'tld' => '.rw', 'ioc' => 'RWA' ],
		[ 'name' => 'Rumänien', 'alpha2' => 'RO', 'alpha3' => 'ROU', 'numeric' => '642', 'tld' => '.ro', 'ioc' => 'ROU' ],
		[ 'name' => 'Russland', 'alpha2' => 'RU', 'alpha3' => 'RUS', 'numeric' => '643', 'tld' => '.ru', 'ioc' => 'RUS' ],
		[ 'name' => 'Salomonen', 'alpha2' => 'SB', 'alpha3' => 'SLB', 'numeric' => '090', 'tld' => '.sb', 'ioc' => 'SOL' ],
		[ 'name' => 'Saint-Barthélemy', 'alpha2' => 'BL', 'alpha3' => 'BLM', 'numeric' => '652', 'tld' => '.bl', 'ioc' => '' ],
		[ 'name' => 'Saint-Martin', 'alpha2' => 'MF', 'alpha3' => 'MAF', 'numeric' => '663', 'tld' => '.mf', 'ioc' => '' ],
		[ 'name' => 'Sambia', 'alpha2' => 'ZM', 'alpha3' => 'ZMB', 'numeric' => '894', 'tld' => '.zm', 'ioc' => 'ZAM' ],
		[ 'name' => 'Samoa', 'alpha2' => 'WS', 'alpha3' => 'WSM', 'numeric' => '882', 'tld' => '.ws', 'ioc' => 'SAM' ],
		[ 'name' => 'San Marino', 'alpha2' => 'SM', 'alpha3' => 'SMR', 'numeric' => '674', 'tld' => '.sm', 'ioc' => 'SMR' ],
		[ 'name' => 'São Tomé und Príncipe', 'alpha2' => 'ST', 'alpha3' => 'STP', 'numeric' => '678', 'tld' => '.st', 'ioc' => 'STP' ],
		[ 'name' => 'Saudi-Arabien', 'alpha2' => 'SA', 'alpha3' => 'SAU', 'numeric' => '682', 'tld' => '.sa', 'ioc' => 'KSA' ],
		[ 'name' => 'Schweden', 'alpha2' => 'SE', 'alpha3' => 'SWE', 'numeric' => '752', 'tld' => '.se', 'ioc' => 'SWE' ],
		[ 'name' => 'Schweiz', 'alpha2' => 'CH', 'alpha3' => 'CHE', 'numeric' => '756', 'tld' => '.ch', 'ioc' => 'SUI' ],
		[ 'name' => 'Senegal', 'alpha2' => 'SN', 'alpha3' => 'SEN', 'numeric' => '686', 'tld' => '.sn', 'ioc' => 'SEN' ],
		[ 'name' => 'Serbien', 'alpha2' => 'RS', 'alpha3' => 'SRB', 'numeric' => '688', 'tld' => '.rs', 'ioc' => 'SRB' ],
		[ 'name' => 'Serbien und Montenegro', 'alpha2' => 'CS', 'alpha3' => 'SCG', 'numeric' => '891', 'tld' => '.yu', 'ioc' => 'SCG' ],
		[ 'name' => 'Seychellen', 'alpha2' => 'SC', 'alpha3' => 'SYC', 'numeric' => '690', 'tld' => '.sc', 'ioc' => 'SEY' ],
		[ 'name' => 'Sierra Leone', 'alpha2' => 'SL', 'alpha3' => 'SLE', 'numeric' => '694', 'tld' => '.sl', 'ioc' => 'SLE' ],
		[ 'name' => 'Simbabwe', 'alpha2' => 'ZW', 'alpha3' => 'ZWE', 'numeric' => '716', 'tld' => '.zw', 'ioc' => 'ZIM' ],
		[ 'name' => 'Singapur', 'alpha2' => 'SG', 'alpha3' => 'SGP', 'numeric' => '702', 'tld' => '.sg', 'ioc' => 'SGP' ],
		[ 'name' => 'Sint Maarten', 'alpha2' => 'SX', 'alpha3' => 'SXM', 'numeric' => '534', 'tld' => '', 'ioc' => '' ],
		[ 'name' => 'Slowakei', 'alpha2' => 'SK', 'alpha3' => 'SVK', 'numeric' => '703', 'tld' => '.sk', 'ioc' => 'SVK' ],
		[ 'name' => 'Slowenien', 'alpha2' => 'SI', 'alpha3' => 'SVN', 'numeric' => '705', 'tld' => '.si', 'ioc' => 'SLO' ],
		[ 'name' => 'Somalia', 'alpha2' => 'SO', 'alpha3' => 'SOM', 'numeric' => '706', 'tld' => '.so', 'ioc' => 'SOM' ],
		[ 'name' => 'Spanien', 'alpha2' => 'ES', 'alpha3' => 'ESP', 'numeric' => '724', 'tld' => '.es', 'ioc' => 'ESP' ],
		[ 'name' => 'Sri Lanka', 'alpha2' => 'LK', 'alpha3' => 'LKA', 'numeric' => '144', 'tld' => '.lk', 'ioc' => 'SRI' ],
		[ 'name' => 'St. Helena', 'alpha2' => 'SH', 'alpha3' => 'SHN', 'numeric' => '654', 'tld' => '.sh', 'ioc' => '' ],
		[ 'name' => 'St. Kitts und Nevis', 'alpha2' => 'KN', 'alpha3' => 'KNA', 'numeric' => '659', 'tld' => '.kn', 'ioc' => 'SKN' ],
		[ 'name' => 'St. Lucia', 'alpha2' => 'LC', 'alpha3' => 'LCA', 'numeric' => '662', 'tld' => '.lc', 'ioc' => 'LCA' ],
		[ 'name' => 'Saint-Pierre und Miquelon', 'alpha2' => 'PM', 'alpha3' => 'SPM', 'numeric' => '666', 'tld' => '.pm', 'ioc' => '' ],
		[ 'name' => 'St. Vincent und die Grenadinen', 'alpha2' => 'VC', 'alpha3' => 'VCT', 'numeric' => '670', 'tld' => '.vc', 'ioc' => 'VIN' ],
		[ 'name' => 'Südafrika', 'alpha2' => 'ZA', 'alpha3' => 'ZAF', 'numeric' => '710', 'tld' => '.za', 'ioc' => 'RSA' ],
		[ 'name' => 'Sudan', 'alpha2' => 'SD', 'alpha3' => 'SDN', 'numeric' => '729', 'tld' => '.sd', 'ioc' => 'SUD' ],
		[ 'name' => 'Südgeorgien und die Südlichen Sandwichinseln', 'alpha2' => 'GS', 'alpha3' => 'SGS', 'numeric' => '239', 'tld' => '.gs', 'ioc' => '' ],
		[ 'name' => 'Südsudan', 'alpha2' => 'SS', 'alpha3' => 'SSD', 'numeric' => '728', 'tld' => '.ss', 'ioc' => 'SSD' ],
		[ 'name' => 'Suriname', 'alpha2' => 'SR', 'alpha3' => 'SUR', 'numeric' => '740', 'tld' => '.sr', 'ioc' => 'SUR' ],
		[ 'name' => 'Svalbard und Jan Mayen', 'alpha2' => 'SJ', 'alpha3' => 'SJM', 'numeric' => '744', 'tld' => '.sj', 'ioc' => '' ],
		[ 'name' => 'Swasiland', 'alpha2' => 'SZ', 'alpha3' => 'SWZ', 'numeric' => '748', 'tld' => '.sz', 'ioc' => 'SWZ' ],
		[ 'name' => 'Syrien', 'alpha2' => 'SY', 'alpha3' => 'SYR', 'numeric' => '760', 'tld' => '.sy', 'ioc' => 'SYR' ],
		[ 'name' => 'Tadschikistan', 'alpha2' => 'TJ', 'alpha3' => 'TJK', 'numeric' => '762', 'tld' => '.tj', 'ioc' => 'TJK' ],
		[ 'name' => 'Republik China', 'alpha2' => 'TW', 'alpha3' => 'TWN', 'numeric' => '158', 'tld' => '.tw', 'ioc' => 'TPE' ],
		[ 'name' => 'Tansania', 'alpha2' => 'TZ', 'alpha3' => 'TZA', 'numeric' => '834', 'tld' => '.tz', 'ioc' => 'TAN' ],
		[ 'name' => 'Thailand', 'alpha2' => 'TH', 'alpha3' => 'THA', 'numeric' => '764', 'tld' => '.th', 'ioc' => 'THA' ],
		[ 'name' => 'Togo', 'alpha2' => 'TG', 'alpha3' => 'TGO', 'numeric' => '768', 'tld' => '.tg', 'ioc' => 'TOG' ],
		[ 'name' => 'Tokelau', 'alpha2' => 'TK', 'alpha3' => 'TKL', 'numeric' => '772', 'tld' => '.tk', 'ioc' => '' ],
		[ 'name' => 'Tonga', 'alpha2' => 'TO', 'alpha3' => 'TON', 'numeric' => '776', 'tld' => '.to', 'ioc' => 'TGA' ],
		[ 'name' => 'Trinidad und Tobago', 'alpha2' => 'TT', 'alpha3' => 'TTO', 'numeric' => '780', 'tld' => '.tt', 'ioc' => 'TRI' ],
		[ 'name' => 'Tristan da Cunha', 'alpha2' => 'TA', 'alpha3' => 'TAA', 'numeric' => '', 'tld' => '', 'ioc' => '' ],
		[ 'name' => 'Tschad', 'alpha2' => 'TD', 'alpha3' => 'TCD', 'numeric' => '148', 'tld' => '.td', 'ioc' => 'CHA' ],
		[ 'name' => 'Tschechien', 'alpha2' => 'CZ', 'alpha3' => 'CZE', 'numeric' => '203', 'tld' => '.cz', 'ioc' => 'CZE' ],
		[ 'name' => 'Tschechoslowakei', 'alpha2' => 'CS', 'alpha3' => 'CSK', 'numeric' => '200', 'tld' => '.cs', 'ioc' => 'TCH' ],
		[ 'name' => 'Tunesien', 'alpha2' => 'TN', 'alpha3' => 'TUN', 'numeric' => '788', 'tld' => '.tn', 'ioc' => 'TUN' ],
		[ 'name' => 'Türkei', 'alpha2' => 'TR', 'alpha3' => 'TUR', 'numeric' => '792', 'tld' => '.tr', 'ioc' => 'TUR' ],
		[ 'name' => 'Turkmenistan', 'alpha2' => 'TM', 'alpha3' => 'TKM', 'numeric' => '795', 'tld' => '.tm', 'ioc' => 'TKM' ],
		[ 'name' => 'Turks- und Caicosinseln', 'alpha2' => 'TC', 'alpha3' => 'TCA', 'numeric' => '796', 'tld' => '.tc', 'ioc' => '' ],
		[ 'name' => 'Tuvalu', 'alpha2' => 'TV', 'alpha3' => 'TUV', 'numeric' => '798', 'tld' => '.tv', 'ioc' => 'TUV' ],
		[ 'name' => 'UdSSR', 'alpha2' => 'SU', 'alpha3' => 'SUN', 'numeric' => '810', 'tld' => '.su', 'ioc' => 'URS' ],
		[ 'name' => 'Uganda', 'alpha2' => 'UG', 'alpha3' => 'UGA', 'numeric' => '800', 'tld' => '.ug', 'ioc' => 'UGA' ],
		[ 'name' => 'Ukraine', 'alpha2' => 'UA', 'alpha3' => 'UKR', 'numeric' => '804', 'tld' => '.ua', 'ioc' => 'UKR' ],
		[ 'name' => 'Ungarn', 'alpha2' => 'HU', 'alpha3' => 'HUN', 'numeric' => '348', 'tld' => '.hu', 'ioc' => 'HUN' ],
		[ 'name' => 'United States Minor Outlying Islands', 'alpha2' => 'UM', 'alpha3' => 'UMI', 'numeric' => '581', 'tld' => '.um', 'ioc' => '' ],
		[ 'name' => 'Uruguay', 'alpha2' => 'UY', 'alpha3' => 'URY', 'numeric' => '858', 'tld' => '.uy', 'ioc' => 'URU' ],
		[ 'name' => 'Usbekistan', 'alpha2' => 'UZ', 'alpha3' => 'UZB', 'numeric' => '860', 'tld' => '.uz', 'ioc' => 'UZB' ],
		[ 'name' => 'Vanuatu', 'alpha2' => 'VU', 'alpha3' => 'VUT', 'numeric' => '548', 'tld' => '.vu', 'ioc' => 'VAN' ],
		[ 'name' => 'Vatikanstadt', 'alpha2' => 'VA', 'alpha3' => 'VAT', 'numeric' => '336', 'tld' => '.va', 'ioc' => '' ],
		[ 'name' => 'Venezuela', 'alpha2' => 'VE', 'alpha3' => 'VEN', 'numeric' => '862', 'tld' => '.ve', 'ioc' => 'VEN' ],
		[ 'name' => 'Vereinigte Arabische Emirate', 'alpha2' => 'AE', 'alpha3' => 'ARE', 'numeric' => '784', 'tld' => '.ae', 'ioc' => 'UAE' ],
		[ 'name' => 'USA', 'alpha2' => 'US', 'alpha3' => 'USA', 'numeric' => '840', 'tld' => '.us', 'ioc' => 'USA' ],
		[ 'name' => 'Großbritannien', 'alpha2' => 'GB', 'alpha3' => 'GBR', 'numeric' => '826', 'tld' => '.uk', 'ioc' => 'GBR' ],
		[ 'name' => 'Vietnam', 'alpha2' => 'VN', 'alpha3' => 'VNM', 'numeric' => '704', 'tld' => '.vn', 'ioc' => 'VIE' ],
		[ 'name' => 'Wallis und Futuna', 'alpha2' => 'WF', 'alpha3' => 'WLF', 'numeric' => '876', 'tld' => '.wf', 'ioc' => '' ],
		[ 'name' => 'Weihnachtsinsel', 'alpha2' => 'CX', 'alpha3' => 'CXR', 'numeric' => '162', 'tld' => '.cx', 'ioc' => '' ],
		[ 'name' => 'Westsahara', 'alpha2' => 'EH', 'alpha3' => 'ESH', 'numeric' => '732', 'tld' => '.eh', 'ioc' => '' ],
		[ 'name' => 'Zaire', 'alpha2' => 'ZR', 'alpha3' => 'ZAR', 'numeric' => '180', 'tld' => '', 'ioc' => '' ],
		[ 'name' => 'Zentralafrikanische Republik', 'alpha2' => 'CF', 'alpha3' => 'CAF', 'numeric' => '140', 'tld' => '.cf', 'ioc' => 'CAF' ],
		[ 'name' => 'Zypern', 'alpha2' => 'CY', 'alpha3' => 'CYP', 'numeric' => '196', 'tld' => '.cy', 'ioc' => 'CYP' ]
	];

	var $user;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		// Benutzerdaten laden
		if(FE_USER_LOGGED_IN)
		{
			// Frontenduser eingeloggt
			$this->user = \FrontendUser::getInstance();
		}
		parent::__construct();
	}


	/**
	 * Return the current object instance (Singleton)
	 * @return BannerCheckHelper
	 */
	public static function getInstance()
	{
		if (self::$instance === null)
		{
			self::$instance = new \Schachbulle\ContaoWertungsportalBundle\Helper\Helper();
		}

		return self::$instance;
	}

	/**
	 * Liefert den Alias der Spielerseite zurück
	 * @return         Alias
	 */
	public static function getSpielerseite($alias = true)
	{
		if($GLOBALS['TL_CONFIG']['wertungsportal_seite_spieler'])
		{
			$pageModel = \PageModel::findByPK($GLOBALS['TL_CONFIG']['wertungsportal_seite_spieler']);

			if($pageModel)
			{
				if($alias)
				{
					return $pageModel->row()['alias'];
				}
				else
				{
					$url = \Controller::generateFrontendUrl($pageModel->row());
					return $url;
				}
			}
		}

		return '';

	}

	/**
	 * Liefert den Alias der Turnierseite zurück
	 * @return         Alias
	 */
	public static function getTurnierseite($alias = true)
	{
		if($GLOBALS['TL_CONFIG']['wertungsportal_seite_turnier'])
		{
			$pageModel = \PageModel::findByPK($GLOBALS['TL_CONFIG']['wertungsportal_seite_turnier']);

			if($pageModel)
			{
				if($alias)
				{
					return $pageModel->row()['alias'];
				}
				else
				{
					$url = \Controller::generateFrontendUrl($pageModel->row());
					return $url;
				}
			}
		}

		return '';

	}

	/**
	 * Liefert den Alias der Vereinseite zurück
	 * @return         Alias
	 */
	public static function getVereinseite($alias = true)
	{
		if($GLOBALS['TL_CONFIG']['wertungsportal_seite_verein'])
		{
			$pageModel = \PageModel::findByPK($GLOBALS['TL_CONFIG']['wertungsportal_seite_verein']);

			if($pageModel)
			{
				if($alias)
				{
					return $pageModel->row()['alias'];
				}
				else
				{
					$url = \Controller::generateFrontendUrl($pageModel->row());
					return $url;
				}
			}
		}

		return '';

	}

	/**
	 * Liefert den Alias der Verbandseite zurück
	 * @param          alias true = nur das Alias zurückgeben, false = komplette URL zurückgeben
	 * @return         Alias
	 */
	public static function getVerbandseite($alias = true)
	{
		if($GLOBALS['TL_CONFIG']['wertungsportal_seite_verband'])
		{
			$pageModel = \PageModel::findByPK($GLOBALS['TL_CONFIG']['wertungsportal_seite_verband']);

			if($pageModel)
			{
				if($alias)
				{
					return $pageModel->row()['alias'];
				}
				else
				{
					$url = \Controller::generateFrontendUrl($pageModel->row());
					return $url;
				}
			}
		}

		return '';

	}

	/**
	 * Zwischenspeicher für die generierten Seiten-URLs (pro Request)
	 */
	protected static $seitenUrlCache = array();

	/**
	 * Liefert die generierte URL der Spielerseite OHNE das URL-Suffix (.html)
	 * als Basis für Modul-Links zurück. Im Gegensatz zum Alias enthält die
	 * generierte URL im Vorschaumodus das preview.php-Präfix, so dass Links
	 * und Formulare die Vorschau nicht mehr verlassen.
	 */
	public static function getSpielerseiteUrl()
	{
		if(!isset(self::$seitenUrlCache['spieler']))
		{
			self::$seitenUrlCache['spieler'] = preg_replace('/\.html$/', '', self::getSpielerseite(false));
		}
		return self::$seitenUrlCache['spieler'];
	}

	/**
	 * Liefert die generierte URL der Turnierseite ohne URL-Suffix zurück (siehe getSpielerseiteUrl)
	 */
	public static function getTurnierseiteUrl()
	{
		if(!isset(self::$seitenUrlCache['turnier']))
		{
			self::$seitenUrlCache['turnier'] = preg_replace('/\.html$/', '', self::getTurnierseite(false));
		}
		return self::$seitenUrlCache['turnier'];
	}

	/**
	 * Liefert die generierte URL der Vereinseite ohne URL-Suffix zurück (siehe getSpielerseiteUrl)
	 */
	public static function getVereinseiteUrl()
	{
		if(!isset(self::$seitenUrlCache['verein']))
		{
			self::$seitenUrlCache['verein'] = preg_replace('/\.html$/', '', self::getVereinseite(false));
		}
		return self::$seitenUrlCache['verein'];
	}

	/**
	 * Liefert die generierte URL der Verbandseite ohne URL-Suffix zurück (siehe getSpielerseiteUrl)
	 */
	public static function getVerbandseiteUrl()
	{
		if(!isset(self::$seitenUrlCache['verband']))
		{
			self::$seitenUrlCache['verband'] = preg_replace('/\.html$/', '', self::getVerbandseite(false));
		}
		return self::$seitenUrlCache['verband'];
	}

	// ─────────────────────────────────────────────
	//  Funktion Gesperrt
	//  Für Gäste der Website Karteisperre = TRUE setzen
	// ─────────────────────────────────────────────
	public static function Gesperrt()
	{
		$mitglied = self::getMitglied(); // Daten des aktuellen Mitgliedes laden

		// Sperrstatus festlegen
		if($GLOBALS['TL_CONFIG']['wertungsportal_karteisperre_gaeste']) $gesperrt = $mitglied->id ? false : true;
		else $gesperrt = false;
		
		return $gesperrt;
	}

	public static function getMitglied()
	{
		return \FrontendUser::getInstance(); //$this->user;
	}

	/**
	 * Fügt in der Rückgabe der Wertungsportal-API die aktuellen FIDE-Daten hinzu
	 * @result         Array    API-Antwort
	 * @param          Array    Parameter für die API
	 * @return         Array    Modifizierte API-Antwort
	 */
	public static function setFIDEDaten($result, $params)
	{
		switch($params['funktion'])
		{
			case 'Spielerliste': // Spielerliste einer Suche
			case 'Vereinsliste': // Spielerliste eines Vereins
				// FIDE-Daten für alle Spieler in einem Rutsch laden statt je Spieler einzeln
				$fideids = array();
				for($x = 0; $x < count($result['body']['data']); $x++)
				{
					if(!empty($result['body']['data'][$x]['fideId'])) $fideids[] = $result['body']['data'][$x]['fideId'];
				}
				$fideliste = self::getFIDEDatenListe($fideids);
				$leer = array('land' => '', 'elo' => '', 'titel' => '');

				for($x = 0; $x < count($result['body']['data']); $x++)
				{
					$fideid = isset($result['body']['data'][$x]['fideId']) ? $result['body']['data'][$x]['fideId'] : false;
					$fide = $fideid && isset($fideliste[$fideid]) ? $fideliste[$fideid] : $leer;
					$result['body']['data'][$x]['fideElo'] = $fide['elo'];
					$result['body']['data'][$x]['fideTitle'] = $fide['titel'];
					$result['body']['data'][$x]['fideNation'] = $fide['land'];
				}
				break;
			case 'Karteikarte': // Karteikarte eines Spielers nach nu-ID
				$fideid = isset($result['body']['fideId']) ? $result['body']['fideId'] : false;
				$fide = self::getFIDEDatenLokal($fideid);
				$result['body']['fideElo'] = $fide['elo'];
				$result['body']['fideTitle'] = $fide['titel'];
				$result['body']['fideNation'] = $fide['land'];
				break;
			default:
		}
		return $result;
	}

	/**
	 * Lädt die FIDE-Daten Elo, Titel, Nation aus der lokalen Quelle
	 */
	public static function getFIDEDatenLokal($fideid)
	{
		$fide = array('land' => '', 'elo' => '', 'titel' => '');
		if($fideid)
		{
			// FIDE-ID in lokaler Datenbank suchen
			$objPlayer = \Database::getInstance()->prepare("SELECT * FROM tl_wertungsportal_elo WHERE fideid = ?")
			                                     ->execute($fideid);
			if($objPlayer->numRows)
			{
				$fide = array
				(
					'land'  => $objPlayer->country,
					'elo'   => $objPlayer->rating ? $objPlayer->rating : '',
					'titel' => $objPlayer->title
				);
			}
		}
		return $fide;
	}

	/**
	 * Lädt die FIDE-Daten (Elo, Titel, Nation) für mehrere FIDE-IDs in einem
	 * Rutsch aus der lokalen Tabelle tl_wertungsportal_elo — vermeidet die Einzelabfrage
	 * je Spieler bei großen Listen.
	 *
	 * @param     array $fideids  FIDE-IDs (leere Werte werden ignoriert)
	 * @return    array           FIDE-ID => array('land' => ..., 'elo' => ..., 'titel' => ...)
	 */
	public static function getFIDEDatenListe($fideids)
	{
		$liste = array();

		// Leere Werte entfernen, Duplikate zusammenfassen
		$fideids = array_values(array_unique(array_filter((array) $fideids)));
		if(!count($fideids)) return $liste;

		// Blockweise laden, um sehr lange IN-Listen zu vermeiden
		foreach(array_chunk($fideids, 500) as $chunk)
		{
			$platzhalter = implode(',', array_fill(0, count($chunk), '?'));
			$objPlayer = \Database::getInstance()->prepare("SELECT fideid, country, rating, title FROM tl_wertungsportal_elo WHERE fideid IN ($platzhalter)")
			                                     ->execute($chunk);
			while($objPlayer->next())
			{
				$liste[$objPlayer->fideid] = array
				(
					'land'  => $objPlayer->country,
					'elo'   => $objPlayer->rating ? $objPlayer->rating : '',
					'titel' => $objPlayer->title
				);
			}
		}

		return $liste;
	}

	/**
	 * Liefert den direkt übergeordneten Verband zu einer Vereins-VKZ als
	 * VKZ + Name. Aus den ersten drei Stellen der (fünfstelligen) Vereins-VKZ
	 * werden Kandidaten von spezifisch nach allgemein gebildet und der ERSTE
	 * lokal existierende Verband genommen — Beispiel Verein 55223:
	 * 1. Kreis 552, sonst 2. Bezirk 550, sonst 3. Landesverband 500.
	 * Existiert wider Erwarten keiner davon, wird als letzter Fallback der
	 * Deutsche Schachbund (000) zurückgegeben.
	 * Verbände liegen in tl_wertungsportal_clubs 3-stellig oder 5-stellig
	 * (?00-Auffüllung) vor.
	 *
	 * @param     String $vkz  VKZ des Vereins
	 * @return    array        array('vkz' => '552', 'name' => '...')
	 */
	public static function getVerband($vkz)
	{
		$vkz = (string) $vkz;

		// Kandidaten von spezifisch (Kreis) nach allgemein (Landesverband);
		// Duplikate zusammenfassen, Reihenfolge erhalten
		$kandidaten = array_values(array_unique(array
		(
			substr($vkz, 0, 3),           // Kreis, z. B. 552
			substr($vkz, 0, 2).'0',       // Bezirk, z. B. 550
			substr($vkz, 0, 1).'00',      // Landesverband, z. B. 500
		)));

		foreach($kandidaten as $kand)
		{
			if(strlen($kand) < 3) continue;

			$objClub = \Database::getInstance()->prepare("SELECT clubName FROM tl_wertungsportal_clubs WHERE clubVkz = ? OR clubVkz = ?")
			                                   ->limit(1)
			                                   ->execute($kand, $kand.'00');

			if($objClub->numRows) return array('vkz' => $kand, 'name' => $objClub->clubName);
		}

		// Harter Fallback: Deutscher Schachbund
		return array('vkz' => '000', 'name' => 'Deutscher Schachbund');
	}

	/**
	 * Zwischenspeicher für bereits geprüfte Blacklist-IDs (pro Request)
	 */
	protected static $blacklistCache = array();

	/**
	 * Prüft nu-Personen-IDs gegen die Blacklist (tl_wertungsportal_persons,
	 * Feld blocked) und liefert die gesperrten IDs als Array id => true
	 * zurück — eine Bulk-Abfrage statt Einzelabfragen je Spieler.
	 * Gesperrte Personen dürfen in keiner Ausgabe der Website erscheinen.
	 *
	 * @param     array $nuIds  nu-Personen-IDs (leere Werte werden ignoriert)
	 * @return    array         nuLigaPersonId => true für gesperrte Personen
	 */
	public static function getBlacklist($nuIds)
	{
		// Die Spalte blocked existiert erst nach contao:migrate —
		// bis dahin gibt es keine Sperren (kein SQL-Fehler im Frontend)
		static $spalteVorhanden = null;
		if($spalteVorhanden === null) $spalteVorhanden = \Database::getInstance()->fieldExists('blocked', 'tl_wertungsportal_persons');
		if(!$spalteVorhanden) return array();

		$nuIds = array_values(array_unique(array_filter((array) $nuIds)));

		// Noch nicht geprüfte IDs ermitteln
		$offen = array();
		foreach($nuIds as $id)
		{
			if(!array_key_exists((string) $id, self::$blacklistCache)) $offen[] = (string) $id;
		}

		// Offene IDs blockweise abfragen und Ergebnis im Request-Cache merken
		if(count($offen))
		{
			foreach($offen as $id) self::$blacklistCache[$id] = false;

			foreach(array_chunk($offen, 500) as $chunk)
			{
				$platzhalter = implode(',', array_fill(0, count($chunk), '?'));
				$objPerson = \Database::getInstance()->prepare("SELECT nuLigaPersonId FROM tl_wertungsportal_persons WHERE blocked = '1' AND nuLigaPersonId IN ($platzhalter)")
				                                     ->execute($chunk);
				while($objPerson->next())
				{
					self::$blacklistCache[(string) $objPerson->nuLigaPersonId] = true;
				}
			}
		}

		// Gesperrte IDs der angefragten Liste zurückgeben
		$liste = array();
		foreach($nuIds as $id)
		{
			if(!empty(self::$blacklistCache[(string) $id])) $liste[(string) $id] = true;
		}

		return $liste;
	}

	/**
	 * Prüft eine einzelne nu-Personen-ID gegen die Blacklist
	 *
	 * @param     String $nuId  nu-Personen-ID
	 * @return    bool          true, wenn die Person gesperrt ist
	 */
	public static function istGeblockt($nuId)
	{
		if(!$nuId) return false;

		return count(self::getBlacklist(array($nuId))) > 0;
	}

	/**
	 * Leitet auf die im System definierte 404-Seite weiter
	 */
	public static function get404($fehler = '')
	{
		throw new \CoreBundle\Exception\PageNotFoundException('Page not found: '.\Environment::get('uri'));
	}

	/**
	 * Liefert die Adresse eines mitgelieferten Platzhalterbildes.
	 *
	 * Gebraucht wird das, solange in den Einstellungen kein Standardbild
	 * ausgewählt ist — was in einer frischen Installation der Normalfall ist,
	 * weil dafür erst eine Datei in die Dateiverwaltung hochgeladen werden
	 * müsste. Die SVG-Dateien liegen im Bundle und damit AUSSERHALB der
	 * Dateiverwaltung; sie laufen deshalb bewusst an der Bilderzeugung vorbei
	 * und werden im Template unmittelbar als <img> eingebunden.
	 *
	 * @param  string $art 'verein' oder 'spieler'
	 *
	 * @return string Absolute Adresse ab dem Wurzelverzeichnis der Website
	 *                (setzt contao:assets:install voraus)
	 */
	public static function platzhalterbild($art)
	{
		$dateien = array
		(
			'verein'  => 'standard-verein.svg',
			'spieler' => 'standard-spieler.svg',
		);

		$datei = isset($dateien[$art]) ? $dateien[$art] : $dateien['verein'];

		// Ausdrücklich absolut: Die Vereinsseite wird unter
		// /vereine/30066.html ausgeliefert, ein relativer Pfad landete dort im
		// Unterverzeichnis. Dass die Contao-Layouts ein <base> mitgeben, ist
		// kein Verlass — es lässt sich abschalten
		return rtrim((string) \Environment::get('path'), '/').'/bundles/contaowertungsportal/images/'.$datei;
	}

	/**
	 * Liest eine Bildgrößen-Einstellung und gibt sie in der Form zurück, die
	 * der Figure-Builder erwartet.
	 *
	 * Die Einstellungen sind serialisierte Arrays. Ist noch nichts gespeichert,
	 * liefert TL_CONFIG null — und ein `unserialize(null)` erzeugt erst eine
	 * Warnung und dann ein `false`, mit dem setSize() nichts anfangen kann.
	 *
	 * @param  string $einstellung Name der Einstellung in TL_CONFIG
	 *
	 * @return array|null Größenangabe, oder null für „keine Vorgabe"
	 */
	public static function bildgroesse($einstellung)
	{
		$wert = $GLOBALS['TL_CONFIG'][$einstellung] ?? null;

		if(empty($wert)) return null;

		$groesse = \StringUtil::deserialize($wert);

		return is_array($groesse) ? $groesse : null;
	}

	// Karteizuweisung() ist am 03.08.2026 entfallen: Die Methode las
	// tl_dwz_spi — eine Tabelle des abgelösten DeWIS-Bundles, die es in einer
	// Installation ohne dieses gar nicht gibt — und wurde von nirgendwo im
	// Bundle aufgerufen.

	/**
	 * Gibt den Status der Karteikartensperre für eine DeWIS-ID zurück
	 * @param id	ID in DeWIS
	 * @return		Karteikarte gesperrt true/false
	 */
	public static function Karteisperre($id)
	{
		$objCheckUser = \Database::getInstance()->prepare('SELECT dewisCard FROM tl_member WHERE id=?')
		                                        ->execute($id);
		return $objCheckUser->dewisCard;
	}

	/**
	 * Gibt die Navigation zurück
	 * @param 		-
	 * @return		Array mit den Links
	 */
	public static function Navigation()
	{
		return array
		(
			'<li class="first"><a href="'.self::getSpielerseite(false).'">Spieler</a></li>',
			'<li class=""><a href="'.self::getVereinseite(false).'">Vereine</a></li>',
			'<li class=""><a href="'.self::getVerbandseite(false).'">Verbände</a></li>',
			'<li class="last"><a href="'.self::getTurnierseite(false).'">Turniere</a></li>',
		);
	}

	/**
	 * Liefert die Gewinnerwartung
	 *
	 * @return float
	 */
	public static function Gewinnerwartung($dwz, $gegnerdwz)
	{
		// Umwandeln in Integer, falls ein String übergeben wurde
		$dwz = (int)$dwz;
		$gegnerdwz = (int)$gegnerdwz;
		if($dwz == 0 || $gegnerdwz == 0) return false;
		return (sprintf ("%5.3f", 1/(1+pow(10,($gegnerdwz-$dwz)/400))));
	}

	/**
	 * Funktion Resultat
	 * Wandelt "WHITE_WINS" u.ä. in "1:0" um
	 * @return float
	 */
	public static function Resultat($string)
	{
		switch($string)
		{
			case 'WHITE_WINS': $ergebnis = '1:0'; break;
			case 'PLUS_MINUS': $ergebnis = '+:-'; break;
			case 'REMIS': $ergebnis = '½:½'; break;
			case 'BLACK_WINS': $ergebnis = '0:1'; break;
			case 'MINUS_PLUS': $ergebnis = '-:+'; break;
			case 'MINUS_MINUS': $ergebnis = '-:-'; break;
			case 'ZERO_MINUS': $ergebnis = '0:-'; break;
			case 'ZERO_HALF': $ergebnis = '0:½'; break;
			case 'MINUS_ZERO': $ergebnis = '-:0'; break;
			case 'HALF_ZERO': $ergebnis = '½:0'; break;
			default: $ergebnis = $string; break;
		}
		return $ergebnis;
	}

	/**
	 * Prüft ob ein Jahr ein Schaltjahr ist und gibt entsprechend die Monatslängen zurück
	 * @param 		-
	 * @return		Array mit Anzahl Tage je Monat
	 */
	public static function Monatstage($jahr)
	{
		$monate = array
		(
			1 => 31,
			2 => 28,
			3 => 31,
			4 => 30,
			5 => 31,
			6 => 30,
			7 => 31,
			8 => 31,
			9 => 30,
			10 => 31,
			11 => 30,
			12 => 31
		);

		if(($jahr % 400) == 0 || (($jahr % 4) == 0 && ($jahr % 100) != 0))
		{
			// Schaltjahr
			$monate[2] = 29;
			return $monate;
		}
		else
		{
			// Kein Schaltjahr
			return $monate;
		}
	}

	public static function datum_mysql2php($datum)
	{
		return $datum ? substr($datum, 8, 2) . '.' . substr($datum, 5, 2) . '.' . substr($datum, 0, 4) : '';
	}

	/**
	 * Füllt fehlende Felder eines Spieler-DTOs der Wertungsportal-API
	 * (players, whitePlayer, blackPlayer) mit Standardwerten auf, damit
	 * Zugriffe auf optionale Felder keine Fehler auslösen.
	 *
	 * @param       Array $player Spieler-DTO aus der API (kann unvollständig sein)
	 * @return      Array
	 */
	public static function PlayerDefaults($player)
	{
		if(!is_array($player)) $player = array();

		$defaults = array
		(
			'playerUuid'               => false,
			'nuLigaPersonId'           => false,
			'firstname'                => false,
			'lastname'                 => false,
			'birthyear'                => false,
			'vkz'                      => false,
			'memberNo'                 => false,
			'clubName'                 => false,
			'fideId'                   => false,
			'playerNo'                 => false,
			'eloPlayer'                => false,
			'ratingOld'                => false,
			'indexOld'                 => false,
			'ratingNew'                => false,
			'indexNew'                 => false,
			'factorK'                  => false,
			'averageRatingCompetitors' => false,
			'wins'                     => false,
			'numberOfGames'            => false,
			'winsExpected'             => false,
			'tournamentPerformance'    => false,
		);

		return array_merge($defaults, $player);
	}

	/**
	 * Formatiert ein Datum aus der Wertungsportal-API fehlertolerant um.
	 * Fehlt der Wert oder passt er nicht zum Eingabeformat (z. B. wenn ein
	 * Turnier noch nie berechnet wurde und lastCalculated fehlt), wird der
	 * Standardwert zurückgegeben statt einen Fehler auszulösen.
	 *
	 * @param       String $wert      Datumswert aus der API (kann fehlen/leer sein)
	 * @param       String $informat  Eingabeformat, z. B. 'Y-m-d' oder 'Y-m-d\TH:i:s'
	 * @param       String $outformat Ausgabeformat, z. B. 'd.m.Y' oder 'd.m.Y H:i'
	 * @param       String $default   Rückgabe, wenn kein gültiges Datum vorliegt
	 * @return      String
	 */
	public static function ApiDatum($wert, $informat = 'Y-m-d', $outformat = 'd.m.Y', $default = 'unbekannt')
	{
		if(!$wert || !is_string($wert)) return $default;

		$datum = \DateTime::createFromFormat($informat, $wert);

		return $datum ? $datum->format($outformat) : $default;
	}

	/**
	 * Ersetzt in einem numerischen Array 0-Werte durch einen Mittelwert der Nachbarwerte
	 * @param 		Array
	 * @return		Array
	 */
	public static function Mittelwerte($array)
	{

		$value = 0;
		$key = -1;
		for($x = 0; $x < count($array); $x++)
		{
			if($array[$x] > 0)
			{
				// Wert ungleich 0 gefunden, das ist der Nachfolgerwert
				// Anzahl 0-Werte davor ermitteln
				$teiler = $x - $key;
				if($teiler > 1)
				{
					// Nullwerte gefunden, ersetzen durch Mittelwerte
					// Mittelwertedifferenz ermitteln
					$mittelwert_differenz = sprintf('%d', ($value - $array[$x]) / $teiler);
					if($key == -1)
					{
						// Nullwerte am Anfang mit aktuellem Wert befüllen
						for($y = 0; $y < $x; $y++)
						{
							$array[$y] = $array[$x];
						}
					}
					else
					{
						// Nullwerte in der Arraymitte mit Differenz füllen
						for($y = $key + 1; $y < $x; $y++)
						{
							$array[$y] = $array[$y-1] - $mittelwert_differenz;
						}
					}
				}
				// Neue Vorgängerwerte setzen, aktuellen Wert benutzen
				$key = $x;
				$value = $array[$x];
			}
		}

		// Nullwerte am Arrayende auffüllen
		for($y = $key + 1; $y < count($array); $y++)
		{
			$array[$y] = $value;
		}

		return $array;

	}

	/**
	 * Setzt den Spielernamen zusammen mit einem Link zur Karteikarte
	 * @param       Array      $person
	 * @return      String
	 */
	public static function Spielername($person)
	{
		if($person['nuLigaPersonId']) $return = sprintf('<a href="'.self::getSpielerseiteUrl().'/%s.html">%s</a>', $person['nuLigaPersonId'], sprintf('%s, %s', $person['lastname'], $person['firstname']));
		else $return = sprintf('%s', sprintf('%s, %s', $person['lastname'], $person['firstname']));

		return $return;
	}

	/**
	 * Korrigiert die Anzeige der Punkte
	 * @param       String      $points
	 * @return      String
	 */
	public static function Punkte($points)
	{
		return ($points == 0.5) ? '½' : str_replace('.5', '½', $points * 1);
	}

	/**
	 * Korrigiert die Anzeige der Gesamtpunkte
	 * 6 -> 6,0
	 * 12.3 -> 12,3
	 * 1.5 -> 1,5 usw.
	 * @param       String      $points
	 * @return      String
	 */
	public static function Gesamtpunkte($points)
	{
		return str_replace('.', ',', sprintf('%.1f', $points));
	}

	/**
	 * Gibt den Erwartungswert in der Form x.xxx zurück
	 * @param       String      $points
	 * @return      String
	 */
	public static function Erwartungswert($we)
	{
		if($we === false) return '';
		else return str_replace('.', ',', sprintf('%.3f', $we));
	}

	/**
	 * Gibt von einem Spieler die DWZ plus Index für die Anzeige zurück
	 * @param       Array      $spieler
	 * @return      String
	 */
	public static function DWZ($rating, $ratingIndex)
	{
		return ($rating == 0 && $ratingIndex == 0) ? '' : sprintf("%s -%s", str_replace(' ', '&nbsp;&nbsp;', sprintf("%4d", $rating)), str_replace(' ', '&nbsp;&nbsp;', sprintf("%3d", $ratingIndex)));
	}

	/**
	 * Hilfsfunktion:
	 * Kürzt den Turniernamen auf 60 Zeichen
	 *
	 * @return string
	 */
	public static function Turnierkurzname($value)
	{
		if(mb_detect_encoding($value,'UTF-8, ISO-8859-1') === 'UTF-8')
		{
			# Der Turniername ist in UTF-8 kodiert und muß vor der Kürzung umgewandelt werden
			$value = utf8_decode($value);
		}

		// Gekürzten Turniernamen generieren und wieder in UTF-8 umwandeln
		$neu = (strlen($value) > 60) ? substr($value,0,60).' [...]' : $value;
		return utf8_encode($neu);

	}

	/**
	 * Liefert zu einem Spieler dessen Mitgliedsstatus
	 * @param $person      Array mit den Spielerdaten
	 * @param $vkz         Gewünschte VKZ, falls leer wird die 1. Mitgliedschaft zurückgegeben
	 * @return             String: P oder leer (für A)
	 */
	public static function getMitgliedsstatus($person, $vkz = false)
	{
		$status = '';
		foreach($person['memberships'] as $mitgliedschaft)
		{
			if($vkz == $mitgliedschaft['vkz'] || !$vkz)
			{
				$status = $mitgliedschaft['licenceState'] == 'PASSIVE' ? 'P' : '';
				break;
			}
		}
		return $status;
	}

	/**
	 * Liefert zu einem Spieler dessen Mitgliedsnummer
	 * @param $person      Array mit den Spielerdaten
	 * @param $vkz         Gewünschte VKZ, falls leer wird die 1. Mitgliedschaft zurückgegeben
	 * @return             String: P oder leer (für A)
	 */
	public static function getMitgliedsnummer($person, $vkz = false)
	{
		$nummer = '';
		foreach($person['memberships'] as $mitgliedschaft)
		{
			if($vkz == $mitgliedschaft['vkz'] || !$vkz)
			{
				$nummer = sprintf('%04d', $mitgliedschaft['memberNo']);
				break;
			}
		}
		return $nummer;
	}

	/**
	 * Gibt die Kalenderwoche aus Spielerdatensatz zurück
	 * @param       Array      $person
	 * @return      String
	 */
	public static function Kalenderwoche($person)
	{
		if(isset($person['weekOfLastTournamentEvaluation']))
		{
			$return = sprintf('<span title="Woche/Jahr der letzten Auswertung">%s/%s</span>', substr($person['weekOfLastTournamentEvaluation'], -2), substr($person['weekOfLastTournamentEvaluation'], 0, 4));
		}
		else $return = '';
		return $return;
	}

	/**
	 * Schreibt Werte aus einem Array vom Array in ein neues Array
	 * @param 		Array
	 * 				Beispiel:
	 *				array(array('item'=>1,'val'=>2),array('item'=>3,'val'=>6))
	 * @param		String ($extract = 'item' oder 'val')
	 * @return		Array
	 */
	public static function ArrayExtract($array, $extract)
	{
		//echo "<pre>";
		//echo count($array);
		//echo "</pre>";
		$newArr = array();
		foreach($array as $key => $value)
		{
			$newArr[] = $value[$extract];
		}
		return $newArr;
	}


	/**
	 * Baut den Hinweis, dass eine Ausgabe aus dem Zwischenspeicher stammt.
	 * Ohne Argument gilt der ganze Seitenaufruf (API::cacheStatus liefert den
	 * frühesten Ablaufzeitpunkt aller Cache-Treffer); mit einer API-Antwort
	 * als Argument gilt nur diese eine Abfrage.
	 *
	 * Steht die Schnittstelle nicht zur Verfügung (abgeschaltet oder ohne
	 * Antwort) und wurden deshalb abgelaufene Daten ausgeliefert, hat dieser
	 * Fall Vorrang: Dann nennt der Hinweis den Grund und das Alter der Daten,
	 * nicht einen Erneuerungszeitpunkt — der wäre in dieser Lage eine leere
	 * Zusage.
	 *
	 * @param  array|null $result  optionale API-Antwort
	 * @return string              Hinweistext oder '' (frisch von der Schnittstelle)
	 */
	public static function cacheHinweis($result = null)
	{
		$format = !empty($GLOBALS['TL_CONFIG']['datimFormat']) ? $GLOBALS['TL_CONFIG']['datimFormat'] : 'd.m.Y H:i';

		// Notbetrieb zuerst prüfen. Beide Rückfallebenen können auf derselben
		// Seite vorkommen (eine Abfrage aus dem Zwischenspeicher, eine aus der
		// örtlichen Datenbank) — dann werden sie in einem Satz genannt
		if(is_array($result))
		{
			$notstand = array_key_exists('notstand', $result) ? $result['notstand'] : false;
			$lokalstand = !empty($result['lokalquelle']) ? (int) ($result['lokalstand'] ?? 0) : false;
		}
		else
		{
			$notstand = \Schachbulle\ContaoWertungsportalBundle\Helper\API::notstand();
			$lokalstand = \Schachbulle\ContaoWertungsportalBundle\Helper\Lokal::stand();
		}

		if($notstand !== false || $lokalstand !== false)
		{
			$quellen = array();

			if($notstand !== false)
			{
				$quellen[] = ($notstand > 0)
					? 'zwischengespeicherte Daten vom '.\Date::parse($format, (int) $notstand).' Uhr'
					: 'zwischengespeicherte Daten';
			}

			if($lokalstand !== false)
			{
				$quellen[] = ($lokalstand > 0)
					? 'Daten aus dem örtlichen Datenbestand (letzte Aktualisierung am '.\Date::parse($format, (int) $lokalstand).' Uhr)'
					: 'Daten aus dem örtlichen Datenbestand';
			}

			return \Schachbulle\ContaoWertungsportalBundle\Helper\API::MELDUNG_KEINE_LIVEDATEN.' Angezeigt werden '.implode(' und ', $quellen).'.';
		}

		if(is_array($result))
		{
			if(empty($result['cachequelle'])) return '';
			$ablauf = isset($result['cacheablauf']) ? (int) $result['cacheablauf'] : 0;
		}
		else
		{
			$status = \Schachbulle\ContaoWertungsportalBundle\Helper\API::cacheStatus();
			if($status === false) return ''; // nichts kam aus dem Zwischenspeicher
			$ablauf = (int) $status;
		}

		if($ablauf <= 0) return 'Diese Daten stammen aus dem Zwischenspeicher.';

		return 'Diese Daten stammen aus dem Zwischenspeicher und werden am '.\Date::parse($format, $ablauf).' Uhr erneuert.';
	}

	/**
	 * Baut die Fehlermeldung, die ein Modul über seinen Fehler-Slot ausgibt.
	 * Steht die Schnittstelle nicht zur Verfügung, ist das kein Fehler der
	 * Schnittstelle, sondern ein Betriebszustand — dann erscheint die schlichte
	 * Meldung ohne HTTP-Code und ohne technische Begleittexte.
	 *
	 * @param  array $result  Antwort von API::autoQuery
	 * @return string         Fehlertext für die Ausgabe
	 */
	public static function apiFehler($result)
	{
		if(!empty($result['keine_livedaten'])) return \Schachbulle\ContaoWertungsportalBundle\Helper\API::MELDUNG_KEINE_LIVEDATEN;

		// Fehlermeldung der API ermitteln (body ist im Fehlerfall meist ein String)
		$meldung = '';
		if(isset($result['body']) && is_string($result['body']) && $result['body'] != '') $meldung = $result['body'];
		elseif(!empty($result['error_message'])) $meldung = $result['error_message'];

		return 'Die Wertungsportal-API meldet einen Fehler (HTTP-Code '.($result['http_code'] ?? '?').')'.($meldung ? ': '.$meldung : '');
	}

	/**
	 * Entfernt Platzhalter-Mitgliedschaften mit der Nummer 0 aus einer
	 * kompletten API-Antwort — unabhängig davon, in welcher Form sie die
	 * Mitgliedschaften mitliefert:
	 *   body.data[].memberships     (Spieler-, Vereins-, Verbandsliste)
	 *   body.memberships            (Karteikarte)
	 *   body.person.memberships     (Turnierhistorie)
	 *
	 * nu vergibt beim Anlegen einer Person zunächst die Mitgliedsnummer 0000
	 * und liefert diesen Eintrag auch dann noch mit, wenn die endgültige
	 * Nummer längst feststeht. Ohne diesen Filter erscheint der Spieler in
	 * jeder Ausgabe doppelt beim selben Verein bzw. mit der Nummer 0000.
	 *
	 * Der Filter läuft zentral in API::autoQuery() und wirkt damit auf ALLE
	 * Ausgaben — auch bei Cache-Treffern, also ohne Warten auf den Cachelauf.
	 *
	 * @param  array $result   API-Antwort
	 * @return array           Antwort mit bereinigten Mitgliedschaften
	 */
	public static function filterMitgliedsnummern($result)
	{
		if(!is_array($result) || !isset($result['body']) || !is_array($result['body'])) return $result;

		$filter = function($memberships)
		{
			return is_array($memberships) ? \Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalPersonsMembershipsModel::filtereNullnummern($memberships) : $memberships;
		};

		// Listen (data-Array)
		if(isset($result['body']['data']) && is_array($result['body']['data']))
		{
			foreach($result['body']['data'] as $i => $person)
			{
				if(isset($person['memberships'])) $result['body']['data'][$i]['memberships'] = $filter($person['memberships']);
			}
		}

		// Einzelne Person (Karteikarte)
		if(isset($result['body']['memberships'])) $result['body']['memberships'] = $filter($result['body']['memberships']);

		// Turnierhistorie (person-Knoten)
		if(isset($result['body']['person']['memberships'])) $result['body']['person']['memberships'] = $filter($result['body']['person']['memberships']);

		return $result;
	}

	/**
	 * Konvertiert einen Namen für die Wertungsportal-API:
	 * Jeder durch Leerzeichen getrennte Namensteil wird einzeln geslugt
	 * (Umlaute u.ä.), die Leerzeichen bleiben erhalten — "von Dissen" darf
	 * nicht als "von-dissen" an die API gehen, sonst gibt es keine Treffer
	 * @param $name        Namensbestandteil (Nachname oder Vorname)
	 * @return string      Konvertierter Name
	 */
	public static function slugName($name)
	{
		$name = trim((string) $name);
		if($name === '') return '';

		$slug = \System::getContainer()->get('contao.slug');
		$teile = array();

		foreach(preg_split('/\s+/', $name) as $teil)
		{
			$teil = $slug->generate($teil, 1);
			if($teil !== '') $teile[] = $teil;
		}

		return implode(' ', $teile);
	}

	/**
	 * Erzeugt den Suchalias eines Textes: kleingeschrieben, ohne Umlaute und
	 * Sonderzeichen, Wörter durch Bindestrich getrennt ("Büchenbach Open" →
	 * "buechenbach-open"). Grundlage ist der Slug-Generator mit deutschem
	 * Sprachraum — nur der schreibt Umlaute so um, wie sie auch von Hand
	 * geschrieben werden (ü → ue, ß → ss). Genau das löst das Suchproblem:
	 * "Büchenbach" und "Buechenbach" ergeben denselben Alias, ebenso
	 * "Königsspringer"/"Koenigsspringer" oder "Groß-Gerau"/"Gross-Gerau".
	 *
	 * Verwendet wird der reine Slug-Generator (contao.slug.generator), NICHT
	 * der Contao-Dienst contao.slug: Letzterer stellt rein numerischen Werten
	 * ein "id-" voran (aus "2025" würde "id-2025", das fände sich dann nicht
	 * mehr in "open-2025") und zieht ohne ausdrückliche Optionen die
	 * Einstellungen einer Seite heran. Sollte der Dienst einmal nicht
	 * öffentlich sein, greift der Umweg über contao.slug samt Rücknahme des
	 * Präfixes.
	 *
	 * Ein Text ohne verwertbare Zeichen (z. B. der nu-Turniername "-") ergäbe
	 * einen leeren Alias und wäre damit von "noch nicht erzeugt" nicht zu
	 * unterscheiden. Solche Werte bekommen deshalb den Platzhalter "-", den
	 * der Slug-Generator selbst nie liefert (er schneidet Trennzeichen an den
	 * Rändern ab).
	 *
	 * @param $text        Ausgangstext (Vereins-, Turnier- oder Personenname)
	 * @return string      Alias, '' bei leerer Eingabe, '-' ohne verwertbare Zeichen
	 */
	public static function alias($text)
	{
		$text = trim((string) $text);
		if($text === '') return '';

		$optionen = array('validChars' => 'a-z0-9', 'locale' => 'de', 'delimiter' => '-');
		$container = \System::getContainer();

		if($container->has('contao.slug.generator'))
		{
			$alias = $container->get('contao.slug.generator')->generate($text, $optionen);
		}
		else
		{
			$alias = $container->get('contao.slug')->generate($text, $optionen);
			// "id-"-Präfix des Contao-Dienstes zurücknehmen (siehe oben)
			if(preg_match('/^id-[1-9][0-9]*$/', $alias)) $alias = substr($alias, 3);
		}

		return $alias !== '' ? $alias : '-';
	}

	/**
	 * Lokale Spielersuche in tl_wertungsportal_persons als Namensanfang-Suche.
	 * Wird als Fallback genutzt, wenn die Wertungsportal-API keine Treffer
	 * liefert (die API vergleicht nur komplette Felder — "müll" findet dort
	 * kein "müller"). Die Rückgabe hat das Format einer API-Antwort der
	 * Funktion Spielerliste, damit die Helper-Klasse Spielersuche sie
	 * unverändert aufbereiten kann.
	 *
	 * PERFORMANCE (gemessen am Livesystem 27.07.2026): Die erste Fassung
	 * suchte mit führendem Platzhalter (LIKE '%x%') und prüfte die laufende
	 * Mitgliedschaft per EXISTS-Unterabfrage in derselben WHERE-Klausel.
	 * Beides ist nicht indexierbar — die Abfrage lief als vollständiger
	 * Tabellendurchlauf über alle Personen und wertete je Zeile die
	 * Unterabfrage aus: 5,4 Sekunden je erfolgloser Suche.
	 * Jetzt: Suche am Namensanfang (nutzt den Index auf den Aliasfeldern)
	 * und Mitgliedschaftsprüfung nachgelagert nur für die Kandidaten.
	 *
	 * UMLAUTE: Gesucht wird über die Aliasfelder (lastnameAlias/
	 * firstnameAlias) statt über die Klarnamen — Suchbegriff und gespeicherter
	 * Name laufen beide durch Helper::alias(). Damit findet "müller" auch ein
	 * als "Mueller" gemeldetes Konto und umgekehrt. Voraussetzung ist die
	 * Migration, die den Bestand mit Aliasen versieht.
	 *
	 * @param $nachname    Nachname (Namensanfang, Rohstring ohne Slug)
	 * @param $vorname     Vorname (Namensanfang, optional)
	 * @param $limit       Maximale Trefferzahl
	 * @return array|false API-förmiges Ergebnis oder false ohne Treffer
	 */
	public static function lokaleSpielersuche($nachname, $vorname = '', $limit = 300)
	{
		$nachname = trim((string) $nachname);
		$vorname = trim((string) $vorname);

		// Ein Nachname von mindestens drei Zeichen ist Pflicht: Kürzere
		// Eingaben liefern zehntausende Zeilen, und eine Suche nur über den
		// Vornamen könnte den Namensindex nicht nutzen (Tabellendurchlauf).
		// Der Vorname verfeinert lediglich das Ergebnis
		if(mb_strlen($nachname) < 3) return false;

		// Suchbegriffe in Aliase umwandeln (Umlaute, Groß-/Kleinschreibung).
		// "-" bedeutet: keine verwertbaren Zeichen — damit ist nicht zu suchen
		$nachnameAlias = self::alias($nachname);
		$vornameAlias = self::alias($vorname);

		if($nachnameAlias === '' || $nachnameAlias === '-') return false;
		if($vornameAlias === '-') $vornameAlias = '';

		// Suchbedingungen aufbauen (LIKE-Platzhalter im Suchbegriff entschärfen).
		// Verstorbene und Blacklist-Personen bleiben wie in der Bestenliste außen vor
		$bedingungen = array
		(
			"p.published = '1'",
			"p.verstorben != '1'",
			"p.blocked != '1'",
		);
		$werte = array();

		$bedingungen[] = 'p.lastnameAlias LIKE ?';
		$werte[] = addcslashes($nachnameAlias, '%_\\').'%';

		if($vornameAlias !== '')
		{
			$bedingungen[] = 'p.firstnameAlias LIKE ?';
			$werte[] = addcslashes($vornameAlias, '%_\\').'%';
		}

		// Mehr Kandidaten laden als am Ende angezeigt werden, weil gleich noch
		// die Abgemeldeten herausfallen. Nur die benötigten Spalten holen —
		// die Personentabelle führt rund 40 Felder (Adresse, Datenschutz,
		// Import-Zusatzdaten), die für die Trefferliste keine Rolle spielen
		// Sortiert wird nach den Aliasfeldern, damit der zusammengesetzte Index
		// (published, lastnameAlias, firstnameAlias) auch die Sortierung
		// abdeckt und MySQL das Ergebnis nicht nachträglich sortieren muss
		$objPersonen = \Database::getInstance()->prepare("SELECT p.id, p.nuLigaPersonId, p.firstname, p.lastname, p.rating, p.`index`, p.fideId, p.weekOfLastTournamentEvaluation FROM tl_wertungsportal_persons p WHERE ".implode(' AND ', $bedingungen)." ORDER BY p.lastnameAlias, p.firstnameAlias LIMIT ".((int) $limit * 2))
		                                       ->execute(...$werte);

		if(!$objPersonen->numRows) return false;

		// Personen einsammeln (DTO im Format der API-Personendatensätze)
		$personen = array();
		while($objPersonen->next())
		{
			$row = $objPersonen->row();
			$dto = array
			(
				'nuLigaPersonId' => $row['nuLigaPersonId'],
				'firstname'      => $row['firstname'],
				'lastname'       => $row['lastname'],
				'rating'         => $row['rating'] ? (int) $row['rating'] : false,
				'index'          => $row['index'] ? (int) $row['index'] : false,
				'memberships'    => array(),
			);
			if($row['fideId']) $dto['fideId'] = $row['fideId'];
			if((string) $row['weekOfLastTournamentEvaluation'] !== '') $dto['weekOfLastTournamentEvaluation'] = $row['weekOfLastTournamentEvaluation'];
			$personen[$row['id']] = $dto;
		}

		// Laufende Mitgliedschaften der Kandidaten in einem Rutsch laden
		// (ACTIVE zuerst, die Spielersuche-Aufbereitung bricht beim ersten
		// Aktiv-Status ab). Der Filter läuft hier statt als Unterabfrage in
		// der Personensuche: Er greift nur auf die wenigen Kandidaten statt
		// auf jede Zeile der Personentabelle. Beendete Mitgliedschaften
		// bleiben außen vor, damit bei einem Vereinswechsel nicht der alte
		// Verein erscheint; das Datum liegt als TT.MM.JJJJ vor und wird wie
		// in der Mitgliedschafts-Sortierung nach JJJJMMTT umgestellt
		$laufend = "(m.spielgenehmigungBis = '' OR CONCAT(SUBSTRING(m.spielgenehmigungBis, 7, 4), SUBSTRING(m.spielgenehmigungBis, 4, 2), SUBSTRING(m.spielgenehmigungBis, 1, 2)) >= ?)";

		$objMitgliedschaften = \Database::getInstance()->prepare("SELECT m.pid, m.vkz, m.clubName, m.licenceState FROM tl_wertungsportal_persons_memberships m WHERE m.pid IN (".implode(',', array_map('intval', array_keys($personen))).") AND m.published = 1 AND ".$laufend." ORDER BY m.licenceState")
		                                               ->execute(date('Ymd'));
		while($objMitgliedschaften->next())
		{
			if(!isset($personen[$objMitgliedschaften->pid])) continue;
			$personen[$objMitgliedschaften->pid]['memberships'][] = array
			(
				'vkz'          => $objMitgliedschaften->vkz,
				'clubName'     => $objMitgliedschaften->clubName,
				'licenceState' => $objMitgliedschaften->licenceState,
			);
		}

		// Abgemeldete Spieler (keine laufende Mitgliedschaft) entfernen und
		// auf die gewünschte Trefferzahl kürzen
		$gefiltert = array();
		foreach($personen as $person)
		{
			if(empty($person['memberships'])) continue;
			$gefiltert[] = $person;
			if(count($gefiltert) >= $limit) break;
		}

		if(!count($gefiltert)) return false;

		// API-förmige Antwort bauen und FIDE-Daten (Elo/Titel) anreichern
		$result = array
		(
			'error'     => false,
			'http_code' => 200,
			'body'      => array('data' => $gefiltert),
		);

		return self::setFIDEDaten($result, array('funktion' => 'Spielerliste'));
	}

	/**
	 * Lokale Turniersuche in tl_wertungsportal_tournaments über das Aliasfeld.
	 * Wird als Fallback genutzt, wenn die Wertungsportal-API keine Treffer
	 * liefert. Sie hilft in zwei Fällen:
	 *
	 * 1. UMLAUTE — eine Suche nach "büchenbach" findet an der Schnittstelle
	 *    nichts, wenn das Turnier dort als "Buechenbach" hinterlegt ist.
	 *    Hier laufen Suchbegriff und gespeicherte Bezeichnung beide durch
	 *    Helper::alias(), die Schreibweise spielt also keine Rolle mehr.
	 * 2. TEILWORTE — die label-Abfrage von nu vergleicht nur den Anfang der
	 *    Bezeichnung; lokal wird an beliebiger Stelle gesucht.
	 *
	 * WICHTIG: Die lokale Turniertabelle ist ein Spiegel dessen, was über
	 * frühere Abfragen schon einmal durchgelaufen ist — sie ist NICHT
	 * vollständig. Die Trefferliste kann deshalb weniger Turniere enthalten,
	 * als die Schnittstelle kennen würde. Deswegen läuft die lokale Suche
	 * ausschließlich als Fallback und weist die Anzeige darauf hin.
	 *
	 * @param $suche       Suchbegriff (Rohstring)
	 * @param $von         Beginn des Zeitraums (JJJJ-MM-TT), optional
	 * @param $bis         Ende des Zeitraums (JJJJ-MM-TT), optional
	 * @param $zps         VKZ-Präfix des Verbands, optional
	 * @param $limit       Maximale Trefferzahl
	 * @return array|false API-förmiges Ergebnis oder false ohne Treffer
	 */
	public static function lokaleTurniersuche($suche, $von = '', $bis = '', $zps = '', $limit = 500)
	{
		$alias = self::alias($suche);

		// Ohne verwertbaren Suchbegriff hat die lokale Suche keinen Sinn:
		// Sie würde den ganzen Spiegelbestand ausgeben und damit vortäuschen,
		// es handle sich um ein vollständiges Suchergebnis
		if($alias === '' || $alias === '-') return false;

		$bedingungen = array("t.published = '1'", 't.labelAlias LIKE ?');
		$werte = array('%'.addcslashes($alias, '%_\\').'%');

		// Zeitraum wie an der Schnittstelle über das Turnierende eingrenzen.
		// enddate steht als JJJJ-MM-TT in der Tabelle und ist damit direkt
		// vergleichbar; leere Werte bleiben außen vor
		if($von !== '')
		{
			$bedingungen[] = "t.enddate != '' AND t.enddate >= ?";
			$werte[] = $von;
		}
		if($bis !== '')
		{
			$bedingungen[] = "t.enddate != '' AND t.enddate <= ?";
			$werte[] = $bis;
		}

		// Verbandsfilter: nu sucht über das Präfix der VKZ, hier genauso
		$zps = rtrim((string) $zps, '0');
		if($zps !== '')
		{
			$bedingungen[] = 't.vkz LIKE ?';
			$werte[] = addcslashes($zps, '%_\\').'%';
		}

		$objTurniere = \Database::getInstance()->prepare("SELECT t.uuid, t.label, t.vkz, t.enddate, t.playerCount, t.referentFirstname, t.referentLastname FROM tl_wertungsportal_tournaments t WHERE ".implode(' AND ', $bedingungen)." ORDER BY t.enddate DESC LIMIT ".(int) $limit)
		                                       ->execute(...$werte);

		if(!$objTurniere->numRows) return false;

		// Antwort im Format der API-Funktion Turnierliste aufbauen, damit die
		// Helper-Klasse Turniersuche sie unverändert aufbereiten kann
		$daten = array();
		while($objTurniere->next())
		{
			$daten[] = array
			(
				'uuid'              => $objTurniere->uuid,
				'label'             => $objTurniere->label,
				'vkz'               => $objTurniere->vkz,
				'enddate'           => $objTurniere->enddate,
				'playerCount'       => $objTurniere->playerCount,
				'referentFirstname' => $objTurniere->referentFirstname,
				'referentLastname'  => $objTurniere->referentLastname,
			);
		}

		return array
		(
			'error'     => false,
			'http_code' => 200,
			'body'      => array('data' => $daten),
		);
	}

	/**
	 * Überprüft den Suchbegriff für eine Spielersuche
	 * @param $search      Suchbegriff
	 * @return array       Array mit Typ und Vorname+Nachname und Vorname+Nachname gedreht
	 */
	public static function checkSearchstringPlayer($search)
	{
		// Rückgabefelder vorbelegen, damit auch pkz/zps sowie der Komma-Zweig
		// keine undefinierten Variablen zurückgeben
		$vorname = $vorname2 = $nachname = $nachname2 = '';

		if(is_numeric($search)) $typ = 'pkz'; // Eine PKZ wurde übergeben
		elseif(strlen($search) == 10 && substr($search,5,1) == '-') $typ = 'zps'; // Eine ZPS wurde übergeben
		else
		{
			// HTML-Entities entfernen (Fixt das Problem mit O'Donnell als Suchstring)
			$search = html_entity_decode($search, ENT_QUOTES | ENT_XML1, 'UTF-8');

			// Ein Name wurde übergeben, zuerst akademische Titel entfernen
			$search = str_replace(',Prof. Dr.','',$search);
			$search = str_replace(',Prof.Dr.','',$search);
			$search = str_replace(',Prof.','',$search);
			$search = str_replace(',Dr.','',$search);
			$search = str_replace('Prof. Dr. ','',$search);
			$search = str_replace('Prof.Dr. ','',$search);
			$search = str_replace('Prof. ','',$search);
			$search = str_replace('Dr. ','',$search);

			// Name am Komma trennen
			$typ = 'name';
			$strKomma = explode(',', $search);
			if(isset($strKomma[1]))
			{
				// Suchbegriff entspricht Nachname, Vorname
				$nachname = trim($strKomma[0]);
				$vorname = trim($strKomma[1]);
			}
			else
			{
				$nachname = $search;
				$vorname = '';
				// Auf Leerzeichen als Trennzeichen überprüfen
				$strLeer = explode(' ', $search);
				if(isset($strLeer[1]))
				{
					// Suchbegriff entspricht Vorname Nachname (wahrscheinlich)
					$nachname2 = trim($strLeer[1]);
					$vorname2 = trim($strLeer[0]);
				}
				else
				{
					// Suchbegriff entspricht Nachname (wahrscheinlich)
					$nachname2 = trim($search);
					$vorname2 = '';
				}
			}
		}

		return array
		(
			'typ'       => $typ,
			'vorname'   => $vorname,
			'vorname2'  => $vorname2,
			'nachname'  => $nachname,
			'nachname2' => $nachname2,
		);
	}

	/**
	 * Funktion Laendercode
	 * @param     string $ioc      dreistelliger IOC-Code
	 * @return    string           CSS-Klassen für Verwendung CSS-Datei des Bundles components/flag-icon-css
	 */
	public static function Laendercode($ioc)
	{
		$ioc = trim(strtoupper($ioc));
		if(!$ioc) return '';

		foreach(self::$countries as $country)
		{
			if($country['ioc'] == $ioc)
			{
				return 'flag-icon flag-icon-'.strtolower($country['alpha2']);
			}
		}
		return '';
	}

	/**
	 * Funktion DownloadDatei
	 * Lädt eine Datei per Curl herunter und prüft das Ergebnis:
	 * Curl-Fehler, HTTP-Status 200 und (optional) gültiges Zip-Archiv.
	 * Bei Fehlschlag wird der Download nach einer kurzen Pause wiederholt,
	 * unvollständige Dateien werden gelöscht statt liegen gelassen
	 * (der nu-Server liefert gelegentlich abgeschnittene Zips).
	 *
	 * @param     string $url      Quell-URL
	 * @param     string $ziel     Zielpfad im Dateisystem
	 * @param     int    $versuche Maximale Anzahl Versuche
	 * @param     bool   $zipCheck Datei nach dem Download als Zip-Archiv prüfen
	 * @return    array            array('success' => bool, 'error' => string, 'versuche' => int)
	 */
	public static function DownloadDatei($url, $ziel, $versuche = 3, $zipCheck = true)
	{
		$fehler = '';

		for($versuch = 1; $versuch <= $versuche; $versuch++)
		{
			if($versuch > 1) sleep(5); // Kurze Pause vor der Wiederholung

			$fp = fopen($ziel, 'w');

			if($fp === false)
			{
				return array('success' => false, 'error' => 'Zieldatei kann nicht geschrieben werden: '.$ziel, 'versuche' => $versuch);
			}

			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_FILE, $fp);
			curl_setopt($ch, CURLOPT_TIMEOUT, 3600);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			curl_exec($ch);
			$curlFehler = curl_errno($ch) ? curl_error($ch) : '';
			$httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
			curl_close($ch);
			fclose($fp);

			if($curlFehler)
			{
				$fehler = 'Curl-Fehler: '.$curlFehler;
			}
			elseif($httpCode != 200)
			{
				$fehler = 'HTTP-Status '.$httpCode;
			}
			elseif($zipCheck)
			{
				// Zip-Konsistenzprüfung erkennt auch abgeschnittene Downloads
				$zip = new \ZipArchive();
				$res = $zip->open($ziel, \ZipArchive::CHECKCONS);

				if($res === true)
				{
					$zip->close();
					return array('success' => true, 'error' => '', 'versuche' => $versuch);
				}

				$fehler = 'Zip-Archiv defekt oder unvollständig (Code '.$res.')';
			}
			else
			{
				return array('success' => true, 'error' => '', 'versuche' => $versuch);
			}

			@unlink($ziel); // Defekte Datei nicht liegen lassen
		}

		return array('success' => false, 'error' => $fehler, 'versuche' => $versuche);
	}

}