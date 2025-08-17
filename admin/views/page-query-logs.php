<?php
/**
 * Query Logs Page Template
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get debug service
$plugin = MT_Plugin::get_instance();
$debug_service = $plugin->get_service('debug');
$debug_status = $debug_service->get_debug_status();
?>

<div class="wrap">
    <h1><?php _e('Query Logs', 'mt'); ?></h1>
    <p class="description">
        <?php _e('View database query logs. SAVEQUERIES must be enabled to record queries.', 'mt'); ?>
    </p>

    <?php if (!$debug_status['savequeries']): ?>
    <div class="notice notice-warning">
        <p>
            <strong><?php _e('SAVEQUERIES is not enabled!', 'mt'); ?></strong>
            <?php _e('Database queries are not being recorded. ', 'mt'); ?>
            <a href="<?php echo admin_url('tools.php?page=mt'); ?>" class="button button-primary">
                <?php _e('Enable SAVEQUERIES', 'mt'); ?>
            </a>
        </p>
    </div>
    <?php endif; ?>

    <div class="mt-logs-header">
        <div class="mt-logs-actions">
            <button type="button" id="refresh-query-logs" class="button">
                <span class="dashicons dashicons-update"></span>
                <?php _e('Refresh', 'mt'); ?>
            </button>
            <button type="button" id="clear-query-logs" class="button">
                <span class="dashicons dashicons-trash"></span>
                <?php _e('Clear', 'mt'); ?>
            </button>
            <button type="button" id="cleanup-query-logs" class="button" title="<?php _e('Remove old backup log files', 'mt'); ?>">
                <span class="dashicons dashicons-admin-tools"></span>
                <?php _e('Cleanup', 'mt'); ?>
            </button>
            <button type="button" id="download-query-logs" class="button">
                <span class="dashicons dashicons-download"></span>
                <?php _e('Download', 'mt'); ?>
            </button>
        </div>

        <div class="mt-logs-info">
            <?php if ($debug_status['query_log_file_exists']): ?>
            <span class="mt-log-size">
                <span class="dashicons dashicons-media-text"></span>
                <?php _e('Current:', 'mt'); ?> <?php echo esc_html($debug_status['query_log_file_size']); ?>
            </span>
            <?php if (isset($debug_status['query_log_total_size'])): ?>
            <span class="mt-log-total-size">
                <span class="dashicons dashicons-database"></span>
                <?php _e('Total (with backups):', 'mt'); ?> <?php echo esc_html($debug_status['query_log_total_size']); ?>
            </span>
            <?php endif; ?>
            <?php if (isset($debug_status['query_log_max_size'])): ?>
            <span class="mt-log-max-size">
                <span class="dashicons dashicons-info"></span>
                <?php _e('Rotation at:', 'mt'); ?> <?php echo esc_html($debug_status['query_log_max_size']); ?>
            </span>
            <?php endif; ?>
            <?php else: ?>
            <span class="mt-no-logs">
                <span class="dashicons dashicons-info"></span>
                <?php _e('No query log file found', 'mt'); ?>
            </span>
            <?php endif; ?>
        </div>
    </div>

    <div class="mt-logs-filters">
        <div class="mt-filter-group">
            <label for="query-time-filter"><?php _e('Time:', 'mt'); ?></label>
            <select id="query-time-filter">
                <option value=""><?php _e('All Time', 'mt'); ?></option>
                <option value="1h"><?php _e('Last Hour', 'mt'); ?></option>
                <option value="24h" selected><?php _e('Last 24 Hours', 'mt'); ?></option>
                <option value="7d"><?php _e('Last 7 Days', 'mt'); ?></option>
            </select>
        </div>

        <div class="mt-filter-group">
            <label for="query-type-filter"><?php _e('Query Type:', 'mt'); ?></label>
            <select id="query-type-filter">
                <option value=""><?php _e('All Types', 'mt'); ?></option>
                <option value="SELECT"><?php _e('SELECT', 'mt'); ?></option>
                <option value="INSERT"><?php _e('INSERT', 'mt'); ?></option>
                <option value="UPDATE"><?php _e('UPDATE', 'mt'); ?></option>
                <option value="DELETE"><?php _e('DELETE', 'mt'); ?></option>
            </select>
        </div>

        <div class="mt-filter-group">
            <label for="query-slow-filter"><?php _e('Performance:', 'mt'); ?></label>
            <select id="query-slow-filter">
                <option value=""><?php _e('All Queries', 'mt'); ?></option>
                <option value="slow"><?php _e('Slow Queries Only', 'mt'); ?></option>
                <option value="fast"><?php _e('Fast Queries Only', 'mt'); ?></option>
            </select>
        </div>

        <div class="mt-filter-group">
            <label for="query-search"><?php _e('Search:', 'mt'); ?></label>
            <input type="text" id="query-search" placeholder="<?php _e('Search in SQL or caller stack...', 'mt'); ?>">
        </div>

        <div class="mt-filter-group">
            <button type="button" id="clear-query-filters" class="button">
                <?php _e('Clear Filters', 'mt'); ?>
            </button>
        </div>
    </div>

    <div class="mt-query-logs-container">
        <div id="mt-query-logs-viewer" class="mt-logs-viewer">
            <?php if (!$debug_status['query_log_file_exists'] || !$debug_status['savequeries']): ?>
            <div class="mt-no-logs-message">
                <div class="dashicons dashicons-info"></div>
                <h3><?php _e('No Query Logs Found', 'mt'); ?></h3>
                <?php if (!$debug_status['savequeries']): ?>
                <p><?php _e('SAVEQUERIES is not enabled. Database queries are not being recorded.', 'mt'); ?></p>
                <p>
                    <a href="<?php echo admin_url('tools.php?page=mt'); ?>" class="button button-primary">
                        <?php _e('Enable SAVEQUERIES', 'mt'); ?>
                    </a>
                </p>
                <?php else: ?>
                <p><?php _e('No database queries have been logged yet. Try visiting some pages first.', 'mt'); ?></p>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="mt-logs-loading">
                <div class="mt-spinner"></div>
                <p><?php _e('Loading query logs...', 'mt'); ?></p>
            </div>
            <div id="mt-query-logs-content" style="display: none;"></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Helper functions for formatting
function parseUrlSource(url) {
    if (!url) return { name: 'Unknown', path: '' };

    // Extract source from URL
    let sourceName = 'WordPress Core';
    let sourcePath = url;

    if (url.includes('/plugins/')) {
        const pluginMatch = url.match(/\/plugins\/([^\/]+)/);
        if (pluginMatch) {
            sourceName = pluginMatch[1].replace(/-/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
            sourcePath = url.replace(/.*\/plugins\//, 'plugins/');
        }
    } else if (url.includes('/themes/')) {
        const themeMatch = url.match(/\/themes\/([^\/]+)/);
        if (themeMatch) {
            sourceName = themeMatch[1].replace(/-/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
            sourcePath = url.replace(/.*\/themes\//, 'themes/');
        }
    } else if (url.includes('/wp-includes/') || url.includes('/wp-admin/')) {
        sourceName = 'WordPress Core';
        sourcePath = url.replace(/.*\/(wp-[^\/]+\/.*)/, '$1');
    }

    return { name: sourceName, path: sourcePath };
}

function formatCaller(caller) {
    if (!caller) return '';

    // If already formatted with stack trace, enhance with markup
    if (caller.includes('()') && caller.includes('\n')) {
        return caller
            .split('\n')
            .map(line => {
                line = line.trim();
                if (!line) return line;

                // Escape HTML first
                const escapedLine = window.mtUtils.escapeHtml(line);

                // Function/method calls (lines that end with ())
                if (line.endsWith('()')) {
                    return `<span class="function-name">${escapedLine}</span>`;
                }
                // File paths (lines that contain : and file extensions)
                else if (line.includes(':') && (line.includes('.php') || line.includes('wp-'))) {
                    return `<span class="file-path">${escapedLine}</span>`;
                }
                return escapedLine;
            })
            .join('\n');
    }

    // Legacy format: replace commas with line breaks and clean up
    return window.mtUtils.escapeHtml(caller
        .replace(/,\s*/g, '\n')
        .replace(/\s+/g, ' ')
        .trim());
}

