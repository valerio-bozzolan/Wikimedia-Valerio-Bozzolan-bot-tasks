/**
 * Script to remove wrong P2044 (altitude) with no references, added by a bot erroneusly.
 *
 * @licene: GNU General Public License v2+ or Creative Commons By Sa 4.0 International
 * @author: [[User:Valerio Bozzolan]]
 */
var WDBoiler = {
	data: null,
	timeout: null,
	current: 0,
	UCSTART: '2017-07-03T23:30:00Z',
	UCSEND:  '2017-07-03T00:00:01Z',
	UCUSER:  'Mr.Ibrahembot',
	PROPERTY: 'P2044',
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
		console.log("Token OK: " + WDBoiler.EDIT_TOKEN);
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
				list:    'usercontribs',
				ucuser:  WDBoiler.UCUSER,
				ucstart: WDBoiler.UCSTART,
				ucend:   WDBoiler.UCEND,
				ucshow:  'new|top',
				format:  'json',
				maxlag:  WDBoiler.MAXLAG
			};

			if( continueData ) {
				for(var continueArg in continueData) {
					data[ continueArg ] = continueData[ continueArg ];
				}
			}

			console.log( data );

			$.ajax( {
				url: WDBoiler.API,
				data: data
			} )
			.success( function ( userContribData ) {
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

		if( usercontrib ) {
			var q = usercontrib.title;

			WDBoiler.log("Fetching " + q + "...");

			var data = {
				action: 'wbgetclaims',
				entity: q,
				format: 'json',
				property: WDBoiler.PROPERTY,
				maxlag:   WDBoiler.MAXLAG
			};

			console.log( data );

			setTimeout( function () {
				$.ajax( {
					url: WDBoiler.API,
					data: data
				} )
				.success( function (data) {
					WDBoiler.log("Fetched " + q);

					console.log( data );

					if( data.claims && data.claims[ WDBoiler.PROPERTY ] ) {

						var firstClaim = data.claims[ WDBoiler.PROPERTY ][0];

						if( firstClaim.references ) {
							WDBoiler.log(firstClaim.id + " has " +  firstClaim.references.length + " references. Skip...");
							pop_usercontrib( usercontribs.shift(), latestCallback );
						} else {
							WDBoiler.log("Removing " + firstClaim.id + "...");

							var data = {
								action: 'wbremoveclaims',
								claim:   firstClaim.id,
								summary: '[[Topic:Tuh6pbxwni9n5gmc|wrong altitude]]',
								format:  'json',
								maxlag:  WDBoiler.MAXLAG,
								token:   WDBoiler.EDIT_TOKEN
							};

							console.log( data );

							setTimeout( function () {
								$.post(	WDBoiler.API, data, function ( data ) {
									if( data.success ) {
										WDBoiler.log("Success!");
										pop_usercontrib( usercontribs.shift(), latestCallback );
									} else {
										WDBoiler.log("ERROR soft fail. Retry...");
										console.log( data );
										pop_usercontrib( usercontribs, latestCallback );
									}
								} )
								.fail( function () {
									WDBoiler.log("ERROR hard fail. Retry...");
									pop_usercontrib( usercontribs, latestCallback );
								} );
							}, WDBoiler.MIN_WRITE_TIMEOUT );
						}
						// end check references
					} else {
						WDBoiler.log(q + " has NOT " + WDBoiler.PROPERTY + ". Skip...");
						pop_usercontrib( usercontribs.shift(), latestCallback );
					}
				} )
				.fail( function () {
					WDBoiler.log("ERROR hard fail. Retry...");
					pop_usercontrib( usercontribs, latestCallback );
				} );
			}, WDBoiler.MIN_TIMEOUT );
		} else {
			latestCallback();
		}
	}
} );

