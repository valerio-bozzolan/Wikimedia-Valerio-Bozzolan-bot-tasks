<?php
# Copyright (C) 2017 Valerio Bozzolan
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
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
 * Save a page.
 *
 * @param $wiki mw\APIRequest or mw\Site
 * @param $csrf The CSRF token
 * @param $title Page title to be created
 * @param $summary Summary
 */
function wiki_save( $wiki, $csrf, $title, $content, $summary ) {
	echo "\n";
	echo "########### Saving [[$title]]: ##########\n";
	echo $content;
	echo "\n";
	echo "#########################################\n";
	echo "Confirm summary: |$summary|\n";
	read();
	return $wiki->post( [
		'action'   => 'edit',
		'title'    => $title,
		'summary'  => $summary,
		'text'     => $content,
		'token'    => $csrf,
		'bot'      => ''
	] );
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

/**
 * Save a page in Wikimedia Commons.
 *
 * @param $title Page title to be saved
 * @param $content Wikitext to be saved
 * @param $summary Summary
 */
function commons_save( $title, $content, $summary ) {
	return wiki_save(
		\wm\Commons::getInstance(),
		$GLOBALS['COMMONS_CSRF_TOKEN'],
		$title,
		$content,
		$summary
	);
}

/**
 * Get a mw\Wikitext object from a page title.
 *
 * @param string $page Page title with namespace prefix
 * @return mw\Wikitext
 */
function commons_wikitext( $page ) {
	// Wikitext
	// https://it.wikipedia.org/w/api.php?action=query&prop=revisions&titles=linux+%28kernel%29&rvprop=content
	$pages = \wm\Commons::getInstance()->fetch( [
		'action' => 'query',
		'prop'   => 'revisions',
		'titles' => $page,
		'rvprop' => 'content'
	] );
	foreach( $pages->query->pages as $page ) {
		return Commons::getInstance()->createWikitext(
			$page->revisions[0]->{'*'}
		);
	}
	throw new Exception("missing page $page");
}

/**
 * Get a Nation object from a string like "italiani".
 *
 * @param string $it_people A string like "italiani"
 * @return Nation
 */
function nation_from_it_people( $it_people ) {
	foreach( $GLOBALS['NATIONS'] as $nation ) {
		if( $nation->itpeople === $it_people ) {
			return $nation;
		}
	}
	throw new Exception("missing it people $it_people");
}

/**
 * Discover if a page exists on Wikimedia Commons.
 *
 * @param $page Page title with namespace prefix
 * @return bool
 */
function commons_page_exists( $page ) {
		$response = \wm\Commons::getInstance()->fetch( [
			'action' => 'query',
			'prop'   => 'info',
			'titles' => $page
		] );
		return ! isset( $response->query->pages->{-1} );
}

/**
 * Add a category into a mw\Wikitext object, only if it's not added yet.
 * If the category is added, a summary is appended.
 *
 * @param $wikitext mw\Wikitext
 * @param $category string Category without namespace prefix
 * @param $summary
 * @return int 0 if unexisting; 1 if existing; 2 if added.
 */
function wikitext_add_category_if_exists( $wikitext, $category, & $summary ) {
		if( $wikitext->hasCategory( $category ) ) {
			return 1;
		}
		if( commons_page_exists("Category:$category") ) {
			$wikitext->addCategory( $category );
			$summary .= "; +[[Category:$category]]";
			return 2;
		}
		return 0;
}