/**
 * Update log info display with fresh data
 */
function updateLogInfo() {
    jQuery.post(ajaxurl, {
        action: 'mt_get_log_info',
        nonce: mtToolkit.nonce
    }, function(response) {
        if (response.success && response.data) {
            const logInfo = response.data;
            const infoElement = document.querySelector('.mt-logs-info');

            if (infoElement && logInfo.query_log_file_exists) {
                infoElement.innerHTML = `
                    <span class="mt-log-size">
                        <span class="dashicons dashicons-media-text"></span>
                        <?php _e('Current:', 'mt'); ?> ${logInfo.query_log_file_size}
                    </span>
                    ${logInfo.query_log_total_size ? `
                    <span class="mt-log-total-size">
                        <span class="dashicons dashicons-database"></span>
                        <?php _e('Total (with backups):', 'mt'); ?> ${logInfo.query_log_total_size}
                    </span>` : ''}
                    ${logInfo.query_log_max_size ? `
                    <span class="mt-log-max-size">
                        <span class="dashicons dashicons-info"></span>
                        <?php _e('Rotation at:', 'mt'); ?> ${logInfo.query_log_max_size}
                    </span>` : ''}
                `;
            } else if (infoElement) {
                infoElement.innerHTML = `
                    <span class="mt-no-logs">
                        <span class="dashicons dashicons-info"></span>
                        <?php _e('No query log file found', 'mt'); ?>
                    </span>
                `;
            }
        }
    });
}

