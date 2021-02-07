#!/usr/bin/php
<?php
# Copyright (C) 2021 Valerio Bozzolan
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

// autoload framework
require __DIR__ . '/../includes/boz-mw/autoload.php';

$fp = fopen( 'players.csv', 'w' );

// Wikimedia Commons
$commons = \wm\Commons::instance();

$rows_raw = file_get_contents( 'out.txt' );

$rows_raw = trim( $rows_raw );

$rows = explode( "\n", $rows_raw );
$n = count( $rows );

$players = [];

while( $rows ) {

	$categories = [];
	for( $i = 0; $i < 50 && $rows; $i++ ) {
		$title = array_pop( $rows );

		$name = extract_volleyball_name_from_file( $title );

		$category = "Category:$name";

		$player = new VolleyballPlayer();
		$player->file = $title;
		$player->cat  = $category;
		$players[] = $player;

		$categories[] = $category;
	}

	// bulk-search the categories
	$queries = $commons->createQuery( [
		'action' => 'query',
		'titles' => $categories,
	] );

	foreach( $queries as $query ) {

		// match the results with my objects
		$matcher = new \mw\API\PageMatcher( $query, $players, 'query' );
		$matcher->matchByCustomJoin(
			function( $page, $player ) {
				$player->exists = $page->ns > 0;
			},
			function( $player ) {
				return $player->cat;
			},
			function ( $page ) {
				return $page->title;
			}
		);

	}
}

foreach( $players as $player ) {

	fputcsv( $fp, [
		$player->file,
		$player->cat,
		$player->exists,
	] );

}

fclose( $fp );

function extract_volleyball_name_from_file( $s ) {

	$n = preg_match( '/^File:(.+) \(/', $s, $matches );

	if( $n === 1 ) {
		return $matches[ 1 ];
	}

	throw new Exception( "cannot parse volleyball player filename $s" );
}

class VolleyballPlayer {

	public $file;
	public $cat;

}
