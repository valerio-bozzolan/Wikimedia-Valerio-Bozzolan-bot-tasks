#!/usr/bin/php
<?php
/*************************************
 * Legavolley importer and uniformer *
 *                                   *
 * @author Valerio Bozzolan          *
 * @license GNU GPL v3+              *
 * @date 2017, 2018, 2019, 2021      *
 *************************************
 *
 ***** WHAT ******************
 * Initially, this bot uniformed volleyball 2017 players' file descriptions
 * in Wikimedia Commons, adding the (new) template {{Depicted person}} and
 * connecting them to Wikidata when possible; the personal category was
 * created if missing and added into the "best fit" national category;
 * all of this in less edit as possible.
 *
 * [[c:Category:Volleyball players by country]]
 * https://commons.wikimedia.org/wiki/Category:Volleyball_players_by_country
 *
 ***** HOW *******************
 * This bot operated generating shitty Pywikibot's replace.py commands,
 * allowing an human to bulk execute them manually.
 * Filenames and names/nationalities of volleyball players were imported from a
 * CSV files provided by [[w:it:Utente:CristianNX]] and other folks.
 *
 * Commons task description:
 * [[c:Commons:Bots/Requests/Valerio Bozzolan bot]]
 * https://commons.wikimedia.org/wiki/Commons:Bots/Requests/Valerio_Bozzolan_bot
 *
 * Italian task brainstorming:
 * [[w:it:Discussioni progetto:Sport/Pallavolo/Legavolley#Categorie e descrizioni]]
 * https://it.wikipedia.org/wiki/Discussioni_progetto:Sport/Pallavolo/Legavolley#Categorie_e_descrizioni
 * https://it.wikipedia.org/wiki/Speciale:PermaLink/93103795#Categorie_e_descrizioni
 *
 * Changes to {{Depicted person}} applied for this task:
 * [[c:Module:Depicted people]]:
 * https://commons.wikimedia.org/wiki/Module:Depicted_people
 * https://commons.wikimedia.org/w/index.php?title=Template%3ADepicted_person&type=revision&diff=265201552&oldid=233297362
 *
 ***** WHAT^2 ****************
 * After that, the task moved to Wikidata.
 * Now lot of volleyball players are without a Wikidata elements, and anyway
 * even when it exists, it miss the image (P18) property and the Commons'
 * category (P373), etc.
 *
 * Wikidata task description:
 * [[d:Wikidata:Requests for permissions/Bot/Valerio Bozzolan bot 2]]
 * https://www.wikidata.org/wiki/Wikidata:Requests_for_permissions/Bot/Valerio_Bozzolan_bot_2
 */

// die on any warning
set_error_handler( function( $severity, $message, $file, $line) {
	if( error_reporting() & $severity ) {
		throw new ErrorException( $message, 0, $severity, $file, $line );
	}
} );

// Framework stuff
require __DIR__ . '/includes/boz-mw/autoload-with-laser-cannon.php';

use wm\Commons;
use wm\Wikidata;
use cli\Input;
use cli\Log;
use cli\ConfigWizard;
use mw\Wikitext;
use mw\API\PageMatcher;
use wb\LabelAction;
use wb\DescriptionAction;
use wb\References;
use wb\Reference;
use wb\SnakItem;

// load configuration file or create one
ConfigWizard::requireOrCreate( __DIR__ . '/../config.php' );

// register some arguments
$opts = cli_options()
  ->addValued( 'players-file',        null, "CSV file with your volleyball players" )
  ->addValued( 'players-cat',         null, "The name of your category without namespace containing files in Wikimedia Commons" )
  ->addValued( 'nat-file',            null, "Nationalities CSV" )
  ->addValued( 'year',                null, "CSV year", date( 'Y' ) )
  ->addValued( 'from',                null, "Starting point (row)" )
  ->addValued( 'wikidata-sandbox',    null, "Wikidata QID element to be used as sandbox" )
  ->addValued( 'sparql',              null, "SPARQL file" )
  ->addFlag(   'always',              null, "Always save without ask" )
  ->addValued( 'always-pause',        null, "Pause before saving during --always mode", 5 )
  ->addFlag(   'debug',               null, "Enable debug mode" )
  ->addFlag(   'skip-without-nation', null, "Eventually skip any volleyball player without identified nation" )
  ->addFlag(   'inspect',             null, "Enable inspect mode" );

// eventually enable debug mode
if( $opts->get( 'debug' ) ) {
	bozmw_debug();
}

// no params no party
if( !$opts->get( 'players-file' ) && !$opts->get( 'players-cat' ) ) {
	help( "Missing --players-file or --players-cat" );
}

if( $opts->get( 'inspect' ) ) {
	\mw\API::$INSPECT_BEFORE_POST = true;
}

if( $opts->get( 'wikidata-sandbox' ) ) {
	define( 'WIKIDATA_SANDBOX', $opts->get( 'wikidata-sandbox' ) );
	define( 'SANDBOXED', true );
	define( 'ONE_SHOT',  true );
	echo WIKIDATA_SANDBOX . "\n";
}

defined( 'SANDBOXED' ) or
define(  'SANDBOXED', false );

defined( 'WIKIDATA_SANDBOX' ) or
define(  'WIKIDATA_SANDBOX', 'Q4115189' );

defined( 'ONE_SHOT' ) or
define(  'ONE_SHOT', false );

defined( 'FORCE_OVERWRITE' ) or
define(  'FORCE_OVERWRITE', false );

defined( 'COMMONS_CONSENSUS_PAGE' ) or
define(  'COMMONS_CONSENSUS_PAGE', 'Commons:Bots/Requests/Valerio Bozzolan bot' );

defined( 'WIKIDATA_CONSENSUS_PAGE' ) or
define(  'WIKIDATA_CONSENSUS_PAGE', 'Wikidata:Requests for permissions/Bot/Valerio Bozzolan bot 2' );

