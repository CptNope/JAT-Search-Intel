/**
 * Search Intel — Gutenberg Sidebar Panel
 *
 * Registers a PluginDocumentSettingPanel in the Block Editor
 * with SEO, AEO, GEO, and Analysis tabs.
 */

import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { TabPanel, Icon } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';

import '../store';
import SeoPanel from './panels/seo-panel';
import AeoPanel from './panels/aeo-panel';
import GeoPanel from './panels/geo-panel';
import ScorePanel from './panels/score-panel';

import './sidebar.css';

const TABS = [
	{
		name: 'seo',
		title: __( 'SEO', 'jat-search-intel' ),
		className: 'si-sidebar-tab',
	},
	{
		name: 'aeo',
		title: __( 'AEO', 'jat-search-intel' ),
		className: 'si-sidebar-tab',
	},
	{
		name: 'geo',
		title: __( 'GEO', 'jat-search-intel' ),
		className: 'si-sidebar-tab',
	},
	{
		name: 'analysis',
		title: __( 'Analysis', 'jat-search-intel' ),
		className: 'si-sidebar-tab',
	},
];

function SearchIntelSidebar() {
	const postType = useSelect(
		( select ) => select( 'core/editor' ).getCurrentPostType(),
		[]
	);

	// Only show for post types that support custom-fields.
	if ( ! postType ) {
		return null;
	}

	return (
		<PluginDocumentSettingPanel
			name="search-intel-panel"
			title={ __( 'Search Intel', 'jat-search-intel' ) }
			className="search-intel-sidebar"
		>
			<TabPanel
				className="si-sidebar-tabs"
				tabs={ TABS }
			>
				{ ( tab ) => {
					switch ( tab.name ) {
						case 'seo':
							return <SeoPanel />;
						case 'aeo':
							return <AeoPanel />;
						case 'geo':
							return <GeoPanel />;
						case 'analysis':
							return <ScorePanel />;
						default:
							return null;
					}
				} }
			</TabPanel>
		</PluginDocumentSettingPanel>
	);
}

registerPlugin( 'search-intel-sidebar', {
	render: SearchIntelSidebar,
	icon: 'chart-line',
} );
