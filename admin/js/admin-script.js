/**
 * JAT Search Intel — Admin Script
 * Handles metabox tabs, SERP preview, FAQ/Entity repeaters, and live analysis.
 */
(function ($) {
    'use strict';

    const SI = window.jatSearchIntel || {};

    /* ========== Tab Navigation ========== */

    $(document).on('click', '.si-tab', function () {
        const tab = $(this).data('tab');
        $('.si-tab').removeClass('si-tab-active');
        $(this).addClass('si-tab-active');
        $('.si-tab-content').removeClass('si-tab-content-active');
        $('.si-tab-content[data-tab="' + tab + '"]').addClass('si-tab-content-active');
    });

    /* ========== SERP Preview ========== */

    function updateSerpPreview() {
        const title = $('#si_title').val() || $('#title').val() || $('input[name="post_title"]').val() || '';
        const desc = $('#si_description').val() || '';

        $('#si-serp-title').text(title.substring(0, 60) || 'Page Title');
        $('#si-serp-desc').text(desc.substring(0, 160) || 'Add a meta description to see the preview...');
    }

    function updateCharCounts() {
        const titleLen = ($('#si_title').val() || '').length;
        const descLen = ($('#si_description').val() || '').length;

        const $titleCount = $('#si-title-count');
        const $descCount = $('#si-desc-count');

        $titleCount.text(titleLen);
        $descCount.text(descLen);

        // Color coding
        $titleCount.toggleClass('si-char-warn', titleLen > 60);
        $descCount.toggleClass('si-char-warn', descLen > 160);
    }

    $(document).on('input', '#si_title, #si_description', function () {
        updateSerpPreview();
        updateCharCounts();
    });

    // Also watch the WP title field
    $(document).on('input', '#title, input[name="post_title"]', function () {
        if (!$('#si_title').val()) {
            updateSerpPreview();
        }
    });

    /* ========== FAQ Repeater ========== */

    function updateFaqsJson() {
        const faqs = [];
        $('.si-faq-item-row').each(function () {
            const question = $(this).find('.si-faq-question').val();
            const answer = $(this).find('.si-faq-answer').val();
            if (question || answer) {
                faqs.push({ question: question, answer: answer });
            }
        });
        $('#si_faqs').val(JSON.stringify(faqs));
    }

    $(document).on('click', '#si-add-faq', function () {
        const index = $('.si-faq-item-row').length;
        const html = `
            <div class="si-faq-item-row" data-index="${index}">
                <input type="text" class="widefat si-faq-question" placeholder="Question" value="" />
                <textarea class="widefat si-faq-answer" rows="2" placeholder="Answer"></textarea>
                <button type="button" class="button si-remove-faq">Remove</button>
            </div>
        `;
        $('#si-faq-repeater').append(html);
    });

    $(document).on('click', '.si-remove-faq', function () {
        $(this).closest('.si-faq-item-row').remove();
        updateFaqsJson();
    });

    $(document).on('input', '.si-faq-question, .si-faq-answer', function () {
        updateFaqsJson();
    });

    /* ========== Entity Repeater ========== */

    function updateEntitiesJson() {
        const entities = [];
        $('.si-entity-row').each(function () {
            const name = $(this).find('.si-entity-name').val();
            const type = $(this).find('.si-entity-type').val();
            const url = $(this).find('.si-entity-url').val();
            if (name) {
                entities.push({ name: name, type: type, url: url });
            }
        });
        $('#si_entities').val(JSON.stringify(entities));
    }

    $(document).on('click', '#si-add-entity', function () {
        const index = $('.si-entity-row').length;
        const html = `
            <div class="si-entity-row" data-index="${index}">
                <input type="text" class="si-entity-name" placeholder="Entity name" value="" />
                <select class="si-entity-type">
                    <option value="Person">Person</option>
                    <option value="Organization">Organization</option>
                    <option value="Place">Place</option>
                    <option value="Product">Product</option>
                    <option value="Event">Event</option>
                    <option value="Thing">Thing</option>
                </select>
                <input type="url" class="si-entity-url" placeholder="URL (optional)" value="" />
                <button type="button" class="button si-remove-entity">Remove</button>
            </div>
        `;
        $('#si-entity-repeater').append(html);
    });

    $(document).on('click', '.si-remove-entity', function () {
        $(this).closest('.si-entity-row').remove();
        updateEntitiesJson();
    });

    $(document).on('input change', '.si-entity-name, .si-entity-type, .si-entity-url', function () {
        updateEntitiesJson();
    });

    /* ========== Citation Repeater ========== */

    function updateCitationsJson() {
        const citations = [];
        $('.si-citation-row').each(function () {
            const source = $(this).find('.si-citation-source').val();
            const url = $(this).find('.si-citation-url').val();
            const date = $(this).find('.si-citation-date').val();
            const quote = $(this).find('.si-citation-quote').val();
            if (source || url) {
                citations.push({ source: source, url: url, date: date, quote: quote });
            }
        });
        $('#si_citations').val(JSON.stringify(citations));
    }

    $(document).on('click', '#si-add-citation', function () {
        const index = $('.si-citation-row').length;
        const html = `
            <div class="si-citation-row" data-index="${index}">
                <input type="text" class="si-citation-source" placeholder="Source name" value="" />
                <input type="url" class="si-citation-url" placeholder="URL" value="" />
                <input type="date" class="si-citation-date" value="" />
                <input type="text" class="si-citation-quote" placeholder="Key quote (optional)" value="" />
                <button type="button" class="button si-remove-citation">Remove</button>
            </div>
        `;
        $('#si-citation-repeater').append(html);
    });

    $(document).on('click', '.si-remove-citation', function () {
        $(this).closest('.si-citation-row').remove();
        updateCitationsJson();
    });

    $(document).on('input change', '.si-citation-source, .si-citation-url, .si-citation-date, .si-citation-quote', function () {
        updateCitationsJson();
    });

    /* ========== Social Image Picker ========== */

    $(document).on('click', '#si-select-social-image', function (e) {
        e.preventDefault();

        var frame = wp.media({
            title: 'Select Social Share Image',
            button: { text: 'Use this image' },
            multiple: false,
            library: { type: 'image' }
        });

        frame.on('select', function () {
            var attachment = frame.state().get('selection').first().toJSON();
            $('#si_social_image').val(attachment.url);
            var $preview = $('#si-social-image-preview');
            $preview.find('img').attr('src', attachment.url);
            $preview.show();
            $('#si-remove-social-image').show();
        });

        frame.open();
    });

    $(document).on('click', '#si-remove-social-image', function () {
        $('#si_social_image').val('');
        $('#si-social-image-preview').hide();
        $(this).hide();
    });

    /* ========== Local Relevance Toggle ========== */

    $(document).on('change', '#si_local_relevance', function () {
        if ($(this).is(':checked')) {
            $('#si-local-location-field').slideDown(200);
        } else {
            $('#si-local-location-field').slideUp(200);
        }
    });

    /* ========== Content Analysis ========== */

    function getEditorContent() {
        // Try Block Editor (Gutenberg)
        if (window.wp && wp.data && wp.data.select('core/editor')) {
            try {
                return wp.data.select('core/editor').getEditedPostContent();
            } catch (e) {
                // Fall through
            }
        }

        // Try Classic Editor (TinyMCE)
        if (typeof tinymce !== 'undefined') {
            const editor = tinymce.get('content');
            if (editor) {
                return editor.getContent();
            }
        }

        // Fallback to textarea
        const $textarea = $('#content');
        return $textarea.length ? $textarea.val() : '';
    }

    function getPostTitle() {
        // Block Editor
        if (window.wp && wp.data && wp.data.select('core/editor')) {
            try {
                return wp.data.select('core/editor').getEditedPostAttribute('title');
            } catch (e) {
                // Fall through
            }
        }

        // Classic Editor
        return $('#title').val() || $('input[name="post_title"]').val() || '';
    }

    function setScoreRing(ringId, score) {
        const $ring = $(ringId);
        const $value = $ring.find('.si-score-value');

        $value.text(score);

        // Remove existing classes
        $ring.removeClass('si-score-good si-score-ok si-score-bad');

        if (score >= 70) {
            $ring.addClass('si-score-good');
        } else if (score >= 40) {
            $ring.addClass('si-score-ok');
        } else {
            $ring.addClass('si-score-bad');
        }
    }

    function renderChecks(containerId, title, checks) {
        const $container = $(containerId);
        let html = '<h4>' + title + '</h4>';

        checks.forEach(function (check) {
            const cls = check.pass ? 'si-check-pass' : 'si-check-fail';
            const icon = check.pass ? '&#10003;' : '&#10007;';
            html += '<div class="si-check-item ' + cls + '">';
            html += '<span class="si-check-icon">' + icon + '</span>';
            html += '<span>' + check.label + '</span>';
            html += '</div>';
        });

        $container.html(html);
    }

    $(document).on('click', '#si-run-analysis', function () {
        const $btn = $(this);
        $btn.prop('disabled', true).text(SI.i18n ? SI.i18n.analyzing : 'Analyzing...');

        const content = getEditorContent();
        const title = $('#si_title').val() || getPostTitle();
        const keyword = $('#si_focus_keyword').val();
        const description = $('#si_description').val();

        $.ajax({
            url: SI.ajaxUrl,
            type: 'POST',
            data: {
                action: 'jat_search_intel_analyze',
                nonce: SI.nonce,
                post_id: SI.postId,
                content: content,
                title: title,
                focus_keyword: keyword,
                description: description
            },
            success: function (response) {
                if (response.success) {
                    const data = response.data;

                    // Update score rings
                    setScoreRing('#si-seo-ring', data.seo_score.score);
                    setScoreRing('#si-aeo-ring', data.aeo_score.score);
                    setScoreRing('#si-geo-ring', data.geo_score.score);
                    setScoreRing('#si-readability-ring', Math.round(data.readability.score));

                    // Render detailed checks
                    renderChecks('#si-seo-checks', 'SEO Checks', data.seo_score.checks);
                    renderChecks('#si-aeo-checks', 'AEO Checks', data.aeo_score.checks);
                    renderChecks('#si-geo-checks', 'GEO Checks', data.geo_score.checks);

                    $('#si-analysis-details').show();
                }
            },
            error: function () {
                alert('Analysis failed. Please try again.');
            },
            complete: function () {
                $btn.prop('disabled', false).text('Run Analysis');
            }
        });
    });

    /* ========== FAQ Generation ========== */

    $(document).on('click', '#si-generate-faqs', function () {
        var $btn = $(this);
        var $status = $('#si-faq-status');
        var useAi = $('#si-faq-use-ai').is(':checked') ? 1 : 0;

        $btn.prop('disabled', true);
        $status.html('<em>Generating...</em>');

        $.ajax({
            url: SI.ajaxUrl,
            type: 'POST',
            data: {
                action: 'jat_search_intel_generate_faqs',
                nonce: SI.faqNonce,
                post_id: SI.postId,
                use_ai: useAi
            },
            success: function (response) {
                if (response.success && response.data.faqs) {
                    var faqs = response.data.faqs;
                    var $repeater = $('#si-faq-repeater');
                    $repeater.empty();

                    faqs.forEach(function (faq, i) {
                        var html = '<div class="si-faq-item-row" data-index="' + i + '">' +
                            '<input type="text" class="widefat si-faq-question" placeholder="Question" value="' + $('<span/>').text(faq.question).html() + '" />' +
                            '<textarea class="widefat si-faq-answer" rows="2" placeholder="Answer">' + $('<span/>').text(faq.answer).html() + '</textarea>' +
                            '<button type="button" class="button si-remove-faq">Remove</button>' +
                            '</div>';
                        $repeater.append(html);
                    });

                    updateFaqsJson();
                    $status.html('<span style="color:green;">&#10003; ' + faqs.length + ' FAQs generated</span>');
                } else {
                    $status.html('<span style="color:red;">No FAQs found.</span>');
                }
            },
            error: function () {
                $status.html('<span style="color:red;">Generation failed.</span>');
            },
            complete: function () {
                $btn.prop('disabled', false);
            }
        });
    });

    /* ========== AI Suggestions ========== */

    $(document).on('click', '#si-ai-suggestions', function () {
        var $btn = $(this);
        var $panel = $('#si-ai-suggestions-panel');
        var $list = $('#si-ai-suggestions-list');

        $btn.prop('disabled', true).text('Loading...');
        $list.html('<em>Generating AI suggestions from analysis results...</em>');
        $panel.show();

        $.ajax({
            url: SI.ajaxUrl,
            type: 'POST',
            data: {
                action: 'jat_search_intel_ai_get_suggestions',
                nonce: SI.nonce,
                post_id: SI.postId,
                force: 1
            },
            success: function (response) {
                if (response.success && response.data.suggestions && response.data.suggestions.length) {
                    var html = '';
                    response.data.suggestions.forEach(function (s) {
                        var isApply = s.action === 'apply';
                        var cls = isApply ? 'si-suggestion-apply' : 'si-suggestion-info';
                        html += '<div class="si-suggestion-item ' + cls + '" style="margin-bottom:10px; padding:10px; background:#f9f9f9; border:1px solid #ddd; border-radius:4px;">';
                        html += '<strong>' + $('<span/>').text(s.title).html() + '</strong>';
                        html += '<p style="margin:6px 0 0; white-space:pre-wrap;">' + $('<span/>').text(s.text).html() + '</p>';
                        if (isApply && s.apply_data && s.apply_data.meta_key) {
                            html += '<button type="button" class="button si-apply-suggestion" data-suggestion-id="' + s.id + '" style="margin-top:6px;">Apply</button>';
                        }
                        html += '</div>';
                    });
                    $list.html(html);
                } else if (response.success) {
                    $list.html('<p>No suggestions — run analysis first to identify issues.</p>');
                } else {
                    $list.html('<p style="color:red;">' + (response.data.message || 'Failed to get suggestions.') + '</p>');
                }
            },
            error: function () {
                $list.html('<p style="color:red;">Request failed.</p>');
            },
            complete: function () {
                $btn.prop('disabled', false).text('AI Suggest Edits');
            }
        });
    });

    $(document).on('click', '.si-apply-suggestion', function () {
        var $btn = $(this);
        var suggestionId = $btn.data('suggestion-id');

        $btn.prop('disabled', true).text('Applying...');

        $.ajax({
            url: SI.ajaxUrl,
            type: 'POST',
            data: {
                action: 'jat_search_intel_ai_apply_suggestion',
                nonce: SI.nonce,
                post_id: SI.postId,
                suggestion_id: suggestionId
            },
            success: function (response) {
                if (response.success) {
                    $btn.replaceWith('<span style="color:green;">&#10003; Applied</span>');

                    // Update the corresponding field in the metabox if visible.
                    var metaKey = response.data.meta_key || '';
                    var value = response.data.value || '';
                    if (metaKey === '_jat_search_intel_description') {
                        $('#si_description').val(value);
                        updateSerpPreview();
                        updateCharCounts();
                    } else if (metaKey === '_jat_search_intel_title') {
                        $('#si_title').val(value);
                        updateSerpPreview();
                        updateCharCounts();
                    } else if (metaKey === '_jat_search_intel_focus_keyword') {
                        $('#si_focus_keyword').val(value);
                    }
                } else {
                    $btn.prop('disabled', false).text('Apply');
                    $btn.after('<span style="color:red;margin-left:8px;">' + (response.data.message || 'Failed') + '</span>');
                }
            }
        });
    });

    /* ========== Init ========== */

    $(document).ready(function () {
        updateSerpPreview();
        updateCharCounts();
    });

})(jQuery);
