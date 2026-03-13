<?php

use PHPUnit\Framework\TestCase;
use Search_Intel\AI_Engine;

class AIEngineTest extends TestCase {

    private function make( array $overrides = [] ): AI_Engine {
        $defaults = [
            'enable_ai_engine' => true,
            'ai_provider'      => 'openai',
            'ai_model'         => '',
            'ai_endpoint'      => '',
            'ai_rate_limit'    => 30,
            'ai_token_budget'  => 0,
        ];
        return new AI_Engine( array_merge( $defaults, $overrides ) );
    }

    // ── Provider configuration ──────────────────────────────────────────

    public function test_get_providers_returns_all_three(): void {
        $ai = $this->make();
        $providers = $ai->get_providers();

        $this->assertArrayHasKey( 'openai', $providers );
        $this->assertArrayHasKey( 'anthropic', $providers );
        $this->assertArrayHasKey( 'ollama', $providers );
        $this->assertSame( 'OpenAI', $providers['openai'] );
    }

    public function test_get_active_provider_defaults_to_openai(): void {
        $ai = $this->make();
        $this->assertSame( 'openai', $ai->get_active_provider() );
    }

    public function test_get_active_provider_respects_setting(): void {
        $ai = $this->make( [ 'ai_provider' => 'anthropic' ] );
        $this->assertSame( 'anthropic', $ai->get_active_provider() );
    }

    public function test_get_active_provider_falls_back_on_invalid(): void {
        $ai = $this->make( [ 'ai_provider' => 'invalid_provider' ] );
        $this->assertSame( 'openai', $ai->get_active_provider() );
    }

    public function test_get_model_returns_default_for_openai(): void {
        $ai = $this->make();
        $this->assertSame( 'gpt-4o-mini', $ai->get_model() );
    }

    public function test_get_model_uses_custom_when_set(): void {
        $ai = $this->make( [ 'ai_model' => 'gpt-4o' ] );
        $this->assertSame( 'gpt-4o', $ai->get_model() );
    }

    public function test_get_endpoint_default_openai(): void {
        $ai = $this->make();
        $this->assertStringContainsString( 'openai.com', $ai->get_endpoint() );
    }

    public function test_get_endpoint_default_anthropic(): void {
        $ai = $this->make( [ 'ai_provider' => 'anthropic' ] );
        $this->assertStringContainsString( 'anthropic.com', $ai->get_endpoint() );
    }

    public function test_get_endpoint_ollama_custom(): void {
        $ai = $this->make( [ 'ai_provider' => 'ollama', 'ai_endpoint' => 'http://gpu-server:11434' ] );
        $this->assertSame( 'http://gpu-server:11434/api/chat', $ai->get_endpoint() );
    }

    // ── Encryption ──────────────────────────────────────────────────────

    public function test_encrypt_decrypt_roundtrip(): void {
        $ai  = $this->make();
        $key = 'sk-test-1234567890abcdef';

        $encrypted = $ai->encrypt( $key );
        $this->assertNotSame( $key, $encrypted );
        $this->assertNotEmpty( $encrypted );

        $decrypted = $ai->decrypt( $encrypted );
        $this->assertSame( $key, $decrypted );
    }

    public function test_encrypt_empty_string_returns_empty(): void {
        $ai = $this->make();
        $this->assertSame( '', $ai->encrypt( '' ) );
    }

    public function test_decrypt_empty_string_returns_empty(): void {
        $ai = $this->make();
        $this->assertSame( '', $ai->decrypt( '' ) );
    }

    public function test_decrypt_short_data_returns_empty(): void {
        $ai = $this->make();
        $this->assertSame( '', $ai->decrypt( base64_encode( 'short' ) ) );
    }

    // ── API key management ──────────────────────────────────────────────

    public function test_save_and_get_api_key(): void {
        global $si_test_state;
        $si_test_state['options'] = [];

        $ai = $this->make();
        $ai->save_api_key( 'openai', 'sk-test-key-123' );

        $retrieved = $ai->get_api_key( 'openai' );
        $this->assertSame( 'sk-test-key-123', $retrieved );
    }

    public function test_get_api_key_missing_provider_returns_empty(): void {
        global $si_test_state;
        $si_test_state['options'] = [];

        $ai = $this->make();
        $this->assertSame( '', $ai->get_api_key( 'anthropic' ) );
    }

    public function test_is_configured_false_without_key(): void {
        global $si_test_state;
        $si_test_state['options'] = [];

        $ai = $this->make();
        $this->assertFalse( $ai->is_configured() );
    }

    public function test_is_configured_true_for_ollama_without_key(): void {
        global $si_test_state;
        $si_test_state['options'] = [];

        $ai = $this->make( [ 'ai_provider' => 'ollama' ] );
        $this->assertTrue( $ai->is_configured() );
    }

    public function test_is_configured_true_with_key(): void {
        global $si_test_state;
        $si_test_state['options'] = [];

        $ai = $this->make();
        $ai->save_api_key( 'openai', 'sk-real-key' );
        $this->assertTrue( $ai->is_configured() );
    }

    // ── Rate limiting ───────────────────────────────────────────────────

