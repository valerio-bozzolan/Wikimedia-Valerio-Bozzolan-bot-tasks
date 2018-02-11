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

# Boz-mw
require '../includes/boz-mw/autoload.php';

# Credentials
require '../config.php';

# Local functions
require 'includes/functions.php';

use wb\DataModel;

# Data
$handle = fopen('data/italian-parks-data.csv', 'r') or die('asd');

// hectare
$AREA_UNIT = 'Q35852';

$row = 0;
while( ( $data = fgetcsv( $handle, 1000, ',' ) ) !== false ) {

	// Skip headers
		continue;
	}

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
		$P625,  // string coordinates
		$P4800, // string ID
		$P809,
		$P627,  // string Q-ID
		$P3425, // string ID
		$label, // string [[something]]
		$P2046, // int
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

	// located in the administrative territorial entity
	if( $P131 ) {
		$P131 = plate_2_wikidataID( $P131 );
		// Something
	}

	$new_data = new DataModel();

	if( $wikidata_ID ) {
		// Edit
	} else {
		// Create
	}
}

fclose($handle);