defined( 'COMMONS_SUMMARY' ) or
define(  'COMMONS_SUMMARY', sprintf(
	"[[%s|uniforming Legavolley players]]",
	COMMONS_CONSENSUS_PAGE
) );

defined( 'WIKIDATA_SUMMARY' ) or
define(  'WIKIDATA_SUMMARY', sprintf(
	"[[d:%s|uniforming Legavolley players]]",
	WIKIDATA_CONSENSUS_PAGE
) );

defined( 'VERBOSE' ) or
define(  'VERBOSE', true );

defined( 'INTERACTION' ) or
define(  'INTERACTION', true );

$wd      = Wikidata::instance()->login();
$commons = Commons ::instance()->login();

$WIKIDATA_PLAYERS_BY_LEGAID = [];
$WIKIDATA_PLAYERS_BY_COMMONS_CAT = [];
$sparql_content_raw = file_get_contents( $opts->get( 'sparql' ) );
if( $sparql_content_raw ) {
	$sparql_content = json_decode( $sparql_content_raw );
	foreach( $sparql_content as $line ) {
		$cat = $line->cat ?? $line->p373 ?? null;
		if( $line->legaID ) {
			$WIKIDATA_PLAYERS_BY_LEGAID[ $line->legaID ] = basename( $line->item );
		}
		if( $cat ) {
			$WIKIDATA_PLAYERS_BY_COMMONS_CAT[ $cat ] = basename( $line->item );
		}
	}
}

#############
# Nations CSV
#############

$NAT_BY_CODE  = [];
$NAT_BY_QCODE = [];
$NAT_FILE = $opts->get( 'nat-file', 'commons-volleyball-nationalities.csv' );
if( file_exists( $NAT_FILE ) ) {
	$handle = fopen( $NAT_FILE, 'r' );
	if( $handle ) {
		$i = -1;
		while( ( $data = fgetcsv( $handle, 1000, ',' ) ) !== false ) {
			$i++;
			if( $i === 0 ) {
				continue; // Skip header
			}
			$data[] = null; // prevent warnings if $better_cat is missing; or don't care if $better_cat is present
			list( $code, $item_id, $it_m, $it_f, $en, $cat, $better_cat ) = $data;
			$NAT_BY_CODE [ $code ] = new Nat( $item_id, $it_m, $en, $cat, $better_cat );
			$NAT_BY_QCODE[ $item_id ] = $NAT_BY_CODE [ $code ];
		}
		fclose( $handle );
	} else {
		error_log( sprintf( "unable to open nationalities %s", $NAT_FILE ) );
	}
} else {
	error_log( sprintf( "unexisting nationalities file %s", $NAT_FILE ) );
}

########################
# Volleyball players CSV
########################

if( $opts->get( 'from' ) ) {
	$LATEST = (int) $opts->get( 'from' );
} else {
	$LATEST = file_exists( 'latest.txt' )
		? (int) trim( file_get_contents( 'latest.txt' ) )
		: false;
}

// try to index players
$PLAYERS_FROM_FILE_INDEXED_BY_FILENAME = [];
if( $opts->get( 'players-file' ) ) {
	foreach( players_from_file() as $player ) {
		$PLAYERS_FROM_FILE_INDEXED_BY_FILENAME[ $player->file ] = $player;
	}
}

$generator = null;
if( $opts->get( 'players-cat' ) ) {
	$generator = players_from_commons_cat();
} else {
	$generator = players_from_file();
}

