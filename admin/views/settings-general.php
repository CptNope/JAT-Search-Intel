<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- View template included inside a class method; variables are method-scoped.
$settings = get_option( 'jat_search_intel_settings', [] );
$settings = wp_parse_args( $settings, \JAT_Search_Intel\Core::get_default_settings() );
?>
<div class="wrap si-settings">
    <h1><span class="dashicons dashicons-chart-area"></span> <?php esc_html_e( 'JAT Search Intel — General Settings', 'jat-search-intel' ); ?></h1>

    <div class="si-settings-header">
        <p><?php esc_html_e( 'Configure your site-wide SEO, AEO, and GEO settings. These defaults apply to all content.', 'jat-search-intel' ); ?></p>
    </div>

    <form method="post" action="options.php">
        <?php settings_fields( 'jat_search_intel_settings_group' ); ?>
        <input type="hidden" name="jat_search_intel_settings[_si_page]" value="general" />

        <div class="si-settings-card">
            <h2><?php esc_html_e( 'Site Identity', 'jat-search-intel' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="si_site_name"><?php esc_html_e( 'Site Name', 'jat-search-intel' ); ?></label></th>
                    <td>
                        <input type="text" id="si_site_name" name="jat_search_intel_settings[site_name]" value="<?php echo esc_attr( $settings['site_name'] ); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th><label for="si_separator"><?php esc_html_e( 'Title Separator', 'jat-search-intel' ); ?></label></th>
                    <td>
                        <select id="si_separator" name="jat_search_intel_settings[separator]">
                            <?php foreach ( [ '|', '-', '–', '—', '·', '•', '»', '/' ] as $sep ) : ?>
                                <option value="<?php echo esc_attr( $sep ); ?>" <?php selected( $settings['separator'], $sep ); ?>><?php echo esc_html( $sep ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="si_knowledge_type"><?php esc_html_e( 'Entity Type', 'jat-search-intel' ); ?></label></th>
                    <td>
                        <select id="si_knowledge_type" name="jat_search_intel_settings[knowledge_type]">
                            <option value="Organization" <?php selected( $settings['knowledge_type'], 'Organization' ); ?>><?php esc_html_e( 'Organization', 'jat-search-intel' ); ?></option>
                            <option value="Person" <?php selected( $settings['knowledge_type'], 'Person' ); ?>><?php esc_html_e( 'Person', 'jat-search-intel' ); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="si_knowledge_name"><?php esc_html_e( 'Organization / Person Name', 'jat-search-intel' ); ?></label></th>
                    <td>
                        <input type="text" id="si_knowledge_name" name="jat_search_intel_settings[knowledge_name]" value="<?php echo esc_attr( $settings['knowledge_name'] ); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th><label for="si_knowledge_logo"><?php esc_html_e( 'Logo URL', 'jat-search-intel' ); ?></label></th>
                    <td>
                        <input type="url" id="si_knowledge_logo" name="jat_search_intel_settings[knowledge_logo]" value="<?php echo esc_url( $settings['knowledge_logo'] ); ?>" class="regular-text" />
                        <p class="description"><?php esc_html_e( 'Recommended: 1200×630px or square logo.', 'jat-search-intel' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="si-settings-card">
            <h2><?php esc_html_e( 'Enabled Post Types', 'jat-search-intel' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Enable JAT Search Intel on', 'jat-search-intel' ); ?></th>
                    <td>
                        <?php
                        $post_types = get_post_types( [ 'public' => true ], 'objects' );
                        foreach ( $post_types as $pt ) :
                            if ( 'attachment' === $pt->name ) {
                                continue;
                            }
                            ?>
                            <label style="display:block; margin-bottom:6px;">
                                <input type="checkbox" name="jat_search_intel_settings[enabled_post_types][]" value="<?php echo esc_attr( $pt->name ); ?>" <?php checked( in_array( $pt->name, $settings['enabled_post_types'] ?? [], true ) ); ?> />
                                <?php echo esc_html( $pt->label ); ?>
                            </label>
                        <?php endforeach; ?>
                    </td>
                </tr>
            </table>
        </div>

        <div class="si-settings-card">
            <h2><?php esc_html_e( 'Module Toggles', 'jat-search-intel' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Core Modules', 'jat-search-intel' ); ?></th>
                    <td>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="jat_search_intel_settings[enable_seo]" value="1" <?php checked( $settings['enable_seo'] ); ?> />
                            <strong><?php esc_html_e( 'SEO', 'jat-search-intel' ); ?></strong> — <?php esc_html_e( 'Meta tags, Open Graph, Twitter Cards, canonical URLs', 'jat-search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="jat_search_intel_settings[enable_schema]" value="1" <?php checked( $settings['enable_schema'] ); ?> />
                            <strong><?php esc_html_e( 'Schema', 'jat-search-intel' ); ?></strong> — <?php esc_html_e( 'JSON-LD structured data', 'jat-search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="jat_search_intel_settings[enable_aeo]" value="1" <?php checked( $settings['enable_aeo'] ); ?> />
                            <strong><?php esc_html_e( 'AEO', 'jat-search-intel' ); ?></strong> — <?php esc_html_e( 'Answer Engine Optimization (FAQ, HowTo, Speakable)', 'jat-search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="jat_search_intel_settings[enable_geo]" value="1" <?php checked( $settings['enable_geo'] ); ?> />
                            <strong><?php esc_html_e( 'GEO', 'jat-search-intel' ); ?></strong> — <?php esc_html_e( 'Generative Engine Optimization (AI meta, llms.txt, E-E-A-T)', 'jat-search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="jat_search_intel_settings[enable_sitemap]" value="1" <?php checked( $settings['enable_sitemap'] ); ?> />
                            <strong><?php esc_html_e( 'Sitemap', 'jat-search-intel' ); ?></strong> — <?php esc_html_e( 'XML sitemap with image support', 'jat-search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="jat_search_intel_settings[enable_analyzer]" value="1" <?php checked( $settings['enable_analyzer'] ); ?> />
                            <strong><?php esc_html_e( 'Content Analyzer', 'jat-search-intel' ); ?></strong> — <?php esc_html_e( 'Real-time scoring and optimization tips', 'jat-search-intel' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Advanced SEO', 'jat-search-intel' ); ?></th>
                    <td>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="jat_search_intel_settings[enable_redirects]" value="1" <?php checked( $settings['enable_redirects'] ); ?> />
                            <strong><?php esc_html_e( 'Redirect Manager', 'jat-search-intel' ); ?></strong> — <?php esc_html_e( '301/302/307/410 redirects, 404 log, auto-redirect on slug change', 'jat-search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="jat_search_intel_settings[enable_indexnow]" value="1" <?php checked( $settings['enable_indexnow'] ); ?> />
                            <strong><?php esc_html_e( 'IndexNow', 'jat-search-intel' ); ?></strong> — <?php esc_html_e( 'Instant indexing via Bing, Yandex, Naver on publish/update', 'jat-search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="jat_search_intel_settings[enable_robots_editor]" value="1" <?php checked( $settings['enable_robots_editor'] ); ?> />
                            <strong><?php esc_html_e( 'Robots.txt Editor', 'jat-search-intel' ); ?></strong> — <?php esc_html_e( 'Visual robots.txt editor with syntax validation', 'jat-search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="jat_search_intel_settings[enable_link_checker]" value="1" <?php checked( $settings['enable_link_checker'] ); ?> />
                            <strong><?php esc_html_e( 'Broken Link Checker', 'jat-search-intel' ); ?></strong> — <?php esc_html_e( 'Background HTTP HEAD scan for broken links', 'jat-search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="jat_search_intel_settings[enable_internal_linker]" value="1" <?php checked( $settings['enable_internal_linker'] ); ?> />
                            <strong><?php esc_html_e( 'Internal Linking Suggestions', 'jat-search-intel' ); ?></strong> — <?php esc_html_e( 'AI-powered related content linking', 'jat-search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="jat_search_intel_settings[enable_local_business]" value="1" <?php checked( $settings['enable_local_business'] ); ?> />
                            <strong><?php esc_html_e( 'Local Business Schema', 'jat-search-intel' ); ?></strong> — <?php esc_html_e( 'Address, hours, geo coordinates, multi-location', 'jat-search-intel' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Advanced AEO', 'jat-search-intel' ); ?></th>
                    <td>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="jat_search_intel_settings[enable_schema_types]" value="1" <?php checked( $settings['enable_schema_types'] ); ?> />
                            <strong><?php esc_html_e( 'Additional Schema Types', 'jat-search-intel' ); ?></strong> — <?php esc_html_e( 'VideoObject, Recipe, Event, Review, Course, Software, JobPosting', 'jat-search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="jat_search_intel_settings[enable_qa_detector]" value="1" <?php checked( $settings['enable_qa_detector'] ); ?> />
                            <strong><?php esc_html_e( 'Q&A Detection Engine', 'jat-search-intel' ); ?></strong> — <?php esc_html_e( 'Auto-classify content for FAQPage, HowTo, QAPage', 'jat-search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="jat_search_intel_settings[enable_faq_generator]" value="1" <?php checked( $settings['enable_faq_generator'] ); ?> />
                            <strong><?php esc_html_e( 'FAQ Generator', 'jat-search-intel' ); ?></strong> — <?php esc_html_e( 'Extract FAQs from content with optional AI enhancement', 'jat-search-intel' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Advanced GEO', 'jat-search-intel' ); ?></th>
                    <td>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="jat_search_intel_settings[enable_visibility_dashboard]" value="1" <?php checked( $settings['enable_visibility_dashboard'] ); ?> />
                            <strong><?php esc_html_e( 'AI Visibility Dashboard', 'jat-search-intel' ); ?></strong> — <?php esc_html_e( 'GEO health score, GSC/Bing API, llms.txt tracking', 'jat-search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="jat_search_intel_settings[enable_citation_graph]" value="1" <?php checked( $settings['enable_citation_graph'] ); ?> />
                            <strong><?php esc_html_e( 'Citation Graph', 'jat-search-intel' ); ?></strong> — <?php esc_html_e( 'Link graph, topic clusters, orphaned content detection', 'jat-search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="jat_search_intel_settings[enable_authority_map]" value="1" <?php checked( $settings['enable_authority_map'] ); ?> />
                            <strong><?php esc_html_e( 'Topical Authority Map', 'jat-search-intel' ); ?></strong> — <?php esc_html_e( 'Authority scoring, content gap analysis, topic clusters', 'jat-search-intel' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'AI & Content Intelligence', 'jat-search-intel' ); ?></th>
                    <td>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="jat_search_intel_settings[enable_ai_engine]" value="1" <?php checked( $settings['enable_ai_engine'] ); ?> />
                            <strong><?php esc_html_e( 'AI Engine', 'jat-search-intel' ); ?></strong> — <?php esc_html_e( 'LLM abstraction (OpenAI, Anthropic, Ollama) for bulk generation', 'jat-search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="jat_search_intel_settings[enable_content_decay]" value="1" <?php checked( $settings['enable_content_decay'] ); ?> />
                            <strong><?php esc_html_e( 'Content Decay Detection', 'jat-search-intel' ); ?></strong> — <?php esc_html_e( 'Track score drops, flag declining content, refresh suggestions', 'jat-search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="jat_search_intel_settings[enable_cannibalization]" value="1" <?php checked( $settings['enable_cannibalization'] ); ?> />
                            <strong><?php esc_html_e( 'Keyword Cannibalization', 'jat-search-intel' ); ?></strong> — <?php esc_html_e( 'Detect duplicate keyword targets with action suggestions', 'jat-search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="jat_search_intel_settings[enable_advanced_linking]" value="1" <?php checked( $settings['enable_advanced_linking'] ); ?> />
                            <strong><?php esc_html_e( 'Advanced Internal Linking', 'jat-search-intel' ); ?></strong> — <?php esc_html_e( 'Auto-suggest links, orphan alerts, equity flow visualization', 'jat-search-intel' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Analytics & Reporting', 'jat-search-intel' ); ?></th>
                    <td>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="jat_search_intel_settings[enable_historical_analytics]" value="1" <?php checked( $settings['enable_historical_analytics'] ); ?> />
                            <strong><?php esc_html_e( 'Score Analytics', 'jat-search-intel' ); ?></strong> — <?php esc_html_e( 'Per-post score history, site-wide trends, before/after comparison', 'jat-search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="jat_search_intel_settings[enable_ga4]" value="1" <?php checked( $settings['enable_ga4'] ); ?> />
                            <strong><?php esc_html_e( 'GA4 Integration', 'jat-search-intel' ); ?></strong> — <?php esc_html_e( 'Correlate SEO scores with organic traffic data', 'jat-search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="jat_search_intel_settings[enable_cwv]" value="1" <?php checked( $settings['enable_cwv'] ); ?> />
                            <strong><?php esc_html_e( 'Core Web Vitals', 'jat-search-intel' ); ?></strong> — <?php esc_html_e( 'PageSpeed Insights API, LCP/INP/CLS tracking', 'jat-search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="jat_search_intel_settings[enable_custom_reports]" value="1" <?php checked( $settings['enable_custom_reports'] ); ?> />
                            <strong><?php esc_html_e( 'Custom Reports & Export', 'jat-search-intel' ); ?></strong> — <?php esc_html_e( 'PDF reports, scheduled email, CSV/JSON export', 'jat-search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="jat_search_intel_settings[enable_notifications]" value="1" <?php checked( $settings['enable_notifications'] ); ?> />
                            <strong><?php esc_html_e( 'Notifications & Alerts', 'jat-search-intel' ); ?></strong> — <?php esc_html_e( 'Email digest, score drop alerts, Slack webhooks', 'jat-search-intel' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Enterprise', 'jat-search-intel' ); ?></th>
                    <td>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="jat_search_intel_settings[enable_rbac]" value="1" <?php checked( $settings['enable_rbac'] ); ?> />
                            <strong><?php esc_html_e( 'Role-Based Access Control', 'jat-search-intel' ); ?></strong> — <?php esc_html_e( 'SEO Editor role, per-module permissions, settings lock', 'jat-search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="jat_search_intel_settings[enable_multilang]" value="1" <?php checked( $settings['enable_multilang'] ); ?> />
                            <strong><?php esc_html_e( 'Multi-Language', 'jat-search-intel' ); ?></strong> — <?php esc_html_e( 'WPML/Polylang integration, hreflang, per-language sitemaps', 'jat-search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="jat_search_intel_settings[enable_white_label]" value="1" <?php checked( $settings['enable_white_label'] ); ?> />
                            <strong><?php esc_html_e( 'White-Label', 'jat-search-intel' ); ?></strong> — <?php esc_html_e( 'Custom branding, plugin name, logo, reseller support', 'jat-search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="jat_search_intel_settings[enable_scale]" value="1" <?php checked( $settings['enable_scale'] ); ?> />
                            <strong><?php esc_html_e( 'Scale & Performance', 'jat-search-intel' ); ?></strong> — <?php esc_html_e( 'Object cache, background queue, bulk editor, headless REST', 'jat-search-intel' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Security', 'jat-search-intel' ); ?></th>
                    <td>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="jat_search_intel_settings[enable_audit_log]" value="1" <?php checked( $settings['enable_audit_log'] ); ?> />
                            <strong><?php esc_html_e( 'Audit Log', 'jat-search-intel' ); ?></strong> — <?php esc_html_e( 'Track settings changes with timestamps and user info', 'jat-search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="jat_search_intel_settings[enable_csp_headers]" value="1" <?php checked( $settings['enable_csp_headers'] ); ?> />
                            <strong><?php esc_html_e( 'CSP Headers', 'jat-search-intel' ); ?></strong> — <?php esc_html_e( 'Content Security Policy on plugin endpoints', 'jat-search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="jat_search_intel_settings[enable_sri]" value="1" <?php checked( $settings['enable_sri'] ); ?> />
                            <strong><?php esc_html_e( 'Subresource Integrity', 'jat-search-intel' ); ?></strong> — <?php esc_html_e( 'SRI hashes on admin JS/CSS assets', 'jat-search-intel' ); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <div class="si-settings-card">
            <h2><?php esc_html_e( 'Data Management', 'jat-search-intel' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Uninstall', 'jat-search-intel' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="jat_search_intel_settings[delete_data_on_uninstall]" value="1" <?php checked( ! empty( $settings['delete_data_on_uninstall'] ) ); ?> />
                            <?php esc_html_e( 'Delete all plugin data (settings, post meta, redirects, logs) when the plugin is deleted', 'jat-search-intel' ); ?>
                        </label>
                        <p class="description"><?php esc_html_e( 'Leave unchecked to keep your SEO data if you reinstall later.', 'jat-search-intel' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <?php submit_button( __( 'Save Settings', 'jat-search-intel' ) ); ?>
    </form>
</div>
