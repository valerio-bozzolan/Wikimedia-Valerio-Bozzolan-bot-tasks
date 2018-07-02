#!/usr/bin/php
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
# GNU Affero General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program. If not, see <http://www.gnu.org/licenses/>.

define(
	'SUMMARY',
	'Bot: '.
	'[[Discussioni modulo:Citazione#Gestione lingue composte da più di una parola|nuovo separatore per le lingue]] '.
	'([[Speciale:PermaLink/98190278#Gestione lingue composte da più di una parola|perm]])'
);

$ALWAYS = true;

// load boz-mw and configuration
require 'includes/boz-mw/autoload.php';
require '../config.php';

$wit = \wm\WikipediaIt::getInstance()->login();
$members = $wit->createQuery( [
	'action'    => 'query',
	'generator' => 'categorymembers',
	'gcmtitle'  => 'Categoria:Voci con modulo citazione e valori lingua da separare con virgola',
	'prop'      => 'revisions',
	'rvprop'    => [
		'content',
		'timestamp',
	],
] );
foreach( $members->getGenerator() as $response ) {
	foreach( $response->query->pages as $page ) {
		cli\Log::info( "[[{$page->title}]]" );

		$wikitext = $wit->createWikitext( $page->revisions[ 0 ]->{ '*' } );
		$n = $wikitext->pregMatchAll( '/(\|[ \t\n]*lingua[ \t\n]*=[ \t\n]*)([a-zA-Zòàùèì,\- \t\n]+)(}}|\|)/', $matches );
		$changes = [];
		if( $n ) {
			for( $i = 0; $i < $n; $i++ ) {
				$complete  = $matches[ 0 ][ $i ];
				$prefix    = $matches[ 1 ][ $i ];
				$languages = $matches[ 2 ][ $i ];
				$suffix    = $matches[ 3 ][ $i ];

				$n_languages = preg_match_all( '/[a-zA-Z\-òàùèì]+/', $languages );
				if( $n_languages > 1 ) {
					if( false === strpos( $languages, ',' ) ) {
						$languages_new = preg_replace( '/\w+/', '$0,', $languages, $n_languages - 1 );
						$wikitext->strReplace(
							$prefix . $languages     . $suffix,
							$prefix . $languages_new . $suffix
						);
						$changes[] = "'$languages' → '$languages_new'";
					} else {
						cli\Log::debug( "nothing to do on '$languages'" );
					}
				}
			}
		} else {
			cli\Log::warn( "[[{$page->title}]] unmatch" );
		}

		if( $wikitext->getSobstitutions() ) {
			$changes = array_unique( $changes );
			foreach( $changes as $change ) {
				cli\Log::info( "\tChange: $change" );
			}
			if( $ALWAYS || 'y' === cli\Input::yesNoQuestion( "Save?" ) ) {
				$summary = SUMMARY . ': ' . implode( '; ', $changes );
				$wit->post( [
					'action'        => 'edit',
					'pageid'        => $page->pageid,
					'basetimestamp' => $page->revisions[ 0 ]->timestamp,
					'text'          => $wikitext->getWikitext(),
					'token'         => $wit->getToken( \mw\Tokens::CSRF ),
					'summary'       => $summary,
					'bot'           => true,
				] );
			}
		}
	}
}