foreach( $generator as $player ) {

	$name             = $player->name ?? null;
	$surname          = $player->surname ?? null;
	$complete_name    = $player->completeName ?? "$name $surname";
	$LEGA_ID          = $player->legaID ?? null;
	$wikidata_item_id = $player->wikidataItemID ?? null;
	$i = $player->i;

	// without namespace
	$personal_cat        = null;
	$personal_cat_exists = null;
	$personal_cat_has_sense = null;

	// complete file path
	// File:Asd.jpg
	$filename = $player->file;

	// Asd.jpg
	$filepath = str_replace( 'File:', '', $filename );

	// it seems OK
	if( false !== $LATEST ) {
		if( $player->i !== $LATEST ) {
			Log::warn( "Skipping $complete_name (jet processed {$player->i}/$LATEST)" );
			continue;
		} else {
			$LATEST = false;
		}
	}

	// The file path is well-known
	if( !commons_page_exists( $filename ) ) {
		Log::warn( "Skipping [[$filename]] that does not exist" );
		continue;
	}

	// Name Surname [[URL]]
	Log::info( sprintf(
		"%s [[%s]]",
		$complete_name,
		commons_page_url( $filename )
	) );

	// default personal category
	if( !$personal_cat ) {
		$personal_cat = $complete_name;
	}

	// personal category: eventually check existence
	if( $personal_cat_exists === null ) {
		$personal_cat_exists = commons_category_exists( $personal_cat );
	}

	// try to retrieve Wikidata ID from Commons category
	if( $wikidata_item_id === null ) {
		$wikidata_item_id = $WIKIDATA_PLAYERS_BY_COMMONS_CAT[ $personal_cat ] ?? null;
		if( $wikidata_item_id ) {
			Log::info( "gotcha! $personal_cat related to $wikidata_item_id thanks to SPARQL response" );
		}
	}

	if( SANDBOXED ) {
		Log::warn( "SANDBOX MODE" );
		$wikidata_item_id = WIKIDATA_SANDBOX;
	} else {
		// get Wikidata item ID from title (or disperately with manual input)
		if( $wikidata_item_id === null ) {
			$wikidata_item_id = get_wikibase_item( $wd, $filename, $complete_name );
		}
	}

	// get Wikidata item data
	$item_data = null;
	if( $wikidata_item_id ) {
		try {
			$item_data = $wd->fetchSingleEntity( $wikidata_item_id, [
				'props'  => [
					'info',
					'sitelinks',
					'aliases',
					'labels',
					'descriptions',
					'claims',
				],
			] );
		} catch( \mw\API\NoSuchEntityException $e ) {
			// do nothing
		}
	}

	// nation from P27: country of citizenship
	$nat_qcode = null;
	if( $item_data ) {
		foreach( $item_data->getClaimsInProperty( 'P27' ) as $claims ) {
			$nation_datavalue = $claims->getMainSnak()->getDataValue()->getValue();
			if( $nation_datavalue ) {
				$nat_qcode = $nation_datavalue[ 'id' ];
			}
			break;
		}
	}

	$nation = null;
	if( $nat_qcode && $NAT_BY_QCODE ) {
		if( empty( $NAT_BY_QCODE[ $nat_qcode ] ) ) {
			throw new \Exception( "missing nation with Q-code $nat_qcode" );
		} else {
			$nation = $NAT_BY_QCODE[ $nat_qcode ];
		}
	}

	// no nation no party
	if( !$nation && $opts->get( 'skip-without-nation' ) ) {
		Log::warn( "skip volleyball player without nation" );
		continue;
	}

	// personal category from Commons sitelink
	if( ! $personal_cat && $item_data && $item_data->getSitelinks()->have( 'commonswiki' ) ) {
		$personal_cat_prefixed = $item_data->getSitelinks()->get( 'commonswiki' )->getTitle();
		$parts = explode( ':', $personal_cat_prefixed );
		$ok = count( $parts ) === 2;
		if( $ok ) {
			list( $check, $personal_cat ) = explode( ':', $personal_cat_prefixed );
			$ok = $check === 'Category';
			if( $ok ) {
				$personal_cat_exists = true;
				$personal_cat_has_sense = true;
			}
		}
		if( !$ok ) {
			Log::error( "Commons sitelink contains 'Category:$personal_cat_prefixed' but it's not a category, don't know what to do, skip" );
		}
	}

	// personal category from Commons category
	if( ! $personal_cat && $item_data && $item_data->hasClaimsInProperty('P373') ) {
		foreach( $item_data->getClaimsInProperty( 'P373' ) as $claims ) {
			$personal_cat = $claims->getMainSnak()->getDataValue()->getValue();
			$personal_cat_has_sense = true;
			break;
		}
	}

	// personal category: eventually check if file is in category
	// this means that the cat has sense
	$file_has_personal_cat = commons_page_has_categories( $filename, [ $personal_cat ], __LINE__ );
	if( $file_has_personal_cat ) {
		$personal_cat_has_sense = true;
	}

	// not sure about this category
	if( !$personal_cat_has_sense && $personal_cat_exists ) {
		//if( ! commons_search_term_in_page_categories( "Category:$personal_cat", 'volleyball' ) ) {
			if( ! INTERACTION ) {
				interaction_warning();
				continue;
			}
			do {
				Log::warn( "[[Category:$personal_cat]]" );
				Log::warn( commons_page_url( "Category:$personal_cat" ) );
				$no = Input::yesNoQuestion( "Is this a volleyball player?" );
				if( $no === 'n' ) {
					$personal_cat .= ' (volleyball player)';
					Log::warn( commons_page_url( "Category:$personal_cat" ) );
					Log::warn( "Insert MANUALLY something as '$personal_cat' or just press ENTER" );
					if( $input = Input::read( $personal_cat ) ) {
						$personal_cat = $input;
					}
				}
				$satisfied = true;
				$personal_cat_exists = commons_category_exists( $personal_cat );
				if( $personal_cat_exists ) {
					Log::warn( "[[Category:$personal_cat]]" );
					Log::warn( commons_page_url( "Category:$personal_cat" ) );
					if( 'n' === Input::yesNoQuestion( "It already exists. Please confirm your choice." ) ) {
						$satisfied = false;
					}
				}

				$file_has_personal_cat = null;
			} while( !$satisfied );
		//}
	}

	// TODO: handle both
	$italian_category = "Volleyball players from Italy";
	$italian_categories = [
		"Men's volleyball players from Italy",
		"Volleyball players from Italy",
	];

	$personal_cat_has_infobox = commons_page_has_templates( "Category:$personal_cat", [ 'Template:Wikidata Infobox' ] );

	// nation-related stuff
	$national_category = null;
	$better_national_cat = null;
	$better_national_cat_exists = null;
	$personal_cat_has_better_national_cat = null;
	$personal_cat_has_best_national_cat = null;
	$personal_cat_has_national_cat = null;
	$personal_cat_has_best_national_cat = null;
	$best_national_category = null;
	if( $nation ) {
		$national_category = $nation->cat;
		$better_national_cat_exists = ! empty( trim( $nation->better_cat ) );
		if( $better_national_cat_exists ) {
			$better_national_cat = $nation->better_cat;
			$personal_cat_has_better_national_cat = commons_page_has_categories( "Category:$personal_cat", [ $better_national_cat ], __LINE__ );
		}

		$personal_cat_has_national_cat = $national_category
			? commons_page_has_categories( "Category:$personal_cat", [ $national_category ], __LINE__ )
			: null;

		$personal_cat_has_best_national_cat = $better_national_cat_exists
			? $personal_cat_has_better_national_cat
			: $personal_cat_has_national_cat;

		$best_national_category = $better_national_cat_exists
			? $better_national_cat
			: $national_category;
	}

	// eventually check again
	if( $file_has_personal_cat === null ) {
		$file_has_personal_cat = commons_page_has_categories( $filename, [ $personal_cat ], __LINE__ );
	}

	$file_has_depicted     = commons_page_has_templates(  $filename, [ 'Template:Depicted person' ] );

	if( !$personal_cat_exists ) {
		$personal_cat_content = $commons->createWikitext();
		if( $name && $surname ) {
			$personal_cat_content->appendWikitext( "{{DEFAULTSORT:$surname, $name}}\n" );
			$personal_cat_content->appendWikitext( "{{Wikidata Infobox|defaultsort=no}}\n" );
		} else {
			$personal_cat_content->appendWikitext( "{{Wikidata Infobox}}\n" );
		}
		$summary = COMMONS_SUMMARY . " +[[Template:Wikidata Infobox]]";

		if( $nation && $best_national_category ) {
			$personal_cat_content->appendWikitext( "[[Category:$best_national_category]]" );
			$summary .= "; +[[Category:$best_national_category]]";
		}

		Log::info( "Please check for duplicates: " . commons_search_page_title( "Category:$personal_cat" ) );
		if( ask_skippable_question( "Create personal category [[Category:$personal_cat]]?" ) ) {
			$commons->edit( [
				'title'   => "Category:$personal_cat",
				'text'    => $personal_cat_content->getWikitext(),
				'summary' => $summary,
				'bot'     => 1,
			] );
		}

		$personal_cat_exists = true;
	}

	// titles to be requested
	$titles = [
		"Category:$personal_cat",
		$filename
	];

	// query the revisions from multiple pages (and their page IDs)
	// https://it.wikipedia.org/w/api.php?action=query&prop=revisions&titles=linux+%28kernel%29&rvprop=content
	$pages = $commons->fetch( [
		'action'  => 'query',
		'prop'    => 'revisions',
		'rvprop'  => 'content',
		'rvslots' => 'main',
		'titles'  => $titles,
	] );

	// associate the results to my pages
	$matcher = new PageMatcher( $pages, $titles );

	// associative array of page title => page content
	$title_content = [];

	// associative array of a page title => page ID
	$title_pageid = [];

	// for each page and my requested titles
	foreach( $matcher->getMatchesByMyTitle() as $title => $page ) {

		// no revisions no party
		if( empty( $page->revisions ) ) {
			throw new Exception( "missing revisions from $title" );
		}

		// remember the page infos
		$title_content[ $title ] =
			// the revision should be just one (the latest one)
			reset( $page->revisions )
				// get just the wikitext from the main slot
				->slots->main->{'*'};

		// remember the page ID
		$title_pageid[ $title ] = $page->pageid;
	}

	// initialize the wikitexts
	$personal_cat_content = $commons->createWikitext( $title_content[ "Category:$personal_cat" ] );
	$filename_content     = $commons->createWikitext( $title_content[ $filename                ] );

	// pageid of the filename
	$filename_pageid = $title_pageid[ $filename ];

	// personal category
	if( $personal_cat_exists ) {

		Log::info( "Category:$personal_cat " . commons_page_url( "Category:$personal_cat" ) );

		// add {{Wikidata infobox}}
		$summary = COMMONS_SUMMARY;
		if( ! $personal_cat_has_infobox && 0 === $personal_cat_content->pregMatch( '/ikidata Infobox/' ) ) {
			$personal_cat_content->prependWikitext( "{{Wikidata Infobox}}\n" );
			$summary .= "; +[[Template:Wikidata Infobox]]";
		}

		if( $nation && $best_national_category && ! $personal_cat_has_best_national_cat ) {
			Log::info( "personal category has not best national_category: 'Category:$best_national_category' ");
			if( $better_national_cat_exists ) {
				if( $personal_cat_has_national_cat ) {
					// National → Best national
					$summary .= "; better national category";
					$personal_cat_content->pregReplace(
						'/' . space2regex( "Category:$national_category" ) . '/',
						"Category:$best_national_category"
					);
				} else {
					// + Best national
					$summary .= "; +[[Category:$better_national_cat]]";
					$personal_cat_content->addCategory( $better_national_cat );
				}
			} elseif( $national_category && ! $personal_cat_has_national_cat ) {
				// + National
				$summary .= "; +[[Category:$national_category]]";
				$personal_cat_content->addCategory( $national_category );
			}
		}

		if( $personal_cat_content->isChanged() && ask_skippable_question( "Save?" ) ) {
			$commons->edit( [
				'title'   => "Category:$personal_cat",
				'text'    => $personal_cat_content->getWikitext(),
				'summary' => $summary,
				'bot'     => 1,
			] );
		}
	}

	// Wikidata labels
	$LABELS = [
		'en' => $complete_name,
		'it' => $complete_name,
	];

	if( $nation ) {
		$DESCRIPTIONS = [
			'en' => sprintf( '%s volleyball player', $nation->en ),
			'it' => sprintf( 'pallavolista %s',      $nation->it ),
		];
	} else {
		$DESCRIPTIONS = [
			'en' => 'volleyball player',
			'it' => 'pallavolista',
		];
	}

	// Wikidata statements
	$STATEMENTS = [
		// Insance of: human
		legavolley_statement_item('P31', 'Q5'),
		// Image
		legavolley_statement_property_commonsmedia('P18', $filepath),
		// Commons category
		legavolley_statement_property_commonscat('P373', $personal_cat),
		// Sex: male
		legavolley_statement_item('P21', 'Q6581097'),
		// Occupation: volleyball player
		legavolley_statement_item('P106', 'Q15117302'),
		// Sport: volleyball
		legavolley_statement_item('P641', 'Q1734'),
	];

	if( $LEGA_ID ) {
		// ID LegaVolley
		$STATEMENTS[] = legavolley_statement_property_string('P4303', $LEGA_ID);
	}

	// Country of citizenship
	if( $nation ) {
		$STATEMENTS[] = legavolley_statement_item('P27', $nation->wd);
	}

	$SITELINK = new wb\Sitelink( 'commonswiki', "Category:$personal_cat" );

	$item_newdata = $wd->createDataModel( $wikidata_item_id );

	if( $wikidata_item_id ) {

		// Sitelink
		if( $personal_cat_exists && ! $item_data->getSitelinks()->have( 'commonswiki' ) ) {
			$item_newdata->getSitelinks()->set( $SITELINK );
		}

		// Labels that are not present
		foreach( $LABELS as $lang => $label ) {
			if( ! $item_data->hasLabelInLanguage( $lang ) ) {
				$item_newdata->setLabel( new LabelAction(
					$lang,
					$label,
					LabelAction::ADD // WIKIBASE BUG!!! THIS IS IGNORED SOMETIMES.
				) );
			}
		}

		// Descriptions that are not present
		foreach( $DESCRIPTIONS as $lang => $description ) {
			if( ! $item_data->hasDescriptionInLanguage( $lang ) ) {
				$item_newdata->setDescription( new DescriptionAction(
					$lang,
					$description,
					DescriptionAction::ADD
				) );
			}
		}

		// Statements that are not present
		foreach( $STATEMENTS as $statement ) {
			$property = $statement->getMainsnak()->getProperty();
			if( ! $item_data->hasClaimsInProperty( $property ) || FORCE_OVERWRITE ) {
				$item_newdata->addClaim( $statement );
			}
		}

		if( !$item_newdata->isEmpty() ) {
			$item_newdata->printChanges();

			// Save existing
			// https://www.wikidata.org/w/api.php?action=help&modules=wbeditentity
			if( ask_skippable_question( "Save?" ) ) {
				$item_newdata->editEntity( [
					'summary.pre' => WIKIDATA_SUMMARY . ": ",
					'bot'         => 1,
				] );
			}
		} else {
			Log::info( "$wikidata_item_id already OK" );
		}
	} else {

		// Commons sitelink
		if( $personal_cat_exists ) {
			$item_newdata->getSitelinks()->set( $SITELINK );
		}

		// Labels
		foreach( $LABELS as $language => $label ) {
			$item_newdata->setLabel( new wb\Label( $language, $label ) );
		}

		// Descriptions
		foreach( $DESCRIPTIONS as $lang => $description ) {
			$item_newdata->setDescription( new wb\Description( $lang, $description ) );
		}

		// Claims
		foreach( $STATEMENTS as $statement ) {
			$item_newdata->addClaim( $statement );
		}

		Log::info( "Confirm creation https://www.wikidata.org/w/index.php?search=" . urlencode( $complete_name ) );
		$item_newdata->printChanges();

		if( ask_skippable_question( "Save?" ) ) {

			// Create
			// https://www.wikidata.org/w/api.php?action=help&modules=wbeditentity
			$result = $item_newdata->editEntity( [
				'new'     => 'item',
				'summary' => WIKIDATA_SUMMARY,
				'bot'     => 1,
			] );

			if( ! $result->success ) {
				var_dump( $result );
				die("API error");
			}

			$wikidata_item_id = $result->entity->id;
		}
	}

	// NULL edit on personal category if added sitelink
	if( $item_newdata->getSitelinks()->have( 'commonswiki' ) && $personal_cat_content ) {
		Log::info( "NULL EDIT" );
		$commons->edit( [
			'title'      => "Category:$personal_cat",
			'appendtext' => "\n",
			'summary'    => "null edit to refresh Wikidata Infobox cache",
			'bot'        => 1,
			'minor'      => 1,
		] );
	}

	// strip out {{Uncategorized}}
	$filename_content->pregReplace( '/{{Uncategorized.*}}[ \t\n]*/', '' );

	if( ! $file_has_depicted || ! $file_has_personal_cat ) {

		$summary = COMMONS_SUMMARY;

		$search_and_replace = [];
		if( ! $file_has_depicted ) {
			$search_and_replace = [
				'/{{[ ]*[eE]n *\|.+?}}\n?/'                    => '',
				'/{{[ ]*[iI]t *\|.+?}}/'                       => "{{Depicted person|$wikidata_item_id}}",
				"/({{Depicted person\|Q[0-9]+}})\n([a-zA-Z])/" => "\$1<br />\n\$2"
			];
			$filename_content->pregReplace(
				array_keys(   $search_and_replace ),
				array_values( $search_and_replace )
			);

			$summary .= "; +[[Template:Depicted person]] [[d:$wikidata_item_id]]";
		}

		foreach( $italian_categories as $italian_category ) {
			$file_has_italian_cat = commons_page_has_categories( $filename, [ $italian_category ], __LINE__ );
			if( $file_has_italian_cat ) {
				if( $national_category && $national_category !== $italian_category ) {
					if( ! $file_has_personal_cat ) {
						$filename_content->pregReplace(
							'/' . space2regex( "Category:$italian_category" ) . '/',
							"Category:$personal_cat"
						);
						$summary .= "; [[Category:$italian_category]] → [[Category:$personal_cat]]";

						$file_has_italian_cat = false;
						$file_has_personal_cat = true;
					}
				}
				if( $file_has_italian_cat ) {
					$filename_content->pregReplace(
						'/\[\[ *' . space2regex( "Category:$italian_category" ) . ' *\]\]\n*/',
						''
					);
					$summary .= "; -[[Category:$italian_category]]";
				}
			}
		}

		// add personal category
		if( $filename_content->addCategory( $personal_cat ) ) {
			$summary .= "; +[[Category:$personal_cat]]";
		}

		if( $filename_content->isChanged() ) {
			$commons->edit( [
				'title'   => $filename,
				'text'    => $filename_content->getWikitext(),
				'summary' => $summary,
				'bot'     => 1,
			] );
		}
	}

	// page ID for the Wikimedia Commons structured data
	$filename_structured_data_id = "M" . $filename_pageid;

	// get the Structured data
	$existing_structured_data = null;
	try {
		$existing_structured_data = $commons->fetchSingleEntity( $filename_structured_data_id, [
			'props' => [
				'labels',
				'descriptions',
				'claims',
			],
		] );
	} catch( \mw\API\NoSuchEntityException $e ) {
		// do nothing
	}

	// create an empty object to be filled with Wikimedia Commons structured data to be saved
	$proposed_structured_data = null;
	if( $existing_structured_data ) {
		$proposed_structured_data = $existing_structured_data->cloneEmpty();
	} else {
		$proposed_structured_data = $commons->createDataModel();
	}

	// eventually add labels in Wikimedia Commons structured data
	foreach( $LABELS as $lang => $label ) {
		if( !$existing_structured_data->hasLabelInLanguage( $lang ) ) {
			$proposed_structured_data->setLabel( new LabelAction( $lang, $label, LabelAction::ADD ) );
		}
	}

	// eventually add labels in Wikimedia Commons Structured Data
	foreach( $DESCRIPTIONS as $lang => $description ) {
		if( !$existing_structured_data->hasDescriptionInLanguage( $lang ) ) {
			$proposed_structured_data->setDescription( new DescriptionAction( $lang, $description, DescriptionAction::ADD ) );
		}
	}

	// eventually set who is "depicted" in the Wikimedia Commons Structured Data
	if( $wikidata_item_id && !$existing_structured_data->hasClaimsInProperty( 'P180' ) ) {

		// add "Depicted" P180 and mark as prominent
		$proposed_structured_data->addClaim(
			legavolley_statement_item( 'P180', $wikidata_item_id )
				->setRank( 'preferred' )
		);
	}

	// check if we can propose some edits
	if( !$proposed_structured_data->isEmpty() ) {

		// show changes
		$proposed_structured_data->printChanges();

		// eventually submit changes
		if( ask_skippable_question( "Save?" ) ) {

			// submit changes with the edit summary
			$proposed_structured_data->editEntity( [
				'summary.pre' => COMMONS_SUMMARY . ': ',
			] );
		}
	}

	// save next
	file_put_contents( 'latest.txt', $player->i );

	if( ONE_SHOT ) {
		break;
	}
}

