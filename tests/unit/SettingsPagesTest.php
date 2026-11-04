<?php
namespace JAT_Search_Intel\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Every checkbox a settings page "manages" must exist on that page, or
 * saving the page would silently switch the option off.
 */
class SettingsPagesTest extends TestCase {

	private const VIEWS = [
		'general' => 'admin/views/settings-general.php',
		'seo'     => 'admin/views/settings-seo.php',
		'aeo'     => 'admin/views/settings-aeo.php',
		'geo'     => 'admin/views/settings-geo.php',
		'sitemap' => 'admin/views/settings-sitemap.php',
		'ai'      => 'admin/views/settings-ai.php',
		'rank'    => 'includes/class-rank-tracker.php',
	];

	/**
	 * @return array<string,string[]>
	 */
	private function managed_bools(): array {
		$src   = (string) file_get_contents( JAT_SEARCH_INTEL_DIR . 'includes/class-admin.php' );
		$start = strpos( $src, '$page_bools = [' );
		$block = substr( $src, $start, strpos( $src, '];', $start ) - $start );
		preg_match_all( "/'(\\w+)' => \\[(.*?)\\]/s", $block, $m, PREG_SET_ORDER );

		$pages = [];
		foreach ( $m as $match ) {
			preg_match_all( "/'(\\w+)'/", $match[2], $keys );
			$pages[ $match[1] ] = $keys[1];
		}
		return $pages;
	}

	public function test_every_settings_page_is_mapped(): void {
		$this->assertSame( array_keys( self::VIEWS ), array_keys( $this->managed_bools() ) );
	}

	public function test_managed_checkboxes_exist_on_their_page(): void {
		foreach ( $this->managed_bools() as $page => $bools ) {
			$view = (string) file_get_contents( JAT_SEARCH_INTEL_DIR . self::VIEWS[ $page ] );
			foreach ( $bools as $key ) {
				$this->assertStringContainsString( "jat_search_intel_settings[{$key}]", $view, "Saving the {$page} page would switch off {$key}: no checkbox for it." );
			}
		}
	}
}
