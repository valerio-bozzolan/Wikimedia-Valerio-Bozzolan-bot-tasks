/**
 * Open popups for delations of moved pages.
 * Run it in your Mozilla Firefox console.
 *
 * @author Valerio Bozzolan
 * @license public domain
 * @date 2017-08-06
 *
 * @param string pages CSV pages in form "Template:A;Template:B\nTitle A;Title B\n" ecc.
 * @param string|undefined wpReason
 */
var OpenDeletionPopups = function ( pages, wpReason ) {
        var script = mw.config.get('wgScript');

        var L10N = {
                wpReason: 'spostato verso [[{b}]]'
        };

        wpReason = wpReason || L10N.wpReason;

        function openInNewTab(url) {
                var win = window.open(url, '_blank');
                win && win.focus();
        }

        var lines = pages.split('\n');
        for(var line in lines) {
                var ab = lines[line].split(';');
                var a = ab[0], b = ab[1];

                b = b.replace('Template:', 'T:');

                openInNewTab( script + '?' + $.param( {
                        title:    a,
                        action:   'delete',
                        wpReason: wpReason.replace('{b}', b)
                } ) );
        }
};
