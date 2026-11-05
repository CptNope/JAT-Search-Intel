/**
 * ESLint flat config (ESLint 9+) for the plugin's plain admin scripts.
 * Same rules and globals as the former .eslintrc.json.
 */
'use strict';

let environment;
try {
	const globals = require( 'globals' );
	environment = { ...globals.browser, ...globals.jquery };
} catch ( e ) {
	// Fallback if the globals package is not installed.
	environment = Object.fromEntries(
		[
			'window', 'document', 'navigator', 'location', 'history', 'console', 'alert', 'confirm', 'prompt',
			'setTimeout', 'clearTimeout', 'setInterval', 'clearInterval', 'requestAnimationFrame',
			'fetch', 'FormData', 'URL', 'URLSearchParams', 'XMLHttpRequest', 'Blob', 'FileReader',
			'Event', 'CustomEvent', 'HTMLElement', 'Node', 'MutationObserver', 'DOMParser',
			'getComputedStyle', 'localStorage', 'sessionStorage', 'jQuery', '$',
		].map( ( name ) => [ name, 'readonly' ] )
	);
}

module.exports = [
	{
		files: [ 'admin/js/**/*.js' ],
		languageOptions: {
			ecmaVersion: 2020,
			sourceType: 'module',
			parserOptions: { ecmaFeatures: { jsx: true } },
			globals: {
				...environment,
				wp: 'readonly',
				ajaxurl: 'readonly',
				jatSearchIntel: 'readonly',
				tinymce: 'readonly',
			},
		},
		rules: {
			'no-unused-vars': 'warn',
			'no-undef': 'error',
			'no-console': 'warn',
			semi: [ 'error', 'always' ],
			eqeqeq: 'warn',
		},
	},
];