// Auto-load query logs when page loads
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('mt-query-logs-content')) {
        loadQueryLogs();
        initializeQueryLogsPage();
    }
});

function initializeQueryLogsPage() {
    // Refresh query logs
    document.getElementById('refresh-query-logs').addEventListener('click', function() {
        loadQueryLogs();
    });

    // Clear query logs
    document.getElementById('clear-query-logs').addEventListener('click', function() {
        if (!confirm(<?php echo json_encode(__('Are you sure you want to clear all query logs?', 'mt')); ?>)) {
            return;
        }

        const logsLoading = document.querySelector('.mt-logs-loading');
        logsLoading.style.display = 'block';

        jQuery.post(ajaxurl, {
            action: 'mt_clear_query_log',
            nonce: mtToolkit.nonce
        }, function(response) {
            logsLoading.style.display = 'none';

            if (response.success) {
                window.mtShowNotice(response.data, 'success');

                // Clear the logs display immediately
                const logsContent = document.getElementById('mt-query-logs-content');
                if (logsContent) {
                    logsContent.innerHTML = '<div class="mt-no-logs-message"><p><?php _e('No query log entries found.', 'mt'); ?></p></div>';
                }

                // Update log info immediately, then reload for complete refresh
                updateLogInfo();
                setTimeout(() => location.reload(), 1500);
            } else {
                window.mtShowNotice(response.data || <?php echo json_encode(__('Error occurred', 'mt')); ?>, 'error');
            }
        });
    });

    // Cleanup old query logs
    document.getElementById('cleanup-query-logs').addEventListener('click', function() {
        if (!confirm(<?php echo json_encode(__('Are you sure you want to cleanup old backup log files?', 'mt')); ?>)) {
            return;
        }

        const logsLoading = document.querySelector('.mt-logs-loading');
        logsLoading.style.display = 'block';

        jQuery.post(ajaxurl, {
            action: 'mt_cleanup_query_logs',
            nonce: mtToolkit.nonce
        }, function(response) {
            logsLoading.style.display = 'none';

            if (response.success) {
                window.mtShowNotice(response.data, 'success');

                // Update log info immediately, then reload for complete refresh
                updateLogInfo();
                setTimeout(() => location.reload(), 1500);
            } else {
                window.mtShowNotice(response.data || <?php echo json_encode(__('Error occurred', 'mt')); ?>, 'error');
            }
        });
    });

    // Download query logs
    document.getElementById('download-query-logs').addEventListener('click', function() {
        const downloadUrl = ajaxurl + '?action=mt_download_query_logs&nonce=' + mtToolkit.nonce;
        const link = document.createElement('a');
        link.href = downloadUrl;
        link.download = 'query-logs-' + new Date().toISOString().slice(0, 10) + '.txt';
        link.click();
    });

    // Filter functionality
    document.getElementById('query-time-filter').addEventListener('change', filterQueryLogs);
    document.getElementById('query-type-filter').addEventListener('change', filterQueryLogs);
    document.getElementById('query-slow-filter').addEventListener('change', filterQueryLogs);
    document.getElementById('query-search').addEventListener('input', window.mtUtils.debounce(filterQueryLogs, 300));

    // Clear filters
    document.getElementById('clear-query-filters').addEventListener('click', function() {
        document.getElementById('query-time-filter').value = '';
        document.getElementById('query-type-filter').value = '';
        document.getElementById('query-slow-filter').value = '';
        document.getElementById('query-search').value = '';
        filterQueryLogs();
    });
}

