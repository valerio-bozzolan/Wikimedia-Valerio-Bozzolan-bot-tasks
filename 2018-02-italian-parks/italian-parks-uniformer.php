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
use wb\Description;
use wb\StatementItem;
use wb\StatementString;
use wb\StatementQuantity;
use wb\StatementGlobeCoordinate;
use wb\StatementTime;
use wb\DataValueTime;
use wb\Snaks;
use wb\SnakItem;
use wb\SnakTime;

// CLI options
$options = getopt('', [
	'sandbox', // to work in the sandbox
	'verbose',  // to inspect the data
	'line:'
] );

# login and fetch the CSRF token
define( 'CSRF',
	Wikidata::getInstance()->login()->fetch( [
		'action' => 'query',
		'meta'   => 'tokens',
		'type'   => 'csrf'
	] )->query->tokens->csrftoken
);

# CSV data
$handle = fopen( 'data/italian-parks-data.csv', 'r' ) or die( 'asd' );

// Wikidata references
$REFERENCES = [ [
	'snaks' => (
		new Snaks( [
			// stated in: Ministry of the Environment
			new SnakItem( 'P248', 'Q3858479' ),

			// retrieved: december 2017
			new SnakTime( 'P813', '+2017-12-00T00:00:00Z', DataValueTime::PRECISION_MONTHS )
		] )
	)->getAll()
] ];

$row = 0;
while( ( $data = fgetcsv( $handle, 1000, ',' ) ) !== false ) {

	// skip headers
	if( $row++ < 4 ) {
		continue;
	}

	if( isset( $options['line'] ) ) {
		if( $row < $options['line'] ) {
			continue;
		}
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

	if( isset( $options['sandbox'] ) ) {
		$wikidata_ID = 'Q4115189';
	}

	// fetch existing Wikidata entity
	$existing = null;
	if( $wikidata_ID ) {
		$existing = DataModel::createFromObject(
			Wikidata::getInstance()->fetch( [
				'action' => 'wbgetentities',
				'ids'    => $wikidata_ID,
				'props'  => 'info|sitelinks|aliases|labels|descriptions|claims|datatype'
			] )->entities->{ $wikidata_ID }
		);
	}

	// reset
	$new = new DataModel();
	$statements = [];
	$summary = '';

	// italian label
	if( $label ) {
		$label = filter_label( $label );
		if( ! $existing || ! $existing->hasLabelsInLanguage('it') ) {
			$new->setLabel( new Label( 'it', $label ) );
			$summary .= " $label";
			echo $label . "\n";
		}
	}

	// italian description e.g. ("riserva naturale in provincia di Sondrio ed in provincia di Torino")
	if( ! $existing || ! $existing->hasDescriptionsInLanguage('it') ) {
		$description = "riserva naturale";
		if( $P131 ) {
			$cities = array_map(
				function ( $city ) {
					return $city->label;
				},
				find_plates( $P131 )
			);
			if( $cities ) {
				$description .= ' in ' . human_implode( $cities );
			}
		}
		$new->setDescription( new Description( 'it', $description ) );
		$summary .= ' +description[it]';
		echo $description . "\n";
	}

	// instance of: nature reserve
	$statements[] = new StatementItem( 'P31', 'Q179049' );

	// country: Italy
	$statements[] = new StatementItem( 'P17', 'Q38' );

	// located in the administrative territorial entity
	if( $P131 ) {
		$P131_cities = find_plates( $P131 );
		foreach( $P131_cities as $P131_city ) {
			$statements[] = new StatementItem( 'P131', $P131_city->item );
		}
	}

	// coordinate location
	if( $P625 ) {
		list( $lat, $lng ) = filter_coordinates( $P625 );
		$statements[] = new StatementGlobeCoordinate( 'P625', $lat, $lng, 0.005 ); // last: precision
	}

	// EUAP ID
	if( $P4800 ) {
		foreach( explode( '/', $P4800 ) as $P4800_value ) {
			$statements[] = new StatementString( 'P4800', trim( $P4800_value ), 0.01 );
		}
	}

	// WDPA ID
	if( $P809 ) {
		foreach( explode( '/', $P809 ) as $P809_value ) {
			$statements[] = new StatementString( 'P809', trim( $P809_value ) );
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

	/* not now :)
	if( $P126 ) {
		$statements[] =
	}
	*/

	// inception
	if( $P571 ) {
		$P571 = (int) $P571;
		$statements[] = new StatementTime( 'P571', "+$P571-00-00T00:00:00Z", DataValueTime::PRECISION_YEARS );
	}

	// properties involved
	$properties = [];

	// append statements without duplicating
	foreach( $statements as $statement ) {
		$property = $statement->getMainsnak()->getProperty();
		if( ! $existing || ! $existing->hasClaimsInProperty( $property ) ) {
			$new->addClaim(
				$statement->setReferences( $REFERENCES )
			);
			$properties[] = $property;
			echo $statement->getMainSnak() . "\n";
		}
	}

	// add involved properties in the summary
	$properties = array_count_values( $properties );
	foreach( $properties as $property => $num ) {
		$summary .= " +[[P:$property]]";
		if( $num > 1 ) {
			$summary .= " ($num values)";
		} elseif( 1 === $num ) {
			$summary .= " " . $new->getClaimsInProperty( $property )[0]->getMainSnak()->getDataValue();
		}
	}

	// skip?
	if( ! $summary ) {
		echo "nothing to do\n";
		continue;
	}

	$summary = '[[wd:Requests for permissions/Bot/Valerio Bozzolan bot 4|importing italian parks]]' . $summary;

	// to inspect the whole data
	if( isset( $options['verbose'] ) ) {
		echo $new->getJSON( JSON_PRETTY_PRINT ) . "\n";
	}

	// question
	echo $summary . "\n";
	echo $wikidata_ID ? "Edit? https://www.wikidata.org/wiki/$wikidata_ID" : "Create?";

	// Save?
	if( read('y') !== 'y' ) {
		continue;
	}

	// save / create
	$args = [
		'action'  => 'wbeditentity',
		'token'   => CSRF,
		'bot'     => 1,
		'data'    => $new->getJSON(),
		'summary' => $summary,
	];
	if( $wikidata_ID ) {
		$args['id']  = $wikidata_ID;
	} else {
		$args['new'] = 'item';
	}
	$result = Wikidata::getInstance()->post( $args );

	// just created? retrieve the ID
	if( ! $wikidata_ID ) {
		$wikidata_ID = $result->entity->id;
		echo "created $wikidata_ID \n";
	}

	// append a line in the log
	stupid_log( $row, $label, $wikidata_ID );
}

fclose( $handle );
