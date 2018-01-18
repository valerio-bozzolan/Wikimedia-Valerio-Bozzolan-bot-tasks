<?php
# Copyright (C) 2018 Valerio Bozzolan
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU Affero General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program. If not, see <http://www.gnu.org/licenses/>.

/*
 * Hervist templates - another PHP version of Harvest templates
 *
 * It can both import stuff in Wikidata and delete the corresponding data
 * from the MediaWiki site, once imported.
 *
 * Promo: Now with more than 100% of vegan PHP spaghetti code!
 */

// Autoload boz-mw classes
require '../includes/boz-mw/autoload.php';

// Wikimedia credentials
require '../config.php';

// Local functions
require 'functions.php';

// Local classes
require 'classes.php';

// Wikidata summary
$WIKIDATA_SUMMARY_PREFIX = 'request for permission example: ';

// Site
$SITE_UID = 'itwiki';
$site = \wm\WikipediaIt::getInstance();
$api  = $site->getApi();

// Site template
$TEMPLATE = 'Divisione amministrativa';

// References used by statements
$REFERENCES = [ [
	'snaks' => wb\Snaks::factory( [
		// imported from: Italian Wikipedia
		new wb\SnakItem( 'P143', 'Q11920' )
	] )->getAll()
] ];

// Category associated to the statement that can be added
$PARAMS = new Params ( [
	new Param(
		'Codice ISO',
		'P300',
		'Codice ISO 3166-2 uguale a Wikidata',
		'Codice ISO 3166-2 assente su Wikidata',
		function ( $value, $self ) use ( $REFERENCES ) {
			return \wb\StatementString::factory( $self->getProperty(), $value )
			->setReferences( $REFERENCES );
		}
	),
	new Param(
		'Codice catastale',
		'P806',
		'Codice catastale italiano uguale a Wikidata',
		'Codice catastale italiano assente su Wikidata',
		function ( $value, $self ) use ( $REFERENCES ) {
			return \wb\StatementString::factory( $self->getProperty(), $value )
			->setReferences( $REFERENCES );
		}
	),
	new Param(
		'Prefisso',
		'P473',
		'Prefisso telefonico locale uguale a Wikidata',
		'Prefisso telefonico locale assente su Wikidata',
		function ( $value, $self ) use ( $REFERENCES ) {
			return \wb\StatementString::factory( $self->getProperty(), $value )
			->setReferences( $REFERENCES );
		}
	),
	new Param(
		'Superficie',
		'P2046',
		'Superficie uguale a Wikidata',
		'Superficie assente su Wikidata',
		function ( $value, $self ) use ( $REFERENCES ) {
			return \wb\StatementQuantity::factory( $self->getProperty(), $value, 'Q712226' ) // Squared kilometer
				->setReferences( $REFERENCES );
		}
	),
	new Param(
		'Targa',
		'P395',
		'Codice immatricolazione uguale a Wikidata',
		'Codice immatricolazione assente su Wikidata',
		function ( $value, $self ) use ( $REFERENCES ) {
			return \wb\StatementString::factory( $self->getProperty(), $value )
				->setReferences( $REFERENCES );
		}
	),
	new Param(
		'Altitudine',
		'P2044',
		'Altezza sul mare uguale a Wikidata',
		'Altezza sul mare assente su Wikidata',
		function ( $value, $self ) use ( $REFERENCES ) {
			return \wb\StatementQuantity::factory( $self->getProperty(), $value, 'Q11573' ) // Meter
				->setReferences( $REFERENCES );
		}
	)
	/*	new Param(
		'Codice postale',
		'P281',
		'Codice postale uguale a Wikidata',
		'Codice postale assente su Wikidata',
		function ( $value, $self ) use ( $REFERENCES ) {
			return \wb\StatementString::factory( $self->getProperty(), $value )
				->setReferences( $REFERENCES );
		}
	),*/
] );