#################
# Commons queries
#################

function commons_page_exists( $page_title ) {
	if( VERBOSE ) {
		Log::debug( "Does [[$page_title]] exists?" );
	}
	$pages_exists = Commons::instance()->fetch( [
		'action' => 'query',
		'titles' => $page_title,
		'prop'   => 'info'
	] );
	$v = false;
	foreach( $pages_exists->query->pages as $page_id => $page ) {
		if( isset( $page->pageid ) && $page->pageid > 0 ) {
			$v = true;
			break;
		}
	}
	debug_yes_no( $v );
	return $v;
}

function commons_category_exists( $category_name ) {
	if( VERBOSE ) {
		Log::debug( "Does [[$category_name]] exist?" );
	}
	$category_info = Commons::instance()->fetch( [
		'action' => 'query',
		'prop'   => 'categoryinfo',
		'titles' => "Category:$category_name"
	] );
	foreach( $category_info->query->pages as $page_id => $page ) {
		if( $page_id > 0 ) {
			return true;
		}
	}
	return false;
}

function commons_search_term_in_page_categories( $page_title, $search_term ) {
	Log::debug( "desperate cesarelombrosing [[$page_title]] looking for '$search_term' ");
	$categories = Commons::instance()->fetch( [
		'action' => 'query',
		'prop'   => 'categories',
		'clshow' => '!hidden',
		'titles' => $page_title
	] );
	foreach( $categories->query->pages as $page ) {
		if( isset( $page->categories ) ) {
			foreach( $page->categories as $category ) {
				$title = strtolower( $category->title );
				if( false !== strpos( $title, $search_term ) ) {
					Log::debug( "matches" );
					return true;
				}
			}
		}
	}
	Log::debug( "doesn't match" );
	return false;
}

