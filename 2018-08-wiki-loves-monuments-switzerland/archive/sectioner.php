#!/usr/bin/php
<?php
/*****************************
 * WLM CH 2018 sectioner     *
 *                           *
 * @author Valerio Bozzolan  *
 * @license GNU GPL v3+      *
 *****************************/

// load boz-mw and configuration
require 'includes/boz-mw/autoload.php';
require __DIR__ . '/../config.php';

while( 1 ) {
	$title = \cli\Input::askInput( "Insert a page title" );

	$result = \wm\Commons::getInstance()->fetch( [
		'action'  => 'query',
		'prop'    => 'revisions',
		'titles'  => $title,
		'rvprop'  => 'content',
		'rvslots' => 'main',
		'rvlimit' => 1,
	] );

	foreach( $result->query->pages as $page ) {
		$old_wikitext = $page->revisions[ 0 ]->slots->main->{ '*' };
		$old_wikitext = str_replace( "{{Cultural heritage CH header 2018}}\n", '', $old_wikitext );
		$towns = 0;
		$last_town = null;
		if( $old_wikitext ) {
			$old_lines = explode( "\n", $old_wikitext );
			$new_lines = [];
			foreach( $old_lines as $line ) {
				if( 1 === preg_match( '/Town *= *([^|]+)\|/', $line, $matches ) ) {
					$town = $matches[ 1 ];
					if( $town !== $last_town ) {
							if( $last_town !== null ) {
								$new_lines[] = '|}';
							}
							$new_lines[] = "{{Cultural heritage CH header 2018|$town}}";
							$towns++;
					}
					$last_town = $town;
				}
				$new_lines[] = $line;
			}
			if( $towns > 1 ) {
				$wikitext = implode( "\n", $new_lines );
				\wm\Commons::getInstance()->login()->edit( [
					'title' => $title,
					'summary' => sprintf(
						'split in %d town headings',
						$towns
					),
					'text' => $wikitext,

					// to operate like a human and avoid Wikimedia Commons bot request for permission :|
					'bot' => 0,
				] );
			}
		}
	}
}
