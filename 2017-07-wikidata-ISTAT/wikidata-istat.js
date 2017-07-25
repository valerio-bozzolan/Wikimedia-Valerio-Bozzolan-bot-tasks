/**
 * Script to add ISTAT data from a provided CSV.
 *
 * @licene: GNU General Public License v2+ or Creative Commons By Sa 4.0 International
 * @author: [[User:Valerio Bozzolan]]
 */
var WDBoiler = {
	data: null,
	timeout: null,
	current: 0,
	MIN_TIMEOUT: 8000,
	MIN_WRITE_TIMEOUT: 60000,
	MAXLAG: 10,
	API: 'https://www.wikidata.org/w/api.php',
	getNextRow: function () {
		return WDBoiler.current < WDBoiler.data.length
			? WDBoiler.data[ WDBoiler.current++ ]
			: null;
	},
	EDIT_TOKEN: null,
	log: function (msg) {
		var now = new Date();
		$('#log').append('[' + now.getHours() + ':' + now.getMinutes() + '] ' + msg + '\n')
		         .scrollTop( $('#log')[0].scrollHeight );
	}
};

$(document).ready( function () {

	if( mw && mw.user.tokens.get( 'editToken' ) ) {
		WDBoiler.EDIT_TOKEN = mw.user.tokens.get( 'editToken' );
		console.log('Token OK');
	}

	// ref: http://stackoverflow.com/a/1293163/2343
	// ref: https://stackoverflow.com/questions/1293147/javascript-code-to-parse-csv-data
	// This will parse a delimited string into an array of
	function CSVToArray(strData, strDelimiter) {
		strDelimiter = strDelimiter || ';';
		var objPattern = new RegExp(
			(
				// Delimiters.
				"(\\" + strDelimiter + "|\\r?\\n|\\r|^)" +
				// Quoted fields.
				"(?:\"([^\"]*(?:\"\"[^\"]*)*)\"|" +
				// Standard fields.
				"([^\"\\" + strDelimiter + "\\r\\n]*))"
			),
			'gi'
		);
		var arrData = [ [] ];
		var arrMatches = null;
		while (arrMatches = objPattern.exec(strData)) {
			var strMatchedDelimiter = arrMatches[1];
			if ( strMatchedDelimiter.length && strMatchedDelimiter !== strDelimiter ) {
				arrData.push([]);
			}
			var strMatchedValue;
			if (arrMatches[2]) {
				strMatchedValue = arrMatches[2].replace(
					new RegExp("\"\"", "g"),
					"\""
				);
			} else {
				strMatchedValue = arrMatches[3];
			}
			arrData[arrData.length - 1].push(strMatchedValue);
		}
		return arrData;
	}

	var $D = $('#bodyContent').empty();

	$D.append('<textarea name="whatever" placeholder="data;data"></textarea>');
	$D.append('<input type="button" type="button" id="datatable-api-sandbox-convert" value="To table" />');
	$D.append('<table id="data-table"></table>');
	$D.append('<button type="button" id="datatable-api-sandbox-play">#play</button>');
	$D.append('<textarea id="log" class="expand"></textarea>');

	if( localStorage ) {
		var val = localStorage.getItem('datatable-api-sandbox-container-last-input');
		if( val ) {
			$('textarea[name=whatever]').val( val );
			update_table( val );
		}
	}

	$('#datatable-api-sandbox-pause').click( function () {
		WDBoiler.timeout && clearInterval( WDBoiler.timeout );
	} );

	$('#datatable-api-sandbox-play').click( function () {
		WDBoiler.current = 0;

		var row = WDBoiler.getNextRow();
		if( row ) {
			do_something( row );
		} else {
			WDBoiler.log("No elements.");
		}
	} );

	$('#datatable-api-sandbox-convert').click( function () {
		var val = $('textarea[name=whatever]').val();
		localStorage && localStorage.setItem('datatable-api-sandbox-container-last-input', val);
		update_table( val );
	} );

	function update_table( val ) {
		WDBoiler.data = CSVToArray( val );

		$t = $('#data-table').empty();
		for(var i=0; i<WDBoiler.data.length; i++) {
			var row = WDBoiler.data[i];
			var $tr = $('<tr>');

			// Numeration
			$tr.append( $('<td>').text(i) );

			for(var j=0; j<row.length; j++) {
				var col = row[j];
				var $td = $('<td>').html( col );
				col._$td = $td;
				$tr.append( $td );
			}

			$t.append( $tr );
		}
	}

	function do_retry(timeout) {
		WDBoiler.current--;
		do_next(timeout);
	}

	function do_next(timeout) {
		WDBoiler.timeout = setTimeout( function () {
			var row = WDBoiler.getNextRow();

			if( row ) {
				do_something( row );
			} else {
				WDBoiler.log("Reached latest element.");
			}
		}, timeout || WDBoiler.MIN_TIMEOUT);
	}

	function do_something(row) {
		WDBoiler.log("Doing...");

		var comune_name = row[3];
		var comune_old  = row[4];
		var comune_new  = row[2];
		var wikidata_ID = row[7];

		if( comune_old === comune_new ) {
			WDBoiler.log("Skipping " + comune_name);
			do_next(1);
			return;
		}

		console.log(comune_name);
		console.log(comune_old);
		console.log(comune_new);
		console.log(wikidata_ID);

		var data = {
			format: 'json',
			action: 'wbgetentities',
			sites: 'itwiki',
			languages: 'it',
			maxlag: WDBoiler.MAXLAG
		};
		if(  wikidata_ID ) {
			data.ids = wikidata_ID;
		} else {
			data.titles = comune_name;
			data.normalize =1;
		}

		$.ajax( {
			method: 'GET',
			url: WDBoiler.API,
			data: data
		} )
		.success( function (data) {
			console.log(data);

			if( data.success ) {
				for(entity_code in data.entities) {
					WDBoiler.log(comune_name + " = " + entity_code);

					var entity = data.entities[entity_code];

					var PROPERTY = 'P635';

					if( entity.claims && entity.claims[ PROPERTY ] ) {

						var save = true;

						var p = entity.claims[ PROPERTY ];

						for(pValue in p) {
							var pValue = p[pValue];

							WDBoiler.LATEST_VALUE = pValue;
							console.log( pValue );

							if( pValue.mainsnak && pValue.mainsnak.datavalue ) {
								var datavalue = pValue.mainsnak.datavalue;
								var v = pValue.mainsnak.datavalue.value;

								WDBoiler.log(comune_name + " con codice " + v);

								if( v === comune_new ) {
									WDBoiler.log(comune_name + " già a posto");
									save = false;
									do_next();
								} else {
									WDBoiler.log(comune_name + " salvataggio nuovo codice " + comune_new );

									datavalue.value = comune_new;

									setTimeout( function () {
										$.post(WDBoiler.API, {
											format: 'json',
											action: 'wbsetclaimvalue',
											claim: pValue.id,
											snaktype: 'value',
											token: WDBoiler.EDIT_TOKEN,
											baserevid: entity.lastrevid,
											value: JSON.stringify( datavalue.value ),
											maxlag: WDBoiler.MAXLAG
										}, function (data) {
											if( data.success ) {
												WDBoiler.log("SAVED! Set references...");

												setTimeout( function () {
													$.post(WDBoiler.API, {
														format: 'json',
														action: 'wbsetreference',
														statement: pValue.id,
														token: WDBoiler.EDIT_TOKEN,
														snaks: JSON.stringify( get_reference_snaks() ),
														maxlag: WDBoiler.MAXLAG
													} )
													.done( function () {
														WDBoiler.log("Reference saved. Next.");
														do_next();
													} )
													.fail( function () {
														WDBoiler.log("hard fail saving reference. Stop.");
													} );
												},  WDBoiler.MIN_WRITE_TIMEOUT );

											} else {
												WDBoiler.log("Soft API failing saving. Retry.");
												console.log(data);
												do_retry();
											}
										} )
										.fail( function () {
											WDBoiler.log("Hard API Fail saving. Retry.");
											do_retry();
										} );
									}, WDBoiler.MIN_WRITE_TIMEOUT );
								}
								// endif v !== comune_new
							} else {
								WDBoiler.log(comune_name + " senza codice ISTAT. Skip.");
								do_next();
							}
						}
					} else {
						WDBoiler.log(comune_name + " has no claims. Skip. Next.");
						do_next();
					}
				}
			} else {
				WDBoiler.log("Soft API failure. Retry.");
				console.log(data);
				do_retry();
			}
		} )
		.fail( function () {
			WDBoiler.log("Hard API Fail");
			do_retry();
		} );
	}
	function get_reference_snaks() {
		return {
			P854: [
				{
					snaktype: 'value',
					property: 'P854',
					datavalue: {
						value: 'https://www.istat.it/storage/codici-unita-amministrative/Nuovo-assetto-territoriale-della-Sardegna.zip',
						type: 'string'
					},
					datatype: 'url'
				}
			],
			P1476: [
				{
					snaktype: 'value',
					property: 'P1476',
					datavalue: {
						value: {
							text: 'Nuovo assetto territoriale della sardegna',
							language: 'it'
						},
						type: 'monolingualtext'
					},
					datatype: 'monolingualtext'
				}
			],
			P2701: [
				{
					snaktype: 'value',
					property: 'P2701',
					datavalue: {
						value: {
							'entity-type': 'item',
							'numeric-id': 136218,
							id: 'Q136218'
						},
						type: 'wikibase-entityid'
					},
					datatype: 'wikibase-item'
				}
			],
			P407: [
				{
					snaktype: 'value',
					property: 'P407',
					datavalue: {
						value: {
							'entity-type': 'item',
							'numeric-id': 652,
							id: 'Q652'
						},
						type: 'wikibase-entityid'
					},
					datatype: 'wikibase-item'
				}
			],
			P813: [
				{
					snaktype: 'value',
					property: 'P813',
					datavalue: {
						value: {
							time: '+2017-07-22T00:00:00Z',
							timezone: 0,
							before: 0,
							after: 0,
							precision: 11,
							calendarmodel: 'http://www.wikidata.org/entity/Q1985727'
						},
						type: 'time'
					},
					datatype: 'time'
				}
			]
		};
	}
} );
