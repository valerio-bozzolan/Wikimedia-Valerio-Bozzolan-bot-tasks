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
use wb\DataModel;
use wb\Snaks;
use wb\SnakItem;
use wb\StatementItem;

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
		$P1435['Q796174'],
		$P1435['Q1191622'],
		$P1435['Q2463705'],
		$P1435['Q46169'],
		$P1435['Q48078443'],
		$P1435['Q3936952'],
		$P1435['Q3936950'],
		$P1435['Q23790'],
		$P126, // string
		$P571  // int
	) = $data;

	$new_data = new DataModel();

	// instance of: nature reserve
	$new_data->addClaim(
		( new StatementItem( 'P31', 'Q179049' ) )
			->setReferences( $REFERENCES )
	);

	// located in the administrative territorial entity
	if( $P131 ) {
		// can specify multiple cities
		$P131_city_IDs = plate_2_wikidataIDs( $P131 );
		foreach( $P131_city_IDs as $P131_city_ID ) {
			$new_data->addClaim(
				( new StatementItem( 'P131', $P131_city_ID ) )	
				->setReferences( $REFERENCES )
			);
		}
	}

	// coordinate location
	if( $P625 ) {
		list( $lat, $lng ) = filter_coordinates( $P625 );
		$new_data->addClaim(
			( new StatementGlobeCoordinate( 'P625', $lat, $lng ) )
			->setReferences( $REFERENCES )
		);
	}

	// EUAP ID
	if( $P4800 ) {
		// can specify multiple values
		foreach( explode( '/', $P4800 ) as $P4800_value ) {
			$new_data->addClaim(
				( new StatementString( 'P4800', trim( $P4800_value ) ) )
				->setReferences( $REFERENCES )
			);
		}
	}

	// WDPA ID
	if( $P809 ) {
		foreach( explode( '/', $P809 ) as $P809_value ) {
			$new_data->addClaim(
				( new StatementString( 'P809', trim( $P809_value ) ) )
				->setReferences( $REFERENCES )
			);
		}
	}

	// Natura 2000 site ID
	if( $P3425 ) {
		$new_data->addClaim(
			( new StatementString( 'P3425', trim( $P3425 ) ) )
			->setReferences( $REFERENCES )
		);
	}

	// area
	if( $P2046 ) {
		$P2046 = (float) str_replace( ',', '.', $P2046 );
		$new_data->addClaim(
			( new StatementQuantity( 'P2046', $P2046, 'Q35852' ) ) // 'Q35852' = hectare
			->setReferences( $REFERENCES )
		);
	}

	if( $wikidata_ID ) {
		// Edit
	} else {
		// Create
	}
}

fclose($handle);
