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
require 'autoload.php';

$commons = wiki( 'commonswiki' );

$data_raw = file_get_contents( 'data/players.serialized' );
$players = unserialize( $data_raw );

$BLACKLIST = [
	'(surname)',
	'olleyball players',
	'players from ',
	'portspeople',
	'Players of ',
	'Sports in ',
	'Men of Italy',
	'(given name)',
	'Portraits of men',
];

foreach( $players as $player ) {

	$file = $commons->createTitleParsing( $player->file );
	$filename = $file->getTitle()->get();
	$player->fileURL = $file->getURL();

	if( $player->cats ) {

		// drop nonsense categories
		foreach( $player->cats as $i => $cat_raw ) {

			// skip unuseful titles
			foreach( $BLACKLIST as $blacklist ) {
				if( strpos( $cat_raw, $blacklist ) !== false ) {
					unset( $player->cats[ $i ] );
					$skip = true;
					break;
				}
			}

		}

		if( $player->cats ) {

			// pick the first category
			$cat_raw = array_shift( $player->cats );

			$cat = $commons->createTitleParsing( $cat_raw );

			$player->cat = $cat_raw;
			$player->catURL = $cat->getURL();

			// other categories?
			if( $player->cats ) {
				$player->otherCats = implode( ', ', $player->cats );
			}
		}
	}
}

$fp = fopen( 'data/players.csv', 'w' );

fputcsv( $fp, [
	"Filename",
	"Filename URL",
	"Personal category",
	"Personal category URL",
	"Other categories",
] );

foreach( $players as $player ) {

	fputcsv( $fp, [
		$player->file,
		$player->fileURL   ?? '',
		$player->cat       ?? '',
		$player->catURL    ?? '',
		$player->otherCats ?? '',
	] );

}
fclose( $fp );