function loadQueryLogs() {
    const logsContent = document.getElementById('mt-query-logs-content');
    const logsLoading = document.querySelector('.mt-logs-loading');

    if (!logsContent) return;

    logsLoading.style.display = 'block';
    logsContent.style.display = 'none';

    jQuery.post(ajaxurl, {
        action: 'mt_get_query_logs',
        nonce: mtToolkit.nonce
    }, function(response) {
        logsLoading.style.display = 'none';

        if (response.success && response.data) {
            displayQueryLogs(response.data);
            logsContent.style.display = 'block';
        } else {
            logsContent.innerHTML = '<div class="notice notice-error"><p>' + <?php echo json_encode(__('Error occurred', 'mt')); ?> + '</p></div>';
            logsContent.style.display = 'block';
        }
    });
}

function displayQueryLogs(logEntries) {
    const logsContent = document.getElementById('mt-query-logs-content');

    if (!logEntries || logEntries.length === 0) {
        logsContent.innerHTML = '<div class="mt-no-logs-message"><p>' + <?php echo json_encode(__('No query log entries found.', 'mt')); ?> + '</p></div>';
        return;
    }

    let html = '<div class="mt-logs-list">';

    logEntries.forEach(function(entry, index) {
        const timeFormatted = new Date(entry.timestamp).toLocaleString();
        const entryId = 'query-entry-' + index;

        html += '<div class="mt-query-log-entry" data-entry="' + index + '">';

        // Collapsible header
        html += '<div class="query-log-header" data-toggle="' + entryId + '">';
        html += '<div class="query-log-meta">';
        html += '<span class="dashicons dashicons-arrow-right-alt2 toggle-icon"></span>';
        html += '<span class="query-log-time">' + timeFormatted + '</span>';

        // Parse URL source
        const urlSource = parseUrlSource(entry.url);
        html += '<div class="query-log-url">';
        html += '<span class="source-name">' + window.mtUtils.escapeHtml(urlSource.name) + '</span>';
        html += '<span class="source-path">' + window.mtUtils.escapeHtml(urlSource.path) + '</span>';
        html += '</div>';

        html += '</div>';
        html += '<div class="query-log-stats">';
        html += '<span class="query-count">' + entry.total_queries + ' queries</span>';
        html += '<span class="query-time">' + entry.total_time + '</span>';
        html += '<span class="query-memory">' + entry.memory_usage + '</span>';
        html += '</div>';
        html += '</div>';

        // Collapsible content (initially hidden)
        html += '<div class="query-log-details" id="' + entryId + '" style="display: none;">';
        html += '<div class="query-loading" style="display: none;">';
        html += '<div class="mt-spinner"></div>';
        html += '<p>Loading query details...</p>';
        html += '</div>';
        html += '</div>';

        html += '</div>';
    });

    html += '</div>';

    logsContent.innerHTML = html;

    // Add click handlers for collapsible headers
    document.querySelectorAll('.query-log-header[data-toggle]').forEach(function(header) {
        header.addEventListener('click', function() {
            const targetId = this.getAttribute('data-toggle');
            const targetDiv = document.getElementById(targetId);
            const icon = this.querySelector('.toggle-icon');
            const entryIndex = this.parentElement.getAttribute('data-entry');

            if (targetDiv.style.display === 'none') {
                // Show details - load via AJAX if not already loaded
                if (!targetDiv.hasAttribute('data-loaded')) {
                    loadQueryDetails(entryIndex, targetId);
                } else {
                    targetDiv.style.display = 'block';
                }
                icon.className = 'dashicons dashicons-arrow-down-alt2 toggle-icon';
            } else {
                // Hide details
                targetDiv.style.display = 'none';
                icon.className = 'dashicons dashicons-arrow-right-alt2 toggle-icon';
            }
        });
    });
}

function loadQueryDetails(entryIndex, targetId) {
    const targetDiv = document.getElementById(targetId);
    const loadingDiv = targetDiv.querySelector('.query-loading');

    // Show loading
    loadingDiv.style.display = 'block';
    targetDiv.style.display = 'block';

    // Get entry data from global variable or reload
    jQuery.post(ajaxurl, {
        action: 'mt_get_query_logs',
        nonce: mtToolkit.nonce
    }, function(response) {
        if (response.success && response.data && response.data[entryIndex]) {
            const entry = response.data[entryIndex];
            displayQueryDetailsTable(entry, targetId);
            targetDiv.setAttribute('data-loaded', 'true');
        } else {
            targetDiv.innerHTML = '<p>Error loading query details.</p>';
        }
        loadingDiv.style.display = 'none';
    });
}