    public function test_get_rate_limit_default(): void {
        $ai = $this->make();
        $this->assertSame( 30, $ai->get_rate_limit() );
    }

    public function test_get_rate_limit_custom(): void {
        $ai = $this->make( [ 'ai_rate_limit' => 10 ] );
        $this->assertSame( 10, $ai->get_rate_limit() );
    }

    public function test_is_rate_limited_false_initially(): void {
        global $si_test_state;
        $si_test_state['transients'] = [];

        $ai = $this->make();
        $this->assertFalse( $ai->is_rate_limited() );
    }

    public function test_is_rate_limited_true_when_count_exceeds(): void {
        global $si_test_state;
        $si_test_state['transients'] = [
            'search_intel_ai_rate_limit' => [ 'count' => 31 ],
        ];

        $ai = $this->make();
        $this->assertTrue( $ai->is_rate_limited() );
    }

    // ── Token budget ────────────────────────────────────────────────────

    public function test_get_token_budget_default_unlimited(): void {
        $ai = $this->make();
        $this->assertSame( 0, $ai->get_token_budget() );
    }

    public function test_is_budget_exceeded_false_when_unlimited(): void {
        global $si_test_state;
        $si_test_state['options'] = [];

        $ai = $this->make();
        $this->assertFalse( $ai->is_budget_exceeded() );
    }

    public function test_is_budget_exceeded_true_when_over(): void {
        global $si_test_state;
        $si_test_state['options'] = [
            'search_intel_ai_token_usage' => [
                'total_tokens' => 200000,
                'month_tokens' => 100001,
                'month'        => gmdate( 'Y-m' ),
            ],
        ];

        $ai = $this->make( [ 'ai_token_budget' => 100000 ] );
        $this->assertTrue( $ai->is_budget_exceeded() );
    }

    public function test_get_token_usage_resets_on_new_month(): void {
        global $si_test_state;
        $si_test_state['options'] = [
            'search_intel_ai_token_usage' => [
                'total_tokens' => 50000,
                'month_tokens' => 50000,
                'month'        => '2020-01',
            ],
        ];

        $ai    = $this->make();
        $usage = $ai->get_token_usage();
        $this->assertSame( 0, $usage['month_tokens'] );
        $this->assertSame( gmdate( 'Y-m' ), $usage['month'] );
    }

    // ── Chat — pre-flight checks ────────────────────────────────────────

    public function test_chat_fails_when_not_configured(): void {
        global $si_test_state;
        $si_test_state['options'] = [];

        $ai     = $this->make();
        $result = $ai->chat( 'system', 'user' );

        $this->assertFalse( $result['success'] );
        $this->assertStringContainsString( 'not configured', $result['error'] );
    }

    public function test_chat_fails_when_rate_limited(): void {
        global $si_test_state;
        $si_test_state['options']    = [];
        $si_test_state['transients'] = [
            'search_intel_ai_rate_limit' => [ 'count' => 999 ],
        ];

        $ai = $this->make( [ 'ai_provider' => 'ollama' ] ); // Ollama needs no key.
        $result = $ai->chat( 'system', 'user' );

        $this->assertFalse( $result['success'] );
        $this->assertStringContainsString( 'Rate limit', $result['error'] );
    }

    public function test_chat_fails_when_budget_exceeded(): void {
        global $si_test_state;
        $si_test_state['options'] = [
            'search_intel_ai_token_usage' => [
                'total_tokens' => 100001,
                'month_tokens' => 100001,
                'month'        => gmdate( 'Y-m' ),
            ],
        ];
        $si_test_state['transients'] = [];

        $ai = $this->make( [ 'ai_provider' => 'ollama', 'ai_token_budget' => 100000 ] );
        $result = $ai->chat( 'system', 'user' );

        $this->assertFalse( $result['success'] );
        $this->assertStringContainsString( 'budget exceeded', $result['error'] );
    }

    // ── Convenience helpers ─────────────────────────────────────────────

    public function test_generate_meta_description_requires_config(): void {
        global $si_test_state;
        $si_test_state['options'] = [];

        $ai     = $this->make();
        $result = $ai->generate_meta_description( 'Content here', 'seo', 'Test Title' );

        $this->assertFalse( $result['success'] );
    }

    public function test_generate_title_suggestions_requires_config(): void {
        global $si_test_state;
        $si_test_state['options'] = [];

        $ai     = $this->make();
        $result = $ai->generate_title_suggestions( 'Content here', 'seo', 'Title' );

        $this->assertFalse( $result['success'] );
    }

    public function test_generate_link_recommendations_empty_candidates(): void {
        $ai     = $this->make();
        $result = $ai->generate_link_recommendations( 1, [], 'content' );

        $this->assertFalse( $result['success'] );
        $this->assertStringContainsString( 'No candidate', $result['error'] );
    }

    public function test_generate_content_brief_requires_config(): void {
        global $si_test_state;
        $si_test_state['options'] = [];

        $ai     = $this->make();
        $result = $ai->generate_content_brief( 'WordPress SEO', [ 'Post 1' ], [] );

        $this->assertFalse( $result['success'] );
    }
}
