#!/usr/bin/php
<?php
/*****************************
 * WLM CH 2018 importer      *
 *                           *
 * @author Valerio Bozzolan  *
 * @license GNU GPL v3+      *
 *****************************/

define( 'SUMMARY', 'Bot: [[Wikidata:Requests for permissions/Bot/Valerio Bozzolan bot 6|importing monuments from LegaVolley]]' );

$ALWAYS = false;

// load boz-mw
require 'includes/boz-mw/autoload.php';

// load configuration
require '../config.php';

use cli\Log;

$CANTON_NAME = json_decode( file_get_contents( 'data/cantons.json' ) );
$QUERY = file_get_contents( 'query.sparql' );

$commons  = \wm\Commons ::getInstance()->login();
$wikidata = \wm\Wikidata::getInstance()->login();

// existing monuments
$results = json_decode( ( new \network\HTTPRequest( 'https://query.wikidata.org/sparql' ) )
	->fetch( [
		'format' => 'json',
		'query'  => $QUERY
	] ) )
	->results
	->bindings;

foreach( $results as $result ) {

	$item         = $result->item       ->value;
	$label_en     = $result->labelEn    ->value;
	$label_native = $result->labelNative->value;
	$label        = $result->itemLabel  ->value;
	$status       = $result->status     ->value;
	$canton       = $result->canton     ->value;
	$cantonLabel  = $result->cantonLabel->value;

	$item   = str_replace( 'http://www.wikidata.org/entity/', '', $item   );
	$status = str_replace( 'http://www.wikidata.org/entity/', '', $status );
	$canton = str_replace( 'http://www.wikidata.org/entity/', '', $canton );

	$is_national = $status === 'Q8274529';
	$is_regional = $status === 'Q12126757';

	if( ! ( $is_national xor $is_regional ) ) {
		Log::warn( "wtf regional or national; skip" );
		continue;
	}

	$canton = $CANTON_NAME[ $canton ];
	if( ! $canton ) {
		Log::warn( "missing canton $canton $cantonLabel" );
		continue;
	}
	$commons_canton_category = "Category:$commons_heritage_category";

	$best_label = coalesce( $label_en, $label_native, $label );
	if( ! $best_label ) {
		Log::warn( "skip $item no label" );
		continue;
	}
	$category_name = "Category:$best_label";

	$commons_heritage_category = sprintf(
		"Cultural properties of %s significance in the canton of $canton",
		$is_national ? "national" : "regional",
		$canton
	);

	$cat_content = [];
	$cat_content[] = "{{Empty category|Populated by [[Commons:Wiki Loves Monuments 2018 in Switzerland|Wiki Loves Monuments 2018 in Switzerland]].}}";
	$cat_content[] = "{{Wikidata Infobox|qid=$item}}";
	$cat_content[] = "[[Category:$commons_canton_category]]";

	$cat_content = implode( "\n", $cat_content );

	// confirm save in Commons
	if( $ALWAYS || 'y' === cli\Input::yesNoQuestion( "Save [[$category_name]]?" ) ) {

		// save in Commons without overwriting
		$status = $commons->post( [
			'title'      => $commons_canton_category,
			'text'       => $cat_content,
			'summary'    => "[[Commons:Wiki Loves Monuments 2018 in Switzerland]]: creating category for monument [[d:$item]]",
			'createonly' => true,
			'token'      => $commons->getToken( mw\Tokens::CSRF ),
		] );

		if( isset( $status->error ) ) {
			if( $status->error->code === 'articleexists' ) {
				Log::warn( "The page $cat_content already exists in Commons. skip" );
				continue;
			}
		}
	}

	// create item with data to be added
	$data_new = new wb\DataModel();

	// add Commons category to item
	$data_new->addClaim( wb\StatementCommonsMedia( 'P373', $category_name ) );

	// save the Commons category in Wikidata
	$wikidata->post( [
		'action'  => 'wbeditentity',
		'id'      => $item,
		'data'    => $data_new->getJSON(),
		'summary' => "[[c:Commons:Wiki Loves Monuments 2018 in Switzerland]]: " . $data_new->getEditSummary(),
		'token'   => $wikidata->getToken( mw\Tokens::CSRF ),
		'bot'     => 1,
	] ) );
}

fclose( $out );

function coalesce() {
	$args = func_get_args();
	foreach( $args as $arg ) {
		if( ! empty( $arg ) ) {
			return $arg;
		}
	}
	return NULL;
}
