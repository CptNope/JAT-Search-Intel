/* global jQuery, siBulkTools */
(function ($) {
    'use strict';

    var i18n = siBulkTools.i18n;

    // ── Bulk Rescore ────────────────────────────────────────────────────────

    $('#si-btn-rescore').on('click', function () {
        var btn = $(this);
        btn.prop('disabled', true);
        $('#si-progress-rescore').show();
        runBatch('si_bulk_rescore', 0, 'rescore');
    });

    // ── Bulk Regenerate ─────────────────────────────────────────────────────

    $('#si-btn-regenerate').on('click', function () {
        var btn = $(this);
        btn.prop('disabled', true);
        $('#si-progress-regenerate').show();
        runBatch('si_bulk_regenerate', 0, 'regenerate');
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
            action: 'si_bulk_sitemap_warm',
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
            action: 'si_export_settings',
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
            a.download = 'search-intel-settings-' + new Date().toISOString().slice(0, 10) + '.json';
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
                if (!parsed.plugin || parsed.plugin !== 'search-intel') {
                    $('#si-result-import')
                        .html('<span class="si-error">Invalid Search Intel settings file.</span>')
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
                action: 'si_import_settings',
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

})(jQuery);
