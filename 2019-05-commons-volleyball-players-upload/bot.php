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

require 'include/boz-mw/autoload.php';
require '../config.php';

use \web\MediaWikis;
use \cli\Log;

$wiki = MediaWikis::findFromUID( 'commonswiki' );
//$wiki->login();

// fingerprint of the default pic
$default_pic_md5 = md5( file_get_contents( 'data/default-pic.jpg' ) );

$header = true;
$handle = fopen( 'data/2019-volleyball-players.csv', 'r' );
while( ( $data = fgetcsv( $handle ) ) !== false ) {
	if( $header ) {
		$header = false;
		continue;
	}
	$n = count( $data );

	/*
	array(11) {
	  [0]=>
	  string(2) "id"
	  [1]=>
	  string(7) "cognome"
	  [2]=>
	  string(4) "nome"
	  [3]=>
	  string(7) "Altezza"
	  [4]=>
	  string(11) "DataNascita"
	  [5]=>
	  string(14) "NazioneNascita"
	  [6]=>
	  string(12) "LuogoNascita"
	  [7]=>
	  string(19) "NazionalitaSportiva"
	  [8]=>
	  string(13) "UltimaSquadra"
	  [9]=>
	  string(14) "ultimastagione"
	  [10]=>
	  string(7) "urlFoto"
	}
	*/

	list( $id, $surname, $name, $h, $birth, $birth_nation, $birth_where, $nation, $last_team, $last_season, $url_photo ) = $data;

	if( empty( $url_photo ) ) {
		Log::warn( "skip $id $surname $name without URL" );
		continue;
	}

	$pic = file_get_contents( $url_photo );
	if( strlen( $pic ) < 300 ) {
		Log::warn( "skip $id $surname $name with no pic $url_photo" );
		continue;
	}

	if( md5( $pic ) === $default_pic_md5 ) {
		Log::warn( "skip $id $surname $name with default pic $url_photo" );
		continue;
	}

	if( empty( $name ) || empty( $surname ) ) {
		Log::warn( "skip $id without name or surname" );
		continue;
	}

	$filename = "$name $surname (Legavolley 2019).jpg";

$date = date('Y-m-d H:i:s');
$text = <<<EOF
== {{int:filedesc}} ==
{{Information
|Description=profile image of $name $surname, volleyball player ($nation)
|Source=[http://www.legavolley.it/ricerca/?TipoOgg=ATL Lega Pallavolo Serie A]
|Date=$date
|Author=Lega Pallavolo Serie A
|Permission=
|other_versions=
}}
== {{int:license-header}} ==
{{Lega Pallavolo|2019}}

[[Category:2019 files from Legavolley stream]]
EOF;

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

	$comment = "Bot: test [[Commons:Bots/Requests/Valerio Bozzolan bot (5)|upload 2019 volleyball players from Legavolley]]";
	$token = $commons->getToken( \mw\Tokens::CSRF );
	$commons->post( [
		'filename' => $filename,
		'comment'  => $comment,
		'text'     => $text,
		'url'      > $url_photo,
		'token'    => $token,
	] );
	exit;
}
