/**
 * JAT Search Intel — FAQ Block
 *
 * Visual FAQ editor with live schema preview.
 * Outputs FAQPage JSON-LD structured data.
 */

import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { InspectorControls, RichText, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, Button, Icon } from '@wordpress/components';
import { plus, trash } from '@wordpress/icons';

import './editor.css';

registerBlockType( 'jat-search-intel/faq', {
	title: __( 'FAQ', 'jat-search-intel' ),
	description: __( 'Add frequently asked questions with automatic FAQPage schema markup.', 'jat-search-intel' ),
	category: 'widgets',
	icon: 'editor-help',
	keywords: [ __( 'faq', 'jat-search-intel' ), __( 'questions', 'jat-search-intel' ), __( 'schema', 'jat-search-intel' ) ],
	supports: {
		html: false,
		align: [ 'wide', 'full' ],
	},
	attributes: {
		items: {
			type: 'array',
			default: [
				{ question: '', answer: '' },
			],
		},
	},

	edit( { attributes, setAttributes } ) {
		const blockProps = useBlockProps( { className: 'si-faq-block' } );
		const { items } = attributes;

		const updateItem = ( index, field, value ) => {
			const updated = [ ...items ];
			updated[ index ] = { ...updated[ index ], [ field ]: value };
			setAttributes( { items: updated } );
		};

		const addItem = () => {
			setAttributes( { items: [ ...items, { question: '', answer: '' } ] } );
		};

		const removeItem = ( index ) => {
			const updated = items.filter( ( _, i ) => i !== index );
			setAttributes( { items: updated.length ? updated : [ { question: '', answer: '' } ] } );
		};

		return (
			<div { ...blockProps }>
				<InspectorControls>
					<PanelBody title={ __( 'FAQ Schema', 'jat-search-intel' ) }>
						<p>{ __( 'This block automatically generates FAQPage schema markup from your questions and answers.', 'jat-search-intel' ) }</p>
						<p><strong>{ items.filter( ( i ) => i.question && i.answer ).length }</strong> { __( 'valid FAQ items', 'jat-search-intel' ) }</p>
					</PanelBody>
				</InspectorControls>

				<div className="si-faq-block__header">
					<span className="si-faq-block__icon">❓</span>
					<span className="si-faq-block__title">{ __( 'FAQ', 'jat-search-intel' ) }</span>
				</div>

				{ items.map( ( item, index ) => (
					<div key={ index } className="si-faq-item">
						<div className="si-faq-item__header">
							<span className="si-faq-item__number">{ index + 1 }</span>
							<Button
								icon={ trash }
								label={ __( 'Remove', 'jat-search-intel' ) }
								onClick={ () => removeItem( index ) }
								isSmall
								isDestructive
								className="si-faq-item__remove"
							/>
						</div>
						<RichText
							tagName="h3"
							className="si-faq-item__question"
							placeholder={ __( 'Question…', 'jat-search-intel' ) }
							value={ item.question }
							onChange={ ( val ) => updateItem( index, 'question', val ) }
							allowedFormats={ [ 'core/bold', 'core/italic' ] }
						/>
						<RichText
							tagName="div"
							className="si-faq-item__answer"
							placeholder={ __( 'Answer…', 'jat-search-intel' ) }
							value={ item.answer }
							onChange={ ( val ) => updateItem( index, 'answer', val ) }
							allowedFormats={ [ 'core/bold', 'core/italic', 'core/link' ] }
						/>
					</div>
				) ) }

				<Button
					icon={ plus }
					variant="secondary"
					className="si-faq-block__add"
					onClick={ addItem }
				>
					{ __( 'Add Question', 'jat-search-intel' ) }
				</Button>
			</div>
		);
	},

	save( { attributes } ) {
		const blockProps = useBlockProps.save( { className: 'si-faq-block' } );
		const { items } = attributes;
		const validItems = items.filter( ( i ) => i.question && i.answer );

		if ( ! validItems.length ) {
			return null;
		}

		return (
			<div { ...blockProps }>
				<div
					className="si-faq-schema"
					data-schema="FAQPage"
					data-items={ JSON.stringify( validItems ) }
				/>
				{ validItems.map( ( item, index ) => (
					<div key={ index } className="si-faq-item" itemScope itemProp="mainEntity" itemType="https://schema.org/Question">
						<h3 className="si-faq-item__question" itemProp="name">
							<RichText.Content value={ item.question } />
						</h3>
						<div className="si-faq-item__answer" itemScope itemProp="acceptedAnswer" itemType="https://schema.org/Answer">
							<div itemProp="text">
								<RichText.Content value={ item.answer } />
							</div>
						</div>
					</div>
				) ) }
			</div>
		);
	},
} );
