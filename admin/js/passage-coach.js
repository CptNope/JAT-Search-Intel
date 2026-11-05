/**
 * JAT Search Intel — Passage Coach panel for the block editor.
 *
 * Plain script on WordPress globals (no build step). Sends the unsaved post
 * content to jat-search-intel/v1/coach/{id}, lists passage-level findings,
 * jumps to the block each one is about, and can insert starter blocks.
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.plugins || ! wp.element || ! wp.data ) {
		return;
	}

	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var useRef = wp.element.useRef;
	var __ = wp.i18n.__;
	var sprintf = wp.i18n.sprintf;
	var useSelect = wp.data.useSelect;
	var useDispatch = wp.data.useDispatch;
	var components = wp.components;
	var Panel = ( wp.editor && wp.editor.PluginDocumentSettingPanel ) || ( wp.editPost && wp.editPost.PluginDocumentSettingPanel );

	if ( ! Panel ) {
		return;
	}

	var SUMMARY_KEY = '_jat_search_intel_ai_summary';
	var NO_META = {};
	var COLORS = { high: '#d63638', medium: '#dba617', low: '#2271b1' };

	function scrollToBlock( clientId ) {
		window.setTimeout( function () {
			var doc = document;
			var frame = document.querySelector( 'iframe[name="editor-canvas"]' );
			if ( frame && frame.contentDocument ) {
				doc = frame.contentDocument;
			}
			var node = doc.querySelector( '[data-block="' + clientId + '"]' );
			if ( node && node.scrollIntoView ) {
				node.scrollIntoView( { behavior: 'smooth', block: 'center' } );
			}
		}, 50 );
	}

	function PassageCoach() {
		// Subscribe only to what the panel renders, so typing in the post does not re-render it.
		var postId = useSelect( function ( select ) {
			return select( 'core/editor' ).getCurrentPostId();
		}, [] );
		var meta = useSelect( function ( select ) {
			return select( 'core/editor' ).getEditedPostAttribute( 'meta' );
		}, [] ) || NO_META;
		var state = { postId: postId, meta: meta };

		var blockEditor = useDispatch( 'core/block-editor' );
		var editorDispatch = useDispatch( 'core/editor' );

		var resultState = useState( null );
		var results = resultState[ 0 ];
		var setResults = resultState[ 1 ];
		var busyState = useState( false );
		var busy = busyState[ 0 ];
		var setBusy = busyState[ 1 ];
		var errorState = useState( '' );
		var error = errorState[ 0 ];
		var setError = errorState[ 1 ];
		var insertedState = useState( {} );
		var inserted = insertedState[ 0 ];
		var setInserted = insertedState[ 1 ];
		var requestId = useRef( 0 );

		function markInserted( item ) {
			var next = Object.assign( {}, inserted );
			next[ item.type ] = true;
			setInserted( next );
		}

		function run() {
			var editor = wp.data.select( 'core/editor' );
			var id = ++requestId.current;
			setBusy( true );
			setError( '' );
			wp.apiFetch( {
				path: '/jat-search-intel/v1/coach/' + state.postId,
				method: 'POST',
				data: {
					content: editor.getEditedPostContent(),
					title: editor.getEditedPostAttribute( 'title' ) || '',
				},
			} ).then( function ( response ) {
				// Ignore answers to older requests that arrive late.
				if ( id === requestId.current ) {
					setResults( response.suggestions || [] );
				}
			} ).catch( function ( e ) {
				if ( id === requestId.current ) {
					setError( ( e && e.message ) || __( 'Could not check this post.', 'jat-search-intel' ) );
				}
			} ).finally( function () {
				if ( id === requestId.current ) {
					setBusy( false );
				}
			} );
		}

		useEffect( function () {
			if ( state.postId ) {
				run();
			}
		}, [ state.postId ] );

		function clientIdAt( index ) {
			var list = wp.data.select( 'core/block-editor' ).getBlocks();
			if ( index < 0 || ! list.length ) {
				return null;
			}
			return list[ Math.min( index, list.length - 1 ) ].clientId;
		}

		function show( item ) {
			var id = clientIdAt( item.block );
			if ( id ) {
				blockEditor.selectBlock( id );
				scrollToBlock( id );
			}
		}

		function insertIntro() {
			var block = wp.blocks.createBlock( 'core/paragraph', {
				placeholder: __( 'Answer the main question in 40–60 words. Lead with the answer, then one sentence of context.', 'jat-search-intel' ),
			} );
			blockEditor.insertBlocks( block, 0 );
			blockEditor.selectBlock( block.clientId );
			scrollToBlock( block.clientId );
			run();
		}

		function insertTable( item ) {
			var cell = function ( text, tag ) {
				return { content: text, tag: tag };
			};
			var head = [ { cells: [
				cell( __( 'Option', 'jat-search-intel' ), 'th' ),
				cell( __( 'Best for', 'jat-search-intel' ), 'th' ),
				cell( __( 'Price', 'jat-search-intel' ), 'th' ),
				cell( __( 'Key difference', 'jat-search-intel' ), 'th' ),
			] } ];
			var row = function () {
				return { cells: [ cell( '', 'td' ), cell( '', 'td' ), cell( '', 'td' ), cell( '', 'td' ) ] };
			};
			var block = wp.blocks.createBlock( 'core/table', { head: head, body: [ row(), row(), row() ], hasFixedLayout: false } );
			var index = Math.max( 0, Math.min( item.block + 1, wp.data.select( 'core/block-editor' ).getBlocks().length ) );
			blockEditor.insertBlocks( block, index );
			blockEditor.selectBlock( block.clientId );
			scrollToBlock( block.clientId );
			run();
		}

		function act( item ) {
			if ( 'insert-intro' === item.action ) {
				markInserted( item );
				return insertIntro();
			}
			if ( 'insert-table' === item.action ) {
				markInserted( item );
				return insertTable( item );
			}
			return show( item );
		}

		function setSummary( value ) {
			var meta = {};
			meta[ SUMMARY_KEY ] = value;
			editorDispatch.editPost( { meta: meta } );
		}

		function renderItem( item ) {
			var buttons = [];
			if ( item.block >= 0 && 'select' !== item.action ) {
				buttons.push( el( components.Button, { key: 'show', variant: 'tertiary', size: 'small', onClick: function () { show( item ); } }, __( 'Show', 'jat-search-intel' ) ) );
			}
			if ( 'select' === item.action ) {
				buttons.push( el( components.Button, { key: 'go', variant: 'secondary', size: 'small', onClick: function () { show( item ); } }, __( 'Show passage', 'jat-search-intel' ) ) );
			}
			if ( 'insert-intro' === item.action && ! inserted[ item.type ] ) {
				buttons.push( el( components.Button, { key: 'intro', variant: 'secondary', size: 'small', onClick: function () { act( item ); } }, __( 'Insert answer paragraph', 'jat-search-intel' ) ) );
			}
			if ( 'insert-table' === item.action && ! inserted[ item.type ] ) {
				buttons.push( el( components.Button, { key: 'table', variant: 'secondary', size: 'small', onClick: function () { act( item ); } }, __( 'Insert comparison table', 'jat-search-intel' ) ) );
			}

			var children = [
				el( 'strong', { key: 't', style: { display: 'block' } }, item.title ),
				el( 'span', { key: 'd', style: { display: 'block', color: '#50575e', margin: '2px 0 4px' } }, item.detail ),
			];
			if ( item.excerpt ) {
				children.push( el( 'q', { key: 'e', style: { display: 'block', fontStyle: 'italic', color: '#757575', margin: '0 0 6px' } }, item.excerpt ) );
			}
			if ( 'open-summary' === item.action ) {
				children.push( el( components.TextareaControl, {
					key: 'summary',
					label: __( 'AI summary', 'jat-search-intel' ),
					hideLabelFromVision: true,
					rows: 3,
					value: state.meta[ SUMMARY_KEY ] || '',
					onChange: setSummary,
					placeholder: __( 'Two or three factual sentences: what this page covers and its main conclusion.', 'jat-search-intel' ),
					__nextHasNoMarginBottom: true,
				} ) );
			}
			if ( buttons.length ) {
				children.push( el( 'div', { key: 'b', style: { display: 'flex', gap: '6px', flexWrap: 'wrap' } }, buttons ) );
			}

			return el( 'li', {
				key: item.id,
				style: { borderLeft: '3px solid ' + ( COLORS[ item.severity ] || '#dcdcde' ), padding: '4px 0 6px 10px', margin: '0 0 12px' },
			}, children );
		}

		var body;
		if ( error ) {
			body = el( components.Notice, { status: 'error', isDismissible: false }, error );
		} else if ( null === results ) {
			body = el( components.Spinner );
		} else if ( ! results.length ) {
			body = el( 'p', null, __( 'Every passage is in good shape for search and AI answers.', 'jat-search-intel' ) );
		} else {
			body = el( 'ul', { style: { margin: '8px 0 0' } }, results.map( renderItem ) );
		}

		return el( Panel, {
			name: 'jat-search-intel-passage-coach',
			title: results && results.length
				/* translators: %d: number of suggestions. */
				? sprintf( __( 'Passage Coach (%d)', 'jat-search-intel' ), results.length )
				: __( 'Passage Coach', 'jat-search-intel' ),
			className: 'jat-search-intel-passage-coach',
		},
			el( 'p', { style: { marginTop: 0, color: '#50575e' } }, __( 'Search and AI answers quote single passages. These are the ones to improve.', 'jat-search-intel' ) ),
			body,
			el( components.Button, { variant: 'secondary', isBusy: busy, disabled: busy || ! state.postId, onClick: run, style: { marginTop: '8px' } }, __( 'Check again', 'jat-search-intel' ) )
		);
	}

	wp.plugins.registerPlugin( 'jat-search-intel-passage-coach', { render: PassageCoach, icon: null } );
}( window.wp ) );
