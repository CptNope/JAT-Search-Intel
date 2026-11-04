/**
 * JAT Search Intel — Citation Block
 *
 * Inline source citation block for referencing external sources.
 * Adds structured citation metadata for AI and search engines.
 */

import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { InspectorControls, RichText, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl, SelectControl } from '@wordpress/components';

import './editor.css';

const SOURCE_TYPE_OPTIONS = [
	{ label: __( 'Website', 'jat-search-intel' ), value: 'website' },
	{ label: __( 'Journal Article', 'jat-search-intel' ), value: 'journal' },
	{ label: __( 'Book', 'jat-search-intel' ), value: 'book' },
	{ label: __( 'Report', 'jat-search-intel' ), value: 'report' },
	{ label: __( 'News Article', 'jat-search-intel' ), value: 'news' },
	{ label: __( 'Government', 'jat-search-intel' ), value: 'government' },
];

registerBlockType( 'jat-search-intel/citation', {
	title: __( 'Citation', 'jat-search-intel' ),
	description: __( 'Inline source citation with structured metadata for AI and search engines.', 'jat-search-intel' ),
	category: 'widgets',
	icon: 'format-quote',
	keywords: [ __( 'citation', 'jat-search-intel' ), __( 'source', 'jat-search-intel' ), __( 'reference', 'jat-search-intel' ) ],
	supports: {
		html: false,
	},
	attributes: {
		text: {
			type: 'string',
			default: '',
		},
		sourceUrl: {
			type: 'string',
			default: '',
		},
		sourceName: {
			type: 'string',
			default: '',
		},
		sourceType: {
			type: 'string',
			default: 'website',
		},
		author: {
			type: 'string',
			default: '',
		},
		date: {
			type: 'string',
			default: '',
		},
	},

	edit( { attributes, setAttributes } ) {
		const blockProps = useBlockProps( { className: 'si-citation-block' } );
		const { text, sourceUrl, sourceName, sourceType, author, date } = attributes;

		return (
			<div { ...blockProps }>
				<InspectorControls>
					<PanelBody title={ __( 'Citation Details', 'jat-search-intel' ) }>
						<TextControl
							label={ __( 'Source Name', 'jat-search-intel' ) }
							value={ sourceName }
							onChange={ ( val ) => setAttributes( { sourceName: val } ) }
							placeholder={ __( 'e.g. Harvard Business Review', 'jat-search-intel' ) }
						/>
						<TextControl
							label={ __( 'Source URL', 'jat-search-intel' ) }
							value={ sourceUrl }
							onChange={ ( val ) => setAttributes( { sourceUrl: val } ) }
							type="url"
						/>
						<SelectControl
							label={ __( 'Source Type', 'jat-search-intel' ) }
							value={ sourceType }
							options={ SOURCE_TYPE_OPTIONS }
							onChange={ ( val ) => setAttributes( { sourceType: val } ) }
						/>
						<TextControl
							label={ __( 'Author', 'jat-search-intel' ) }
							value={ author }
							onChange={ ( val ) => setAttributes( { author: val } ) }
						/>
						<TextControl
							label={ __( 'Date', 'jat-search-intel' ) }
							value={ date }
							onChange={ ( val ) => setAttributes( { date: val } ) }
							type="date"
						/>
					</PanelBody>
				</InspectorControls>

				<div className="si-citation__marker">📎</div>
				<RichText
					tagName="blockquote"
					className="si-citation__text"
					placeholder={ __( 'Quote or referenced text…', 'jat-search-intel' ) }
					value={ text }
					onChange={ ( val ) => setAttributes( { text: val } ) }
					allowedFormats={ [ 'core/bold', 'core/italic' ] }
				/>
				<div className="si-citation__source">
					{ sourceName && (
						<span className="si-citation__name">— { sourceName }</span>
					) }
					{ author && (
						<span className="si-citation__author">, { author }</span>
					) }
					{ date && (
						<span className="si-citation__date"> ({ date })</span>
					) }
				</div>
			</div>
		);
	},

	save( { attributes } ) {
		const blockProps = useBlockProps.save( { className: 'si-citation-block' } );
		const { text, sourceUrl, sourceName, sourceType, author, date } = attributes;

		if ( ! text ) {
			return null;
		}

		return (
			<figure
				{ ...blockProps }
				data-si-citation="true"
				data-source-type={ sourceType }
				itemScope
				itemType="https://schema.org/CreativeWork"
			>
				<blockquote className="si-citation__text" itemProp="text">
					<RichText.Content value={ text } />
				</blockquote>
				<figcaption className="si-citation__source">
					{ sourceUrl ? (
						<a
							href={ sourceUrl }
							className="si-citation__name"
							itemProp="url"
							rel="noopener noreferrer"
							target="_blank"
						>
							<span itemProp="name">{ sourceName || sourceUrl }</span>
						</a>
					) : (
						sourceName && <span className="si-citation__name" itemProp="name">{ sourceName }</span>
					) }
					{ author && (
						<span className="si-citation__author" itemProp="author">{ author }</span>
					) }
					{ date && (
						<time className="si-citation__date" itemProp="datePublished" dateTime={ date }>
							{ date }
						</time>
					) }
				</figcaption>
			</figure>
		);
	},
} );
