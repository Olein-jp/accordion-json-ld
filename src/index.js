import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { createElement, Fragment } from '@wordpress/element';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const TARGET_BLOCKS = [ 'core/accordion' ];
const ATTRIBUTE_NAME = 'accordionJsonLdEnabled';

const addAttribute = ( settings, name ) => {
	if ( TARGET_BLOCKS.indexOf( name ) === -1 ) {
		return settings;
	}

	return {
		...settings,
		attributes: {
			...settings.attributes,
			accordionJsonLdEnabled: {
				type: 'boolean',
				default: false,
			},
		},
	};
};

addFilter(
	'blocks.registerBlockType',
	'accordion-json-ld/attribute',
	addAttribute
);

const withInspectorControl = createHigherOrderComponent( ( BlockEdit ) => {
	return ( props ) => {
		if ( TARGET_BLOCKS.indexOf( props.name ) === -1 ) {
			return createElement( BlockEdit, props );
		}

		const enabled = props.attributes[ ATTRIBUTE_NAME ];

		return createElement(
			Fragment,
			null,
			createElement( BlockEdit, props ),
			createElement(
				InspectorControls,
				null,
				createElement(
					PanelBody,
					{
						title: __( '構造化データ', 'accordion-json-ld' ),
						initialOpen: true,
					},
					createElement( ToggleControl, {
						label: __(
							'構造化データ（JSON-LD）を出力する',
							'accordion-json-ld'
						),
						checked: !! enabled,
						onChange: ( value ) => {
							props.setAttributes( {
								accordionJsonLdEnabled: value,
							} );
						},
					} )
				)
			)
		);
	};
}, 'withAccordionJsonLdInspector' );

addFilter(
	'editor.BlockEdit',
	'accordion-json-ld/inspector-control',
	withInspectorControl
);
