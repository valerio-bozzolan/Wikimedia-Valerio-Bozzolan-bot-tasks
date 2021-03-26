#!/usr/bin/php
<?php

require 'load.php';

// Wikimedia CH
$wmch = wmch();

// fix read permission
$wmch->login();

$wmch_users_queries = $wmch->createQuery( [
	'action'  => 'query',
	'list'    => 'allusers',
	'aulimit' => 500,
] );

$users = [];

foreach( $wmch_users_queries as $wmch_user_query_batches ) {

	foreach( $wmch_user_query_batches as $wmch_user_query_batch ) {

		$allusers = $wmch_user_query_batch->allusers ?? [];
		foreach( $allusers as $user ) {

			$users[] = $user;

		}
	}

}

file_put_data( 'wmch-users.data', $users );
