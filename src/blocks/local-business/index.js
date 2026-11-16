/**
 * JAT Search Intel — Local Business Block
 *
 * Shows a saved location's details, opening hours or map.
 * Dynamic block — rendered by Local_Blocks::render_block() in PHP.
 */

import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl, ToggleControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

registerBlockType( 'jat-search-intel/local-business', {
	apiVersion: 3,
	title: __( 'Local Business', 'jat-search-intel' ),
	description: __(
		'Your business details, opening hours or a map, from JAT Search Intel > Settings > Local Business.',
		'jat-search-intel'
	),
	category: 'widgets',
	icon: 'store',
	keywords: [
		__( 'address', 'jat-search-intel' ),
		__( 'hours', 'jat-search-intel' ),
		__( 'map', 'jat-search-intel' ),
		__( 'local seo', 'jat-search-intel' ),
	],
	supports: { html: false, align: [ 'wide', 'full' ] },
	attributes: {
		display: { type: 'string', default: 'info' },
		location: { type: 'number', default: 1 },
		showName: { type: 'boolean', default: true },
	},

	edit( { attributes, setAttributes } ) {
		const blockProps = useBlockProps();
		const { display, location, showName } = attributes;

		return (
			<div { ...blockProps }>
				<InspectorControls>
					<PanelBody title={ __( 'Local Business', 'jat-search-intel' ) }>
						<SelectControl
							label={ __( 'Show', 'jat-search-intel' ) }
							value={ display }
							options={ [
								{ label: __( 'Business details', 'jat-search-intel' ), value: 'info' },
								{ label: __( 'Opening hours', 'jat-search-intel' ), value: 'hours' },
								{ label: __( 'Map and directions', 'jat-search-intel' ), value: 'map' },
							] }
							onChange={ ( value ) => setAttributes( { display: value } ) }
						/>
						<TextControl
							label={ __( 'Location number', 'jat-search-intel' ) }
							help={ __( '1 for the first location in settings, 2 for the second, and so on.', 'jat-search-intel' ) }
							type="number"
							min="1"
							value={ location }
							onChange={ ( value ) => setAttributes( { location: Math.max( 1, parseInt( value, 10 ) || 1 ) } ) }
						/>
						{ display === 'info' && (
							<ToggleControl
								label={ __( 'Show business name', 'jat-search-intel' ) }
								checked={ showName }
								onChange={ ( value ) => setAttributes( { showName: value } ) }
							/>
						) }
					</PanelBody>
				</InspectorControls>
				<ServerSideRender block="jat-search-intel/local-business" attributes={ attributes } />
			</div>
		);
	},

	save() {
		return null;
	},
} );
