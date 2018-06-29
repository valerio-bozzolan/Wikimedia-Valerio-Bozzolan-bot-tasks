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

require 'includes/boz-mw/autoload.php';
require '../config.php';

$REFERENCES = [ [
	'snaks' => (
		new wb\Snaks( [
			// stated in: Ministry of the Environment
			new wb\SnakItem( 'P248', 'Q3858479' ),

			// retrieved
			new wb\SnakTime( 'P813', '+2018-06-00T00:00:00Z', wb\DataValueTime::PRECISION_MONTHS )
		] )
	)->getAll()
] ];

$wikidata = \wm\Wikidata::getInstance();
foreach( explode( "\n", trim( file_get_contents( 'data/italian-natura-ids.csv' ) ) ) as $park ) {

	list( $entity_id, $natura_id ) = explode( ',', $park );

	// Data old
	$data_old = $wikidata->fetch( [
		'action' => 'wbgetentities',
		'props'  => 'claims',
		'ids'    => $entity_id,
	] );
	$data_old = wb\DataModel::createFromObject( $data_old->entities->{ $entity_id } );

	// Data new
	$data = ( new wb\DataModel() )
		->addClaim(
			( new wb\StatementString( 'P3425', $natura_id ) )
				->setReferences( $REFERENCES )
		);

	if( ! $data_old->hasClaimsInProperty( 'P3425' ) ) {
		$wikidata->post( [
			'action'  => 'wbeditentity',
			'summary' => 'Bot: [[Wikidata:Requests for permissions/Bot/Valerio Bozzolan bot 4|importing Natura 2000 site ID]]',
			'token'   => $wikidata->getToken( mw\Tokens::CSRF ),
			'bot'     => 1,
			'id'      => $entity_id,
			'data'    => $data->getJSON()
		] );
	}
}