/**
 * Check if a page has all the categories
 */
function commons_page_has_categories( $page_title, $categories = [], $line = __LINE__ ) {
	foreach( $categories as & $category ) {
		$category = "Category:$category";
	}
	Log::debug( "[[$page_title]] have these categories? ([[{$categories[0]}]], ...) (line $line)" );
	$results = commons_page_props( $page_title, 'categories', [
		'clcategories' => $categories,
	] );
	$status = false;
	foreach( $results as $result ) {
		foreach( $result->query->pages as $page ) {
			if( isset( $page->categories ) ) {
				$status = count( $page->categories ) === count( $categories );
				break;
			}
		}
	}
	debug_yes_no( $status );
	return $status;
}

function commons_page_has_templates( $page_title, $templates = [] ) {
	Log::debug( "[[$page_title]] have these templates? ([[{$templates[0]}]], ...)" );
	$results = commons_page_props( $page_title, 'templates', [
		'tlnamespace' => 10,
		'tltemplates' => $templates,
	] );
	$status = false;
	foreach( $results as $result ) {
		foreach( $result->query->pages as $page ) {
			if( isset( $page->templates ) ) {
				$status = count( $page->templates ) === count( $templates );
				break;
			}
		}
	}
	debug_yes_no( $status );
	return $status;
}

