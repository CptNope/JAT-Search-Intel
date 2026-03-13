/**
 * Search Intel — Answer Box Block
 *
 * Direct-answer formatting block optimized for featured snippets.
 * Styled container with semantic markup for AI/search extraction.
 */

import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { RichText, useBlockProps } from '@wordpress/block-editor';

import './editor.css';

registerBlockType( 'search-intel/answer-box', {
	title: __( 'Answer Box', 'search-intel' ),
	description: __( 'A highlighted answer box optimized for featured snippets and AI extraction.', 'search-intel' ),
	category: 'widgets',
	icon: 'format-status',
	keywords: [ __( 'answer', 'search-intel' ), __( 'snippet', 'search-intel' ), __( 'featured', 'search-intel' ) ],
	supports: {
		html: false,
		align: [ 'wide' ],
	},
	attributes: {
		question: {
			type: 'string',
			default: '',
		},
		answer: {
			type: 'string',
			default: '',
		},
	},

	edit( { attributes, setAttributes } ) {
		const blockProps = useBlockProps( { className: 'si-answer-box' } );
		const { question, answer } = attributes;

		return (
			<div { ...blockProps }>
				<div className="si-answer-box__header">
					<span className="si-answer-box__icon">💡</span>
					<span className="si-answer-box__label">{ __( 'Answer Box', 'search-intel' ) }</span>
				</div>
				<RichText
					tagName="h4"
					className="si-answer-box__question"
					placeholder={ __( 'Question…', 'search-intel' ) }
					value={ question }
					onChange={ ( val ) => setAttributes( { question: val } ) }
					allowedFormats={ [ 'core/bold', 'core/italic' ] }
				/>
				<RichText
					tagName="div"
					className="si-answer-box__answer"
					placeholder={ __( 'Concise answer for featured snippet…', 'search-intel' ) }
					value={ answer }
					onChange={ ( val ) => setAttributes( { answer: val } ) }
					allowedFormats={ [ 'core/bold', 'core/italic', 'core/link' ] }
				/>
			</div>
		);
	},

	save( { attributes } ) {
		const blockProps = useBlockProps.save( { className: 'si-answer-box' } );
		const { question, answer } = attributes;

		if ( ! question || ! answer ) {
			return null;
		}

		return (
			<div { ...blockProps } data-si-answer-box="true">
				<h4 className="si-answer-box__question">
					<RichText.Content value={ question } />
				</h4>
				<div className="si-answer-box__answer" data-si-direct-answer="true">
					<RichText.Content value={ answer } />
				</div>
			</div>
		);
	},
} );
