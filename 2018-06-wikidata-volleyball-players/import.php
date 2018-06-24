#!/usr/bin/php
<?php
/*****************************
 * Legavolley 2018 importer *
 *                           *
 * @author Valerio Bozzolan  *
 * @license GNU GPL v3+      *
 *****************************/

define( 'SUMMARY', 'test importing volleyball players from LegaVolley' );

// load boz-mw
require 'includes/boz-mw/autoload.php';

// load configuration
require '../config.php';

use cli\Log;

// COUNTRIES
$COUNTRIES = [];
$handle = fopen( 'data/volleyball-nationalities.csv', 'r' ) or die( 'cannot open nationalities' );
$i = 0;
while( ($data = fgetcsv($handle, 1000, ",")) !== false ) {
	if( 0 === $i++ ) {
		continue;
	}
	list( $iso, $entity_id, $citizen_it_m, $citizen_it_f, $city_en ) = $data;
	$COUNTRIES[ $iso ] = [
		'iso'          => $iso,
		'entity-id'    => $entity_id,
		'citizen.it.m' => $citizen_it_m,
		'citizen.it.f' => $citizen_it_f,
		'name.en'      => $city_en,
	];
}
fclose( $handle );

// new volleyball players
$NEW_PLAYERS = [];
$i = 0;
$handle = fopen( 'data/2018-legavolley-volleyball-players.csv', 'r' ) or die( 'cannot open volleyball players' );
while( ($data = fgetcsv($handle, 1000, ",")) !== false ) {
	if( 0 === $i++ ) {
		continue;
	}

	// normalization
	array_walk( $data, function ( & $value ) {
		trim( $value );
	} );

	list( $legavolley_id, $surname, $name, $height, $birthday_raw, $country_raw, , , ) = $data;

	$height   = 'NULL' === $height       ? null : (int) $height;
	$birthday = 'NULL' === $birthday_raw ? null : DateTime::createFromFormat( 'd/m/Y', $birthday_raw );

	if( false === $birthday ) {
		die( "wrong birthday $birthday_raw" );
	}

	$country_data = $COUNTRIES[ $country_raw ];
	$country_data or die( "missing country $country_raw" );

	// code => data
	$NEW_PLAYERS[ $data[ 0 ] ] = [
		'name'          => $name,
		'surname'       => $surname,
		'legavolley-id' => $legavolley_id,
		'height'        => $height,
		'birthday'      => $birthday,
		'country-data'  => $country_data,
		'img'           => sprintf( '%s %s (Legavolley 2018).jpg', $name, $surname ),
	];
}
fclose( $handle );

// existing volleyball players
$datas = json_decode( ( new \network\HTTPRequest( 'https://query.wikidata.org/sparql' ) )
	->fetch( [
		'format' => 'json',
		'query'  =>
			  "SELECT ?item ?legavolley_id "
			. "WHERE "
			. "{ "
			. "  ?item wdt:P4303 ?legavolley_id." // ID LegaVolley
			. "}"
	] ) )
	->results
	->bindings;

// existing players (LegaVolleyID => Wikidata entity ID)
$EXISTING_PLAYERS = [];
foreach( $datas as $data ) {
	$entity = str_replace( 'http://www.wikidata.org/entity/', '', $data->item->value );
	$legavolley_id = strtoupper( $data->legavolley_id->value );
	$EXISTING_PLAYERS[ $legavolley_id ] = $entity;
}

