/**
 * Script to remove wrong P2044 (elevation) with no references, added by a bot erroneusly.
 *
 * Run this script in your browser console in Wikidata.
 *
 * @licence: GNU General Public License v2+ or Creative Commons By Sa 4.0 International
 * @author: [[User:Valerio Bozzolan]]
 */
var WDBoiler = {
	//		 Set up your custom HTTPS proxy of 'http://www.geonames.org/getJSON'
	GEO_API: 'https://boz.reyboz.it/PROXY/geonames/getJSON',
	API:     'https://www.wikidata.org/w/api.php',
	UCSTART: '2017-07-03T23:30:00Z',
	UCSEND:  '2017-07-03T00:00:01Z',
	UCUSER:  'Mr.Ibrahembot',
	UCSHOW:  'new', // 'new|top'
	PROPERTY:'P2044',
	SANDBOX: false,
	ELEVATION_THRESHOLD: 10,
	MIN_TIMEOUT:       5000,
	MIN_WRITE_TIMEOUT: 60000,
	MAXLAG:            10,
	EDIT_TOKEN:        null,
	log: function (msg) {
		var now = new Date();
		$('#log').append('[' + now.getFullYear() + "/" + now.getMonth() + "/" + now.getDay() + " " + now.getHours() + ':' + now.getMinutes() + '] ' + msg + '\n')
		         .scrollTop( $('#log')[0].scrollHeight );
	},
	getSnakFromGeoID: function (geoID) {
		return {
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
						time: '+2017-07-26T00:00:00Z',
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
	},
	JSONElevationClaimValue: function (elevation) {
		var sign = elevation > 0 ? '+' : '-';
		return JSON.stringify( {
			amount: sign + elevation,
			unit: 'http://www.wikidata.org/entity/Q11573'
		} );
	},
	addReferenceToClaimGeo : function ( claimID, GeoNamesID, success, fail, softFail ) {

		WDBoiler.log("Setting reference to claim " + claimID + "...");

		var snak = WDBoiler.getSnakFromGeoID( GeoNamesID );

		console.log( snak );

		var data = {
			action:    'wbsetreference',
			format:    'json',
			statement: claimID,
			snaks:     JSON.stringify( snak ),
			maxlag:    WDBoiler.MAXLAG,
			token:     WDBoiler.EDIT_TOKEN
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
				action:   'wbgetclaims',
				format:   'json',
				entity:   q,
				maxlag:   WDBoiler.MAXLAG
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

								if( geoData && geoData.altitude ) {
									var intElevationGeoData = parseInt( geoData.altitude );

									WDBoiler.log("GeoNames elevation " + geoData.altitude + " ( " + intElevationGeoData + " )");

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

										if( diff > WDBoiler.ELEVATION_THRESHOLD ) {
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
													value:    WDBoiler.JSONElevationClaimValue( intElevationGeoData ),
													maxlag:   WDBoiler.MAXLAG,
													token:    WDBoiler.EDIT_TOKEN
												};

												console.log(data);

												setTimeout( function () {
													$.post(WDBoiler.API, data, function ( response ) {
														if( response.success ) {
															WDBoiler.addReferenceToClaimGeo( firstClaim.id, GeoNamesID, nextContrib, hardFail, softFail );
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
											value:    WDBoiler.JSONElevationClaimValue( intElevationGeoData ),
											maxlag:   WDBoiler.MAXLAG,
											token:    WDBoiler.EDIT_TOKEN 
										};

										console.log( data );

										setTimeout( function () {
											$.post(WDBoiler.API, data, function ( response ) {
												console.log( response );
												if( response.success ) {
													WDBoiler.log("Claim created.");
													WDBoiler.addReferenceToClaimGeo( response.claim.id, GeoNamesID, nextContrib, hardFail, softFail);
												} else {
													softFail();
												}
											} ).fail( hardFail );
										}, WDBoiler.MIN_WRITE_TIMEOUT );
									}
								} else {
									WDBoiler.log("Can't retrieve srt3 from " + GeoNamesID + ". Skip...");
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
