#!/usr/bin/php
<?php

define( 'BASE_URL', 'https://iconoteca.arc.usi.ch' );

// inventory prefix to be stripped out to read the image ID (note the double slash! asd)
define( 'INVENTORY_PREFIX_TO_STRIP', BASE_URL . '//thumb.php?inventario=' );

// URL to the single photo from the image ID (DOI)
define( 'INVENTORY_URL_FORMAT',      BASE_URL . '/it/inventario/%d' );

// URL of the high quality image
define( 'HIGH_QUALITY_IMAGE_URL',    BASE_URL . '/image-viewer.php?inventario=%d' );

// image download name (with image ID)
define( 'IMAGE_DOWNLOAD_NAME', 'images/%d.jpg' );

// array of metadatas displayed in the body in the '.metadati' selector
// basically they are the labels displayed in the body on every image like this one:
// https://iconoteca.arc.usi.ch/it/inventario/51630
$METADATA_BODY = [
	new Metadata( 'Luogo rappresentato' ),
	new Metadata( 'Tipologia di risorsa' ),
	new Metadata( 'Creatore' ),
	new Metadata( 'Data' ),
	new Metadata( 'DOI', function ( $p ) {

		// the DOI is a link, so just extract the URL

		// text displayed after the label (manually stripping the label)
		return $p->find( 'a' )->attr( 'href' );
	} ),
	new Metadata( 'ID immagine' ),
	new Metadata( 'Licenza', function( $p ) {

		// the License is a link, so just extract the URL

		// text displayed after the label (manually stripping the label)
		return $p->find( 'a' )->attr( 'href' );
	} ),
];

// array of metadatas displayed in the footer in the '.metadati_completi' selector
// basically they are the labels displayed in the footer on every image like this one:
// https://iconoteca.arc.usi.ch/it/inventario/51630
$METADATA_FOOTER = [
	new Metadata( 'Titolo opera' ),
	new Metadata( 'Titolo originale' ),
	new Metadata( 'Iscrizione' ),
	new Metadata( 'Collezione' ),
	new Metadata( 'Data creazione' ),
	new Metadata( 'Luogo creazione' ),
	new Metadata( 'Nome creatore' ),
	new Metadata( 'Descrittori Sbt' ),
	new Metadata( 'Descrittori Getty AAT' ),
	new Metadata( 'Luogo rappresentato', function( $p ) {

		// take just the text inside the link
		return $p->find( 'a' )->text();
	} ),
	new Metadata( 'Classificazione' ),
	new Metadata( 'Tipo materiale' ),
	new Metadata( 'Designazione specifica del materiale' ),
	new Metadata( 'Supporto originale' ),
	new Metadata( 'Materiale del supporto' ),
	new Metadata( 'Nome oggetto culturale' ),
	new Metadata( 'Colore' ),
	new Metadata( 'Polarità' ),
	new Metadata( 'Tipo supporto' ),
	new Metadata( 'Processo e tecnica' ),
	new Metadata( 'Montaggio' ),
	new Metadata( 'Orientamento e forma' ),
	new Metadata( 'Dimensioni' ),
];

// metadata by selector
$METADATA_BY_SELECTOR = [
	'.metadati'          => $METADATA_BODY,
	'.metadati_completi' => $METADATA_FOOTER,
];

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

		// low quality image URL
		$img_hq_url = sprintf(
			HIGH_QUALITY_IMAGE_URL,
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

		// eventually download the image and save
		if( !file_exists( $img_path ) ) {

			message( "Fetching $img_hq_url in $img_path..." );

			// download the image
			$img_hq_bin = file_get_contents( $img_hq_url );

			// save the HQ image or write an error
			if( !file_put_contents( $img_path, $img_hq_bin ) ) {
				message( "cannot write $img_path" );
			}
		}

		// all right
		message( "completed $img_id" );
	}

}

/**
 * Metadata
 *
 * Basically the Titolo opera, Titolo originale etc. from:
 *   https://iconoteca.arc.usi.ch/it/inventario/51630
 */
class Metadata {

	public $label;

	public $valueAdapter;

	/**
	 * Constructor
	 *
	 * @param string   $label Metadata label e.g. 'Titolo opera'
	 * @param function $value_adapter Optional callable
	 */
	public function __construct( $label, $value_adapter = null ) {
		$this->label = $label;
		$this->valueAdapter = $value_adapter;
	}

	/**
	 * Get the text of the label
	 *
	 * Basically from 'foo' its 'foo:'
	 *
	 * @return string
	 */
	public function getLabel() {
		return $this->label . ':';
	}

	/**
	 * Check if a label matches the one of this metadata
	 *
	 * @return bool
	 */
	public function matchesLabel( $label ) {
		return $this->getLabel() === $label;
	}

	/**
	 * Create a MetadataValue object from a value
	 *
	 * Note that the value will be adapted.
	 *
	 * @param mixed $value
	 * @return Metadatavalue
	 */
	public function createValue( $value ) {

		// eventually apply the custom value adapter
		if( $this->valueAdapter ) {
			$user_adapter = $this->valueAdapter;
			$value = $user_adapter( $value );
		} else {
			// otherwise apply the default value adapter
			$value = self::defaultValueAdapter( $value );
		}

		return new MetadataValue( $this, $value );
	}

	/**
	 * Default value adapter
	 *
	 * Note: as default the value is the paragraph selector. So we strip the label and get the clean data.
	 *
	 * @param string $img_metadata_p
	 * @return string
	 */
	private static function defaultValueAdapter( $img_metadata_p ) {

		// text displayed after the label (manually stripping the label)
		$img_metadata_p_text = $img_metadata_p->html();

		// label
		// it contains 'Titolo originale:'
		$img_metadata_p_label = $img_metadata_p->find( 'label' );

		// label text
		// e.g. 'Titolo originale:'
		$img_metadata_p_label_html = $img_metadata_p_label->html();

		// complete text of the paragraph stripping its label
		$img_metadata_p_text = trim( str_replace( "<label>$img_metadata_p_label_html</label>", '', $img_metadata_p_text ) );

		return $img_metadata_p_text;

	}
}

/**
 * A Metadata related to a value
 */
class MetadataValue {

	public $metadata;

	public $value;

	public function __construct( Metadata $metadata, $value ) {
		$this->metadata = $metadata;
		$this->value    = $value;
	}

	public function getData() {
		return [
			$this->metadata->label,
			$this->value,
		];
	}
}

/**
 * Find a matching metadata from a label and return a MetadataValue
 *
 * @param array  $metadatas Array of known metadatas
 * @param string $label Original label like 'Titolo originale:'
 * @param string $value Value related to the matching metadata
 * @return MetadataValue|false Matching metadata or false if not found
 */
function find_matching_metadatavalue_from_label( $metadatas, $label, $value ) {

	// find the matching metadata
	foreach( $metadatas as $metadata ) {
		if( $metadata->matchesLabel( $label ) ) {
			return $metadata->createValue( $value );
		}
	}

	// no metadata no party
	return false;
}

function message( $message ) {
	printf(
		"[%s] %s\n",
		date( 'Y-m-d H:i:s' ),
		$message
	);
}