// Wikidata
$wd = \wm\Wikidata::getInstance()->login();
foreach( $NEW_PLAYERS as $NEW_PLAYER ) {

	$name          = $NEW_PLAYER[ 'name'          ];
	$surname       = $NEW_PLAYER[ 'surname'       ];
	$legavolley_id = $NEW_PLAYER[ 'legavolley-id' ];
	$country       = $NEW_PLAYER[ 'country-data'  ];
	$height        = $NEW_PLAYER[ 'height'        ];
	$birthday      = $NEW_PLAYER[ 'birthday'      ];
	$changes       = [];

	$entity_id = false;
	if( isset( $EXISTING_PLAYERS[ $legavolley_id ] ) ) {
		$entity_id = $EXISTING_PLAYERS[ $legavolley_id ];
	}

	// Wikidata statements
	$STATEMENTS = [
		// ID LegaVolley
		new wb\StatementString( 'P4303', $legavolley_id ),
		// Image
//		new wb\StatementCommonsMedia( 'P18', $NEW_PLAYER[ 'img' ] ),
		// Country of citizenship
		new wb\StatementItem( 'P27', $country[ 'entity-id' ] ),
		// Instance of: human
		new wb\StatementItem('P31', 'Q5'),
		// Sex: male
		new wb\StatementItem( 'P21', 'Q6581097' ),
		// Occupation: volleyball player
		new wb\StatementItem( 'P106', 'Q15117302' ),
		// Sport: volleyball
		new wb\StatementItem( 'P641', 'Q1734' ),
	];
	if( $birthday ) {
		$STATEMENTS[] = new wb\StatementTime(
			'P569', // date of birth
			$birthday->format( '+Y-m-d\T00:00:00\Z' ),
			wb\DataValueTime::PRECISION_DAYS
		);
	}
	if( $height ) {
		$STATEMENTS[] = new wb\StatementQuantity(
			'P2048', // height
			$height,
			'Q174728' // centimeter
		);
	}

	// Wikidata labels
	$LABELS = [
		'en' => sprintf( '%s %s', $name, $surname ),
		'it' => sprintf( '%s %s', $name, $surname ),
	];
	$DESCRIPTIONS = [
		'en' => 'volleyball player',
		'it' => sprintf( 'pallavolista %s', $country[ 'citizen.it.m' ] ),
	];

	// item with data to be added
	$data_new = new wb\DataModel();

	// Existing data
	$data_old = null;
	if( $entity_id ) {
		// https://www.wikidata.org/w/api.pkhp?action=help&modules=wbgetentities
		$data_old = $wd->fetch( [
			'action' => 'wbgetentities',
			'ids'    => $entity_id,
			'props'  => 'info|sitelinks|aliases|labels|descriptions|claims|datatype'
		] );
		$data_old = wb\DataModel::createFromObject( $data_old->entities->{ $entity_id } );
	}

	// append labels without overwriting
	foreach( $LABELS as $lang => $text ) {
		if( ! $data_old || ! $data_old->hasLabelsInLanguage( $lang ) ) {
			$data_new->setLabel( new wb\LabelAction( $lang, $text, wb\LabelAction::ADD ) );
			$changes[] = "+[label][$lang]";
		}
	}

	// append descriptions without overwriting
	foreach( $DESCRIPTIONS as $lang => $text ) {
		if( ! $data_old || ! $data_old->hasDescriptionsInLanguage( $lang ) ) {
			$data_new->setDescription( new wb\DescriptionAction( $lang, $text, wb\DescriptionAction::ADD ) );
			$changes[] = " +[description][$lang]";
		}
	}

	// append statements without duplicating
	foreach( $STATEMENTS as $statement ) {
		$property = $statement->getMainsnak()->getProperty();
		if( ! $data_old || ! $data_old->hasClaimsInProperty( $property ) ) {
			$data_new->addClaim( $statement->setReferences( legavolley_references() ) );

			// add involved property in the summary
			$claims = $data_new->getClaimsInProperty( $property );
			$num = count( $claims );
			if( $num > 1 ) {
				$changes[] = "+[[P:$property]] ($num values)";
			} elseif( 1 === $num ) {
				$changes[] = "+[[P:$property]] " . $claims[ 0 ]->getMainSnak()->getDataValue();
			}
		}
	}

	// Wikidata new data
	// https://www.wikidata.org/w/api.php?action=help&modules=wbeditentity
	$wbeditentity = [
		'action'  => 'wbeditentity',
		'summary' => SUMMARY . ' ' . implode( $changes ),
		'token'   => $wd->getToken( mw\Tokens::CSRF ),
		'bot'     => 1,
	];

	if( $entity_id ) {
		if( $data_new->countClaims() ) {
			print_r( $changes );
			if( 'y' === cli\Input::yesNoQuestion( "Save $name $surname $entity_id?" ) ) {
				$wd->post( array_replace( $wbeditentity, [
					'id'   => $entity_id,
					'data' => $data_new->getJSON()
				] ) );
			}
		} else {
			Log::info( "Nothing to be done to $name $surname $entity_id" );
		}
	} else {
		print_r( $changes );
		if( 'y' === cli\Input::yesNoQuestion( "Create $name $surname? | $summary" ) ) {
			// Create new item
			$result = $wd->post( array_replace( $wbeditentity, [
				'new'  => 'item',
				'data' => $data_new->getJSON()
			] ) );
		}
	}
}

##############################
# Legavolley referenced claims
##############################

function legavolley_references() {
	$snaks = new wb\Snaks( [
		// stated in: Lega Pallavolo Serie A
		new wb\SnakItem( 'P248', 'Q16571730' )
	] );
	return [ [ 'snaks' => $snaks->getAll() ] ];
}
