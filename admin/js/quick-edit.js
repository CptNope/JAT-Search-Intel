/**
 * Quick Edit: fill the JAT Search Intel fields from the row's hidden data.
 *
 * @package JAT_Search_Intel
 */
( function () {
	'use strict';

	if ( typeof window.inlineEditPost === 'undefined' ) {
		return;
	}

	var originalEdit = window.inlineEditPost.edit;

	window.inlineEditPost.edit = function ( id ) {
		originalEdit.apply( this, arguments );

		var postId = typeof id === 'object' ? parseInt( this.getId( id ), 10 ) : parseInt( id, 10 );
		if ( ! postId ) {
			return;
		}

		var row = document.getElementById( 'post-' + postId );
		var editRow = document.getElementById( 'edit-' + postId );
		if ( ! row || ! editRow ) {
			return;
		}

		var data = row.querySelector( '.jsi-inline-data' );
		if ( ! data ) {
			return;
		}

		var set = function ( name, value ) {
			var field = editRow.querySelector( '[name="' + name + '"]' );
			if ( field ) {
				field.value = value || '';
			}
		};

		set( 'jsi_title', data.getAttribute( 'data-title' ) );
		set( 'jsi_description', data.getAttribute( 'data-description' ) );
		set( 'jsi_keyword', data.getAttribute( 'data-keyword' ) );

		var noindex = editRow.querySelector( '[name="jsi_noindex"]' );
		if ( noindex ) {
			noindex.checked = data.getAttribute( 'data-noindex' ) === '1';
		}
	};
} )();
