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

# boz-mw
require '../includes/boz-mw/autoload.php';

# bot credentials
require '../config.php';

# local functions
require 'includes/functions.php';

# classes shortcut
use wm\Wikidata;
use wb\DataModel;
use wb\Label;
use wb\StatementItem;
use wb\StatementString;
use wb\StatementQuantity;
use wb\StatementGlobeCoordinate;
use wb\StatementTime;
use wb\DataValueTime;
use wb\Snaks;
use wb\SnakItem;

# login and fetch the CSRF token
define( 'CSRF',
	Wikidata::getInstance()->login()->fetch( [
		'action' => 'query',
		'meta'   => 'tokens',
		'type'   => 'csrf'
	] )->query->tokens->csrftoken
);

# data
$handle = fopen('data/italian-parks-data.csv', 'r') or die('asd');

// Wikidata references
$REFERENCES = [ [
	'snaks' => (
		new Snaks( [
			// stated in: Ministry of the Environment
			new SnakItem( 'P248', 'Q3858479' )
		] )
	)->getAll()
] ];

$row = 0;
while( ( $data = fgetcsv( $handle, 1000, ',' ) ) !== false ) {

	// skip headers
	if( $row++ < 3 ) {
		continue;
	}

	// null empty values
	foreach( $data as & $value ) {
		$value = trim( $value );
		$value = str_replace( "\n", '', $value ); // the dataset is a bit dirty
		if( ! $value ) {
			$value = null;
		}
	}

	// Add missing columns to don't make list() errors
	$n = count( $data );
	for( $i = $n; $i < 20; $i++ ) {
		$data[] = null;
	}

	$P1435 = [];
	list(
		$wikidata_ID,
		$P131,  // located in the administrative territorial entity
		        // string(2)
		$P625,  // coordinate location
		        // string coordinates
		$P4800, // EUAP ID
		        // string
		$P809,  // WDPA ID
		        // string
		$P627,  // string Q-ID
		$P3425, // Natura 2000 site ID
		        // string ID
		$label, // string [[something]]
		$P2046, // area
		        // float
				// various heritage designation
		$P1435['Q796174'],
		$P1435['Q1191622'],
		$P1435['Q2463705'],
		$P1435['Q46169'],
		$P1435['Q48078443'],
		$P1435['Q3936952'],
		$P1435['Q3936950'],
		$P1435['Q23790'],
		$P126, // maintained by
		       // string
		$P571  // inception
		       // int
	) = $data;

	// fetch existing Wikidata entity
	$existing = null;
	if( $wikidata_ID = 'Q4115189' ) {
		$existing = DataModel::createFromObject( 
			Wikidata::getInstance()->fetch( [
				'action' => 'wbgetentities',
				'ids'    => $wikidata_ID,
				'props'  => 'info|sitelinks|aliases|labels|claims|datatype'
			] )->entities->{ $wikidata_ID }
		);
	}

	$new = new DataModel();
	$statements = [];
	$summary = 'test before [[Wd:Requests for permissions/Bot/Valerio Bozzolan bot 4|importing italian parks]]';

	// label
	if( $label ) {
		if( ! $existing || ! $existing->hasLabelsInLanguage('it') ) {
			$label = filter_label( $label );
			$new->setLabel( new Label( 'it', $label ) );
			$summary .= ' +label[it]';
		}
	}

	// instance of: nature reserve
	$statements[] = new StatementItem( 'P31', 'Q179049' );

	// located in the administrative territorial entity
	if( $P131 ) {
		// can specify multiple cities
		$P131_city_IDs = plate_2_wikidataIDs( $P131 );
		foreach( $P131_city_IDs as $P131_city_ID ) {
			$statements[] =	new StatementItem( 'P131', $P131_city_ID );
		}
	}

	// coordinate location
	if( $P625 ) {
		list( $lat, $lng ) = filter_coordinates( $P625 );
		$statements[] = new StatementGlobeCoordinate( 'P625', $lat, $lng, 0.01 ); // last: precision
	}

	// EUAP ID
	if( $P4800 ) {
		// can specify multiple values
		foreach( explode( '/', $P4800 ) as $P4800_value ) {
			$statements[] = new StatementString( 'P4800', trim( $P4800_value ), 0.01 );
		}
	}

	// WDPA ID
	if( $P809 ) {
		foreach( explode( '/', $P809 ) as $P809_value ) {
			$new->addClaim(
				( new StatementString( 'P809', trim( $P809_value ) ) )
				->setReferences( $REFERENCES )
			);
		}
	}

	// Natura 2000 site ID
	if( $P3425 ) {
		$statements[] = new StatementString( 'P3425', trim( $P3425 ) );
	}

	// area
	if( $P2046 ) {
		$P2046 = (float) str_replace( ',', '.', $P2046 );
		$statements[] = new StatementQuantity( 'P2046', $P2046, 'Q35852' ); // 'Q35852' = hectare
	}

	// heritage designation
	foreach( $P1435 as $P1435_item => $selected ) {
		if( ! empty( trim( $selected ) ) ) {
			$statements[] = new StatementItem( 'P1435', $P1435_item );
		}
	}

	/* TODO
	if( $P126 ) {
		$statements[] = 
	}
	*/

	// inception
	if( $P571 ) {
		$P571 = (int) $P571;
		$statements[] = new StatementTime( 'P571', "+$P571-00-00T00:00:00Z", DataValueTime::PRECISION_YEARS );
		
	}

	// append statements without duplicating
	foreach( $statements as $statement ) {
		$property = $statement->getMainsnak()->getProperty();
		if( ! $existing || ! $existing->hasClaimsInProperty( $property ) ) {
			$new->addClaim(
				$statement->setReferences( $REFERENCES )
			);
			$summary .= " +[[P:$property]]";
		}
	}

	// check and save
	echo $new->getJSON( JSON_PRETTY_PRINT ) . "\n";
	echo $summary . "\n";
	echo "Save? ";
	if( read('y') === 'y' ) {
		echo "Saving\n";

		$args = [
			'action'  => 'wbeditentity',
			'token'   => CSRF,
			'bot'     => 1,
			'data'    => $new->getJSON(),
			'summary' => $summary,
		];

		if( $wikidata_ID ) {
			// save existing
			$args['id'] = $wikidata_ID;
		}
		Wikidata::getInstance()->post( $args );
	}
}

fclose($handle);
