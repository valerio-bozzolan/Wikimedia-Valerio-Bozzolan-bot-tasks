#!/usr/bin/php
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

// autoload framework
require __DIR__ . '/../includes/boz-mw/autoload.php';

// require some values
require 'bootstrap.php';

// load configuration file or create one
cli\ConfigWizard::requireOrCreate( __DIR__ . '/../config.php' );

// https://commons.wikimedia.org/wiki/Commons:Bots/Requests/Valerio_Bozzolan_bot_(6)
$COMMONS_CONSENSUS_PAGE = "[[Commons:Bots/Requests/Valerio Bozzolan bot (6)|authorized import from Academy of architecture Library of Mendrisio]]";

// load Wikimedia Commons
$commons = \wm\Commons::instance();

use \cli\Log;
use \cli\Input;
use \cli\Opts;
use \cli\ParamValuedLong;
use \cli\ParamFlag;
use \cli\ParamFlagLong;

// register all CLI parameters
$opts = new Opts( [
	new ParamFlagLong(   'porcelain',     "Do nothing" ),
	new ParamFlagLong(   'preview',       "Show a preview of the saved wikitext" ),
	new ParamFlagLong(   'force-upload',  "Force a re-upload even if the page exists" ),
	new ParamValuedLong( 'start-from',    "Start from a specific row (default to 1)" ),
	new ParamValuedLong( 'limit',         "Process only this number of results" ),
	new ParamFlag(       'help',    'h',  "Show this help and quit" ),
] );

// arguments
$unnamed_opts = Opts::unnamedArguments();
$dir      = $unnamed_opts[0] ?? null;
$template = $unnamed_opts[1] ?? null;

// porcelain mode means that nothing have to be saved
$PORCELAIN = $opts->getArg( 'porcelain' );

// get a preview of the current wikitext
$PREVIEW = $opts->getArg( 'preview' );

// check if you have to force the upload
$FORCE_UPLOAD = $opts->getArg( 'force-upload' );

// start from this row
$START_FROM = $opts->getArg( 'start-from' );

// limit to this number of results
$LIMIT = $opts->getArg( 'limit' );

// show the help
$show_help = $opts->getArg( 'help' );

// no dir no party
if( !$dir || !$template ) {
	$show_help = true;
}

// show an help message
if( $show_help ) {
	echo "Usage:\n {$argv[ 0 ]} [OPTIONS] path/data/ path/template/name.tpl\n\n";
	echo "Allowed OPTIONS:\n";

	$opts->printParams();

	exit(
		$opts->getArg( 'help' )
		? 0
		: 1
	);
}

// missing creators in Wikimedia Commons
$MISSING_COMMONS_CREATOR = [];

$file_pattern = $dir . '/' . '*.jpg';

// doi_by_name
$doi_by_name = [];

// array of duplicate DOIs
$duplicate_dois = [];

// duplicate SHA1 of filenames
$duplicate_sha1 = [];

// scan the directory - this is written as-is to do not disturb GNU nano with slash and star. asd
foreach( glob( $file_pattern ) as $file ) {

	// no data no party
	$file_data = @file_get_contents( "$file.json" );
	$file_data = json_decode( $file_data );
	if( !$file_data ) {
		echo "skip $file missing data\n";
		continue;
	}

	// check available metadata
	$img_id      = $file_data->{"ID immagine"};
	$title       = $file_data->{"Titolo opera"};


	// DOI by name
	if( empty( $doi_by_name[ $title ] ) ) {
		$doi_by_name[ $title ] = [];
	} else {
		$duplicate_dois[] = $img_id;
	}
	$doi_by_name[ $title ]  [] = $img_id;


	// title by SHA1
	$sha1 = sha1_file( $file );
	if( empty( $duplicate_sha1[ $sha1 ] ) ) {
		$duplicate_sha1[ $sha1 ] = [];
	}
	$duplicate_sha1[ $sha1 ][] = "$title - DOI $img_id";
}

// find duplicates
$duplicates = false;
foreach( $doi_by_name as $title => $dois ) {

	// a title should be unique by DOI
	if( count( $dois ) > 1 ) {
		Log::warn( sprintf(
			"found duplicate title '%s' DOIs %s",
			$title,
			implode( ', ', $dois )
		) );

		$duplicates = true;
	}
}

foreach( $duplicate_sha1 as $sha1 => $titles ) {

	if( count( $titles ) > 1 ) {

		Log::warn( sprintf(
			"found duplicate file '%s'",
			implode( ', ', $titles )
		) );

		$duplicates = true;
	}
}

// login in Commons
$commons->login();

