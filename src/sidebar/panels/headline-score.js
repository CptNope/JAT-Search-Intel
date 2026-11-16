/**
 * Headline score — shown under the SERP preview in the SEO tab.
 *
 * Scores the post headline with the server-side Headline_Analyzer
 * (POST /jat-search-intel/v1/headline), debounced while typing.
 */

import apiFetch from '@wordpress/api-fetch';
import { useEffect, useState } from '@wordpress/element';
import { PanelBody } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

const STATUS_LABEL = {
	good: __( 'Good', 'jat-search-intel' ),
	ok: __( 'Fair', 'jat-search-intel' ),
	improve: __( 'Improve', 'jat-search-intel' ),
};

export default function HeadlineScore( { headline } ) {
	const [ result, setResult ] = useState( null );
	const [ error, setError ] = useState( false );

	useEffect( () => {
		const text = ( headline || '' ).trim();
		if ( ! text ) {
			setResult( null );
			return undefined;
		}
		let cancelled = false;
		const timer = setTimeout( () => {
			apiFetch( {
				path: '/jat-search-intel/v1/headline',
				method: 'POST',
				data: { headline: text },
			} )
				.then( ( data ) => {
					if ( ! cancelled ) {
						setResult( data );
						setError( false );
					}
				} )
				.catch( () => {
					if ( ! cancelled ) {
						setError( true );
					}
				} );
		}, 600 );
		return () => {
			cancelled = true;
			clearTimeout( timer );
		};
	}, [ headline ] );

	if ( ! headline ) {
		return null;
	}

	let tone = 'improve';
	if ( result && result.score >= 70 ) {
		tone = 'good';
	} else if ( result && result.score >= 50 ) {
		tone = 'ok';
	}

	return (
		<PanelBody
			title={
				result
					? sprintf(
							/* translators: %d: headline score out of 100 */
							__( 'Headline score: %d/100', 'jat-search-intel' ),
							result.score
					  )
					: __( 'Headline score', 'jat-search-intel' )
			}
			initialOpen={ false }
			className={ `si-headline si-headline--${ tone }` }
		>
			{ error && (
				<p>{ __( 'The headline could not be scored right now.', 'jat-search-intel' ) }</p>
			) }
			{ result && (
				<ul className="si-headline__checks">
					{ result.checks.map( ( check ) => (
						<li
							key={ check.id }
							className={ `si-headline__check si-headline__check--${ check.status }` }
						>
							<strong>{ STATUS_LABEL[ check.status ] || check.status }:</strong>{ ' ' }
							{ check.message }
						</li>
					) ) }
				</ul>
			) }
		</PanelBody>
	);
}
