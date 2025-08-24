<?php
/**
 * SMTP Logs Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$plugin = MT_Plugin::get_instance();
$smtp_service = $plugin->get_service('smtp_logger');
$smtp_status = $smtp_service->get_logging_status();
?>

<div class="wrap">
    <h1><?php _e('SMTP Logs', 'morden-toolkit'); ?></h1>
    <p class="description">
        <?php _e('View email logs sent through WordPress. Enable logging to start recording email activity.', 'morden-toolkit'); ?>
    </p>

    <?php if (!$smtp_status['enabled']): ?>
    <div class="notice notice-warning">
        <p>
            <strong><?php _e('SMTP logging is not enabled!', 'morden-toolkit'); ?></strong>
            <?php _e('Email logs are not being recorded. ', 'morden-toolkit'); ?>
            <button type="button" id="enable-smtp-logging" class="button button-primary">
                <?php _e('Enable SMTP Logging', 'morden-toolkit'); ?>
            </button>
        </p>
    </div>
    <?php endif; ?>

    <div class="mt-logs-header <?php echo !$smtp_status['enabled'] ? 'disabled' : ''; ?>">
        <div class="mt-logs-actions">
            <button type="button" id="refresh-smtp-logs" class="button" <?php echo !$smtp_status['enabled'] ? 'disabled' : ''; ?>>
                <span class="dashicons dashicons-update"></span>
                <?php _e('Refresh', 'morden-toolkit'); ?>
            </button>
            <button type="button" id="clear-smtp-logs" class="button" title="<?php _e('Clear current day logs', 'morden-toolkit'); ?>" <?php echo !$smtp_status['enabled'] ? 'disabled' : ''; ?>>
                <span class="dashicons dashicons-trash"></span>
                <?php _e('Clear', 'morden-toolkit'); ?>
            </button>
            <button type="button" id="cleanup-smtp-logs" class="button" title="<?php _e('Remove old log files', 'morden-toolkit'); ?>" <?php echo !$smtp_status['enabled'] ? 'disabled' : ''; ?>>
                <span class="dashicons dashicons-admin-tools"></span>
                <?php _e('Cleanup', 'morden-toolkit'); ?>
            </button>
            <button type="button" id="download-smtp-logs" class="button" <?php echo !$smtp_status['enabled'] ? 'disabled' : ''; ?>>
                <span class="dashicons dashicons-download"></span>
                <?php _e('Download', 'morden-toolkit'); ?>
            </button>
        </div>

        <div class="mt-logs-info">
            <?php if ($smtp_status['current_log_exists']): ?>
            <span class="mt-log-size">
                <span class="dashicons dashicons-media-text"></span>
                <?php _e('Today:', 'morden-toolkit'); ?> <?php echo esc_html($smtp_status['current_log_size']); ?>
            </span>
            <?php else: ?>
            <span class="mt-no-logs">
                <span class="dashicons dashicons-info"></span>
                <?php _e('No log file for today', 'morden-toolkit'); ?>
            </span>
            <?php endif; ?>
        </div>
    </div>

    <div class="mt-logs-filters <?php echo !$smtp_status['enabled'] ? 'disabled' : ''; ?>">
        <div class="mt-filter-group">
            <label for="smtp-date-filter"><?php _e('Date:', 'morden-toolkit'); ?></label>
            <select id="smtp-date-filter" <?php echo !$smtp_status['enabled'] ? 'disabled' : ''; ?>>
                <option value=""><?php _e('Today', 'morden-toolkit'); ?></option>
                <?php foreach ($smtp_status['available_files'] as $file): ?>
                <option value="<?php echo esc_attr($file['date']); ?>">
                    <?php echo esc_html($file['formatted_date']); ?> (<?php echo esc_html($file['size_formatted']); ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mt-filter-group">
            <label for="smtp-status-filter"><?php _e('Status:', 'morden-toolkit'); ?></label>
            <select id="smtp-status-filter" <?php echo !$smtp_status['enabled'] ? 'disabled' : ''; ?>>
                <option value=""><?php _e('All Status', 'morden-toolkit'); ?></option>
                <option value="sent"><?php _e('Sent', 'morden-toolkit'); ?></option>
                <option value="failed"><?php _e('Failed', 'morden-toolkit'); ?></option>
                <option value="queued"><?php _e('Queued', 'morden-toolkit'); ?></option>
            </select>
        </div>

        <div class="mt-filter-group">
            <label for="smtp-search"><?php _e('Search:', 'morden-toolkit'); ?></label>
            <input type="text" id="smtp-search" placeholder="<?php _e('Search sender, recipient, or subject...', 'morden-toolkit'); ?>" <?php echo !$smtp_status['enabled'] ? 'disabled' : ''; ?>>
        </div>

        <div class="mt-filter-group">
            <button type="button" id="clear-smtp-filters" class="button" <?php echo !$smtp_status['enabled'] ? 'disabled' : ''; ?>>
                <?php _e('Clear Filters', 'morden-toolkit'); ?>
            </button>
        </div>
    </div>

    <div class="mt-smtp-logs-container">
        <div id="mt-smtp-logs-viewer" class="mt-logs-viewer">
            <?php if (!$smtp_status['current_log_exists'] && !$smtp_status['enabled']): ?>
            <div class="mt-no-logs-message">
                <div class="dashicons dashicons-info"></div>
                <h3><?php _e('No SMTP Logs Found', 'morden-toolkit'); ?></h3>
                <p><?php _e('SMTP logging is not enabled. Email activity is not being recorded.', 'morden-toolkit'); ?></p>
                <p>
                    <button type="button" id="enable-smtp-logging-2" class="button button-primary">
                        <?php _e('Enable SMTP Logging', 'morden-toolkit'); ?>
                    </button>
                </p>
            </div>
            <?php else: ?>
            <div class="mt-logs-loading">
                <div class="mt-spinner"></div>
                <p><?php _e('Loading SMTP logs...', 'morden-toolkit'); ?></p>
            </div>
            <div id="mt-smtp-logs-content" style="display: none;"></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Helper functions for formatting
function formatSmtpStatus(status) {
    const statusMap = {
        'sent': { class: 'success', text: '<?php _e('Sent', 'morden-toolkit'); ?>' },
        'failed': { class: 'error', text: '<?php _e('Failed', 'morden-toolkit'); ?>' },
        'queued': { class: 'warning', text: '<?php _e('Queued', 'morden-toolkit'); ?>' }
    };

    return statusMap[status] || { class: 'info', text: status };
}

function formatSmtpRecipients(recipients) {
    if (Array.isArray(recipients)) {
        return recipients.join(', ');
    }
    return recipients || '';
}

function formatSmtpDate(timestamp) {
    try {
        const date = new Date(timestamp * 1000); // Convert Unix timestamp
        return date.toLocaleString();
    } catch (e) {
        return timestamp;
    }
}

// Helper function to format bytes
function formatBytes(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Auto-load SMTP logs when page loads
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('mt-smtp-logs-content')) {
        loadSmtpLogs();
        initializeSmtpLogsPage();
    }
});

function initializeSmtpLogsPage() {
    // SMTP logging toggle (only if exists on page)
    const smtpToggle = document.getElementById('smtp-logging-toggle');
    if (smtpToggle) {
        smtpToggle.addEventListener('change', function() {
            const enabled = this.checked;

            jQuery.post(ajaxurl, {
                action: 'mt_toggle_smtp_logging',
                enabled: enabled,
                nonce: mtToolkit.nonce
            }, function(response) {
                if (response.success) {
                    window.mtShowNotice(response.data.message, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    window.mtShowNotice(response.data || 'Failed to toggle SMTP logging', 'error');
                }
            });
        });
    }

    // Enable logging buttons
    const enableButtons = ['#enable-smtp-logging', '#enable-smtp-logging-2'];
    enableButtons.forEach(selector => {
        const btn = document.querySelector(selector);
        if (btn) {
            btn.addEventListener('click', function() {
                // Redirect to debug management tab to enable SMTP logging
                window.location.href = 'tools.php?page=mt&tab=debug#smtp-logging';
            });
        }
    });

    // Refresh SMTP logs
    document.getElementById('refresh-smtp-logs').addEventListener('click', function() {
        if (this.disabled || !<?php echo json_encode($smtp_status['enabled']); ?>) {
            return false;
        }
        loadSmtpLogs();
    });

    // Clear SMTP logs
    document.getElementById('clear-smtp-logs').addEventListener('click', function() {
        if (this.disabled || !<?php echo json_encode($smtp_status['enabled']); ?>) {
            return false;
        }
        if (!confirm(<?php echo json_encode(__('Are you sure you want to clear today\'s SMTP logs?', 'morden-toolkit')); ?>)) {
            return;
        }

        const logsLoading = document.querySelector('.mt-logs-loading');
        logsLoading.style.display = 'block';

        jQuery.post(ajaxurl, {
            action: 'mt_clear_smtp_logs',
            nonce: mtToolkit.nonce
        }, function(response) {
            logsLoading.style.display = 'none';

            if (response.success) {
                window.mtShowNotice(response.data, 'success');
                loadSmtpLogs();
            } else {
                window.mtShowNotice(response.data || <?php echo json_encode(__('Error occurred', 'morden-toolkit')); ?>, 'error');
            }
        });
    });

    // Cleanup SMTP logs
    document.getElementById('cleanup-smtp-logs').addEventListener('click', function() {
        if (this.disabled || !<?php echo json_encode($smtp_status['enabled']); ?>) {
            return false;
        }
        const keepDays = prompt('<?php _e('Keep logs for how many days?', 'morden-toolkit'); ?>', '30');
        if (!keepDays || isNaN(keepDays)) return;

        const logsLoading = document.querySelector('.mt-logs-loading');
        logsLoading.style.display = 'block';

        jQuery.post(ajaxurl, {
            action: 'mt_cleanup_smtp_logs',
            keep_days: parseInt(keepDays),
            nonce: mtToolkit.nonce
        }, function(response) {
            logsLoading.style.display = 'none';

            if (response.success) {
                window.mtShowNotice(response.data, 'success');
                location.reload();
            } else {
                window.mtShowNotice(response.data || <?php echo json_encode(__('Error occurred', 'morden-toolkit')); ?>, 'error');
            }
        });
    });

    // Download SMTP logs
    document.getElementById('download-smtp-logs').addEventListener('click', function() {
        if (this.disabled || !<?php echo json_encode($smtp_status['enabled']); ?>) {
            return false;
        }
        const selectedDate = document.getElementById('smtp-date-filter').value || '<?php echo date('dmY'); ?>';
        const downloadUrl = ajaxurl + '?action=mt_download_smtp_logs&date=' + selectedDate + '&nonce=' + mtToolkit.nonce;
        const link = document.createElement('a');
        link.href = downloadUrl;
        link.download = 'smtp-logs-' + selectedDate + '.log';
        link.click();
    });

    // Filter functionality
    if (<?php echo json_encode($smtp_status['enabled']); ?>) {
        document.getElementById('smtp-date-filter').addEventListener('change', loadSmtpLogs);
        document.getElementById('smtp-status-filter').addEventListener('change', filterSmtpLogs);
        document.getElementById('smtp-search').addEventListener('input', window.mtUtils.debounce(filterSmtpLogs, 300));

        // Clear filters
        document.getElementById('clear-smtp-filters').addEventListener('click', function() {
            document.getElementById('smtp-date-filter').value = '';
            document.getElementById('smtp-status-filter').value = '';
            document.getElementById('smtp-search').value = '';
            loadSmtpLogs();
        });
    }
}

function loadSmtpLogs() {
    const logsContent = document.getElementById('mt-smtp-logs-content');
    const logsLoading = document.querySelector('.mt-logs-loading');

    if (!logsContent) return;

    // Check if SMTP logging is enabled
    if (!<?php echo json_encode($smtp_status['enabled']); ?>) {
        logsLoading.style.display = 'none';
        logsContent.innerHTML = '<div class="notice notice-warning"><p><?php _e('SMTP logging is disabled. Enable it in the Debug Management tab to start recording email activity.', 'morden-toolkit'); ?></p></div>';
        logsContent.style.display = 'block';
        return;
    }

    logsLoading.style.display = 'block';
    logsContent.style.display = 'none';

    const selectedDate = document.getElementById('smtp-date-filter').value;

    jQuery.post(ajaxurl, {
        action: 'mt_get_smtp_logs',
        date: selectedDate,
        nonce: mtToolkit.nonce
    }, function(response) {
        logsLoading.style.display = 'none';

        if (response.success && response.data) {
            displaySmtpLogs(response.data);
            logsContent.style.display = 'block';
        } else {
            logsContent.innerHTML = '<div class="notice notice-error"><p>' + <?php echo json_encode(__('Error occurred', 'morden-toolkit')); ?> + '</p></div>';
            logsContent.style.display = 'block';
        }
    });
}

function displaySmtpLogs(logEntries) {
    const logsContent = document.getElementById('mt-smtp-logs-content');

    if (!logEntries || logEntries.length === 0) {
        logsContent.innerHTML = '<div class="mt-no-logs-message"><p>' + <?php echo json_encode(__('No SMTP log entries found.', 'morden-toolkit')); ?> + '</p></div>';
        return;
    }

    let html = '<div class="mt-logs-list">';

    logEntries.forEach(function(entry, index) {
        const entryId = 'smtp-entry-' + index;
        const status = formatSmtpStatus(entry.status);
        const timeFormatted = formatSmtpDate(entry.ts);

        html += '<div class="mt-smtp-log-entry" data-entry="' + index + '" data-status="' + entry.status + '">';

        // Collapsible header - Sender and Subject on left, Status and Date on right
        html += '<div class="smtp-log-header" data-toggle="' + entryId + '">';
        html += '<div class="smtp-log-main">';
        html += '<span class="dashicons dashicons-arrow-right-alt2 toggle-icon"></span>';
        html += '<div class="smtp-log-info">';
        html += '<div class="smtp-sender"><strong>' + window.mtUtils.escapeHtml(entry.from || entry.mailfrom) + '</strong></div>';
        html += '<div class="smtp-subject">' + window.mtUtils.escapeHtml(entry.subject || 'No Subject') + '</div>';
        html += '</div>';
        html += '</div>';
        html += '<div class="smtp-log-meta">';
        html += '<span class="smtp-status status-' + status.class + '">' + status.text + '</span>';
        html += '<span class="smtp-time">' + timeFormatted + '</span>';
        html += '</div>';
        html += '</div>';

        // Collapsible content (initially hidden)
        html += '<div class="smtp-log-details" id="' + entryId + '" style="display: none;">';
        html += '<div class="smtp-details-content">';

        // Recipients
        html += '<div class="detail-row"><strong><?php _e('To:', 'morden-toolkit'); ?></strong> ' + window.mtUtils.escapeHtml(formatSmtpRecipients(entry.to || entry.rcptto)) + '</div>';

        // CC/BCC if present
        if (entry.cc && entry.cc.length > 0) {
            html += '<div class="detail-row"><strong><?php _e('CC:', 'morden-toolkit'); ?></strong> ' + window.mtUtils.escapeHtml(formatSmtpRecipients(entry.cc)) + '</div>';
        }
        if (entry.bcc && entry.bcc.length > 0) {
            html += '<div class="detail-row"><strong><?php _e('BCC:', 'morden-toolkit'); ?></strong> ' + window.mtUtils.escapeHtml(formatSmtpRecipients(entry.bcc)) + '</div>';
        }

        // Message ID and Reply-To
        if (entry.msg_id) {
            html += '<div class="detail-row"><strong><?php _e('Message ID:', 'morden-toolkit'); ?></strong> ' + window.mtUtils.escapeHtml(entry.msg_id) + '</div>';
        }
        if (entry.reply_to) {
            html += '<div class="detail-row"><strong><?php _e('Reply-To:', 'morden-toolkit'); ?></strong> ' + window.mtUtils.escapeHtml(entry.reply_to) + '</div>';
        }

        // Error message if failed
        if (entry.status === 'failed' && (entry.last_reply || entry.error_message)) {
            const errorMessage = entry.error_message || entry.last_reply;
            html += '<div class="detail-row error"><strong><?php _e('Error:', 'morden-toolkit'); ?></strong> ' + window.mtUtils.escapeHtml(errorMessage) + '</div>';
        }

        // Message size
        if (entry.message_size) {
            html += '<div class="detail-row"><strong><?php _e('Message Size:', 'morden-toolkit'); ?></strong> ' + entry.message_size + ' bytes</div>';
        }

        // Email content type
        if (entry.email_content_type) {
            html += '<div class="detail-row"><strong><?php _e('Content Type:', 'morden-toolkit'); ?></strong> ' + window.mtUtils.escapeHtml(entry.email_content_type) + '</div>';
        }

        // Originating IP if enabled (updated field names)
        if ((entry.ip_address && entry.ip_address !== 'IP logging disabled') || (entry.x_originating_ip && entry.x_originating_ip !== 'IP logging disabled')) {
            const ipAddress = entry.ip_address || entry.x_originating_ip;
            html += '<div class="detail-row"><strong><?php _e('Originating IP:', 'morden-toolkit'); ?></strong> ' + window.mtUtils.escapeHtml(ipAddress) + '</div>';
        }

        // Enhanced caller information
        if (entry.caller_info) {
            html += '<div class="detail-section">';
            html += '<strong><?php _e('Email Source:', 'morden-toolkit'); ?></strong>';
            html += '<div class="caller-details">';
            html += '<div><strong><?php _e('Type:', 'morden-toolkit'); ?></strong> ' + window.mtUtils.escapeHtml(entry.caller_info.type || 'Unknown') + '</div>';
            html += '<div><strong><?php _e('Name:', 'morden-toolkit'); ?></strong> ' + window.mtUtils.escapeHtml(entry.caller_info.name || 'Unknown') + '</div>';
            if (entry.caller_info.file) {
                html += '<div><strong><?php _e('File:', 'morden-toolkit'); ?></strong> ' + window.mtUtils.escapeHtml(entry.caller_info.file) + '</div>';
            }
            if (entry.caller_info.line) {
                html += '<div><strong><?php _e('Line:', 'morden-toolkit'); ?></strong> ' + entry.caller_info.line + '</div>';
            }
            if (entry.caller_info.function) {
                html += '<div><strong><?php _e('Function:', 'morden-toolkit'); ?></strong> ' + window.mtUtils.escapeHtml(entry.caller_info.function) + '</div>';
            }
            if (entry.caller_info.class) {
                html += '<div><strong><?php _e('Class:', 'morden-toolkit'); ?></strong> ' + window.mtUtils.escapeHtml(entry.caller_info.class) + '</div>';
            }
            // Plugin/Theme specific data
            if (entry.caller_info.plugin_data && entry.caller_info.plugin_data.version) {
                html += '<div><strong><?php _e('Plugin Version:', 'morden-toolkit'); ?></strong> ' + window.mtUtils.escapeHtml(entry.caller_info.plugin_data.version) + '</div>';
            }
            if (entry.caller_info.theme_data && entry.caller_info.theme_data.version) {
                html += '<div><strong><?php _e('Theme Version:', 'morden-toolkit'); ?></strong> ' + window.mtUtils.escapeHtml(entry.caller_info.theme_data.version) + '</div>';
            }
            html += '</div>';
            html += '</div>';
        }

        // Enhanced WordPress context
        if (entry.wordpress_context) {
            html += '<div class="detail-section">';
            html += '<strong><?php _e('WordPress Context:', 'morden-toolkit'); ?></strong>';
            html += '<div class="context-details">';
            if (entry.wordpress_context.url) {
                html += '<div><strong><?php _e('URL:', 'morden-toolkit'); ?></strong> ' + window.mtUtils.escapeHtml(entry.wordpress_context.url) + '</div>';
            }
            if (entry.wordpress_context.user_id) {
                html += '<div><strong><?php _e('User ID:', 'morden-toolkit'); ?></strong> ' + entry.wordpress_context.user_id + '</div>';
            }
            if (entry.wordpress_context.request_method) {
                html += '<div><strong><?php _e('Request Method:', 'morden-toolkit'); ?></strong> ' + window.mtUtils.escapeHtml(entry.wordpress_context.request_method) + '</div>';
            }
            if (entry.wordpress_context.is_admin !== undefined) {
                html += '<div><strong><?php _e('Admin Request:', 'morden-toolkit'); ?></strong> ' + (entry.wordpress_context.is_admin ? 'Yes' : 'No') + '</div>';
            }
            if (entry.wordpress_context.is_ajax !== undefined) {
                html += '<div><strong><?php _e('AJAX Request:', 'morden-toolkit'); ?></strong> ' + (entry.wordpress_context.is_ajax ? 'Yes' : 'No') + '</div>';
            }
            if (entry.wordpress_context.is_cron !== undefined) {
                html += '<div><strong><?php _e('Cron Request:', 'morden-toolkit'); ?></strong> ' + (entry.wordpress_context.is_cron ? 'Yes' : 'No') + '</div>';
            }
            if (entry.wordpress_context.wp_version) {
                html += '<div><strong><?php _e('WordPress Version:', 'morden-toolkit'); ?></strong> ' + window.mtUtils.escapeHtml(entry.wordpress_context.wp_version) + '</div>';
            }
            if (entry.wordpress_context.php_version) {
                html += '<div><strong><?php _e('PHP Version:', 'morden-toolkit'); ?></strong> ' + window.mtUtils.escapeHtml(entry.wordpress_context.php_version) + '</div>';
            }
            if (entry.wordpress_context.memory_usage) {
                html += '<div><strong><?php _e('Memory Usage:', 'morden-toolkit'); ?></strong> ' + formatBytes(entry.wordpress_context.memory_usage) + '</div>';
            }
            if (entry.wordpress_context.peak_memory) {
                html += '<div><strong><?php _e('Peak Memory:', 'morden-toolkit'); ?></strong> ' + formatBytes(entry.wordpress_context.peak_memory) + '</div>';
            }
            // Legacy caller for backward compatibility
            if (entry.wordpress_context.caller && !entry.caller_info) {
                html += '<div><strong><?php _e('Source:', 'morden-toolkit'); ?></strong> ' + window.mtUtils.escapeHtml(entry.wordpress_context.caller) + '</div>';
            }
            html += '</div>';
            html += '</div>';
        }
        if (entry.email_headers || entry.all_headers) {
            html += '<div class="detail-section">';
            html += '<strong><?php _e('Email Headers:', 'morden-toolkit'); ?></strong>';
            html += '<div class="email-headers-details">';

            // Enhanced headers from email_headers object
            if (entry.email_headers) {
                if (entry.email_headers.return_path) {
                    html += '<div><strong><?php _e('Return-Path:', 'morden-toolkit'); ?></strong> ' + window.mtUtils.escapeHtml(entry.email_headers.return_path) + '</div>';
                }
                if (entry.email_headers.message_id) {
                    html += '<div><strong><?php _e('Message-ID:', 'morden-toolkit'); ?></strong> ' + window.mtUtils.escapeHtml(entry.email_headers.message_id) + '</div>';
                }
                if (entry.email_headers.in_reply_to) {
                    html += '<div><strong><?php _e('In-Reply-To:', 'morden-toolkit'); ?></strong> ' + window.mtUtils.escapeHtml(entry.email_headers.in_reply_to) + '</div>';
                }
                if (entry.email_headers.references) {
                    html += '<div><strong><?php _e('References:', 'morden-toolkit'); ?></strong> ' + window.mtUtils.escapeHtml(entry.email_headers.references) + '</div>';
                }
                if (entry.email_headers.priority) {
                    html += '<div><strong><?php _e('Priority:', 'morden-toolkit'); ?></strong> ' + window.mtUtils.escapeHtml(entry.email_headers.priority) + '</div>';
                }
            }

            // All headers (either from all_headers or email_headers.all_headers)
            const headers = entry.all_headers || entry.email_headers?.all_headers;
            if (headers) {
                for (const [key, value] of Object.entries(headers)) {
                    if (key !== 'all_headers') {  // Avoid recursive display
                        html += '<div><strong>' + window.mtUtils.escapeHtml(key) + ':</strong> ' + window.mtUtils.escapeHtml(value) + '</div>';
                    }
                }
            }

            html += '</div>';
            html += '</div>';
        }
        // Raw headers (collapsible)
        if (entry.headers_raw) {
            html += '<div class="detail-section">';
            html += '<div class="headers-toggle-wrapper">';
            html += '<strong><?php _e('Raw Headers:', 'morden-toolkit'); ?></strong>';
            html += '<button type="button" class="button button-small toggle-headers" data-target="headers-' + index + '" style="margin-left: 10px; font-size: 11px;"><?php _e('Show/Hide', 'morden-toolkit'); ?></button>';
            html += '<button type="button" class="button button-small view-full-headers" data-entry-id="' + index + '" style="margin-left: 5px; font-size: 11px;"><?php _e('View Full', 'morden-toolkit'); ?></button>';
            html += '</div>';
            html += '<div class="headers-content" id="headers-' + index + '" style="display: none;">';
            html += '<pre class="headers-raw">' + window.mtUtils.escapeHtml(entry.headers_raw) + '</pre>';
            html += '</div>';
            html += '</div>';
        }

        // Email content preview (enhanced)
        const emailContent = entry.email_content || entry.message;
        if (emailContent) {
            const contentPreview = emailContent.substring(0, 200) + (emailContent.length > 200 ? '...' : '');
            html += '<div class="detail-section">';
            html += '<strong><?php _e('Email Content:', 'morden-toolkit'); ?></strong>';
            if (emailContent.length > 200) {
                html += '<button type="button" class="button button-small view-full-content" data-entry-id="' + index + '" style="margin-left: 10px; font-size: 11px;"><?php _e('View Full Content', 'morden-toolkit'); ?></button>';
            }
            // Show content type if available
            if (entry.email_content_type) {
                html += '<div style="margin-top: 5px; font-size: 12px; color: #666;"><strong><?php _e('Type:', 'morden-toolkit'); ?></strong> ' + window.mtUtils.escapeHtml(entry.email_content_type) + '</div>';
            }
            if (entry.email_content_html !== undefined) {
                html += '<div style="margin-top: 3px; font-size: 12px; color: #666;"><strong><?php _e('Format:', 'morden-toolkit'); ?></strong> ' + (entry.email_content_html ? 'HTML' : 'Plain Text') + '</div>';
            }
            html += '<div class="message-preview">' + window.mtUtils.escapeHtml(contentPreview) + '</div>';
            html += '</div>';
        }

        // Email attachments (enhanced)
        if (entry.email_attachments && entry.email_attachments.length > 0) {
            html += '<div class="detail-section">';
            html += '<strong><?php _e('Attachments:', 'morden-toolkit'); ?></strong>';
            html += '<div class="attachments-list">';
            entry.email_attachments.forEach(function(attachment) {
                html += '<div class="attachment-item">';
                if (typeof attachment === 'object') {
                    html += '<div><strong><?php _e('File:', 'morden-toolkit'); ?></strong> ' + window.mtUtils.escapeHtml(attachment.file_name || 'Unknown') + '</div>';
                    if (attachment.file_size) {
                        html += '<div><strong><?php _e('Size:', 'morden-toolkit'); ?></strong> ' + formatBytes(attachment.file_size) + '</div>';
                    }
                    if (attachment.mime_type) {
                        html += '<div><strong><?php _e('Type:', 'morden-toolkit'); ?></strong> ' + window.mtUtils.escapeHtml(attachment.mime_type) + '</div>';
                    }
                } else {
                    html += window.mtUtils.escapeHtml(attachment);
                }
                html += '</div>';
            });
            html += '</div>';
            html += '</div>';
        } else if (entry.attachments && entry.attachments.length > 0) {
            // Fallback for legacy attachment format
            html += '<div class="detail-section">';
            html += '<strong><?php _e('Attachments:', 'morden-toolkit'); ?></strong>';
            html += '<div class="attachments-list">';
            entry.attachments.forEach(function(attachment) {
                html += '<div class="attachment-item">' + window.mtUtils.escapeHtml(attachment) + '</div>';
            });
            html += '</div>';
            html += '</div>';
        }

        // Enhanced email headers (consolidated)

        html += '</div>';
        html += '</div>';

        html += '</div>';
    });

    html += '</div>';

    logsContent.innerHTML = html;

    // Add click handlers for collapsible headers
    document.querySelectorAll('.smtp-log-header[data-toggle]').forEach(function(header) {
        header.addEventListener('click', function() {
            const targetId = this.getAttribute('data-toggle');
            const target = document.getElementById(targetId);
            const icon = this.querySelector('.toggle-icon');

            if (target.style.display === 'none' || target.style.display === '') {
                target.style.display = 'block';
                icon.classList.remove('dashicons-arrow-right-alt2');
                icon.classList.add('dashicons-arrow-down-alt2');
            } else {
                target.style.display = 'none';
                icon.classList.remove('dashicons-arrow-down-alt2');
                icon.classList.add('dashicons-arrow-right-alt2');
            }
        });
    });

    // Add click handlers for view full content buttons
    document.querySelectorAll('.view-full-content').forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.stopPropagation();
            const entryId = this.getAttribute('data-entry-id');
            const entry = logEntries[entryId];
            const emailContent = entry.email_content || entry.message || entry.message_body;
            if (entry && emailContent) {
                showFullContentModal(emailContent, entry.subject || entry.email_subject || 'No Subject');
            }
        });
    });

    // Add click handlers for headers toggle buttons
    document.querySelectorAll('.toggle-headers').forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.stopPropagation();
            const targetId = this.getAttribute('data-target');
            const target = document.getElementById(targetId);

            if (target.style.display === 'none' || target.style.display === '') {
                target.style.display = 'block';
                this.textContent = '<?php _e('Hide', 'morden-toolkit'); ?>';
            } else {
                target.style.display = 'none';
                this.textContent = '<?php _e('Show', 'morden-toolkit'); ?>';
            }
        });
    });

    // Add click handlers for view full headers buttons
    document.querySelectorAll('.view-full-headers').forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.stopPropagation();
            const entryId = this.getAttribute('data-entry-id');
            const entry = logEntries[entryId];
            if (entry && entry.headers_raw) {
                showFullContentModal(entry.headers_raw, entry.subject || 'No Subject', 'headers');
            }
        });
    });
}

function filterSmtpLogs() {
    const statusFilter = document.getElementById('smtp-status-filter').value;
    const searchTerm = document.getElementById('smtp-search').value.toLowerCase();

    document.querySelectorAll('.mt-smtp-log-entry').forEach(function(entry) {
        let show = true;

        // Status filter
        if (statusFilter && entry.getAttribute('data-status') !== statusFilter) {
            show = false;
        }

        // Search filter
        if (searchTerm && show) {
            const textContent = entry.textContent.toLowerCase();
            if (!textContent.includes(searchTerm)) {
                show = false;
            }
        }

        entry.style.display = show ? 'block' : 'none';
    });
}

// Function to show full content modal
function showFullContentModal(content, subject, type = 'message') {
    // Remove existing modal if any
    const existingModal = document.getElementById('smtp-full-content-modal');
    if (existingModal) {
        existingModal.remove();
    }

    const title = type === 'headers' ? '<?php _e('Full Email Headers', 'morden-toolkit'); ?>' : '<?php _e('Full Email Content', 'morden-toolkit'); ?>';

    // Create modal
    const modal = document.createElement('div');
    modal.id = 'smtp-full-content-modal';
    modal.className = 'smtp-modal-overlay';

    modal.innerHTML = `
        <div class="smtp-modal">
            <div class="smtp-modal-header">
                <h3>${title}: ${window.mtUtils.escapeHtml(subject)}</h3>
                <button type="button" class="smtp-modal-close" aria-label="<?php _e('Close', 'morden-toolkit'); ?>">&times;</button>
            </div>
            <div class="smtp-modal-body">
                <textarea readonly class="smtp-full-content-textarea">${window.mtUtils.escapeHtml(content)}</textarea>
            </div>
            <div class="smtp-modal-footer">
                <button type="button" class="button" id="copy-content-btn"><?php _e('Copy to Clipboard', 'morden-toolkit'); ?></button>
            </div>
        </div>
    `;

    // Add to body
    document.body.appendChild(modal);

    // Add event listeners
    modal.querySelectorAll('.smtp-modal-close').forEach(function(closeBtn) {
        closeBtn.addEventListener('click', function() {
            modal.remove();
        });
    });

    // Copy to clipboard functionality
    document.getElementById('copy-content-btn').addEventListener('click', function() {
        const textarea = modal.querySelector('.smtp-full-content-textarea');
        textarea.select();
        textarea.setSelectionRange(0, 99999); // For mobile devices

        try {
            document.execCommand('copy');
            this.textContent = '<?php _e('Copied!', 'morden-toolkit'); ?>';
            setTimeout(() => {
                this.textContent = '<?php _e('Copy to Clipboard', 'morden-toolkit'); ?>';
            }, 2000);
        } catch (err) {
            console.error('Failed to copy content:', err);
        }
    });

    // Close modal when clicking outside
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.remove();
        }
    });

    // Close modal with Escape key
    const escapeHandler = function(e) {
        if (e.key === 'Escape') {
            modal.remove();
            document.removeEventListener('keydown', escapeHandler);
        }
    };
    document.addEventListener('keydown', escapeHandler);

    // Focus on textarea
    modal.querySelector('.smtp-full-content-textarea').focus();
}
</script>

<style>
.mt-smtp-log-entry {
    border: 1px solid #ddd;
    margin-bottom: 10px;
    border-radius: 4px;
    background: #fff;
}

.smtp-log-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    cursor: pointer;
    border-bottom: 1px solid #eee;
}

.smtp-log-header:hover {
    background-color: #f9f9f9;
}

.smtp-log-main {
    display: flex;
    align-items: center;
    flex: 1;
}

.smtp-log-info {
    margin-left: 8px;
}

.smtp-sender {
    font-size: 14px;
    color: #2271b1;
}

.smtp-subject {
    font-size: 12px;
    color: #646970;
    margin-top: 2px;
}

.smtp-log-meta {
    display: flex;
    align-items: center;
    gap: 12px;
}

.smtp-status {
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.smtp-status.status-success {
    background: #d1e7dd;
    color: #0a3622;
}

.smtp-status.status-error {
    background: #f8d7da;
    color: #58151c;
}

.smtp-status.status-warning {
    background: #fff3cd;
    color: #664d03;
}

.smtp-time {
    color: #646970;
    font-size: 12px;
}

.smtp-log-details {
    padding: 16px;
    background: #f8f9fa;
    border-top: 1px solid #eee;
}

.detail-row {
    margin-bottom: 8px;
    line-height: 1.4;
}

.detail-row.error {
    color: #d63638;
}

.detail-section {
    margin-top: 12px;
    margin-bottom: 12px;
}

.context-details {
    margin-left: 12px;
    font-size: 12px;
    color: #646970;
}

.message-preview {
    margin-top: 4px;
    padding: 8px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 3px;
    font-family: monospace;
    font-size: 12px;
    max-height: 100px;
    overflow-y: auto;
}

.attachments-list {
    margin-top: 4px;
    padding: 8px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 3px;
    font-family: monospace;
    font-size: 12px;
}

.caller-details {
    margin-top: 4px;
    padding: 8px;
    background: #f8f9fa;
    border: 1px solid #e1e5e9;
    border-radius: 3px;
    font-family: monospace;
    font-size: 12px;
}

.caller-details div {
    padding: 2px 0;
    border-bottom: 1px solid #eee;
}

.caller-details div:last-child {
    border-bottom: none;
}

.email-headers-details {
    margin-top: 4px;
    padding: 8px;
    background: #f0f8ff;
    border: 1px solid #d0e7ff;
    border-radius: 3px;
    font-family: monospace;
    font-size: 12px;
}

.email-headers-details div {
    padding: 2px 0;
    border-bottom: 1px solid #e0f0ff;
}

.email-headers-details div:last-child {
    border-bottom: none;
}

.context-details {
    margin-top: 4px;
    padding: 8px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 3px;
    font-family: monospace;
    font-size: 12px;
}

.context-details div {
    padding: 2px 0;
    border-bottom: 1px solid #eee;
}

.context-details div:last-child {
    border-bottom: none;
}

.attachment-item {
    padding: 4px 0;
    border-bottom: 1px solid #eee;
    background: #fafafa;
    margin: 2px 0;
    padding: 6px 8px;
    border-radius: 3px;
}

.attachment-item:last-child {
    border-bottom: none;
}

.attachment-item div {
    margin: 2px 0;
    font-size: 11px;
}

.headers-details {
    margin-top: 4px;
    padding: 8px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 3px;
    font-family: monospace;
    font-size: 12px;
}

.headers-details div {
    padding: 2px 0;
    border-bottom: 1px solid #eee;
}

.headers-details div:last-child {
    border-bottom: none;
}

.toggle-icon {
    transition: transform 0.2s;
}

.mt-smtp-toggle {
    margin-right: 16px;
}

/* Disabled state styling when SMTP logging is off */
.mt-logs-header.disabled,
.mt-logs-filters.disabled {
    opacity: 0.5;
    pointer-events: none;
}

