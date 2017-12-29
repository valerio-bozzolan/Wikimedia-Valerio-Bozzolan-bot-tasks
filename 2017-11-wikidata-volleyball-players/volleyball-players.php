<?php
/*****************************
 * Legavolley 2017 uniformer *
 *                           *
 * @author Valerio Bozzolan  *
 * @license GNU GPL v3+      *
 * @date 2017                *
 *****************************
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
 * Italian task brainmasturbing:
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

#################
# Framework stuff
#################

require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/boz-mw/autoload.php';

mw\APIRequest::$WAIT_POST = 0.2;

##############################
# Start of spaghetti constants
##############################

if( isset( $argv[1] ) ) {
	define( 'WIKIDATA_SANDBOX', $argv[1] );
	define( 'SANDBOXED', true            );
	define( 'ONE_SHOT', true             );
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
	"[[%s|uniforming Legavolley 2017 players]]",
	COMMONS_CONSENSUS_PAGE
) );

defined( 'WIKIDATA_SUMMARY' ) or
define(  'WIKIDATA_SUMMARY', sprintf(
	"[[%s|uniforming Legavolley 2017 players]]",
	WIKIDATA_CONSENSUS_PAGE
) );

defined( 'VERBOSE' ) or
define(  'VERBOSE', true );

defined( 'INTERACTION' ) or
define(  'INTERACTION', true );

defined( 'ALWAYS' ) or
define(  'ALWAYS', true );

######################
# Wikidata Login token
######################

$wikidata_api = mw\APIRequest::factory('https://www.wikidata.org/w/api.php');
$logintoken = $wikidata_api->fetch( [
	'action' => 'query',
	'meta'   => 'tokens',
	'type'   => 'login'
] )->query->tokens->logintoken;

################
# Wikidata login
################

$response = $wikidata_api->post( [
	'action'     => 'login',
	'lgname'     => WIKI_USERNAME,
	'lgpassword' => WIKI_PASSWORD,
	'lgtoken'    => $logintoken
] );
if( ! isset( $response->login->result ) || $response->login->result !== 'Success' ) {
	throw new Exception("login failed");
}

##############################
# Wikidata CSRF token (logged)
##############################

$WIKIDATA_CSRF_TOKEN = $wikidata_api->fetch( [
	'action' => 'query',
	'meta'   => 'tokens',
	'type'   => 'csrf'
] )->query->tokens->csrftoken;

#####################
# Commons Login token
#####################

$commons_api = mw\APIRequest::factory('https://commons.wikimedia.org/w/api.php');
$logintoken = $commons_api->fetch( [
	'action' => 'query',
	'meta'   => 'tokens',
	'type'   => 'login'
] )->query->tokens->logintoken;

###############
# Commons login
###############

$response = $commons_api->post( [
	'action'     => 'login',
	'lgname'     => WIKI_USERNAME,
	'lgpassword' => WIKI_PASSWORD,
	'lgtoken'    => $logintoken
] );
if( ! isset( $response->login->result ) || $response->login->result !== 'Success' ) {
	throw new Exception("login failed");
}

#############################
# Commons CSRF token (logged)
#############################

$COMMONS_CSRF_TOKEN = $commons_api->fetch( [
	'action' => 'query',
	'meta'   => 'tokens',
	'type'   => 'csrf'
] )->query->tokens->csrftoken;

#############
# Nations CSV
#############

$NATIONS = [];
if( ($handle = fopen('commons-volleyball-nationalities.csv', 'r') ) !== false ) {
	$i = -1;
	while( ($data = fgetcsv($handle, 1000, ',') ) !== false ) {
		$i++;

		if( $i === 0 ) {
			// Skip header
			continue;
		}

		// :P This prevents warnings if $better_cat is missing; or don't care if $better_cat is present.
		$data[] = null;

		list($code, $wikidata_id, $it_m, $it_f, $en, $cat, $better_cat ) = $data;
		$NATIONS[ $code ] = new Nat($wikidata_id, $it_m, $en, $cat, $better_cat);
	}
	fclose($handle);
}

$missing_natcodes = [];

########################
# Volleyball players CSV
########################

$LATEST = file_exists( 'latest.txt' )
	? trim( file_get_contents( 'latest.txt' ) )
	: false;

if( ( $handle = fopen('commons-volleyball-players.csv', 'r') ) !== false ) {

	$porcelain = false;

	$i = -1;
	while( ( $data = fgetcsv($handle, 1000, ',')) !== false ) {
		$i++;

		if( $i === 0 ) {
			// Skip header
			continue;
		}

		list($ID, $surname, $name, $natcode, $file) = $data;

		if( ! isset( $NATIONS[ $natcode ] ) ) {
			$missing_natcodes[] = $natcode;
			$porcelain = true;
		}

		if( $porcelain ) {
			continue;
		}

		if( false !== $LATEST && $ID < $LATEST ) {
			echo "Skipping $surname (jet processed)...\n";
			continue;
		}
		$LATEST = $ID;
		file_put_contents( 'latest.txt', $LATEST );

		$filepath = "$file.jpg";
		$filename = "File:$filepath";
		if( ! commons_page_exists( $filename ) ) {
			echo "# Skipping [[$filename]] that does not exist\n";
			continue;
		}

		$filename_url = commons_page_url( $filename );
		$complete_name = "$name $surname";

		$wikidata_item = get_wikidata_item( $filename, $complete_name );
		if( SANDBOXED ) {
			echo "SANDBOXED\n";
			$wikidata_item = WIKIDATA_SANDBOX;
		}

		$wikidata_item_data = null;
		if( $wikidata_item ) {
			// Retrieve & compare
			// https://www.wikidata.org/w/api.php?action=help&modules=wbgetentities
			$wikidata_item_data = $wikidata_api->fetch( [
				'action' => 'wbgetentities',
				'ids'    => $wikidata_item,
				'props'  => 'info|sitelinks|aliases|labels|descriptions|claims|datatype'
			] );
			if( ! isset( $wikidata_item_data->entities->{ $wikidata_item } ) ) {
				throw new Exception("$wikidata_item does not exist?");
			}
			$wikidata_item_data = wb\DataModel::createFromObject( $wikidata_item_data->entities->{ $wikidata_item } );
		}

		$personal_category = $complete_name;
		if( $wikidata_item && $wikidata_item_data->hasClaimsInProperty('P373') ) {
			foreach( $wikidata_item_data->getClaimsInProperty('P373') as $claims ) {
				$personal_category = $claims->getMainSnak()->getDataValue()->getValue();
				break;
			}
		}

		$personal_category_prefixed = "Category:$personal_category";
		$personal_category_url      = commons_page_url( $personal_category_prefixed );
		$personal_category_exists   = commons_category_exists(  $personal_category_prefixed );

		if( $personal_category_exists ) {
			if( ! commons_search_term_in_page_categories( $personal_category_prefixed, 'volleyball' ) ) {
				if( ! INTERACTION ) {
					interaction_warning();
					continue;
				}

				echo "# Is he a volleyball player? [ENTER/n]\n";
				echo "# [[$personal_category_prefixed]]\n";
				echo "# " . commons_page_url( $personal_category_prefixed ) . "\n";
				$no = read();
				if( $no === 'n' || $no === 'N' ) {
					$personal_category .= ' (volleyball player)';
					echo "Insert MANUALLY something as '$personal_category':";
					$personal_category = read( $personal_category );
					$personal_category_prefixed = "Category:$personal_category";
					echo "# Input: [[$personal_category_prefixed]]\n";
					echo "# " . commons_page_url( $personal_category_prefixed ) . "\n";
					$personal_category_exists = commons_category_exists(  $personal_category_prefixed );
					echo "# Confirm category name.";
					read();
				}
			}
 		}

		$italian_category = "Men's volleyball players from Italy";
		$italian_category_prefixed = "Category:$italian_category";

		$nation = $NATIONS[ $natcode ];
		$national_category = $nation->cat;
		$national_category_prefixed = "Category:$national_category";

		$better_national_category_exists = ! empty( trim( $nation->better_cat ) );
		if( $better_national_category_exists ) {
			$better_national_category = $nation->better_cat;
			$better_national_category_prefixed = "Category:$better_national_category";
			$personal_category_has_better_national_category = commons_page_has_categories( $personal_category_prefixed, [ $better_national_category_prefixed ] );
		}

		$file_has_personal_category = commons_page_has_categories( $filename, [ $personal_category_prefixed ] );
		$file_has_italian_category  = commons_page_has_categories( $filename, [ $italian_category_prefixed ] );
		$file_has_iten_templates    = commons_page_has_templates(  $filename, [ 'Template:En', 'Template:It' ] );
		$file_has_template_depicted = commons_page_has_templates(  $filename, [ 'Template:Depicted person' ] );
		$personal_category_has_national_category = commons_page_has_categories( $personal_category_prefixed, [ $national_category_prefixed ] );
		$personal_category_has_wikidata_template = commons_page_has_templates(  $personal_category_prefixed, [ 'Template:Wikidata person' ] );

		$personal_category_has_best_national_category = $better_national_category_exists
			? $personal_category_has_better_national_category
			: $personal_category_has_national_category;

		$best_national_category = $better_national_category_exists
			? $better_national_category
			: $national_category;

		$best_national_category_prefixed = "Category:$best_national_category";

		echo "# $name $surname [[$filename_url]]\n";

		// Wikidata labels
		$LABELS = [
			'en' => sprintf( '%s %s', $name, $surname ),
			'it' => sprintf( '%s %s', $name, $surname )
		];
		$DESCRIPTIONS = [
			'en' => sprintf( '%s volleyball player', $nation->en ),
			'it' => sprintf( 'pallavolista %s',      $nation->it )
		];

		// Wikidata statements
		$STATEMENTS = [
			// Insance of: human
			legavolley_wikidata_statement_item('P31', 'Q5'),
			// Image
			legavolley_wikidata_statement_property_commonsmedia('P18', $filepath),
			// Commons category
			legavolley_wikidata_statement_property_string('P373', $personal_category),
			// Sex: male
			legavolley_wikidata_statement_item('P21', 'Q6581097'),
			// Country of citizenship
			legavolley_wikidata_statement_item('P27', $nation->wd),
			// Occupation: volleyball player
			legavolley_wikidata_statement_item('P106', 'Q15117302'),
			// Sport: volleyball
			legavolley_wikidata_statement_item('P641', 'Q1734'),
			// ID LegaVolley
			legavolley_wikidata_statement_property_string('P4303', $ID)
		];

		$wikidata_item_new_data = new wb\DataModel();

		if( $wikidata_item ) {
			$summary = WIKIDATA_SUMMARY;

			// Labels that are not present
			foreach( $LABELS as $lang => $label ) {
				if( ! $wikidata_item_data->hasLabelsInLanguage( $lang ) ) {
					$summary .= "; +label $lang";
					$wikidata_item_new_data->setLabel( new wb\LabelAction(
						$lang,
						$label,
						wb\LabelAction::ADD // BUG!!! THIS IS IGNORED SOMETIMES.
					) );
				}
			}

			// Descriptions that are not present
			foreach( $DESCRIPTIONS as $lang => $description ) {
				if( ! $wikidata_item_data->hasDescriptionsInLanguage( $lang ) ) {
					$summary .= "; +description $lang";
					$wikidata_item_new_data->setDescription( new wb\DescriptionAction(
						$lang,
						$description,
						wb\DescriptionAction::ADD
					) );
				}
			}

			// Statements that are not present
			foreach( $STATEMENTS as $statement ) {
				$property = $statement->getMainsnak()->getProperty();
				if( ! $wikidata_item_data->hasClaimsInProperty( $property ) || FORCE_OVERWRITE ) {
					$summary .= "; +[[P:$property]]";
					$wikidata_item_new_data->addClaim( $statement );
				}
			}

			if( $wikidata_item_new_data->countClaims() ) {
				echo $wikidata_item_new_data->getJSON( JSON_PRETTY_PRINT );
				echo "Confirm existing https://www.wikidata.org/wiki/$wikidata_item\n";
				sleep(1);

				// Save existing
				// https://www.wikidata.org/w/api.php?action=help&modules=wbeditentity
				$wikidata_api->post( [
					'action'  => 'wbeditentity',
					'id'      => $wikidata_item,
					'summary' => $summary,
					'token'   => $WIKIDATA_CSRF_TOKEN,
					'bot'     => 1,
					'data'    => $wikidata_item_new_data->getJSON()
				] );
			} else {
				echo "# $wikidata_item OK, skip...\n";
			}
		} else {

			// Labels
			foreach( $LABELS as $language => $label ) {
				$wikidata_item_new_data->setLabel( new wb\Label( $language, $label ) );
			}

			// Descriptions
			foreach( $DESCRIPTIONS as $lang => $description ) {
				$wikidata_item_new_data->setDescription( new wb\Description( $lang, $description ) );
			}

			// Claims
			foreach( $STATEMENTS as $statement ) {
				$wikidata_item_new_data->addClaim( $statement );
			}

			echo $wikidata_item_new_data->getJSON( JSON_PRETTY_PRINT );

			echo "Confirm creation https://www.wikidata.org/w/index.php?search=" . urlencode( $complete_name );
			read();

			// Create
			// https://www.wikidata.org/w/api.php?action=help&modules=wbeditentity
			$result = $wikidata_api->post( [
				'action'  => 'wbeditentity',
				'new'     => 'item',
				'summary' => WIKIDATA_SUMMARY,
				'token'   => $WIKIDATA_CSRF_TOKEN,
				'bot'     => 1,
				'data'    => $wikidata_item_new_data->getJSON()
			] );

			if( ! $result->success ) {
				var_dump( $result );
				die("API error");
			}

			$wikidata_item = $result->entity->id;
		}

		echo "$wikidata_item\n";

		// Wikitext
		// https://it.wikipedia.org/w/api.php?action=query&prop=revisions&titles=linux+%28kernel%29&rvprop=content
		$titles = [
			$personal_category_prefixed,
			$filename
		];
		$pages = $commons_api->fetch( [
			'action' => 'query',
			'prop'   => 'revisions',
			'titles' => implode( '|', $titles ),
			'rvprop' => 'content'
		] );
		$title_pages = [];
		foreach( $titles as $title ) {
			$normalized_title = $title;
			if( isset( $query->normalized ) ) {
				foreach( $query->normalized as $normalized ) {
					if( $normalized->from === $title ) {
						$normalized_title = $normalized->to;
						break;
					}
				}
			}
			$page_content = null;
			foreach( $pages->query->pages as $page ) {
				if( $page->title === $normalized_title ) {
					$page_content = $page->revisions[0]->{'*'};
					break;
				}
			}
			$title_pages[ $title ] = $page_content;
		}

		$personal_category_content = $title_pages[ $personal_category_prefixed ];
		$filename_content          = $title_pages[ $filename ];

		if( ! $file_has_template_depicted || ! $file_has_personal_category ) {

			$summary = COMMONS_SUMMARY;

			$replace = false;

			$search_and_replace = [];
			if( ! $file_has_template_depicted ) {
				$search_and_replace = [
					'/{{[ ]*[eE]n *\|.+?}}\n?/'                    => '',
					'/{{[ ]*[iI]t *\|.+?}}/'                       => "{{Depicted person|$wikidata_item}}",
					"/({{Depicted person\|Q[0-9]+}})\n([a-zA-Z])/" => "\$1<br />\n\$2"
				];
				$filename_content = preg_replace(
					array_keys(   $search_and_replace ),
					array_values( $search_and_replace ),
					$filename_content
				);

				$summary .= "; +[[Template:Depicted person]] [[d:$wikidata_item]]";
				$replace = true;
			}

			if( $file_has_italian_category ) {
				if( $national_category !== $italian_category ) {
					if( ! $file_has_personal_category ) {
						$filename_content = preg_replace(
							'/' . space2regex( $italian_category_prefixed ) . '/',
							$personal_category_prefixed,
							$filename_content
						);
						$summary .= "; [[$italian_category_prefixed]] → [[$personal_category_prefixed]]";

						$file_has_italian_category = false;
						$file_has_personal_category = true;
						$replace = true;
					}
				}
				if( $file_has_italian_category ) {
					$filename_content = preg_replace(
						'/\[\[ *' . space2regex( $italian_category_prefixed ) . ' *\]\]\n*/',
						'',
						$filename_content
					);
					$summary .= "; -[[$italian_category_prefixed]]";
					$replace = true;
				}
			}

			if( ! $file_has_personal_category ) {
				$filename_content = preg_replace(
					'/$/',
					"\n[[$personal_category_prefixed]]",
					$filename_content
				);
				$summary .= "; +[[$personal_category_prefixed]]";
				$replace = true;

				// Probably it has two very similar categories
				if( $personal_category !== $complete_name ) {
					var_dump( $personal_category, $complete_name );
					if( 1 === preg_match( '/' . space2regex( $complete_name ) . '/', $complete_name ) ) {
						$filename_content = preg_replace(
							'/\[\[ *' . space2regex( "Category:$complete_name" ) . ' *\]\]\n*/',
							'',
							$filename_content
						);
						$summary .= "; -[[Category:$complete_name]] (wrong name)";
					}
				}
			}

			if( $replace ) {
				commons_save( $filename, $filename_content, $summary );
			}
		}

		echo "\n\n";

		$summary = COMMONS_SUMMARY;

		if( $personal_category_exists ) {
			echo "# [[Category:$personal_category]] [[$personal_category_url]]\n";

			// Decides if skip
			if( $personal_category_has_best_national_category ) {
				if( $personal_category_has_wikidata_template ) {
					echo "# It's perfect yet! Skip.\n";
					continue;
				}
			}

			if( ! $personal_category_has_wikidata_template ) {
				$summary .= sprintf("; +[[Template:Wikidata person]] [[d:%s]]", $wikidata_item);
				$personal_category_content = preg_replace(
					'/^(?!{{Wikidata person)/',
					"{{Wikidata person|$wikidata_item}}\n",
					$personal_category_content
				);
			}

			if( ! $personal_category_has_best_national_category ) {
				echo "personal category has not best national_category: '$best_national_category_prefixed'\n";

				if( $better_national_category_exists ) {
					if( $personal_category_has_national_category ) {
						// National → Best national
						$summary .= "; better national category";
						$personal_category_content = preg_replace(
							'/' . space2regex( $national_category_prefixed ) . '/',
							$best_national_category_prefixed,
							$personal_category_content
						);
					} else {
						// + Best national
						$summary .= "; +[[$better_national_category_prefixed]]";
						$personal_category_content = preg_replace(
							'/$/',
							"\n[[$better_national_category_prefixed]]",
							$personal_category_content
						);
					}
				} elseif( ! $personal_category_has_national_category ) {
					// + National
					$summary .= "; +[[$national_category_prefixed]]";
					$personal_category_content = preg_replace(
						'/$/',
						"\n[[$national_category_prefixed]]",
						$personal_category_content
					);
				}
			}

			commons_save( $personal_category_prefixed, $personal_category_content, $summary );
		} else {
			// Create

			$summary = COMMONS_SUMMARY;

			$lines = [];
			$lines[] = sprintf(
				'{{Wikidata person|%s}}',
				$wikidata_item
			);
			$summary .= "; +[[Template:Wikidata person]] [[d:$wikidata_item]]";

			$lines[] = sprintf(
				'{{DEFAULTSORT:%s, %s}}',
				$surname,
				$name
			);

			$lines[] = "[[$best_national_category_prefixed]]";
			$summary .= "; +[[$best_national_category_prefixed]]";

			commons_save( $personal_category_prefixed, implode("\n", $lines), $summary );
		}

		if( ONE_SHOT ) {
			break;
		}
	}
}

