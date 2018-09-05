#!/usr/bin/php
<?php
/*****************************
 * Mibact code fixer         *
 *                           *
 * @author Valerio Bozzolan  *
 * @license GNU GPL v3+      *
 *****************************/

// load boz-mw and configurations
require 'boz-mw/autoload.php';
require __DIR__ . '/../config.php';

define( 'MIBACT_ID',        'P5782' );
define( 'CATALOG_CODE',     'P528'  );
define( 'DESCRIBED_AT_URL', 'P973'  );

wm\Wikidata::getInstance()->login();

foreach( wm\Wikidata::querySPARQL( file_get_contents( 'query.sparql' ) ) as $result )  {

	$code    = $result->catv->value;
	$item_id = $result->id  ->value;
	$item_id = str_replace( 'http://www.wikidata.org/entity/', '', $item_id );
	$code    = str_replace( 'DBUnico.',                        '', $code    );

	// pull existing data
	$item = wm\Wikidata::getInstance()
		->fetchSingleEntity( $item_id, [ 'props' => 'claims' ] );

	// data to be pushed
	$data = $item->cloneEmpty();

	// push the MiBACT ID if not exists
	if( ! $item->hasClaimsInProperty( MIBACT_ID ) ) {
		$data->addClaim( new wb\StatementExternalID( MIBACT_ID, $code ) );
	}

	// push for removal of any catalog code claims
	foreach( $item->getClaimsInProperty( CATALOG_CODE ) as $claim ) {
		$code = $claim->getMainsnak()->getDataValue()->getValue();
		if( false !== strpos( $code, 'DBUnico.' ) ) {
			$data->addClaim( $claim->cloneForRemoval() );
		}
	}

	// push for removal of any described at URL claims
	foreach( $item->getClaimsInProperty( DESCRIBED_AT_URL ) as $claim ) {
		$code = $claim->getMainsnak()->getDataValue()->getValue();
		if( false !== strpos( $code, 'http://dati.beniculturali.it/mibact/luoghi/resource/CulturalInstituteOrSite' ) ) {
			$data->addClaim( $claim->cloneForRemoval() );
		}
	}

	if( $data->countClaims() ) {
		$data->printChanges();
		if( 'y' === cli\Input::yesNoQuestion( 'save?' ) ) {
			$data->editEntity( [
				'bot' => 1,
				'summary.pre' => 'Bot: [[Wikidata:Property proposal/DBUnico ID|migrate to new DBUnico ID]]: '
			] );
		}
	}
}
