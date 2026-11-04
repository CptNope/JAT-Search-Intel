<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- View template included inside a class method; variables are method-scoped.
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
        <button type="button" class="si-tab si-tab-active" data-tab="seo"><?php esc_html_e( 'SEO', 'jat-search-intel' ); ?></button>
        <button type="button" class="si-tab" data-tab="aeo"><?php esc_html_e( 'AEO', 'jat-search-intel' ); ?></button>
        <button type="button" class="si-tab" data-tab="geo"><?php esc_html_e( 'GEO', 'jat-search-intel' ); ?></button>
        <button type="button" class="si-tab" data-tab="advanced"><?php esc_html_e( 'Advanced', 'jat-search-intel' ); ?></button>
        <button type="button" class="si-tab" data-tab="analysis"><?php esc_html_e( 'Analysis', 'jat-search-intel' ); ?></button>
    </div>

    <!-- SEO Tab -->
    <div class="si-tab-content si-tab-content-active" data-tab="seo">
        <div class="si-field">
            <label for="si_focus_keyword"><?php esc_html_e( 'Focus Keyword', 'jat-search-intel' ); ?></label>
            <input type="text" id="si_focus_keyword" name="si_focus_keyword" value="<?php echo esc_attr( $meta['focus_keyword'] ); ?>" placeholder="<?php esc_attr_e( 'Enter your primary keyword...', 'jat-search-intel' ); ?>" class="widefat" />
        </div>

        <div class="si-field">
            <label for="si_secondary_keywords"><?php esc_html_e( 'Secondary Keywords', 'jat-search-intel' ); ?></label>
            <input type="text" id="si_secondary_keywords" name="si_secondary_keywords" value="<?php echo esc_attr( $meta['secondary_keywords'] ); ?>" placeholder="<?php esc_attr_e( 'keyword two, keyword three, keyword four...', 'jat-search-intel' ); ?>" class="widefat" />
            <p class="description"><?php esc_html_e( 'Comma-separated secondary keywords to track in content analysis.', 'jat-search-intel' ); ?></p>
        </div>

        <div class="si-field">
            <label for="si_title"><?php esc_html_e( 'SEO Title', 'jat-search-intel' ); ?></label>
            <input type="text" id="si_title" name="si_title" value="<?php echo esc_attr( $meta['title'] ); ?>" placeholder="<?php echo esc_attr( get_the_title( $post->ID ) ); ?>" class="widefat" />
            <div class="si-char-count">
                <span id="si-title-count"><?php echo esc_html( strlen( $meta['title'] ?: get_the_title( $post->ID ) ) ); ?></span>/60
            </div>
        </div>

        <!-- SERP Preview -->
        <div class="si-serp-preview">
            <span class="si-serp-label"><?php esc_html_e( 'Search Preview', 'jat-search-intel' ); ?></span>
            <div class="si-serp-title" id="si-serp-title"><?php echo esc_html( $meta['title'] ?: get_the_title( $post->ID ) ); ?></div>
            <div class="si-serp-url"><?php echo esc_url( get_permalink( $post->ID ) ); ?></div>
            <div class="si-serp-desc" id="si-serp-desc"><?php echo esc_html( $meta['description'] ?: wp_trim_words( wp_strip_all_tags( $post->post_content ), 25, '...' ) ); ?></div>
        </div>

        <div class="si-field">
            <label for="si_description"><?php esc_html_e( 'Meta Description', 'jat-search-intel' ); ?></label>
            <textarea id="si_description" name="si_description" rows="3" class="widefat" placeholder="<?php esc_attr_e( 'Enter a compelling meta description...', 'jat-search-intel' ); ?>"><?php echo esc_textarea( $meta['description'] ); ?></textarea>
            <div class="si-char-count">
                <span id="si-desc-count"><?php echo esc_html( strlen( $meta['description'] ) ); ?></span>/160
            </div>
        </div>

        <div class="si-field">
            <label for="si_canonical"><?php esc_html_e( 'Canonical URL', 'jat-search-intel' ); ?></label>
            <input type="url" id="si_canonical" name="si_canonical" value="<?php echo esc_url( $meta['canonical'] ); ?>" placeholder="<?php echo esc_url( get_permalink( $post->ID ) ); ?>" class="widefat" />
        </div>

        <div class="si-field si-field-inline">
            <label>
                <input type="checkbox" name="si_noindex" value="1" <?php checked( $meta['noindex'], '1' ); ?> />
                <?php esc_html_e( 'noindex (exclude from search results)', 'jat-search-intel' ); ?>
            </label>
            <label>
                <input type="checkbox" name="si_nofollow" value="1" <?php checked( $meta['nofollow'], '1' ); ?> />
                <?php esc_html_e( 'nofollow (don\'t follow links on this page)', 'jat-search-intel' ); ?>
            </label>
        </div>

        <hr />
        <h3><?php esc_html_e( 'Social Sharing', 'jat-search-intel' ); ?></h3>
        <p class="description"><?php esc_html_e( 'Override how this post appears when shared on social networks. Leave blank to use the SEO title and description.', 'jat-search-intel' ); ?></p>

        <div class="si-field">
            <label for="si_social_title"><?php esc_html_e( 'Social Title', 'jat-search-intel' ); ?></label>
            <input type="text" id="si_social_title" name="si_social_title" value="<?php echo esc_attr( $meta['social_title'] ); ?>" placeholder="<?php esc_attr_e( 'Custom title for social sharing...', 'jat-search-intel' ); ?>" class="widefat" />
        </div>

        <div class="si-field">
            <label for="si_social_description"><?php esc_html_e( 'Social Description', 'jat-search-intel' ); ?></label>
            <textarea id="si_social_description" name="si_social_description" rows="2" class="widefat" placeholder="<?php esc_attr_e( 'Custom description for social sharing...', 'jat-search-intel' ); ?>"><?php echo esc_textarea( $meta['social_description'] ); ?></textarea>
        </div>

        <div class="si-field">
            <label><?php esc_html_e( 'Social Image', 'jat-search-intel' ); ?></label>
            <div class="si-image-picker">
                <input type="hidden" id="si_social_image" name="si_social_image" value="<?php echo esc_url( $meta['social_image'] ); ?>" />
                <?php if ( $meta['social_image'] ) : ?>
                    <div id="si-social-image-preview" class="si-image-preview">
                        <img src="<?php echo esc_url( $meta['social_image'] ); ?>" alt="" style="max-width:300px; height:auto; border-radius:4px;" />
                    </div>
                <?php else : ?>
                    <div id="si-social-image-preview" class="si-image-preview" style="display:none;">
                        <img src="" alt="" style="max-width:300px; height:auto; border-radius:4px;" />
                    </div>
                <?php endif; ?>
                <div style="margin-top:8px;">
                    <button type="button" class="button" id="si-select-social-image"><?php esc_html_e( 'Select Image', 'jat-search-intel' ); ?></button>
                    <button type="button" class="button si-remove-image" id="si-remove-social-image" <?php echo $meta['social_image'] ? '' : 'style="display:none;"'; ?>><?php esc_html_e( 'Remove', 'jat-search-intel' ); ?></button>
                </div>
                <p class="description"><?php esc_html_e( 'Recommended: 1200x630px. Overrides featured image for OG/Twitter cards.', 'jat-search-intel' ); ?></p>
            </div>
        </div>
    </div>

    <!-- AEO Tab -->
    <div class="si-tab-content" data-tab="aeo">
        <h3><?php esc_html_e( 'FAQ Schema', 'jat-search-intel' ); ?></h3>
        <p class="description"><?php esc_html_e( 'Add FAQ items to generate FAQPage schema. Questions in your content (H2/H3 ending with ?) are also auto-detected.', 'jat-search-intel' ); ?></p>

        <div id="si-faq-repeater">
            <?php
            $faqs = get_post_meta( $post->ID, '_jat_search_intel_faqs', true );
            if ( ! empty( $faqs ) && is_array( $faqs ) ) :
                foreach ( $faqs as $i => $faq ) :
                    ?>
                    <div class="si-faq-item-row" data-index="<?php echo esc_attr( $i ); ?>">
                        <input type="text" class="widefat si-faq-question" placeholder="<?php esc_attr_e( 'Question', 'jat-search-intel' ); ?>" value="<?php echo esc_attr( $faq['question'] ); ?>" />
                        <textarea class="widefat si-faq-answer" rows="2" placeholder="<?php esc_attr_e( 'Answer', 'jat-search-intel' ); ?>"><?php echo esc_textarea( $faq['answer'] ); ?></textarea>
                        <button type="button" class="button si-remove-faq"><?php esc_html_e( 'Remove', 'jat-search-intel' ); ?></button>
                    </div>
                    <?php
                endforeach;
            endif;
            ?>
        </div>
        <div style="display:flex; gap:8px; align-items:center; margin-top:8px;">
            <button type="button" class="button" id="si-add-faq"><?php esc_html_e( '+ Add FAQ Item', 'jat-search-intel' ); ?></button>
            <button type="button" class="button button-primary" id="si-generate-faqs"><?php esc_html_e( 'Generate FAQs', 'jat-search-intel' ); ?></button>
            <?php
            $ai_available = \JAT_Search_Intel\Core::get_instance()->ai_engine->is_configured();
            if ( $ai_available ) : ?>
                <label style="margin-left:4px;">
                    <input type="checkbox" id="si-faq-use-ai" value="1" checked />
                    <?php esc_html_e( 'Enhance with AI', 'jat-search-intel' ); ?>
                </label>
            <?php endif; ?>
            <span id="si-faq-status"></span>
        </div>
        <input type="hidden" name="si_faqs" id="si_faqs" value="<?php echo esc_attr( wp_json_encode( $faqs ?: [] ) ); ?>" />

        <hr />

        <h3><?php esc_html_e( 'Schema Type', 'jat-search-intel' ); ?></h3>
        <div class="si-field">
            <label for="si_schema_type"><?php esc_html_e( 'Content Schema Type', 'jat-search-intel' ); ?></label>
            <select id="si_schema_type" name="si_schema_type" class="widefat">
                <option value=""><?php esc_html_e( '— Default —', 'jat-search-intel' ); ?></option>
                <option value="Article" <?php selected( $meta['schema_type'], 'Article' ); ?>><?php esc_html_e( 'Article', 'jat-search-intel' ); ?></option>
                <option value="NewsArticle" <?php selected( $meta['schema_type'], 'NewsArticle' ); ?>><?php esc_html_e( 'News Article', 'jat-search-intel' ); ?></option>
                <option value="BlogPosting" <?php selected( $meta['schema_type'], 'BlogPosting' ); ?>><?php esc_html_e( 'Blog Post', 'jat-search-intel' ); ?></option>
                <option value="TechArticle" <?php selected( $meta['schema_type'], 'TechArticle' ); ?>><?php esc_html_e( 'Tech Article', 'jat-search-intel' ); ?></option>
                <option value="ScholarlyArticle" <?php selected( $meta['schema_type'], 'ScholarlyArticle' ); ?>><?php esc_html_e( 'Scholarly Article', 'jat-search-intel' ); ?></option>
                <option value="HowTo" <?php selected( $meta['schema_type'], 'HowTo' ); ?>><?php esc_html_e( 'HowTo', 'jat-search-intel' ); ?></option>
                <option value="WebPage" <?php selected( $meta['schema_type'], 'WebPage' ); ?>><?php esc_html_e( 'Web Page', 'jat-search-intel' ); ?></option>
                <option value="FAQPage" <?php selected( $meta['schema_type'], 'FAQPage' ); ?>><?php esc_html_e( 'FAQ Page', 'jat-search-intel' ); ?></option>
                <option value="AboutPage" <?php selected( $meta['schema_type'], 'AboutPage' ); ?>><?php esc_html_e( 'About Page', 'jat-search-intel' ); ?></option>
                <option value="ContactPage" <?php selected( $meta['schema_type'], 'ContactPage' ); ?>><?php esc_html_e( 'Contact Page', 'jat-search-intel' ); ?></option>
            </select>
        </div>

        <hr />
        <h3><?php esc_html_e( 'Direct Answer', 'jat-search-intel' ); ?></h3>
        <div class="si-field">
            <label for="si_direct_answer"><?php esc_html_e( 'What is this post answering?', 'jat-search-intel' ); ?></label>
            <textarea id="si_direct_answer" name="si_direct_answer" rows="2" class="widefat" placeholder="<?php esc_attr_e( 'A concise, direct answer to the main question this post addresses...', 'jat-search-intel' ); ?>"><?php echo esc_textarea( $meta['direct_answer'] ); ?></textarea>
            <p class="description"><?php esc_html_e( 'This is rendered as a meta tag and included in Article schema for featured snippet targeting.', 'jat-search-intel' ); ?></p>
        </div>

        <hr />
        <h3><?php esc_html_e( 'Citations / Sources', 'jat-search-intel' ); ?></h3>
        <p class="description"><?php esc_html_e( 'Add source references to strengthen credibility and enable citation schema output.', 'jat-search-intel' ); ?></p>

        <div id="si-citation-repeater">
            <?php
            $citations = get_post_meta( $post->ID, '_jat_search_intel_citations', true );
            if ( ! empty( $citations ) && is_array( $citations ) ) :
                foreach ( $citations as $i => $cite ) :
                    ?>
                    <div class="si-citation-row" data-index="<?php echo esc_attr( $i ); ?>">
                        <input type="text" class="si-citation-source" placeholder="<?php esc_attr_e( 'Source name', 'jat-search-intel' ); ?>" value="<?php echo esc_attr( $cite['source'] ); ?>" />
                        <input type="url" class="si-citation-url" placeholder="<?php esc_attr_e( 'URL', 'jat-search-intel' ); ?>" value="<?php echo esc_url( $cite['url'] ); ?>" />
                        <input type="date" class="si-citation-date" value="<?php echo esc_attr( $cite['date'] ); ?>" />
                        <input type="text" class="si-citation-quote" placeholder="<?php esc_attr_e( 'Key quote (optional)', 'jat-search-intel' ); ?>" value="<?php echo esc_attr( $cite['quote'] ); ?>" />
                        <button type="button" class="button si-remove-citation"><?php esc_html_e( 'Remove', 'jat-search-intel' ); ?></button>
                    </div>
                    <?php
                endforeach;
            endif;
            ?>
        </div>
        <button type="button" class="button" id="si-add-citation"><?php esc_html_e( '+ Add Citation', 'jat-search-intel' ); ?></button>
        <input type="hidden" name="si_citations" id="si_citations" value="<?php echo esc_attr( wp_json_encode( $citations ?: [] ) ); ?>" />
    </div>

    <!-- GEO Tab -->
    <div class="si-tab-content" data-tab="geo">
        <div class="si-field">
            <label for="si_ai_summary"><?php esc_html_e( 'AI Summary', 'jat-search-intel' ); ?></label>
            <textarea id="si_ai_summary" name="si_ai_summary" rows="3" class="widefat" placeholder="<?php esc_attr_e( 'Write a concise summary optimized for AI/LLM consumption...', 'jat-search-intel' ); ?>"><?php echo esc_textarea( $meta['ai_summary'] ); ?></textarea>
            <p class="description"><?php esc_html_e( 'A clear, factual summary that AI systems can use to understand and cite your content.', 'jat-search-intel' ); ?></p>
        </div>

        <div class="si-field">
            <label for="si_topics"><?php esc_html_e( 'Key Topics / Entities', 'jat-search-intel' ); ?></label>
            <?php $topics = get_post_meta( $post->ID, '_jat_search_intel_topics', true ); ?>
            <input type="text" id="si_topics" name="si_topics" value="<?php echo esc_attr( is_array( $topics ) ? implode( ', ', $topics ) : '' ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'SEO, Content Marketing, Google Algorithm...', 'jat-search-intel' ); ?>" />
            <p class="description"><?php esc_html_e( 'Comma-separated list of key topics and entities in this content.', 'jat-search-intel' ); ?></p>
        </div>

        <div class="si-field">
            <label for="si_expertise_level"><?php esc_html_e( 'Expertise Level', 'jat-search-intel' ); ?></label>
            <select id="si_expertise_level" name="si_expertise_level" class="widefat">
                <option value=""><?php esc_html_e( '— Auto-detect —', 'jat-search-intel' ); ?></option>
                <option value="beginner" <?php selected( $meta['expertise_level'], 'beginner' ); ?>><?php esc_html_e( 'Beginner', 'jat-search-intel' ); ?></option>
                <option value="intermediate" <?php selected( $meta['expertise_level'], 'intermediate' ); ?>><?php esc_html_e( 'Intermediate', 'jat-search-intel' ); ?></option>
                <option value="advanced" <?php selected( $meta['expertise_level'], 'advanced' ); ?>><?php esc_html_e( 'Advanced', 'jat-search-intel' ); ?></option>
                <option value="expert" <?php selected( $meta['expertise_level'], 'expert' ); ?>><?php esc_html_e( 'Expert', 'jat-search-intel' ); ?></option>
            </select>
        </div>

        <div class="si-field">
            <label for="si_content_type"><?php esc_html_e( 'Content Type', 'jat-search-intel' ); ?></label>
            <select id="si_content_type" name="si_content_type" class="widefat">
                <option value=""><?php esc_html_e( '— Auto-detect —', 'jat-search-intel' ); ?></option>
                <option value="article" <?php selected( $meta['content_type'], 'article' ); ?>><?php esc_html_e( 'Article', 'jat-search-intel' ); ?></option>
                <option value="long-form-guide" <?php selected( $meta['content_type'], 'long-form-guide' ); ?>><?php esc_html_e( 'Long-Form Guide', 'jat-search-intel' ); ?></option>
                <option value="how-to" <?php selected( $meta['content_type'], 'how-to' ); ?>><?php esc_html_e( 'How-To', 'jat-search-intel' ); ?></option>
                <option value="faq" <?php selected( $meta['content_type'], 'faq' ); ?>><?php esc_html_e( 'FAQ', 'jat-search-intel' ); ?></option>
                <option value="comparison" <?php selected( $meta['content_type'], 'comparison' ); ?>><?php esc_html_e( 'Comparison', 'jat-search-intel' ); ?></option>
                <option value="review" <?php selected( $meta['content_type'], 'review' ); ?>><?php esc_html_e( 'Review', 'jat-search-intel' ); ?></option>
                <option value="informational" <?php selected( $meta['content_type'], 'informational' ); ?>><?php esc_html_e( 'Informational', 'jat-search-intel' ); ?></option>
                <option value="news" <?php selected( $meta['content_type'], 'news' ); ?>><?php esc_html_e( 'News', 'jat-search-intel' ); ?></option>
            </select>
        </div>

        <div class="si-field">
            <label for="si_content_intent"><?php esc_html_e( 'Content Intent', 'jat-search-intel' ); ?></label>
            <select id="si_content_intent" name="si_content_intent" class="widefat">
                <option value=""><?php esc_html_e( '— Select —', 'jat-search-intel' ); ?></option>
                <option value="informational" <?php selected( $meta['content_intent'], 'informational' ); ?>><?php esc_html_e( 'Informational', 'jat-search-intel' ); ?></option>
                <option value="commercial" <?php selected( $meta['content_intent'], 'commercial' ); ?>><?php esc_html_e( 'Commercial', 'jat-search-intel' ); ?></option>
                <option value="navigational" <?php selected( $meta['content_intent'], 'navigational' ); ?>><?php esc_html_e( 'Navigational', 'jat-search-intel' ); ?></option>
                <option value="educational" <?php selected( $meta['content_intent'], 'educational' ); ?>><?php esc_html_e( 'Educational', 'jat-search-intel' ); ?></option>
                <option value="transactional" <?php selected( $meta['content_intent'], 'transactional' ); ?>><?php esc_html_e( 'Transactional', 'jat-search-intel' ); ?></option>
            </select>
            <p class="description"><?php esc_html_e( 'The primary search intent this content addresses. Used in meta tags and JSON-LD.', 'jat-search-intel' ); ?></p>
        </div>

        <div class="si-field">
            <label for="si_audience"><?php esc_html_e( 'Target Audience', 'jat-search-intel' ); ?></label>
            <select id="si_audience" name="si_audience" class="widefat">
                <option value=""><?php esc_html_e( '— Select —', 'jat-search-intel' ); ?></option>
                <option value="beginner" <?php selected( $meta['audience'], 'beginner' ); ?>><?php esc_html_e( 'Beginner', 'jat-search-intel' ); ?></option>
                <option value="intermediate" <?php selected( $meta['audience'], 'intermediate' ); ?>><?php esc_html_e( 'Intermediate', 'jat-search-intel' ); ?></option>
                <option value="advanced" <?php selected( $meta['audience'], 'advanced' ); ?>><?php esc_html_e( 'Advanced', 'jat-search-intel' ); ?></option>
                <option value="professional" <?php selected( $meta['audience'], 'professional' ); ?>><?php esc_html_e( 'Professional', 'jat-search-intel' ); ?></option>
                <option value="general" <?php selected( $meta['audience'], 'general' ); ?>><?php esc_html_e( 'General', 'jat-search-intel' ); ?></option>
            </select>
            <p class="description"><?php esc_html_e( 'Who this content is written for. Rendered in schema.org/audience.', 'jat-search-intel' ); ?></p>
        </div>

        <div class="si-field">
            <label for="si_author_expertise_notes"><?php esc_html_e( 'Author Expertise Notes', 'jat-search-intel' ); ?></label>
            <textarea id="si_author_expertise_notes" name="si_author_expertise_notes" rows="2" class="widefat" placeholder="<?php esc_attr_e( 'Why is the author qualified to write this? e.g., 10 years in SEO, certified Google Ads specialist...', 'jat-search-intel' ); ?>"><?php echo esc_textarea( $meta['author_expertise_notes'] ); ?></textarea>
            <p class="description"><?php esc_html_e( 'Per-post author authority context. Supplements the global E-E-A-T author profile.', 'jat-search-intel' ); ?></p>
        </div>

        <div class="si-field">
            <label for="si_fact_checked"><?php esc_html_e( 'Fact-Check Date', 'jat-search-intel' ); ?></label>
            <input type="date" id="si_fact_checked" name="si_fact_checked" value="<?php echo esc_attr( $meta['fact_checked'] ); ?>" class="widefat" />
            <p class="description"><?php esc_html_e( 'Date this content was last fact-checked. Signals trust to AI engines.', 'jat-search-intel' ); ?></p>
        </div>

        <h3><?php esc_html_e( 'Entity Markup', 'jat-search-intel' ); ?></h3>
        <p class="description"><?php esc_html_e( 'Define named entities in your content for semantic clarity.', 'jat-search-intel' ); ?></p>

        <div id="si-entity-repeater">
            <?php
            $entities = get_post_meta( $post->ID, '_jat_search_intel_entities', true );
            if ( ! empty( $entities ) && is_array( $entities ) ) :
                foreach ( $entities as $i => $entity ) :
                    ?>
                    <div class="si-entity-row" data-index="<?php echo esc_attr( $i ); ?>">
                        <input type="text" class="si-entity-name" placeholder="<?php esc_attr_e( 'Entity name', 'jat-search-intel' ); ?>" value="<?php echo esc_attr( $entity['name'] ); ?>" />
                        <select class="si-entity-type">
                            <option value="Person" <?php selected( $entity['type'], 'Person' ); ?>>Person</option>
                            <option value="Organization" <?php selected( $entity['type'], 'Organization' ); ?>>Organization</option>
                            <option value="Place" <?php selected( $entity['type'], 'Place' ); ?>>Place</option>
                            <option value="Product" <?php selected( $entity['type'], 'Product' ); ?>>Product</option>
                            <option value="Event" <?php selected( $entity['type'], 'Event' ); ?>>Event</option>
                            <option value="Thing" <?php selected( $entity['type'], 'Thing' ); ?>>Thing</option>
                        </select>
                        <input type="url" class="si-entity-url" placeholder="<?php esc_attr_e( 'URL (optional)', 'jat-search-intel' ); ?>" value="<?php echo esc_url( $entity['url'] ); ?>" />
                        <button type="button" class="button si-remove-entity"><?php esc_html_e( 'Remove', 'jat-search-intel' ); ?></button>
                    </div>
                    <?php
                endforeach;
            endif;
            ?>
        </div>
        <button type="button" class="button" id="si-add-entity"><?php esc_html_e( '+ Add Entity', 'jat-search-intel' ); ?></button>
        <input type="hidden" name="si_entities" id="si_entities" value="<?php echo esc_attr( wp_json_encode( $entities ?: [] ) ); ?>" />

        <hr />
        <h3><?php esc_html_e( 'Local Relevance', 'jat-search-intel' ); ?></h3>
        <div class="si-field si-field-inline">
            <label>
                <input type="checkbox" name="si_local_relevance" id="si_local_relevance" value="1" <?php checked( $meta['local_relevance'], '1' ); ?> />
                <?php esc_html_e( 'This content has local/geographic relevance', 'jat-search-intel' ); ?>
            </label>
        </div>

        <div class="si-field" id="si-local-location-field" <?php echo empty( $meta['local_relevance'] ) ? 'style="display:none;"' : ''; ?>>
            <label for="si_local_location"><?php esc_html_e( 'Location', 'jat-search-intel' ); ?></label>
            <input type="text" id="si_local_location" name="si_local_location" value="<?php echo esc_attr( $meta['local_location'] ); ?>" placeholder="<?php esc_attr_e( 'e.g., New York, NY or London, UK', 'jat-search-intel' ); ?>" class="widefat" />
            <p class="description"><?php esc_html_e( 'Outputs contentLocation with @type: Place in JSON-LD schema.', 'jat-search-intel' ); ?></p>
        </div>
    </div>

    <!-- Advanced Tab -->
    <div class="si-tab-content" data-tab="advanced">
        <div class="si-field si-field-inline">
            <label>
                <input type="checkbox" name="si_ai_noindex" value="1" <?php checked( $meta['ai_noindex'], '1' ); ?> />
                <?php esc_html_e( 'Block AI crawlers (GPTBot, Google-Extended, etc.)', 'jat-search-intel' ); ?>
            </label>
        </div>

        <p class="description"><?php esc_html_e( 'Prevent AI bots from indexing this specific page. Useful for gated or premium content.', 'jat-search-intel' ); ?></p>
    </div>

    <!-- Analysis Tab -->
    <div class="si-tab-content" data-tab="analysis">
        <div class="si-analysis-scores" id="si-analysis-scores">
            <div class="si-score-ring" id="si-seo-ring">
                <div class="si-score-value"><?php echo esc_html( get_post_meta( $post->ID, '_jat_search_intel_seo_score', true ) ?: '—' ); ?></div>
                <div class="si-score-label"><?php esc_html_e( 'SEO', 'jat-search-intel' ); ?></div>
            </div>
            <div class="si-score-ring" id="si-aeo-ring">
                <div class="si-score-value"><?php echo esc_html( get_post_meta( $post->ID, '_jat_search_intel_aeo_score', true ) ?: '—' ); ?></div>
                <div class="si-score-label"><?php esc_html_e( 'AEO', 'jat-search-intel' ); ?></div>
            </div>
            <div class="si-score-ring" id="si-geo-ring">
                <div class="si-score-value"><?php echo esc_html( get_post_meta( $post->ID, '_jat_search_intel_geo_score', true ) ?: '—' ); ?></div>
                <div class="si-score-label"><?php esc_html_e( 'GEO', 'jat-search-intel' ); ?></div>
            </div>
            <div class="si-score-ring" id="si-readability-ring">
                <div class="si-score-value"><?php echo esc_html( get_post_meta( $post->ID, '_jat_search_intel_readability_score', true ) ?: '—' ); ?></div>
                <div class="si-score-label"><?php esc_html_e( 'Readability', 'jat-search-intel' ); ?></div>
            </div>
        </div>

        <div style="display:flex; gap:8px; align-items:center; margin:12px 0;">
            <button type="button" class="button button-primary" id="si-run-analysis"><?php esc_html_e( 'Run Analysis', 'jat-search-intel' ); ?></button>
            <?php if ( \JAT_Search_Intel\Core::get_instance()->ai_engine->is_configured() ) : ?>
                <button type="button" class="button" id="si-ai-suggestions"><?php esc_html_e( 'AI Suggest Edits', 'jat-search-intel' ); ?></button>
            <?php endif; ?>
        </div>

        <div id="si-analysis-details" class="si-analysis-details" style="display:none;">
            <div class="si-analysis-section" id="si-seo-checks"></div>
            <div class="si-analysis-section" id="si-aeo-checks"></div>
            <div class="si-analysis-section" id="si-geo-checks"></div>
        </div>

        <!-- AI Suggestions -->
        <div id="si-ai-suggestions-panel" style="display:none; margin-top:12px;">
            <h4><?php esc_html_e( 'AI Suggestions', 'jat-search-intel' ); ?></h4>
            <div id="si-ai-suggestions-list"></div>
        </div>

        <!-- Score History -->
        <?php
        $history = get_post_meta( $post->ID, '_jat_search_intel_analytics_history', true );
        if ( ! empty( $history ) && is_array( $history ) ) :
            $recent = array_slice( $history, -10 );
        ?>
        <hr />
        <h4><?php esc_html_e( 'Score History', 'jat-search-intel' ); ?></h4>
        <table class="widefat striped" style="margin-top:8px;">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Date', 'jat-search-intel' ); ?></th>
                    <th><?php esc_html_e( 'SEO', 'jat-search-intel' ); ?></th>
                    <th><?php esc_html_e( 'AEO', 'jat-search-intel' ); ?></th>
                    <th><?php esc_html_e( 'GEO', 'jat-search-intel' ); ?></th>
                    <th><?php esc_html_e( 'Read.', 'jat-search-intel' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( array_reverse( $recent ) as $entry ) : ?>
                <tr>
                    <td><?php echo esc_html( $entry['date'] ?? '—' ); ?></td>
                    <td><?php echo esc_html( $entry['seo'] ?? '—' ); ?></td>
                    <td><?php echo esc_html( $entry['aeo'] ?? '—' ); ?></td>
                    <td><?php echo esc_html( $entry['geo'] ?? '—' ); ?></td>
                    <td><?php echo esc_html( $entry['readability'] ?? '—' ); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

</div>
