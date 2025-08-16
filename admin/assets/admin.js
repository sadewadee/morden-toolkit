/**
 * Morden Toolkit Admin JavaScript
 */

(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        initializeTabs();
        initializeToggles();
        initializeDebugActions();
        initializeQueryMonitor();
        initializeHtaccessEditor();
        initializePHPConfig();
        initializeLogsPage();
    });

    /**
     * Initialize tab navigation
     */
    function initializeTabs() {
        $('.mt-tab-btn').on('click', function() {
            const tabId = $(this).data('tab');

            // Update tab buttons
            $('.mt-tab-btn').removeClass('active');
            $(this).addClass('active');

            // Update tab contents
            $('.mt-tab-content').removeClass('active');
            $('#tab-' + tabId).addClass('active');
        });
    }

    /**
     * Initialize toggle switches
     */
    function initializeToggles() {
        $('.mt-toggle-wrapper input[type="checkbox"]').on('change', function() {
            const $toggle = $(this).siblings('.mt-toggle');
            if ($(this).is(':checked')) {
                $toggle.addClass('active');
            } else {
                $toggle.removeClass('active');
            }
        });

        $('.mt-toggle').on('click', function() {
            const $checkbox = $(this).siblings('input[type="checkbox"]');
            $checkbox.prop('checked', !$checkbox.is(':checked')).trigger('change');
        });
    }

    /**
     * Initialize debug management actions
     */
    function initializeDebugActions() {
        // Debug mode toggle
        $('#debug-mode-toggle').on('change', function() {
            const enabled = $(this).is(':checked');

            showLoading();

            $.post(mtToolkit.ajaxurl, {
                action: 'morden_toggle_debug',
                enabled: enabled,
                nonce: mtToolkit.nonce
            }, function(response) {
                hideLoading();

                if (response.success) {
                    showNotice(response.data.message, 'success');
                    updateDebugStatus(enabled);
                } else {
                    showNotice(response.data || mtToolkit.strings.error_occurred, 'error');
                    // Revert toggle state
                    $('#debug-mode-toggle').prop('checked', !enabled).trigger('change');
                }
            }).fail(function() {
                hideLoading();
                showNotice(mtToolkit.strings.error_occurred, 'error');
                $('#debug-mode-toggle').prop('checked', !enabled).trigger('change');
            });
        });

        // Clear debug log
        $('#clear-debug-log').on('click', function() {
            if (!confirm(mtToolkit.strings.confirm_clear_logs)) {
                return;
            }

            showLoading();

            $.post(mtToolkit.ajaxurl, {
                action: 'morden_clear_debug_log',
                nonce: mtToolkit.nonce
            }, function(response) {
                hideLoading();

                if (response.success) {
                    showNotice(response.data, 'success');
                } else {
                    showNotice(response.data || mtToolkit.strings.error_occurred, 'error');
                }
            });
        });
    }

    /**
     * Initialize query monitor actions
     */
    function initializeQueryMonitor() {
        $('#query-monitor-toggle').on('change', function() {
            const enabled = $(this).is(':checked');

            showLoading();

            $.post(mtToolkit.ajaxurl, {
                action: 'morden_toggle_query_monitor',
                enabled: enabled,
                nonce: mtToolkit.nonce
            }, function(response) {
                hideLoading();

                if (response.success) {
                    showNotice(response.data.message, 'success');
                } else {
                    showNotice(response.data || mtToolkit.strings.error_occurred, 'error');
                    $('#query-monitor-toggle').prop('checked', !enabled).trigger('change');
                }
            });
        });
    }

    /**
     * Initialize .htaccess editor
     */
    function initializeHtaccessEditor() {
        let originalContent = $('#htaccess-editor').val();

        // Save .htaccess
        $('#save-htaccess').on('click', function() {
            const content = $('#htaccess-editor').val();

            showLoading();

            $.post(mtToolkit.ajaxurl, {
                action: 'morden_save_htaccess',
                content: content,
                nonce: mtToolkit.nonce
            }, function(response) {
                hideLoading();

                if (response.success) {
                    showNotice(response.data, 'success');
                    originalContent = content;
                    // Refresh page to update backup info
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showNotice(response.data || mtToolkit.strings.error_occurred, 'error');
                }
            });
        });

        // Restore .htaccess
        $('.mt-restore-item').on('click', function(e) {
            e.preventDefault();

            if (!confirm(mtToolkit.strings.confirm_restore_htaccess)) {
                return;
            }

            const backupIndex = $(this).data('index');

            showLoading();

            $.post(mtToolkit.ajaxurl, {
                action: 'morden_restore_htaccess',
                backup_index: backupIndex,
                nonce: mtToolkit.nonce
            }, function(response) {
                hideLoading();

                if (response.success) {
                    showNotice(response.data, 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showNotice(response.data || mtToolkit.strings.error_occurred, 'error');
                }
            });
        });

        // Cancel changes
        $('#cancel-htaccess').on('click', function() {
            $('#htaccess-editor').val(originalContent);
        });

        // Insert snippets
        $('.mt-snippet-btn').on('click', function() {
            const snippet = $(this).data('snippet');
            const $editor = $('#htaccess-editor');
            const currentContent = $editor.val();

            if (currentContent && !currentContent.endsWith('\n')) {
                $editor.val(currentContent + '\n\n' + snippet);
            } else {
                $editor.val(currentContent + snippet);
            }
        });
    }

    /**
     * Initialize PHP config actions
     */
    function initializePHPConfig() {
        // Preset selection
        $('input[name="php_preset"]').on('change', function() {
            $('.mt-preset-option').removeClass('selected');
            $(this).closest('.mt-preset-option').addClass('selected');
        });

        // Apply configuration
        $('#apply-php-preset').on('click', function() {
            const preset = $('input[name="php_preset"]:checked').val();

            if (!preset) {
                showNotice('Please select a preset first.', 'error');
                return;
            }

            showLoading();

            $.post(mtToolkit.ajaxurl, {
                action: 'morden_apply_php_preset',
                preset: preset,
                nonce: mtToolkit.nonce
            }, function(response) {
                hideLoading();

                if (response.success) {
                    showNotice(response.data, 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    showNotice(response.data || mtToolkit.strings.error_occurred, 'error');
                }
            });
        });
    }

    /**
     * Initialize logs page functionality
     */
    function initializeLogsPage() {
        if (!$('#mt-logs-viewer').length) {
            return;
        }

        // Refresh logs
        $('#refresh-logs').on('click', function() {
            loadDebugLogs();
        });

        // Clear logs
        $('#clear-logs').on('click', function() {
            if (!confirm(mtToolkit.strings.confirm_clear_logs)) {
                return;
            }

            showLoading();

            $.post(mtToolkit.ajaxurl, {
                action: 'morden_clear_debug_log',
                nonce: mtToolkit.nonce
            }, function(response) {
                hideLoading();

                if (response.success) {
                    showNotice(response.data, 'success');
                    $('#mt-logs-content').html('<div class="mt-no-logs-message"><p>No log entries found.</p></div>');
                } else {
                    showNotice(response.data || mtToolkit.strings.error_occurred, 'error');
                }
            });
        });

        // Download logs
        $('#download-logs').on('click', function() {
            // Create download link
            const downloadUrl = mtToolkit.ajaxurl + '?action=morden_download_logs&nonce=' + mtToolkit.nonce;
            const link = document.createElement('a');
            link.href = downloadUrl;
            link.download = 'debug-logs-' + new Date().toISOString().slice(0, 10) + '.txt';
            link.click();
        });

        // Filter logs
        $('#log-level-filter, #log-time-filter').on('change', function() {
            filterLogs();
        });

        $('#log-search').on('input', debounce(function() {
            filterLogs();
        }, 300));
    }

    /**
     * Load debug logs
     */
    function loadDebugLogs() {
        const $logsContent = $('#mt-logs-content');
        const $logsLoading = $('.mt-logs-loading');

        if (!$logsContent.length) return;

        $logsLoading.show();
        $logsContent.hide();

        $.post(mtToolkit.ajaxurl, {
            action: 'morden_get_debug_log',
            nonce: mtToolkit.nonce
        }, function(response) {
            $logsLoading.hide();

            if (response.success && response.data) {
                displayLogs(response.data);
                $logsContent.show();
            } else {
                $logsContent.html('<div class="notice notice-error"><p>' + mtToolkit.strings.error_occurred + '</p></div>');
                $logsContent.show();
            }
        });
    }

    /**
     * Display logs in the viewer
     */
    function displayLogs(logs) {
        const $logsContent = $('#mt-logs-content');

        if (!logs || logs.length === 0) {
            $logsContent.html('<div class="mt-no-logs-message"><p>No log entries found.</p></div>');
            return;
        }

        let html = '<div class="mt-logs-list">';

        logs.forEach(function(log) {
            const levelClass = 'log-level-' + log.level.toLowerCase();
            const timeFormatted = new Date(log.timestamp).toLocaleString();

            html += '<div class="mt-log-entry ' + levelClass + '">';
            html += '<div class="log-header">';
            html += '<span class="log-level">' + log.level + '</span>';
            html += '<span class="log-time">' + timeFormatted + '</span>';
            html += '</div>';
            html += '<div class="log-message">' + escapeHtml(log.message) + '</div>';

            if (log.file) {
                html += '<div class="log-file">';
                html += '<span class="dashicons dashicons-media-code"></span>';
                html += escapeHtml(log.file);
                if (log.line) {
                    html += ':' + log.line;
                }
                html += '</div>';
            }
            html += '</div>';
        });

        html += '</div>';

        $logsContent.html(html);
    }

    /**
     * Filter logs based on level, time and search
     */
    function filterLogs() {
        const level = $('#log-level-filter').val();
        const timeFilter = $('#log-time-filter').val();
        const searchTerm = $('#log-search').val().toLowerCase();

        $('.mt-log-entry').each(function() {
            let show = true;

            // Level filter
            if (level && !$(this).hasClass('log-level-' + level.toLowerCase())) {
                show = false;
            }

            // Search filter
            if (searchTerm && $(this).text().toLowerCase().indexOf(searchTerm) === -1) {
                show = false;
            }

            // Time filter would require more complex logic with actual timestamps

            $(this).toggle(show);
        });
    }

    /**
     * Update debug status display
     */
    function updateDebugStatus(enabled) {
        const $indicator = $('.mt-status-indicator');
        const $statusText = $('.mt-status-item span:last-child');

        if (enabled) {
            $indicator.removeClass('inactive').addClass('active');
            $statusText.text('Status: Debug Enabled');
        } else {
            $indicator.removeClass('active').addClass('inactive');
            $statusText.text('Status: Debug Disabled');
        }
    }

    /**
     * Show loading overlay
     */
    function showLoading() {
        $('#mt-loading-overlay').show();
    }

    /**
     * Hide loading overlay
     */
    function hideLoading() {
        $('#mt-loading-overlay').hide();
    }

    /**
     * Show notice message
     */
    function showNotice(message, type) {
        type = type || 'info';

        const noticeClass = 'notice notice-' + type;
        const $notice = $('<div class="' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');

        // Insert after page title
        $('.wrap h1').after($notice);

        // Auto dismiss after 5 seconds
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);

        // Make dismissible
        $notice.find('.notice-dismiss').on('click', function() {
            $notice.remove();
        });
    }

    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Debounce function
     */
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = function() {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

})(jQuery);
