<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Metabox template for the post editor.
 *
 * @var array $meta  Post meta values.
 * @var WP_Post $post The current post object.
 */
?>
<div class="si-metabox" id="si-metabox">

    <!-- Tab Navigation -->
    <div class="si-tabs">
        <button type="button" class="si-tab si-tab-active" data-tab="seo"><?php esc_html_e( 'SEO', 'search-intel' ); ?></button>
        <button type="button" class="si-tab" data-tab="aeo"><?php esc_html_e( 'AEO', 'search-intel' ); ?></button>
        <button type="button" class="si-tab" data-tab="geo"><?php esc_html_e( 'GEO', 'search-intel' ); ?></button>
        <button type="button" class="si-tab" data-tab="advanced"><?php esc_html_e( 'Advanced', 'search-intel' ); ?></button>
        <button type="button" class="si-tab" data-tab="analysis"><?php esc_html_e( 'Analysis', 'search-intel' ); ?></button>
    </div>

    <!-- SEO Tab -->
    <div class="si-tab-content si-tab-content-active" data-tab="seo">
        <div class="si-field">
            <label for="si_focus_keyword"><?php esc_html_e( 'Focus Keyword', 'search-intel' ); ?></label>
            <input type="text" id="si_focus_keyword" name="si_focus_keyword" value="<?php echo esc_attr( $meta['focus_keyword'] ); ?>" placeholder="<?php esc_attr_e( 'Enter your primary keyword...', 'search-intel' ); ?>" class="widefat" />
        </div>

        <div class="si-field">
            <label for="si_title"><?php esc_html_e( 'SEO Title', 'search-intel' ); ?></label>
            <input type="text" id="si_title" name="si_title" value="<?php echo esc_attr( $meta['title'] ); ?>" placeholder="<?php echo esc_attr( get_the_title( $post->ID ) ); ?>" class="widefat" />
            <div class="si-char-count">
                <span id="si-title-count"><?php echo esc_html( strlen( $meta['title'] ?: get_the_title( $post->ID ) ) ); ?></span>/60
            </div>
        </div>

        <!-- SERP Preview -->
        <div class="si-serp-preview">
            <span class="si-serp-label"><?php esc_html_e( 'Search Preview', 'search-intel' ); ?></span>
            <div class="si-serp-title" id="si-serp-title"><?php echo esc_html( $meta['title'] ?: get_the_title( $post->ID ) ); ?></div>
            <div class="si-serp-url"><?php echo esc_url( get_permalink( $post->ID ) ); ?></div>
            <div class="si-serp-desc" id="si-serp-desc"><?php echo esc_html( $meta['description'] ?: wp_trim_words( wp_strip_all_tags( $post->post_content ), 25, '...' ) ); ?></div>
        </div>

        <div class="si-field">
            <label for="si_description"><?php esc_html_e( 'Meta Description', 'search-intel' ); ?></label>
            <textarea id="si_description" name="si_description" rows="3" class="widefat" placeholder="<?php esc_attr_e( 'Enter a compelling meta description...', 'search-intel' ); ?>"><?php echo esc_textarea( $meta['description'] ); ?></textarea>
            <div class="si-char-count">
                <span id="si-desc-count"><?php echo esc_html( strlen( $meta['description'] ) ); ?></span>/160
            </div>
        </div>

        <div class="si-field">
            <label for="si_canonical"><?php esc_html_e( 'Canonical URL', 'search-intel' ); ?></label>
            <input type="url" id="si_canonical" name="si_canonical" value="<?php echo esc_url( $meta['canonical'] ); ?>" placeholder="<?php echo esc_url( get_permalink( $post->ID ) ); ?>" class="widefat" />
        </div>

        <div class="si-field si-field-inline">
            <label>
                <input type="checkbox" name="si_noindex" value="1" <?php checked( $meta['noindex'], '1' ); ?> />
                <?php esc_html_e( 'noindex (exclude from search results)', 'search-intel' ); ?>
            </label>
            <label>
                <input type="checkbox" name="si_nofollow" value="1" <?php checked( $meta['nofollow'], '1' ); ?> />
                <?php esc_html_e( 'nofollow (don\'t follow links on this page)', 'search-intel' ); ?>
            </label>
        </div>
    </div>

    <!-- AEO Tab -->
    <div class="si-tab-content" data-tab="aeo">
        <h3><?php esc_html_e( 'FAQ Schema', 'search-intel' ); ?></h3>
        <p class="description"><?php esc_html_e( 'Add FAQ items to generate FAQPage schema. Questions in your content (H2/H3 ending with ?) are also auto-detected.', 'search-intel' ); ?></p>

        <div id="si-faq-repeater">
            <?php
            $faqs = get_post_meta( $post->ID, '_search_intel_faqs', true );
            if ( ! empty( $faqs ) && is_array( $faqs ) ) :
                foreach ( $faqs as $i => $faq ) :
                    ?>
                    <div class="si-faq-item-row" data-index="<?php echo esc_attr( $i ); ?>">
                        <input type="text" class="widefat si-faq-question" placeholder="<?php esc_attr_e( 'Question', 'search-intel' ); ?>" value="<?php echo esc_attr( $faq['question'] ); ?>" />
                        <textarea class="widefat si-faq-answer" rows="2" placeholder="<?php esc_attr_e( 'Answer', 'search-intel' ); ?>"><?php echo esc_textarea( $faq['answer'] ); ?></textarea>
                        <button type="button" class="button si-remove-faq"><?php esc_html_e( 'Remove', 'search-intel' ); ?></button>
                    </div>
                    <?php
                endforeach;
            endif;
            ?>
        </div>
        <button type="button" class="button" id="si-add-faq"><?php esc_html_e( '+ Add FAQ Item', 'search-intel' ); ?></button>
        <input type="hidden" name="si_faqs" id="si_faqs" value="<?php echo esc_attr( wp_json_encode( $faqs ?: [] ) ); ?>" />

        <hr />

        <h3><?php esc_html_e( 'Schema Type', 'search-intel' ); ?></h3>
        <div class="si-field">
            <label for="si_schema_type"><?php esc_html_e( 'Content Schema Type', 'search-intel' ); ?></label>
            <select id="si_schema_type" name="si_schema_type" class="widefat">
                <option value=""><?php esc_html_e( '— Default —', 'search-intel' ); ?></option>
                <option value="Article" <?php selected( $meta['schema_type'], 'Article' ); ?>><?php esc_html_e( 'Article', 'search-intel' ); ?></option>
                <option value="NewsArticle" <?php selected( $meta['schema_type'], 'NewsArticle' ); ?>><?php esc_html_e( 'News Article', 'search-intel' ); ?></option>
                <option value="BlogPosting" <?php selected( $meta['schema_type'], 'BlogPosting' ); ?>><?php esc_html_e( 'Blog Post', 'search-intel' ); ?></option>
                <option value="TechArticle" <?php selected( $meta['schema_type'], 'TechArticle' ); ?>><?php esc_html_e( 'Tech Article', 'search-intel' ); ?></option>
                <option value="ScholarlyArticle" <?php selected( $meta['schema_type'], 'ScholarlyArticle' ); ?>><?php esc_html_e( 'Scholarly Article', 'search-intel' ); ?></option>
                <option value="HowTo" <?php selected( $meta['schema_type'], 'HowTo' ); ?>><?php esc_html_e( 'HowTo', 'search-intel' ); ?></option>
                <option value="WebPage" <?php selected( $meta['schema_type'], 'WebPage' ); ?>><?php esc_html_e( 'Web Page', 'search-intel' ); ?></option>
                <option value="FAQPage" <?php selected( $meta['schema_type'], 'FAQPage' ); ?>><?php esc_html_e( 'FAQ Page', 'search-intel' ); ?></option>
                <option value="AboutPage" <?php selected( $meta['schema_type'], 'AboutPage' ); ?>><?php esc_html_e( 'About Page', 'search-intel' ); ?></option>
                <option value="ContactPage" <?php selected( $meta['schema_type'], 'ContactPage' ); ?>><?php esc_html_e( 'Contact Page', 'search-intel' ); ?></option>
            </select>
        </div>
    </div>

    <!-- GEO Tab -->
    <div class="si-tab-content" data-tab="geo">
        <div class="si-field">
            <label for="si_ai_summary"><?php esc_html_e( 'AI Summary', 'search-intel' ); ?></label>
            <textarea id="si_ai_summary" name="si_ai_summary" rows="3" class="widefat" placeholder="<?php esc_attr_e( 'Write a concise summary optimized for AI/LLM consumption...', 'search-intel' ); ?>"><?php echo esc_textarea( $meta['ai_summary'] ); ?></textarea>
            <p class="description"><?php esc_html_e( 'A clear, factual summary that AI systems can use to understand and cite your content.', 'search-intel' ); ?></p>
        </div>

        <div class="si-field">
            <label for="si_topics"><?php esc_html_e( 'Key Topics / Entities', 'search-intel' ); ?></label>
            <?php $topics = get_post_meta( $post->ID, '_search_intel_topics', true ); ?>
            <input type="text" id="si_topics" name="si_topics" value="<?php echo esc_attr( is_array( $topics ) ? implode( ', ', $topics ) : '' ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'SEO, Content Marketing, Google Algorithm...', 'search-intel' ); ?>" />
            <p class="description"><?php esc_html_e( 'Comma-separated list of key topics and entities in this content.', 'search-intel' ); ?></p>
        </div>

        <div class="si-field">
            <label for="si_expertise_level"><?php esc_html_e( 'Expertise Level', 'search-intel' ); ?></label>
            <select id="si_expertise_level" name="si_expertise_level" class="widefat">
                <option value=""><?php esc_html_e( '— Auto-detect —', 'search-intel' ); ?></option>
                <option value="beginner" <?php selected( $meta['expertise_level'], 'beginner' ); ?>><?php esc_html_e( 'Beginner', 'search-intel' ); ?></option>
                <option value="intermediate" <?php selected( $meta['expertise_level'], 'intermediate' ); ?>><?php esc_html_e( 'Intermediate', 'search-intel' ); ?></option>
                <option value="advanced" <?php selected( $meta['expertise_level'], 'advanced' ); ?>><?php esc_html_e( 'Advanced', 'search-intel' ); ?></option>
                <option value="expert" <?php selected( $meta['expertise_level'], 'expert' ); ?>><?php esc_html_e( 'Expert', 'search-intel' ); ?></option>
            </select>
        </div>

        <div class="si-field">
            <label for="si_content_type"><?php esc_html_e( 'Content Type', 'search-intel' ); ?></label>
            <select id="si_content_type" name="si_content_type" class="widefat">
                <option value=""><?php esc_html_e( '— Auto-detect —', 'search-intel' ); ?></option>
                <option value="article" <?php selected( $meta['content_type'], 'article' ); ?>><?php esc_html_e( 'Article', 'search-intel' ); ?></option>
                <option value="long-form-guide" <?php selected( $meta['content_type'], 'long-form-guide' ); ?>><?php esc_html_e( 'Long-Form Guide', 'search-intel' ); ?></option>
                <option value="how-to" <?php selected( $meta['content_type'], 'how-to' ); ?>><?php esc_html_e( 'How-To', 'search-intel' ); ?></option>
                <option value="faq" <?php selected( $meta['content_type'], 'faq' ); ?>><?php esc_html_e( 'FAQ', 'search-intel' ); ?></option>
                <option value="comparison" <?php selected( $meta['content_type'], 'comparison' ); ?>><?php esc_html_e( 'Comparison', 'search-intel' ); ?></option>
                <option value="review" <?php selected( $meta['content_type'], 'review' ); ?>><?php esc_html_e( 'Review', 'search-intel' ); ?></option>
                <option value="informational" <?php selected( $meta['content_type'], 'informational' ); ?>><?php esc_html_e( 'Informational', 'search-intel' ); ?></option>
                <option value="news" <?php selected( $meta['content_type'], 'news' ); ?>><?php esc_html_e( 'News', 'search-intel' ); ?></option>
            </select>
        </div>

        <div class="si-field">
            <label for="si_fact_checked"><?php esc_html_e( 'Fact-Check Date', 'search-intel' ); ?></label>
            <input type="date" id="si_fact_checked" name="si_fact_checked" value="<?php echo esc_attr( $meta['fact_checked'] ); ?>" class="widefat" />
            <p class="description"><?php esc_html_e( 'Date this content was last fact-checked. Signals trust to AI engines.', 'search-intel' ); ?></p>
        </div>

        <h3><?php esc_html_e( 'Entity Markup', 'search-intel' ); ?></h3>
        <p class="description"><?php esc_html_e( 'Define named entities in your content for semantic clarity.', 'search-intel' ); ?></p>

        <div id="si-entity-repeater">
            <?php
            $entities = get_post_meta( $post->ID, '_search_intel_entities', true );
            if ( ! empty( $entities ) && is_array( $entities ) ) :
                foreach ( $entities as $i => $entity ) :
                    ?>
                    <div class="si-entity-row" data-index="<?php echo esc_attr( $i ); ?>">
                        <input type="text" class="si-entity-name" placeholder="<?php esc_attr_e( 'Entity name', 'search-intel' ); ?>" value="<?php echo esc_attr( $entity['name'] ); ?>" />
                        <select class="si-entity-type">
                            <option value="Person" <?php selected( $entity['type'], 'Person' ); ?>>Person</option>
                            <option value="Organization" <?php selected( $entity['type'], 'Organization' ); ?>>Organization</option>
                            <option value="Place" <?php selected( $entity['type'], 'Place' ); ?>>Place</option>
                            <option value="Product" <?php selected( $entity['type'], 'Product' ); ?>>Product</option>
                            <option value="Event" <?php selected( $entity['type'], 'Event' ); ?>>Event</option>
                            <option value="Thing" <?php selected( $entity['type'], 'Thing' ); ?>>Thing</option>
                        </select>
                        <input type="url" class="si-entity-url" placeholder="<?php esc_attr_e( 'URL (optional)', 'search-intel' ); ?>" value="<?php echo esc_url( $entity['url'] ); ?>" />
                        <button type="button" class="button si-remove-entity"><?php esc_html_e( 'Remove', 'search-intel' ); ?></button>
                    </div>
                    <?php
                endforeach;
            endif;
            ?>
        </div>
        <button type="button" class="button" id="si-add-entity"><?php esc_html_e( '+ Add Entity', 'search-intel' ); ?></button>
        <input type="hidden" name="si_entities" id="si_entities" value="<?php echo esc_attr( wp_json_encode( $entities ?: [] ) ); ?>" />
    </div>

    <!-- Advanced Tab -->
    <div class="si-tab-content" data-tab="advanced">
        <div class="si-field si-field-inline">
            <label>
                <input type="checkbox" name="si_ai_noindex" value="1" <?php checked( $meta['ai_noindex'], '1' ); ?> />
                <?php esc_html_e( 'Block AI crawlers (GPTBot, Google-Extended, etc.)', 'search-intel' ); ?>
            </label>
        </div>

        <p class="description"><?php esc_html_e( 'Prevent AI bots from indexing this specific page. Useful for gated or premium content.', 'search-intel' ); ?></p>
    </div>

    <!-- Analysis Tab -->
    <div class="si-tab-content" data-tab="analysis">
        <div class="si-analysis-scores" id="si-analysis-scores">
            <div class="si-score-ring" id="si-seo-ring">
                <div class="si-score-value">—</div>
                <div class="si-score-label"><?php esc_html_e( 'SEO', 'search-intel' ); ?></div>
            </div>
            <div class="si-score-ring" id="si-aeo-ring">
                <div class="si-score-value">—</div>
                <div class="si-score-label"><?php esc_html_e( 'AEO', 'search-intel' ); ?></div>
            </div>
            <div class="si-score-ring" id="si-geo-ring">
                <div class="si-score-value">—</div>
                <div class="si-score-label"><?php esc_html_e( 'GEO', 'search-intel' ); ?></div>
            </div>
            <div class="si-score-ring" id="si-readability-ring">
                <div class="si-score-value">—</div>
                <div class="si-score-label"><?php esc_html_e( 'Readability', 'search-intel' ); ?></div>
            </div>
        </div>

        <button type="button" class="button button-primary" id="si-run-analysis"><?php esc_html_e( 'Run Analysis', 'search-intel' ); ?></button>

        <div id="si-analysis-details" class="si-analysis-details" style="display:none;">
            <div class="si-analysis-section" id="si-seo-checks"></div>
            <div class="si-analysis-section" id="si-aeo-checks"></div>
            <div class="si-analysis-section" id="si-geo-checks"></div>
        </div>
    </div>

</div>
