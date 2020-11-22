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
