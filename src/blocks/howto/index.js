/**
 * Search Intel — HowTo Block
 *
 * Step-by-step editor with image support per step.
 * Outputs HowTo JSON-LD structured data.
 */

import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { InspectorControls, RichText, MediaUpload, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl, Button } from '@wordpress/components';
import { plus, trash } from '@wordpress/icons';

import './editor.css';

registerBlockType( 'search-intel/howto', {
	title: __( 'How To', 'search-intel' ),
	description: __( 'Step-by-step instructions with automatic HowTo schema markup.', 'search-intel' ),
	category: 'widgets',
	icon: 'editor-ol',
	keywords: [ __( 'howto', 'search-intel' ), __( 'steps', 'search-intel' ), __( 'tutorial', 'search-intel' ) ],
	supports: {
		html: false,
		align: [ 'wide', 'full' ],
	},
	attributes: {
		title: {
			type: 'string',
			default: '',
		},
		description: {
			type: 'string',
			default: '',
		},
		totalTime: {
			type: 'string',
			default: '',
		},
		steps: {
			type: 'array',
			default: [
				{ name: '', text: '', imageUrl: '', imageId: 0 },
			],
		},
	},

	edit( { attributes, setAttributes } ) {
		const blockProps = useBlockProps( { className: 'si-howto-block' } );
		const { title, description, totalTime, steps } = attributes;

		const updateStep = ( index, field, value ) => {
			const updated = [ ...steps ];
			updated[ index ] = { ...updated[ index ], [ field ]: value };
			setAttributes( { steps: updated } );
		};

		const addStep = () => {
			setAttributes( {
				steps: [ ...steps, { name: '', text: '', imageUrl: '', imageId: 0 } ],
			} );
		};

		const removeStep = ( index ) => {
			const updated = steps.filter( ( _, i ) => i !== index );
			setAttributes( { steps: updated.length ? updated : [ { name: '', text: '', imageUrl: '', imageId: 0 } ] } );
		};

		return (
			<div { ...blockProps }>
				<InspectorControls>
					<PanelBody title={ __( 'HowTo Settings', 'search-intel' ) }>
						<TextControl
							label={ __( 'Total Time (ISO 8601)', 'search-intel' ) }
							value={ totalTime }
							onChange={ ( val ) => setAttributes( { totalTime: val } ) }
							help={ __( 'e.g. PT30M for 30 minutes, PT1H for 1 hour', 'search-intel' ) }
							placeholder="PT30M"
						/>
						<p><strong>{ steps.filter( ( s ) => s.name && s.text ).length }</strong> { __( 'valid steps', 'search-intel' ) }</p>
					</PanelBody>
				</InspectorControls>

				<div className="si-howto-block__header">
					<span className="si-howto-block__icon">📋</span>
					<span className="si-howto-block__label">{ __( 'How To', 'search-intel' ) }</span>
				</div>

				<RichText
					tagName="h3"
					className="si-howto-block__title"
					placeholder={ __( 'How to title…', 'search-intel' ) }
					value={ title }
					onChange={ ( val ) => setAttributes( { title: val } ) }
					allowedFormats={ [] }
				/>

				<RichText
					tagName="p"
					className="si-howto-block__description"
					placeholder={ __( 'Brief description of this how-to…', 'search-intel' ) }
					value={ description }
					onChange={ ( val ) => setAttributes( { description: val } ) }
					allowedFormats={ [ 'core/bold', 'core/italic' ] }
				/>

				{ steps.map( ( step, index ) => (
					<div key={ index } className="si-howto-step">
						<div className="si-howto-step__header">
							<span className="si-howto-step__number">
								{ __( 'Step', 'search-intel' ) } { index + 1 }
							</span>
							<Button
								icon={ trash }
								label={ __( 'Remove Step', 'search-intel' ) }
								onClick={ () => removeStep( index ) }
								isSmall
								isDestructive
								className="si-howto-step__remove"
							/>
						</div>
						<RichText
							tagName="h4"
							className="si-howto-step__name"
							placeholder={ __( 'Step name…', 'search-intel' ) }
							value={ step.name }
							onChange={ ( val ) => updateStep( index, 'name', val ) }
							allowedFormats={ [ 'core/bold' ] }
						/>
						<RichText
							tagName="div"
							className="si-howto-step__text"
							placeholder={ __( 'Step instructions…', 'search-intel' ) }
							value={ step.text }
							onChange={ ( val ) => updateStep( index, 'text', val ) }
							allowedFormats={ [ 'core/bold', 'core/italic', 'core/link' ] }
						/>
						<div className="si-howto-step__image">
							<MediaUpload
								onSelect={ ( media ) => {
									updateStep( index, 'imageUrl', media.url );
									updateStep( index, 'imageId', media.id );
								} }
								allowedTypes={ [ 'image' ] }
								value={ step.imageId }
								render={ ( { open } ) => (
									step.imageUrl ? (
										<div className="si-howto-step__image-preview">
											<img src={ step.imageUrl } alt="" />
											<Button
												isSmall
												isDestructive
												onClick={ () => {
													updateStep( index, 'imageUrl', '' );
													updateStep( index, 'imageId', 0 );
												} }
											>
												{ __( 'Remove', 'search-intel' ) }
											</Button>
										</div>
									) : (
										<Button isSmall variant="secondary" onClick={ open }>
											{ __( 'Add Image', 'search-intel' ) }
										</Button>
									)
								) }
							/>
						</div>
					</div>
				) ) }

				<Button
					icon={ plus }
					variant="secondary"
					className="si-howto-block__add"
					onClick={ addStep }
				>
					{ __( 'Add Step', 'search-intel' ) }
				</Button>
			</div>
		);
	},

	save( { attributes } ) {
		const blockProps = useBlockProps.save( { className: 'si-howto-block' } );
		const { title, description, totalTime, steps } = attributes;
		const validSteps = steps.filter( ( s ) => s.name && s.text );

		if ( ! validSteps.length ) {
			return null;
		}

		return (
			<div { ...blockProps }>
				<div
					className="si-howto-schema"
					data-schema="HowTo"
					data-title={ title }
					data-total-time={ totalTime }
					data-steps={ JSON.stringify( validSteps ) }
				/>
				{ title && <h3 className="si-howto-block__title">{ title }</h3> }
				{ description && (
					<p className="si-howto-block__description">
						<RichText.Content value={ description } />
					</p>
				) }
				<ol className="si-howto-steps">
					{ validSteps.map( ( step, index ) => (
						<li key={ index } className="si-howto-step">
							<h4 className="si-howto-step__name">
								<RichText.Content value={ step.name } />
							</h4>
							<div className="si-howto-step__text">
								<RichText.Content value={ step.text } />
							</div>
							{ step.imageUrl && (
								<img
									className="si-howto-step__img"
									src={ step.imageUrl }
									alt=""
								/>
							) }
						</li>
					) ) }
				</ol>
			</div>
		);
	},
} );