.mt-logs-header.disabled .button,
.mt-logs-filters.disabled input,
.mt-logs-filters.disabled select,
.mt-logs-filters.disabled .button {
    cursor: not-allowed;
}

/* Full content modal styles */
.smtp-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.smtp-modal {
    background: #fff;
    border-radius: 4px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    max-width: 90vw;
    max-height: 90vh;
    width: 800px;
    display: flex;
    flex-direction: column;
}

.smtp-modal-header {
    padding: 16px 20px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f8f9fa;
    border-radius: 4px 4px 0 0;
}

.smtp-modal-header h3 {
    margin: 0;
    font-size: 16px;
    color: #23282d;
}

.smtp-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #666;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 2px;
    width: ;
}

.smtp-modal-close:hover {
    background: #f0f0f0;
    color: #333;
}

.smtp-modal-body {
    padding: 20px;
    flex: 1;
    overflow: hidden;
}

.smtp-full-content-textarea {
    width: 100%;
    height: 400px;
    font-family: 'Courier New', monospace;
    font-size: 12px;
    line-height: 1.4;
    border: 1px solid #ddd;
    border-radius: 3px;
    padding: 12px;
    resize: vertical;
    background: #fafafa;
    color: #333;
}

.smtp-full-content-textarea:focus {
    outline: none;
    border-color: #007cba;
    box-shadow: 0 0 0 1px #007cba;
}

