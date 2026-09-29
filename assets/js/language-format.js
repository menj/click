/**
 * menj.click — Language tools for the editor.
 *
 * 1. A "Language" button in the formatting toolbar tags selected text with
 *    lang/dir (span.menj-lang), e.g. a Hebrew word inside English prose.
 * 2. A "Language" setting on text blocks tags the whole block. It is stored
 *    in the block comment and applied on render, so core blocks stay valid.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var __ = wp.i18n.__;
	var richText = wp.richText;
	var BlockControls = wp.blockEditor.BlockControls;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var ToolbarGroup = wp.components.ToolbarGroup;
	var ToolbarDropdownMenu = wp.components.ToolbarDropdownMenu;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var createHOC = wp.compose.createHigherOrderComponent;
	var LANGS = window.menjClickLanguages || {};
	var FORMAT = 'menj/lang';
	var BLOCKS = [ 'core/paragraph', 'core/heading', 'core/quote', 'core/pullquote', 'core/verse', 'core/list', 'core/list-item', 'core/preformatted', 'core/table' ];

	function attrsFor( key ) {
		var def = LANGS[ key ];
		var out = {};
		if ( def.lang ) {
			out.lang = def.lang;
		}
		if ( def.dir ) {
			out.dir = def.dir;
		}
		if ( def.variant ) {
			out.variant = def.variant;
		}
		return out;
	}

	function matches( active, key ) {
		var want = attrsFor( key );
		return ( active.lang || '' ) === ( want.lang || '' ) && ( active.variant || '' ) === ( want.variant || '' );
	}

	/* 1. Inline format. */
	richText.registerFormatType( FORMAT, {
		title: __( 'Language', 'menj-click' ),
		tagName: 'span',
		className: 'menj-lang',
		attributes: { lang: 'lang', dir: 'dir', variant: 'data-menj-variant' },
		edit: function ( props ) {
			var active = props.isActive ? ( props.activeAttributes || {} ) : {};
			var items = Object.keys( LANGS ).map( function ( key ) {
				return {
					title: LANGS[ key ].label,
					isActive: props.isActive && matches( active, key ),
					onClick: function () {
						var value = richText.removeFormat( props.value, FORMAT );
						props.onChange( richText.applyFormat( value, { type: FORMAT, attributes: attrsFor( key ) } ) );
					}
				};
			} );
			if ( props.isActive ) {
				items.push( {
					title: __( 'Remove language', 'menj-click' ),
					onClick: function () {
						props.onChange( richText.removeFormat( props.value, FORMAT ) );
					}
				} );
			}
			return el( BlockControls, { group: 'inline' },
				el( ToolbarGroup, null,
					el( ToolbarDropdownMenu, { icon: 'translation', label: __( 'Language', 'menj-click' ), controls: items } )
				)
			);
		}
	} );

	/* 2. Block setting. */
	wp.hooks.addFilter( 'blocks.registerBlockType', 'menj-click/lang-attribute', function ( settings, name ) {
		if ( BLOCKS.indexOf( name ) === -1 ) {
			return settings;
		}
		settings.attributes = Object.assign( {}, settings.attributes, { menjLang: { type: 'string', default: '' } } );
		return settings;
	} );

	var choices = [ { value: '', label: __( 'Same as the site', 'menj-click' ) } ].concat(
		Object.keys( LANGS ).map( function ( key ) {
			return { value: key, label: LANGS[ key ].label };
		} )
	);

	wp.hooks.addFilter( 'editor.BlockEdit', 'menj-click/lang-panel', createHOC( function ( BlockEdit ) {
		return function ( props ) {
			if ( BLOCKS.indexOf( props.name ) === -1 ) {
				return el( BlockEdit, props );
			}
			return el( Fragment, null,
				el( BlockEdit, props ),
				el( InspectorControls, null,
					el( PanelBody, { title: __( 'Language', 'menj-click' ), initialOpen: !! props.attributes.menjLang },
						el( SelectControl, {
							label: __( 'Language of this block', 'menj-click' ),
							value: props.attributes.menjLang || '',
							options: choices,
							help: __( 'Sets the typeface and reading direction.', 'menj-click' ),
							onChange: function ( value ) {
								props.setAttributes( { menjLang: value } );
							}
						} )
					)
				)
			);
		};
	}, 'withMenjLangPanel' ) );

	wp.hooks.addFilter( 'editor.BlockListBlock', 'menj-click/lang-wrapper', createHOC( function ( BlockListBlock ) {
		return function ( props ) {
			var key = props.attributes && props.attributes.menjLang;
			if ( ! key || ! LANGS[ key ] ) {
				return el( BlockListBlock, props );
			}
			var def = LANGS[ key ];
			var wrapperProps = Object.assign( {}, props.wrapperProps );
			if ( def.lang ) {
				wrapperProps.lang = def.lang;
			}
			if ( def.dir ) {
				wrapperProps.dir = def.dir;
			}
			if ( def.variant ) {
				wrapperProps[ 'data-menj-variant' ] = def.variant;
			}
			return el( BlockListBlock, Object.assign( {}, props, { wrapperProps: wrapperProps } ) );
		};
	}, 'withMenjLangWrapper' ) );
}( window.wp ) );
