/**
 * JAT Search Intel — editor extras (plain script on WordPress globals, no build step).
 *
 * - "Preferred Source on Google" block (rendered on the server).
 * - Primary category panel.
 * - Social preview panel (Facebook / LinkedIn and X cards).
 * - Reading time and "don't update the last-modified date" panel.
 * - SEO change history panel.
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.element || ! wp.blocks || ! wp.i18n ) {
		return;
	}

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var __ = wp.i18n.__;
	var sprintf = wp.i18n.sprintf;
	var _n = wp.i18n._n;
	var components = wp.components;

	/* ------------------------------------------------------------------ *
	 * Block: Preferred Source on Google
	 * ------------------------------------------------------------------ */
	if ( ! wp.blocks.getBlockType( 'jat-search-intel/preferred-source' ) ) {
		var InspectorControls = ( wp.blockEditor || {} ).InspectorControls;
		var ServerSideRender = wp.serverSideRender;

		wp.blocks.registerBlockType( 'jat-search-intel/preferred-source', {
			apiVersion: 3,
			title: __( 'Preferred Source on Google', 'jat-search-intel' ),
			description: __( 'A button that lets readers add this site as a preferred source in Google Search.', 'jat-search-intel' ),
			icon: 'star-filled',
			category: 'widgets',
			keywords: [ 'google', 'preferred', 'source', 'news' ],
			attributes: { label: { type: 'string', default: '' } },
			edit: function ( props ) {
				var blockProps = wp.blockEditor && wp.blockEditor.useBlockProps ? wp.blockEditor.useBlockProps() : {};
				return el( 'div', blockProps,
					InspectorControls && el( InspectorControls, null,
						el( components.PanelBody, { title: __( 'Button', 'jat-search-intel' ) },
							el( components.TextControl, {
								label: __( 'Label', 'jat-search-intel' ),
								help: __( 'Leave empty for "Add [site name] as a preferred source on Google".', 'jat-search-intel' ),
								value: props.attributes.label,
								onChange: function ( value ) {
									props.setAttributes( { label: value } );
								},
								__nextHasNoMarginBottom: true,
							} )
						)
					),
					ServerSideRender
						? el( ServerSideRender, { block: 'jat-search-intel/preferred-source', attributes: props.attributes } )
						: el( 'p', null, __( 'Preferred Source on Google', 'jat-search-intel' ) )
				);
			},
			save: function () {
				return null;
			},
		} );
	}

	/* ------------------------------------------------------------------ *
	 * Post editor panels
	 * ------------------------------------------------------------------ */
	var Panel = ( wp.editor && wp.editor.PluginDocumentSettingPanel ) || ( wp.editPost && wp.editPost.PluginDocumentSettingPanel );
	if ( ! wp.plugins || ! wp.data || ! Panel ) {
		return;
	}

	var useSelect = wp.data.useSelect;
	var useDispatch = wp.data.useDispatch;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var NO_META = {};
	var WORDS_PER_MINUTE = 238;
	var KEYS = {
		primary: '_jat_search_intel_primary_category',
		freeze: '_jat_search_intel_freeze_modified',
		title: '_jat_search_intel_title',
		description: '_jat_search_intel_description',
		ogTitle: '_jat_search_intel_og_title',
		ogDescription: '_jat_search_intel_og_description',
		ogImage: '_jat_search_intel_og_image',
		xTitle: '_jat_search_intel_twitter_title',
		xDescription: '_jat_search_intel_twitter_description',
		xImage: '_jat_search_intel_twitter_image',
	};

	function useMeta() {
		var meta = useSelect( function ( select ) {
			return select( 'core/editor' ).getEditedPostAttribute( 'meta' );
		}, [] ) || NO_META;
		var editPost = useDispatch( 'core/editor' ).editPost;
		function setMeta( key, value ) {
			var update = {};
			update[ key ] = value;
			editPost( { meta: update } );
		}
		return [ meta, setMeta ];
	}

	function hasMeta( meta, key ) {
		return Object.prototype.hasOwnProperty.call( meta, key );
	}

	function stripTags( html ) {
		var div = document.createElement( 'div' );
		div.innerHTML = String( html || '' );
		return ( div.textContent || '' ).replace( /\s+/g, ' ' ).trim();
	}

	function truncate( text, max ) {
		text = String( text || '' );
		return text.length > max ? text.slice( 0, max - 1 ).trim() + '…' : text;
	}

	/* Primary category ------------------------------------------------- */
	function PrimaryCategory() {
		var state = useMeta();
		var meta = state[ 0 ];
		var setMeta = state[ 1 ];
		var ids = useSelect( function ( select ) {
			return select( 'core/editor' ).getEditedPostAttribute( 'categories' );
		}, [] );
		var terms = useSelect( function ( select ) {
			if ( ! ids || ids.length < 2 ) {
				return null;
			}
			return select( 'core' ).getEntityRecords( 'taxonomy', 'category', { include: ids, per_page: 100, _fields: 'id,name' } );
		}, [ ids ] );

		if ( ! hasMeta( meta, KEYS.primary ) || ! ids || ids.length < 2 ) {
			return null;
		}

		var options = [ { label: __( 'Default (first category)', 'jat-search-intel' ), value: '' } ].concat(
			( terms || [] ).map( function ( term ) {
				return { label: stripTags( term.name ), value: String( term.id ) };
			} )
		);
		var value = String( meta[ KEYS.primary ] || '' );
		if ( value && ids.indexOf( parseInt( value, 10 ) ) === -1 ) {
			value = '';
		}

		return el( Panel, { name: 'jat-search-intel-primary-category', title: __( 'Primary category', 'jat-search-intel' ) },
			el( components.SelectControl, {
				label: __( 'Primary category', 'jat-search-intel' ),
				hideLabelFromVision: true,
				value: value,
				options: options,
				onChange: function ( next ) {
					setMeta( KEYS.primary, next );
				},
				help: __( 'Used in the permalink, breadcrumbs and schema.', 'jat-search-intel' ),
				__nextHasNoMarginBottom: true,
			} )
		);
	}

	/* Social preview --------------------------------------------------- */
	function useFeaturedImageUrl() {
		return useSelect( function ( select ) {
			var id = select( 'core/editor' ).getEditedPostAttribute( 'featured_media' );
			if ( ! id ) {
				return '';
			}
			var media = select( 'core' ).getMedia( id, { context: 'view' } );
			return media ? ( ( media.media_details && media.media_details.sizes && media.media_details.sizes.large && media.media_details.sizes.large.source_url ) || media.source_url || '' ) : '';
		}, [] );
	}

	function Card( props ) {
		var imageStyle = {
			width: '100%',
			aspectRatio: '1.91 / 1',
			background: props.image ? 'center / cover no-repeat url("' + encodeURI( props.image ) + '")' : '#f0f0f1',
			borderRadius: props.rounded ? '12px 12px 0 0' : 0,
			display: 'flex',
			alignItems: 'center',
			justifyContent: 'center',
			color: '#757575',
			fontSize: '12px',
		};
		return el( 'div', { style: { border: '1px solid #dcdcde', borderRadius: props.rounded ? '12px' : '2px', overflow: 'hidden', margin: '6px 0 14px', background: '#fff' } },
			el( 'div', { style: imageStyle, role: props.image ? 'img' : undefined, 'aria-label': props.image ? __( 'Share image', 'jat-search-intel' ) : undefined }, props.image ? null : __( 'No image: add a featured image or social image', 'jat-search-intel' ) ),
			el( 'div', { style: { padding: '8px 10px', background: props.rounded ? '#fff' : '#f2f3f5' } },
				el( 'div', { style: { fontSize: '11px', textTransform: 'uppercase', color: '#606770' } }, props.domain ),
				el( 'div', { style: { fontWeight: 600, fontSize: '14px', color: '#1d2129', margin: '2px 0' } }, truncate( props.title, 88 ) ),
				props.description ? el( 'div', { style: { fontSize: '12px', color: '#606770' } }, truncate( props.description, 110 ) ) : null
			)
		);
	}

	function SocialPreview() {
		var state = useMeta();
		var meta = state[ 0 ];
		var post = useSelect( function ( select ) {
			var editor = select( 'core/editor' );
			return {
				title: editor.getEditedPostAttribute( 'title' ),
				excerpt: editor.getEditedPostAttribute( 'excerpt' ),
				link: editor.getPermalink ? editor.getPermalink() : '',
			};
		}, [] );
		var featured = useFeaturedImageUrl();

		if ( ! hasMeta( meta, KEYS.title ) ) {
			return null;
		}

		var domain = '';
		try {
			domain = new window.URL( post.link || window.location.href ).host;
		} catch ( e ) {
			domain = window.location.host;
		}

		var seoTitle = meta[ KEYS.title ] || stripTags( post.title );
		var seoDescription = meta[ KEYS.description ] || stripTags( post.excerpt );
		var ogTitle = meta[ KEYS.ogTitle ] || seoTitle;
		var ogDescription = meta[ KEYS.ogDescription ] || seoDescription;
		var ogImage = meta[ KEYS.ogImage ] || featured;

		return el( Panel, { name: 'jat-search-intel-social-preview', title: __( 'Social preview', 'jat-search-intel' ) },
			el( 'p', { style: { margin: '0 0 4px', fontWeight: 600 } }, __( 'Facebook and LinkedIn', 'jat-search-intel' ) ),
			el( Card, { domain: domain, title: ogTitle, description: ogDescription, image: ogImage } ),
			el( 'p', { style: { margin: '0 0 4px', fontWeight: 600 } }, __( 'X', 'jat-search-intel' ) ),
			el( Card, {
				rounded: true,
				domain: domain,
				title: meta[ KEYS.xTitle ] || ogTitle,
				description: '',
				image: meta[ KEYS.xImage ] || ogImage,
			} ),
			el( 'p', { className: 'description', style: { color: '#757575' } }, __( 'Change the social title, description and image in the JAT Search Intel sidebar.', 'jat-search-intel' ) )
		);
	}

	/* Reading time and modified date ------------------------------------ */
	function ReadingAndDates() {
		var state = useMeta();
		var meta = state[ 0 ];
		var setMeta = state[ 1 ];
		var info = useSelect( function ( select ) {
			var editor = select( 'core/editor' );
			return {
				content: editor.getEditedPostContent(),
				status: editor.getEditedPostAttribute( 'status' ),
				modified: editor.getEditedPostAttribute( 'modified' ),
			};
		}, [] );

		if ( ! hasMeta( meta, KEYS.freeze ) ) {
			return null;
		}

		var text = stripTags( info.content );
		var words = text ? text.split( ' ' ).length : 0;
		var minutes = words ? Math.max( 1, Math.ceil( words / WORDS_PER_MINUTE ) ) : 0;

		return el( Panel, { name: 'jat-search-intel-reading-dates', title: __( 'Reading time & last modified', 'jat-search-intel' ) },
			el( 'p', null,
				minutes
					/* translators: 1: number of minutes, 2: number of words. */
					? sprintf( _n( 'About %1$d minute to read (%2$d words).', 'About %1$d minutes to read (%2$d words).', minutes, 'jat-search-intel' ), minutes, words )
					: __( 'Add content to see the reading time.', 'jat-search-intel' )
			),
			el( components.ToggleControl, {
				label: __( 'Don\'t update the last-modified date', 'jat-search-intel' ),
				help: 'publish' === info.status
					? __( 'For small fixes. Saving keeps the current "last updated" date in search results, schema and the sitemap.', 'jat-search-intel' )
					: __( 'Takes effect once the post is published.', 'jat-search-intel' ),
				checked: '1' === String( meta[ KEYS.freeze ] || '' ),
				onChange: function ( on ) {
					setMeta( KEYS.freeze, on ? '1' : '' );
				},
				__nextHasNoMarginBottom: true,
			} )
		);
	}

	/* SEO change history ------------------------------------------------ */
	function History() {
		var postId = useSelect( function ( select ) {
			return select( 'core/editor' ).getCurrentPostId();
		}, [] );
		var isSaving = useSelect( function ( select ) {
			var editor = select( 'core/editor' );
			return editor.isSavingPost() && ! editor.isAutosavingPost();
		}, [] );
		var entriesState = useState( null );
		var entries = entriesState[ 0 ];
		var setEntries = entriesState[ 1 ];
		var busyState = useState( false );
		var busy = busyState[ 0 ];
		var setBusy = busyState[ 1 ];
		var editPost = useDispatch( 'core/editor' ).editPost;
		var notices = useDispatch( 'core/notices' );

		function load() {
			if ( ! postId || ! wp.apiFetch ) {
				return;
			}
			wp.apiFetch( { path: '/jat-search-intel/v1/history/' + postId } ).then( function ( response ) {
				setEntries( response.entries || [] );
			} ).catch( function () {
				setEntries( [] );
			} );
		}

		useEffect( load, [ postId ] );
		useEffect( function () {
			if ( ! isSaving ) {
				load();
			}
		}, [ isSaving ] );

		function restore( entry ) {
			setBusy( true );
			wp.apiFetch( { path: '/jat-search-intel/v1/history/' + postId + '/restore', method: 'POST', data: { entry: entry.id } } ).then( function ( response ) {
				// Mirror the restored values in the editor so a later save doesn't overwrite them.
				if ( response.meta ) {
					editPost( { meta: response.meta } );
				}
				notices.createSuccessNotice( __( 'SEO fields restored.', 'jat-search-intel' ), { type: 'snackbar' } );
				load();
			} ).catch( function ( e ) {
				notices.createErrorNotice( ( e && e.message ) || __( 'Could not restore.', 'jat-search-intel' ), { type: 'snackbar' } );
			} ).finally( function () {
				setBusy( false );
			} );
		}

		if ( null === entries || ! entries.length ) {
			return null;
		}

		return el( Panel, { name: 'jat-search-intel-history', title: __( 'SEO change history', 'jat-search-intel' ) },
			el( 'ul', { style: { margin: 0 } }, entries.slice( 0, 10 ).map( function ( entry ) {
				return el( 'li', { key: entry.id, style: { borderLeft: '3px solid #dcdcde', padding: '2px 0 6px 8px', margin: '0 0 10px' } },
					el( 'strong', { style: { display: 'block' } }, entry.when ),
					el( 'span', { style: { display: 'block', color: '#50575e' } },
						/* translators: 1: user name, 2: list of changed fields. */
						sprintf( __( '%1$s changed %2$s', 'jat-search-intel' ), entry.user, entry.fields.join( ', ' ) )
					),
					el( components.Button, { variant: 'link', disabled: busy, onClick: function () {
						restore( entry );
					} }, __( 'Restore previous values', 'jat-search-intel' ) )
				);
			} ) )
		);
	}

	function Extras() {
		return el( Fragment, null,
			el( PrimaryCategory ),
			el( ReadingAndDates ),
			el( SocialPreview ),
			el( History )
		);
	}

	wp.plugins.registerPlugin( 'jat-search-intel-editor-extras', { render: Extras, icon: null } );
}( window.wp ) );
