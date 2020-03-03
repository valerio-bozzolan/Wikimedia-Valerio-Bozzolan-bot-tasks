#!/usr/bin/php
<?php
/*************************************
 * Legavolley importer and uniformer *
 *                                   *
 * @author Valerio Bozzolan          *
 * @license GNU GPL v3+              *
 * @date 2017, 2018, 2019            *
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
require __DIR__ . '/includes/boz-mw/autoload.php';

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

$OPTS = getopt( '' ,[
	'wikidata-sandbox:',
	'players-file:',
	'from:',
	'inspect',
] );

if( ! $OPTS || empty( $OPTS[ 'players-file' ] ) ) {
	die( "Usage: --players-file=filename.csv\n" );
}

if( isset( $OPTS[ 'inspect' ] ) ) {
	\mw\API::$INSPECT_BEFORE_POST = true;
}

if( isset( $OPTS[ 'wikidata-sandbox' ] ) ) {
	define( 'WIKIDATA_SANDBOX', $OPTS[ 'wikidata-sandbox' ] );
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
	"[[wikidata:%s|uniforming Legavolley players]]",
	WIKIDATA_CONSENSUS_PAGE
) );

defined( 'VERBOSE' ) or
define(  'VERBOSE', true );

defined( 'INTERACTION' ) or
define(  'INTERACTION', true );

defined( 'ALWAYS' ) or
define(  'ALWAYS', true );

$wd      = Wikidata::instance()->login();
$commons = Commons ::instance()->login();

#############
# Nations CSV
#############

$NAT_BY_CODE  = [];
$NAT_BY_QCODE = [];
$handle = fopen( 'commons-volleyball-nationalities.csv', 'r' ) or die( "cannot open nationalities" );
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

########################
# Volleyball players CSV
########################

if( empty( $OPTS[ 'from' ] ) ) {
	$LATEST = file_exists( 'latest.txt' )
		? trim( file_get_contents( 'latest.txt' ) )
		: false;
} else {
	$LATEST = $OPTS[ 'from' ];
}

$handle = fopen( $OPTS[ 'players-file' ], 'r' ) or die( "cannot open {$OPTS[ 'players-file' ]}" );
$i = -1;
while( ( $data = fgetcsv( $handle, 1000, ',' ) ) !== false ) {
	$i++;

	if( $i === 0 ) {
		// Skip header
		continue;
	}

	list( $name, $surname, $file, $ID ) = $data;

	// it seems OK
	if( false !== $LATEST ) {
		if( $ID !== $LATEST ) {
			Log::warn( "Skipping $name $surname (jet processed)" );
			continue;
		} else {
			$LATEST = false;
		}
	}

	// The file path is well-known
	$filepath = "$file.jpg";
	$filename = "File:$filepath";
	if( ! commons_page_exists( $filename ) ) {
		Log::warn( "Skipping [[$filename]] that does not exist" );
		continue;
	}
	$complete_name = "$name $surname";

	// Name Surname [[URL]]
	Log::info( sprintf(
		"%s [[%s]]",
		$complete_name,
		commons_page_url( $filename )
	) );


	// get Wikidata item ID from title (or disperately with manual input)
	$item_id = $ID ? $ID : get_wikidata_item( $filename, $complete_name );
	if( SANDBOXED ) {
		Log::warn( "SANDBOX MODE" );
		$item_id = WIKIDATA_SANDBOX;
	}


	// get Wikidata item data
	$item_data = null;
	if( $item_id ) {
		$item_data = $wd->fetchSingleEntity( $item_id, [
			'props'  => [
				'info',
				'sitelinks',
				'aliases',
				'labels',
				'descriptions',
				'claims',
			],
		] );
	}

	// nation from P27: country of citizenship
	$nat_qcode = null;
	if( $item_data ) {
		foreach( $item_data->getClaimsInProperty( 'P27' ) as $claims ) {
			$nation = $claims->getMainSnak()->getDataValue()->getValue();
			if( $nation ) {
				$nat_qcode = $nation[ 'id' ];
			}
			break;
		}
	}
	if( empty( $NAT_BY_QCODE[ $nat_qcode ] ) ) {
		throw new \Exception( "missing nation with Q-code $nat_qcode" );
	}
	$nation = $NAT_BY_QCODE[ $nat_qcode ];


	// reset personal category
	$personal_cat = null;
	$personal_cat_exists = null;
	$personal_cat_has_sense = null;

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

	// default personal category
	if( !$personal_cat ) {
		$personal_cat = $complete_name;
	}

	// personal category: eventually check existence
	if( $personal_cat_exists === null ) {
		$personal_cat_exists = commons_category_exists( $personal_cat );
	}

	// not sure about this category
	if( !$personal_cat_has_sense && commons_category_exists( $personal_cat ) ) {
		//if( ! commons_search_term_in_page_categories( "Category:$personal_cat", 'volleyball' ) ) {
			if( ! INTERACTION ) {
				interaction_warning();
				continue;
			}
			do {
				Log::warn( "[[Category:$personal_cat]]" );
				Log::warn( commons_page_url( "Category:$personal_cat" ) );
				$no = Input::yesNoQuestion( "Is he a volleyball player?" );
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
			} while( !$satisfied );
		//}
	}

	$italian_category  = "Men's volleyball players from Italy";
	$national_category = $nation->cat;
	$better_national_cat_exists = ! empty( trim( $nation->better_cat ) );
	if( $better_national_cat_exists ) {
		$better_national_cat = $nation->better_cat;
		$personal_cat_has_better_national_cat = commons_page_has_categories( "Category:$personal_cat", [ $better_national_cat ] );
	}
	$personal_cat_has_infobox = commons_page_has_templates( "Category:$personal_cat", [ 'Template:Wikidata Infobox' ] );
	$personal_cat_has_national_cat = $national_category
		? commons_page_has_categories( "Category:$personal_cat", [ $national_category ] )
		: null;

	$personal_cat_has_best_national_cat = $better_national_cat_exists
		? $personal_cat_has_better_national_cat
		: $personal_cat_has_national_cat;

	$best_national_category = $better_national_cat_exists
		? $better_national_cat
		: $national_category;

	$file_has_personal_cat = commons_page_has_categories( $filename, [ $personal_cat ] );
	$file_has_italian_cat  = commons_page_has_categories( $filename, [ $italian_category ] );
	$file_has_depicted     = commons_page_has_templates(  $filename, [ 'Template:Depicted person' ] );

	if( !$personal_cat_exists ) {
		$personal_cat_content = $commons->createWikitext();
		$personal_cat_content->appendWikitext( "{{DEFAULTSORT:$surname, $name}}\n" );
		$personal_cat_content->appendWikitext( "{{Wikidata Infobox|defaultsort=no}}\n" );
		$personal_cat_content->appendWikitext( "[[Category:$best_national_category]]" );
		$summary = COMMONS_SUMMARY . " +[[Template:Wikidata Infobox]] [[d:$item_id]]; +[[Category:$best_national_category]]";
		$commons->edit( [
			'title'   => "Category:$personal_cat",
			'text'    => $personal_cat_content->getWikitext(),
			'summary' => $summary,
			'bot'     => 1,
		] );
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

		if( $best_national_category && ! $personal_cat_has_best_national_cat ) {
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

		if( $personal_cat_content->isChanged() ) {
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
		'en' => sprintf( '%s %s', $name, $surname ),
		'it' => sprintf( '%s %s', $name, $surname ),
	];
	$DESCRIPTIONS = [
		'en' => sprintf( '%s volleyball player', $nation->en ),
		'it' => sprintf( 'pallavolista %s',      $nation->it ),
	];

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
		// Country of citizenship
		legavolley_statement_item('P27', $nation->wd),
		// Occupation: volleyball player
		legavolley_statement_item('P106', 'Q15117302'),
		// Sport: volleyball
		legavolley_statement_item('P641', 'Q1734'),
		// ID LegaVolley
		legavolley_statement_property_string('P4303', $ID),
	];

	$SITELINK = new wb\Sitelink( 'commonswiki', "Category:$personal_cat" );

	$item_newdata = $wd->createDataModel( $item_id );

	if( $item_id ) {
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
			$item_newdata->editEntity( [
				'summary.pre' => WIKIDATA_SUMMARY . ": ",
				'bot'         => 1,
			] );
		} else {
			Log::info( "$item_id already OK" );
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
		$save = Input::yesNoQuestion( "Save?" );
		if( 'y' === $save ) {
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

			$item_id = $result->entity->id;
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
				'/{{[ ]*[iI]t *\|.+?}}/'                       => "{{Depicted person|$item_id}}",
				"/({{Depicted person\|Q[0-9]+}})\n([a-zA-Z])/" => "\$1<br />\n\$2"
			];
			$filename_content->pregReplace(
				array_keys(   $search_and_replace ),
				array_values( $search_and_replace )
			);

			$summary .= "; +[[Template:Depicted person]] [[d:$item_id]]";
		}

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
	$existing_structured_data = $commons->fetchSingleEntity( $filename_structured_data_id, [
		'props' => [
			'labels',
			'descriptions',
			'claims',
		],
	] );

	// create an empty object to be filled with Wikimedia Commons structured data to be saved
	$proposed_structured_data = $existing_structured_data->cloneEmpty();

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
	if( $item_id && !$existing_structured_data->hasClaimsInProperty( 'P180' ) ) {

		// add "Depicted" P180 and mark as prominent
		$proposed_structured_data->addClaim(
			legavolley_statement_item( 'P180', $item_id )
				->setRank( 'preferred' )
		);
	}

	// check if we can propose some edits
	if( !$proposed_structured_data->isEmpty() ) {
		// show changes
		$proposed_structured_data->printChanges();

		// eventually submit changes
		$save = Input::yesNoQuestion( "Save?" );
		if( 'y' === $save ) {

			// submit changes with the edit summary
			$proposed_structured_data->editEntity( [
				'summary.pre' => WIKIDATA_SUMMARY . ': ',
			] );
		}
	}

	// eventually add descriptions in Wikimedia Commons structured data

	// save next
	file_put_contents( 'latest.txt', $item_id );

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
	Log::debug( "Cesarelombrosing [[$page_title]] looking for '$search_term' ");
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

function commons_page_has_categories( $page_title, $categories = [] ) {
	foreach( $categories as & $category ) {
		$category = "Category:$category";
	}
	Log::debug( "[[$page_title]] have these categories? ([[{$categories[0]}]], ...)" );
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
		Log::debug( "Cesarelombrosing following volleyball players:" );
		foreach( $item_ids as $item_id ) {
			Log::debug( "https://www.wikidata.org/wiki/$item_id" );
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
		'props'     => 'descriptions|claims',
		'ids'       => $item_ids,
		'languages' => $languages,
	] );
	$matching_wikidata_IDs = [];
	foreach( $entities->getGenerator() as $entity ) {
		foreach( $entity->entities as $item_id => $entity ) {
			$entity_object = wb\DataModel::createFromObject( $entity );
			foreach( $item_ids as $item_id ) {
				if( $item_id === $item_id ) {

					// Find LegaVolley ID
					if( $entity_object->hasClaimsInProperty('P4303') ) {
						Log::debug( "ID LegaVolley match" );
						$matching_wikidata_IDs[ $item_id ] = true;
						break;
					}

					// Find "volleyball" in description
					foreach( $SEARCH_TERMS as $language => $term ) {
						if( isset( $entity->descriptions->{ $language } ) ) {
							$label = $entity->descriptions->{ $language };
							if( false !== strpos( $label->value, $term ) ) {
								Log::debug( "Wikidata $language label match" );
								$matching_wikidata_IDs[ $item_id ] = true;
								break;
							}
						}
					}

					// Find an image like "Foo (Legavolley 2017).jpg"
					$images = $entity_object->getClaimsInProperty('P18');
					foreach( $images as $image ) {
						$image_value = $image->getMainsnak()->getDataValue()->getValue();
						if( false !== strpos( $image_value, 'Legavolley' ) ) {
							Log::debug( "image name with 'Legavolley' match" );
							$matching_wikidata_IDs[ $item_id ] = true;
							break;
						}
					}
				}
			}
		}
	}
	$matching_wikidata_IDs = array_keys( $matching_wikidata_IDs );
	if( VERBOSE ) {
		echo "# Matching:\n";
		foreach( $matching_wikidata_IDs as $matching_wikidata_ID ) {
			Log::debug( "https://www.wikidata.org/wiki/$item_id" );
		}
		if( ! $matching_wikidata_IDs ) {
			Log::debug( "none :(" );
		}
	}
	return $matching_wikidata_IDs;
}

function fetch_wikidata_item( $page_name ) {
	// https://commons.wikimedia.org/w/api.php?action=query&prop=wbentityusage&titles=Category:Alberto+Casadei
	$wbentityusage = Commons::instance()->fetch( [
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

function get_wikidata_item( $page_name, $title ) {
	static $latest;
	static $latest_title;
	if( $latest_title === $title ) {
		return $latest;
	}
	$latest_title = $title;
	$latest = fetch_wikidata_item( $page_name );
	if( ! $latest ) {
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

############
# Mixed shit
############

function interaction_warning() {
	echo "# Skipping: it requires interaction\n";
}

function commons_page_url($page) {
	return 'https://commons.wikimedia.org/wiki/' . urlencode( str_replace(' ', '_', $page) );
}

function debug_yes_no($v) {
	if( VERBOSE ) {
		$yesno = $v ? "yes" : "no";
		Log::debug( $yesno );
	}
}

function ask_which($answers, $question, $callback = null) {
	if( ! $answers ) {
		return false;
	}

	if( 1 === count( $answers ) ) {
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
