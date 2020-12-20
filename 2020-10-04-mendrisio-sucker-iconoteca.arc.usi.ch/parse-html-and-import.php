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

// metadata by selector
$METADATA_BY_SELECTOR = [
	'.metadati'          => $METADATA_BODY,
	'.metadati_completi' => $METADATA_FOOTER,
];

// reports base directory
$REPORTS_BASE_PATH = "./reports";

// phpQuery1
// https://github.com/phpquery/phpquery
$PHPQUERY = __DIR__ . '/phpquery/phpQuery/phpQuery.php';

// no phpQuery no party
if( !file_exists( $PHPQUERY ) ) {
	echo "Please read the README\n";
	exit( 5 );
}

// load phpQuery
require $PHPQUERY;

// no command line no party
if( !$argv ) {
	echo "Not in command line?\n";
	exit( 1 );
}

// no first argument no party
$page = $argv[1] ?? null;
if( !$page ) {
	echo "Usage:\n  {$argv[0]} FILE.html\n";
	exit( 2 );
}

// no file no party
if( !file_exists( $page ) ) {
	echo "Unexisting file $page\n";
	exit( 3 );
}

// read the file content
$content = file_get_contents( $page );

// no content no party
if( !$content ) {
	echo "No content no party\n";
	exit( 4 );
}

// parse the document
$document = phpQuery::newDocument( $content );

// enter in page content
$content = pq( $document )->find( '.page-content' );

// traverse the DOM tree
foreach( $content->find( '.row' ) as $row ) {

	foreach( pq( $row )->find( '.col-md-4' ) as $col ) {

		// image element
		$img = pq( $col )->find( 'img' );

		// image relative path in the URL
		$img_path = $img->attr( 'src' );

		// no URL no party (wrong elements)
		if( !$img_path ) {
			continue;
		}

		// absolute image URL
		$img_url = BASE_URL . '/' . $img_path;

		// image identifier
		$img_id = str_replace( INVENTORY_PREFIX_TO_STRIP, '', $img_url );

		// it's an integer
		$img_id = (int) $img_id;

		// image permalink
		$img_page_url = sprintf(
			INVENTORY_URL_FORMAT,
			$img_id
		);

		// image permalink HTMl content
		message( "Sucking $img_page_url..." );

		$img_page_content = file_get_contents( $img_page_url );
		if( !$img_page_content ) {
			message( "Skip failed download $img_page_url" );
			continue;
		}

		// parse image permalink page
		$img_page = pq( phpQuery::newDocument( $img_page_content ) );

		// image data read
		$img_metadata_values = [];

		// loop all the possible metadatas finding them from the right selector
		foreach( $METADATA_BY_SELECTOR as $metadata_selector => $possible_metadatas ) {

			// parse image body metadatas section
			foreach( $img_page->find( $metadata_selector ) as $img_metadata ) {

				// traverse all the paragraphs containing metadatas and try to parse
				foreach( pq( $img_metadata )->find( 'p' ) as $img_metadata_p_raw ) {

					// paragraph element
					$img_metadata_p = pq( $img_metadata_p_raw );

					// label
					// it contains 'Titolo originale:'
					$img_metadata_p_label = $img_metadata_p->find( 'label' );

					// label text
					// e.g. 'Titolo originale:'
					$img_metadata_p_label_txt = $img_metadata_p_label->text();

					// metadata matching this label
					$img_metadata_value = find_matching_metadatavalue_from_label( $possible_metadatas, $img_metadata_p_label_txt, $img_metadata_p );

					// gotcha?
					if( $img_metadata_value ) {
						$img_metadata_values[] = $img_metadata_value;
					} else {
						message( "Unknown metadata '$img_metadata_p_label_txt' not found in $metadata_selector" );
					}
				}
			}
		}

		// main image
		$img_main = $img_page->find( '.zoomviewer img' );

		// hight quality image URL
		$img_hq_url = sprintf(
			HIGH_QUALITY_IMAGE_URL,
			$img_id
		);

		// low quality image URL
		$img_lq_url = sprintf(
			LOW_QUALITY_IMAGE_URL,
			$img_id
		);

		// image pathname
		$img_path = sprintf(
			IMAGE_DOWNLOAD_NAME,
			$img_id
		);

		// build a metadata file
		$img_path_json = "$img_path.json";
		$img_data_json = [];
		foreach( $img_metadata_values as $img_metadata_value ) {
			message( "  $key: $value" );
			list( $key, $value ) = $img_metadata_value->getData();
			$img_data_json[ $key ] = $value;
		}

		// no json write no party
		if( !file_put_contents( $img_path_json, json_encode( $img_data_json, JSON_PRETTY_PRINT ) ) ) {
			message( "cannot write $img_path_json" );
		}

		foreach( [ $img_hq_url, $img_lq_url ] as $img_url ) {

			// eventually download the image and save
			if( !file_exists( $img_path ) ) {

				message( "Fetching $img_url in $img_path..." );

				// download the image
				$img_bin = file_get_contents( $img_url );

				// sometime this is not an image but is a shitty text
				// «ERRORE: il livello d'accesso impostato al file non consente di scaricare questa immagine» ASD
				if( strlen( $img_bin ) > 1000 ) {

					// save the HQ image or write an error
					if( !file_put_contents( $img_path, $img_bin ) ) {
						message( "cannot write $img_path" );
					}
				} else {

					// WHAAT THE FUUUUUCK IS THIS SHIT
					message( "invalid image" );
				}
			}

		}

		// all right
		message( "completed $img_id" );
	}

}
