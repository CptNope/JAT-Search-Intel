/**
 * Score / Analysis Panel — Gutenberg sidebar tab.
 *
 * Displays SEO, AEO, GEO, Readability score rings
 * with a "Run Analysis" button.
 */

import { Button, Spinner } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { META_KEYS } from '../../store';

/**
 * Score ring SVG component.
 */
function ScoreRing( { label, score, color } ) {
	const radius = 28;
	const circumference = 2 * Math.PI * radius;
	const offset = circumference - ( score / 100 ) * circumference;

	return (
		<div className="si-score-ring">
			<svg width="70" height="70" viewBox="0 0 70 70">
				<circle
					cx="35"
					cy="35"
					r={ radius }
					fill="none"
					stroke="#e0e0e0"
					strokeWidth="6"
				/>
				<circle
					cx="35"
					cy="35"
					r={ radius }
					fill="none"
					stroke={ color }
					strokeWidth="6"
					strokeDasharray={ circumference }
					strokeDashoffset={ offset }
					strokeLinecap="round"
					transform="rotate(-90 35 35)"
				/>
				<text
					x="35"
					y="35"
					textAnchor="middle"
					dominantBaseline="central"
					fontSize="16"
					fontWeight="bold"
					fill={ color }
				>
					{ score }
				</text>
			</svg>
			<span className="si-score-ring__label">{ label }</span>
		</div>
	);
}

/**
 * Get color for score value.
 */
function getScoreColor( score ) {
	if ( score >= 80 ) {
		return '#00a32a';
	}
	if ( score >= 50 ) {
		return '#dba617';
	}
	return '#cc1818';
}

export default function ScorePanel() {
	const { isAnalyzing, analysisResults } = useSelect(
		( select ) => ( {
			isAnalyzing: select( 'search-intel/editor' ).isAnalyzing(),
			analysisResults: select( 'search-intel/editor' ).getAnalysisResults(),
		} ),
		[]
	);

	const { seoScore, aeoScore, geoScore, readabilityScore } = useSelect(
		( select ) => {
			const meta = select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {};
			return {
				seoScore: parseInt( meta[ META_KEYS.seoScore ] || '0', 10 ),
				aeoScore: parseInt( meta[ META_KEYS.aeoScore ] || '0', 10 ),
				geoScore: parseInt( meta[ META_KEYS.geoScore ] || '0', 10 ),
				readabilityScore: parseInt( meta[ META_KEYS.readabilityScore ] || '0', 10 ),
			};
		},
		[]
	);

	const { runAnalysis } = useDispatch( 'search-intel/editor' );

	const checks = analysisResults?.checks || {};

	return (
		<div className="si-panel si-panel--scores">
			<div className="si-score-rings">
				<ScoreRing
					label={ __( 'SEO', 'search-intel' ) }
					score={ seoScore }
					color={ getScoreColor( seoScore ) }
				/>
				<ScoreRing
					label={ __( 'AEO', 'search-intel' ) }
					score={ aeoScore }
					color={ getScoreColor( aeoScore ) }
				/>
				<ScoreRing
					label={ __( 'GEO', 'search-intel' ) }
					score={ geoScore }
					color={ getScoreColor( geoScore ) }
				/>
				<ScoreRing
					label={ __( 'Read', 'search-intel' ) }
					score={ readabilityScore }
					color={ getScoreColor( readabilityScore ) }
				/>
			</div>

			<Button
				variant="secondary"
				className="si-analyze-btn"
				onClick={ runAnalysis }
				disabled={ isAnalyzing }
			>
				{ isAnalyzing ? (
					<>
						<Spinner /> { __( 'Analyzing…', 'search-intel' ) }
					</>
				) : (
					__( 'Run Analysis', 'search-intel' )
				) }
			</Button>

			{ analysisResults && Object.keys( checks ).length > 0 && (
				<div className="si-analysis-checks">
					{ Object.entries( checks ).map( ( [ category, items ] ) => (
						<div key={ category } className="si-check-category">
							<h4 className="si-check-category__title">
								{ category.toUpperCase() }
							</h4>
							<ul className="si-check-list">
								{ Array.isArray( items ) && items.map( ( item, i ) => (
									<li
										key={ i }
										className={ `si-check-item si-check-item--${ item.status || 'info' }` }
									>
										<span className="si-check-item__icon">
											{ item.status === 'pass' ? '✓' : item.status === 'fail' ? '✗' : '—' }
										</span>
										<span className="si-check-item__text">
											{ item.message || item }
										</span>
									</li>
								) ) }
							</ul>
						</div>
					) ) }
				</div>
			) }
		</div>
	);
}
