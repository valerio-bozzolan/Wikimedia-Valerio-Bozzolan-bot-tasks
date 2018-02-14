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

/**
 * @param $plate plate codes e.g. 'TO/MI'
 * @return string Wikidata entity Q-IDs e.g. [ 'Q495', 'Q490' ]
 */
function plate_2_wikidataIDs( $plates ) {
	$plates = explode( '/', $plates );
	array_walk( $plates, function ( & $value ) {
		$value = plate_2_wikidataID( $value );
	} );
	return $plates;
}

/**
 * @param $plate plate code e.g. 'TO'
 * @return array Wikidata entity Q-ID e.g. 'Q495'
 */
function plate_2_wikidataID( $plate ) {

	// read the plate codes once
	static $plates;
	if( ! $plates ) {
		$data = file_get_contents( 'data/italian-license-plate-codes.csv' );
		$rows = explode( "\n", $data );
		array_shift( $rows ); // skip head
		$plates = [];
		foreach( $rows as $row ) {
			$data = explode( ',', $row, 3 );
			if( 3 === count( $data ) ) {
				list( $code, $wikidata_url, $label ) = $data;
				$plates[ $code ] = str_replace( 'http://www.wikidata.org/entity/', '', $wikidata_url );
			}
		}
	}

	if( ! isset( $plates[ $plate ] ) ) {
		throw new Exception('missing plate');
	}

	return $plates[ $plate ];
}

/**
 * @param $coordinates string e.g. '46.15876°N 9.893705°E'
 * @return array north/est pair
 */
function filter_coordinates( $coordinates ) {
	$coordinates = str_replace( [ 'N', 'E', '°'], '' , $coordinates ); // damn syntax
	$coordinates = str_replace(  ',',             '.', $coordinates ); // damn commma
	$coordinates = preg_replace( '/ +/',          ' ', $coordinates ); // damn syntax
	$coordinates = explode( ' ', $coordinates, 2 );
	if( 2 !== count( $coordinates ) ) {
		throw new Exception( 'wrong coordinates' );
	}
	return [
		(float) $coordinates[ 0 ],
		(float) $coordinates[ 1 ]
	];
}

/**
 * @param $label string
 */
function filter_label( $label ) {
	$label = trim( $label, "][ \n" );
	$label = explode( '|', $label, 2 );
	if( count( $label ) === 2 ) {
		return $label[1];
	}
	return $label[0];
}

/**
 * Read from the STDIN
 * @param $default string
 * @return string
 */
function read( $default = '' ) {
	$v = trim( fgets( STDIN ) );
	return $v ? $v : $default;
}
