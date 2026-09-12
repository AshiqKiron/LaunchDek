( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var root = document.querySelector( '.launchdek-admin' );

		if ( ! root ) {
			return;
		}

		root.setAttribute( 'data-launchdek-ready', 'true' );
	} );
}() );
