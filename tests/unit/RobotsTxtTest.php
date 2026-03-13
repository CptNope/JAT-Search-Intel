<?php
namespace Search_Intel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Search_Intel\Robots_Txt;

class RobotsTxtTest extends TestCase {

	private Robots_Txt $robots;

	protected function setUp(): void {
		global $si_test_state;
		$si_test_state = [
			'actions'        => [],
			'filters'        => [],
			'options'        => [],
			'post_meta'      => [],
			'user_meta'      => [],
			'posts'          => [],
			'transients'     => [],
			'current_user'   => 1,
			'active_plugins' => [],
		];

		$this->robots = new Robots_Txt( [ 'enable_robots_editor' => true ] );
	}

	// ── Constructor ──────────────────────────────────────────────────────

	public function test_constructor_registers_robots_txt_filter(): void {
		global $si_test_state;
		$hooks = array_column( $si_test_state['filters']['robots_txt'] ?? [], 'callback' );
		$found = false;
		foreach ( $hooks as $cb ) {
			if ( is_array( $cb ) && $cb[1] === 'filter_robots_txt' ) $found = true;
		}
		$this->assertTrue( $found );
	}

	public function test_constructor_disabled_does_not_hook(): void {
		global $si_test_state;
		$si_test_state['filters'] = [];
		new Robots_Txt( [ 'enable_robots_editor' => false ] );
		$hooks = $si_test_state['filters']['robots_txt'] ?? [];
		$this->assertEmpty( $hooks );
	}

	// ── Content CRUD ─────────────────────────────────────────────────────

	public function test_get_content_returns_empty_string_initially(): void {
		$this->assertEquals( '', $this->robots->get_content() );
	}

	public function test_save_content_persists(): void {
		$this->robots->save_content( "User-agent: *\nDisallow: /wp-admin/" );
		$this->assertStringContainsString( 'User-agent', $this->robots->get_content() );
	}

	// ── filter_robots_txt ────────────────────────────────────────────────

	public function test_filter_returns_default_when_no_custom(): void {
		$result = $this->robots->filter_robots_txt( 'default content', true );
		$this->assertEquals( 'default content', $result );
	}

	public function test_filter_returns_custom_when_set(): void {
		$this->robots->save_content( "User-agent: *\nDisallow: /private/" );
		$result = $this->robots->filter_robots_txt( 'default content', true );
		$this->assertStringContainsString( 'Disallow: /private/', $result );
	}

	// ── Validation ───────────────────────────────────────────────────────

	public function test_validate_valid_robots_txt(): void {
		$content = "User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\nSitemap: https://example.com/sitemap.xml";
		$result  = $this->robots->validate( $content );

		$this->assertTrue( $result['valid'] );
		$this->assertEmpty( $result['errors'] );
	}

	public function test_validate_missing_user_agent(): void {
		$content = "Disallow: /wp-admin/";
		$result  = $this->robots->validate( $content );

		$this->assertFalse( $result['valid'] );
		$this->assertNotEmpty( $result['errors'] );
	}

	public function test_validate_empty_user_agent_value(): void {
		$content = "User-agent: \nDisallow: /";
		$result  = $this->robots->validate( $content );

		$this->assertFalse( $result['valid'] );
	}

	public function test_validate_missing_colon(): void {
		$content = "User-agent *\nDisallow /";
		$result  = $this->robots->validate( $content );

		$this->assertFalse( $result['valid'] );
	}

	public function test_validate_invalid_sitemap_url(): void {
		$content = "User-agent: *\nSitemap: not-a-url";
		$result  = $this->robots->validate( $content );

		$this->assertFalse( $result['valid'] );
	}

	public function test_validate_unknown_directive_warning(): void {
		$content = "User-agent: *\nFoobar: test";
		$result  = $this->robots->validate( $content );

		$this->assertNotEmpty( $result['warnings'] );
	}

	public function test_validate_wildcard_warning(): void {
		$content = "User-agent: *\nDisallow: /search*";
		$result  = $this->robots->validate( $content );

		$this->assertNotEmpty( $result['warnings'] );
	}

	public function test_validate_empty_content(): void {
		$result = $this->robots->validate( '' );
		$this->assertTrue( $result['valid'] );
		$this->assertEmpty( $result['errors'] );
	}

	public function test_validate_comments_only(): void {
		$content = "# This is a comment\n# Another comment";
		$result  = $this->robots->validate( $content );

		$this->assertTrue( $result['valid'] );
	}

	public function test_validate_disallow_before_user_agent(): void {
		$content = "Disallow: /\nUser-agent: *";
		$result  = $this->robots->validate( $content );

		$this->assertFalse( $result['valid'] );
	}
}
