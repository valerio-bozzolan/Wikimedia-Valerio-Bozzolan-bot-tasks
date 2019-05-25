#!/usr/bin/php
<?php
# Copyright (C) 2019 Valerio Bozzolan
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
require 'include/boz-mw/autoload.php';

// load credentials
require '../config.php';

use \web\MediaWikis;
use \cli\Log;
use \network\ContentDisposition;

// login in MediaWiki Commons
$wiki = MediaWikis::findFromUID( 'commonswiki' );
$wiki->login();

// fingerprint of the default pic
$default_pic_md5 = md5( file_get_contents( 'data/default-pic.jpg' ) );

$header = true;
$handle = fopen( 'data/2019-volleyball-players.csv', 'r' );
while( ( $data = fgetcsv( $handle ) ) !== false ) {

	// skip header
	if( $header ) {
		$header = false;
		continue;
	}

	// populate arguments
	list(
		$id,
		$surname,
		$name,
		$h,
		$birth,
		$birth_nation,
		$birth_where,
		$nation,
		$last_team,
		$last_season,
		$url_photo
	) = $data;

	// avoid nonsense files
	$pic = file_get_contents( $url_photo );
	if( strlen( $pic ) < 300 ) {
		Log::warn( "skip $id $surname $name with no pic $url_photo" );
		continue;
	}

	// avoid default pic
	if( md5( $pic ) === $default_pic_md5 ) {
		Log::warn( "skip $id $surname $name with default pic $url_photo" );
		continue;
	}

	if( empty( $name ) || empty( $surname ) ) {
		Log::warn( "skip $id without name or surname" );
		continue;
	}

	// check if the page already exists
	$filename = "$name $surname (Legavolley 2019).jpg";
	$response = $wiki->fetch( [
		'action' => 'query',
		'prop'   => 'info',
		'titles' => "File:$filename",
	] );
	$exists = false;
	foreach( $response->query as $pages ) {
		foreach( $pages as $page ) {
			$exists = empty( $page->missing );
		}
	}
	if( !$exists )  {
		Log::warn( "skip File:$filename already existing" );
		continue;
	}

	$date = date('Y-m-d H:i:s');
	$text = require( 'data/content.wiki.php' );
	$comment = "Bot: test [[Commons:Bots/Requests/Valerio Bozzolan bot (5)|upload 2019 volleyball players from Legavolley]]";

	try {
		// send a POST with multipart
		$response = $wiki->upload( [
			'comment'  => $comment,
			'text'     => $text,
			'filename' => $filename,
			ContentDisposition::createFromNameURLType( 'file', $url_photo, 'image/jpg' ),
		] );

	} catch( \mw\API\Exception $e ) {
		Log::error( $e->getMessage() );
		continue;
	}

	// look at the response
	$msg = $response->upload->result;
	Log::info( "response: $msg" );
	if( isset( $response->upload->warnings ) ) {
		print_r( $response->upload->warnings );
	}

	file_put_contents( 'done.log', "$id;$filename;$msg\n", FILE_APPEND );
}
