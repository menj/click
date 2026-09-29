/**
 * menj.click — copy buttons on the Shorts page and home page.
 */
( function () {
	'use strict';

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '[data-menj-copy]' );
		if ( ! button || ! navigator.clipboard ) {
			return;
		}
		navigator.clipboard.writeText( button.getAttribute( 'data-menj-copy' ) ).then( function () {
			var label = button.textContent;
			button.textContent = button.getAttribute( 'data-copied' ) || 'Copied';
			button.classList.add( 'is-copied' );
			setTimeout( function () {
				button.textContent = label;
				button.classList.remove( 'is-copied' );
			}, 1600 );
		} );
	} );
}() );
