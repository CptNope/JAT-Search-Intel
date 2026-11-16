/**
 * Redirects page: add, edit, delete, import and export rules, and clear
 * the 404 log.
 *
 * @package JAT_Search_Intel
 */
( function () {
	'use strict';

	var config = window.jatSearchIntelRedirects;
	if ( ! config ) {
		return;
	}
	var t = config.i18n || {};

	function $( id ) {
		return document.getElementById( id );
	}

	function post( action, data ) {
		var body = data instanceof FormData ? data : new FormData();
		if ( ! ( data instanceof FormData ) ) {
			Object.keys( data || {} ).forEach( function ( key ) {
				body.append( key, data[ key ] );
			} );
		}
		body.append( 'action', action );
		body.append( 'nonce', config.nonce );
		return fetch( config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } ).then(
			function ( response ) {
				return response.json();
			}
		);
	}

	function download( action, extra ) {
		var params = new URLSearchParams( Object.assign( { action: action, nonce: config.nonce }, extra || {} ) );
		window.location.href = config.ajaxUrl + '?' + params.toString();
	}

	// ── Modal ─────────────────────────────────────────────────────────────
	var modal = $( 'si-redirect-modal' );
	var lastFocus = null;

	function openModal( values ) {
		if ( ! modal ) {
			return;
		}
		values = values || {};
		$( 'si-modal-title' ).textContent = values.id ? t.edit : t.add;
		$( 'si-redirect-id' ).value = values.id || '0';
		$( 'si-source-url' ).value = values.source || '';
		$( 'si-target-url' ).value = values.target || '';
		$( 'si-status-code' ).value = values.code || '301';
		$( 'si-match-type' ).value = values.type || 'exact';
		$( 'si-redirect-note' ).value = values.note || '';
		$( 'si-redirect-enabled' ).checked = values.enabled !== '0';

		lastFocus = document.activeElement;
		modal.style.cssText = 'display:block;position:fixed;inset:0;z-index:100000;background:rgba(0,0,0,.5);overflow:auto;';
		modal.setAttribute( 'role', 'dialog' );
		modal.setAttribute( 'aria-modal', 'true' );
		modal.setAttribute( 'aria-labelledby', 'si-modal-title' );
		$( values.source ? 'si-target-url' : 'si-source-url' ).focus();
	}

	function closeModal() {
		if ( modal ) {
			modal.style.display = 'none';
		}
		if ( lastFocus && lastFocus.focus ) {
			lastFocus.focus();
		}
	}

	function save() {
		var button = $( 'si-save-redirect' );
		button.disabled = true;
		post( 'jat_search_intel_save_redirect', {
			id: $( 'si-redirect-id' ).value,
			source_url: $( 'si-source-url' ).value,
			target_url: $( 'si-target-url' ).value,
			status_code: $( 'si-status-code' ).value,
			match_type: $( 'si-match-type' ).value,
			note: $( 'si-redirect-note' ).value,
			enabled: $( 'si-redirect-enabled' ).checked ? '1' : '',
		} )
			.then( function ( json ) {
				if ( json && json.success ) {
					window.location.reload();
					return;
				}
				window.alert( t.saveFailed ); // eslint-disable-line no-alert
			} )
			.catch( function () {
				window.alert( t.failed ); // eslint-disable-line no-alert
			} )
			.finally( function () {
				button.disabled = false;
			} );
	}

	// ── Events ────────────────────────────────────────────────────────────
	document.addEventListener( 'click', function ( event ) {
		var target = event.target.closest ? event.target.closest( 'button' ) : null;
		if ( ! target ) {
			if ( modal && event.target === modal ) {
				closeModal();
			}
			return;
		}

		if ( target.id === 'si-add-redirect' ) {
			openModal();
		} else if ( target.classList.contains( 'si-edit-redirect' ) ) {
			var row = target.closest( 'tr' );
			openModal( {
				id: row.dataset.id,
				source: row.dataset.source,
				target: row.dataset.target,
				code: row.dataset.code,
				type: row.dataset.type,
				note: row.dataset.note,
				enabled: row.dataset.enabled,
			} );
		} else if ( target.classList.contains( 'si-create-redirect-from-404' ) ) {
			openModal( { source: target.dataset.source } );
		} else if ( target.classList.contains( 'si-delete-redirect' ) ) {
			// eslint-disable-next-line no-alert
			if ( window.confirm( t.confirmDelete ) ) {
				post( 'jat_search_intel_delete_redirect', { id: target.dataset.id } ).then( function ( json ) {
					if ( json && json.success ) {
						target.closest( 'tr' ).remove();
					} else {
						window.alert( t.failed ); // eslint-disable-line no-alert
					}
				} );
			}
		} else if ( target.id === 'si-save-redirect' ) {
			save();
		} else if ( target.classList.contains( 'si-close-modal' ) ) {
			closeModal();
		} else if ( target.id === 'si-export-csv' ) {
			download( 'jat_search_intel_export_redirects' );
		} else if ( target.classList.contains( 'si-export-server' ) ) {
			download( 'jat_search_intel_export_server_rules', { format: target.dataset.format } );
		} else if ( target.id === 'si-import-csv' ) {
			$( 'si-import-file' ).click();
		} else if ( target.id === 'si-clear-404-log' ) {
			// eslint-disable-next-line no-alert
			if ( window.confirm( t.confirmClear ) ) {
				post( 'jat_search_intel_clear_404_log' ).then( function () {
					window.location.reload();
				} );
			}
		}
	} );

	document.addEventListener( 'keydown', function ( event ) {
		if ( event.key === 'Escape' && modal && modal.style.display !== 'none' ) {
			closeModal();
		}
	} );

	var fileInput = $( 'si-import-file' );
	if ( fileInput ) {
		fileInput.addEventListener( 'change', function () {
			if ( ! fileInput.files.length ) {
				return;
			}
			var data = new FormData();
			data.append( 'csv_file', fileInput.files[ 0 ] );
			post( 'jat_search_intel_import_redirects', data )
				.then( function ( json ) {
					if ( json && json.success ) {
						var r = json.data || {};
						window.alert( // eslint-disable-line no-alert
							( t.imported || '' )
								.replace( '%1$d', r.imported || 0 )
								.replace( '%2$d', r.skipped || 0 )
								.replace( '%3$d', r.errors || 0 )
						);
						window.location.reload();
					} else {
						window.alert( t.failed ); // eslint-disable-line no-alert
					}
				} )
				.catch( function () {
					window.alert( t.failed ); // eslint-disable-line no-alert
				} );
			fileInput.value = '';
		} );
	}
} )();
