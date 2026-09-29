/**
 * menj.click — listings screen: ask for a reason when rejecting.
 */
( function () {
	'use strict';

	var i18n = window.menjClickDirAdmin || {};

	document.addEventListener( 'click', function ( event ) {
		var link = event.target.closest( '[data-menj-reject]' );
		if ( ! link ) {
			return;
		}
		event.preventDefault();
		var reason = window.prompt( i18n.rejectPrompt || 'Reason (optional):', '' );
		if ( null === reason ) {
			return;
		}
		var url = new URL( link.href );
		url.searchParams.set( 'reason', reason );
		window.location.href = url.toString();
	} );
}() );
