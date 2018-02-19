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
 * A lazy rappresentation of a plate
 */
class Plate {

	/**
	 * Constructor
	 *
	 * @param $code string
	 * @param $item string
	 * @param $label string
	 */
	public function __construct( $code, $item, $label ) {
		$this->code  = $code;
		$this->item  = $item;
		$this->label = $label;
	}

	/**
	 * Constructor from CSV data
	 *
	 * @param $data array e.g. [ 'TO', 'http://www.wikidata.org/entity/Q16287', 'provincia di Torino' ]
	 */
	public static function createFromData( $data ) {
		list( $code, $wikidata_url, $label ) = $data;
		$item = str_replace( 'http://www.wikidata.org/entity/', '', $wikidata_url );
		return new self( $code, $item, $label );
	}
}
