/**
 * Search Intel — Breadcrumbs Block
 *
 * Theme-aware breadcrumb navigation.
 * Dynamic block — rendered via PHP on frontend.
 */

import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl } from '@wordpress/components';

registerBlockType( 'search-intel/breadcrumbs', {
	title: __( 'Breadcrumbs', 'search-intel' ),
	description: __( 'Theme-aware breadcrumb navigation with BreadcrumbList schema.', 'search-intel' ),
	category: 'widgets',
	icon: 'arrow-right-alt',
	keywords: [ __( 'breadcrumbs', 'search-intel' ), __( 'navigation', 'search-intel' ), __( 'path', 'search-intel' ) ],
	supports: {
		html: false,
		align: [ 'wide', 'full' ],
	},
	attributes: {
		separator: {
			type: 'string',
			default: '›',
		},
		homeLabel: {
			type: 'string',
			default: 'Home',
		},
		showHome: {
			type: 'boolean',
			default: true,
		},
	},

	edit( { attributes, setAttributes } ) {
		const blockProps = useBlockProps( { className: 'si-breadcrumbs-block' } );
		const { separator, homeLabel } = attributes;

		const SEPARATOR_OPTIONS = [
			{ label: '›', value: '›' },
			{ label: '/', value: '/' },
			{ label: '»', value: '»' },
			{ label: '→', value: '→' },
			{ label: '|', value: '|' },
		];

		return (
			<div { ...blockProps }>
				<InspectorControls>
					<PanelBody title={ __( 'Breadcrumb Settings', 'search-intel' ) }>
						<SelectControl
							label={ __( 'Separator', 'search-intel' ) }
							value={ separator }
							options={ SEPARATOR_OPTIONS }
							onChange={ ( val ) => setAttributes( { separator: val } ) }
						/>
						<TextControl
							label={ __( 'Home Label', 'search-intel' ) }
							value={ homeLabel }
							onChange={ ( val ) => setAttributes( { homeLabel: val } ) }
						/>
					</PanelBody>
				</InspectorControls>

				<nav className="si-breadcrumbs" aria-label={ __( 'Breadcrumb', 'search-intel' ) }>
					<span className="si-breadcrumbs__item si-breadcrumbs__item--home">
						{ homeLabel }
					</span>
					<span className="si-breadcrumbs__separator">{ ` ${ separator } ` }</span>
					<span className="si-breadcrumbs__item si-breadcrumbs__item--category">
						{ __( 'Category', 'search-intel' ) }
					</span>
					<span className="si-breadcrumbs__separator">{ ` ${ separator } ` }</span>
					<span className="si-breadcrumbs__item si-breadcrumbs__item--current">
						{ __( 'Current Page', 'search-intel' ) }
					</span>
				</nav>
				<p className="si-breadcrumbs__note">
					{ __( 'Preview — actual breadcrumbs render from page hierarchy on the frontend.', 'search-intel' ) }
				</p>
			</div>
		);
	},

	// Dynamic block — rendered via PHP on frontend.
	save() {
		return null;
	},
} );