// columns to be displayed in the report
$REPORT_COLUMNS = [
	'N',
	'FILE_WLINK',
	'FILE_THUMB',
	'TITLE',
	'DESCRIPTION',
	'LICENSE',
	'DATE',
	'AUTHOR',
	'CREATOR_COMMONS_LINK',
	'SIZE_TEMPLATE',
	'MEDIUM',
	'MEDIUM_TEMPLATE',
	'PLACE_CREATION',
	'DOI_ID',
	'SOURCE',
];

// write report
$write_report_csv  = fopen( 'write-report.csv',  'w' );
$write_report_wiki = fopen( 'write-report.wiki', 'w' );

// columns to be displayed in the log
$log_args = [];
foreach( $REPORT_COLUMNS as $k => $v ) {
	$log_args[] = $v;
}

// write reports
fputcsv( $write_report_csv, $log_args );
fwrite(  $write_report_wiki, "{| class=\"wikitable\"\n|-\n! " . implode( "\n! ", $log_args ) . "\n" );

$row = 0;
$processeds = 0;

// scan the directory - this is written as-is to do not disturb GNU nano with slash and star. asd
foreach( glob( $file_pattern ) as $file ) {

	// no data no party
	$file_data = @file_get_contents( "$file.json" );
	$file_data = json_decode( $file_data );
	if( !$file_data ) {
		echo "skip $file missing data\n";
		continue;
	}

	$row++;

	// start from this element
	if( $START_FROM ) {
		if( $row < $START_FROM ) {
			// skip
			continue;
		} else {
			// continue normally
			$START_FROM = null;
		}
	}

	// number of processed images
	$processeds++;

	// apply limit
	if( $LIMIT && $processeds > $LIMIT ) {
		Log::info( "reached limit of $LIMIT" );
		exit( 0 );
	}

	// check available metadata
	$img_id      = $file_data->{"ID immagine"};
	$title       = $file_data->{"Titolo opera"};
	$title_orig  = $file_data->{"Titolo originale"}     ?? null;
	$collection  = $file_data->{"Collezione"}           ?? null;
	$license     = $file_data->{"Licenza"}              ?? null;
	$type        = $file_data->{"Tipologia di risorsa"} ?? "Fotografia";
	$size        = $file_data->{"Dimensioni"}           ?? '';
	$material    = $file_data->{"Tipo materiale"}       ?? null;
	$author_name = $file_data->{"Nome creatore"}        ?? null;
	$author      = $file_data->{"Creatore"}             ?? $author_name;
	$date        = $file_data->{"Data"}                 ?? $file_data->{"Data creazione"} ?? null;
	$process     = $file_data->{"Processo e tecnica"}   ?? null;
	$place       = $file_data->{"Luogo creazione"}      ?? null;

	// build the |medium= parameter
	// example: "Carta. Fatto cor culo."
	$medium_parts = [];
	if( $material ) {
		$medium_parts[] = $material;
	}
	if( $process ) {
		$medium_parts[] = $process;
	}
	$medium = implode( '. ', $medium_parts );

	// obtain a {{Technique}} template parsing keywords from $medium
	$medium_template = italian_technique_2_commons_template( $process );

	// check license
	$license_templates = '';
	if( $license === 'https://creativecommons.org/licenses/by-sa/4.0/deed.it' ) {
		$license_templates .= "{{Cc-by-sa-4.0}}";
	} else {
		throw new Exception( "unknown license $license" );
	}

	// source URL
	$source_url = null;
	if( $img_id ) {
		$source_url = sprintf( INVENTORY_URL_FORMAT, $img_id );
	}

	// drop nonsense authors
	if( $author === 'Autore non identificato' ) {
		$author = null;
	}
	if( $author === 'ignoto' ) {
		$author = null;
	}

	// creator on Wikimedia Commons
	$creator_commons = null;
	$creator_commons_template = null;
	$creator_commons_link = null;
	if( $author ) {
		$creator_commons = search_creator_on_commons( $author );

		if( $creator_commons ) {
			$creator_commons_template = '{{' . $creator_commons . '}}';
			$creator_commons_link = "[[$creator_commons]]";
		} else {
			$author_possible_variant = first_name_variant( $author );

			$creator_commons_template = $author_possible_variant;

			$MISSING_COMMONS_CREATOR[ $author_possible_variant ] = $MISSING_COMMONS_CREATOR[ $author_possible_variant ] ?? 0;
			$MISSING_COMMONS_CREATOR[ $author_possible_variant ]++;

			Log::warn( "missing Wikimedia Commons [[Creator:$author_possible_variant]]" );
		}
	}

	// parse the image size
	$size_template = parse_size( $size );

	// check if the filename exists
	$filename = "$title.jpg";
	$filename_complete = "File:$filename";
	$filename_unique          = "$title (DOI $img_id).jpg";
	$filename_unique_complete = "File:$filename_unique";

	// if this is a duplicate, make the title unique
	if( in_array( $img_id, $duplicate_dois ) ) {
		$filename          = $filename_unique;
		$filename_complete = $filename_unique_complete;
	}

	// template arguments
	$template_args = [
		'N'                 => $row,
		'FILE_THUMB'        => "[[$filename_complete|100px]]",
		'FILE_WLINK'        => "[[:$filename_complete]]",
		'TITLE'             => $title_orig  ? "{{it|$title_orig}}"  : '',
		'DESCRIPTION'       => $title       ? "{{it|$title}}"       : '',
		'LICENSE'           => $license,
		'LICENSE_TEMPLATES' => $license_templates,
		'DATE'              => italian_date_2_commons( $date ),
		'METADATA'          => $file_data,
		'DOI_ID'            => $img_id,
		'SOURCE'            => $source_url,
		'AUTHOR'            => $author,
		'CREATOR_COMMONS'   => $creator_commons_template,
		'CREATOR_COMMONS_LINK' => $creator_commons_link,
		'SIZE_TEMPLATE'     => $size_template,
		'MEDIUM'            => $medium,
		'MEDIUM_TEMPLATE'   => $medium_template,
		'PLACE_CREATION'    => $place,
	];

	// build the page content
	$page_content = template_content( $template, $template_args );

	// check if you want to show a preview
	if( $PREVIEW ) {
		echo "---\n";
		echo $page_content;
		echo "---\n";
	}

	// columns to be displayed in the log
	$log_args = [];
	foreach( $REPORT_COLUMNS as $column ) {
		$log_args[] = $template_args[ $column ];
	}

	// write reports
	fputcsv( $write_report_csv, $log_args );
	fwrite(  $write_report_wiki, "|-\n| " . implode( "\n| ", $log_args ) . "\n" );

	// check if the Commons page exists
	    $commons_page_id = wiki_page_id( $commons, $filename_complete );
	if( $commons_page_id && !$FORCE_UPLOAD ) {

		// page exists
		Log::info( sprintf(
			"updating https://commons.wikimedia.org/wiki/%s",
			rawurlencode( $filename_complete )
		) );

		// eventually skip saving
		if( !$PORCELAIN ) {

			Log::info( sprintf( "Saving..." ) );

			// save
			// https://www.mediawiki.org/w/api.php?action=help&modules=parse
			$result = $commons->edit( [
				'title'         => $filename_complete,
				'text'          => $page_content,
				'summary'       => "Bot: $COMMONS_CONSENSUS_PAGE",
				'minor'         => true,
				'bot'           => true,
			] );

			// eventually wait some time if something was changed
			if( isset( $result->edit->nochange ) ) {
				Log::info( "no change" );
			} else {
				Log::info( "saved" );
				sleep( 5 );
			}
		}

	} else {

		// print a message
		Log::info( sprintf(
			"try to upload https://commons.wikimedia.org/wiki/%s (DOI %s)",
			rawurlencode( $filename_complete ),
			$img_id
		) );

		// upload this damn image
		try {

			if( !$PORCELAIN ) {

				// https://www.mediawiki.org/w/api.php?action=help&modules=upload
				$response = $commons->upload( [
					'comment'        => "Bot: $COMMONS_CONSENSUS_PAGE",
					'text'           => $page_content,
					'filename'       => $filename,
					'ignorewarnings' => $FORCE_UPLOAD,
					\network\ContentDisposition::createFromNameURLType( 'file', $file, 'image/jpg' ),
				] );

				if( $response->upload->result === 'Success' ) {
					var_dump( $response );
					echo "Done.\n";
				} else {
					// what the fuuck?
					print_r( $response );
				}

				// put in the log this shit
				file_put_contents( 'upload.out', "$img_id;$filename\n", FILE_APPEND );

				// wait to do not use the bot flag
				sleep( 5 );

			}

		} catch( Exception $e ) {
			printf( "%s: %s", get_class( $e ), $e->getMessage() );
			file_put_contents( 'upload.out.err', $e->getMessage(), FILE_APPEND );
		}
	}

	/*
	// structured data ID
	$commons_structured_id = "M{$commons_page_id}";

	// now the page exists - fetch the Entity ID
	$commons_structured = $commons->fetchSingleEntity( $commons_structured_id );

	//
	$commons_structured->hasClaimsInProperty();

	// source URL
	// Property:P854

	// Inscription
	// Property:P1684
	*/
}

// show missing Commons creator
if( $MISSING_COMMONS_CREATOR ) {
	print_r( $MISSING_COMMONS_CREATOR );
}

fwrite(  $write_report_wiki, "|}\n" );

fclose( $write_report_csv );
fclose( $write_report_wiki );
