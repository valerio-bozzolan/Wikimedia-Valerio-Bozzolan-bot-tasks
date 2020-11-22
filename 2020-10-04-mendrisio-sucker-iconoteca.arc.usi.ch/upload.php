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

// autoload framework
require __DIR__ . '/../includes/boz-mw/autoload.php';

// require some values
require 'bootstrap.php';

// load configuration file or create one
cli\ConfigWizard::requireOrCreate( __DIR__ . '/../config.php' );

// load Wikimedia Commons
$commons = \wm\Commons::instance();

// login in Commons
$commons->login();

// input directory
$dir      = $argv[1] ?? null;
$template = $argv[2] ?? null;

// no dir no party
if( !$dir || !$template ) {
	printf( "Usage:\n %s DIRECTORY TEMPLATE\n", $argv[0] );
	exit( 1 );
}

// scan the directory
foreach( glob( "$dir/*.jpg" ) as $file ) {

	// no data no party
	$file_data = @file_get_contents( "$file.json" );
	$file_data = json_decode( $file_data );
	if( !$file_data ) {
		echo "skip $file missing data\n";
		continue;
	}

	// check available metadata
	$img_id      = $file_data->{"ID immagine"};
	$title       = $file_data->{"Titolo opera"};
	$title_orig  = $file_data->{"Titolo originale"} ?? null;
	$collection  = $file_data->{"Collezione"} ?? null;
	$license     = $file_data->Licenza ?? null;
	$type        = $file_data->{"Tipologia di risorsa"} ?? "Fotografia";
	$size        = $file_data->{"Dimensioni"} ?? '';
	$material    = $file_data->{"Materiale del supporto"} ?? '';
	$author_name = $file_data->{"Nome creatore"} ?? '';
	$author      = $file_data->{"Creatore"} ?? $author_name ?? "ignoto";
	$date        = $file_data->{"Data"} ?? $file_data->{"Data creazione"} ?? null;

	// check license
	$license_templates = '';
	if( $license === 'https://creativecommons.org/licenses/by-sa/4.0/deed.it' ) {
		$license_templates .= "{{Cc-by-sa-4.0}}";
	} else {
		throw new Exception( "unknown license $license" );
	}

	// source URL
	$source_url = null;
	if( $img_id ) {
		$source_url = sprintf( INVENTORY_URL_FORMAT, $img_id );
	}

	// build a smart description
	$description = [];
//	if( $type ) {
//		if( $material ) {
//			$description[] = "$type ($material)";
//		} else {
//			$description[] = $type;
//		}
//	}
	$description[] = $title ?? $title_orig;
	$description = implode( '. ', $description );
	$description = "{{it|$description}}";

	// build the page content
	$page_content = template_content( $template, [
		'DESCRIPTION'       => $description,
		'LICENSE_TEMPLATES' => $license_templates,
		'DATE'              => $date,
		'AUTHOR'            => $author,
		'METADATA'          => $file_data,
		'SOURCE'            => $source_url,
	] );

	// print a message
	$filename = "$title.jpg";
	printf(
		"try to upload https://commons.wikimedia.org/wiki/File:%s (%s)\n",
		rawurlencode( $filename ),
		$img_id
	);

	// upload this damn image
	try {

		$response = $commons->upload( [
			'comment'  => "Bot: import related to [[w:it:Wikipedia:Raduni/Biblioteca dell'Accademia di Mendrisio 4 ottobre 2020]]",
			'text'     => $page_content,
			'filename' => "$title.jpg",
			\network\ContentDisposition::createFromNameURLType( 'file', $file, 'image/jpg' ),
		] );

		if( $response->upload->result === 'Success' ) {
			echo "Done.";
		} else {
			// what the fuuck?
			print_r( $response );
		}

	} catch( Exception $e ) {
		printf( "%s: %s", get_class( $e ), $e->getMessage() );
		file_put_contents( 'log.out.err', $e->getMessage(), FILE_APPEND );
	}

	// put in the log this shit
	file_put_contents( 'log.out', "$img_id;$filename\n", FILE_APPEND );

	// wait to do not use the bot flag
	sleep( 60 );
}
