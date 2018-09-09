#!/usr/bin/php
<?php
/***********************************************
 * WLM CH 2018 import of instance of farmhouse *
 *                                             *
 * @author Valerio Bozzolan                    *
 * @license GNU GPL v3+                        *
 ***********************************************/

// load boz-mw
require '../includes/boz-mw/autoload.php';
require __DIR__ . '/../../config.php';

$QUERY = <<<ASD
# Farmhouses that are not instance of farmhouse
SELECT DISTINCT ?item WHERE {
  ?item wdt:P1435 [];
        wdt:P381 [].
  ?item rdfs:label ?itemLabel.
  MINUS {
   ?item wdt:P31/wdt:P279* wd:Q131596
  }
  FILTER( LANG( ?itemLabel ) = "en" ).
  FILTER( CONTAINS( ?itemLabel, "farmhouse" ) ).
}
ASD;

$results = \wm\Wikidata::querySPARQL( $QUERY );
foreach( $results as $result ) {
	$item = str_replace( 'http://www.wikidata.org/entity/', '', $result->item->value );
	$data = \wm\Wikidata::getInstance()
			->login()
			->createDataModel()
			->addClaim( new \wb\StatementItem( 'P31', 'Q489357' ) )
			->printChanges()
			->editEntity( [
				'id' => $item,
				'summary.pre' => '[[c:Commons:Wiki Loves Monuments 2018 in Switzerland|Wiki Loves Monuments 2018 in Switzerland]]: ',
				'bot' => 1,
			] );
}