if( $missing_natcodes ) {
	echo "Missing natcodes: ";
	echo implode("\n", array_unique( $missing_natcodes ) );
}

#################
# Commons queries
#################

function commons_page_exists( $page_title ) {
	if( VERBOSE ) {
		echo "# Does [[$page_title]] exists?\n";
	}
	$pages_exists = mw\APIRequest::factory('https://commons.wikimedia.org/w/api.php', [
		'action' => 'query',
		'titles' => $page_title,
		'prop'   => 'info'
	] )->fetch();
	foreach( $pages_exists->query->pages as $page_id => $page ) {
		if( $page_id > 0 ) {
			return true;
		}
	}
	return false;
}

function commons_category_exists($category_name) {
	if( VERBOSE ) {
		echo "# Does [[$category_name]] exist?\n";
	}
	$category_info = mw\APIRequest::factory('https://commons.wikimedia.org/w/api.php', [
		'action' => 'query',
		'prop'   => 'categoryinfo',
		'titles' => $category_name
	] )->fetch();
	foreach( $category_info->query->pages as $page_id => $page ) {
		if( $page_id > 0 ) {
			return true;
		}
	}
	return false;
}

function commons_search_term_in_page_categories( $page_title, $search_term ) {
	if( VERBOSE ) {
		echo "# Cesarelombrosing [[$page_title]] looking for '$search_term'\n";
	}
	$categories = mw\APIRequest::factory('https://commons.wikimedia.org/w/api.php', [
		'action' => 'query',
		'prop'   => 'categories',
		'clshow' => '!hidden',
		'titles' => $page_title
	] )->fetch();
	foreach( $categories->query->pages as $page ) {
		foreach( $page->categories as $category ) {
			$title = strtolower( $category->title );
			if( false !== strpos( $title, $search_term ) ) {
				if( VERBOSE ) {
					echo "# Matches\n";
				}
				return true;
			}
		}
	}
	if( VERBOSE ) {
		echo "# Doesn't match\n";
	}
	return false;
}

