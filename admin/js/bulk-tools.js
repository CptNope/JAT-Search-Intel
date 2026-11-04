/* global jQuery, siBulkTools */
(function ($) {
    'use strict';

    var i18n = siBulkTools.i18n;

    // ── Bulk Rescore ────────────────────────────────────────────────────────

    $('#si-btn-rescore').on('click', function () {
        var btn = $(this);
        btn.prop('disabled', true);
        $('#si-progress-rescore').show();
        runBatch('jat_search_intel_bulk_rescore', 0, 'rescore');
    });

    // ── Bulk Regenerate ─────────────────────────────────────────────────────

    $('#si-btn-regenerate').on('click', function () {
        var btn = $(this);
        btn.prop('disabled', true);
        $('#si-progress-regenerate').show();
        runBatch('jat_search_intel_bulk_regenerate', 0, 'regenerate');
    });

    /**
     * Run a batch AJAX operation with progress bar.
     */
    function runBatch(action, offset, key) {
        var postType = $('#si-bulk-post-type').val();

        $.post(siBulkTools.ajaxUrl, {
            action: action,
            nonce: siBulkTools.nonce,
            offset: offset,
            post_type: postType
        }, function (response) {
            if (!response.success) {
                $('#si-text-' + key).text(i18n.error);
                $('#si-btn-' + key).prop('disabled', false);
                return;
            }

            var data = response.data;
            var pct = Math.round((data.done / data.total) * 100) || 0;
            $('#si-fill-' + key).css('width', pct + '%');
            $('#si-text-' + key).text(data.done + ' / ' + data.total + ' (' + pct + '%)');

            if (data.has_more) {
                runBatch(action, data.next_offset, key);
            } else {
                $('#si-text-' + key).text(data.total + ' / ' + data.total + ' — ' + i18n.complete);
                $('#si-btn-' + key).prop('disabled', false);
            }
        }).fail(function () {
            $('#si-text-' + key).text(i18n.error);
            $('#si-btn-' + key).prop('disabled', false);
        });
    }

    // ── Sitemap Cache Warm ──────────────────────────────────────────────────

    $('#si-btn-sitemap-warm').on('click', function () {
        var btn = $(this);
        btn.prop('disabled', true);

        $.post(siBulkTools.ajaxUrl, {
            action: 'jat_search_intel_bulk_sitemap_warm',
            nonce: siBulkTools.nonce
        }, function (response) {
            btn.prop('disabled', false);
            if (response.success) {
                $('#si-result-sitemap')
                    .html('<span class="si-success">' + response.data.message + '</span>')
                    .show();
            } else {
                $('#si-result-sitemap')
                    .html('<span class="si-error">' + i18n.error + '</span>')
                    .show();
            }
        }).fail(function () {
            btn.prop('disabled', false);
            $('#si-result-sitemap')
                .html('<span class="si-error">' + i18n.error + '</span>')
                .show();
        });
    });

    // ── Export Settings ─────────────────────────────────────────────────────

    $('#si-btn-export').on('click', function () {
        $.post(siBulkTools.ajaxUrl, {
            action: 'jat_search_intel_export_settings',
            nonce: siBulkTools.nonce
        }, function (response) {
            if (!response.success) {
                alert(i18n.error);
                return;
            }

            var json = JSON.stringify(response.data, null, 2);
            var blob = new Blob([json], { type: 'application/json' });
            var url = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = 'jat-search-intel-settings-' + new Date().toISOString().slice(0, 10) + '.json';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        });
    });

    // ── Import Settings ─────────────────────────────────────────────────────

    $('#si-import-file').on('change', function () {
        $('#si-btn-import').prop('disabled', !this.files.length);
    });

    $('#si-btn-import').on('click', function () {
        var file = $('#si-import-file')[0].files[0];
        if (!file) {
            return;
        }

        var mode = $('input[name="si_import_mode"]:checked').val();

        if (mode === 'overwrite' && !confirm(i18n.confirm)) {
            return;
        }

        var reader = new FileReader();
        reader.onload = function (e) {
            var json = e.target.result;

            // Validate JSON before sending.
            try {
                var parsed = JSON.parse(json);
                if (!parsed.plugin || parsed.plugin !== 'jat-search-intel') {
                    $('#si-result-import')
                        .html('<span class="si-error">Invalid JAT Search Intel settings file.</span>')
                        .show();
                    return;
                }
            } catch (err) {
                $('#si-result-import')
                    .html('<span class="si-error">Invalid JSON file.</span>')
                    .show();
                return;
            }

            $('#si-btn-import').prop('disabled', true);

            $.post(siBulkTools.ajaxUrl, {
                action: 'jat_search_intel_import_settings',
                nonce: siBulkTools.nonce,
                import_data: json,
                import_mode: mode
            }, function (response) {
                $('#si-btn-import').prop('disabled', false);
                if (response.success) {
                    $('#si-result-import')
                        .html('<span class="si-success">' + response.data.message + '</span>')
                        .show();
                } else {
                    $('#si-result-import')
                        .html('<span class="si-error">' + (response.data.message || i18n.error) + '</span>')
                        .show();
                }
            }).fail(function () {
                $('#si-btn-import').prop('disabled', false);
                $('#si-result-import')
                    .html('<span class="si-error">' + i18n.error + '</span>')
                    .show();
            });
        };
        reader.readAsText(file);
    });

    // ── Bulk AI Content Generation ─────────────────────────────────────────

    // Select / Deselect All toggle.
    $('#si-ai-gen-select-all').on('change', function () {
        $('.si-ai-gen-field').prop('checked', $(this).is(':checked'));
    });
    $('.si-ai-gen-field').on('change', function () {
        var total = $('.si-ai-gen-field').length;
        var checked = $('.si-ai-gen-field:checked').length;
        $('#si-ai-gen-select-all').prop('checked', checked === total);
    });

    $('#si-btn-ai-generate').on('click', function () {
        var fields = [];
        $('.si-ai-gen-field:checked').each(function () {
            fields.push($(this).val());
        });

        if (!fields.length) {
            $('#si-result-ai-generate')
                .html('<span class="si-error">Select at least one field to generate.</span>')
                .show();
            return;
        }

        var mode = $('input[name="si_ai_gen_mode"]:checked').val();

        if (mode === 'overwrite_all' && !confirm('This will overwrite existing AI-generated content. Continue?')) {
            return;
        }

        $(this).prop('disabled', true);
        $('#si-progress-ai-generate').show();
        $('#si-result-ai-generate').hide();
        runAiBatch(0, fields, mode, 0, 0);
    });

    function runAiBatch(offset, fields, mode, generated, errors) {
        var postType = $('#si-bulk-post-type').val();

        $.post(siBulkTools.ajaxUrl, {
            action: 'jat_search_intel_bulk_ai_generate',
            nonce: siBulkTools.nonce,
            offset: offset,
            post_type: postType,
            fields: fields,
            mode: mode
        }, function (response) {
            if (!response.success) {
                $('#si-text-ai-generate').text(response.data.message || i18n.error);
                $('#si-btn-ai-generate').prop('disabled', false);
                return;
            }

            var data = response.data;
            var pct = Math.round((data.done / data.total) * 100) || 0;
            generated += data.generated || 0;
            errors += data.errors || 0;

            $('#si-fill-ai-generate').css('width', pct + '%');
            $('#si-text-ai-generate').text(
                data.done + ' / ' + data.total + ' (' + pct + '%) — ' +
                generated + ' generated, ' + errors + ' errors'
            );

            if (data.has_more) {
                runAiBatch(data.next_offset, fields, mode, generated, errors);
            } else {
                $('#si-text-ai-generate').text(
                    data.total + ' / ' + data.total + ' — ' + i18n.complete +
                    ' (' + generated + ' generated, ' + errors + ' errors)'
                );
                $('#si-btn-ai-generate').prop('disabled', false);
            }
        }).fail(function () {
            $('#si-text-ai-generate').text(i18n.error);
            $('#si-btn-ai-generate').prop('disabled', false);
        });
    }

})(jQuery);
