#!/usr/bin/php
<?php
/*****************************
 * Legavolley 2018 importer *
 *                           *
 * @author Valerio Bozzolan  *
 * @license GNU GPL v3+      *
 *****************************/

define( 'SUMMARY', 'Bot: [[Wikidata:Requests for permissions/Bot/Valerio Bozzolan bot 6|importing volleyball players from LegaVolley]]' );

// load boz-mw
require 'includes/boz-mw/autoload.php';

use cli\Log;
use cli\ConfigWizard;
use cli\Opts;
use cli\ParamValuedLong;
use cli\ParamFlag;
use cli\ParamFlagLong;
use wb\References;
use wb\Reference;
use wb\SnakItem;
use wb\StatementItem;

// load configuration file or create one
ConfigWizard::requireOrCreate( __DIR__ . '/../config.php' );

// load options
$opts = new Opts( [
	new ParamValuedLong( 'start-qid', "start from this Wikidata Q ID" ),
	new ParamFlagLong(  'always',    "save without confirmation" ),
	new ParamFlag(      'help', 'h', "show this help and quit" ),
] );

// check if you loaded the help message
if( $opts->getArg( 'help' ) ) {
	help();
}

// starting QID
$start_from_qid = $opts->getArg( 'start-qid' );

// input CSV path name
$input_csv = Opts::unnamedArguments()[0] ?? null;
if( !$input_csv ) {
	help( "please specify a CSV file" );
}

// check if you want to save without confirmation
$ALWAYS = $opts->getArg( 'always' );

// COUNTRIES
$COUNTRIES = [];
$handle = @fopen( 'data/volleyball-nationalities.csv', 'r' );
if( !$handle ) {
	help( "cannot open nationalities" );
}

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
$handle = @fopen( $input_csv, 'r' );
if( !$handle ) {
	help( "cannot open volleyball players from CSV file: $input_csv" );
}
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

	// try birthday from another format
	if( false === $birthday ) {
		$birthday = DateTime::createFromFormat( 'Y-m-d', $birthday_raw );
	}
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
$asd = date( 'U' );
$datas = \wm\Wikidata::querySPARQL(
	  "# Sorry but I have to invalidate this cache somehow: $asd. asd\n"
	. "SELECT ?item ?legavolley_id "
	. "WHERE "
	. "{ "
	. "  ?item wdt:P4303 ?legavolley_id." // ID LegaVolley
	. "}"
);

// existing players (LegaVolleyID => Wikidata entity ID)
$EXISTING_PLAYERS = [];
foreach( $datas as $data ) {
	$entity = str_replace( 'http://www.wikidata.org/entity/', '', $data->item->value );
	$legavolley_id = strtoupper( $data->legavolley_id->value );
	$EXISTING_PLAYERS[ $legavolley_id ] = $entity;
}

$out = fopen('out.csv', 'w');

// Wikidata
$wd = \wm\Wikidata::instance()->login();
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

	if( !$entity_id ) {
		echo "Look for an existing? https://www.wikidata.org/w/index.php?search=" . urlencode( "$name $surname" ) . "\n";
		$entity_id = cli\Input::askInput( "Enter entity ID or nothing (expected code: $legavolley_id)", false );
	}

	// eventually skip until reached wanted ID
	if( $start_from_qid ) {
		if( $entity_id !== $start_from_qid ) {
			continue;
		}
		$start_from_qid = null;
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
	$data_new = $wd->createDataModel();

	// Existing data
	$data_old = null;
	if( $entity_id ) {
		$data_new->setEntityID( $entity_id );

		// https://www.wikidata.org/w/api.pkhp?action=help&modules=wbgetentities
		$data_old = $wd->fetchSingleEntity( $entity_id, [
			'props'  => [
				'info',
				'sitelinks',
				'aliases',
				'labels',
				'descriptions',
				'claims',
				'datatype',
			],
		] );
	}

	// append labels without overwriting
	foreach( $LABELS as $lang => $text ) {
		if( ! $data_old || ! $data_old->hasLabelInLanguage( $lang ) ) {
			$data_new->setLabel( new wb\LabelAction( $lang, $text, wb\LabelAction::ADD ) );
			$changes[] = "+[label][$lang]";
		}
	}

	// append descriptions without overwriting
	foreach( $DESCRIPTIONS as $lang => $text ) {
		if( ! $data_old || ! $data_old->hasDescriptionInLanguage( $lang ) ) {
			$data_new->setDescription( new wb\DescriptionAction( $lang, $text, wb\DescriptionAction::ADD ) );
			$changes[] = "+[description][$lang]";
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

	if( $entity_id ) {
		if( $data_new->countClaims() ) {
			$data_new->printChanges();

			// https://www.wikidata.org/w/api.php?action=help&modules=wbeditentity
			if( $ALWAYS || 'y' === cli\Input::yesNoQuestion( "Save $name $surname $entity_id?" ) ) {
				$data_new->editEntity( [
					'summary.pre' => SUMMARY . ' ',
					'bot'         => 1,
				] );
			}
		} else {
			Log::info( "Nothing to be done to $name $surname $entity_id" );
		}
	} else {
		print_r( $changes );
		if( $ALWAYS || 'y' === cli\Input::yesNoQuestion( "Create $name $surname?" ) ) {
			// Create new item
			// https://www.wikidata.org/w/api.php?action=help&modules=wbeditentity
			$result = $data_new->editEntity( [
				'new'         => 'item',
				'summary.pre' => SUMMARY . ' ',
				'bot'         => 1,
			] );

			if( $result->success ) {
				$entity_id = $result->entity->id;
			} else {
				var_dump( $result );
				echo "exit because API not said success\n";
				exit( 2 );
			}
		}

		Log::info( "Created $entity_id" );
	}

	fputcsv($out, [
		$entity_id,
		$name,
		$surname,
		$legavolley_id,
	] );
}

fclose($out);

echo "End of the story\n";

##############################
# Legavolley referenced claims
##############################

function legavolley_references() {

	$references = new References();

	// stated in: Lega Pallavolo Serie A
	$reference = new Reference();
	$reference->add( new SnakItem( 'P248', 'Q16571730' ) );
	$references->add( $reference );

	return $references;
}

/**
 * Show an help menu and quit
 *
 * @param string $error_message Error message
 */
function help( $error_message = null ) {
	global $opts, $argv;

	echo "Usage:\n {$argv[ 0 ]} file.csv [OPTIONS]\n";
	echo "OPTIONS:\n";
	$opts->printParams();
	if( $error_message ) {
		echo "\nError: $error_message\n";
		exit( 1 );
	}
	exit( 0 );
}