function displayQueryDetailsTable(entry, targetId) {
    const targetDiv = document.getElementById(targetId);

    if (!entry.queries || entry.queries.length === 0) {
        targetDiv.innerHTML = '<p>No query details available.</p>';
        return;
    }

    let html = '<table class="query-log-table">';
    html += '<thead>';
    html += '<tr>';
    html += '<th>No</th>';
    html += '<th>Time</th>';
    html += '<th>Query</th>';
    html += '<th>Caller Stack</th>';
    html += '</tr>';
    html += '</thead>';
    html += '<tbody>';

    entry.queries.forEach(function(query, index) {
        const rowClass = query.is_slow ? 'slow-query' : '';
        const queryNumber = query.number ? query.number.replace('Query #', '') : (index + 1);

        html += '<tr class="' + rowClass + '" data-query-type="' + getQueryType(query.sql) + '">';
        html += '<td class="query-number">' + queryNumber + '</td>';
        html += '<td class="query-time">';
        if (query.is_slow) {
            html += '<span class="slow-indicator" title="Slow Query">⚠️</span> ';
        }
        html += query.time + '</td>';
        html += '<td class="query-sql">';
        html += '<div class="sql-container">';
        html += '<code>' + window.mtUtils.escapeHtml(query.sql) + '</code>';
        html += '<button type="button" class="button-link copy-sql" title="Copy SQL">';
        html += '<span class="dashicons dashicons-admin-page"></span>';
        html += '</button>';
        html += '</div>';
        html += '</td>';
        html += '<td class="query-caller">' + formatCaller(query.caller) + '</td>';
        html += '</tr>';
    });

    html += '</tbody>';
    html += '</table>';

    targetDiv.innerHTML = html;

    // Add copy functionality
    targetDiv.querySelectorAll('.copy-sql').forEach(function(button) {
        button.addEventListener('click', function() {
            const sql = this.previousElementSibling.textContent;
            navigator.clipboard.writeText(sql).then(function() {
                window.mtShowNotice('SQL copied to clipboard', 'success');
            });
        });
    });
}

function getQueryType(sql) {
    const sqlUpper = sql.toUpperCase().trim();
    if (sqlUpper.startsWith('SELECT')) return 'SELECT';
    if (sqlUpper.startsWith('INSERT')) return 'INSERT';
    if (sqlUpper.startsWith('UPDATE')) return 'UPDATE';
    if (sqlUpper.startsWith('DELETE')) return 'DELETE';
    return 'OTHER';
}

function filterQueryLogs() {
    const timeFilter = document.getElementById('query-time-filter').value;
    const typeFilter = document.getElementById('query-type-filter').value;
    const slowFilter = document.getElementById('query-slow-filter').value;
    const searchTerm = document.getElementById('query-search').value.toLowerCase();

    document.querySelectorAll('.mt-query-log-entry').forEach(function(entry) {
        let show = true;

        // Time filter (would require more complex logic with actual timestamps)
        // For now, just implement search and type filters

        // Search filter
        if (searchTerm) {
            const entryText = entry.textContent.toLowerCase();
            if (entryText.indexOf(searchTerm) === -1) {
                show = false;
            }
        }

        // Type filter (check queries in details if loaded)
        if (typeFilter) {
            const queries = entry.querySelectorAll('tr[data-query-type]');
            if (queries.length > 0) {
                let hasMatchingType = false;
                queries.forEach(function(queryRow) {
                    if (queryRow.getAttribute('data-query-type') === typeFilter) {
                        hasMatchingType = true;
                    }
                });
                if (!hasMatchingType) {
                    show = false;
                }
            }
        }

        // Slow query filter
        if (slowFilter) {
            const slowQueries = entry.querySelectorAll('.slow-query');
            if (slowFilter === 'slow' && slowQueries.length === 0) {
                show = false;
            } else if (slowFilter === 'fast' && slowQueries.length > 0) {
                show = false;
            }
        }

        entry.style.display = show ? 'block' : 'none';
    });
}
</script>
