#!/usr/bin/php
<?php
# Copyright (C) 2018 Valerio Bozzolan
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU Affero General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
# GNU Affero General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program. If not, see <http://www.gnu.org/licenses/>.

define( 'SUMMARY', 'Bot: [[Wikidata:Requests for permissions/Bot/Valerio Bozzolan bot 5|importing Lega Serie A soccer player ID]]' );

$ALWAYS = true;

// load boz-mw and configuration
require 'includes/boz-mw/autoload.php';
require '../config.php';

# CSV soccer players
$local_players    = explode( "\n", file_get_contents( 'data/players.csv'          ) );
array_shift( $local_players );    // strip CSV header

$wikidata_players = explode( "\n", file_get_contents( 'data/wikidata_players.csv' ) );
array_shift( $wikidata_players ); // strip CSV header

// Wikidata
$wd = \wm\Wikidata::getInstance()->login();
foreach( $local_players as $local_player ) {
	list( $soccer_id, $slug ) = explode( ',', $local_player );
	if( ! $slug ) {
		continue;
	}

	$matches = [];
	foreach( $wikidata_players as $wikidata_player ) {
		$wikidata_player = explode( ',', $wikidata_player );
		if( 2 === count( $wikidata_player ) && slug_match_name( $slug, $wikidata_player[ 1 ] ) ) {
			$matches[] = $wikidata_player;
		}
	}

	$n = count( $matches );
	if( ! $n ) {
		cli\Log::warn( "no match for $slug" );
		continue;
	} elseif( 1 === $n ) {
		$entity_id = $matches[ 0 ][ 0 ];
	} else {
		print_r( $matches );
		$entity_id = cli\Input::askInput( "Insert best Wikidata ID" );
	}

	// https://www.wikidata.org/w/api.pkhp?action=help&modules=wbgetentities
	$data_old = $wd->fetch( [
		'action' => 'wbgetentities',
		'ids'    => $entity_id,
		'props'  => 'claims'
	] );
	$data_old = wb\DataModel::createFromObject( $data_old->entities->{ $entity_id } );

	// append statements without duplicating
	$statements = [
		new wb\StatementString( 'P5339', $soccer_id ) // LegaVolley ID
	];
	$data_new = new wb\DataModel();
	foreach( $statements as $statement ) {
		$property = $statement->getMainsnak()->getProperty();
		if( ! $data_old || ! $data_old->hasClaimsInProperty( $property ) ) {
			$data_new->addClaim( $statement->setReferences( references() ) );
		}
	}

	// Wikidata new data
	// https://www.wikidata.org/w/api.php?action=help&modules=wbeditentity
	$wbeditentity = [
		'action'  => 'wbeditentity',
		'summary' => SUMMARY,
		'token'   => $wd->getToken( mw\Tokens::CSRF ),
		'bot'     => 1,
	];

	if( $data_new->countClaims() ) {
		cli\Log::info( "https://www.wikidata.org/wiki/$entity_id $slug $soccer_id" );
		if( $ALWAYS || 'y' === cli\Input::yesNoQuestion( "Save?" ) ) {
			$wd->post( array_replace( $wbeditentity, [
				'id'   => $entity_id,
				'data' => $data_new->getJSON()
			] ) );
		}
	} else {
		cli\Log::info( "Nothing to be done to $slug" );
	}
}

function references() {
	$snaks = new wb\Snaks( [
		// stated in: Lega Serie A
		new wb\SnakItem( 'P248', 'Q2427920' )
	] );
	return [ [ 'snaks' => $snaks->getAll() ] ];
}

function slug_match_name( $slug, $name ) {
	$slug_pattern = str_replace( '\-', '.*', preg_quote( $slug ) );
	return 1 === preg_match( "/$slug_pattern/", strtolower( $name ) );
}
