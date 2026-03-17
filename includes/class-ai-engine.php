<?php
/**
 * AI Engine — LLM abstraction layer.
 *
 * Provides a unified interface for generating text via OpenAI, Anthropic, or
 * Ollama (local). Handles encrypted API key storage, per-provider request
 * formatting, rate limiting, and token budget controls.
 *
 * @package Search_Intel
 * @since   3.2.0
 */

namespace Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_Engine {

    /** @var array Plugin settings. */
    private $settings;

    /** @var string Transient key for rate-limit tracking. */
    private const RATE_LIMIT_KEY = 'search_intel_ai_rate_limit';

    /** @var string Option key for lifetime token usage. */
    private const TOKEN_USAGE_KEY = 'search_intel_ai_token_usage';

    /** @var string Option key for encrypted API keys. */
    private const API_KEYS_OPTION = 'search_intel_ai_api_keys';

    /** @var array Supported providers and their default endpoints. */
    private const PROVIDERS = [
        'openai'    => [
            'label'    => 'OpenAI',
            'endpoint' => 'https://api.openai.com/v1/chat/completions',
            'model'    => 'gpt-4o-mini',
        ],
        'anthropic' => [
            'label'    => 'Anthropic',
            'endpoint' => 'https://api.anthropic.com/v1/messages',
            'model'    => 'claude-3-5-haiku-20241022',
        ],
        'ollama'    => [
            'label'    => 'Ollama (Local)',
            'endpoint' => 'http://localhost:11434/api/chat',
            'model'    => 'llama3.2',
        ],
    ];

    /**
     * Constructor.
     *
     * @param array $settings Plugin settings.
     */
    public function __construct( array $settings ) {
        $this->settings = $settings;
    }

    /*----------------------------------------------------------------------
     * Provider configuration
     *--------------------------------------------------------------------*/

    /**
     * Get list of supported providers.
     *
     * @return array Provider ID => label.
     */
    public function get_providers(): array {
        $list = [];
        foreach ( self::PROVIDERS as $id => $meta ) {
            $list[ $id ] = $meta['label'];
        }
        return $list;
    }

    /**
     * Get the currently active provider ID.
     *
     * @return string 'openai', 'anthropic', or 'ollama'.
     */
    public function get_active_provider(): string {
        $provider = $this->settings['ai_provider'] ?? 'openai';
        return array_key_exists( $provider, self::PROVIDERS ) ? $provider : 'openai';
    }

    /**
     * Get the model to use for the active provider.
     *
     * @return string Model identifier.
     */
    public function get_model(): string {
        $provider = $this->get_active_provider();
        $custom   = $this->settings['ai_model'] ?? '';
        return $custom !== '' ? $custom : self::PROVIDERS[ $provider ]['model'];
    }

    /**
     * Get the endpoint URL for the active provider.
     *
     * @return string Endpoint URL.
     */
    public function get_endpoint(): string {
        $provider = $this->get_active_provider();
        $custom   = $this->settings['ai_endpoint'] ?? '';
        if ( $custom !== '' && 'ollama' === $provider ) {
            // Only append /api/chat if the user gave a base URL without it.
            if ( str_contains( $custom, '/api/' ) ) {
                return rtrim( $custom, '/' );
            }
            return rtrim( $custom, '/' ) . '/api/chat';
        }
        return $custom !== '' ? $custom : self::PROVIDERS[ $provider ]['endpoint'];
    }

    /*----------------------------------------------------------------------
     * API key management (encrypted storage)
     *--------------------------------------------------------------------*/

    /**
     * Get the encryption key derived from WordPress salts.
     *
     * @return string 32-byte key.
     */
    private function get_encryption_key(): string {
        $salt = defined( 'LOGGED_IN_SALT' ) ? LOGGED_IN_SALT : 'search-intel-fallback-salt';
        return substr( hash( 'sha256', $salt . 'search_intel_ai', true ), 0, 32 );
    }

