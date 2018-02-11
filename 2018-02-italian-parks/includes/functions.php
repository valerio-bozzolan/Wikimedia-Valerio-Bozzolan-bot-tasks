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

function plate_2_wikidataID( $plate ) {
	static $plates;

	if( ! $plates ) {
		$data = file_get_contents( 'data/italian-license-plate-codes.csv' );
		$rows = explode( "\n", $data );
		array_shift( $rows ); // skip head

		$plates = [];
		foreach( $rows as $row ) {
			$row = explode( ',', $row, 3 );
			$plates[ $row[0] ] = str_replace( 'http://www.wikidata.org/entity/', '', $row[1] );
		}
	}

	if( ! isset( $plates[ $plate ] ) ) {
		throw new Exception('missing plate');
	}

	return $plates[ $plate ];
}
