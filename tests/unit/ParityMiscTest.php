<?php
namespace JAT_Search_Intel\Tests\Unit;

use JAT_Search_Intel\Admin;
use JAT_Search_Intel\AI_Engine;
use JAT_Search_Intel\Robots_Txt;

class ParityMiscTest extends ParityTestCase {

	/**
	 * @dataProvider verification_codes
	 */
	public function test_sanitize_verification_code( string $raw, string $expected ): void {
		$this->assertSame( $expected, Admin::sanitize_verification_code( $raw ) );
	}

	public static function verification_codes(): array {
		return [
			'plain'        => [ 'abc123_-XYZ', 'abc123_-XYZ' ],
			'full tag'     => [ '<meta name="google-site-verification" content="AbC-123=" />', 'AbC-123=' ],
			'single quote' => [ "<meta name='msvalidate.01' content='DEADBEEF' />", 'DEADBEEF' ],
			'junk'         => [ ' "><script>x</script> ', 'scriptx/script' ],
			'empty'        => [ '', '' ],
		];
	}

	public function test_openai_reasoning_models_use_completion_tokens(): void {
		$body = AI_Engine::openai_body( 'openai', 'gpt-5-mini', 'sys', 'user', [ 'max_tokens' => 300 ] );
		$this->assertSame( 2000, $body['max_completion_tokens'] );
		$this->assertArrayNotHasKey( 'temperature', $body );
		$this->assertArrayNotHasKey( 'max_tokens', $body );

		$body = AI_Engine::openai_body( 'openai', 'o4-mini', 'sys', 'user', [ 'max_tokens' => 1000 ] );
		$this->assertSame( 4000, $body['max_completion_tokens'] );
	}

	public function test_other_models_use_max_tokens_and_temperature(): void {
		$body = AI_Engine::openai_body( 'mistral', 'mistral-small-latest', 'sys', 'user', [ 'max_tokens' => 300, 'temperature' => 0.7 ] );
		$this->assertSame( 300, $body['max_tokens'] );
		$this->assertSame( 0.7, $body['temperature'] );
		$this->assertSame( 'system', $body['messages'][0]['role'] );

		// Only OpenAI's own reasoning models get the special handling.
		$body = AI_Engine::openai_body( 'openrouter', 'gpt-5-mini', 'sys', 'user', [] );
		$this->assertSame( 500, $body['max_tokens'] );
	}

	public function test_providers(): void {
		$this->assertTrue( AI_Engine::is_provider( 'gemini' ) );
		$this->assertFalse( AI_Engine::is_provider( 'skynet' ) );
		$details = AI_Engine::provider_details();
		$this->assertFalse( $details['ollama']['needs_key'] );
		$this->assertTrue( $details['anthropic']['needs_key'] );
		foreach ( $details as $id => $provider ) {
			$this->assertNotSame( '', $provider['label'], $id );
		}
	}

	public function test_robots_history_keeps_previous_versions(): void {
		$robots = new Robots_Txt( [] );
		$this->assertTrue( $robots->save_content( "User-agent: *\nDisallow: /a" ) );
		$this->assertTrue( $robots->save_content( "User-agent: *\nDisallow: /b" ) );
		$this->assertFalse( $robots->save_content( "User-agent: *\nDisallow: /b" ), 'Saving the same text adds no version.' );

		$history = Robots_Txt::get_history();
		$this->assertCount( 2, $history );
		$this->assertSame( "User-agent: *\nDisallow: /a", $history[0]['content'] );
		$this->assertSame( '', $history[1]['content'] );
	}

	public function test_robots_history_is_capped(): void {
		$robots = new Robots_Txt( [] );
		for ( $i = 0; $i < Robots_Txt::HISTORY_SIZE + 4; $i++ ) {
			$robots->save_content( 'Disallow: /' . $i );
		}
		$this->assertCount( Robots_Txt::HISTORY_SIZE, Robots_Txt::get_history() );
	}
}
