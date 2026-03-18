<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
$settings = get_option( 'search_intel_settings', [] );
$settings = wp_parse_args( $settings, \Search_Intel\Core::get_default_settings() );
?>
<div class="wrap si-settings">
    <h1><span class="dashicons dashicons-chart-area"></span> <?php esc_html_e( 'Search Intel — General Settings', 'search-intel' ); ?></h1>

    <div class="si-settings-header">
        <p><?php esc_html_e( 'Configure your site-wide SEO, AEO, and GEO settings. These defaults apply to all content.', 'search-intel' ); ?></p>
    </div>

    <form method="post" action="options.php">
        <?php settings_fields( 'search_intel_settings_group' ); ?>
        <input type="hidden" name="search_intel_settings[_si_page]" value="general" />

        <div class="si-settings-card">
            <h2><?php esc_html_e( 'Site Identity', 'search-intel' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="si_site_name"><?php esc_html_e( 'Site Name', 'search-intel' ); ?></label></th>
                    <td>
                        <input type="text" id="si_site_name" name="search_intel_settings[site_name]" value="<?php echo esc_attr( $settings['site_name'] ); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th><label for="si_separator"><?php esc_html_e( 'Title Separator', 'search-intel' ); ?></label></th>
                    <td>
                        <select id="si_separator" name="search_intel_settings[separator]">
                            <?php foreach ( [ '|', '-', '–', '—', '·', '•', '»', '/' ] as $sep ) : ?>
                                <option value="<?php echo esc_attr( $sep ); ?>" <?php selected( $settings['separator'], $sep ); ?>><?php echo esc_html( $sep ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="si_knowledge_type"><?php esc_html_e( 'Entity Type', 'search-intel' ); ?></label></th>
                    <td>
                        <select id="si_knowledge_type" name="search_intel_settings[knowledge_type]">
                            <option value="Organization" <?php selected( $settings['knowledge_type'], 'Organization' ); ?>><?php esc_html_e( 'Organization', 'search-intel' ); ?></option>
                            <option value="Person" <?php selected( $settings['knowledge_type'], 'Person' ); ?>><?php esc_html_e( 'Person', 'search-intel' ); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="si_knowledge_name"><?php esc_html_e( 'Organization / Person Name', 'search-intel' ); ?></label></th>
                    <td>
                        <input type="text" id="si_knowledge_name" name="search_intel_settings[knowledge_name]" value="<?php echo esc_attr( $settings['knowledge_name'] ); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th><label for="si_knowledge_logo"><?php esc_html_e( 'Logo URL', 'search-intel' ); ?></label></th>
                    <td>
                        <input type="url" id="si_knowledge_logo" name="search_intel_settings[knowledge_logo]" value="<?php echo esc_url( $settings['knowledge_logo'] ); ?>" class="regular-text" />
                        <p class="description"><?php esc_html_e( 'Recommended: 1200×630px or square logo.', 'search-intel' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="si-settings-card">
            <h2><?php esc_html_e( 'Enabled Post Types', 'search-intel' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Enable Search Intel on', 'search-intel' ); ?></th>
                    <td>
                        <?php
                        $post_types = get_post_types( [ 'public' => true ], 'objects' );
                        foreach ( $post_types as $pt ) :
                            if ( 'attachment' === $pt->name ) {
                                continue;
                            }
                            ?>
                            <label style="display:block; margin-bottom:6px;">
                                <input type="checkbox" name="search_intel_settings[enabled_post_types][]" value="<?php echo esc_attr( $pt->name ); ?>" <?php checked( in_array( $pt->name, $settings['enabled_post_types'] ?? [], true ) ); ?> />
                                <?php echo esc_html( $pt->label ); ?>
                            </label>
                        <?php endforeach; ?>
                    </td>
                </tr>
            </table>
        </div>

        <div class="si-settings-card">
            <h2><?php esc_html_e( 'Module Toggles', 'search-intel' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Core Modules', 'search-intel' ); ?></th>
                    <td>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="search_intel_settings[enable_seo]" value="1" <?php checked( $settings['enable_seo'] ); ?> />
                            <strong><?php esc_html_e( 'SEO', 'search-intel' ); ?></strong> — <?php esc_html_e( 'Meta tags, Open Graph, Twitter Cards, canonical URLs', 'search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="search_intel_settings[enable_schema]" value="1" <?php checked( $settings['enable_schema'] ); ?> />
                            <strong><?php esc_html_e( 'Schema', 'search-intel' ); ?></strong> — <?php esc_html_e( 'JSON-LD structured data', 'search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="search_intel_settings[enable_aeo]" value="1" <?php checked( $settings['enable_aeo'] ); ?> />
                            <strong><?php esc_html_e( 'AEO', 'search-intel' ); ?></strong> — <?php esc_html_e( 'Answer Engine Optimization (FAQ, HowTo, Speakable)', 'search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="search_intel_settings[enable_geo]" value="1" <?php checked( $settings['enable_geo'] ); ?> />
                            <strong><?php esc_html_e( 'GEO', 'search-intel' ); ?></strong> — <?php esc_html_e( 'Generative Engine Optimization (AI meta, llms.txt, E-E-A-T)', 'search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="search_intel_settings[enable_sitemap]" value="1" <?php checked( $settings['enable_sitemap'] ); ?> />
                            <strong><?php esc_html_e( 'Sitemap', 'search-intel' ); ?></strong> — <?php esc_html_e( 'XML sitemap with image support', 'search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="search_intel_settings[enable_analyzer]" value="1" <?php checked( $settings['enable_analyzer'] ); ?> />
                            <strong><?php esc_html_e( 'Content Analyzer', 'search-intel' ); ?></strong> — <?php esc_html_e( 'Real-time scoring and optimization tips', 'search-intel' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Advanced SEO', 'search-intel' ); ?></th>
                    <td>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="search_intel_settings[enable_redirects]" value="1" <?php checked( $settings['enable_redirects'] ); ?> />
                            <strong><?php esc_html_e( 'Redirect Manager', 'search-intel' ); ?></strong> — <?php esc_html_e( '301/302/307/410 redirects, 404 log, auto-redirect on slug change', 'search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="search_intel_settings[enable_indexnow]" value="1" <?php checked( $settings['enable_indexnow'] ); ?> />
                            <strong><?php esc_html_e( 'IndexNow', 'search-intel' ); ?></strong> — <?php esc_html_e( 'Instant indexing via Bing, Yandex, Naver on publish/update', 'search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="search_intel_settings[enable_robots_editor]" value="1" <?php checked( $settings['enable_robots_editor'] ); ?> />
                            <strong><?php esc_html_e( 'Robots.txt Editor', 'search-intel' ); ?></strong> — <?php esc_html_e( 'Visual robots.txt editor with syntax validation', 'search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="search_intel_settings[enable_link_checker]" value="1" <?php checked( $settings['enable_link_checker'] ); ?> />
                            <strong><?php esc_html_e( 'Broken Link Checker', 'search-intel' ); ?></strong> — <?php esc_html_e( 'Background HTTP HEAD scan for broken links', 'search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="search_intel_settings[enable_internal_linker]" value="1" <?php checked( $settings['enable_internal_linker'] ); ?> />
                            <strong><?php esc_html_e( 'Internal Linking Suggestions', 'search-intel' ); ?></strong> — <?php esc_html_e( 'AI-powered related content linking', 'search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="search_intel_settings[enable_local_business]" value="1" <?php checked( $settings['enable_local_business'] ); ?> />
                            <strong><?php esc_html_e( 'Local Business Schema', 'search-intel' ); ?></strong> — <?php esc_html_e( 'Address, hours, geo coordinates, multi-location', 'search-intel' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Advanced AEO', 'search-intel' ); ?></th>
                    <td>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="search_intel_settings[enable_schema_types]" value="1" <?php checked( $settings['enable_schema_types'] ); ?> />
                            <strong><?php esc_html_e( 'Additional Schema Types', 'search-intel' ); ?></strong> — <?php esc_html_e( 'VideoObject, Recipe, Event, Review, Course, Software, JobPosting', 'search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="search_intel_settings[enable_qa_detector]" value="1" <?php checked( $settings['enable_qa_detector'] ); ?> />
                            <strong><?php esc_html_e( 'Q&A Detection Engine', 'search-intel' ); ?></strong> — <?php esc_html_e( 'Auto-classify content for FAQPage, HowTo, QAPage', 'search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="search_intel_settings[enable_faq_generator]" value="1" <?php checked( $settings['enable_faq_generator'] ); ?> />
                            <strong><?php esc_html_e( 'FAQ Generator', 'search-intel' ); ?></strong> — <?php esc_html_e( 'Extract FAQs from content with optional AI enhancement', 'search-intel' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Advanced GEO', 'search-intel' ); ?></th>
                    <td>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="search_intel_settings[enable_visibility_dashboard]" value="1" <?php checked( $settings['enable_visibility_dashboard'] ); ?> />
                            <strong><?php esc_html_e( 'AI Visibility Dashboard', 'search-intel' ); ?></strong> — <?php esc_html_e( 'GEO health score, GSC/Bing API, llms.txt tracking', 'search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="search_intel_settings[enable_citation_graph]" value="1" <?php checked( $settings['enable_citation_graph'] ); ?> />
                            <strong><?php esc_html_e( 'Citation Graph', 'search-intel' ); ?></strong> — <?php esc_html_e( 'Link graph, topic clusters, orphaned content detection', 'search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="search_intel_settings[enable_authority_map]" value="1" <?php checked( $settings['enable_authority_map'] ); ?> />
                            <strong><?php esc_html_e( 'Topical Authority Map', 'search-intel' ); ?></strong> — <?php esc_html_e( 'Authority scoring, content gap analysis, topic clusters', 'search-intel' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'AI & Content Intelligence', 'search-intel' ); ?></th>
                    <td>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="search_intel_settings[enable_ai_engine]" value="1" <?php checked( $settings['enable_ai_engine'] ); ?> />
                            <strong><?php esc_html_e( 'AI Engine', 'search-intel' ); ?></strong> — <?php esc_html_e( 'LLM abstraction (OpenAI, Anthropic, Ollama) for bulk generation', 'search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="search_intel_settings[enable_content_decay]" value="1" <?php checked( $settings['enable_content_decay'] ); ?> />
                            <strong><?php esc_html_e( 'Content Decay Detection', 'search-intel' ); ?></strong> — <?php esc_html_e( 'Track score drops, flag declining content, refresh suggestions', 'search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="search_intel_settings[enable_cannibalization]" value="1" <?php checked( $settings['enable_cannibalization'] ); ?> />
                            <strong><?php esc_html_e( 'Keyword Cannibalization', 'search-intel' ); ?></strong> — <?php esc_html_e( 'Detect duplicate keyword targets with action suggestions', 'search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="search_intel_settings[enable_advanced_linking]" value="1" <?php checked( $settings['enable_advanced_linking'] ); ?> />
                            <strong><?php esc_html_e( 'Advanced Internal Linking', 'search-intel' ); ?></strong> — <?php esc_html_e( 'Auto-suggest links, orphan alerts, equity flow visualization', 'search-intel' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Analytics & Reporting', 'search-intel' ); ?></th>
                    <td>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="search_intel_settings[enable_historical_analytics]" value="1" <?php checked( $settings['enable_historical_analytics'] ); ?> />
                            <strong><?php esc_html_e( 'Score Analytics', 'search-intel' ); ?></strong> — <?php esc_html_e( 'Per-post score history, site-wide trends, before/after comparison', 'search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="search_intel_settings[enable_ga4]" value="1" <?php checked( $settings['enable_ga4'] ); ?> />
                            <strong><?php esc_html_e( 'GA4 Integration', 'search-intel' ); ?></strong> — <?php esc_html_e( 'Correlate SEO scores with organic traffic data', 'search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="search_intel_settings[enable_cwv]" value="1" <?php checked( $settings['enable_cwv'] ); ?> />
                            <strong><?php esc_html_e( 'Core Web Vitals', 'search-intel' ); ?></strong> — <?php esc_html_e( 'PageSpeed Insights API, LCP/INP/CLS tracking', 'search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="search_intel_settings[enable_custom_reports]" value="1" <?php checked( $settings['enable_custom_reports'] ); ?> />
                            <strong><?php esc_html_e( 'Custom Reports & Export', 'search-intel' ); ?></strong> — <?php esc_html_e( 'PDF reports, scheduled email, CSV/JSON export', 'search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="search_intel_settings[enable_notifications]" value="1" <?php checked( $settings['enable_notifications'] ); ?> />
                            <strong><?php esc_html_e( 'Notifications & Alerts', 'search-intel' ); ?></strong> — <?php esc_html_e( 'Email digest, score drop alerts, Slack webhooks', 'search-intel' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Enterprise', 'search-intel' ); ?></th>
                    <td>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="search_intel_settings[enable_rbac]" value="1" <?php checked( $settings['enable_rbac'] ); ?> />
                            <strong><?php esc_html_e( 'Role-Based Access Control', 'search-intel' ); ?></strong> — <?php esc_html_e( 'SEO Editor role, per-module permissions, settings lock', 'search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="search_intel_settings[enable_multilang]" value="1" <?php checked( $settings['enable_multilang'] ); ?> />
                            <strong><?php esc_html_e( 'Multi-Language', 'search-intel' ); ?></strong> — <?php esc_html_e( 'WPML/Polylang integration, hreflang, per-language sitemaps', 'search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="search_intel_settings[enable_white_label]" value="1" <?php checked( $settings['enable_white_label'] ); ?> />
                            <strong><?php esc_html_e( 'White-Label', 'search-intel' ); ?></strong> — <?php esc_html_e( 'Custom branding, plugin name, logo, reseller support', 'search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="search_intel_settings[enable_scale]" value="1" <?php checked( $settings['enable_scale'] ); ?> />
                            <strong><?php esc_html_e( 'Scale & Performance', 'search-intel' ); ?></strong> — <?php esc_html_e( 'Object cache, background queue, bulk editor, headless REST', 'search-intel' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Security', 'search-intel' ); ?></th>
                    <td>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="search_intel_settings[enable_audit_log]" value="1" <?php checked( $settings['enable_audit_log'] ); ?> />
                            <strong><?php esc_html_e( 'Audit Log', 'search-intel' ); ?></strong> — <?php esc_html_e( 'Track settings changes with timestamps and user info', 'search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="search_intel_settings[enable_csp_headers]" value="1" <?php checked( $settings['enable_csp_headers'] ); ?> />
                            <strong><?php esc_html_e( 'CSP Headers', 'search-intel' ); ?></strong> — <?php esc_html_e( 'Content Security Policy on plugin endpoints', 'search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="search_intel_settings[enable_sri]" value="1" <?php checked( $settings['enable_sri'] ); ?> />
                            <strong><?php esc_html_e( 'Subresource Integrity', 'search-intel' ); ?></strong> — <?php esc_html_e( 'SRI hashes on admin JS/CSS assets', 'search-intel' ); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <?php submit_button( __( 'Save Settings', 'search-intel' ) ); ?>
    </form>
</div>
