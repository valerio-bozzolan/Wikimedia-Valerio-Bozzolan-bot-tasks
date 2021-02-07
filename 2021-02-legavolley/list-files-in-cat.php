#!/usr/bin/php
<?php
# Copyright (C) 2021 Valerio Bozzolan
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

// autoload framework
require __DIR__ . '/../includes/boz-mw/autoload.php';

$MAIN_CAT = 'Category:Files from Legavolley stream';

$wiki = \wm\Commons::instance();

$queries =
	$wiki->createQuery( [
		'action'  => 'query',
		'list'    => 'categorymembers',
		'cmtitle' => $MAIN_CAT,
		'cmtype'  => 'subcat',
	] );

$sub_categories = [];
foreach( $queries as $query ) {
	foreach( $query->query->categorymembers as $page ) {
		$sub_categories[] = $page->title;
	}
}

$files = [];

// now for each sthat we have the correct categories
foreach( $sub_categories as $sub_category ) {

	$queries =
		$wiki->createQuery( [
			'action'  => 'query',
			'list'    => 'categorymembers',
			'cmtitle' => $sub_category,
			'cmtype'  => 'file',
		] );

	// for each query
	foreach( $queries as $query ) {

		$members = $query->query->categorymembers ?? [];
		foreach( $members as $page ) {

			$title = $page->title;
			$files[] = $title;

			echo "$title\n";
		}
	}

}
