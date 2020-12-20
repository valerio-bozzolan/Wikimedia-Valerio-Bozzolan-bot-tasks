#!/usr/bin/php
<?php
# Copyright (C) 2020 Valerio Bozzolan
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

// load common files
require 'bootstrap.php';

// process this number of files at time
$BATCH_SIZE = 500;

// pathname to the download directory
$DOWNLOAD_DIR = "./downloads";

// phpQuery1
// https://github.com/phpquery/phpquery
$PHPQUERY = __DIR__ . '/phpquery/phpQuery/phpQuery.php';
require $PHPQUERY;

// no command line no party
if( !$argv ) {
	echo "Not in command line?\n";
	exit( 1 );
}

// no first argument no party
$COLLECTION_ID   = $argv[1] ?? null;
$COLLECTION_NICK = $argv[2] ?? null;
$TEMPLATE_PATH   = $argv[3] ?? null;

if( !$COLLECTION_ID ) {
	echo "Error: missing COLLECTION_ID\n";
}

if( !$COLLECTION_NICK ) {
	echo "Error: missing COLLECTION_NICKNAME\n";
}

if( !$COLLECTION_PATH ) {
	echo "Warning: missing COLLECTION_PATH\n";
}

if( !$COLLECTION_ID || !$COLLECTION_NICK ) {
	echo "Usage:\n";
	echo "  {$argv[0]} COLLECTION_ID COLLECTION_NICKNAME COLLECTION_PATH\n";
	exit( 2 );
}

// number of search results
$search_results = null;

// HTTP query for the search home
$base_url = 'https://iconoteca.arc.usi.ch/it/ricerca';
$home_url = "$base_url?" . http_build_query( [
	'isPostBack' => '1',
	'id_fondo'   => $COLLECTION_ID,
] );

// read the file content
$content = file_get_contents( $home_url );

// parse the document
$document = phpQuery::newDocument( $content );

// enter in page content
foreach( pq( $document )->find( '.paginazione' ) as $pagination ) {
	$pagination_text = $pagination->textContent;
	$pagination_text = str_replace( 'Risultati:', '', $pagination_text );
	$search_results = (int) trim( $pagination_text );
	break;
}

$commands = [];

for( $position = 0; $position < $search_results; $position = $position + $BATCH_SIZE ) {

	// page with some results
	$page_url = "$base_url?" . http_build_query( [
		'isPostBack' => '1',
		'id_fondo'   => $COLLECTION_ID,
		'start'      => $position,
		'step'       => $BATCH_SIZE,
	] );

	// page HTML content
	echo "Downloading $page_url ...\n";
	$page_content = file_get_contents( $page_url );

	// filename that will be writte in download
	$page_file_name = "collection-$COLLECTION_NICK-$COLLECTION_ID-from-$position-to-$BATCH_SIZE.html";

	// full path of the above
	$page_file_path = "$DOWNLOAD_DIR/$page_file_name";

	file_put_contents( $page_file_path, $page_content );

	// show what we will do
	$command_parts = [
		'./parse-html-and-import.php',
		escapeshellarg( $page_file_path ),
	];

	$command = implode( ' ', $command_parts );


	$commands[] = $command;
}

foreach( $commands as $command ) {

	echo $command . "\n";

}
