/**
 * menj.click — settings screen behaviour. No dependencies.
 */
( function () {
	'use strict';

	var i18n = window.menjClickAdmin || {};
	var counter = Date.now();

	/* Repeaters: add, reorder, remove rows. */
	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '[data-repeater-add], [data-repeater-remove], [data-repeater-up], [data-repeater-down]' );
		if ( ! button ) {
			return;
		}
		var repeater = button.closest( '[data-repeater]' );
		var rows = repeater.querySelector( '[data-repeater-rows]' );
		var row = button.closest( '[data-repeater-row]' );

		if ( button.hasAttribute( 'data-repeater-add' ) ) {
			var template = repeater.querySelector( '[data-repeater-template]' );
			var html = template.innerHTML.replace( /__i__/g, 'n' + ( counter++ ) );
			rows.insertAdjacentHTML( 'beforeend', html );
			var added = rows.lastElementChild;
			var first = added && added.querySelector( 'input, select' );
			if ( first ) {
				first.focus();
			}
		} else if ( button.hasAttribute( 'data-repeater-remove' ) ) {
			row.remove();
		} else if ( button.hasAttribute( 'data-repeater-up' ) && row.previousElementSibling ) {
			rows.insertBefore( row, row.previousElementSibling );
			button.focus();
		} else if ( button.hasAttribute( 'data-repeater-down' ) && row.nextElementSibling ) {
			rows.insertBefore( row.nextElementSibling, row );
			button.focus();
		}
	} );

	/* Copy buttons. */
	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '[data-copy]' );
		if ( ! button || ! navigator.clipboard ) {
			return;
		}
		navigator.clipboard.writeText( button.getAttribute( 'data-copy' ) ).then( function () {
			var label = button.getAttribute( 'aria-label' );
			button.classList.add( 'is-copied' );
			button.setAttribute( 'aria-label', i18n.copied || 'Copied' );
			setTimeout( function () {
				button.classList.remove( 'is-copied' );
				button.setAttribute( 'aria-label', label );
			}, 1600 );
		} );
	} );

	/* Confirm destructive forms. */
	document.addEventListener( 'submit', function ( event ) {
		var message = event.target.getAttribute( 'data-confirm' );
		if ( message && ! window.confirm( message ) ) {
			event.preventDefault();
		}
	} );

	/* Delete-all: enable only after typing DELETE. */
	document.querySelectorAll( '[data-delete-all]' ).forEach( function ( form ) {
		var input = form.querySelector( '[data-delete-input]' );
		var button = form.querySelector( '[data-delete-button]' );
		input.addEventListener( 'input', function () {
			button.disabled = 'DELETE' !== input.value.trim();
		} );
	} );

	/* Hero background: show the image picker only for "Photo". */
	document.querySelectorAll( '[data-hero-background]' ).forEach( function ( group ) {
		var picker = document.querySelector( '[data-hero-image]' );
		group.addEventListener( 'change', function ( event ) {
			if ( picker && 'radio' === event.target.type ) {
				picker.hidden = 'image' !== event.target.value;
			}
		} );
	} );

	/* Media library picker. */
	document.querySelectorAll( '[data-media]' ).forEach( function ( box ) {
		var input = box.querySelector( '[data-media-input]' );
		var preview = box.querySelector( '[data-media-preview]' );
		var clear = box.querySelector( '[data-media-clear]' );
		var frame;

		box.querySelector( '[data-media-pick]' ).addEventListener( 'click', function () {
			if ( ! window.wp || ! window.wp.media ) {
				return;
			}
			if ( ! frame ) {
				frame = window.wp.media( {
					title: i18n.chooseImage || 'Choose image',
					button: { text: i18n.useImage || 'Use this image' },
					library: { type: 'image' },
					multiple: false
				} );
				frame.on( 'select', function () {
					var file = frame.state().get( 'selection' ).first().toJSON();
					var sizes = file.sizes || {};
					input.value = file.id;
					preview.src = ( sizes.medium || sizes.full || file ).url;
					preview.hidden = false;
					clear.hidden = false;
				} );
			}
			frame.open();
		} );

		clear.addEventListener( 'click', function () {
			input.value = '0';
			preview.hidden = true;
			clear.hidden = true;
		} );
	} );

	/* Range readouts. */
	document.querySelectorAll( '[data-range]' ).forEach( function ( range ) {
		var output = document.querySelector( 'output[for="' + range.id + '"]' );
		range.addEventListener( 'input', function () {
			if ( output ) {
				output.textContent = range.value + '%';
			}
		} );
	} );

	/* Slug inputs: keep to lowercase letters, digits, - and _. */
	document.querySelectorAll( '[data-slug-input]' ).forEach( function ( input ) {
		input.addEventListener( 'input', function () {
			var clean = input.value.toLowerCase().replace( /^\/+/, '' ).replace( /[^a-z0-9_-]/g, '' ).slice( 0, 64 );
			if ( clean !== input.value ) {
				input.value = clean;
			}
		} );
	} );

	/* Warn about 301s. */
	document.querySelectorAll( '[data-warn-301]' ).forEach( function ( warning ) {
		var form = warning.closest( 'form' );
		form.addEventListener( 'change', function ( event ) {
			if ( event.target.hasAttribute( 'data-redirect-type' ) ) {
				warning.hidden = '301' !== event.target.value;
			}
		} );
	} );
}() );