    /**
     * Encrypt a plaintext value.
     *
     * @param string $plaintext Value to encrypt.
     * @return string Base64-encoded ciphertext with IV prepended.
     */
    public function encrypt( string $plaintext ): string {
        if ( $plaintext === '' ) {
            return '';
        }
        if ( ! function_exists( 'openssl_encrypt' ) ) {
            return base64_encode( $plaintext ); // Fallback — not truly secure.
        }
        $key    = $this->get_encryption_key();
        $iv     = openssl_random_pseudo_bytes( 16 );
        $cipher = openssl_encrypt( $plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
        return base64_encode( $iv . $cipher );
    }

    /**
     * Decrypt a ciphertext value.
     *
     * @param string $ciphertext Base64-encoded ciphertext with IV.
     * @return string Decrypted plaintext.
     */
    public function decrypt( string $ciphertext ): string {
        if ( $ciphertext === '' ) {
            return '';
        }
        if ( ! function_exists( 'openssl_decrypt' ) ) {
            return base64_decode( $ciphertext ); // Fallback.
        }
        $key  = $this->get_encryption_key();
        $data = base64_decode( $ciphertext );
        if ( strlen( $data ) < 17 ) {
            return '';
        }
        $iv     = substr( $data, 0, 16 );
        $cipher = substr( $data, 16 );
        $result = openssl_decrypt( $cipher, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
        return $result !== false ? $result : '';
    }

    /**
     * Save an API key (encrypted) for a provider.
     *
     * @param string $provider Provider ID.
     * @param string $api_key  Plaintext API key.
     */
    public function save_api_key( string $provider, string $api_key ): void {
        $keys = get_option( self::API_KEYS_OPTION, [] );
        $keys[ $provider ] = $this->encrypt( $api_key );
        update_option( self::API_KEYS_OPTION, $keys, false );
    }

    /**
     * Get the decrypted API key for a provider.
     *
     * @param string $provider Provider ID (default: active provider).
     * @return string Decrypted API key.
     */
    public function get_api_key( string $provider = '' ): string {
        if ( $provider === '' ) {
            $provider = $this->get_active_provider();
        }
        $keys = get_option( self::API_KEYS_OPTION, [] );
        if ( ! isset( $keys[ $provider ] ) ) {
            return '';
        }
        return $this->decrypt( $keys[ $provider ] );
    }

    /**
     * Check if the active provider is configured (has an API key or is local).
     *
     * @return bool
     */
    public function is_configured(): bool {
        $provider = $this->get_active_provider();
        if ( 'ollama' === $provider ) {
            return true; // No key needed for local Ollama.
        }
        return $this->get_api_key( $provider ) !== '';
    }

    /*----------------------------------------------------------------------
     * Rate limiting & token budget
     *--------------------------------------------------------------------*/

    /**
     * Get the maximum requests per minute.
     *
     * @return int
     */
    public function get_rate_limit(): int {
        return max( 1, (int) ( $this->settings['ai_rate_limit'] ?? 30 ) );
    }

    /**
     * Get the monthly token budget (0 = unlimited).
     *
     * @return int
     */
    public function get_token_budget(): int {
        return max( 0, (int) ( $this->settings['ai_token_budget'] ?? 0 ) );
    }

    /**
     * Check if we are currently rate-limited.
     *
     * @return bool True if requests should be throttled.
     */
    public function is_rate_limited(): bool {
        // Local models have no external rate limits.
        if ( 'ollama' === $this->get_active_provider() ) {
            return false;
        }
        $data = get_transient( self::RATE_LIMIT_KEY );
        if ( false === $data ) {
            return false;
        }
        return ( $data['count'] ?? 0 ) >= $this->get_rate_limit();
    }

    /**
     * Record a request for rate-limiting purposes.
     */
    private function record_request(): void {
        // Local models have no external rate limits.
        if ( 'ollama' === $this->get_active_provider() ) {
            return;
        }
        $data = get_transient( self::RATE_LIMIT_KEY );
        if ( false === $data ) {
            // Start a new 60-second window.
            set_transient( self::RATE_LIMIT_KEY, [ 'count' => 1 ], 60 );
            return;
        }
        // Increment count without renewing the TTL.
        $data['count'] = ( $data['count'] ?? 0 ) + 1;
        $GLOBALS['wpdb']->update(
            $GLOBALS['wpdb']->options,
            [ 'option_value' => maybe_serialize( $data ) ],
            [ 'option_name' => '_transient_' . self::RATE_LIMIT_KEY ]
        );
        wp_cache_set( self::RATE_LIMIT_KEY, $data, 'transient' );
    }

    /**
     * Get lifetime token usage stats.
     *
     * @return array { total_tokens: int, month_tokens: int, month: string }
     */
    public function get_token_usage(): array {
        $current_month = gmdate( 'Y-m' );
        $data = get_option( self::TOKEN_USAGE_KEY, [
            'total_tokens' => 0,
            'month_tokens' => 0,
            'month'        => $current_month,
        ] );
        // Reset month counter if new month.
        if ( ( $data['month'] ?? '' ) !== $current_month ) {
            $data['month_tokens'] = 0;
            $data['month']        = $current_month;
            update_option( self::TOKEN_USAGE_KEY, $data, false );
        }
        return $data;
    }

    /**
     * Check if the monthly token budget has been exceeded.
     *
     * @return bool True if budget exceeded.
     */
    public function is_budget_exceeded(): bool {
        $budget = $this->get_token_budget();
        if ( 0 === $budget ) {
            return false; // Unlimited.
        }
        $usage = $this->get_token_usage();
        return $usage['month_tokens'] >= $budget;
    }

    /**
     * Record token usage after a request.
     *
     * @param int $tokens Number of tokens consumed.
     */
    private function record_token_usage( int $tokens ): void {
        $data = $this->get_token_usage();
        $data['total_tokens'] += $tokens;
        $data['month_tokens'] += $tokens;
        update_option( self::TOKEN_USAGE_KEY, $data, false );
    }

    /*----------------------------------------------------------------------
     * LLM request execution
     *--------------------------------------------------------------------*/

    /**
     * Send a chat completion request to the active provider.
     *
     * @param string $system_prompt System message describing the AI's role.
     * @param string $user_prompt   User message / content to process.
     * @param array  $options       Optional overrides: model, temperature, max_tokens.
     * @return array { success: bool, text: string, tokens_used: int, error: string }
     */
    public function chat( string $system_prompt, string $user_prompt, array $options = [] ): array {
        // Pre-flight checks.
        if ( ! $this->is_configured() ) {
            return $this->error_response( 'AI provider is not configured. Please add an API key in Settings.' );
        }
        if ( $this->is_rate_limited() ) {
            return $this->error_response( 'Rate limit reached. Please wait a moment and try again.' );
        }
        if ( $this->is_budget_exceeded() ) {
            return $this->error_response( 'Monthly token budget exceeded.' );
        }

        $this->record_request();

        $provider = $this->get_active_provider();
        switch ( $provider ) {
            case 'anthropic':
                return $this->request_anthropic( $system_prompt, $user_prompt, $options );
            case 'ollama':
                return $this->request_ollama( $system_prompt, $user_prompt, $options );
            default:
                return $this->request_openai( $system_prompt, $user_prompt, $options );
        }
    }

    /**
     * Send a request to OpenAI's chat completions API.
     *
     * @param string $system System prompt.
     * @param string $user   User prompt.
     * @param array  $opts   Options.
     * @return array
     */
    private function request_openai( string $system, string $user, array $opts ): array {
        $body = [
            'model'       => $opts['model'] ?? $this->get_model(),
            'messages'    => [
                [ 'role' => 'system', 'content' => $system ],
                [ 'role' => 'user', 'content' => $user ],
            ],
            'temperature' => (float) ( $opts['temperature'] ?? 0.3 ),
            'max_tokens'  => (int) ( $opts['max_tokens'] ?? 500 ),
        ];

        $response = wp_remote_post( $this->get_endpoint(), [
            'timeout' => 30,
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->get_api_key(),
            ],
            'body' => (string) wp_json_encode( $body ),
        ] );

        if ( is_wp_error( $response ) ) {
            return $this->error_response( $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $json = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 || ! isset( $json['choices'][0]['message']['content'] ) ) {
            $msg = $json['error']['message'] ?? "OpenAI API returned HTTP {$code}";
            return $this->error_response( $msg );
        }

        $text   = trim( $json['choices'][0]['message']['content'] );
        $tokens = (int) ( $json['usage']['total_tokens'] ?? 0 );
        $this->record_token_usage( $tokens );

        return [
            'success'     => true,
            'text'        => $text,
            'tokens_used' => $tokens,
            'error'       => '',
        ];
    }

    /**
     * Send a request to Anthropic's messages API.
     *
     * @param string $system System prompt.
     * @param string $user   User prompt.
     * @param array  $opts   Options.
     * @return array
     */
    private function request_anthropic( string $system, string $user, array $opts ): array {
        $body = [
            'model'      => $opts['model'] ?? $this->get_model(),
            'max_tokens' => (int) ( $opts['max_tokens'] ?? 500 ),
            'system'     => $system,
            'messages'   => [
                [ 'role' => 'user', 'content' => $user ],
            ],
        ];

        $response = wp_remote_post( $this->get_endpoint(), [
            'timeout' => 30,
            'headers' => [
                'Content-Type'      => 'application/json',
                'x-api-key'         => $this->get_api_key(),
                'anthropic-version' => '2023-06-01',
            ],
            'body' => (string) wp_json_encode( $body ),
        ] );

        if ( is_wp_error( $response ) ) {
            return $this->error_response( $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $json = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 || ! isset( $json['content'][0]['text'] ) ) {
            $msg = $json['error']['message'] ?? "Anthropic API returned HTTP {$code}";
            return $this->error_response( $msg );
        }

        $text   = trim( $json['content'][0]['text'] );
        $tokens = ( $json['usage']['input_tokens'] ?? 0 ) + ( $json['usage']['output_tokens'] ?? 0 );
        $this->record_token_usage( (int) $tokens );

        return [
            'success'     => true,
            'text'        => $text,
            'tokens_used' => (int) $tokens,
            'error'       => '',
        ];
    }

    /**
     * Send a request to a local Ollama instance.
     *
     * @param string $system System prompt.
     * @param string $user   User prompt.
     * @param array  $opts   Options.
     * @return array
     */
    private function request_ollama( string $system, string $user, array $opts ): array {
        $body = [
            'model'    => $opts['model'] ?? $this->get_model(),
            'messages' => [
                [ 'role' => 'system', 'content' => $system ],
                [ 'role' => 'user', 'content' => $user ],
            ],
            'stream'   => false,
            'options'  => [
                'temperature' => (float) ( $opts['temperature'] ?? 0.3 ),
                'num_predict' => (int) ( $opts['max_tokens'] ?? 500 ),
            ],
        ];

        $response = wp_remote_post( $this->get_endpoint(), [
            'timeout' => 120, // Local models can be slow.
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => (string) wp_json_encode( $body ),
        ] );

        if ( is_wp_error( $response ) ) {
            return $this->error_response( $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $json = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 || ! isset( $json['message']['content'] ) ) {
            $msg = $json['error'] ?? "Ollama returned HTTP {$code}";
            return $this->error_response( is_array( $msg ) ? wp_json_encode( $msg ) : $msg );
        }

        $text   = trim( $json['message']['content'] );
        $tokens = (int) ( $json['eval_count'] ?? 0 ) + (int) ( $json['prompt_eval_count'] ?? 0 );
        $this->record_token_usage( $tokens );

        return [
            'success'     => true,
            'text'        => $text,
            'tokens_used' => $tokens,
            'error'       => '',
        ];
    }

    /**
     * Build a standardized error response.
     *
     * @param string $message Error message.
     * @return array
     */
    private function error_response( string $message ): array {
        return [
            'success'     => false,
            'text'        => '',
            'tokens_used' => 0,
            'error'       => $message,
        ];
    }

    /*----------------------------------------------------------------------
     * Convenience helpers for content generation
     *--------------------------------------------------------------------*/

    /**
     * Generate a meta description for a post.
     *
     * @param string $content  Post content (plain text).
     * @param string $keyword  Focus keyword.
     * @param string $title    Post title.
     * @return array AI response.
     */
    public function generate_meta_description( string $content, string $keyword, string $title ): array {
        $system = 'You are an SEO expert. Write a compelling meta description for a web page. '
                . 'The description must be 120-155 characters, include the focus keyword naturally, '
                . 'and entice users to click. Return ONLY the meta description text, no quotes or explanation.';

        $user = "Title: {$title}\nFocus keyword: {$keyword}\n\nContent excerpt:\n"
              . mb_substr( wp_strip_all_tags( $content ), 0, 1500 );

        return $this->chat( $system, $user, [ 'max_tokens' => 100, 'temperature' => 0.5 ] );
    }

    /**
     * Generate SEO title suggestions for a post.
     *
     * @param string $content Post content (plain text).
     * @param string $keyword Focus keyword.
     * @param string $title   Current post title.
     * @return array AI response (text contains numbered suggestions).
     */
    public function generate_title_suggestions( string $content, string $keyword, string $title ): array {
        $system = 'You are an SEO expert. Suggest 3 alternative SEO title tags for a web page. '
                . 'Each title must be 50-60 characters, include the focus keyword near the beginning, '
                . 'and be compelling. Return a numbered list (1. 2. 3.) with no extra commentary.';

        $user = "Current title: {$title}\nFocus keyword: {$keyword}\n\nContent excerpt:\n"
              . mb_substr( wp_strip_all_tags( $content ), 0, 1500 );

        return $this->chat( $system, $user, [ 'max_tokens' => 250, 'temperature' => 0.7 ] );
    }

    /**
     * Generate internal link recommendations based on Citation Graph data.
     *
     * @param int   $post_id       Current post ID.
     * @param array $candidate_posts Array of [ id => int, title => string, url => string ].
     * @param string $content       Current post content excerpt.
     * @return array AI response.
     */
    public function generate_link_recommendations( int $post_id, array $candidate_posts, string $content ): array {
        if ( empty( $candidate_posts ) ) {
            return $this->error_response( 'No candidate posts available for linking.' );
        }

        $system = 'You are an internal linking strategist. Given a post and a list of candidate posts, '
                . 'recommend the top 5 most relevant internal links. For each recommendation, provide: '
                . 'the post ID, a suggested anchor text phrase from the current content, and a brief reason. '
                . 'Format as a numbered list: "1. [ID] — anchor: \"phrase\" — reason: ..."';

        $candidates = '';
        foreach ( array_slice( $candidate_posts, 0, 30 ) as $p ) {
            $candidates .= "- ID {$p['id']}: {$p['title']} ({$p['url']})\n";
        }

        $user = "Current post (ID {$post_id}) content excerpt:\n"
              . mb_substr( wp_strip_all_tags( $content ), 0, 1500 )
              . "\n\nCandidate posts:\n{$candidates}";

        return $this->chat( $system, $user, [ 'max_tokens' => 500, 'temperature' => 0.3 ] );
    }

    /**
     * Generate a content brief for a gap topic from Authority Map data.
     *
     * @param string $topic            Topic / keyword to cover.
     * @param array  $existing_titles  Titles of existing posts in this topic area.
     * @param array  $gap_data         Gap analysis data from Authority Map.
     * @return array AI response.
     */
    public function generate_content_brief( string $topic, array $existing_titles, array $gap_data ): array {
        $system = 'You are a content strategist specializing in topical authority. '
                . 'Generate a content brief for a new article that fills a topic gap. Include: '
                . '1) Suggested title (50-60 chars), 2) Target word count, 3) Key sections/outline (H2s), '
                . '4) Focus keyword, 5) Secondary keywords, 6) Internal linking targets from existing content. '
                . 'Be specific and actionable.';

        $existing = implode( "\n", array_map( fn( $t ) => "- {$t}", array_slice( $existing_titles, 0, 20 ) ) );
        $gaps     = ! empty( $gap_data ) ? wp_json_encode( $gap_data ) : 'No specific gap data available.';

        $user = "Topic to cover: {$topic}\n\nExisting content in this area:\n{$existing}\n\nGap analysis:\n{$gaps}";

        return $this->chat( $system, $user, [ 'max_tokens' => 800, 'temperature' => 0.5 ] );
    }
}
