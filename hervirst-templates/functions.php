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

/**
 * Get the wikitext from a page title.
 *
 * @param $site \mw\Site MediaWiki site
 * @param $page string Page title with namespace prefix
 * @return \mw\Wikitext Page wikitext
 */
function fetch_wikitext( \mw\Site $site, $page ) {
	// Wikitext
	// https://it.wikipedia.org/w/api.php?action=query&prop=revisions&titles=linux+%28kernel%29&rvprop=content
	$pages = $site->fetch( [
		'action' => 'query',
		'prop'   => 'revisions',
		'titles' => $page,
		'rvprop' => 'content'
	] );
	foreach( $pages->query->pages as $page ) {
		if( isset( $page->revisions ) ) {
			return $site->createWikitext(
				$page->revisions[0]->{'*'}
			);
		}
	}
	return false;
}

/**
 * Fetch a Wikidata data from a page_title related to a sitelink
 *
 * @param $site \mw\Site MediaWiki site
 * @param $title string Page title
 * @return \wb\DataModel Wikidata data
 */
function fetch_wikidata_data( \mw\Site $site, $title ) {
	$result = \wm\Wikidata::getInstance()->fetch( [
		'action' => 'wbgetentities',
		'sites'  => $site->getUID(),
		'titles' => $title,
		'props'  => 'claims'
	] );
	foreach( $result->entities as $entity ) {
		return \wb\DataModel::createFromObject( $entity );
	}
	throw new Exception('missing entity');
}

/**
 * Given some unprefixed categories, it returns which of them are into a page.
 *
 * @param $site \mw\Site MediaWiki site
 * @param $page string Page title
 * @param $categories array Categories unprefixed
 */
function page_has_categories( \mw\Site $site, $page, $categories ) {
	$prefix = $site->getNamespace(14)->getName();
	$prefixed_categories = [];
	foreach( $categories as $category ) {
		$prefixed_categories[] = "$prefix:$category";
	}
	$founds = [];
	$result = $site->fetch( [
		'action' => 'query',
		'prop'   => 'categories',
		'titles' => $page,
		'clcategories' => $prefixed_categories
	] );
	foreach( $result->query->pages as $page ) {
		if( isset( $page->categories ) ) {
			foreach( $page->categories as $category ) {
				$k = array_search( $category->title, $prefixed_categories, true );
				if( false !== $k ) {
					$founds[] = $categories[ $k ];
				}
			}
		}
	}
	return $founds;
}

/**
 * Save a page in a MediaWiki site
 *
 * @param $site \mw\Site MediaWiki site
 * @param $title string Page title to be saved
 * @param $wikitext \mw\Wikitext Wikitext to be saved
 * @param $summary string Summary
 */
function wiki_save( \mw\Site $site, $title, \mw\Wikitext $wikitext, $summary ) {
	static $csrf;
	if( ! $csrf ) {
		$csrf = fetch_site_csrf( $site );
	}
	try {
		return wiki_save_csrf(
			$site,
			$title,
			$wikitext,
			$summary,
			$csrf
		);
	} catch( \mw\API\BadTokenException $e ) {
			return wiki_save( $site, $title, $wikitext, $summary );
	}
}

/**
 * Save an existing Wikidata entity from its title on a certain site.
 *
 * @param $site_uid string Site UID e.g. 'enwiki'
 * @param $tite string Page title on the specified site
 * @param $data \wb\DataModel New data to be saved
 * @param $summary string Summary
 */
function wikidata_save_existing_from_title( $site_uid, $title, \wb\DataModel $data, $summary ) {
	static $csrf;
	if( ! $csrf ) {
		$csrf = fetch_site_csrf( \wm\Wikidata::getInstance() );
	}
	try {
		return wikidata_save_existing_from_title_using_csrf( $site_uid, $title, $data, $summary, $csrf );
	} catch( \mw\API\BadTokenException $e ) {
		return wikidata_save_existing_entity( $site_uid, $title, $data, $summary );
	}
}

/**
 * Save a page.
 *
 * @param $wiki \mw\Site MediaWiki site
 * @param $title string Page title to be created
 * @param $wikitext \mw\Wikitext Page title to be created
 * @param $summary string Summary
 * @param $csrf string CSRF token
 */
function wiki_save_csrf( \mw\Site $site, $title, \mw\Wikitext $wikitext, $summary, $csrf ) {
	static $read = false;
	echo "\n";
	foreach( explode( "\n", $wikitext->getPrepended() ) as $line ) {
		if( $line ) {
			echo "+$line\n";
		}
	}
	echo "\n";
	echo "#########################################\n";
	echo "Confirm summary: |$summary|\n";
	echo "#########################################\n";
	foreach( $wikitext->getSobstitutions() as $sobstitution ) {
		list( $a, $b ) = $sobstitution;
		foreach( explode( "\n", $a ) as $line ) {
			if( ! empty( $line ) ) {
				echo "-$line\n";
			}
		}
		foreach( explode( "\n", $b ) as $line ) {
			if( ! empty( $line ) ) {
				echo "+$line\n";
			}
		}
		echo "\n";
	}
	echo "\n";
	foreach( explode( "\n", $wikitext->getAppended() ) as $line ) {
		if( $line ) {
			echo "+$line\n";
		}
	}
	echo "########### Saving [[$title]]: ##########\n";
	read();
	return $site->post( [
		'action'   => 'edit',
		'title'    => $title,
		'summary'  => $summary,
		'text'     => $wikitext->getWikitext(),
		'token'    => $csrf,
		'bot'      => ''
	] );
}

/**
 * Save an existing Wikidata entity from its title on a certain site.
 *
 * @param $site_uid string Site UID e.g. 'enwiki'
 * @param $tite string Page title on the specified site
 * @param $data \wb\DataModel New data to be saved
 * @param $summary string Save summary
 * @param $csrf string CSRF token
 */
function wikidata_save_existing_from_title_using_csrf( $site_uid, $title, \wb\DataModel $data, $summary, $csrf ) {
		echo "#########################################\n";
		echo "\n";
		echo $data->getJSON( JSON_PRETTY_PRINT );
		echo "\n";
		echo "#########################################\n";
		echo "$site_uid.$title\n";
		echo "Confirm summary: |$summary|\n";
		echo "#########################################\n";
		read();
		// https://www.wikidata.org/w/api.php?action=help&modules=wbeditentity
		$result = \wm\Wikidata::getInstance()->post( [
			'action'  => 'wbeditentity',
			'site'    => $site_uid,
			'title'   => $title,
			'data'    => $data->getJSON(),
			'summary' => $summary,
			'token'   => $csrf,
			'bot'     => ''
		] );
		return $result;
}

/**
 * Site CSRF
 *
 * @param $site \mw\Site MediaWiki site
 * @return string
 */
function fetch_site_csrf( \mw\Site $site ) {
	return $site->login()->fetch( [
		'action' => 'query',
		'meta'   => 'tokens',
		'type'   => 'csrf'
	] )->query->tokens->csrftoken;
}

/**
 * Wait for an input
 *
 * @param $default Default string to be returned if nothing is chopped.
 * @return string Chopped string, or $default.
 */
function read( $default = '' ) {
	$v = chop( fgets(STDIN) );
	return $v ? $v : $default;
}