foreach( $PARAMS->getAll() as $param_object ) {

	$category_same_on_wikidata    = $param_object->getCategorySameOnWikidata();
	$category_missing_on_wikidata = $param_object->getCategoryMissingOnWikidata();

	// Import local value from local MediaWiki to Wikidata
	$continue = null;
	do {
		echo "Pages {{" . $TEMPLATE . "}} with $category_missing_on_wikidata\n";
		$result = $api->fetch( [
			'action'      => 'query',
			'list'        => 'search',
			'srnamespace' => 0,
			'srsearch'    => "hastemplate:\"$TEMPLATE\" incategory:\"$category_missing_on_wikidata\"",
			'continue'    => $continue
		] );
		foreach( $result->query->search as $page ) {
			$title = $page->title;
			$wikitext = fetch_wikitext     ( $site, $title );
			$data     = fetch_wikidata_data( $site, $title );
			$new_data = new \wb\DataModel();
			$properties_added = [];
			echo "[[$title]]\n";
			if( $wikitext && $data ) {
				$has_categories = page_has_categories( $site, $title, $PARAMS->getCategoriesMissingOnWikidata() );
				foreach( $has_categories as $category ) {
					$current_param_object = $PARAMS->getFromCategoryMissingOnWikidata( $category );
					$current_param_property = $current_param_object->getProperty();
					$current_param_name     = $current_param_object->getParam();
					if( ! $data->hasClaimsInProperty( $current_param_property ) ) {
						$current_param_pattern = $current_param_object->getParamPattern('value');
						if( 1 === $wikitext->pregMatch( $current_param_pattern, $matches ) ) {
							$value = $matches['value'];
							echo "$current_param_name = $value\n";
							$statement = $current_param_object->getStatementFromValue( $value );
							if( $statement ) {
								$new_data->addClaim( $statement );
								$properties_added[] = $current_param_property;
							}
						}
					}
				}
			}
			if( $properties_added ) {
				$summary = $WIKIDATA_SUMMARY_PREFIX . "imported";
				foreach( $properties_added as $property_added ) {
					$summary .= " [[P:$property_added]]";
				}
				$summary .= " from itwiki";
				wikidata_save_existing_from_title( $site->getUID(), $title, $new_data, $summary );
			}
		}
		$continue = $result->continue;
	} while( $continue );
	// \Import local value from local MediaWiki to Wikidata

	// Remove duplicated value from local MediaWiki
	$continue = null;
	do {
		$result = $api->fetch( [
			'action'      => 'query',
			'list'        => 'search',
			'srnamespace' => 0,
			'srsearch'    => "hastemplate:\"$TEMPLATE\" incategory:\"$category_same_on_wikidata\"",
			'continue'    => $continue
		] );
		foreach( $result->query->search as $page ) {
			$title = $page->title;
			echo "$title\n";
			$wikitext = fetch_wikitext( $site, $title );
			if( $wikitext ) {
				$has_categories = page_has_categories( $site, $title, $PARAMS->getCategoriesSameOnWikidata() );
				$same_params = [];
				foreach( $has_categories as $category ) {
					$param_object = $PARAMS->getFromCategorySameOnWikidata( $category );
					$param_pattern        = $param_object->getParamPattern( null, 'end' );
					$param_end_group_name = \regex\Generic::groupName('end');
					$wikitext->pregReplace( $param_pattern, $param_end_group_name, 1, $count );
					if( $count ) {
						$same_params[] = $param;
					}
				}
				if( $same_params ) {
					$summary  = "[[T:$TEMPLATE]]:";
					$summary .= " ";
					$summary .= count( $same_params ) === 1 ? 'parametro' : 'parametri';
					$summary .= ": ";
					$summary .= "'" . implode("', '", $same_params ) . "'";
					$summary .= " ";
					$summary .= count( $same_params ) === 1 ? 'uguale' : 'uguali';
					$summary .= ' a Wikidata';
					echo $summary;
					wiki_save( $site, $title, $wikitext, $summary );
				}
			}
		}
		$continue = $result->continue;
	} while( $continue );
	// \Remove duplicated value from local MediaWiki
}
