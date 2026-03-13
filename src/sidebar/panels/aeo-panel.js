/**
 * AEO Panel — Gutenberg sidebar tab.
 *
 * Fields: direct answer, schema type override, FAQ toggle.
 */

import { TextareaControl, SelectControl, PanelBody } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { META_KEYS } from '../../store';

function usePostMeta( metaKey ) {
	const value = useSelect(
		( select ) => {
			const meta = select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {};
			return meta[ metaKey ] || '';
		},
		[ metaKey ]
	);

	const { editPost } = useDispatch( 'core/editor' );

	const setValue = ( newValue ) => {
		editPost( { meta: { [ metaKey ]: newValue } } );
	};

	return [ value, setValue ];
}

const SCHEMA_TYPE_OPTIONS = [
	{ label: __( 'Auto-detect', 'search-intel' ), value: '' },
	{ label: __( 'Article', 'search-intel' ), value: 'Article' },
	{ label: __( 'NewsArticle', 'search-intel' ), value: 'NewsArticle' },
	{ label: __( 'BlogPosting', 'search-intel' ), value: 'BlogPosting' },
	{ label: __( 'HowTo', 'search-intel' ), value: 'HowTo' },
	{ label: __( 'FAQPage', 'search-intel' ), value: 'FAQPage' },
	{ label: __( 'Review', 'search-intel' ), value: 'Review' },
];

export default function AeoPanel() {
	const [ directAnswer, setDirectAnswer ] = usePostMeta( META_KEYS.directAnswer );
	const [ schemaType, setSchemaType ] = usePostMeta( META_KEYS.schemaType );

	const answerLength = directAnswer.length;

	return (
		<div className="si-panel si-panel--aeo">
			<TextareaControl
				label={ __( 'Direct Answer', 'search-intel' ) }
				help={
					<>
						<span className={ answerLength > 300 ? 'si-count--over' : '' }>
							{ answerLength }/300
						</span>
						<br />
						{ __( 'A concise answer for featured snippets and voice assistants.', 'search-intel' ) }
					</>
				}
				value={ directAnswer }
				onChange={ setDirectAnswer }
				rows={ 4 }
			/>

			<SelectControl
				label={ __( 'Schema Type Override', 'search-intel' ) }
				value={ schemaType }
				options={ SCHEMA_TYPE_OPTIONS }
				onChange={ setSchemaType }
				help={ __( 'Leave on Auto-detect to let Search Intel choose the best schema type.', 'search-intel' ) }
			/>

			<PanelBody
				title={ __( 'FAQ & HowTo', 'search-intel' ) }
				initialOpen={ false }
			>
				<p className="si-panel__help">
					{ __( 'Use the Search Intel FAQ or HowTo blocks in the editor content area to add structured FAQ/HowTo data. These blocks automatically generate the correct schema markup.', 'search-intel' ) }
				</p>
			</PanelBody>
		</div>
	);
}