function commons_page_props( $page, $prop, $args = [] ) {
	$api = Commons::instance()->createQuery( array_replace( [
		'action'    => 'query',
		'prop'      => $prop,
		'titles'    => $page,
		'requestid' => date( 'U' ), // cache invalidator
	], $args ) );
	$pages = [];
	foreach( $api->getGenerator() as $page ) {
		// TODO: callback to obtain the right object?
		$pages[] = $page;
	}
	return $pages;
}

##################
# Wikidata queries
##################

function search_disperately_wikidata_item( $title ) {
	Log::debug( "Searching Wikidata item as '$title'" );
	$wbsearch = Wikidata::instance()->fetch( [
		'action'      => 'query',
		'list'        => 'wbsearch',
		'wbssearch'   => $title,
		'wbslanguage' => 'en'
	] );
	$titles = [];
	foreach( $wbsearch->query->wbsearch as $wbsearch ) {
		$titles[] = $wbsearch->title;
	}

	$titles = filter_volleyball_wikidata_IDs( $titles );

	return ask_which( $titles, "Pick Wikidata Item for '$title' volleyball player:", function( $title ) {
		return "https://www.wikidata.org/wiki/{$title}";
	} );
}

/**
 * Giving a list of volleyball player Wikidata IDs, returns which of them has a "volleyball label".
 */
