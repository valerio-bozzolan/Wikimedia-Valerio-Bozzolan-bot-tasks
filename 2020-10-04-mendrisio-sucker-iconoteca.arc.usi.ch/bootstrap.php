<?php
# Copyright (C) 2020 Valerio Bozzolan
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

// require two dummy classes
require 'include/functions.php';
require 'include/class-Metadata.php';
require 'include/class-MetadataValue.php';

// base URL to be scraped
define( 'BASE_URL', 'https://iconoteca.arc.usi.ch' );

// inventory prefix to be stripped out to read the image ID (note the double slash! asd)
define( 'INVENTORY_PREFIX_TO_STRIP', BASE_URL . '//thumb.php?inventario=' );

// URL to the single photo from the image ID (DOI)
define( 'INVENTORY_URL_FORMAT',      BASE_URL . '/it/inventario/%d' );

// URL of the high quality image
define( 'HIGH_QUALITY_IMAGE_URL',    BASE_URL . '/image-viewer.php?inventario=%d' );

// URL of the high quality image
define( 'LOW_QUALITY_IMAGE_URL',     BASE_URL . '/image_permission_show.php?inventario=%d' );

// image download name (with image ID)
define( 'IMAGE_DOWNLOAD_NAME', 'images/%d.jpg' );

// array of metadatas displayed in the body in the '.metadati' selector
// basically they are the labels displayed in the body on every image like this one:
// https://iconoteca.arc.usi.ch/it/inventario/51630
$METADATA_BODY = [
	new Metadata( 'Luogo rappresentato' ),
	new Metadata( 'Tipologia di risorsa' ),
	new Metadata( 'Creatore' ),
	new Metadata( 'Data' ),
	new Metadata( 'DOI', function ( $p ) {

		// the DOI is a link, so just extract the URL

		// text displayed after the label (manually stripping the label)
		return $p->find( 'a' )->attr( 'href' );
	} ),
	new Metadata( 'ID immagine' ),
	new Metadata( 'Licenza', function( $p ) {

		// the License is a link, so just extract the URL

		// text displayed after the label (manually stripping the label)
		return $p->find( 'a' )->attr( 'href' );
	} ),
];

// array of metadatas displayed in the footer in the '.metadati_completi' selector
// basically they are the labels displayed in the footer on every image like this one:
// https://iconoteca.arc.usi.ch/it/inventario/51630
$METADATA_FOOTER = [
	new Metadata( 'Titolo opera' ),
	new Metadata( 'Titolo originale' ),
	new Metadata( 'Iscrizione' ),
	new Metadata( 'Collezione' ),
	new Metadata( 'Data creazione' ),
	new Metadata( 'Luogo creazione' ),
	new Metadata( 'Nome creatore' ),
	new Metadata( 'Descrittori Sbt' ),
	new Metadata( 'Descrittori Getty AAT' ),
	new Metadata( 'Luogo rappresentato', function( $p ) {

		// take just the text inside the link
		return $p->find( 'a' )->text();
	} ),
	new Metadata( 'Classificazione' ),
	new Metadata( 'Tipo materiale' ),
	new Metadata( 'Designazione specifica del materiale' ),
	new Metadata( 'Supporto originale' ),
	new Metadata( 'Materiale del supporto' ),
	new Metadata( 'Nome oggetto culturale' ),
	new Metadata( 'Colore' ),
	new Metadata( 'Polarità' ),
	new Metadata( 'Tipo supporto' ),
	new Metadata( 'Processo e tecnica' ),
	new Metadata( 'Montaggio' ),
	new Metadata( 'Orientamento e forma' ),
	new Metadata( 'Dimensioni' ),
];
