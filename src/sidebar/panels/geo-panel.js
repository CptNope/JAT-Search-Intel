/**
 * GEO Panel — Gutenberg sidebar tab.
 *
 * Fields: AI summary, key topics, content type, intent, audience,
 * expertise level, fact-check date, local relevance.
 */

import { TextControl, TextareaControl, SelectControl, ToggleControl } from '@wordpress/components';
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

const CONTENT_TYPE_OPTIONS = [
	{ label: __( 'Auto-detect', 'search-intel' ), value: '' },
	{ label: __( 'Article', 'search-intel' ), value: 'article' },
	{ label: __( 'How-To', 'search-intel' ), value: 'how-to' },
	{ label: __( 'FAQ', 'search-intel' ), value: 'faq' },
	{ label: __( 'Comparison', 'search-intel' ), value: 'comparison' },
	{ label: __( 'Review', 'search-intel' ), value: 'review' },
	{ label: __( 'Guide', 'search-intel' ), value: 'guide' },
	{ label: __( 'News', 'search-intel' ), value: 'news' },
	{ label: __( 'Opinion', 'search-intel' ), value: 'opinion' },
];

const INTENT_OPTIONS = [
	{ label: __( 'Auto-detect', 'search-intel' ), value: '' },
	{ label: __( 'Informational', 'search-intel' ), value: 'informational' },
	{ label: __( 'Navigational', 'search-intel' ), value: 'navigational' },
	{ label: __( 'Transactional', 'search-intel' ), value: 'transactional' },
	{ label: __( 'Commercial', 'search-intel' ), value: 'commercial' },
];

const AUDIENCE_OPTIONS = [
	{ label: __( 'Auto-detect', 'search-intel' ), value: '' },
	{ label: __( 'General', 'search-intel' ), value: 'general' },
	{ label: __( 'Beginner', 'search-intel' ), value: 'beginner' },
	{ label: __( 'Intermediate', 'search-intel' ), value: 'intermediate' },
	{ label: __( 'Expert', 'search-intel' ), value: 'expert' },
	{ label: __( 'Professional', 'search-intel' ), value: 'professional' },
];

const EXPERTISE_OPTIONS = [
	{ label: __( 'Auto-detect', 'search-intel' ), value: '' },
	{ label: __( 'Beginner', 'search-intel' ), value: 'beginner' },
	{ label: __( 'Intermediate', 'search-intel' ), value: 'intermediate' },
	{ label: __( 'Advanced', 'search-intel' ), value: 'advanced' },
	{ label: __( 'Expert', 'search-intel' ), value: 'expert' },
];

export default function GeoPanel() {
	const [ aiSummary, setAiSummary ] = usePostMeta( META_KEYS.aiSummary );
	const [ keyTopics, setKeyTopics ] = usePostMeta( META_KEYS.keyTopics );
	const [ contentType, setContentType ] = usePostMeta( META_KEYS.contentType );
	const [ contentIntent, setContentIntent ] = usePostMeta( META_KEYS.contentIntent );
	const [ targetAudience, setTargetAudience ] = usePostMeta( META_KEYS.targetAudience );
	const [ expertiseLevel, setExpertiseLevel ] = usePostMeta( META_KEYS.expertiseLevel );
	const [ factCheckDate, setFactCheckDate ] = usePostMeta( META_KEYS.factCheckDate );
	const [ localRelevance, setLocalRelevance ] = usePostMeta( META_KEYS.localRelevance );
	const [ localLocation, setLocalLocation ] = usePostMeta( META_KEYS.localLocation );

	const summaryLength = aiSummary.length;

	return (
		<div className="si-panel si-panel--geo">
			<TextareaControl
				label={ __( 'AI Summary', 'search-intel' ) }
				help={
					<>
						<span className={ summaryLength > 500 ? 'si-count--over' : '' }>
							{ summaryLength }/500
						</span>
						<br />
						{ __( 'A concise, factual summary for LLM consumption.', 'search-intel' ) }
					</>
				}
				value={ aiSummary }
				onChange={ setAiSummary }
				rows={ 4 }
			/>

			<TextControl
				label={ __( 'Key Topics', 'search-intel' ) }
				value={ keyTopics }
				onChange={ setKeyTopics }
				help={ __( 'Comma-separated key topics for AI indexing.', 'search-intel' ) }
			/>

			<SelectControl
				label={ __( 'Content Type', 'search-intel' ) }
				value={ contentType }
				options={ CONTENT_TYPE_OPTIONS }
				onChange={ setContentType }
			/>

			<SelectControl
				label={ __( 'Content Intent', 'search-intel' ) }
				value={ contentIntent }
				options={ INTENT_OPTIONS }
				onChange={ setContentIntent }
			/>

			<SelectControl
				label={ __( 'Target Audience', 'search-intel' ) }
				value={ targetAudience }
				options={ AUDIENCE_OPTIONS }
				onChange={ setTargetAudience }
			/>

			<SelectControl
				label={ __( 'Expertise Level', 'search-intel' ) }
				value={ expertiseLevel }
				options={ EXPERTISE_OPTIONS }
				onChange={ setExpertiseLevel }
			/>

			<TextControl
				label={ __( 'Fact-Check Date', 'search-intel' ) }
				value={ factCheckDate }
				onChange={ setFactCheckDate }
				type="date"
			/>

			<ToggleControl
				label={ __( 'Local Relevance', 'search-intel' ) }
				checked={ localRelevance === '1' || localRelevance === true }
				onChange={ ( val ) => setLocalRelevance( val ? '1' : '' ) }
			/>

			{ ( localRelevance === '1' || localRelevance === true ) && (
				<TextControl
					label={ __( 'Location', 'search-intel' ) }
					value={ localLocation }
					onChange={ setLocalLocation }
					help={ __( 'City, state, or region for local relevance signals.', 'search-intel' ) }
				/>
			) }
		</div>
	);
}