function filter_volleyball_wikidata_IDs( $item_ids ) {
	if( ! $item_ids ) {
		return [];
	}

	if( Log::$DEBUG ) {
		Log::debug( "desperate cesarelombrosing following volleyball players:" );
		foreach( $item_ids as $item_id ) {
			Log::debug( " https://www.wikidata.org/wiki/$item_id" );
		}
	}

	$SEARCH_TERMS = [
		'de' => 'volleyball',
		'en' => 'volleyball',
		'it' => 'pallavol' // pallavolo | pallavolista
	];

	$languages = array_keys( $SEARCH_TERMS );

	// https://www.wikidata.org/w/api.php?action=wbgetentities&props=descriptions&ids=Q19675&languages=en|it
	$entities = Wikidata::instance()->createQuery( [
		'action'    => 'wbgetentities',
		'props'     => [ 'descriptions', 'claims' ],
		'ids'       => $item_ids,
		'languages' => $languages,
	] );
	$matching_wikidata_IDs = [];
	foreach( $entities->getGenerator() as $entity ) {
		foreach( $entity->entities as $item_id => $entity ) {

			Log::debug( " examining $item_id" );

			$entity_object = wb\DataModel::createFromObject( $entity );

			// Find LegaVolley ID
			if( $entity_object->hasClaimsInProperty('P4303') ) {
				Log::debug( "  ID LegaVolley match" );
				$matching_wikidata_IDs[ $item_id ] = true;
				break;
			}

			// Find "volleyball" in description
			foreach( $SEARCH_TERMS as $language => $term ) {
				if( isset( $entity->descriptions->{ $language } ) ) {
					$label = $entity->descriptions->{ $language }->value;
					if( false !== strpos( $label, $term ) ) {
						Log::debug( "  Wikidata description[$language] '$label' MATCHES '$term'" );
						$matching_wikidata_IDs[ $item_id ] = $item_id;
						break;
					} else {
						Log::debug( "  Wikidata description[$language] '$label' does NOT match '$term'");
					}
				}
			}

			// Find an image like "Foo (Legavolley 2017).jpg"
			$images = $entity_object->getClaimsInProperty('P18');
			foreach( $images as $image ) {
				$image_value = $image->getMainsnak()->getDataValue()->getValue();
				if( false !== strpos( $image_value, 'Legavolley' ) ) {
					Log::debug( "  image name with 'Legavolley' match" );
					$matching_wikidata_IDs[ $item_id ] = $item_id;
					break;
				}
			}
		}
	}
	$matching_wikidata_IDs = array_keys( $matching_wikidata_IDs );
	if( VERBOSE ) {
		Log::debug( "cesarelombroso candidates:" );
		foreach( $matching_wikidata_IDs as $matching_wikidata_ID ) {
			Log::debug( " https://www.wikidata.org/wiki/$matching_wikidata_ID" );
		}
		if( ! $matching_wikidata_IDs ) {
			Log::debug( "none :(" );
		}
	}
	return $matching_wikidata_IDs;
}

function fetch_wikibase_item( $site, $page_name ) {
	// https://commons.wikimedia.org/w/api.php?action=query&prop=wbentityusage&titles=Category:Alberto+Casadei
	$wbentityusage = $site->fetch( [
		'action' => 'query',
		'prop'   => 'wbentityusage',
		'titles' => $page_name
	] );

	// Page not found
	if( isset( $wbentityusage->query->pages->{-1} ) ) {
		return false;
	}

	foreach( $wbentityusage->query->pages as $page_id => $page ) {
		if( isset( $page->wbentityusage ) ) {
			foreach( $page->wbentityusage as $item_id => $aspects ) {
				// Just the first is enough
				return $item_id;
			}
		}
	}

	// Wikidata element not found
	return null;
}

function get_wikibase_item( $site, $page_name, $title ) {
	static $latest;
	static $latest_title;
	if( $latest_title === $title ) {
		return $latest;
	}
	$latest_title = $title;
	$latest = fetch_wikibase_item( $site, $page_name );
	if( !$latest ) {
		$latest = search_disperately_wikidata_item( $title );
	}
	return $latest;
}

##############################
# Legavolley referenced claims
##############################

function legavolley_statement_item( $property, $item ) {
	$statement = new \wb\StatementItem( $property, $item );
	return $statement->setReferences( legavolley_references() );
}

function legavolley_statement_property_string( $property, $string ) {
	$statement = new wb\StatementString( $property, $string );
	return $statement->setReferences( legavolley_references() );
}

function legavolley_statement_property_commonscat( $property, $cat ) {
	$statement = new wb\StatementCommonsCategory( $property, $cat );
	return $statement->setReferences( legavolley_references() );
}

function legavolley_statement_property_commonsmedia( $property, $filename ) {
	$statement = new wb\StatementCommonsMedia( $property, $filename );
	return $statement->setReferences( legavolley_references() );
}

function legavolley_references() {
	$references = new References();

	// stated in: Lega Pallavolo Serie A
	$reference = new Reference();
	$reference->add( new SnakItem( 'P248', 'Q16571730' ) );
	$references->add( $reference );

	return $references;
}

###########
# CSV stuff
###########

/**
 * Store nationality stuff
 */
class Nat {
	var $wd;
	var $it;
	var $en;
	var $cat;
	var $better_cat;

	function __construct( $item_id, $it, $en, $cat, $better_cat ) {
		$this->wd         = $item_id;
		$this->it         = $it;
		$this->en         = $en;
		$this->cat        = $cat;
		$this->better_cat = $better_cat;
	}
}

class Player {

}

############
# Mixed shit
############

function interaction_warning() {
	echo "# Skipping: it requires interaction\n";
}

function commons_page_url($page) {
	return 'https://commons.wikimedia.org/wiki/' . urlencode( str_replace(' ', '_', $page) );
}

function debug_yes_no( $v ) {
	if( VERBOSE ) {
		$yesno = $v ? "yes" : "no";
		Log::debug( " $yesno" );
	}
}

