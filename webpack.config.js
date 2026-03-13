const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		'sidebar': path.resolve( __dirname, 'src/sidebar/index.js' ),
		'blocks/faq/index': path.resolve( __dirname, 'src/blocks/faq/index.js' ),
		'blocks/howto/index': path.resolve( __dirname, 'src/blocks/howto/index.js' ),
		'blocks/toc/index': path.resolve( __dirname, 'src/blocks/toc/index.js' ),
		'blocks/breadcrumbs/index': path.resolve( __dirname, 'src/blocks/breadcrumbs/index.js' ),
		'blocks/answer-box/index': path.resolve( __dirname, 'src/blocks/answer-box/index.js' ),
		'blocks/citation/index': path.resolve( __dirname, 'src/blocks/citation/index.js' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'build' ),
	},
};
