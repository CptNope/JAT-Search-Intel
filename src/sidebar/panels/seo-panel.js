/**
 * SEO Panel — Gutenberg sidebar tab.
 *
 * Fields: title, description, focus keyword, canonical URL,
 * noindex, nofollow, social overrides.
 */

import { TextControl, TextareaControl, ToggleControl, PanelBody } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { META_KEYS } from '../../store';

/**
 * Helper to read/write a single post meta key via core/editor.
 */
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

/**
 * SERP Preview component.
 */
function SerpPreview( { title, description, url } ) {
	const displayTitle = title || __( 'Page Title', 'jat-search-intel' );
	const displayDesc = description || __( 'Meta description will appear here...', 'jat-search-intel' );
	const displayUrl = url || 'https://example.com';

	return (
		<div className="si-serp-preview">
			<div className="si-serp-preview__title">{ displayTitle }</div>
			<div className="si-serp-preview__url">{ displayUrl }</div>
			<div className="si-serp-preview__description">{ displayDesc }</div>
		</div>
	);
}

export default function SeoPanel() {
	const [ title, setTitle ] = usePostMeta( META_KEYS.title );
	const [ description, setDescription ] = usePostMeta( META_KEYS.description );
	const [ focusKeyword, setFocusKeyword ] = usePostMeta( META_KEYS.focusKeyword );
	const [ canonicalUrl, setCanonicalUrl ] = usePostMeta( META_KEYS.canonicalUrl );
	const [ noindex, setNoindex ] = usePostMeta( META_KEYS.noindex );
	const [ nofollow, setNofollow ] = usePostMeta( META_KEYS.nofollow );
	const [ ogTitle, setOgTitle ] = usePostMeta( META_KEYS.ogTitle );
	const [ ogDescription, setOgDescription ] = usePostMeta( META_KEYS.ogDescription );
	const [ twitterTitle, setTwitterTitle ] = usePostMeta( META_KEYS.twitterTitle );
	const [ twitterDescription, setTwitterDescription ] = usePostMeta( META_KEYS.twitterDescription );
	const [ secondaryKeywords, setSecondaryKeywords ] = usePostMeta( META_KEYS.secondaryKeywords );

	const postTitle = useSelect(
		( select ) => select( 'core/editor' ).getEditedPostAttribute( 'title' ) || '',
		[]
	);
	const permalink = useSelect(
		( select ) => select( 'core/editor' ).getPermalink() || '',
		[]
	);

	const displayTitle = title || postTitle;
	const titleLength = displayTitle.length;
	const descLength = description.length;

	return (
		<div className="si-panel si-panel--seo">
			<SerpPreview
				title={ displayTitle }
				description={ description }
				url={ permalink }
			/>

			<TextControl
				label={ __( 'SEO Title', 'jat-search-intel' ) }
				value={ title }
				onChange={ setTitle }
				help={
					<span className={ titleLength > 60 ? 'si-count--over' : '' }>
						{ titleLength }/60
					</span>
				}
			/>

			<TextareaControl
				label={ __( 'Meta Description', 'jat-search-intel' ) }
				value={ description }
				onChange={ setDescription }
				rows={ 3 }
				help={
					<span className={ descLength > 160 ? 'si-count--over' : '' }>
						{ descLength }/160
					</span>
				}
			/>

			<TextControl
				label={ __( 'Focus Keyword', 'jat-search-intel' ) }
				value={ focusKeyword }
				onChange={ setFocusKeyword }
			/>

			<TextControl
				label={ __( 'Secondary Keywords', 'jat-search-intel' ) }
				value={ secondaryKeywords }
				onChange={ setSecondaryKeywords }
				help={ __( 'Comma-separated', 'jat-search-intel' ) }
			/>

			<TextControl
				label={ __( 'Canonical URL', 'jat-search-intel' ) }
				value={ canonicalUrl }
				onChange={ setCanonicalUrl }
				type="url"
			/>

			<div className="si-toggles">
				<ToggleControl
					label={ __( 'noindex', 'jat-search-intel' ) }
					checked={ noindex === '1' || noindex === true }
					onChange={ ( val ) => setNoindex( val ? '1' : '' ) }
				/>
				<ToggleControl
					label={ __( 'nofollow', 'jat-search-intel' ) }
					checked={ nofollow === '1' || nofollow === true }
					onChange={ ( val ) => setNofollow( val ? '1' : '' ) }
				/>
			</div>

			<PanelBody
				title={ __( 'Social Overrides', 'jat-search-intel' ) }
				initialOpen={ false }
				className="si-social-overrides"
			>
				<TextControl
					label={ __( 'OG Title', 'jat-search-intel' ) }
					value={ ogTitle }
					onChange={ setOgTitle }
				/>
				<TextControl
					label={ __( 'OG Description', 'jat-search-intel' ) }
					value={ ogDescription }
					onChange={ setOgDescription }
				/>
				<TextControl
					label={ __( 'Twitter Title', 'jat-search-intel' ) }
					value={ twitterTitle }
					onChange={ setTwitterTitle }
				/>
				<TextControl
					label={ __( 'Twitter Description', 'jat-search-intel' ) }
					value={ twitterDescription }
					onChange={ setTwitterDescription }
				/>
			</PanelBody>
		</div>
	);
}
