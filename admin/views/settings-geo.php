<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- View template included inside a class method; variables are method-scoped.
$settings = get_option( 'jat_search_intel_settings', [] );
$settings = wp_parse_args( $settings, \JAT_Search_Intel\Core::get_default_settings() );
?>
<div class="wrap si-settings">
    <h1><span class="dashicons dashicons-superhero-alt"></span> <?php esc_html_e( 'JAT Search Intel — GEO Settings', 'jat-search-intel' ); ?></h1>
    <p class="si-settings-intro"><?php esc_html_e( 'Generative Engine Optimization — optimize for AI-powered search (ChatGPT, Perplexity, Google AI Overviews, etc.).', 'jat-search-intel' ); ?></p>

    <form method="post" action="options.php">
        <?php settings_fields( 'jat_search_intel_settings_group' ); ?>
        <input type="hidden" name="jat_search_intel_settings[_si_page]" value="geo" />

        <?php // Settings from other pages are preserved by the page-scoped sanitizer. ?>

        <div class="si-settings-card">
            <h2><?php esc_html_e( 'AI Meta Tags', 'jat-search-intel' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Enable AI Meta', 'jat-search-intel' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="jat_search_intel_settings[enable_ai_meta]" value="1" <?php checked( $settings['enable_ai_meta'] ); ?> />
                            <?php esc_html_e( 'Output AI-specific meta tags (content type, topics, freshness signals)', 'jat-search-intel' ); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <div class="si-settings-card">
            <h2><?php esc_html_e( 'llms.txt', 'jat-search-intel' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Enable llms.txt', 'jat-search-intel' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="jat_search_intel_settings[enable_llms_txt]" value="1" <?php checked( $settings['enable_llms_txt'] ); ?> />
                            <?php esc_html_e( 'Serve a machine-readable site summary at /llms.txt for LLM crawlers', 'jat-search-intel' ); ?>
                        </label>
                        <p class="description">
                            <?php if ( $settings['enable_llms_txt'] ) : ?>
                                <?php esc_html_e( 'Your llms.txt is live at:', 'jat-search-intel' ); ?>
                                <a href="<?php echo esc_url( home_url( '/llms.txt' ) ); ?>" target="_blank"><?php echo esc_url( home_url( '/llms.txt' ) ); ?></a>
                                <br />
                                <?php esc_html_e( 'Full version:', 'jat-search-intel' ); ?>
                                <a href="<?php echo esc_url( home_url( '/llms-full.txt' ) ); ?>" target="_blank"><?php echo esc_url( home_url( '/llms-full.txt' ) ); ?></a>
                            <?php endif; ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="si-settings-card">
            <h2><?php esc_html_e( 'Entity & Semantic Markup', 'jat-search-intel' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Enable Entity Markup', 'jat-search-intel' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="jat_search_intel_settings[enable_entity_markup]" value="1" <?php checked( $settings['enable_entity_markup'] ); ?> />
                            <?php esc_html_e( 'Wrap defined entities with Schema.org microdata for AI comprehension', 'jat-search-intel' ); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <div class="si-settings-card">
            <h2><?php esc_html_e( 'Content Freshness', 'jat-search-intel' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Freshness Signals', 'jat-search-intel' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="jat_search_intel_settings[content_freshness]" value="1" <?php checked( $settings['content_freshness'] ); ?> />
                            <?php esc_html_e( 'Output content freshness meta tags (very-fresh, fresh, recent, aging, evergreen)', 'jat-search-intel' ); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <div class="si-settings-card">
            <h2><?php esc_html_e( 'E-E-A-T (Author Authority)', 'jat-search-intel' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="si_author_expertise"><?php esc_html_e( 'Default Expertise Area', 'jat-search-intel' ); ?></label></th>
                    <td>
                        <input type="text" id="si_author_expertise" name="jat_search_intel_settings[author_expertise]" value="<?php echo esc_attr( $settings['author_expertise'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'e.g., Digital Marketing, Software Engineering', 'jat-search-intel' ); ?>" />
                        <p class="description"><?php esc_html_e( 'Fallback expertise area when authors don\'t have individual E-E-A-T data configured. Set per-author data in user profiles.', 'jat-search-intel' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="si-settings-card">
            <h2><?php esc_html_e( 'Content Defaults', 'jat-search-intel' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="si_ai_summary_word_count"><?php esc_html_e( 'AI Summary Target Words', 'jat-search-intel' ); ?></label></th>
                    <td>
                        <input type="number" id="si_ai_summary_word_count" name="jat_search_intel_settings[ai_summary_word_count]" value="<?php echo esc_attr( $settings['ai_summary_word_count'] ); ?>" min="20" max="200" step="5" class="small-text" />
                        <span class="description"><?php esc_html_e( 'words (shown as guidance in the post editor)', 'jat-search-intel' ); ?></span>
                    </td>
                </tr>
                <tr>
                    <th><label for="si_default_content_intent"><?php esc_html_e( 'Default Content Intent', 'jat-search-intel' ); ?></label></th>
                    <td>
                        <select id="si_default_content_intent" name="jat_search_intel_settings[default_content_intent]">
                            <option value="" <?php selected( $settings['default_content_intent'], '' ); ?>><?php esc_html_e( '— None —', 'jat-search-intel' ); ?></option>
                            <option value="informational" <?php selected( $settings['default_content_intent'], 'informational' ); ?>><?php esc_html_e( 'Informational', 'jat-search-intel' ); ?></option>
                            <option value="navigational" <?php selected( $settings['default_content_intent'], 'navigational' ); ?>><?php esc_html_e( 'Navigational', 'jat-search-intel' ); ?></option>
                            <option value="transactional" <?php selected( $settings['default_content_intent'], 'transactional' ); ?>><?php esc_html_e( 'Transactional', 'jat-search-intel' ); ?></option>
                            <option value="commercial" <?php selected( $settings['default_content_intent'], 'commercial' ); ?>><?php esc_html_e( 'Commercial Investigation', 'jat-search-intel' ); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e( 'Pre-selected content intent for new posts. Can be overridden per-post.', 'jat-search-intel' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="si_default_audience"><?php esc_html_e( 'Default Audience', 'jat-search-intel' ); ?></label></th>
                    <td>
                        <input type="text" id="si_default_audience" name="jat_search_intel_settings[default_audience]" value="<?php echo esc_attr( $settings['default_audience'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'e.g., Developers, Small Business Owners', 'jat-search-intel' ); ?>" />
                        <p class="description"><?php esc_html_e( 'Pre-filled audience for new posts. Can be overridden per-post.', 'jat-search-intel' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="si-settings-card">
            <h2><?php esc_html_e( 'REST API', 'jat-search-intel' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Enable REST API', 'jat-search-intel' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="jat_search_intel_settings[enable_rest_api]" value="1" <?php checked( $settings['enable_rest_api'] ); ?> />
                            <?php esc_html_e( 'Expose JAT Search Intel data via the WordPress REST API (scores, meta, analysis)', 'jat-search-intel' ); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e( 'Endpoints: /wp-json/jat-search-intel/v1/scores/{id}, /geo/{id}, /meta/{id}, /analyze/{id}, /settings, /sitemap/invalidate', 'jat-search-intel' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="si-settings-card">
            <h2><?php esc_html_e( 'AI Agents & Crawlers', 'jat-search-intel' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Markdown for AI Agents', 'jat-search-intel' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="jat_search_intel_settings[enable_markdown_endpoints]" value="1" <?php checked( ! empty( $settings['enable_markdown_endpoints'] ) ); ?> />
                            <?php esc_html_e( 'Serve a clean Markdown copy of each public post at its URL + .md (or ?format=md)', 'jat-search-intel' ); ?>
                        </label>
                        <p class="description"><?php esc_html_e( 'Pages advertise it with <link rel="alternate" type="text/markdown"> and llms.txt links to it. Markdown copies are marked noindex so they never compete with the HTML page.', 'jat-search-intel' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'AI Traffic Analytics', 'jat-search-intel' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="jat_search_intel_settings[enable_ai_crawler_log]" value="1" <?php checked( ! empty( $settings['enable_ai_crawler_log'] ) ); ?> />
                            <?php esc_html_e( 'Record AI crawler visits and visitors arriving from ChatGPT, Perplexity, Claude, Gemini, Copilot and other assistants', 'jat-search-intel' ); ?>
                        </label>
                        <p class="description"><?php esc_html_e( 'Stores daily counts and page paths only. No IP addresses or cookies. See JAT Search Intel > AI Search.', 'jat-search-intel' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Content Signals', 'jat-search-intel' ); ?></th>
                    <td>
                        <?php
                        $cs_values = (array) ( $settings['content_signals'] ?? [] );
                        $cs_labels = [
                            'search'   => __( 'Search results (links and snippets)', 'jat-search-intel' ),
                            'ai-input' => __( 'AI answers (e.g. AI Overviews, chat answers that cite you)', 'jat-search-intel' ),
                            'ai-train' => __( 'AI model training', 'jat-search-intel' ),
                        ];
                        foreach ( $cs_labels as $cs_key => $cs_label ) :
                            $cs_value = $cs_values[ $cs_key ] ?? '';
                            ?>
                            <label style="display:block; margin-bottom:6px;">
                                <select name="jat_search_intel_settings[content_signals][<?php echo esc_attr( $cs_key ); ?>]" data-jsi-signal="<?php echo esc_attr( $cs_key ); ?>">
                                    <option value="" <?php selected( $cs_value, '' ); ?>><?php esc_html_e( 'Not stated', 'jat-search-intel' ); ?></option>
                                    <option value="yes" <?php selected( $cs_value, 'yes' ); ?>><?php esc_html_e( 'Yes', 'jat-search-intel' ); ?></option>
                                    <option value="no" <?php selected( $cs_value, 'no' ); ?>><?php esc_html_e( 'No', 'jat-search-intel' ); ?></option>
                                </select>
                                <?php echo esc_html( $cs_label ); ?>
                            </label>
                        <?php endforeach; ?>
                        <p class="description"><?php esc_html_e( 'Adds a Content-Signal line to robots.txt stating how your content may be used. It is a public statement of your terms; not every company has committed to honor it, so combine it with the crawler rules below.', 'jat-search-intel' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Crawler Access', 'jat-search-intel' ); ?></th>
                    <td>
                        <p>
                            <button type="button" class="button" data-jsi-ai-preset="allow"><?php esc_html_e( 'Allow all', 'jat-search-intel' ); ?></button>
                            <button type="button" class="button" data-jsi-ai-preset="training"><?php esc_html_e( 'Block training, keep AI search', 'jat-search-intel' ); ?></button>
                            <button type="button" class="button" data-jsi-ai-preset="block"><?php esc_html_e( 'Block all AI', 'jat-search-intel' ); ?></button>
                        </p>
                        <table class="widefat striped" style="max-width:720px">
                            <thead><tr>
                                <th><?php esc_html_e( 'Crawler', 'jat-search-intel' ); ?></th>
                                <th><?php esc_html_e( 'Operator', 'jat-search-intel' ); ?></th>
                                <th><?php esc_html_e( 'Purpose', 'jat-search-intel' ); ?></th>
                                <th><?php esc_html_e( 'robots.txt', 'jat-search-intel' ); ?></th>
                            </tr></thead>
                            <tbody>
                            <?php
                            $ai_rules = (array) ( $settings['ai_crawler_rules'] ?? [] );
                            foreach ( \JAT_Search_Intel\AI_Search::BOTS as $bot_token => $bot_info ) :
                                $bot_rule = $ai_rules[ $bot_token ] ?? 'allow';
                                ?>
                                <tr>
                                    <td><code><?php echo esc_html( $bot_token ); ?></code></td>
                                    <td><?php echo esc_html( $bot_info[0] ); ?></td>
                                    <td><?php echo esc_html( \JAT_Search_Intel\AI_Search::purpose_label( $bot_info[1] ) ); ?></td>
                                    <td>
                                        <select name="jat_search_intel_settings[ai_crawler_rules][<?php echo esc_attr( $bot_token ); ?>]" data-jsi-purpose="<?php echo esc_attr( $bot_info[1] ); ?>">
                                            <option value="allow" <?php selected( $bot_rule, 'allow' ); ?>><?php esc_html_e( 'Allow', 'jat-search-intel' ); ?></option>
                                            <option value="block" <?php selected( $bot_rule, 'block' ); ?>><?php esc_html_e( 'Block', 'jat-search-intel' ); ?></option>
                                        </select>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p class="description"><?php esc_html_e( 'Blocked crawlers get a "Disallow: /" group in your robots.txt. Blocking "AI search" crawlers removes you from that assistant\'s answers; most sites block training only. "Live fetch" agents act for a person and may not obey robots.txt.', 'jat-search-intel' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="si-settings-card si-info-card">
            <h2><?php esc_html_e( 'GEO Tips', 'jat-search-intel' ); ?></h2>
            <ul>
                <li><?php esc_html_e( '✓ Write clear definitions — "X is defined as..." helps AI systems extract facts.', 'jat-search-intel' ); ?></li>
                <li><?php esc_html_e( '✓ Cite authoritative sources — AI engines prefer content with verifiable claims.', 'jat-search-intel' ); ?></li>
                <li><?php esc_html_e( '✓ Keep content updated — freshness signals influence AI citation ranking.', 'jat-search-intel' ); ?></li>
                <li><?php esc_html_e( '✓ Use the AI Summary field to provide a concise, factual overview for LLMs.', 'jat-search-intel' ); ?></li>
                <li><?php esc_html_e( '✓ Define entities (people, organizations, products) so AI understands context.', 'jat-search-intel' ); ?></li>
                <li><?php esc_html_e( '✓ Fill out author profiles with credentials and expertise for E-E-A-T signals.', 'jat-search-intel' ); ?></li>
            </ul>
        </div>

        <?php submit_button( __( 'Save GEO Settings', 'jat-search-intel' ) ); ?>
    </form>
</div>
