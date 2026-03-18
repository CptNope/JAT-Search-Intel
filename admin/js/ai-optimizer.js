/**
 * Search Intel — AI Optimizer meta box interactions.
 *
 * @package Search_Intel
 * @since   3.4.0
 */
/* global jQuery, siAiOptimizer */
(function ($) {
	'use strict';

	var $wrap, $genBtn, $optBtn, $rollBtn, $list, $loading, $loadingText,
		$result, $snapshotInfo, postId;

	function init() {
		$wrap        = $('#si-ai-optimizer');
		$genBtn      = $('#si-ai-generate-btn');
		$optBtn      = $('#si-ai-auto-optimize-btn');
		$rollBtn     = $('#si-ai-rollback-btn');
		$list        = $('#si-ai-suggestions-list');
		$loading     = $('#si-ai-loading');
		$loadingText = $('#si-ai-loading-text');
		$result      = $('#si-ai-optimize-result');
		$snapshotInfo = $('#si-ai-snapshot-info');
		postId       = $wrap.data('post-id');

		if (!postId) {
			return;
		}

		$genBtn.on('click', generateSuggestions);
		$optBtn.on('click', autoOptimize);
		$rollBtn.on('click', rollback);
		$wrap.on('click', '.si-ai-apply-btn', applySuggestion);
	}

	/* ----- Auto-Optimize ------------------------------------------------ */

	function autoOptimize() {
		if (!confirm('AI will rewrite your content to fix failing checks.\n\nA snapshot will be saved so you can rollback.\n\nProceed?')) {
			return;
		}

		$optBtn.prop('disabled', true);
		$rollBtn.prop('disabled', true);
		$result.hide();
		showLoading('Optimizing with AI — this may take 30-60 seconds…');

		$.post(siAiOptimizer.ajaxUrl, {
			action:  'si_ai_auto_optimize',
			nonce:   siAiOptimizer.nonce,
			post_id: postId
		})
		.done(function (res) {
			hideLoading();
			$optBtn.prop('disabled', false);

			if (res.success) {
				renderOptimizeResult(res.data);
				$rollBtn.prop('disabled', false);
				updateSnapshotInfo('Just now');
			} else {
				showError(res.data && res.data.message ? res.data.message : 'Optimization failed.');
				$rollBtn.prop('disabled', false);
			}
		})
		.fail(function () {
			hideLoading();
			$optBtn.prop('disabled', false);
			$rollBtn.prop('disabled', false);
			showError('Request failed. Check your connection and AI provider settings.');
		});
	}

	function renderOptimizeResult(data) {
		var html = '<div class="si-optimize-result si-optimize-result--success">';
		html += '<strong>' + esc(data.message) + '</strong><br>';

		// Score deltas.
		if (data.old_scores && data.new_scores) {
			html += '<div style="margin:6px 0;">';
			var modules = ['seo', 'aeo', 'geo', 'readability'];
			var labels  = { seo: 'SEO', aeo: 'AEO', geo: 'GEO', readability: 'READ' };
			for (var i = 0; i < modules.length; i++) {
				var m   = modules[i];
				var old = parseFloat(data.old_scores[m]) || 0;
				var nw  = parseFloat(data.new_scores[m]) || 0;
				var diff = Math.round((nw - old) * 10) / 10;
				var cls  = diff > 0 ? 'up' : (diff < 0 ? 'down' : 'same');
				var arrow = diff > 0 ? '↑' : (diff < 0 ? '↓' : '→');
				html += '<span class="si-score-delta">' + labels[m] + ' ' + old + ' ' + arrow + ' ' + nw;
				html += ' <span class="' + cls + '">(' + (diff > 0 ? '+' : '') + diff + ')</span></span> ';
			}
			html += '</div>';

			// Update the score badges in the meta box.
			updateScoreBadges(data.new_scores);
		}

		// Changes list.
		if (data.changes && data.changes.length) {
			html += '<ul style="margin:4px 0 0 16px;font-size:11px;">';
			for (var j = 0; j < data.changes.length; j++) {
				html += '<li>' + esc(data.changes[j]) + '</li>';
			}
			html += '</ul>';
		}

		html += '</div>';
		$result.html(html).show();
	}

	/* ----- Rollback ----------------------------------------------------- */

	function rollback() {
		if (!confirm('This will restore the post to its state before the last AI optimization.\n\nThe page will reload after rollback.\n\nProceed?')) {
			return;
		}

		$rollBtn.prop('disabled', true).text('Rolling back…');
		showLoading('Restoring previous version…');

		$.post(siAiOptimizer.ajaxUrl, {
			action:  'si_ai_rollback',
			nonce:   siAiOptimizer.nonce,
			post_id: postId
		})
		.done(function (res) {
			hideLoading();
			if (res.success) {
				// Reload the page to reflect restored content.
				window.location.reload();
			} else {
				$rollBtn.prop('disabled', false).text('Rollback');
				showError(res.data && res.data.message ? res.data.message : 'Rollback failed.');
			}
		})
		.fail(function () {
			hideLoading();
			$rollBtn.prop('disabled', false).text('Rollback');
			showError('Rollback request failed.');
		});
	}

	/* ----- Generate Suggestions ----------------------------------------- */

	function generateSuggestions() {
		$genBtn.prop('disabled', true);
		$list.html('');
		showLoading('Generating AI suggestions…');

		$.post(siAiOptimizer.ajaxUrl, {
			action:  'si_ai_get_suggestions',
			nonce:   siAiOptimizer.nonce,
			post_id: postId,
			force:   1
		})
		.done(function (res) {
			hideLoading();
			$genBtn.prop('disabled', false).text('Regenerate AI Suggestions');

			if (!res.success || !res.data.suggestions.length) {
				$list.html('<p>No suggestions generated.</p>');
				return;
			}

			renderSuggestions(res.data.suggestions);
		})
		.fail(function () {
			hideLoading();
			$genBtn.prop('disabled', false);
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

	/* ----- Apply Single Suggestion -------------------------------------- */

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

	/* ----- Helpers ------------------------------------------------------- */

	function showLoading(text) {
		$loadingText.text(text);
		$loading.show();
	}

	function hideLoading() {
		$loading.hide();
	}

	function showError(msg) {
		$result.html('<div class="si-optimize-result si-optimize-result--error">' + esc(msg) + '</div>').show();
	}

	function updateScoreBadges(scores) {
		var $badges = $wrap.find('.si-ai-scores .si-score-badge');
		var keys = ['seo', 'aeo', 'geo', 'readability'];
		var labels = ['SEO', 'AEO', 'GEO', 'READ'];
		$badges.each(function (i) {
			if (i < keys.length && scores[keys[i]] !== undefined) {
				var val = parseFloat(scores[keys[i]]) || 0;
				$(this).text(labels[i] + ' ' + val);
				$(this).css('background', scoreColor(val));
			}
		});
	}

	function updateSnapshotInfo(dateStr) {
		$snapshotInfo.text('Last snapshot: ' + dateStr).show();
	}

	function scoreColor(score) {
		if (score >= 70) return '#00a32a';
		if (score >= 40) return '#dba617';
		return '#d63638';
	}

	function esc(str) {
		if (!str) return '';
		var div = document.createElement('div');
		div.appendChild(document.createTextNode(str));
		return div.innerHTML;
	}

	$(document).ready(init);

})(jQuery);
