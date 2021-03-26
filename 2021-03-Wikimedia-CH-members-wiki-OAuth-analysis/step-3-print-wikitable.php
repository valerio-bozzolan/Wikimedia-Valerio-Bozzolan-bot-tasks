#!/usr/bin/php
<?php

require 'load.php';

$users = file_get_data( 'wmch-users-checked.data' );

usort( $users, function( $a, $b ) {

	if( $a->name === $b->name ) {
		return 0;
	}

	return $a->name < $b->name ? -1 : 1;
} );

$print_table = function( $users, $condition = null ) {

	echo "{| class=\"wikitable\"\n";
	echo "! Line\n";
	echo "! Username WMCH\n";
	echo "! Link Meta (assumed)\n";
	echo "! Meta\n";

	$i = 1;
	foreach( $users as $user ) {

		$user->metauserid = $user->metauserid ?? '';

		if( !$condition || $condition( $user ) ) {

			echo    "|-\n";
			echo    "| $i\n";
			printf( "| [[User:%s]]\n",   $user->name );
			printf( "| [[metawikipedia:User:%s]]\n", $user->name );
			echo    "| {$user->metauserid}\n";

			$i++;
		}
	}

	echo "|}\n";
};

echo "== Matching users ==\n";
$print_table( $users, function( $user ) {
	return !empty( $user->metauserid );
} );
echo "\n";

echo "== Missing users ==\n";
$print_table( $users, function( $user ) {
	return empty( $user->metauserid );
} );
