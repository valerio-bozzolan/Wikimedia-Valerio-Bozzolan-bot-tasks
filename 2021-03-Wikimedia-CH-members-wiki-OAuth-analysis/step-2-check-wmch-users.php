#!/usr/bin/php
<?php

$BATCH = 50;

require 'load.php';

$users = file_get_data( 'wmch-users.data' );

$all_users = $users;

/**
 * Callback fired for each match
 */
$matched_callback = function( $response_user, $my_user ) {

	// registration date if provided
	if( isset( $response_user->registration ) ) {
		$my_user->registration = $response_user->registration;
	}

	// inherit Meta user ID if provided
	if( isset( $response_user->userid ) ) {
		$my_user->metauserid = $response_user->userid;
	}
};

$get_name = function ( $a ) {
	return $a->name;
};

do {

	// process some each time
	$batch = [];
	for( $i = 0; $i < $BATCH && $users; $i++ ) {
		$batch[] = array_pop( $users )->name;
	}

	$queries = meta()->createQuery( [
		'action'  => 'query',
		'list'    => 'users',
		'ususers' => $batch,
		'usprop'  => 'registration',
	] );
	foreach( $queries as $query ) {

		// match results
		response_matcher( $query, $all_users, 'query', 'users' )
			->matchByCustomJoin( $matched_callback, $get_name );
	}

} while( $users );

// save the merged data
file_put_data( 'wmch-users-checked.data', $all_users );
