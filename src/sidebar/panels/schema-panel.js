/**
 * Schema Panel — fill-in forms for Recipe, Event, Review, Course,
 * SoftwareApplication, JobPosting and VideoObject structured data.
 *
 * Field definitions come from Schema_Types::get_fields() via the
 * jatSearchIntelSchema global, so PHP and the editor never disagree.
 */

import {
	SelectControl,
	TextControl,
	TextareaControl,
	ExternalLink,
	Notice,
} from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

const TYPE_KEY = '_jat_search_intel_schema_type_extra';
const PREFIX = '_jat_search_intel_';

function config() {
	return window.jatSearchIntelSchema || { enabled: false, types: {}, fields: {} };
}

function FieldControl( { name, field, value, onChange } ) {
	const [ label, kind, help ] = field;
	const common = { label, help, value: value || '', onChange };

	switch ( kind ) {
		case 'textarea':
			return <TextareaControl { ...common } rows={ 3 } key={ name } />;
		case 'lines':
			return <TextareaControl { ...common } rows={ 5 } key={ name } />;
		case 'datetime':
			return <TextControl { ...common } type="datetime-local" key={ name } />;
		case 'number':
		case 'minutes':
			return <TextControl { ...common } type="number" min="0" step="any" key={ name } />;
		case 'url':
			return <TextControl { ...common } type="url" key={ name } />;
		default:
			return <TextControl { ...common } key={ name } />;
	}
}

export default function SchemaPanel() {
	const { enabled, settingsUrl, types, fields } = config();

	const { meta, permalink, status } = useSelect( ( select ) => {
		const editor = select( 'core/editor' );
		return {
			meta: editor.getEditedPostAttribute( 'meta' ) || {},
			permalink: editor.getPermalink() || '',
			status: editor.getEditedPostAttribute( 'status' ),
		};
	}, [] );
	const { editPost } = useDispatch( 'core/editor' );

	const type = meta[ TYPE_KEY ] || '';
	const typeFields = ( type && fields[ type ] ) || {};

	const options = [
		{ label: __( 'None', 'jat-search-intel' ), value: '' },
		...Object.keys( types ).map( ( key ) => ( { label: types[ key ], value: key } ) ),
	];

	const set = ( key, value ) => editPost( { meta: { [ key ]: value } } );

	return (
		<div className="si-panel si-panel--schema">
			{ ! enabled && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'Schema types are switched off, so these fields are saved but not published. Turn on "Schema types" under JAT Search Intel > Settings > Module Toggles.',
						'jat-search-intel'
					) }{ ' ' }
					{ settingsUrl && (
						<ExternalLink href={ settingsUrl }>
							{ __( 'Open settings', 'jat-search-intel' ) }
						</ExternalLink>
					) }
				</Notice>
			) }

			<SelectControl
				label={ __( 'What is this page about?', 'jat-search-intel' ) }
				help={ __(
					'Adds rich-result structured data for this type, on top of the Article or WebPage schema.',
					'jat-search-intel'
				) }
				value={ type }
				options={ options }
				onChange={ ( value ) => set( TYPE_KEY, value ) }
			/>

			{ Object.keys( typeFields ).map( ( name ) => (
				<FieldControl
					key={ name }
					name={ name }
					field={ typeFields[ name ] }
					value={ meta[ PREFIX + name ] }
					onChange={ ( value ) => set( PREFIX + name, value ) }
				/>
			) ) }

			{ type && permalink && status === 'publish' && (
				<p className="si-schema-test">
					<ExternalLink
						href={ `https://search.google.com/test/rich-results?url=${ encodeURIComponent(
							permalink
						) }` }
					>
						{ __( 'Test in Google Rich Results', 'jat-search-intel' ) }
					</ExternalLink>
				</p>
			) }
		</div>
	);
}
