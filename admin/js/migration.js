/**
 * Migration page: preview and run imports from other SEO plugins.
 *
 * @package JAT_Search_Intel
 */
( function () {
	'use strict';

	var config = window.jatSearchIntelMigration;
	if ( ! config ) {
		return;
	}
	var t = config.i18n || {};

	function el( tag, text, attrs ) {
		var node = document.createElement( tag );
		if ( text !== undefined && text !== null ) {
			node.textContent = String( text );
		}
		Object.keys( attrs || {} ).forEach( function ( key ) {
			node.setAttribute( key, attrs[ key ] );
		} );
		return node;
	}

	function addRow( table, label, value ) {
		var row = el( 'tr' );
		row.appendChild( el( 'th', label, { scope: 'row' } ) );
		row.appendChild( el( 'td', value ) );
		table.appendChild( row );
	}

	function renderPreview( box, data ) {
		box.textContent = '';
		var summary = el( 'table', null, { class: 'widefat striped' } );
		addRow( summary, t.posts, data.post_count || 0 );
		addRow( summary, t.terms, data.terms || 0 );
		addRow( summary, t.redirects, data.redirects || 0 );
		addRow( summary, t.settings, ( data.settings || [] ).join( ', ' ) || '0' );
		box.appendChild( summary );

		var fields = data.fields || {};
		var keys = Object.keys( fields );
		if ( ! keys.length ) {
			return;
		}
		var table = el( 'table', null, { class: 'widefat striped', style: 'margin-top:12px' } );
		var head = el( 'tr' );
		[ t.field, t.count, t.example ].forEach( function ( label ) {
			head.appendChild( el( 'th', label, { scope: 'col' } ) );
		} );
		table.appendChild( head );
		keys.forEach( function ( key ) {
			var row = el( 'tr' );
			var sample = ( fields[ key ].samples || [] )[ 0 ];
			row.appendChild( el( 'td', key ) );
			row.appendChild( el( 'td', fields[ key ].count ) );
			row.appendChild( el( 'td', sample ? sample.title + ': ' + sample.value : '' ) );
			table.appendChild( row );
		} );
		box.appendChild( table );
	}

	function renderResult( box, data ) {
		box.textContent = '';
		var table = el( 'table', null, { class: 'widefat striped' } );
		addRow( table, t.migrated, data.migrated || 0 );
		addRow( table, t.skipped, data.skipped || 0 );
		addRow( table, t.terms, data.terms || 0 );
		addRow( table, t.redirects, data.redirects || 0 );
		addRow( table, t.settings, ( data.settings || [] ).length );
		box.appendChild( table );
		box.appendChild( el( 'p', t.done ) );
	}

	function send( action, source, overwrite, box, buttons ) {
		var body = new window.FormData();
		body.append( 'action', action === 'run' ? 'jat_search_intel_migration_run' : 'jat_search_intel_migration_dryrun' );
		body.append( 'nonce', config.nonce );
		body.append( 'source', source );
		body.append( 'overwrite', overwrite ? '1' : '0' );

		buttons.forEach( function ( b ) {
			b.disabled = true;
		} );
		box.textContent = t.working;

		window
			.fetch( config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( json ) {
				if ( ! json || ! json.success ) {
					throw new Error( ( json && json.data ) || t.failed );
				}
				if ( action === 'run' ) {
					renderResult( box, json.data );
				} else {
					renderPreview( box, json.data );
				}
			} )
			.catch( function ( error ) {
				box.textContent = ( error && error.message ) || t.failed;
			} )
			.finally( function () {
				buttons.forEach( function ( b ) {
					b.disabled = false;
				} );
			} );
	}

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '[data-jsi-migrate]' );
		if ( ! button ) {
			return;
		}
		var source = button.getAttribute( 'data-source' );
		var action = button.getAttribute( 'data-jsi-migrate' );
		var card = button.closest( '.card' );
		var box = card.querySelector( '[data-jsi-result]' );
		var overwrite = card.querySelector( '[data-jsi-overwrite]' );
		var buttons = Array.prototype.slice.call( card.querySelectorAll( '[data-jsi-migrate]' ) );

		if ( action === 'run' && ! window.confirm( t.confirm ) ) {
			return;
		}
		send( action, source, overwrite && overwrite.checked, box, buttons );
	} );
} )();
