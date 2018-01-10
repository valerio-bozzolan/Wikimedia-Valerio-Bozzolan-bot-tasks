#!/usr/bin/php
<?php
# Copyright (C) 2017 Valerio Bozzolan
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program. If not, see <http://www.gnu.org/licenses/>.

# boz-mw
require '../includes/boz-mw/autoload.php';

# Auth tokens
require '../config.php';

# Local tools
require 'functions.php';
require 'classes.php';

define('SUMMARY', '[[Commons:Bots/Requests/Valerio Bozzolan bot (2)|uniforming italian sport icons]]');

#############################
# Commons CSRF token (logged)
#############################

$COMMONS_CSRF_TOKEN = \wm\Commons::getInstance()->login()->fetch( [
	'action' => 'query',
	'meta'   => 'tokens',
	'type'   => 'csrf'
] )->query->tokens->csrftoken;

################
# Prepare sports
################

$SPORTS = [];
$sports_data = trim( file_get_contents('sports.csv') );
$sports_data = explode( "\n", $sports_data );
array_shift( $sports_data );
foreach( $sports_data as $i => $sport_data ) {
	$sport_data = explode( ',', $sport_data );
	$sport = Sport::createFromData( $sport_data );
	$SPORTS[] = $sport;
}

#################
# Prepare nations
#################

$NATIONS = [];
$nations_data = trim( file_get_contents('nations.csv') );
$nations_data = explode( "\n", $nations_data );
array_shift( $nations_data );
foreach( $nations_data as $i => $nation_data ) {
	$nation_data = explode( ',', $nation_data );
	$nation = Nation::createFromData( $nation_data );
	$NATIONS[] = $nation;
}

foreach( $SPORTS as $sport ) {

	// Upper case
	$ensport_uc = ucfirst( $sport->ensport );

	foreach( $NATIONS as $nation ) {

		// Retrieve a Wikitext object
		$commons_title = "File:{$ensport_uc} {$nation->ennation}.png";
		$commons_wikitext = commons_wikitext( $commons_title );

		// Some categories
		$sport_category             = $sport->commonscat;
		$nation_category            = "Flags of {$nation->ennation}";
		$nation_category_icons      = "Flags of {$nation->ennation} icons";
		$nation_category_png        = "PNG flags of {$nation->ennation}";
		$nation_category_variations = "Variations on flags of {$nation->ennation}";

		// Clear summary
		$summary = '';

		// Add some nation-related categories... if they exist...
		$categories = [
			$nation_category_icons,
			$nation_category_png,
			$nation_category_variations
		];
		$wtf = true;
		foreach( $categories as $category ) {
			$done = wikitext_add_category_if_exists( $commons_wikitext, $category, $summary );
			if( $done ) {
				$wtf = false;
			}
		}

		// WTF! No previous nation-related category exist!
		if( $wtf ) {
			// Last chance
			wikitext_add_category_if_exists( $commons_wikitext, $nation_category, $summary );
		}

		// Add a sport-related category
		wikitext_add_category_if_exists( $commons_wikitext, $sport_category, $summary );

		// Destroy the damn {{Uncategorized}}
		if( 1 === $commons_wikitext->pregMatch('/{{Uncategorized\|.*?}}/') ) {
			$commons_wikitext->pregReplace( '/{{Uncategorized *\|.*?}}.*\n/', '' );
			$summary .= "; -[[Template:Uncategorized]]";
		}

		// Update description
		$description = "{{en|Icon for {$sport->ensport} from {$nation->ennation}}}{{it|Icona per {$sport->itsportmen} {$nation->itpeople}}}";
		$description_pattern = '/\|.*Description *= *' . preg_quote( $description ) . '/';
		if( 1 !== $commons_wikitext->pregMatch( $description_pattern ) ) {
			$description_pattern = '/(\| *Description *= *).*?(\n\|)/s';
			$commons_wikitext->pregReplace( $description_pattern, "\\1$description\\2", 1 );
			$summary .= "; updated en/it description";
		}

		// Save the whole stuff
		if( $summary ) {
			commons_save( $commons_title, $commons_wikitext->getWikitext(), SUMMARY . $summary );
		}

		echo "OK $commons_title! Yeah.\n";
	}
}
