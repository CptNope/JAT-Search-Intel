<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- View template included inside a class method; variables are method-scoped.
$settings  = get_option( 'jat_search_intel_settings', [] );
$settings  = wp_parse_args( $settings, \JAT_Search_Intel\Core::get_default_settings() );
$ai_engine = \JAT_Search_Intel\Core::get_instance()->ai_engine;
$providers = $ai_engine->get_providers();
$usage     = $ai_engine->get_token_usage();
?>
<div class="wrap si-settings">
    <h1><span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'JAT Search Intel — AI Engine', 'jat-search-intel' ); ?></h1>
    <p class="si-settings-intro"><?php esc_html_e( 'Configure your AI provider for content generation, meta descriptions, title suggestions, and bulk AI tools.', 'jat-search-intel' ); ?></p>

    <form method="post" action="options.php" id="si-ai-settings-form">
        <?php settings_fields( 'jat_search_intel_settings_group' ); ?>
        <input type="hidden" name="jat_search_intel_settings[_si_page]" value="ai" />

        <?php // Settings from other pages are preserved by the page-scoped sanitizer. ?>

        <!-- Enable AI Engine -->
        <div class="si-settings-card">
            <h2><?php esc_html_e( 'Enable AI Engine', 'jat-search-intel' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'AI Engine', 'jat-search-intel' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="jat_search_intel_settings[enable_ai_engine]" value="1" <?php checked( $settings['enable_ai_engine'] ); ?> />
                            <?php esc_html_e( 'Enable AI-powered content generation (meta descriptions, title suggestions, AI summaries, bulk generation)', 'jat-search-intel' ); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Provider Selection -->
        <div class="si-settings-card">
            <h2><?php esc_html_e( 'AI Provider', 'jat-search-intel' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="si_ai_provider"><?php esc_html_e( 'Active Provider', 'jat-search-intel' ); ?></label></th>
                    <td>
                        <select id="si_ai_provider" name="jat_search_intel_settings[ai_provider]">
                            <?php foreach ( $providers as $provider_id => $label ) : ?>
                                <option value="<?php echo esc_attr( $provider_id ); ?>" <?php selected( $settings['ai_provider'], $provider_id ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e( 'Select which AI provider to use for content generation.', 'jat-search-intel' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="si_ai_model"><?php esc_html_e( 'Model Override', 'jat-search-intel' ); ?></label></th>
                    <td>
                        <input type="text" id="si_ai_model" name="jat_search_intel_settings[ai_model]" value="<?php echo esc_attr( $settings['ai_model'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Leave blank for default', 'jat-search-intel' ); ?>" />
                        <p class="description">
                            <?php esc_html_e( 'Defaults:', 'jat-search-intel' ); ?>
                            <?php
                            $si_defaults = [];
                            foreach ( \JAT_Search_Intel\AI_Engine::provider_details() as $si_details ) {
                                if ( '' !== $si_details['model'] ) {
                                    $si_defaults[] = '<strong>' . esc_html( $si_details['label'] ) . ':</strong> <code>' . esc_html( $si_details['model'] ) . '</code>';
                                }
                            }
                            echo wp_kses_post( implode( ' &nbsp;|&nbsp; ', $si_defaults ) );
                            ?>
                        </p>
                    </td>
                </tr>
                <tr id="si-ai-endpoint-row">
                    <th><label for="si_ai_endpoint"><?php esc_html_e( 'Custom Endpoint', 'jat-search-intel' ); ?></label></th>
                    <td>
                        <input type="url" id="si_ai_endpoint" name="jat_search_intel_settings[ai_endpoint]" value="<?php echo esc_url( $settings['ai_endpoint'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Leave blank for default', 'jat-search-intel' ); ?>" />
                        <p class="description"><?php esc_html_e( 'For Ollama: your local server URL (e.g. http://localhost:11434). For "Other OpenAI-compatible API": its base URL (e.g. https://api.groq.com/openai/v1), and set the model above. Otherwise leave blank.', 'jat-search-intel' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- API Keys -->
        <div class="si-settings-card">
            <h2><?php esc_html_e( 'API Keys', 'jat-search-intel' ); ?></h2>
            <p class="description" style="margin-bottom: 12px;"><?php esc_html_e( 'API keys are encrypted before storage. They are never exposed in page source or exports.', 'jat-search-intel' ); ?></p>
            <table class="form-table">
                <?php foreach ( \JAT_Search_Intel\AI_Engine::provider_details() as $si_provider => $si_details ) : ?>
                    <?php if ( ! $si_details['needs_key'] ) : ?>
                        <tr class="si-api-key-row" data-provider="<?php echo esc_attr( $si_provider ); ?>">
                            <th><?php echo esc_html( $si_details['label'] ); ?></th>
                            <td>
                                <p class="description">
                                    <?php
                                    echo 'ollama' === $si_provider
                                        ? esc_html__( 'Ollama runs locally, so no API key is needed. Make sure it is running and reachable from your server.', 'jat-search-intel' )
                                        : esc_html__( 'Set the endpoint and model above. Add a key below only if your server needs one.', 'jat-search-intel' );
                                    ?>
                                </p>
                                <?php if ( 'openai_compatible' === $si_provider ) : ?>
                                    <div style="display:flex; gap:8px; align-items:center; margin:6px 0;">
                                        <input type="password" id="si_api_key_<?php echo esc_attr( $si_provider ); ?>" class="regular-text si-api-key-input" data-provider="<?php echo esc_attr( $si_provider ); ?>" value="<?php echo $ai_engine->get_api_key( $si_provider ) !== '' ? '••••••••••••••••' : ''; ?>" autocomplete="off" />
                                        <button type="button" class="button si-save-api-key" data-provider="<?php echo esc_attr( $si_provider ); ?>"><?php esc_html_e( 'Save Key', 'jat-search-intel' ); ?></button>
                                    </div>
                                <?php endif; ?>
                                <button type="button" class="button si-test-api-key" data-provider="<?php echo esc_attr( $si_provider ); ?>"><?php esc_html_e( 'Test Connection', 'jat-search-intel' ); ?></button>
                                <span class="si-api-key-status" data-provider="<?php echo esc_attr( $si_provider ); ?>"></span>
                            </td>
                        </tr>
                        <?php continue; ?>
                    <?php endif; ?>
                    <tr class="si-api-key-row" data-provider="<?php echo esc_attr( $si_provider ); ?>">
                        <th>
                            <label for="si_api_key_<?php echo esc_attr( $si_provider ); ?>">
                                <?php
                                /* translators: %s: AI provider name. */
                                echo esc_html( sprintf( __( '%s API Key', 'jat-search-intel' ), $si_details['label'] ) );
                                ?>
                            </label>
                        </th>
                        <td>
                            <div style="display:flex; gap:8px; align-items:center;">
                                <input type="password" id="si_api_key_<?php echo esc_attr( $si_provider ); ?>" class="regular-text si-api-key-input" data-provider="<?php echo esc_attr( $si_provider ); ?>" value="<?php echo $ai_engine->get_api_key( $si_provider ) !== '' ? '••••••••••••••••' : ''; ?>" autocomplete="off" />
                                <button type="button" class="button si-save-api-key" data-provider="<?php echo esc_attr( $si_provider ); ?>"><?php esc_html_e( 'Save Key', 'jat-search-intel' ); ?></button>
                                <button type="button" class="button si-test-api-key" data-provider="<?php echo esc_attr( $si_provider ); ?>"><?php esc_html_e( 'Test', 'jat-search-intel' ); ?></button>
                                <span class="si-api-key-status" data-provider="<?php echo esc_attr( $si_provider ); ?>">
                                    <?php if ( $ai_engine->get_api_key( $si_provider ) !== '' ) : ?>
                                        <span class="si-status-saved">&#10003; <?php esc_html_e( 'Saved', 'jat-search-intel' ); ?></span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php if ( '' !== $si_details['key_url'] ) : ?>
                                <p class="description"><a href="<?php echo esc_url( $si_details['key_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Get an API key →', 'jat-search-intel' ); ?></a></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <!-- Rate Limiting & Budget -->
        <div class="si-settings-card">
            <h2><?php esc_html_e( 'Rate Limiting & Budget', 'jat-search-intel' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="si_ai_rate_limit"><?php esc_html_e( 'Max Requests / Minute', 'jat-search-intel' ); ?></label></th>
                    <td>
                        <input type="number" id="si_ai_rate_limit" name="jat_search_intel_settings[ai_rate_limit]" value="<?php echo esc_attr( $settings['ai_rate_limit'] ); ?>" min="1" max="200" step="1" class="small-text" />
                        <p class="description"><?php esc_html_e( 'Limits how many AI requests can be made per minute to avoid hitting provider rate limits.', 'jat-search-intel' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="si_ai_token_budget"><?php esc_html_e( 'Monthly Token Budget', 'jat-search-intel' ); ?></label></th>
                    <td>
                        <input type="number" id="si_ai_token_budget" name="jat_search_intel_settings[ai_token_budget]" value="<?php echo esc_attr( $settings['ai_token_budget'] ); ?>" min="0" step="1000" class="regular-text" />
                        <p class="description"><?php esc_html_e( 'Set to 0 for unlimited. Prevents runaway costs by capping total tokens per month.', 'jat-search-intel' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Token Usage Stats -->
        <div class="si-settings-card si-info-card">
            <h2><?php esc_html_e( 'Usage Statistics', 'jat-search-intel' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'This Month', 'jat-search-intel' ); ?></th>
                    <td>
                        <strong><?php echo esc_html( number_format( $usage['month_tokens'] ) ); ?></strong> <?php esc_html_e( 'tokens', 'jat-search-intel' ); ?>
                        <?php if ( (int) $settings['ai_token_budget'] > 0 ) : ?>
                            / <?php echo esc_html( number_format( (int) $settings['ai_token_budget'] ) ); ?>
                            (<?php echo esc_html( round( ( $usage['month_tokens'] / max( 1, (int) $settings['ai_token_budget'] ) ) * 100, 1 ) ); ?>%)
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Lifetime', 'jat-search-intel' ); ?></th>
                    <td><strong><?php echo esc_html( number_format( $usage['total_tokens'] ) ); ?></strong> <?php esc_html_e( 'tokens', 'jat-search-intel' ); ?></td>
                </tr>
            </table>
        </div>

        <?php submit_button( __( 'Save AI Settings', 'jat-search-intel' ) ); ?>
    </form>
</div>

<style>
    .si-status-saved { color: #00a32a; font-weight: 600; }
    .si-status-error { color: #d63638; font-weight: 600; }
    .si-status-testing { color: #dba617; font-weight: 600; }
</style>

<script>
jQuery(function ($) {
    'use strict';

    var ajaxUrl = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
    var nonce   = '<?php echo esc_js( wp_create_nonce( 'si_ai_settings' ) ); ?>';

    // Messages can come from a remote AI server, so they are always inserted as text.
    function setStatus(el, cls, text) {
        el.empty().append($('<span>').addClass(cls).text(text));
    }

    // Save API key via AJAX (encrypted storage).
    $('.si-save-api-key').on('click', function () {
        var btn      = $(this);
        var provider = btn.data('provider');
        var input    = $('.si-api-key-input[data-provider="' + provider + '"]');
        var apiKey   = input.val();
        var status   = $('.si-api-key-status[data-provider="' + provider + '"]');

        if (!apiKey || apiKey === '••••••••••••••••') {
            setStatus(status, 'si-status-error', 'Enter a new key first.');
            return;
        }

        btn.prop('disabled', true);
        setStatus(status, 'si-status-testing', 'Saving...');

        $.post(ajaxUrl, {
            action: 'jat_search_intel_save_ai_api_key',
            nonce: nonce,
            provider: provider,
            api_key: apiKey
        }, function (response) {
            btn.prop('disabled', false);
            if (response.success) {
                setStatus(status, 'si-status-saved', '\u2713 Saved');
                input.val('••••••••••••••••');
            } else {
                setStatus(status, 'si-status-error', (response.data && response.data.message) || 'Error');
            }
        }).fail(function () {
            btn.prop('disabled', false);
            setStatus(status, 'si-status-error', 'Request failed.');
        });
    });

    // Test API key connection.
    $('.si-test-api-key').on('click', function () {
        var btn      = $(this);
        var provider = btn.data('provider');
        var status   = $('.si-api-key-status[data-provider="' + provider + '"]');

        btn.prop('disabled', true);
        setStatus(status, 'si-status-testing', 'Testing...');

        $.post(ajaxUrl, {
            action: 'jat_search_intel_test_ai_connection',
            nonce: nonce,
            provider: provider
        }, function (response) {
            btn.prop('disabled', false);
            if (response.success) {
                setStatus(status, 'si-status-saved', '\u2713 ' + response.data.message);
            } else {
                setStatus(status, 'si-status-error', '\u2717 ' + ((response.data && response.data.message) || 'Connection failed'));
            }
        }).fail(function () {
            btn.prop('disabled', false);
            setStatus(status, 'si-status-error', 'Request failed.');
        });
    });

    // Clear placeholder on focus.
    $('.si-api-key-input').on('focus', function () {
        if ($(this).val() === '••••••••••••••••') {
            $(this).val('').attr('type', 'text');
        }
    }).on('blur', function () {
        $(this).attr('type', 'password');
    });
});
</script>
