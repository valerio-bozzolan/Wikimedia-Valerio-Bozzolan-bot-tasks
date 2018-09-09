<?php

// load boz-mw
require 'includes/boz-mw/autoload.php';

// load configuration
require __DIR__ . '/../config.php';

mw\API::$INSPECT_BEFORE_POST = true;

$QUERY = <<<ASD
# Farmhouses with "Farmhouse" as label
SELECT DISTINCT ?item ?itemLabel ?cityLabel ?address WHERE {
  ?item wdt:P1435 [];
        wdt:P381 [];
        wdt:P969 ?address;
        wdt:P131 ?city.
  ?city wdt:P31 wd:Q70208.
  ?item rdfs:label ?itemLabelMust.
  FILTER( LANG( ?itemLabelMust ) = "en" ).
  FILTER( STR( ?itemLabelMust ) = "Farmhouse" ).
  SERVICE wikibase:label { bd:serviceParam wikibase:language "en". }
}
ORDER BY DESC( ?image ) ?itemLabel
ASD;

// existing monuments
$results = \wm\Wikidata::querySPARQL( $QUERY );

foreach( $results as $result ) {
	$item    = $result->item->value;
	$city    = $result->cityLabel->value;
	$address = $result->address->value;

	$item = str_replace( 'http://www.wikidata.org/entity/', '', $item );

	$label = "farmhouse in $city, $address";

	\wm\Wikidata::getInstance()->login()->createDataModel()
		->setEntityID( $item )
		->setLabel( new \wb\LabelAction( 'en', $label, wb\LabelAction::OVERWRITE ) )
		->printChanges()
		->editEntity( [
			'summary' => '[[c:Commons:Wiki Loves Monuments 2018 in Switzerland|Wiki Loves Monuments 2018 in Switzerland]]: differentiating farmhouses'
		] );
}
