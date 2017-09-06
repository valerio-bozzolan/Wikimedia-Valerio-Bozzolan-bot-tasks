/**
 * Script to remove wrong P2044 (elevation) with no references, added by a bot erroneusly.
 *
 * Run this script in your browser console in Wikidata.
 *
 * @licence GNU General Public License v2+ or Creative Commons By Sa 4.0 International
 * @author [[User:Valerio Bozzolan]]
 */
var WDBoiler = {
	//		 Set up your custom HTTPS proxy of 'http://www.geonames.org/getJSON'
	GEO_API: 'https://boz.reyboz.it/PROXY/geonames/getJSON',
	API:     'https://www.wikidata.org/w/api.php',
	UCSTART: '2017-07-03T23:30:00Z',
	UCSEND:  '2017-07-03T00:00:01Z',
	UCUSER:  'Mr.Ibrahembot',
	UCSHOW:  'new|top', // 'new|top'
	PROPERTY:'P2044',
	SANDBOX: false,
	ELEVATION_THRESHOLD: 7,
	MIN_TIMEOUT:       2500,
	MIN_WRITE_TIMEOUT: 6000,
	MAXLAG:            10,
	EDIT_TOKEN:        null,
	ME: 'Valerio Bozzolan bot',
	log: function (msg) {
		var now = new Date();
		$('#log').val(
			$('#log').val() +
			'[' +
			now.getFullYear() + "/" +
			now.getMonth() + "/" +
			now.getDay() + " " +
			now.getHours() + ':' +
			now.getMinutes() + ':' +
			now.getSeconds() + '] ' + msg +
			'\n'
		)
		.scrollTop( $('#log')[0].scrollHeight );
	},
	/**
	 * @return [ value, source, +-value ]
	 */
	getGeoNamesElevation: function (item) {
		if( item.elevation ) {
		    return [ item.elevation, 'elevation', null ];
		}
		if( item.altitude ) {
			return [ item.altitude, 'altitude', null ];
		}
		if( item.srtm3 && item.srtm3 !== -32768 ) {
			// Uncertainties in the Shuttle Radar Topography Mission (SRTM) Heights: Insights from the Indian Himalaya and Peninsula
			// https://www.ncbi.nlm.nih.gov/pmc/articles/PMC5296860/
		    return [ item.srtm3, 'srtm3', 16 ];
		}
		if( item.gtopo30 && item.gtopo30 != -9999 ) {
			// GTOPO30 Documentation
			// https://webgis.wr.usgs.gov/globalgis/gtopo30/gtopo30.htm#h31
		    return [ item.gtopo30, 'gtopo30', 30 ];
		}
		return [ null, 'none', null ];
	},
	getWikidataTime: function () {
		var now = new Date();

		function zeroFill(n, fill) {
			var s = '' + n;
			fill = fill || 2;
			while( s.length < fill ) {
				s = '0' + s;
			}
			return s;
		}

		// '+2017-07-31T00:00:00Z'
		// Precision: day
		var date =
			'+' +     now.getUTCFullYear()   + '-' +
			zeroFill( now.getUTCMonth() + 1) + '-' +
			zeroFill( now.getUTCDate()     ) + 'T' +
			zeroFill( 0 ) + ':' +
			zeroFill( 0 ) + ':' +
			zeroFill( 0 ) + 'Z';

		return date;
	},
	getSnakFromGeo: function (geoID, source) {
		var data = {
			P854: [{
				snaktype: 'value',
				property: 'P854',
				datavalue: {
					value: 'http://www.geonames.org/' + geoID,
					type: 'string'
				},
				datatype: 'url'
			}],
			P123: [{
				snaktype: 'value',
				property: 'P123',
				datavalue: {
					value: {
						'entity-type': 'item',
						'numeric-id': 830106,
						id: 'Q830106'
					},
					type: 'wikibase-entityid'
				},
				datatype: 'wikibase-item'
			}],
			P813: [{
				snaktype: 'value',
				property: 'P813',
				datavalue: {
					value: {
						time: WDBoiler.getWikidataTime(),
						timezone: 0,
						before: 0,
						after: 0,
						precision: 11,
						calendarmodel: 'http://www.wikidata.org/entity/Q1985727'
					},
					type: 'time'
				},
				datatype: 'time'
			}]
		};

		if( source === 'gtopo30' ) {
			// Imported from GTOPO30
			data['P143'] = [{
				snaktype: 'value',
				property: 'P143',
				datavalue: {
					value: {
						'entity-type': 'item',
						'numeric-id': 1487345,
						id: 'Q1487345'
					},
					type: 'wikibase-entityid'
				},
				datatype: 'wikibase-item'
			}];
		} else if( source === 'srtm3' ) {
			// Imported from Shuttle Radar Topography Mission
			data['P143'] = [{
				snaktype: 'value',
				property: 'P143',
				datavalue: {
					value: {
						'entity-type': 'item',
						'numeric-id': 965136,
						id: 'Q965136'
					},
					type: 'wikibase-entityid'
				},
				datatype: 'wikibase-item'
			}];
		}

		return data;
	},
	JSONElevationClaimValue: function (elevation, absDiscard) {
		var data = {
			amount: elevation,
			unit: 'http://www.wikidata.org/entity/Q11573'
		};
		if( absDiscard ) {
			data.lowerBound = elevation - absDiscard;
			data.upperBound = elevation + absDiscard;
		}
		return JSON.stringify( data );
	},
	addReferenceToClaimGeo: function ( claimID, GeoNamesID, source, success, fail, softFail ) {

		WDBoiler.log("Setting reference to claim " + claimID + "...");

		var snak = WDBoiler.getSnakFromGeo( GeoNamesID, source );

		console.log( snak );

		var data = {
			action:    'wbsetreference',
			format:    'json',
			statement: claimID,
			snaks:     JSON.stringify( snak ),
			maxlag:    WDBoiler.MAXLAG,
			token:     WDBoiler.EDIT_TOKEN,
			bot:       1
		};

		console.log( data );

		setTimeout( function () {
			$.post(WDBoiler.API, data, function ( response ) {
				console.log( response );
				if( response.success ) {
					WDBoiler.log("Reference OK!");
					success( response );
				} else {
					WDBoiler.log("ERROR soft fail.");
					softFail && softFail() || fail();
				}
			} ).fail( function () {
				WDBoiler.log("ERROR hard fail.");
				fail();
			} );
		}, WDBoiler.MIN_WRITE_TIMEOUT );
	}
};

