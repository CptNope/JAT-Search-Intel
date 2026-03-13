/**
 * Search Intel Editor Store
 *
 * @wordpress/data store for managing SEO/AEO/GEO post meta
 * in the Gutenberg sidebar panel.
 *
 * Store name: 'search-intel/editor'
 */

import { createReduxStore, register } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

/**
 * Meta key map — mirrors the PHP _search_intel_* meta keys.
 */
export const META_KEYS = {
	// SEO
	title: '_search_intel_title',
	description: '_search_intel_description',
	focusKeyword: '_search_intel_focus_keyword',
	canonicalUrl: '_search_intel_canonical_url',
	noindex: '_search_intel_noindex',
	nofollow: '_search_intel_nofollow',
	ogTitle: '_search_intel_og_title',
	ogDescription: '_search_intel_og_description',
	ogImage: '_search_intel_og_image',
	twitterTitle: '_search_intel_twitter_title',
	twitterDescription: '_search_intel_twitter_description',
	twitterImage: '_search_intel_twitter_image',
	secondaryKeywords: '_search_intel_secondary_keywords',

	// AEO
	directAnswer: '_search_intel_direct_answer',
	schemaType: '_search_intel_schema_type',

	// GEO
	aiSummary: '_search_intel_ai_summary',
	keyTopics: '_search_intel_key_topics',
	contentType: '_search_intel_content_type',
	contentIntent: '_search_intel_content_intent',
	targetAudience: '_search_intel_target_audience',
	expertiseLevel: '_search_intel_expertise_level',
	factCheckDate: '_search_intel_fact_check_date',
	localRelevance: '_search_intel_local_relevance',
	localLocation: '_search_intel_local_location',

	// Scores
	seoScore: '_search_intel_seo_score',
	aeoScore: '_search_intel_aeo_score',
	geoScore: '_search_intel_geo_score',
	readabilityScore: '_search_intel_readability_score',
};

/**
 * Default state.
 */
const DEFAULT_STATE = {
	isAnalyzing: false,
	analysisResults: null,
	activePanel: 'seo',
};

/**
 * Actions.
 */
const actions = {
	setActivePanel( panel ) {
		return { type: 'SET_ACTIVE_PANEL', panel };
	},

	setIsAnalyzing( isAnalyzing ) {
		return { type: 'SET_IS_ANALYZING', isAnalyzing };
	},

	setAnalysisResults( results ) {
		return { type: 'SET_ANALYSIS_RESULTS', results };
	},

	/**
	 * Run content analysis via REST API.
	 */
	runAnalysis() {
		return async ( { dispatch, select, registry } ) => {
			const postId = registry.select( 'core/editor' ).getCurrentPostId();
			if ( ! postId ) {
				return;
			}

			dispatch.setIsAnalyzing( true );

			try {
				const response = await window.wp.apiFetch( {
					path: `/search-intel/v1/analyze/${ postId }`,
					method: 'POST',
				} );

				if ( response?.scores ) {
					dispatch.setAnalysisResults( response );

					// Save scores to post meta via core editor.
					const editPost = registry.dispatch( 'core/editor' ).editPost;
					editPost( {
						meta: {
							[ META_KEYS.seoScore ]: String( response.scores.seo ?? 0 ),
							[ META_KEYS.aeoScore ]: String( response.scores.aeo ?? 0 ),
							[ META_KEYS.geoScore ]: String( response.scores.geo ?? 0 ),
							[ META_KEYS.readabilityScore ]: String( response.scores.readability ?? 0 ),
						},
					} );
				}
			} catch ( error ) {
				// eslint-disable-next-line no-console
				console.error( 'Search Intel analysis error:', error );
			} finally {
				dispatch.setIsAnalyzing( false );
			}
		};
	},
};

/**
 * Reducer.
 */
function reducer( state = DEFAULT_STATE, action ) {
	switch ( action.type ) {
		case 'SET_ACTIVE_PANEL':
			return { ...state, activePanel: action.panel };
		case 'SET_IS_ANALYZING':
			return { ...state, isAnalyzing: action.isAnalyzing };
		case 'SET_ANALYSIS_RESULTS':
			return { ...state, analysisResults: action.results };
		default:
			return state;
	}
}

/**
 * Selectors.
 */
const selectors = {
	getActivePanel( state ) {
		return state.activePanel;
	},

	isAnalyzing( state ) {
		return state.isAnalyzing;
	},

	getAnalysisResults( state ) {
		return state.analysisResults;
	},
};

/**
 * Create and register the store.
 */
const store = createReduxStore( 'search-intel/editor', {
	reducer,
	actions,
	selectors,
} );

register( store );

export default store;