function ask_which($answers, $question, $callback = null, $take_first_if_one = true ) {
	if( ! $answers ) {
		return false;
	}

	if( 1 === count( $answers ) && $take_first_if_one ) {
		return array_pop( $answers );
	}

	echo "# $question\n";
	$answers = array_values( $answers );
	foreach( $answers as $i => $question ) {
		if( $callback !== null ) {
			$question = $callback( $question );
		}
		printf("# \t[%s]: %s\n", $i + 1, $question);
	}

	if( ! INTERACTION ) {
		echo "# More than one result\n";
		return null;
	}

	printf("\t[ENTER]: none\n");

	$in = (int) Input::read();

	return $in <= 0 ? false : $answers[ $in - 1 ];
}

function space2regex( $s ) {
	return str_replace( ' ', '[_ ]+', escape_regex( $s ) );
}

function escape_regex( $s ) {
	return str_replace( '/', '\/', preg_quote( $s ) );
}

	//
/**
 * File:Aaron Russell (Legavolley 2019).jpg
 * @return "Aaron Russell"
 */
function extract_complete_name_from_file_title( $title ) {

	$title = str_replace( "File:", '', $title );

	preg_match( '/(.+) \(.+/', $title, $matches );

	if( isset( $matches[ 1] ) ) {
		return $matches[ 1 ];
	}

	throw new Exception( "Cannot extract name from $title" ) ;
}

function help( $message = null ) {

	echo " ________________________________________________________________ \n";
	echo "|                                                                |\n";
	echo "| Welcome in the Legavolley Commons/Wikidata bot                 |\n";
	echo "| Designed by Cristian Cenci and implemented by Valerio Bozzolan |\n";
	echo "| Since 2017 at your service! bip.                               |\n";
	echo "|________________________________________________________________|\n";

	echo "\n";

	// show the fantastic manual
	$opts = cli_options();
	printf( "Usage: %s [OPTIONS] \n", $GLOBALS[ 'argv' ][ 0 ] );
	echo "\n";
	echo    "All the OPTIONS:\n";
	$opts->printParams();

	// eventually show a message
	if( $message ) {
		echo "\nERROR: $message\n";
	}

	exit;
}

function commons_search_page_title( $title ) {
	return sprintf(
		"https://commons.wikimedia.org/w/index.php?title=Special:MediaSearch&go=Vai&type=page&search=%s",
		urlencode( $title )
	);
}

function ask_skippable_question( $question ) {
	$ok = true;

	if( cli_options()->get( 'always' ) ) {

		// ask your question
		Log::info( $question );

		// stay ready to wait something
		$seconds = (int) cli_options()->get( 'always-pause' );

		// show a stupid anti-panic progress bar
		Log::info( " Press CTRL+C to interrupt or wait $seconds seconds.", [ 'newline' => false ] );
		for( $i = 0; $i < $seconds; $i++ ) {
			sleep( 1 );
			echo '.';
		}
		echo "\n";

	} else {
		Log::info( "Please check for duplicates: " . commons_search_page_title( "Category:$personal_cat" ) );
		$ok = Input::yesNoQuestion( $question ) === 'y';
	}
	return $ok;
}


function players_from_file() {

	$players_file = cli_options()->get( 'players-file' );

	$year = cli_options()->get( 'year' );

	$handle = fopen( $players_file, 'r' ) or die( "cannot open $players_file" );
	$i = -1;
	while( ( $data = fgetcsv( $handle, 1000, ',' ) ) !== false ) {
		$i++;

		if( $i === 0 ) {
			// Skip header
			continue;
		}

		$LEGA_ID = null;
		$surname = null;
		$name = null;
		$nation = null;
		$file = null;
		$date = null;
		$photo_url = null;
		$height = null;

		if( count( $data ) === 5 ) {
			//   BAS-NIC-96,Bassanello,Nicolò,ITA,     Nicolò Bassanello (Legavolley 2017)
			list( $LEGA_ID, $surname, $name, $nation, $file ) = $data;
		} elseif( count( $data ) >= 9 ) {
			// RUS-AAR-93,  Russell,  Aaron,    205,     1993-06-04,USA,     Baltimora,USA, Itas Trentino,2018,http://www.legavolley.it/FotoAtleta.aspx?Field=FotoTessera&Key=RUS-AAR-93
			list( $LEGA_ID, $surname, $name,    $height, $date,     $nation, $city,    $boh,$boh,         $year, $photo_url ) = $data;
		} else {
			var_dump( $data );
			throw new Exception( "bad row" );
		}

		$player = new Player();
		$player->name    = $name;
		$player->surname = $surname;
		$player->completeName = "$name $surname";
		$player->file   = $file ?? "File:$name $surname (LegaVolley $year).jpg";
		$player->nation = $nation;
		$player->date   = $date;
		$player->height = $height;
		$player->legaID = $LEGA_ID;
		$player->i = $i;

		$player->wikidataItemID = $GLOBALS['WIKIDATA_PLAYERS_BY_LEGAID'][ $LEGA_ID ] ?? null;

		yield $player;
	}


}

function players_from_commons_cat() {

	global $PLAYERS_FROM_FILE_INDEXED_BY_FILENAME;

	/**
	 * Get the category members
	 *
	 * https://meta.wikimedia.org/w/api.php?action=help&modules=query%2Bcategorymembers
	 */
	$queries =
		commons()->createQuery( [
			'action'  => 'query',
			'list'    => 'categorymembers',
			'cmtype'  => 'file',
			'cmtitle' => 'Category:' . cli_options()->get( 'players-cat' ),
		] );

	$i = 0;
	foreach( $queries as $query ) {


		$pages = $query->query->categorymembers ?? [];
		foreach( $pages as $page ) {

			$player = $PLAYERS_FROM_FILE_INDEXED_BY_FILENAME[ $page->title ] ?? new Player();

//			$player->name = $name;
//			$player->surname = $surname;
			$player->completeName = $player->completeName ?? extract_complete_name_from_file_title( $page->title );
			$player->file = $page->title;
			$player->wikidataItemID = $player->wikidataItemID ?? null;
			$player->i = $i;

			yield $player;

			$i++;
		}
	}


}
