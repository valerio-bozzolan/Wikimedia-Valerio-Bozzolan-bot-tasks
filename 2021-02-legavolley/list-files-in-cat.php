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
require 'autoload.php';

$MAIN_CAT = 'Category:Files from Legavolley stream';

$wiki = \wm\Commons::instance();

// collect first-class categories
$queries =
	$wiki->createQuery( [
		'action'  => 'query',
		'list'    => 'categorymembers',
		'cmtitle' => $MAIN_CAT,
		'cmtype'  => 'subcat',
	] );

// query all sub-categories
$volleyball_categories = [];
foreach( $queries as $query ) {
	foreach( $query->query->categorymembers as $page ) {
		$volleyball_categories[] = $page->title;
	}
}

$players = [];

// now for each sthat we have the correct categories
foreach( $volleyball_categories as $sub_category ) {

	// prepare to query each file with their categories
	$queries =
		$wiki->createQuery( [
			// query files
			'action'    => 'query',
			'generator' => 'categorymembers',
			'gcmtitle'  => $sub_category,
			'gcmtype'   => 'file',
			'gcmlimit'   => 500,

			// for each file get its categories
			'prop'      => 'categories',
			'clshow'    => '!hidden',
		] );

	// for each query
	foreach( $queries as $query ) {

		// for each file
		$pages = $query->query->pages ?? [];
		foreach( $pages as $page ) {

			echo "$title\n";
			$title  = $page->title;
			$pageid = $page->pageid;

			// get or create
			$players[ $pageid ] = $players[ $pageid ] ?? new VolleyballPlayerFile();
			$player = $players[ $pageid ];
			$player->file = $title;

			// eventually loop the categories
			$categories = $page->categories ?? [];
			foreach( $categories as $category ) {

				echo "$category_title\n";
				$category_title = $category->title;

				$player->cats[] = $category_title;
			}
		}
	}

}

$data = serialize( $players );

file_put_contents( 'data/players.serialized', $data );
