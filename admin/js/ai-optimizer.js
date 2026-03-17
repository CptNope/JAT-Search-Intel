/**
 * Search Intel — AI Optimizer meta box interactions.
 *
 * @package Search_Intel
 * @since   3.4.0
 */
/* global jQuery, siAiOptimizer */
(function ($) {
	'use strict';

	var $wrap, $btn, $list, $loading, postId;

	function init() {
		$wrap    = $('#si-ai-optimizer');
		$btn     = $('#si-ai-generate-btn');
		$list    = $('#si-ai-suggestions-list');
		$loading = $('#si-ai-loading');
		postId   = $wrap.data('post-id');

		if (!postId) {
			return;
		}

		$btn.on('click', generateSuggestions);
		$wrap.on('click', '.si-ai-apply-btn', applySuggestion);
	}

	function generateSuggestions() {
		$btn.prop('disabled', true);
		$list.html('');
		$loading.show();

		$.post(siAiOptimizer.ajaxUrl, {
			action:  'si_ai_get_suggestions',
			nonce:   siAiOptimizer.nonce,
			post_id: postId,
			force:   1
		})
		.done(function (res) {
			$loading.hide();
			$btn.prop('disabled', false).text('Regenerate AI Suggestions');

			if (!res.success || !res.data.suggestions.length) {
				$list.html('<p>No suggestions generated.</p>');
				return;
			}

			renderSuggestions(res.data.suggestions);
		})
		.fail(function () {
			$loading.hide();
			$btn.prop('disabled', false);
			$list.html('<p style="color:#d63638;">Failed to generate suggestions. Check your AI provider settings.</p>');
		});
	}

	function renderSuggestions(suggestions) {
		var html = '';
		$.each(suggestions, function (i, s) {
			var applied = s.action === 'applied';
			var cls     = applied ? 'si-suggestion si-suggestion--applied' : 'si-suggestion';

			html += '<div class="' + cls + '" data-suggestion-id="' + esc(s.id) + '">';
			html += '<h4>' + esc(s.title);
			if (applied) {
				html += ' <span style="color:#00a32a;">✓ Applied</span>';
			}
			html += '</h4>';
			html += '<p>' + esc(s.text) + '</p>';

			if (s.action === 'apply' && s.apply_data) {
				html += '<button type="button" class="button si-ai-apply-btn" data-suggestion-id="' + esc(s.id) + '">Apply</button>';
			} else if (s.action === 'review') {
				html += '<em style="font-size:11px;color:#646970;">Review and apply manually</em>';
			}

			html += '</div>';
		});

		$list.html(html);
	}

	function applySuggestion(e) {
		var $btn_el      = $(e.currentTarget);
		var suggestionId = $btn_el.data('suggestion-id');

		$btn_el.prop('disabled', true).text('Applying…');

		$.post(siAiOptimizer.ajaxUrl, {
			action:        'si_ai_apply_suggestion',
			nonce:         siAiOptimizer.nonce,
			post_id:       postId,
			suggestion_id: suggestionId
		})
		.done(function (res) {
			if (res.success) {
				var $card = $btn_el.closest('.si-suggestion');
				$card.addClass('si-suggestion--applied');
				$card.find('h4').append(' <span style="color:#00a32a;">✓ Applied</span>');
				$btn_el.remove();
			} else {
				$btn_el.prop('disabled', false).text('Apply');
				alert(res.data && res.data.message ? res.data.message : 'Failed to apply.');
			}
		})
		.fail(function () {
			$btn_el.prop('disabled', false).text('Apply');
			alert('Request failed.');
		});
	}

	function esc(str) {
		if (!str) return '';
		var div = document.createElement('div');
		div.appendChild(document.createTextNode(str));
		return div.innerHTML;
	}

	$(document).ready(init);

})(jQuery);