$(document).ready( function () {

	// I forget to log in as my bot :)
	if( ! WDBoiler.SANDBOX && mw.user.getName() !== WDBoiler.ME ) {
		alert("You are not '" + WDBoiler.ME + "'... isn't it?");
		return;
	}

	$('#firstHeading').text('[BOT]');
	document.title = '[BOT]';

	window.onbeforeunload = function(e) {
		return 'Sure?';
	};

	mw.loader.using( 'mediawiki.ForeignApi' );

	if( mw && mw.user.tokens.get( 'editToken' ) ) {
		WDBoiler.EDIT_TOKEN = mw.user.tokens.get( 'editToken' );
		console.log("Token " + WDBoiler.EDIT_TOKEN);
	}

	var $D = $('#bodyContent').empty();

	$D.append('<button type="button" id="datatable-api-sandbox-play">#play</button>');
	$D.append('<textarea id="log" class="expand"></textarea>');

	$('#datatable-api-sandbox-play').click( function () {
		fetch_contribs();
	} );

	function fetch_contribs( continueData ) {
		WDBoiler.log("Fetching contribs...");

		setTimeout( function () {
			var data = {
				action:  'query',
				format:  'json',
				list:    'usercontribs',
				ucuser:  WDBoiler.UCUSER,
				ucstart: WDBoiler.UCSTART,
				ucend:   WDBoiler.UCEND,
				ucshow:  WDBoiler.UCSHOW,
				maxlag:  WDBoiler.MAXLAG
			};

			if( continueData ) {
				for(var continueArg in continueData) {
					data[ continueArg ] = continueData[ continueArg ];
				}
			}

			console.log( data );

			$.getJSON(WDBoiler.API, data, function ( userContribData ) {
				console.log( userContribData );

				if( userContribData && userContribData.query && userContribData.query.usercontribs ) {
					pop_usercontrib( userContribData.query.usercontribs, function () {
						if( userContribData.continue ) {
							WDBoiler.log("Continuing...");
							fetch_contribs( userContribData.continue );
						} else {
							WDBoiler.log("Reached latest contrib! END.");
						}
					} );
				} else {
					WDBoiler.log("Soft API failure. Retry.");
					fetch_contribs( userContribData.continue );
				}
			} )
			.fail( function () {
				WDBoiler.log("Hard API Fail. Retry.");
				fetch_contribs( userContribData.continue );
			} );
		},  WDBoiler.MIN_TIMEOUT );
	}
	function pop_usercontrib( usercontribs, latestCallback ) {

		var usercontrib = usercontribs[0];

		function retryContrib() {
			pop_usercontrib( usercontribs, latestCallback );
		}
		function skipContrib() {
			pop_usercontrib( usercontribs.shift(), latestCallback );
		}
		function nextContrib() {
			WDBoiler.log("OK. Next...");
			skipContrib();			
		}
		function hardFail() {
			WDBoiler.log("ERROR hard fail. Retry...");
			retryContrib();
		}
		function softFail() {
			WDBoiler.log("ERROR soft fail. Retry...");
			retryContrib();
		}

		if( usercontrib ) {
			var q = usercontrib.title;

			if( WDBoiler.SANDBOX ) {
				q = 'Q4115189';
			}

			WDBoiler.log("Fetching " + q + "...");

			var data = {
				action: 'wbgetclaims',
				format: 'json',
				entity: q,
				maxlag: WDBoiler.MAXLAG
			};

			console.log( data );

			setTimeout( function () {
				$.getJSON(WDBoiler.API, data, function ( qData ) {
					WDBoiler.log("Fetched " + q);

					console.log( qData );

					if( ! qData.claims ) {
						WDBoiler.log("No claims. Skip...");
						skipContrib();
						return;
					}

					// get GeoNames ID
					if( qData.claims['P1566'] ) {
						var GeoNamesID = qData.claims['P1566'][0].mainsnak.datavalue.value;

						WDBoiler.log("Fetching GeoNamesID " + GeoNamesID + "...");

						// fetch GeoNames elevation
						setTimeout( function () {

							$.ajax( WDBoiler.GEO_API, {
								data: { id: GeoNamesID },
								dataType: 'jsonp',
								crossOrigin: true
							} )
							.success( function ( geoData ) {
								console.log(geoData);

								var geoDataElevation        = WDBoiler.getGeoNamesElevation(geoData);
								var geoDataElevationValue   = geoDataElevation[0];
								var geoDataElevationSource  = geoDataElevation[1];
								var geoDataElevationDiscard = geoDataElevation[2];

								if( geoData && geoDataElevationSource !== 'none' ) {

									var intElevationGeoData = parseInt( geoDataElevationValue );
									WDBoiler.log(
										"GeoNames elevation source: " + geoDataElevationSource +
										" value: " + geoDataElevationValue + " (" + intElevationGeoData + ")" +
										" precision: ±" + geoDataElevationDiscard
									);

									if( qData.claims[ WDBoiler.PROPERTY ] ) {
										// Check if wrong
										WDBoiler.log("Check current elevation");

										var firstClaim = qData.claims[ WDBoiler.PROPERTY ][0];

										console.log( firstClaim );

										var unit = firstClaim.mainsnak.datavalue.value.unit;

										// If not meters, skip
										if( unit !== 'http://www.wikidata.org/entity/Q11573' ) {
											WDBoiler.log("Units are not meters. Skip...");
											skipContrib();
											return;
										} 

										var elevation    = firstClaim.mainsnak.datavalue.value.amount;
										var intElevation = parseInt( elevation );

										WDBoiler.log("Elevation " + elevation + " ( " + intElevation + " )");

										var diff = Math.abs( intElevation - intElevationGeoData );

										WDBoiler.log("They diverge " + diff + " (threshold is " + WDBoiler.ELEVATION_THRESHOLD + ")");

										function setReferences() {
											WDBoiler.addReferenceToClaimGeo( firstClaim.id, GeoNamesID, geoDataElevationSource, nextContrib, hardFail, softFail );
										}

										if( diff === 0 ) {
											if( ! firstClaim.references || firstClaim.references.length === 0 ) {
												WDBoiler.log("No references! Setting...");
												setReferences();
											} else {
												WDBoiler.log("Yet references. Skip...");
											}
										} else if( diff > WDBoiler.ELEVATION_THRESHOLD ) {
											if( firstClaim.references && firstClaim.references.length ) {
												WDBoiler.log("Don't update elevation because it already has " + firstClaim.references.length + " references! Skip...");
												skipContrib();
											} else {
												WDBoiler.log("Fixing elevation...");

												var data = {
													action:   'wbsetclaimvalue',
													format:   'json',
													claim:    firstClaim.id,
													snaktype: 'value',
													value:    WDBoiler.JSONElevationClaimValue( geoDataElevationValue, geoDataElevationDiscard ),
													maxlag:   WDBoiler.MAXLAG,
													token:    WDBoiler.EDIT_TOKEN,
													bot:      1
												};

												console.log(data);

												setTimeout( function () {
													$.post(WDBoiler.API, data, function ( response ) {
														if( response.success ) {
															setReferences();
														} else {
															softFail();
														}
													} )
													.fail( hardFail );
												}, WDBoiler.MIN_WRITE_TIMEOUT );
											}
										} else {
											WDBoiler.log("Nothing to do. Skip...");
											skipContrib();
										}
									} else {
										// Insert Value
										WDBoiler.log(q + " has NOT " + WDBoiler.PROPERTY + ". Adding...");

										var data = {
											action:   'wbcreateclaim',
											format:   'json',
											entity:   q,
											property: WDBoiler.PROPERTY,
											snaktype: 'value',
											value:    WDBoiler.JSONElevationClaimValue( geoDataElevationValue, geoDataElevationDiscard ),
											maxlag:   WDBoiler.MAXLAG,
											token:    WDBoiler.EDIT_TOKEN,
											bot:      1
										};

										console.log( data );

										setTimeout( function () {
											$.post(WDBoiler.API, data, function ( response ) {
												console.log( response );
												if( response.success ) {
													WDBoiler.log("Claim created.");
													WDBoiler.addReferenceToClaimGeo( response.claim.id, GeoNamesID, geoDataElevationSource, nextContrib, hardFail, softFail);
												} else {
													softFail();
												}
											} ).fail( hardFail );
										}, WDBoiler.MIN_WRITE_TIMEOUT );
									}
								} else {
									WDBoiler.log("Can't retrieve elevation from " + GeoNamesID + ". Skip...");
									skipContrib();
								}
							} ).fail( hardFail );
						}, WDBoiler.MIN_TIMEOUT );
					} else {
						WDBoiler.log("No GeoNames ID. Skip...");
						skipContrib();
					}
				} ).fail( hardFail );

			}, WDBoiler.MIN_TIMEOUT );
		} else {
			latestCallback();
		}
	}
} );
