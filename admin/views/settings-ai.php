<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
$settings  = get_option( 'search_intel_settings', [] );
$settings  = wp_parse_args( $settings, \Search_Intel\Core::get_default_settings() );
$ai_engine = \Search_Intel\Core::get_instance()->ai_engine;
$providers = $ai_engine->get_providers();
$usage     = $ai_engine->get_token_usage();
?>
<div class="wrap si-settings">
    <h1><span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'Search Intel — AI Engine', 'search-intel' ); ?></h1>
    <p class="si-settings-intro"><?php esc_html_e( 'Configure your AI provider for content generation, meta descriptions, title suggestions, and bulk AI tools.', 'search-intel' ); ?></p>

    <form method="post" action="options.php" id="si-ai-settings-form">
        <?php settings_fields( 'search_intel_settings_group' ); ?>

        <?php
        // Preserve all non-AI settings as hidden fields.
        $ai_keys = [ 'enable_ai_engine', 'ai_provider', 'ai_model', 'ai_endpoint', 'ai_rate_limit', 'ai_token_budget' ];
        foreach ( $settings as $key => $val ) :
            if ( in_array( $key, $ai_keys, true ) ) {
                continue;
            }
            if ( is_array( $val ) ) :
                foreach ( $val as $v ) : ?>
                    <input type="hidden" name="search_intel_settings[<?php echo esc_attr( $key ); ?>][]" value="<?php echo esc_attr( $v ); ?>" />
                <?php endforeach;
            elseif ( is_bool( $val ) || $val === true ) :
                if ( $val ) : ?>
                    <input type="hidden" name="search_intel_settings[<?php echo esc_attr( $key ); ?>]" value="1" />
                <?php endif;
            else : ?>
                <input type="hidden" name="search_intel_settings[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $val ); ?>" />
            <?php endif;
        endforeach;
        ?>

        <!-- Enable AI Engine -->
        <div class="si-settings-card">
            <h2><?php esc_html_e( 'Enable AI Engine', 'search-intel' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'AI Engine', 'search-intel' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="search_intel_settings[enable_ai_engine]" value="1" <?php checked( $settings['enable_ai_engine'] ); ?> />
                            <?php esc_html_e( 'Enable AI-powered content generation (meta descriptions, title suggestions, AI summaries, bulk generation)', 'search-intel' ); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Provider Selection -->
        <div class="si-settings-card">
            <h2><?php esc_html_e( 'AI Provider', 'search-intel' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="si_ai_provider"><?php esc_html_e( 'Active Provider', 'search-intel' ); ?></label></th>
                    <td>
                        <select id="si_ai_provider" name="search_intel_settings[ai_provider]">
                            <?php foreach ( $providers as $id => $label ) : ?>
                                <option value="<?php echo esc_attr( $id ); ?>" <?php selected( $settings['ai_provider'], $id ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e( 'Select which AI provider to use for content generation.', 'search-intel' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="si_ai_model"><?php esc_html_e( 'Model Override', 'search-intel' ); ?></label></th>
                    <td>
                        <input type="text" id="si_ai_model" name="search_intel_settings[ai_model]" value="<?php echo esc_attr( $settings['ai_model'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Leave blank for default', 'search-intel' ); ?>" />
                        <p class="description">
                            <?php esc_html_e( 'Defaults:', 'search-intel' ); ?>
                            <strong>OpenAI:</strong> gpt-4o-mini &nbsp;|&nbsp;
                            <strong>Anthropic:</strong> claude-3-5-haiku-20241022 &nbsp;|&nbsp;
                            <strong>Ollama:</strong> llama3.2
                        </p>
                    </td>
                </tr>
                <tr id="si-ai-endpoint-row">
                    <th><label for="si_ai_endpoint"><?php esc_html_e( 'Custom Endpoint', 'search-intel' ); ?></label></th>
                    <td>
                        <input type="url" id="si_ai_endpoint" name="search_intel_settings[ai_endpoint]" value="<?php echo esc_url( $settings['ai_endpoint'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Leave blank for default', 'search-intel' ); ?>" />
                        <p class="description"><?php esc_html_e( 'For Ollama: your local server URL (e.g. http://localhost:11434). For OpenAI/Anthropic: use a proxy or compatible endpoint.', 'search-intel' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- API Keys -->
        <div class="si-settings-card">
            <h2><?php esc_html_e( 'API Keys', 'search-intel' ); ?></h2>
            <p class="description" style="margin-bottom: 12px;"><?php esc_html_e( 'API keys are encrypted before storage. They are never exposed in page source or exports.', 'search-intel' ); ?></p>
            <table class="form-table">
                <tr class="si-api-key-row" data-provider="openai">
                    <th><label for="si_api_key_openai"><?php esc_html_e( 'OpenAI API Key', 'search-intel' ); ?></label></th>
                    <td>
                        <div style="display:flex; gap:8px; align-items:center;">
                            <input type="password" id="si_api_key_openai" class="regular-text si-api-key-input" data-provider="openai" placeholder="sk-..." value="<?php echo $ai_engine->get_api_key( 'openai' ) !== '' ? '••••••••••••••••' : ''; ?>" autocomplete="off" />
                            <button type="button" class="button si-save-api-key" data-provider="openai"><?php esc_html_e( 'Save Key', 'search-intel' ); ?></button>
                            <button type="button" class="button si-test-api-key" data-provider="openai"><?php esc_html_e( 'Test', 'search-intel' ); ?></button>
                            <span class="si-api-key-status" data-provider="openai">
                                <?php if ( $ai_engine->get_api_key( 'openai' ) !== '' ) : ?>
                                    <span class="si-status-saved">&#10003; <?php esc_html_e( 'Saved', 'search-intel' ); ?></span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <p class="description"><a href="https://platform.openai.com/api-keys" target="_blank"><?php esc_html_e( 'Get your OpenAI API key →', 'search-intel' ); ?></a></p>
                    </td>
                </tr>
                <tr class="si-api-key-row" data-provider="anthropic">
                    <th><label for="si_api_key_anthropic"><?php esc_html_e( 'Anthropic API Key', 'search-intel' ); ?></label></th>
                    <td>
                        <div style="display:flex; gap:8px; align-items:center;">
                            <input type="password" id="si_api_key_anthropic" class="regular-text si-api-key-input" data-provider="anthropic" placeholder="sk-ant-..." value="<?php echo $ai_engine->get_api_key( 'anthropic' ) !== '' ? '••••••••••••••••' : ''; ?>" autocomplete="off" />
                            <button type="button" class="button si-save-api-key" data-provider="anthropic"><?php esc_html_e( 'Save Key', 'search-intel' ); ?></button>
                            <button type="button" class="button si-test-api-key" data-provider="anthropic"><?php esc_html_e( 'Test', 'search-intel' ); ?></button>
                            <span class="si-api-key-status" data-provider="anthropic">
                                <?php if ( $ai_engine->get_api_key( 'anthropic' ) !== '' ) : ?>
                                    <span class="si-status-saved">&#10003; <?php esc_html_e( 'Saved', 'search-intel' ); ?></span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <p class="description"><a href="https://console.anthropic.com/settings/keys" target="_blank"><?php esc_html_e( 'Get your Anthropic API key →', 'search-intel' ); ?></a></p>
                    </td>
                </tr>
                <tr class="si-api-key-row" data-provider="ollama">
                    <th><?php esc_html_e( 'Ollama (Local)', 'search-intel' ); ?></th>
                    <td>
                        <p class="description"><?php esc_html_e( 'Ollama runs locally — no API key required. Ensure Ollama is running on your server.', 'search-intel' ); ?></p>
                        <button type="button" class="button si-test-api-key" data-provider="ollama"><?php esc_html_e( 'Test Connection', 'search-intel' ); ?></button>
                        <span class="si-api-key-status" data-provider="ollama"></span>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Rate Limiting & Budget -->
        <div class="si-settings-card">
            <h2><?php esc_html_e( 'Rate Limiting & Budget', 'search-intel' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="si_ai_rate_limit"><?php esc_html_e( 'Max Requests / Minute', 'search-intel' ); ?></label></th>
                    <td>
                        <input type="number" id="si_ai_rate_limit" name="search_intel_settings[ai_rate_limit]" value="<?php echo esc_attr( $settings['ai_rate_limit'] ); ?>" min="1" max="200" step="1" class="small-text" />
                        <p class="description"><?php esc_html_e( 'Limits how many AI requests can be made per minute to avoid hitting provider rate limits.', 'search-intel' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="si_ai_token_budget"><?php esc_html_e( 'Monthly Token Budget', 'search-intel' ); ?></label></th>
                    <td>
                        <input type="number" id="si_ai_token_budget" name="search_intel_settings[ai_token_budget]" value="<?php echo esc_attr( $settings['ai_token_budget'] ); ?>" min="0" step="1000" class="regular-text" />
                        <p class="description"><?php esc_html_e( 'Set to 0 for unlimited. Prevents runaway costs by capping total tokens per month.', 'search-intel' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Token Usage Stats -->
        <div class="si-settings-card si-info-card">
            <h2><?php esc_html_e( 'Usage Statistics', 'search-intel' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'This Month', 'search-intel' ); ?></th>
                    <td>
                        <strong><?php echo esc_html( number_format( $usage['month_tokens'] ) ); ?></strong> <?php esc_html_e( 'tokens', 'search-intel' ); ?>
                        <?php if ( (int) $settings['ai_token_budget'] > 0 ) : ?>
                            / <?php echo esc_html( number_format( (int) $settings['ai_token_budget'] ) ); ?>
                            (<?php echo esc_html( round( ( $usage['month_tokens'] / max( 1, (int) $settings['ai_token_budget'] ) ) * 100, 1 ) ); ?>%)
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Lifetime', 'search-intel' ); ?></th>
                    <td><strong><?php echo esc_html( number_format( $usage['total_tokens'] ) ); ?></strong> <?php esc_html_e( 'tokens', 'search-intel' ); ?></td>
                </tr>
            </table>
        </div>

        <?php submit_button( __( 'Save AI Settings', 'search-intel' ) ); ?>
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

    // Save API key via AJAX (encrypted storage).
    $('.si-save-api-key').on('click', function () {
        var btn      = $(this);
        var provider = btn.data('provider');
        var input    = $('.si-api-key-input[data-provider="' + provider + '"]');
        var apiKey   = input.val();
        var status   = $('.si-api-key-status[data-provider="' + provider + '"]');

        if (!apiKey || apiKey === '••••••••••••••••') {
            status.html('<span class="si-status-error">Enter a new key first.</span>');
            return;
        }

        btn.prop('disabled', true);
        status.html('<span class="si-status-testing">Saving...</span>');

        $.post(ajaxUrl, {
            action: 'si_save_ai_api_key',
            nonce: nonce,
            provider: provider,
            api_key: apiKey
        }, function (response) {
            btn.prop('disabled', false);
            if (response.success) {
                status.html('<span class="si-status-saved">&#10003; Saved</span>');
                input.val('••••••••••••••••');
            } else {
                status.html('<span class="si-status-error">' + (response.data.message || 'Error') + '</span>');
            }
        }).fail(function () {
            btn.prop('disabled', false);
            status.html('<span class="si-status-error">Request failed.</span>');
        });
    });

    // Test API key connection.
    $('.si-test-api-key').on('click', function () {
        var btn      = $(this);
        var provider = btn.data('provider');
        var status   = $('.si-api-key-status[data-provider="' + provider + '"]');

        btn.prop('disabled', true);
        status.html('<span class="si-status-testing">Testing...</span>');

        $.post(ajaxUrl, {
            action: 'si_test_ai_connection',
            nonce: nonce,
            provider: provider
        }, function (response) {
            btn.prop('disabled', false);
            if (response.success) {
                status.html('<span class="si-status-saved">&#10003; ' + response.data.message + '</span>');
            } else {
                status.html('<span class="si-status-error">&#10007; ' + (response.data.message || 'Connection failed') + '</span>');
            }
        }).fail(function () {
            btn.prop('disabled', false);
            status.html('<span class="si-status-error">Request failed.</span>');
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
