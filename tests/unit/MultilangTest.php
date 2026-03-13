<?php
namespace Search_Intel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Search_Intel\Multilang;

class MultilangTest extends TestCase {

	protected function setUp(): void {
		global $si_test_state;
		$si_test_state = [
			'options'    => [],
			'post_meta'  => [],
			'posts'      => [],
			'transients' => [],
			'actions'    => [],
		];
	}

	private function make( array $settings = [] ): Multilang {
		return new Multilang( array_merge( [ 'enable_multilang' => true ], $settings ) );
	}

	// ── Plugin Detection ─────────────────────────────────────────────────────

	public function test_detect_plugin_none(): void {
		$ml = $this->make();
		$this->assertEquals( '', $ml->detect_plugin() );
	}

	// ── Languages ────────────────────────────────────────────────────────────

	public function test_get_languages_returns_site_locale(): void {
		$ml = $this->make();
		$langs = $ml->get_languages();
		$this->assertIsArray( $langs );
		$this->assertNotEmpty( $langs );
	}

	public function test_get_default_language(): void {
		$ml = $this->make();
		$lang = $ml->get_default_language();
		$this->assertIsString( $lang );
		$this->assertNotEmpty( $lang );
	}

	public function test_get_post_language(): void {
		$ml = $this->make();
		$lang = $ml->get_post_language( 1 );
		$this->assertIsString( $lang );
	}

	// ── Post Translations ────────────────────────────────────────────────────

	public function test_get_post_translations_returns_self(): void {
		$ml = $this->make();
		$translations = $ml->get_post_translations( 1 );
		$this->assertIsArray( $translations );
		$this->assertNotEmpty( $translations );
		// With no multilingual plugin, should return the post itself.
		$this->assertContains( 1, $translations );
	}

	// ── Per-Language Meta ────────────────────────────────────────────────────

	public function test_get_lang_meta_falls_back_to_base(): void {
		global $si_test_state;
		$si_test_state['post_meta'][1]['_search_intel_title'] = 'Base Title';

		$ml = $this->make();
		$val = $ml->get_lang_meta( 1, '_search_intel_title', 'en' );
		$this->assertEquals( 'Base Title', $val );
	}

	public function test_get_lang_meta_uses_lang_specific(): void {
		global $si_test_state;
		$si_test_state['post_meta'][1]['_search_intel_title'] = 'Base Title';
		$si_test_state['post_meta'][1]['_search_intel_title_fr'] = 'Titre Français';

		$ml = $this->make();
		$val = $ml->get_lang_meta( 1, '_search_intel_title', 'fr' );
		$this->assertEquals( 'Titre Français', $val );
	}

	public function test_save_lang_meta(): void {
		global $si_test_state;
		$ml = $this->make();
		$ml->save_lang_meta( 1, '_search_intel_title', 'Título', 'es' );

		$this->assertEquals( 'Título', $si_test_state['post_meta'][1]['_search_intel_title_es'] );
	}

	// ── Language Sitemap URLs ────────────────────────────────────────────────

	public function test_get_language_sitemap_urls_empty(): void {
		$ml = $this->make();
		$urls = $ml->get_language_sitemap_urls( 'en' );
		$this->assertIsArray( $urls );
	}

	// ── Language llms.txt ────────────────────────────────────────────────────

	public function test_get_language_llms_txt_structure(): void {
		$ml = $this->make();
		$txt = $ml->get_language_llms_txt( 'en' );
		$this->assertIsString( $txt );
		$this->assertStringContainsString( '## Language: en', $txt );
	}

	// ── Add Language Fields ──────────────────────────────────────────────────

	public function test_add_language_fields(): void {
		$ml = $this->make();
		$fields = $ml->add_language_fields( [ 'title' => 'Test' ], 1 );
		$this->assertArrayHasKey( 'language', $fields );
	}

	// ── Disabled ─────────────────────────────────────────────────────────────

	public function test_disabled_does_not_register(): void {
		global $si_test_state;
		$si_test_state['actions'] = [];

		$ml = new Multilang( [ 'enable_multilang' => false ] );
		$this->assertArrayNotHasKey( 'wp_head', $si_test_state['actions'] ?? [] );
	}
}
