#!/usr/bin/php
<?php
/*****************************
 * Lega Serie A IDs finder   *
 *                           *
 * @author Valerio Bozzolan  *
 * @license GNU GPL v3+      *
 *****************************/

// Giving an HTTP page from stdin, it generates a CSV in stdout

$stdin = file_get_contents( 'php://stdin' );

// find a link
preg_match_all( '#/giocatori/([a-zA-Z-]+)/([a-zA-Z0-9-]+)#', $stdin, $matches,  PREG_PATTERN_ORDER );
$n = count( $matches[ 0 ] );
for( $i = 0; $i < $n; $i++ ) {
	echo $matches[ 2 ][ $i ] . ',' . $matches[ 1 ][ $i ] . "\n";
}
