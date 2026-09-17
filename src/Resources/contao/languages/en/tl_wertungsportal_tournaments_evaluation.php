<?php

/*
 * English language file for tl_wertungsportal_tournaments_evaluation (Contao 4.13)
 */

// Legends
$GLOBALS['TL_LANG']['tl_wertungsportal_tournaments_evaluation']['player_legend']  = 'Player';
$GLOBALS['TL_LANG']['tl_wertungsportal_tournaments_evaluation']['club_legend']    = 'Club';
$GLOBALS['TL_LANG']['tl_wertungsportal_tournaments_evaluation']['dwz_legend']     = 'Evaluation';
$GLOBALS['TL_LANG']['tl_wertungsportal_tournaments_evaluation']['publish_legend'] = 'Publishing';

// Fields
$GLOBALS['TL_LANG']['tl_wertungsportal_tournaments_evaluation']['playerUuid']               = ['Player UUID', 'UUID of the player in this tournament.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tournaments_evaluation']['nuLigaPersonId']           = ['nuLiga person ID', 'ID of the person in nuLiga.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tournaments_evaluation']['firstname']                = ['First name', 'First name of the player.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tournaments_evaluation']['lastname']                 = ['Last name', 'Last name of the player.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tournaments_evaluation']['birthyear']                = ['Year of birth', 'Year of birth of the player.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tournaments_evaluation']['vkz']                      = ['Club number (VKZ)', 'Club number at the time of the tournament.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tournaments_evaluation']['memberNo']                 = ['Member number', 'Member number of the player.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tournaments_evaluation']['clubName']                 = ['Club name', 'Name of the club at the time of the tournament.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tournaments_evaluation']['fideId']                   = ['FIDE ID', 'FIDE identification number of the player.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tournaments_evaluation']['playerNo']                 = ['Player number', 'Number of the player in the tournament.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tournaments_evaluation']['eloPlayer']                = ['Elo player', 'The player was rated by Elo.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tournaments_evaluation']['member']                   = ['Member', 'Membership status as delivered by the API. No new DWZ is shown for non-members (rating regulations 3.4.3). Empty = unknown (row from before version 1.45.0).'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tournaments_evaluation']['member_optionen']          = ['1' => 'Member', '0' => 'Non-member'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tournaments_evaluation']['ratingOld']                = ['DWZ old', 'DWZ rating before the tournament.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tournaments_evaluation']['ratingOldDisplayString']   = ['DWZ old (display text)', 'Display text delivered by the API. For non-members this is the only place holding the entry rating, without index (usually an Elo).'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tournaments_evaluation']['indexOld']                 = ['Index old', 'DWZ index before the tournament.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tournaments_evaluation']['ratingNew']                = ['DWZ new', 'DWZ rating after the tournament.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tournaments_evaluation']['indexNew']                 = ['Index new', 'DWZ index after the tournament.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tournaments_evaluation']['ratingNewDisplayString']   = ['DWZ new (display text)', 'Display text delivered by the API. For participants without any rating this is the only place holding, in brackets, the calculated rating they count with for their opponents, e.g. "(1318)". The website shows it as entry rating, without brackets.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tournaments_evaluation']['factorK']                  = ['Factor K', 'Development coefficient K of the evaluation.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tournaments_evaluation']['averageRatingCompetitors'] = ['Average rating of competitors', 'Average DWZ rating of the opponents.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tournaments_evaluation']['wins']                     = ['Points', 'Points scored in the tournament.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tournaments_evaluation']['numberOfGames']            = ['Games', 'Number of rated games.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tournaments_evaluation']['winsExpected']             = ['Expected points', 'Expected score according to the calculation.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tournaments_evaluation']['tournamentPerformance']    = ['Tournament performance', 'DWZ performance in the tournament.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tournaments_evaluation']['published']                = ['Publish', 'Show the evaluation entry on the website.'];

// Buttons
$GLOBALS['TL_LANG']['tl_wertungsportal_tournaments_evaluation']['new']    = ['New evaluation entry', 'Create a new evaluation entry'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tournaments_evaluation']['edit']   = ['Edit evaluation entry', 'Edit evaluation entry ID %s'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tournaments_evaluation']['copy']   = ['Duplicate evaluation entry', 'Duplicate evaluation entry ID %s'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tournaments_evaluation']['delete'] = ['Delete evaluation entry', 'Delete evaluation entry ID %s'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tournaments_evaluation']['toggle'] = ['Publish/unpublish evaluation entry', 'Publish/unpublish evaluation entry ID %s'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tournaments_evaluation']['show']   = ['Show details', 'Show details of evaluation entry ID %s'];
