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
$QUERY = file_get_contents( 'data/query.sparql' );

$commons  = \wm\Commons ::getInstance()->login();
$wikidata = \wm\Wikidata::getInstance()->login();

// existing monuments
$results = \wm\Wikidata::querySPARQL( $QUERY );

foreach( $results as $result ) {

	// normalize stuff
	$entity_id    = $result->item       ->value;
	$label_en     = $result->labelEn    ->value;
	$label_native = $result->labelNative->value;
	$label        = $result->itemLabel  ->value;
	$status       = $result->status     ->value;
	$canton       = $result->canton     ->value;
	$cantonLabel  = $result->cantonLabel->value;
	$entity_id = str_replace( 'http://www.wikidata.org/entity/', '', $entity_id );
	$status    = str_replace( 'http://www.wikidata.org/entity/', '', $status );
	$canton    = str_replace( 'http://www.wikidata.org/entity/', '', $canton );


	// create Commons canton name
	if( ! isset( $CANTON_NAME->{ $canton } ) ) {
		Log::warn( "missing canton $canton $cantonLabel" );
		continue;
	}
	$canton = $CANTON_NAME->{ $canton };


	// create Commons category title
	$category_name = ucfirst( coalesce( $label_en, $label_native, $label ) );
	$category_name_prefixed = "Category:$category_name";
	if( ! $category_name ) {
		Log::warn( "skip $entity_id no best label" );
		continue;
	}
	Log::info( "Commons category: [[$category_name_prefixed]]" );

	// create Commons heritage category
	$is_national = $status === 'Q8274529';
	$is_regional = $status === 'Q12126757';
	if( ! ( $is_national xor $is_regional ) ) {
		Log::warn( "wtf regional or national; skip" );
		continue;
	}
	$commons_heritage_category = sprintf(
		"Cultural properties of %s significance in the canton of $canton",
		$is_national ? "national" : "regional",
		$canton
	);
	$commons_canton_category = "Category:$commons_heritage_category";

	// fetch existing entity
	$entity = $wikidata->fetchSingleEntity( $entity_id, [ 'props'  => 'claims' ] );

	// entity has images?
	if( $entity->hasClaimsInProperty( 'P373' ) ) {
		Log::warn( "already has Commons category; skip" );
		continue;
	}

	// manual check asd
	if( 'n' === cli\Input::yesNoQuestion( "Do you like [[$category_name_prefixed]]?" ) ) {
		continue;
	}

	$category_has_images = false;

	// has images?
	if( $entity->hasClaimsInProperty( 'P18' ) ) {
		foreach( $entity->getClaimsInProperty( 'P18' ) as $claim ) {
			$image = $claim->getMainsnak()->getDataValue()->getValue();

			// categorize that image with the unexisting category
			Log::info( "categorize [[File:$image]] under [[$category_name_prefixed]]" );
			$commons->edit( [
				'title'      => "File:$image",
				'appendtext' => "\n[[$category_name_prefixed]]",
				'summary'    =>  "[[Commons:Wiki Loves Monuments 2018 in Switzerland]]: +[[$category_name_prefixed]]",
				'bot'        => 1,
			] );

			$category_has_images = true;
		}
	}
	Log::info( $category_has_images ? "has images" : "no images" );

	// Commons category content
	$cat_content = "";
	if( ! $category_has_images ) {
		$cat_content .= "{{Empty category|Populated by [[Commons:Wiki Loves Monuments 2018 in Switzerland|Wiki Loves Monuments 2018 in Switzerland]].}}\n";
	}
	$cat_content .= "{{Wikidata Infobox|qid=$entity_id}}\n";
	$cat_content .= "[[Category:$commons_heritage_category]]";

	// confirm save in Commons
	echo "---\n$cat_content\n---\n";
	Log::info( "save Commons [[$category_name_prefixed]]" );
	try {
		// save in Commons without overwriting
		$status = $commons->edit( [
			'title'      => $category_name_prefixed,
			'text'       => $cat_content,
			'summary'    => "[[Commons:Wiki Loves Monuments 2018 in Switzerland]]: creating category for monument [[d:$entity_id|$category_name]]",
			'createonly' => true,
			'bot'        => 1,
		] );
	} catch( mw\API\ArticleExistsException $e ) {
		Log::warn( "The page [[$category_name_prefixed]] already exists in Commons. skip" );
		if( 'y' === cli\Input::yesNoQuestion( "Skip [[$category_name_prefixed]]?" ) ) {
			continue;
		}
	}

	// save the Commons category in Wikidata
	$entity_data = ( new wb\DataModel() )
		->addClaim( new wb\StatementCommonsCategory( 'P373', $category_name ) );

	// save the Commons category in Wikidata
	Log::info( "saving Commons category in Wikidata" );
	$wikidata->editEntity( [
		'id'      => $entity_id,
		'data'    => $entity_data->getJSON(),
		'summary' => "[[c:Commons:Wiki Loves Monuments 2018 in Switzerland]]: " . $entity_data->getEditSummary(),
		'bot'     => 1,
	] );
}

function coalesce() {
	$args = func_get_args();
	foreach( $args as $arg ) {
		if( ! empty( $arg ) ) {
			return $arg;
		}
	}
	return NULL;
}
