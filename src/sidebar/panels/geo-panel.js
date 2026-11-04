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
	{ label: __( 'Auto-detect', 'jat-search-intel' ), value: '' },
	{ label: __( 'Article', 'jat-search-intel' ), value: 'article' },
	{ label: __( 'How-To', 'jat-search-intel' ), value: 'how-to' },
	{ label: __( 'FAQ', 'jat-search-intel' ), value: 'faq' },
	{ label: __( 'Comparison', 'jat-search-intel' ), value: 'comparison' },
	{ label: __( 'Review', 'jat-search-intel' ), value: 'review' },
	{ label: __( 'Guide', 'jat-search-intel' ), value: 'guide' },
	{ label: __( 'News', 'jat-search-intel' ), value: 'news' },
	{ label: __( 'Opinion', 'jat-search-intel' ), value: 'opinion' },
];

const INTENT_OPTIONS = [
	{ label: __( 'Auto-detect', 'jat-search-intel' ), value: '' },
	{ label: __( 'Informational', 'jat-search-intel' ), value: 'informational' },
	{ label: __( 'Navigational', 'jat-search-intel' ), value: 'navigational' },
	{ label: __( 'Transactional', 'jat-search-intel' ), value: 'transactional' },
	{ label: __( 'Commercial', 'jat-search-intel' ), value: 'commercial' },
];

const AUDIENCE_OPTIONS = [
	{ label: __( 'Auto-detect', 'jat-search-intel' ), value: '' },
	{ label: __( 'General', 'jat-search-intel' ), value: 'general' },
	{ label: __( 'Beginner', 'jat-search-intel' ), value: 'beginner' },
	{ label: __( 'Intermediate', 'jat-search-intel' ), value: 'intermediate' },
	{ label: __( 'Expert', 'jat-search-intel' ), value: 'expert' },
	{ label: __( 'Professional', 'jat-search-intel' ), value: 'professional' },
];

const EXPERTISE_OPTIONS = [
	{ label: __( 'Auto-detect', 'jat-search-intel' ), value: '' },
	{ label: __( 'Beginner', 'jat-search-intel' ), value: 'beginner' },
	{ label: __( 'Intermediate', 'jat-search-intel' ), value: 'intermediate' },
	{ label: __( 'Advanced', 'jat-search-intel' ), value: 'advanced' },
	{ label: __( 'Expert', 'jat-search-intel' ), value: 'expert' },
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
				label={ __( 'AI Summary', 'jat-search-intel' ) }
				help={
					<>
						<span className={ summaryLength > 500 ? 'si-count--over' : '' }>
							{ summaryLength }/500
						</span>
						<br />
						{ __( 'A concise, factual summary for LLM consumption.', 'jat-search-intel' ) }
					</>
				}
				value={ aiSummary }
				onChange={ setAiSummary }
				rows={ 4 }
			/>

			<TextControl
				label={ __( 'Key Topics', 'jat-search-intel' ) }
				value={ keyTopics }
				onChange={ setKeyTopics }
				help={ __( 'Comma-separated key topics for AI indexing.', 'jat-search-intel' ) }
			/>

			<SelectControl
				label={ __( 'Content Type', 'jat-search-intel' ) }
				value={ contentType }
				options={ CONTENT_TYPE_OPTIONS }
				onChange={ setContentType }
			/>

			<SelectControl
				label={ __( 'Content Intent', 'jat-search-intel' ) }
				value={ contentIntent }
				options={ INTENT_OPTIONS }
				onChange={ setContentIntent }
			/>

			<SelectControl
				label={ __( 'Target Audience', 'jat-search-intel' ) }
				value={ targetAudience }
				options={ AUDIENCE_OPTIONS }
				onChange={ setTargetAudience }
			/>

			<SelectControl
				label={ __( 'Expertise Level', 'jat-search-intel' ) }
				value={ expertiseLevel }
				options={ EXPERTISE_OPTIONS }
				onChange={ setExpertiseLevel }
			/>

			<TextControl
				label={ __( 'Fact-Check Date', 'jat-search-intel' ) }
				value={ factCheckDate }
				onChange={ setFactCheckDate }
				type="date"
			/>

			<ToggleControl
				label={ __( 'Local Relevance', 'jat-search-intel' ) }
				checked={ localRelevance === '1' || localRelevance === true }
				onChange={ ( val ) => setLocalRelevance( val ? '1' : '' ) }
			/>

			{ ( localRelevance === '1' || localRelevance === true ) && (
				<TextControl
					label={ __( 'Location', 'jat-search-intel' ) }
					value={ localLocation }
					onChange={ setLocalLocation }
					help={ __( 'City, state, or region for local relevance signals.', 'jat-search-intel' ) }
				/>
			) }
		</div>
	);
}