function commons_page_has_categories( $page_title, $categories = [] ) {
	if( VERBOSE ) {
		echo "# Does [[$page_title]] have these categories? ([[{$categories[0]}]], ...)\n";
	}
	$results = commons_page_props( $page_title, 'categories', [
		'clcategories' => implode( '|', $categories )
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
	if( VERBOSE ) {
		echo "# Does [[$page_title]] have these templates? ([[{$templates[0]}]], ...)\n";
	}
	$results = commons_page_props( $page_title, 'templates', [
		'tlnamespace' => 10,
		'tltemplates' => implode( '|', $templates )
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
	$args = array_merge( [
		'action' => 'query',
		'prop'   => $prop,
		'titles' => $page
	], $args );

	$api = mw\APIRequest::factory( 'https://commons.wikimedia.org/w/api.php', $args );
	$pages = [];
	while( $api->hasNext() ) {
		// TODO: callback to obtain the right object?
		$pages[] = $api->getNext();
	}
	return $pages;
}

##################
# Wikidata queries
##################

function search_disperately_wikidata_item( $title ) {
	echo "# Searching Wikidata item as '$title'\n";
	$wbsearch = mw\APIRequest::factory( 'https://www.wikidata.org/w/api.php', [
		'action'      => 'query',
		'list'        => 'wbsearch',
		'wbssearch'   => $title,
		'wbslanguage' => 'en'
	] )->fetch();
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
function filter_volleyball_wikidata_IDs( $wikidata_IDs ) {
	if( ! $wikidata_IDs ) {
		return [];
	}

	if( VERBOSE ) {
		echo "# Cesarelombrosing following volleyball players:\n";
		foreach( $wikidata_IDs as $wikidata_ID ) {
			echo "# \t https://www.wikidata.org/wiki/$wikidata_ID \n";
		}
	}

	$SEARCH_TERMS = [
		'de' => 'volleyball',
		'en' => 'volleyball',
		'it' => 'pallavol' // pallavolo | pallavolista
	];

	$languages = array_keys( $SEARCH_TERMS );

	// https://www.wikidata.org/w/api.php?action=wbgetentities&props=descriptions&ids=Q19675&languages=en|it
	$entities = mw\APIRequest::factory( 'https://www.wikidata.org/w/api.php', [
		'action'    => 'wbgetentities',
		'props'     => 'descriptions|claims',
		'ids'       => implode( '|', $wikidata_IDs ),
		'languages' => implode( '|', $languages    )
	] );
	$matching_wikidata_IDs = [];
	while( $entities->hasNext() ) {
		$entity = $entities->getNext();
		foreach( $entity->entities as $entity_ID => $entity ) {
			$entity_object = wb\DataModel::createFromObject( $entity );
			foreach( $wikidata_IDs as $wikidata_ID ) {
				if( $wikidata_ID === $entity_ID ) {

					// Find LegaVolley ID
					if( $entity_object->hasClaimsInProperty('P4303') ) {
						if( VERBOSE ) {
							echo "ID LegaVolley match\n";
						}
						$matching_wikidata_IDs[ $wikidata_ID ] = true;
						break;
					}

					// Find "volleyball" in description
					foreach( $SEARCH_TERMS as $language => $term ) {
						if( isset( $entity->descriptions->{ $language } ) ) {
							$label = $entity->descriptions->{ $language };
							if( false !== strpos( $label->value, $term ) ) {
								if( VERBOSE ) {
									echo "Wikidata $language label match\n";
								}
								$matching_wikidata_IDs[ $wikidata_ID ] = true;
								break;
							}
						}
					}

					// Find an image like "Foo (Legavolley 2017).jpg"
					$images = $entity_object->getClaimsInProperty('P18');
					foreach( $images as $image ) {
						$image_value = $image->getMainsnak()->getDataValue()->getValue();
						if( false !== strpos( $image_value, 'Legavolley' ) ) {
							if( VERBOSE ) {
								echo "Image name with 'Legavolley' match\n";
							}
							$matching_wikidata_IDs[ $wikidata_ID ] = true;
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
		foreach($matching_wikidata_IDs as $matching_wikidata_ID) {
			echo "# https://www.wikidata.org/wiki/$wikidata_ID \n";
		}
		if( ! $matching_wikidata_IDs ) {
			echo "# None :(\n";
		}
	}
	return $matching_wikidata_IDs;
}

function fetch_wikidata_item( $page_name ) {
	// https://commons.wikimedia.org/w/api.php?action=query&prop=wbentityusage&titles=Category:Alberto+Casadei
	$wbentityusage = mw\APIRequest::factory( 'https://commons.wikimedia.org/w/api.php', [
		'action' => 'query',
		'prop'   => 'wbentityusage',
		'titles' => $page_name
	] )->fetch();

	// Page not found
	if( isset( $wbentityusage->query->pages->{-1} ) ) {
		return false;
	}

	foreach( $wbentityusage->query->pages as $page_id => $page ) {
		if( isset( $page->wbentityusage ) ) {
			foreach( $page->wbentityusage as $wikidata_id => $aspects ) {
				// Just the first is enough
				return $wikidata_id;
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

function legavolley_wikidata_statement_item( $property, $item ) {
	$statement = new wb\StatementItem( $property, $item );
	return $statement->setReferences( legavolley_references() );
}

function legavolley_wikidata_statement_property_string( $property, $string ) {
	$statement = new wb\StatementString( $property, $string );
	return $statement->setReferences( legavolley_references() );
}

function legavolley_wikidata_statement_property_commonsmedia( $property, $filename ) {
	$statement = new wb\StatementCommonsMedia( $property, $filename );
	return $statement->setReferences( legavolley_references() );
}

function legavolley_references() {
	$snaks = new wb\Snaks( [
		// stated in: Lega Pallavolo Serie A
		new wb\SnakItem( 'P248', 'Q16571730' )
	] );
	return [ [ 'snaks' => $snaks->getAll() ] ];
}

###########
# CSV stuff
###########

class Nat {
	var $wd;
	var $it;
	var $en;
	var $cat;
	var $better_cat;

	function __construct( $wikidata_id, $it, $en, $cat, $better_cat ) {
		$this->wd         = $wikidata_id;
		$this->it         = $it;
		$this->en         = $en;
		$this->cat        = $cat;
		$this->better_cat = $better_cat;
	}
}

############
# Mixed shit
############

function read( $default = '' ) {
	$v = chop( fgets(STDIN) );
	return $v ? $v : $default;
}

function interaction_warning() {
	echo "# Skipping: it requires interaction\n";
}

function commons_page_url($page) {
	return 'https://commons.wikimedia.org/wiki/' . urlencode( str_replace(' ', '_', $page) );
}

function debug_yes_no($v) {
	if( VERBOSE ) {
		$yesno = $v ? "yes" : "no";
		echo "# $yesno\n";
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

	$in = (int) read();

	return $in <= 0 ? false : $answers[ $in - 1 ];
}

function wiki_save( $api, $csrf, $title, $content, $summary ) {
	echo "########### Saving [[$title]]: ##########\n";
	echo $content;
	echo "\n";
	echo "#########################################\n";
	echo "Confirm: |$summary|";
	read();
	return $api->post( [
		'action'   => 'edit',
		'title'    => $title,
		'summary'  => $summary,
		'text'     => $content,
		'token'    => $csrf,
		'bot'      => ''
	] );
}

function commons_save( $title, $content, $summary ) {
	return wiki_save(
		$GLOBALS['commons_api'],
		$GLOBALS['COMMONS_CSRF_TOKEN'],
		$title,
		$content,
		$summary
	);
}

function space2regex( $s ) {
	return str_replace( ' ', '[_ ]+', escape_regex( $s ) );
}

function escape_regex( $s ) {
	return str_replace( '/', '\/', preg_quote( $s ) );
}
