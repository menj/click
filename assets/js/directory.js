/**
 * menj.click — directory forms: character counters, copy buttons,
 * confirmations, and hints that follow the chosen listing type.
 */
( function () {
	'use strict';

	/* Character counters. */
	document.querySelectorAll( '[data-menj-counter]' ).forEach( function ( field ) {
		var max = parseInt( field.getAttribute( 'data-menj-counter' ), 10 );
		var out = document.querySelector( '[data-menj-counter-for="' + field.id + '"]' );
		if ( ! out || ! max ) {
			return;
		}
		var update = function () {
			var left = max - field.value.length;
			out.textContent = left;
			out.classList.toggle( 'is-over', left < 0 );
		};
		field.addEventListener( 'input', update );
		update();
	} );

	/* Copy buttons (link-back code). */
	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '[data-menj-copy]' );
		if ( ! button || ! navigator.clipboard ) {
			return;
		}
		navigator.clipboard.writeText( button.getAttribute( 'data-menj-copy' ) ).then( function () {
			var label = button.textContent;
			button.textContent = button.getAttribute( 'data-copied' ) || 'Copied';
			setTimeout( function () {
				button.textContent = label;
			}, 1600 );
		} );
	} );

	/* Confirm destructive actions. */
	document.addEventListener( 'submit', function ( event ) {
		var message = event.target.getAttribute( 'data-menj-confirm' );
		if ( message && ! window.confirm( message ) ) {
			event.preventDefault();
		}
	} );

	/* Pay page: options apply straight away; length only matters when paying once. */
	document.querySelectorAll( '[data-menj-autosubmit]' ).forEach( function ( form ) {
		form.addEventListener( 'change', function () {
			var once = form.querySelector( 'input[name="renew"][value="once"]' );
			var periods = form.querySelector( '[data-menj-periods]' );
			if ( periods && once ) {
				periods.hidden = ! once.checked;
			}
			form.submit();
		} );
	} );

	/* Listing type: payment note, and the link-back box only for Free. */
	document.querySelectorAll( '[data-menj-form]' ).forEach( function ( form ) {
		var note = form.querySelector( '[data-menj-pay-note]' );
		var recpr = form.querySelector( '[data-menj-recpr]' );
		var sync = function () {
			var chosen = form.querySelector( 'input[name="listing[tier]"]:checked' );
			if ( ! chosen ) {
				return;
			}
			var paid = '1' === chosen.getAttribute( 'data-paid' );
			if ( note ) {
				note.hidden = ! paid;
			}
			if ( recpr ) {
				recpr.hidden = paid;
			}
		};
		form.addEventListener( 'change', sync );
		sync();
	} );
}() );
