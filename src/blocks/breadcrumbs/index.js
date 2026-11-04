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
	title: __( 'Breadcrumbs', 'jat-search-intel' ),
	description: __( 'Theme-aware breadcrumb navigation with BreadcrumbList schema.', 'jat-search-intel' ),
	category: 'widgets',
	icon: 'arrow-right-alt',
	keywords: [ __( 'breadcrumbs', 'jat-search-intel' ), __( 'navigation', 'jat-search-intel' ), __( 'path', 'jat-search-intel' ) ],
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
					<PanelBody title={ __( 'Breadcrumb Settings', 'jat-search-intel' ) }>
						<SelectControl
							label={ __( 'Separator', 'jat-search-intel' ) }
							value={ separator }
							options={ SEPARATOR_OPTIONS }
							onChange={ ( val ) => setAttributes( { separator: val } ) }
						/>
						<TextControl
							label={ __( 'Home Label', 'jat-search-intel' ) }
							value={ homeLabel }
							onChange={ ( val ) => setAttributes( { homeLabel: val } ) }
						/>
					</PanelBody>
				</InspectorControls>

				<nav className="si-breadcrumbs" aria-label={ __( 'Breadcrumb', 'jat-search-intel' ) }>
					<span className="si-breadcrumbs__item si-breadcrumbs__item--home">
						{ homeLabel }
					</span>
					<span className="si-breadcrumbs__separator">{ ` ${ separator } ` }</span>
					<span className="si-breadcrumbs__item si-breadcrumbs__item--category">
						{ __( 'Category', 'jat-search-intel' ) }
					</span>
					<span className="si-breadcrumbs__separator">{ ` ${ separator } ` }</span>
					<span className="si-breadcrumbs__item si-breadcrumbs__item--current">
						{ __( 'Current Page', 'jat-search-intel' ) }
					</span>
				</nav>
				<p className="si-breadcrumbs__note">
					{ __( 'Preview — actual breadcrumbs render from page hierarchy on the frontend.', 'jat-search-intel' ) }
				</p>
			</div>
		);
	},

	// Dynamic block — rendered via PHP on frontend.
	save() {
		return null;
	},
} );
