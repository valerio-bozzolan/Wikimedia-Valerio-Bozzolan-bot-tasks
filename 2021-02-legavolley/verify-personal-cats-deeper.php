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

foreach( $players as $player ) {

	$file = $commons->createTitleParsing( $player->file );
	$filename = $file->getTitle()->get();
	$player->fileURL = $file->getURL();

	$filename_words = explode( ' ', $filename );

	$first_word = $filename_words[ 0 ];

	if( $player->cats ) {

		foreach( $player->cats as $cat_raw ) {

			$cat = $commons->createTitleParsing( $cat_raw );
			$cat_name = $cat->getTitle()->get();

			if( strpos( $cat_name, $first_word ) !== false ) {
				$player->cat = $cat_raw;
				$player->catURL = $cat->getURL();
				break;
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
] );

foreach( $players as $player ) {

	fputcsv( $fp, [
		$player->file,
		$player->fileURL ?? '',
		$player->cat     ?? '',
		$player->catURL  ?? '',
	] );

}
fclose( $fp );

