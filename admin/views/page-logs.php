<?php
/**
 * Debug Logs Page Template
 *
 * @package Morden Toolkit
 * @author Morden Team
 * @license GPL v3 or later
 * @link https://github.com/sadewadee/morden-toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}


$plugin = MT_Plugin::get_instance();
$debug_service = $plugin->get_service('debug');
$debug_status = $debug_service->get_debug_status();
?>

<div class="wrap">
    <h1><?php _e('Debug Logs', 'morden-toolkit'); ?></h1>
    <p class="description">
        <?php _e('View dan manage WordPress debug logs.', 'morden-toolkit'); ?>
    </p>

    <div class="mt-logs-header">
        <div class="mt-logs-actions">
            <button type="button" id="refresh-logs" class="button">
                <span class="dashicons dashicons-update"></span>
                <?php _e('Refresh', 'morden-toolkit'); ?>
            </button>
            <button type="button" id="clear-logs" class="button">
                <span class="dashicons dashicons-trash"></span>
                <?php _e('Clear', 'morden-toolkit'); ?>
            </button>
            <button type="button" id="download-logs" class="button">
                <span class="dashicons dashicons-download"></span>
                <?php _e('Download', 'morden-toolkit'); ?>
            </button>
        </div>

        <div class="mt-logs-info">
            <?php if ($debug_status['log_file_exists']): ?>
            <span class="mt-log-size">
                <span class="dashicons dashicons-media-text"></span>
                <?php _e('File Size:', 'morden-toolkit'); ?> <?php echo esc_html($debug_status['log_file_size']); ?>
            </span>
            <?php else: ?>
            <span class="mt-no-logs">
                <span class="dashicons dashicons-info"></span>
                <?php _e('No log file found', 'morden-toolkit'); ?>
            </span>
            <?php endif; ?>
        </div>
    </div>

    <div class="mt-logs-filters">
        <div class="mt-filter-group">
            <label for="log-level-filter"><?php _e('Level:', 'morden-toolkit'); ?></label>
            <select id="log-level-filter">
                <option value=""><?php _e('All Levels', 'morden-toolkit'); ?></option>
                <option value="ERROR"><?php _e('Error', 'morden-toolkit'); ?></option>
                <option value="WARNING"><?php _e('Warning', 'morden-toolkit'); ?></option>
                <option value="NOTICE"><?php _e('Notice', 'morden-toolkit'); ?></option>
                <option value="DEPRECATED"><?php _e('Deprecated', 'morden-toolkit'); ?></option>
            </select>
        </div>

        <div class="mt-filter-group">
            <label for="log-time-filter"><?php _e('Time:', 'morden-toolkit'); ?></label>
            <select id="log-time-filter">
                <option value=""><?php _e('All Time', 'morden-toolkit'); ?></option>
                <option value="1h"><?php _e('Last Hour', 'morden-toolkit'); ?></option>
                <option value="24h" selected><?php _e('Last 24 Hours', 'morden-toolkit'); ?></option>
                <option value="7d"><?php _e('Last 7 Days', 'morden-toolkit'); ?></option>
            </select>
        </div>

        <div class="mt-filter-group">
            <label for="log-search"><?php _e('Search:', 'morden-toolkit'); ?></label>
            <input type="text" id="log-search" placeholder="<?php _e('Search logs...', 'morden-toolkit'); ?>">
        </div>
    </div>

    <div class="mt-logs-container">
        <div id="mt-logs-viewer" class="mt-logs-viewer">
            <?php if (!$debug_status['log_file_exists']): ?>
            <div class="mt-no-logs-message">
                <div class="dashicons dashicons-info"></div>
                <h3><?php _e('No Debug Logs Found', 'morden-toolkit'); ?></h3>
                <p><?php _e('Debug logging is not enabled or no errors have been logged yet.', 'morden-toolkit'); ?></p>
                <?php if (!$debug_status['enabled']): ?>
                <p>
                    <a href="<?php echo admin_url('tools.php?page=mt-toolkit'); ?>" class="button button-primary">
                        <?php _e('Enable Debug Mode', 'morden-toolkit'); ?>
                    </a>
                </p>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="mt-logs-loading">
                <div class="mt-spinner"></div>
                <p><?php _e('Loading logs...', 'morden-toolkit'); ?></p>
            </div>
            <div id="mt-logs-content" style="display: none;"></div>
            <?php endif; ?>
        </div>

        <div id="mt-logs-pagination" class="mt-logs-pagination" style="display: none;">
            <div class="mt-pagination-info">
                <span id="logs-showing-info"></span>
            </div>
            <div class="mt-pagination-controls">
                <button type="button" id="logs-prev-page" class="button" disabled>
                    <span class="dashicons dashicons-arrow-left-alt2"></span>
                    <?php _e('Previous', 'morden-toolkit'); ?>
                </button>
                <span id="logs-page-info"></span>
                <button type="button" id="logs-next-page" class="button">
                    <?php _e('Next', 'morden-toolkit'); ?>
                    <span class="dashicons dashicons-arrow-right-alt2"></span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-load logs when page loads
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('mt-logs-content')) {
        loadDebugLogs();
    }
});

function loadDebugLogs() {
    const logsContent = document.getElementById('mt-logs-content');
    const logsLoading = document.querySelector('.mt-logs-loading');

    if (!logsContent) return;

    logsLoading.style.display = 'block';
    logsContent.style.display = 'none';

    jQuery.post(ajaxurl, {
        action: 'mt_get_debug_log',
        nonce: mtToolkit.nonce
    }, function(response) {
        logsLoading.style.display = 'none';

        if (response.success && response.data) {
            displayLogs(response.data);
            logsContent.style.display = 'block';
        } else {
            logsContent.innerHTML = '<div class="notice notice-error"><p>' + mtToolkit.strings.error_occurred + '</p></div>';
            logsContent.style.display = 'block';
        }
    });
}

function displayLogs(logs) {
    const logsContent = document.getElementById('mt-logs-content');

    if (!logs || logs.length === 0) {
        logsContent.innerHTML = '<div class="mt-no-logs-message"><p>' + <?php echo json_encode(__('No log entries found.', 'morden-toolkit')); ?> + '</p></div>';
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

    logsContent.innerHTML = html;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>
