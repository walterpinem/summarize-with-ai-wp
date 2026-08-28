/**
 * Summarize with AI - editor block.
 *
 * Rendered server side so the block and the shortcode can never drift apart.
 *
 * @package SUMMARIZEAI
 */

( function ( blocks, element, blockEditor, components, i18n, serverSideRender ) {
	'use strict';

	var el = element.createElement;
	var __ = i18n.__;
	var InspectorControls = blockEditor.InspectorControls;
	var useBlockProps = blockEditor.useBlockProps;
	var ServerSideRender = serverSideRender;
	var settings = window.summarizeWithAIBlock || { services: [], defaults: {} };

	/**
	 * Toggle a service ID inside the selected list.
	 *
	 * @param {string[]} current  Currently selected service IDs.
	 * @param {string}   id       Service ID to toggle.
	 * @param {boolean}  selected Whether the service should be selected.
	 * @return {string[]} Updated list, in registry order.
	 */
	function toggleService( current, id, selected ) {
		var next = ( current || [] ).slice();

		if ( selected && next.indexOf( id ) === -1 ) {
			next.push( id );
		}

		if ( ! selected ) {
			next = next.filter( function ( item ) {
				return item !== id;
			} );
		}

		return settings.services
			.map( function ( service ) {
				return service.id;
			} )
			.filter( function ( serviceId ) {
				return next.indexOf( serviceId ) !== -1;
			} );
	}

	blocks.registerBlockType( 'summarizewithai/buttons', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var selected = attributes.services && attributes.services.length
				? attributes.services
				: settings.defaults.services || [];

			var serviceControls = settings.services.map( function ( service ) {
				return el( components.CheckboxControl, {
					key: service.id,
					__nextHasNoMarginBottom: true,
					label: service.label,
					checked: selected.indexOf( service.id ) !== -1,
					onChange: function ( isChecked ) {
						props.setAttributes( {
							services: toggleService( selected, service.id, isChecked )
						} );
					}
				} );
			} );

			var inspector = el(
				InspectorControls,
				{},
				el(
					components.PanelBody,
					{ title: __( 'Services', 'summarize-with-ai' ), initialOpen: true },
					serviceControls.length
						? serviceControls
						: el( 'p', {}, __( 'No AI services are registered.', 'summarize-with-ai' ) )
				),
				el(
					components.PanelBody,
					{ title: __( 'Appearance', 'summarize-with-ai' ), initialOpen: false },
					el( components.TextControl, {
						__nextHasNoMarginBottom: true,
						label: __( 'Label', 'summarize-with-ai' ),
						help: __( 'Leave empty to hide the text before the buttons.', 'summarize-with-ai' ),
						value: attributes.label === undefined ? settings.defaults.label || '' : attributes.label,
						onChange: function ( value ) {
							props.setAttributes( { label: value } );
						}
					} ),
					el( components.SelectControl, {
						__nextHasNoMarginBottom: true,
						label: __( 'Button style', 'summarize-with-ai' ),
						value: attributes.buttonStyle || settings.defaults.buttonStyle || 'filled',
						options: [
							{ label: __( 'Filled', 'summarize-with-ai' ), value: 'filled' },
							{ label: __( 'Outline', 'summarize-with-ai' ), value: 'outline' },
							{ label: __( 'Minimal', 'summarize-with-ai' ), value: 'minimal' }
						],
						onChange: function ( value ) {
							props.setAttributes( { buttonStyle: value } );
						}
					} ),
					el( components.SelectControl, {
						__nextHasNoMarginBottom: true,
						label: __( 'Layout', 'summarize-with-ai' ),
						value: attributes.layout || settings.defaults.layout || 'inline',
						options: [
							{ label: __( 'Inline', 'summarize-with-ai' ), value: 'inline' },
							{ label: __( 'Stacked', 'summarize-with-ai' ), value: 'stacked' }
						],
						onChange: function ( value ) {
							props.setAttributes( { layout: value } );
						}
					} ),
					el( components.ToggleControl, {
						__nextHasNoMarginBottom: true,
						label: __( 'Show service names', 'summarize-with-ai' ),
						checked: !! attributes.showText,
						onChange: function ( value ) {
							props.setAttributes( { showText: value } );
						}
					} ),
					el( components.ToggleControl, {
						__nextHasNoMarginBottom: true,
						label: __( 'Show copy-prompt button', 'summarize-with-ai' ),
						checked: !! attributes.showCopy,
						onChange: function ( value ) {
							props.setAttributes( { showCopy: value } );
						}
					} ),
					el( components.SelectControl, {
						__nextHasNoMarginBottom: true,
						label: __( 'Google preferred-source button', 'summarize-with-ai' ),
						help: __( 'Overrides the placement set on the Google settings tab, for this block only.', 'summarize-with-ai' ),
						value: attributes.googleSource || 'auto',
						options: [
							{ label: __( 'Follow the settings', 'summarize-with-ai' ), value: 'auto' },
							{ label: __( 'Hide it', 'summarize-with-ai' ), value: 'no' },
							{ label: __( 'First button in the row', 'summarize-with-ai' ), value: 'first' },
							{ label: __( 'Last button in the row', 'summarize-with-ai' ), value: 'last' },
							{ label: __( 'Its own row underneath', 'summarize-with-ai' ), value: 'row' }
						],
						onChange: function ( value ) {
							props.setAttributes( { googleSource: value } );
						}
					} )
				)
			);

			return el(
				'div',
				useBlockProps(),
				inspector,
				el( components.Disabled, {}, el( ServerSideRender, {
					block: 'summarizewithai/buttons',
					attributes: attributes
				} ) )
			);
		},

		save: function () {
			// Rendered by summarizewithai_render_block() in PHP.
			return null;
		}
	} );

	blocks.registerBlockType( 'summarizewithai/google-source', {
		edit: function ( props ) {
			var attributes = props.attributes;

			var inspector = el(
				InspectorControls,
				{},
				el(
					components.PanelBody,
					{ title: __( 'Button', 'summarize-with-ai' ), initialOpen: true },
					el( components.TextControl, {
						__nextHasNoMarginBottom: true,
						label: __( 'Label', 'summarize-with-ai' ),
						help: __( 'Placeholders such as {site_name} are replaced when the page is rendered.', 'summarize-with-ai' ),
						value: attributes.label === undefined ? settings.defaults.googleLabel || '' : attributes.label,
						onChange: function ( value ) {
							props.setAttributes( { label: value } );
						}
					} ),
					el( components.SelectControl, {
						__nextHasNoMarginBottom: true,
						label: __( 'Alignment', 'summarize-with-ai' ),
						value: attributes.align || 'left',
						options: [
							{ label: __( 'Left', 'summarize-with-ai' ), value: 'left' },
							{ label: __( 'Center', 'summarize-with-ai' ), value: 'center' },
							{ label: __( 'Right', 'summarize-with-ai' ), value: 'right' }
						],
						onChange: function ( value ) {
							props.setAttributes( { align: value } );
						}
					} )
				)
			);

			return el(
				'div',
				useBlockProps(),
				inspector,
				el( components.Disabled, {}, el( ServerSideRender, {
					block: 'summarizewithai/google-source',
					attributes: attributes
				} ) )
			);
		},

		save: function () {
			// Rendered by summarizewithai_render_google_source_block() in PHP.
			return null;
		}
	} );
} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.i18n,
	window.wp.serverSideRender
);
