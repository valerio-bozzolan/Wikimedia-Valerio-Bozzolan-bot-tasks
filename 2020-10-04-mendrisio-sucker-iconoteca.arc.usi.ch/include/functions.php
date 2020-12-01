<?php
# Copyright (C) 2020 Valerio Bozzolan
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU Affero General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
# GNU Affero General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program. If not, see <http://www.gnu.org/licenses/>.

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

/**
 * Print a message
 */
function message( $message ) {
	printf(
		"[%s] %s\n",
		date( 'Y-m-d H:i:s' ),
		$message
	);
}

/**
 * Covert an HTML link to a wikitext one
 */
function html_link_2_wikitext( $txt ) {

	return preg_replace_callback( '@<a href="(.+?)">(.+?)</a>@', function( $matches ) {

		// eventually make the URL absolute
		$url = $matches[1];
		if( $url[0] === '/' ) {
			$url = BASE_URL . $url;
		}

		return sprintf(
			'[%s %s]',
			$url,
			$matches[2]
		);
	}, $txt );

	// <a href="/it/ricerca?isPostBack=1&amp;soggetto=Sculture">Sculture</a>
}

/**
 * Require a certain page from the template directory
 *
 * It will eventually echo something.
 *
 * @param $name string page name
 * @param $args mixed arguments to be passed to the page scope
 */
function template( $template, $template_args = [] ) {
	extract( $template_args, EXTR_SKIP );
	return require $template;
}

/**
 * Get the template output
 *
 * It will echo nothing.
 *
 * @param $name string page name (to be sanitized)
 * @param $args mixed arguments to be passed to the page scope
 * @see template()
 * @return string The template output
 */
function template_content( $name, $args = [] ) {
	ob_start();
	template( $name, $args );
	$text = ob_get_contents();
	ob_end_clean();
	return $text;
}

/**
 * Generator of name variants
 *
 * @param string $name
 * @return string
 */
function generator_name_variants( $name ) {

	// "Anderson, Domenico" → "Domenico Anderson"
	$parts = explode( ", ", $name );
	if( count( $parts ) === 2 ) {
		yield "{$parts[1]} {$parts[0]}";
		yield "{$parts[0]} {$parts[1]}";
	} else {
		yield $name;
	}
}

/**
 * Generate the first name variant
 */
function first_name_variant( $name ) {
	foreach( generator_name_variants( $name ) as $variant ) {
		return $variant;
	}
	return $name;
}

/**
 * Search an Author in Wikidata
 *
 * @param string $name
 * @return string Q-ID or NULL
 */
function search_author_in_wikidata( $name ) {

	// terms that can be found in a description to classify someone
	$TERMS = [
		'photographer',
		'fotograf',
	];

	$all = [
		'good'       => [],
		'undetected' => [],
	];

	// generate a list of possible query terms
	foreach( generator_name_variants( $name ) as $variant ) {

		// query Wikidata results using wbsearcentity API
		$candidates = find_wikidata_entity_by_title( $variant );
		foreach( $candidates as $id => $description ) {

			// check if this Wikidata result matches one of the well-known terms
			$found = is_term_found( $TERMS, $description );

			// index in the right namespace
			$key = $found ? 'good' : 'undetected';
			$all[ $key ][ $id ] = $description;
		}

		// if something good was found, quit earlier with the first ID. otherwise continue
		if( count( $all[ 'good' ] ) === 1 ) {
			return array_keys( $all[ 'good' ] )[0];
		}
	}

	// check if something was undetected
	if( $all[ 'undetected' ] ) {
		print_r( $all );
		throw new Exception( "undetected. TODO: pick" );
	}

	if( $all[ 'good' ] ) {
		print_r( $all );
		throw new Exception( "detected multiple good possibilities. TODO: pick" );
	}

	// nothing found
	return null;
}

/**
 * Search a Creator in Commons
 *
 * @param string $name
 * @return string Q-ID or NULL
 */
function search_creator_on_commons( $name ) {

	$wiki = \wm\Commons::instance();

	// generate a list of possible query terms
	foreach( generator_name_variants( $name ) as $variant ) {

		$title = "Creator:$variant";
		if( wiki_page_id( $wiki, $title ) ) {
			return $title;
		}
	}

	return false;
}


/**
 * Search something in Wikidata
 *
 * @param string $search
 * @return array Associative array of ID and its description
 */
function find_wikidata_entity_by_title( $search ) {

	$founds = [];

	$wikidata = \wm\Wikidata::instance();

	// https://www.wikidata.org/w/api.php?action=help&modules=wbsearchentities
	$queries =
		$wikidata->createQuery( [
			'action'   => 'wbsearchentities',
			'search'   => $search,
			'language' => 'it',
			'type'     => 'item',
		] );

	// loop queries
	foreach( $queries as $query ) {

		$results = $query->search ?? [];
		foreach( $results as $result ) {

			$id = $result->id;

			$description = $result->description;

			// store as ID => description
			$founds[ $id ] = $description;
		}
	}

	return $founds;
}

/**
 * Test an array of terms and return true if one is found in the subject
 *
 * @param array $terms
 * @param string $subject
 * @return bool
 */
function is_term_found( $terms, $subject ) {

	// for each term
	foreach( $terms as $term ) {

		// check if the term is part of the subject
		if( strpos( $subject, $term ) !== false ) {

			// gotcha!
			return true;
		}
	}

	return false;
}

/**
 * Check if a page exists
 *
 * @return int|false
 */
function wiki_page_id( $wiki, $title ) {

	$result =
		$wiki->fetch( [
			'action' => 'query',
			'prop'   => 'info',
			'titles' => $title,
		] );

	$pages = $result->query->pages ?? [];
	foreach( $pages as $page ) {

		// no page no party
		if( isset( $page->missing ) ) {
			return false;
		}

		// that's OK
		return $page->pageid;
	}

	// no page no party
	throw new Exception( "what" );
}

/**
 * Parse "27x20 cm" and return a {{Size}} template if possible
 *
 * @return string
 */
function parse_size( $size ) {

	$found = preg_match( '/^ *([0-9]+)x([0-9]+) *([a-zA-Z]+) *$/', $size, $matches );

	if( $found ) {
		$w    = $matches[ 1 ];
		$h    = $matches[ 2 ];
		$unit = $matches[ 3 ];

		return "{{Size|unit = $unit |width = $w |height = $h}}";
	}

	return $size;
}
