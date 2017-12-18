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
 * Initially, this bot uniformed volleyball 2017 players' descriptions adding
 * the (new) template {{Depicted person}} and connecting them to Wikidata when
 * possible; the personal category was created if missing and added into the
 * "best fit" national category; all of this in less edit as possible.
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
 * category (P373).
 */

#################
# Framework stuff
#################
require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/boz-mw/autoload.php';

##############################
# Start of spaghetti constants
##############################

defined( 'SANDBOXED' ) or
define( 'SANDBOXED', true );

defined( 'WIKIDATA_SANDBOX' ) or
define(  'WIKIDATA_SANDBOX', 'Q4115189' );

defined( 'CONSENSUS_PAGE' ) or
define(  'CONSENSUS_PAGE', 'c:Commons:Bots/Requests/Valerio Bozzolan bot' )

defined( 'SUMMARY' ) or
define( 'SUMMARY', sprintf(
	"[[%s|uniforming Legavolley 2017 players]]",
	CONSENSUS_PAGE
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

$wikidata_api = APIRequest::factory('https://www.wikidata.org/w/api.php');
$response = $wikidata_api->fetch( [
	'action' => 'query',
	'meta'   => 'tokens',
	'type'   => 'login'
] );
if( ! isset( $response->query->tokens->logintoken ) ) {
	throw new Exception("can't retrieve login token");
}
$logintoken = $response->query->tokens->logintoken;

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

$response = $wikidata_api->fetch( [
	'action' => 'query',
	'meta'   => 'tokens',
	'type'   => 'csrf'
] );
if( ! isset( $response->query->tokens->csrftoken ) ) {
	throw new Exception("missing csrf");
}
$WIKIDATA_CSRF_TOKEN = $response->query->tokens->csrftoken;

$SCRIPT = 'python ~/pywikibot/pwb.py';

#############
# Nations CSV
#############

$NATIONS = [];
if( ($handle = fopen('commons-volleyball-nationalities.csv', 'r') ) !== false ) {
	while( ($data = fgetcsv($handle, 1000, ';') ) !== false ) {

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

if( ( $handle = fopen('commons-volleyball-players.csv', 'r') ) !== false ) {

	$porcelain = false;

	$i = -1;
	while( ( $data = fgetcsv($handle, 1000, ',')) !== false ) {
		$i++;

		if( $i === 0 ) {
			// Skip header
			continue;
		}

		list($surname, $name, $natcode, $file) = $data;

		if( ! isset( $NATIONS[ $natcode ] ) ) {
			$missing_natcodes[] = $natcode;
			$porcelain = true;
		}

		if( $porcelain ) {
			continue;
		}

		$filepath = "$file.jpg";
		$filename = "File:$filepath";
		if( ! commons_page_exists( $filename ) ) {
			echo "# Skipping [[$filename]] that does not exist\n";
			continue;
		}

		$filename_url = commons_page_url( $filename );

		$complete_name              = "$name $surname";
		$personal_category          = $complete_name;
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
					$personal_category_exists   = commons_category_exists(  $personal_category_prefixed );

					echo "# Confirm?";
					read();
				}
			}
 		}

		$italian_category = "Men's volleyball players from Italy";
		$italian_category_prefixed = "Category:$italian_category";

		$nation = $NATIONS[ $natcode ];
		$national_category = $nation->cat;
		$national_category_prefixed = "Category:$national_category";

		// Wikidata labels
		$LABELS = [
			'en' => sprintf(
				'%s %s, %s volleyball player',
				$name,
				$surname,
				$nation->en
			),
			'it' => sprintf(
				'%s %s, pallavolista %s',
				$name,
				$surname,
				$nation->it
			)
		];

		$better_national_category_exists = isset( $nation->better_cat );
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

		$wikidata_item = get_wikidata_item( $filename, $complete_name );

		if( SANDBOXED ) {
			$wikidata_item = $WIKIDATA_SANDBOX;
		}

		$wikidata_item_new_data = new WikidataDataModel();

		$claims = [];

		if( $wikidata_item ) {
			// Retrieve
			// https://www.wikidata.org/w/api.php?action=help&modules=wbgetentities
			$wikidata_item_data = $wikidata_api->fetch( [
				'action' => 'wbgetentities',
				'ids'    => $wikidata_item,
				'props'  => 'info|sitelinks|aliases|labels|descriptions|claims|datatype'
			] );
			if( ! isset( $wikidata_item_data->entities->{ $wikidata_item } ) ) {
				throw new Exception("$wikidata_item does not exist?");
			}
			$wikidata_item_data = $wikidata_item_data->entities->{ $wikidata_item };

			##
			# Append new labels
			##
			$labels = [];
			foreach( $LABELS as $lang => $label ) {
				if( ! isset( $wikidata_item_data->labels->{ $lang } ) ) {
					$labels[] = $LABELS[ $lang ];
				}
			}
			$wikidata_item_new_data->setLabels( $labels, 'add' );

			// Image
			if( ! isset( $wikidata_item_data->claims->{ 'P18' } ) ) {
				$claims[] = legavolley_wikidata_claim_property_commonsmedia('P18', $filepath);
			}

			// Commons category
			if( ! isset( $wikidata_item_data->claims->{ 'P373' } ) ) {
				$claims[] = legavolley_wikidata_claim_property_string('P373', $personal_category);
			}

			// Sex: male
			if( ! isset( $wikidata_item_data->claims->{ 'P21' } ) ) {
				$claims[] = legavolley_wikidata_claim_property_entity('P21', 'Q6581097');
			}

			// Country of citizenship (P27)
			if( ! isset( $wikidata_item_data->claims->{ 'P27' } ) ) {
				$claims[] = legavolley_wikidata_claim_property_entity('P27', $nation->wd);
			}

			// Occupation: volleyball player
			if( ! isset( $wikidata_item_data->claims->{ 'P106' } ) ) {
				$claims[] = legavolley_wikidata_claim_property_entity('P106', 'Q15117302');
			}

			$wikidata_item_new_data->set( [
				'claims' => claims( $claims )
			] );

			echo json_encode( $wikidata_item_new_data->get(), JSON_PRETTY_PRINT );
			echo "Confirm";
			read();

			// Save existing
			// https://www.wikidata.org/w/api.php?action=help&modules=wbeditentity
			$wikidata_api->post( [
		        	'action'  => 'wbeditentity',
				'id'      => $wikidata_item,
				'summary' => SUMMARY,
				'token'   => $WIKIDATA_CSRF_TOKEN,
				'bot'     => 1,
				'data'    => $wikidata_item_new_data->getJSON()
			] );

			exit;
		} else {
			exit;

			// All labels
			$wikidata_item_new_data->setLabels( $LABELS );

			// Image
			$claims[] = legavolley_wikidata_claim_property_commonsmedia('P18', $filepath);

			// Commons category
			$claims[] = legavolley_wikidata_claim_property_string('P373', $personal_category);

			// Sex: male
			$claims[] = legavolley_wikidata_claim_property_entity('P21', 'Q6581097');

			// Country of citizenship (P27)
			$claims = legavolley_wikidata_claim_property_entity('P27', $nation->wd);

			// Occupation: volleyball player
			$claims = legavolley_wikidata_claim_property_entity('P106', 'Q15117302');

			$wikidata_item_new_data->set( [
				'claims' => $claims
			] );

			// Create
			// https://www.wikidata.org/w/api.php?action=help&modules=wbeditentity
			$wikidata_api->post( [
		        	'action'  => 'wbeditentity',
				'new'     => 'item',
				'summary' => SUMMARY,
				'token'   => $WIKIDATA_CSRF_TOKEN,
				'bot'     => 1,
				'data'    => $wikidata_item_new_data->getJSON()
			] );
		}

		if( ! $file_has_template_depicted || ! $file_has_personal_category ) {
			$summary = SUMMARY;
			$pwb = clone $PWB;
			$pwb->arg('replace.py')
			    ->arg('-regex')
			    ->arg('-lang:commons')
			    ->arg('-family:commons')
			    ->arg("-page:$filename");

			$replace = false;

			if( $wikidata_item ) {
				if( ! $file_has_template_depicted ) {
					$pwb->arg('{{[ ]*[eE]n *\|.+?}}\n?');
					$pwb->arg('');
					$pwb->arg('{{[ ]*[iI]t *\|.+?}}');
					$pwb->arg( sprintf(
						'{{Depicted person|%s}}',
						$wikidata_item
					) );
					$summary .= "; +[[Template:Depicted person]] [[d:$wikidata_item]]";
					$replace = true;
				}
			} elseif( ! $file_has_iten_templates ) {
				// {{It|asd}}
				$pwb->arg( '{{[ ]*[iI]t *\|.+?}}' );
				$pwb->arg( sprintf(
					'{{en|%s}}\n{{it|%s}}',
					$LABELS['en'],
					$LABELS['it']
				) );

				$summary .= "; +[[Template:En]] +[[Template:It]]";
				$replace = true;
			}

			if( $file_has_italian_category ) {
				if( $national_category !== $italian_category ) {
					if( ! $file_has_personal_category ) {
						$pwb->arg( Generic::space2regex( $italian_category_prefixed ) );
						$pwb->arg( $personal_category_prefixed );
						$summary .= "; [[$italian_category_prefixed]] → [[$personal_category_prefixed]]";

						$file_has_italian_category = false;
						$file_has_personal_category = true;
						$replace = true;
					}
				}
				if( $file_has_italian_category ) {
					$pwb->arg( '\[\[ *' . Generic::space2regex( $italian_category_prefixed ) . ' *\]\]\n*' );
					$pwb->arg('');
					$summary .= "; -[[$italian_category_prefixed]]";
					$replace = true;
				}
			}

			if( ! $file_has_personal_category ) {
				$pwb->arg('$');
				$pwb->arg('\n[[' . $personal_category_prefixed . ']]');
				$summary .= "; +[[$personal_category_prefixed]]";
				$replace = true;
			}

			if( ALWAYS ) {
				$pwb->arg('-always');
			}

			if( $replace ) {
				echo $pwb->arg("-summary:$summary")->get();

				if( INTERACTION ) {
					read();
				}
			}
		} else {
			echo "# Skipped...";
		}

		echo "\n\n";

		if( $personal_category_exists ) {
			echo "# [[Category:$personal_category]] [[$personal_category_url]]\n";

			// Decides if skip
			if( $personal_category_has_best_national_category ) {
				if( $personal_category_has_wikidata_template ) {
					echo "# It's perfect yet! Skip.\n";
					continue;
				} elseif( ! $wikidata_item ) {
					$wikidata_item = get_wikidata_item( $filename, $complete_name );
					if( ! INTERACTION && null === $wikidata_item ) {
						interaction_warning();
						continue;
					}

					if( ! $wikidata_item ) {
						echo "# It misses the Wikidata template, but no Wikidata item. Skip.\n";
						continue;
					}
				}
			}

			// Pywikibot replace.py
			$pwb = clone $PWB;
			$pwb->arg('replace.py')
			    ->arg('-regex')
			    ->arg('-lang:commons')
			    ->arg('-family:commons')
			    ->arg("-page:$personal_category_prefixed");

			$summary = SUMMARY;

			if( ! $personal_category_has_wikidata_template ) {
				$wikidata_item = get_wikidata_item( $filename, $complete_name );
				if( ! INTERACTION && null === $wikidata_item ) {
					interaction_warning();
					continue;
				}

				if( $wikidata_item ) {
					$summary .= $wikidata_item ? sprintf("; +[[Template:Wikidata person]] [[d:%s]]", $wikidata_item) : '';

					$pwb->arg('^(?!{{Wikidata person)');
					$pwb->arg( sprintf(
						'{{Wikidata person|%s}}\n',
						$wikidata_item
					) );
				}
			}

			if( ! $personal_category_has_best_national_category ) {

				if( $better_national_category_exists ) {
					if( $personal_category_has_national_category ) {
						// National → Best national
						$pwb->arg( Generic::space2regex( $national_category_prefixed ) );
						$pwb->arg( $better_national_category_prefixed );
						$summary .= "; better national category";
					} else {
						// + Best national
						$pwb->arg('$');
						$pwb->arg('\n[[' . $better_national_category_prefixed . ']]');
						$summary .= "; +[[$better_national_category_prefixed]]";
					}
				} elseif( ! $personal_category_has_national_category ) {
					// + National
					$pwb->arg('$');
					$pwb->arg('\n[[' . $national_category_prefixed . ']]');
					$summary .= "; +[[$national_category_prefixed]]";
				}
			}

			$pwb->arg("-summary:$summary");

			if( ALWAYS ) {
				$pwb->arg('-always');
			}

			echo $pwb->get();

			if( INTERACTION ) {
				read();
			}

		} else {
			// Pywikibot create

			$summary = SUMMARY;

			$wikidata_item = get_wikidata_item( $filename, $complete_name );
			if( ! INTERACTION && null === $wikidata_item ) {
				interaction_warning();
				continue;
			}

			$temp = tmpfile();
			$temp_name = stream_get_meta_data( $temp );
			$temp_name = $temp_name['uri'];

			// https://www.mediawiki.org/wiki/Manual:Pywikibot/pagefromfile.py
			$lines = [];

			$lines[] = '{{-start-}}';

			$lines[] = "'''$personal_category_prefixed'''";

			if( $wikidata_item ) {
				$lines[] = sprintf(
					'{{Wikidata person|%s}}',
					$wikidata_item
				);
				$summary .= "; +[[Template:Wikidata person]] [[d:$wikidata_item]]";
			}

			$lines[] = sprintf(
				'{{DEFAULTSORT:%s, %s}}',
				$surname,
				$name
			);

			if( ! $wikidata_item ) {
				$lines[] = "[[Category:Men by name]]";
				$lines[] = "[[Category:$name (given name)]]";
				$lines[] = "[[Category:$surname (surname)|$name $surname]]";
				$lines[] = "[[Category:Year of birth missing]]";
				$summary .= "; +categories";
			}

			$lines[] = "[[$best_national_category_prefixed]]";
			$summary .= "; +[[$best_national_category_prefixed]]";

			$lines[] = '{{-stop-}}';

			// Yes, I know, but don't fight about it
			$tmp = '/tmp/asd';
			echo "cat > '$tmp' <<EOF_ASD_ASD\n";
			foreach($lines as $line) {
				echo "$line\n";
			}
			echo "EOF_ASD_ASD\n";
			file_put_contents($tmp, implode("\n", $lines) );

			$pwb = clone $PWB;
			$pwb->arg('pagefromfile.py')
			    ->arg('-lang:commons')
			    ->arg('-family:commons')
			    ->arg('-notitle')
			    ->arg('-safe')
			    ->arg("-file:$tmp")
			    ->arg("-summary:$summary");

			echo $pwb->get();

			echo "\n";
			echo "rm '$tmp'\n";

			if( INTERACTION ) {
				read();
			}
		}
	}

	if( ! INTERACTION ) {
		echo "sleep 5\n";
	}

	echo "\n\n\n\n";
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
	$pages_exists = APIRequest::factory('https://commons.wikimedia.org/w/api.php', [
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
	$category_info = APIRequest::factory('https://commons.wikimedia.org/w/api.php', [
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
	$categories = APIRequest::factory('https://commons.wikimedia.org/w/api.php', [
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

	$api = APIRequest::factory( 'https://commons.wikimedia.org/w/api.php', $args );
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
	$wbsearch = APIRequest::factory( 'https://www.wikidata.org/w/api.php', [
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
	$entities = APIRequest::factory( 'https://www.wikidata.org/w/api.php', [
		'action'    => 'wbgetentities',
		'props'     => 'descriptions',
		'ids'       => implode( '|', $wikidata_IDs ),
		'languages' => implode( '|', $languages    )
	] );
	$matching_wikidata_IDs = [];
	while( $entities->hasNext() ) {
		$entity = $entities->getNext();
		foreach( $entity->entities as $entity_ID => $entity ) {
			foreach( $wikidata_IDs as $wikidata_ID ) {
				if( $wikidata_ID === $entity_ID ) {
					foreach( $SEARCH_TERMS as $language => $term ) {
						if( isset( $entity->descriptions->{ $language } ) ) {
							$label = $entity->descriptions->{ $language };
							if( false !== strpos( $label->value, $term ) ) {
								if( VERBOSE ) {
									echo "# Wikidata $language label match\n";
								}
								$matching_wikidata_IDs[ $wikidata_ID ] = true;
								break;
							}
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
	$wbentityusage = APIRequest::factory( 'https://commons.wikimedia.org/w/api.php', [
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

function legavolley_wikidata_claim_property_snak( $property, $snak ) {
	return wikidata_claim_property_snak( $property, $snak, legavolley_references() );
}

function legavolley_wikidata_claim_property_entity( $property, $entity ) {
	return wikidata_claim_property_entity( $property, $entity, legavolley_references() );
}

function legavolley_wikidata_claim_property_string( $property, $string ) {
	return wikidata_claim_property_string( $property, $value, legavolley_references() );
}

function legavolley_wikidata_claim_property_commonsmedia( $property, $filename ) {
	return wikidata_claim_property_commonsmedia( $property, $filename, legavolley_references() );
}

function legavolley_references() {
	return [ [ 'snaks' => snaks( [
		// stated in: Lega Pallavolo Serie A
		snak_item( 'P248', 'Q16571730' )
	] ) ] ];
}

###################################
# Claims (they are formed by snaks)
###################################

function wikidata_claim_property_snak( $property, $snak, $references = [] ) {
	$claim = [
		'mainsnak' => $snak,
		'type'     => 'statement',
		'rank'     => 'normal'
	];
	if( $references ) {
		$claim['references'] = $references;
	}
	return $claim;
}

function wikidata_claim_property_entity( $property, $entity, $references = [] ) {
	return wikidata_claim_property_snak(
		$property,
		snak_item( $property, $entity ),
		$references
	);
}

function wikidata_claim_property_commonsmedia( $property, $filename, $references = [] ) {
	return wikidata_claim_property_snak(
		$property,
		snak_commonmedia( $property, $filename ),
		$references
	);
}

###############################
# Snaks (they have a datavalue)
###############################

function snak( $snaktype, $property, $datatype, $datavalue ) {
	return [
		'snaktype'  => $snaktype,
		'property'  => $property,
		'datatype'  => $datatype,
		'datavalue' => $datavalue
	];
}

function snak_commonmedia( $property, $filename ) {
	return snak(
		'value',
		$property,
		'commonsMedia',
		datavalue_string( $filename )
	);
}

function snak_item( $property, $item ) {
	return snak(
		'value',
		$property,
		'wikibase-item',
		datavalue_item( $item )
	);
}

###############################
# Datavalues (snak's datavalue)
###############################

function datavalue( $type, $value ) {
	return [
		'type'  => $type,
		'value' => $value
	];
}

function datavalue_string( $value ) {
	return datavalue( 'string', $value );
}

function datavalue_item( $entity ) {
	$entity_numeric = (int) substr( $entity, 1 );
	return datavalue( 'wikibase-entityid', [
		'entity-type' => 'item',
		'numeric-id'  => $entity_numeric,
		'id'          => $entity
	] );
}

#####################################################################
# Property aggregators (snaks and claims must be property-associated)
#####################################################################

function snaks( $snaks ) {
	$properties = [];
	foreach( $snaks as $snak ) {
		$property = $snak['property'];
		if( ! isset( $properties[ $property ] ) ) {
			$properties[ $property ] = [];
		}
		$properties[ $property ][] = $snak;
	}
	return $properties;
}

function claims( $claims = [] ) {
	$properties = [];
	foreach( $claims as $claim ) {
		$property = $claim['mainsnak']['property'];
		if( ! isset( $properties[ $property ] ) ) {
			$properties[ $property ] = [];
		}
		$properties[ $property ][] = $claim;
	}
	return $properties;
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
