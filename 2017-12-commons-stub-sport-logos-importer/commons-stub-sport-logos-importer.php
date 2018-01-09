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

require '../config.php';
require '../includes/boz-mw/autoload.php';

define('SUMMARY', '[[Commons:Bots/Requests/Valerio Bozzolan bot (2)|uniforming italian sport icons]]');

#####################
# Commons Login token
#####################

mw\APIRequest::$WAIT_POST = 0.2;

$logintoken = \wm\Commons::getInstance()->fetch( [
	'action' => 'query',
	'meta'   => 'tokens',
	'type'   => 'login'
] )->query->tokens->logintoken;

###############
# Commons login
###############

$response = \wm\Commons::getInstance()->post( [
	'action'     => 'login',
	'lgname'     => WIKI_USERNAME,
	'lgpassword' => WIKI_PASSWORD,
	'lgtoken'    => $logintoken
] );
if( ! isset( $response->login->result ) || $response->login->result !== 'Success' ) {
	throw new Exception("login failed");
}

#############################
# Commons CSRF token (logged)
#############################

$COMMONS_CSRF_TOKEN = \wm\Commons::getInstance()->fetch( [
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

		// Retrieve wikitext
		$commons_title = "File:{$ensport_uc} {$nation->ennation}.png";
		$commons_wikitext = commons_wikitext( $commons_title );

		// Transform to object
		$commons_wikitext_object = new \mw\Wikitext( \wm\Commons::getInstance(), $commons_wikitext );

		// Titles
		$nation_category            = "Flags of {$nation->ennation}";
		$nation_category_icons      = "Flags of {$nation->ennation} icons";
		$nation_category_png        = "PNG flags of {$nation->ennation}";
		$nation_category_variations = "Variations on flags of {$nation->ennation}";

		// Clear summary
		$summary = '';

		// Add categories
		$categories = [
			$nation_category_icons,
			$nation_category_png,
			$nation_category_variations
		];
		$has_one = false;
		foreach( $categories as $category ) {
			if( $commons_wikitext_object->hasCategory( $category ) ) {
				$has_one = true;
			} else {
				if( commons_page_exists("Category:$category") ) {
					$has_one = true;
					$commons_wikitext_object->addCategory( $category );
					$summary .= "; [[Category:$category]]";
				}
			}
		}

		// Add the generic national category only if others are missing
		if( ! $has_one ) {
			if( ! $commons_wikitext_object->hasCategory( $nation_category ) ) {
				if( commons_page_exists( "Category:$nation_category" ) ) {
					$commons_wikitext_object->addCategory( $nation_category );
					$summary .= "; +[[Category:$nation_category]]";
				}
			}
		}

		// Update description
		$description = "{{en|Icon for {$sport->ensport} from {$nation->ennation}}}{{it|Icona per {$sport->itsportmen} {$nation->itpeople}}}";
		$description_pattern = '/\|.*Description *= *' . preg_quote( $description ) . '/';
		if( 1 !== $commons_wikitext_object->pregMatch( $description_pattern ) ) {
			$description_pattern = '/(\| *Description *= *).*?(\n\|)/s';
			$commons_wikitext_object->pregReplace( $description_pattern, "\\1$description\\2", 1 );
			$summary .= "; updated en/it description";
		}

		// Save
		if( $summary ) {
			commons_save( $commons_title, $commons_wikitext_object->getWikitext(), SUMMARY . $summary );
		}

		echo "OK $commons_title\n";
	}
}

class Sport {
	function __construct( $itwikiprefix, $ensport, $ensportmen, $itsport, $itsportmen ) {
		$this->itwikiprefix = $itwikiprefix;
		$this->ensport      = $ensport;
		$this->ensportmen   = $ensportmen;
		$this->itsport      = $itsport;
		$this->itsportmen   = $itsportmen;
	}

	static function createFromData( $data ) {
		return new self(
			$data[0],
			$data[1],
			$data[2],
			$data[3],
			$data[4]
		);
	}
}

class Nation {
	function __construct( $itpeople, $ennation ) {
		$this->itpeople = $itpeople;
		$this->ennation = $ennation;
	}

	static function createFromData( $data ) {
		return new self(
			$data[0],
			$data[1]
		);
	}
}

function wiki_save( $wiki, $csrf, $title, $content, $summary ) {
	echo "\n";
	echo "########### Saving [[$title]]: ##########\n";
	echo $content;
	echo "\n";
	echo "#########################################\n";
	echo "Confirm summary: |$summary|\n";
	read();
	return $wiki->post( [
		'action'   => 'edit',
		'title'    => $title,
		'summary'  => $summary,
		'text'     => $content,
		'token'    => $csrf,
		'bot'      => ''
	] );
}

function read( $default = '' ) {
	$v = chop( fgets(STDIN) );
	return $v ? $v : $default;
}

function commons_save( $title, $content, $summary ) {
	return wiki_save(
		\wm\Commons::getInstance(),
		$GLOBALS['COMMONS_CSRF_TOKEN'],
		$title,
		$content,
		$summary
	);
}

function commons_wikitext( $page ) {
	// Wikitext
	// https://it.wikipedia.org/w/api.php?action=query&prop=revisions&titles=linux+%28kernel%29&rvprop=content
	$pages = \wm\Commons::getInstance()->fetch( [
		'action' => 'query',
		'prop'   => 'revisions',
		'titles' => $page,
		'rvprop' => 'content'
	] );
	foreach( $pages->query->pages as $page ) {
		return $page->revisions[0]->{'*'};
	}
	throw new Exception("missing page $page");
}

function nation_from_it_people( $it_people ) {
	foreach( $GLOBALS['NATIONS'] as $nation ) {
		if( $nation->itpeople === $it_people ) {
			return $nation;
		}
	}
	throw new Exception("missing it people $it_people");
}

function commons_page_exists( $page ) {
		$response = \wm\Commons::getInstance()->fetch( [
			'action' => 'query',
			'prop'   => 'info',
			'titles' => $page
		] );
		return ! isset( $response->query->pages->{-1} );
}
