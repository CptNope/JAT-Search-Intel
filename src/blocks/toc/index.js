/**
 * JAT Search Intel — Table of Contents Block
 *
 * Auto-generated TOC from post headings.
 * Renders dynamically in the editor and uses PHP render_callback on frontend.
 */

import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, RangeControl, ToggleControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';

registerBlockType( 'jat-search-intel/toc', {
	title: __( 'Table of Contents', 'jat-search-intel' ),
	description: __( 'Auto-generated table of contents from headings in the post.', 'jat-search-intel' ),
	category: 'widgets',
	icon: 'list-view',
	keywords: [ __( 'toc', 'jat-search-intel' ), __( 'contents', 'jat-search-intel' ), __( 'navigation', 'jat-search-intel' ) ],
	supports: {
		html: false,
		align: [ 'wide' ],
	},
	attributes: {
		maxDepth: {
			type: 'number',
			default: 3,
		},
		ordered: {
			type: 'boolean',
			default: true,
		},
	},

	edit( { attributes, setAttributes } ) {
		const blockProps = useBlockProps( { className: 'si-toc-block' } );
		const { maxDepth, ordered } = attributes;
		const [ headings, setHeadings ] = useState( [] );

		const blocks = useSelect(
			( select ) => select( 'core/block-editor' ).getBlocks(),
			[]
		);

		useEffect( () => {
			const found = [];
			const extractHeadings = ( blockList ) => {
				for ( const block of blockList ) {
					if ( block.name === 'core/heading' ) {
						const level = block.attributes.level || 2;
						if ( level <= maxDepth + 1 ) {
							found.push( {
								level,
								content: ( block.attributes.content || '' ).replace( /<[^>]+>/g, '' ),
								clientId: block.clientId,
							} );
						}
					}
					if ( block.innerBlocks?.length ) {
						extractHeadings( block.innerBlocks );
					}
				}
			};
			extractHeadings( blocks );
			setHeadings( found );
		}, [ blocks, maxDepth ] );

		const ListTag = ordered ? 'ol' : 'ul';

		return (
			<div { ...blockProps }>
				<InspectorControls>
					<PanelBody title={ __( 'TOC Settings', 'jat-search-intel' ) }>
						<RangeControl
							label={ __( 'Max Heading Depth', 'jat-search-intel' ) }
							value={ maxDepth }
							onChange={ ( val ) => setAttributes( { maxDepth: val } ) }
							min={ 1 }
							max={ 6 }
						/>
						<ToggleControl
							label={ __( 'Ordered List', 'jat-search-intel' ) }
							checked={ ordered }
							onChange={ ( val ) => setAttributes( { ordered: val } ) }
						/>
					</PanelBody>
				</InspectorControls>

				<div className="si-toc-block__header">
					<span className="si-toc-block__icon">📑</span>
					<span className="si-toc-block__title">{ __( 'Table of Contents', 'jat-search-intel' ) }</span>
				</div>

				{ headings.length === 0 ? (
					<p className="si-toc-block__empty">
						{ __( 'Add headings to your post to generate the table of contents.', 'jat-search-intel' ) }
					</p>
				) : (
					<ListTag className="si-toc-list">
						{ headings.map( ( heading, i ) => (
							<li
								key={ i }
								className={ `si-toc-item si-toc-item--h${ heading.level }` }
								style={ { marginLeft: `${ ( heading.level - 2 ) * 16 }px` } }
							>
								{ heading.content || __( '(empty heading)', 'jat-search-intel' ) }
							</li>
						) ) }
					</ListTag>
				) }
			</div>
		);
	},

	// Dynamic block — rendered via PHP on frontend.
	save() {
		return null;
	},
} );
