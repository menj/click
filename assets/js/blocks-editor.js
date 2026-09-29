/**
 * menj.click — editor side of the theme's dynamic blocks. Each block
 * previews its server rendering; content is edited under menj.click.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var __ = wp.i18n.__;
	var ServerSideRender = wp.serverSideRender;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var ToggleControl = wp.components.ToggleControl;
	var RangeControl = wp.components.RangeControl;
	var data = window.menjClickBlocks || { icons: {}, tones: {} };

	function options( map ) {
		return Object.keys( map ).map( function ( key ) {
			return { value: key, label: map[ key ] };
		} );
	}

	var modes = [
		{ value: 'featured', label: __( 'Chosen in menj.click settings', 'menj-click' ) },
		{ value: 'all', label: __( 'Every active short link', 'menj-click' ) }
	];

	var controls = {
		'menj/wordmark': function ( a, set ) {
			return [ el( SelectControl, { key: 'v', label: __( 'Style', 'menj-click' ), value: a.variant, options: [ { value: 'header', label: __( 'Header', 'menj-click' ) }, { value: 'footer', label: __( 'Footer (light on dark)', 'menj-click' ) } ], onChange: function ( v ) { set( { variant: v } ); } } ) ];
		},
		'menj/link-panel': function ( a, set ) {
			return [ el( SelectControl, { key: 'l', label: __( 'Layout', 'menj-click' ), value: a.layout, options: [ { value: 'list', label: __( 'List', 'menj-click' ) }, { value: 'grid', label: __( 'Grid', 'menj-click' ) } ], onChange: function ( v ) { set( { layout: v } ); } } ) ];
		},
		'menj/icon': function ( a, set ) {
			return [
				el( SelectControl, { key: 'n', label: __( 'Icon', 'menj-click' ), value: a.name, options: options( data.icons ), onChange: function ( v ) { set( { name: v } ); } } ),
				el( SelectControl, { key: 't', label: __( 'Colour', 'menj-click' ), value: a.tone, options: options( data.tones ), onChange: function ( v ) { set( { tone: v } ); } } ),
				el( RangeControl, { key: 's', label: __( 'Size', 'menj-click' ), value: a.size, min: 16, max: 96, onChange: function ( v ) { set( { size: v } ); } } )
			];
		},
		'menj/short-links': function ( a, set ) {
			return [
				el( SelectControl, { key: 'm', label: __( 'Show', 'menj-click' ), value: a.mode, options: modes, onChange: function ( v ) { set( { mode: v } ); } } ),
				el( ToggleControl, { key: 'c', label: __( 'Copy buttons', 'menj-click' ), checked: !! a.copy, onChange: function ( v ) { set( { copy: v } ); } } )
			];
		},
		'menj/qr-grid': function ( a, set ) {
			return [
				el( SelectControl, { key: 'm', label: __( 'Show', 'menj-click' ), value: a.mode, options: modes, onChange: function ( v ) { set( { mode: v } ); } } ),
				el( ToggleControl, { key: 'd', label: __( 'SVG and PNG download links', 'menj-click' ), checked: !! a.downloads, onChange: function ( v ) { set( { downloads: v } ); } } )
			];
		},
		'menj/latest-notes': function ( a, set ) {
			return [ el( RangeControl, { key: 'c', label: __( 'How many (0 uses the setting)', 'menj-click' ), value: a.count, min: 0, max: 6, onChange: function ( v ) { set( { count: v } ); } } ) ];
		}
	};

	[ 'wordmark', 'hero', 'link-panel', 'icon', 'short-links', 'qr-grid', 'latest-notes', 'notes-masthead', 'social', 'directory', 'listing' ].forEach( function ( slug ) {
		var name = 'menj/' + slug;
		wp.blocks.registerBlockType( name, {
			edit: function ( props ) {
				var blockProps = useBlockProps();
				var panel = controls[ name ]
					? el( InspectorControls, null, el( PanelBody, { title: __( 'Settings', 'menj-click' ) }, controls[ name ]( props.attributes, props.setAttributes ) ) )
					: null;
				return el( Fragment, null, panel, el( 'div', blockProps, el( ServerSideRender, { block: name, attributes: props.attributes } ) ) );
			},
			save: function () {
				return null;
			}
		} );
	} );
}( window.wp ) );
