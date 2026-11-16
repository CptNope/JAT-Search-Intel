<?php
namespace JAT_Search_Intel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use JAT_Search_Intel\Redirect_Manager;

class ChainTestRedirectManager extends Redirect_Manager {
	/** @var array<int,array<string,mixed>> */
	public $rules = [];
	protected function all_rules(): array {
		return $this->rules;
	}
}

class RedirectChainsTest extends TestCase {

	private function rule( int $id, string $source, string $target, string $type = 'exact', int $code = 301, int $enabled = 1 ): array {
		return [ 'id' => $id, 'source_url' => $source, 'target_url' => $target, 'status_code' => $code, 'match_type' => $type, 'enabled' => $enabled ];
	}

	public function test_finds_a_chain(): void {
		$chains = Redirect_Manager::find_chains( [
			$this->rule( 1, '/a', '/b' ),
			$this->rule( 2, '/b/', '/c' ),
		] );
		$this->assertCount( 1, $chains );
		$this->assertSame( [ '/a', '/b', '/c' ], $chains[0]['paths'] );
		$this->assertFalse( $chains[0]['loop'] );
	}

	public function test_single_hop_is_not_a_chain(): void {
		$this->assertSame( [], Redirect_Manager::find_chains( [ $this->rule( 1, '/a', '/b' ), $this->rule( 2, '/x', 'https://other.example/b' ) ] ) );
	}

	public function test_finds_a_loop_once(): void {
		$chains = Redirect_Manager::find_chains( [
			$this->rule( 1, '/a', '/b' ),
			$this->rule( 2, '/b', '/A' ),
		] );
		$this->assertCount( 1, $chains );
		$this->assertTrue( $chains[0]['loop'] );
	}

	public function test_disabled_and_gone_rules_are_ignored(): void {
		$this->assertSame( [], Redirect_Manager::find_chains( [
			$this->rule( 1, '/a', '/b' ),
			$this->rule( 2, '/b', '/c', 'exact', 301, 0 ),
			$this->rule( 3, '/c', '', 'exact', 410 ),
		] ) );
	}

	public function test_save_refuses_a_rule_that_closes_a_loop(): void {
		$manager        = new ChainTestRedirectManager( [] );
		$manager->rules = [ $this->rule( 1, '/a', '/b' ) ];
		$this->assertFalse( $manager->save_redirect( [ 'source_url' => '/b', 'target_url' => '/a', 'match_type' => 'exact', 'enabled' => 1 ] ) );
		$this->assertNotFalse( $manager->save_redirect( [ 'source_url' => '/b', 'target_url' => '/c', 'match_type' => 'exact', 'enabled' => 1 ] ) );
	}

	public function test_editing_a_rule_does_not_loop_with_its_old_self(): void {
		$manager        = new ChainTestRedirectManager( [] );
		$manager->rules = [ $this->rule( 1, '/a', '/b' ) ];
		$this->assertNotFalse( $manager->save_redirect( [ 'id' => 1, 'source_url' => '/b', 'target_url' => '/a', 'match_type' => 'exact', 'enabled' => 1 ] ) );
	}

	public function test_clean_url_field_keeps_encoding_and_regex(): void {
		$this->assertSame( '/caf%C3%A9/', Redirect_Manager::clean_url_field( "  /caf%C3%A9/\n" ) );
		$this->assertSame( '^/blog/(\d+)/(.*)$', Redirect_Manager::clean_url_field( '^/blog/(\d+)/(.*)$' ) );
		$this->assertSame( '/x', Redirect_Manager::clean_url_field( '<b>/x</b>' ) );
	}

	public function test_apache_rules(): void {
		$out = Redirect_Manager::server_rules( [
			$this->rule( 1, '/old-page/', '/new-page/' ),
			$this->rule( 2, '/shop', 'https://example.com/store/', 'prefix', 302 ),
			$this->rule( 3, '^/blog/(\d+)$', '/posts/$1', 'regex' ),
			$this->rule( 4, '/gone', '', 'exact', 410 ),
			$this->rule( 5, '/off', '/x', 'exact', 301, 0 ),
		], 'apache' );

		$this->assertStringContainsString( 'RewriteCond %{REQUEST_URI} ^/old\-page/?$ [NC]', $out );
		$this->assertStringContainsString( 'RewriteRule ^ /new-page/ [R=301,L,NE]', $out );
		$this->assertStringContainsString( 'RewriteCond %{REQUEST_URI} ^/shop [NC]', $out );
		$this->assertStringContainsString( 'RewriteRule ^ https://example.com/store/ [R=302,L,NE]', $out );
		$this->assertStringContainsString( 'RewriteRule ^ /posts/%1 [R=301,L,NE]', $out );
		$this->assertStringContainsString( 'RewriteRule ^ - [G,L]', $out );
		$this->assertStringNotContainsString( '/off', $out );
		$this->assertStringContainsString( '# END JAT Search Intel redirects', $out );
	}

	public function test_nginx_rules(): void {
		$out = Redirect_Manager::server_rules( [
			$this->rule( 1, '/old-page', '/new-page/' ),
			$this->rule( 3, '^/blog/(\d+)$', '/posts/$1', 'regex', 307 ),
			$this->rule( 4, '/gone', '', 'exact', 410 ),
		], 'nginx' );

		$this->assertStringContainsString( 'location ~* "^/old\-page/?$" { return 301 "/new-page/"; }', $out );
		$this->assertStringContainsString( 'location ~* "^/blog/(\d+)$" { return 307 "/posts/$1"; }', $out );
		$this->assertStringContainsString( 'location ~* "^/gone/?$" { return 410; }', $out );
	}
}