.smtp-modal-footer {
    padding: 16px 20px;
    border-top: 1px solid #ddd;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    background: #f8f9fa;
    border-radius: 0 0 4px 4px;
}

.view-full-content {
    background: #f0f0f0;
    border: 1px solid #ccc;
    color: #555;
    padding: 2px 8px;
    border-radius: 3px;
    cursor: pointer;
    text-decoration: none;
}

.view-full-content:hover {
    background: #e0e0e0;
    border-color: #999;
    color: #333;
}

/* Headers section styling */
.headers-toggle-wrapper {
    display: flex;
    align-items: center;
    margin-bottom: 8px;
}

.headers-content {
    margin-top: 8px;
}

.headers-raw {
    background: #f8f8f8;
    border: 1px solid #ddd;
    border-radius: 3px;
    padding: 10px;
    font-family: 'Courier New', monospace;
    font-size: 11px;
    line-height: 1.4;
    color: #333;
    white-space: pre-wrap;
    word-wrap: break-word;
    max-height: 200px;
    overflow-y: auto;
    margin: 0;
}

.toggle-headers {
    background: #f0f0f0;
    border: 1px solid #ccc;
    color: #555;
    padding: 2px 8px;
    border-radius: 3px;
    cursor: pointer;
    text-decoration: none;
}

.toggle-headers:hover {
    background: #e0e0e0;
    border-color: #999;
    color: #333;
}

.view-full-headers {
    background: #f0f0f0;
    border: 1px solid #ccc;
    color: #555;
    padding: 2px 8px;
    border-radius: 3px;
    cursor: pointer;
    text-decoration: none;
}

.view-full-headers:hover {
    background: #e0e0e0;
    border-color: #999;
    color: #333;
}

/* Responsive modal */
@media (max-width: 768px) {
    .smtp-modal {
        width: 95vw;
        height: 90vh;
        margin: 0;
    }

    .smtp-full-content-textarea {
        height: 300px;
    }

    .smtp-modal-header h3 {
        font-size: 14px;
    }

    .smtp-modal-footer {
        flex-direction: column;
    }

    .smtp-modal-footer .button {
        width: 100%;
        margin: 0;
    }
}
</style>