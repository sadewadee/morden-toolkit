<?php
/**
 * SMTP Logs Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$plugin = MT_Plugin::get_instance();
$smtp_service = $plugin->get_service('smtp_logger');
$smtp_status = $smtp_service->get_smtp_status();
?>

<div class="wrap">
    <h1><?php _e('SMTP Logs', 'morden-toolkit'); ?></h1>
    <p class="description">
        <?php _e('View email logs sent through WordPress wp_mail() function. SMTP logging must be enabled to record emails.', 'morden-toolkit'); ?>
    </p>

    <?php if (!$smtp_status['smtp_logging_enabled']): ?>
    <div class="notice notice-warning">
        <p>
            <strong><?php _e('SMTP Logging is not enabled!', 'morden-toolkit'); ?></strong>
            <?php _e('Email logs are not being recorded. ', 'morden-toolkit'); ?>
            <a href="<?php echo admin_url('tools.php?page=mt'); ?>" class="button button-primary">
                <?php _e('Enable SMTP Logging', 'morden-toolkit'); ?>
            </a>
        </p>
    </div>
    <?php endif; ?>

    <div class="mt-logs-header">
        <div class="mt-logs-actions">
            <button type="button" id="refresh-smtp-logs" class="button">
                <span class="dashicons dashicons-update"></span>
                <?php _e('Refresh', 'morden-toolkit'); ?>
            </button>
            <button type="button" id="clear-smtp-logs" class="button">
                <span class="dashicons dashicons-trash"></span>
                <?php _e('Clear', 'morden-toolkit'); ?>
            </button>
            <button type="button" id="download-smtp-logs" class="button">
                <span class="dashicons dashicons-download"></span>
                <?php _e('Download', 'morden-toolkit'); ?>
            </button>
            <button type="button" id="test-email" class="button">
                <span class="dashicons dashicons-email"></span>
                <?php _e('Send Test Email', 'morden-toolkit'); ?>
            </button>
        </div>

        <div class="mt-logs-info">
            <?php if ($smtp_status['total_logs'] > 0): ?>
            <span class="mt-log-total">
                <span class="dashicons dashicons-email"></span>
                <?php _e('Total:', 'morden-toolkit'); ?> <?php echo esc_html($smtp_status['total_logs']); ?>
            </span>
            <span class="mt-log-sent">
                <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                <?php _e('Sent:', 'morden-toolkit'); ?> <?php echo esc_html($smtp_status['sent_logs']); ?>
            </span>
            <span class="mt-log-failed">
                <span class="dashicons dashicons-dismiss" style="color: #dc3232;"></span>
                <?php _e('Failed:', 'morden-toolkit'); ?> <?php echo esc_html($smtp_status['failed_logs']); ?>
            </span>
            <span class="mt-log-success-rate">
                <span class="dashicons dashicons-chart-line"></span>
                <?php _e('Success Rate:', 'morden-toolkit'); ?> <?php echo esc_html($smtp_status['success_rate']); ?>%
            </span>
            <span class="mt-log-recent">
                <span class="dashicons dashicons-clock"></span>
                <?php _e('Last 24h:', 'morden-toolkit'); ?> <?php echo esc_html($smtp_status['last_24h_count']); ?>
            </span>
            <?php else: ?>
            <span class="mt-no-logs">
                <span class="dashicons dashicons-info"></span>
                <?php _e('No email logs found', 'morden-toolkit'); ?>
            </span>
            <?php endif; ?>
        </div>
    </div>

    <div class="mt-logs-filters">
        <div class="mt-filter-group">
            <label for="smtp-time-filter"><?php _e('Time:', 'morden-toolkit'); ?></label>
            <select id="smtp-time-filter">
                <option value=""><?php _e('All Time', 'morden-toolkit'); ?></option>
                <option value="1h"><?php _e('Last Hour', 'morden-toolkit'); ?></option>
                <option value="24h" selected><?php _e('Last 24 Hours', 'morden-toolkit'); ?></option>
                <option value="7d"><?php _e('Last 7 Days', 'morden-toolkit'); ?></option>
            </select>
        </div>

        <div class="mt-filter-group">
            <label for="smtp-status-filter"><?php _e('Status:', 'morden-toolkit'); ?></label>
            <select id="smtp-status-filter">
                <option value=""><?php _e('All Status', 'morden-toolkit'); ?></option>
                <option value="sent"><?php _e('Sent', 'morden-toolkit'); ?></option>
                <option value="failed"><?php _e('Failed', 'morden-toolkit'); ?></option>
                <option value="queued"><?php _e('Queued', 'morden-toolkit'); ?></option>
            </select>
        </div>

        <div class="mt-filter-group">
            <label for="smtp-search"><?php _e('Search:', 'morden-toolkit'); ?></label>
            <input type="text" id="smtp-search" placeholder="<?php _e('Search in email, subject, or message...', 'morden-toolkit'); ?>">
        </div>

        <div class="mt-filter-group">
            <button type="button" id="clear-smtp-filters" class="button">
                <?php _e('Clear Filters', 'morden-toolkit'); ?>
            </button>
        </div>
    </div>

    <div class="mt-logs-container">
        <div id="mt-logs-viewer" class="mt-logs-viewer">
            <?php if (!$smtp_status['smtp_logging_enabled']): ?>
            <div class="mt-no-logs-message">
                <div class="dashicons dashicons-info"></div>
                <h3><?php _e('SMTP Logging Disabled', 'morden-toolkit'); ?></h3>
                <p><?php _e('SMTP logging is not enabled. Email logs are not being recorded.', 'morden-toolkit'); ?></p>
                <p>
                    <a href="<?php echo admin_url('tools.php?page=mt'); ?>" class="button button-primary">
                        <?php _e('Enable SMTP Logging', 'morden-toolkit'); ?>
                    </a>
                </p>
            </div>
            <?php elseif ($smtp_status['total_logs'] == 0): ?>
            <div class="mt-no-logs-message">
                <div class="dashicons dashicons-email"></div>
                <h3><?php _e('No Email Logs Found', 'morden-toolkit'); ?></h3>
                <p><?php _e('No emails have been logged yet. Try sending a test email or wait for WordPress to send emails.', 'morden-toolkit'); ?></p>
                <p>
                    <button type="button" id="send-test-email" class="button button-primary">
                        <span class="dashicons dashicons-email"></span>
                        <?php _e('Send Test Email', 'morden-toolkit'); ?>
                    </button>
                </p>
            </div>
            <?php else: ?>
            <div class="mt-logs-loading">
                <div class="mt-spinner"></div>
                <p><?php _e('Loading SMTP logs...', 'morden-toolkit'); ?></p>
            </div>
            <div id="mt-logs-content" style="display: none;"></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style type="text/css">
/* Isolate email content from dashboard CSS/JS */
.mt-smtp-log-content {
    all: initial !important;
    font-family: 'Courier New', Courier, monospace !important;
    font-size: 12px !important;
    line-height: 1.4 !important;
    color: #333 !important;
    background: #f9f9f9 !important;
    border: 1px solid #ddd !important;
    border-radius: 3px !important;
    padding: 10px !important;
    margin: 5px 0 !important;
    white-space: pre-wrap !important;
    word-wrap: break-word !important;
    overflow-x: auto !important;
    max-height: 300px !important;
    overflow-y: auto !important;
    display: block !important;
    width: 100% !important;
    box-sizing: border-box !important;
    text-align: left !important;
    direction: ltr !important;
    unicode-bidi: normal !important;
}

.mt-smtp-log-content * {
    all: unset !important;
}

.log-detail-item {
    margin-bottom: 10px !important;
}

.log-detail-item strong {
    font-weight: bold !important;
    color: #23282d !important;
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    let currentPage = 1;
    let isLoading = false;

    // Load SMTP logs
    function loadSMTPLogs(page = 1, filters = {}) {
        if (isLoading) return;
        
        isLoading = true;
        $('.mt-logs-loading').show();
        $('#mt-logs-content').hide();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mt_get_smtp_logs',
                page: page,
                filters: filters,
                nonce: '<?php echo wp_create_nonce('mt_smtp_logs_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    displaySMTPLogs(response.data.logs, response.data.pagination);
                } else {
                    $('#mt-logs-content').html('<div class="mt-no-logs-message"><p>' + (response.data || 'Error loading logs') + '</p></div>');
                }
            },
            error: function() {
                $('#mt-logs-content').html('<div class="mt-no-logs-message"><p>Error loading SMTP logs.</p></div>');
            },
            complete: function() {
                isLoading = false;
                $('.mt-logs-loading').hide();
                $('#mt-logs-content').show();
            }
        });
    }

    // Display SMTP logs
    function displaySMTPLogs(logs, pagination) {
        if (!logs || logs.length === 0) {
            $('#mt-logs-content').html('<div class="mt-no-logs-message"><p>No email logs found.</p></div>');
            return;
        }

        let html = '<div class="mt-logs-list">';
        
        logs.forEach(function(log) {
            let statusClass = 'mt-log-entry-' + log.status;
            let statusIcon = log.status === 'sent' ? 'yes-alt' : (log.status === 'failed' ? 'dismiss' : 'clock');
            let statusColor = log.status === 'sent' ? '#46b450' : (log.status === 'failed' ? '#dc3232' : '#ffb900');
            
            // Prepare raw SMTP session data
            let rawSmtpData = {
                to_email: log.to_email,
                from_email: log.from_email || 'Not specified',
                subject: log.subject || 'No subject',
                message: log.message || 'No message content',
                headers: log.headers || 'No headers',
                attachments: log.attachments || 'No attachments',
                status: log.status,
                error_message: log.error_message || 'No error',
                created_at: log.created_at,
                sent_at: log.sent_at || 'Not sent',
                user_id: log.user_id || 'Unknown',
                server_ip: log.server_ip || 'Unknown',
                email_source: log.email_source || 'Unknown',
                mailer: log.mailer || 'Unknown'
            };
            
            html += '<div class="mt-log-entry ' + statusClass + '" data-log-id="' + log.id + '">';
            html += '<div class="log-header" style="cursor: pointer;">';
            
            // Left side: log-to (top) and log-subject (bottom)
            html += '<div class="log-main-info">';
            html += '<div class="log-to">' + log.to_email + '</div>';
            html += '<div class="log-subject">' + (log.subject || 'No subject') + '</div>';
            html += '</div>';
            
            // Right side: log-status (top) and log-time (bottom)
            html += '<div class="log-status-info">';
            html += '<div class="log-status">';
            html += '<span class="dashicons dashicons-' + statusIcon + '" style="color: ' + statusColor + ';"></span>';
            html += '<span class="log-status-text">' + log.status.toUpperCase() + '</span>';
            html += '</div>';
            html += '<div class="log-time">' + log.created_at + '</div>';
            html += '</div>';
            
            html += '</div>';
            
            // Hidden details section
            html += '<div class="log-details" style="display: none;">';
            html += '<div class="log-details-header"><strong>SMTP Session Details</strong></div>';
            html += '<div class="log-detail-item"><strong>To:</strong> ' + rawSmtpData.to_email + '</div>';
            html += '<div class="log-detail-item"><strong>From:</strong> ' + rawSmtpData.from_email + '</div>';
            html += '<div class="log-detail-item"><strong>Subject:</strong> ' + rawSmtpData.subject + '</div>';
            html += '<div class="log-detail-item"><strong>Status:</strong> ' + rawSmtpData.status + '</div>';
            html += '<div class="log-detail-item"><strong>Created:</strong> ' + rawSmtpData.created_at + '</div>';
            html += '<div class="log-detail-item"><strong>Sent:</strong> ' + rawSmtpData.sent_at + '</div>';
            html += '<div class="log-detail-item"><strong>User ID:</strong> ' + rawSmtpData.user_id + '</div>';
            html += '<div class="log-detail-item"><strong>Server IP:</strong> ' + rawSmtpData.server_ip + '</div>';
            html += '<div class="log-detail-item"><strong>Email Source:</strong> ' + rawSmtpData.email_source + '</div>';
            html += '<div class="log-detail-item"><strong>Mailer:</strong> ' + rawSmtpData.mailer + '</div>';
            
            if (log.status === 'failed' && rawSmtpData.error_message !== 'No error') {
                html += '<div class="log-detail-item log-error"><strong>Error:</strong> ' + rawSmtpData.error_message + '</div>';
            }
            
            html += '<div class="log-detail-item"><strong>Headers:</strong><pre class="mt-smtp-log-content">' + rawSmtpData.headers + '</pre></div>';
            html += '<div class="log-detail-item"><strong>Message:</strong><pre class="mt-smtp-log-content">' + rawSmtpData.message + '</pre></div>';
            
            if (rawSmtpData.attachments !== 'No attachments') {
                html += '<div class="log-detail-item"><strong>Attachments:</strong> ' + rawSmtpData.attachments + '</div>';
            }
            
            html += '</div>';
            html += '</div>';
        });
        
        html += '</div>';
        
        // Add pagination if needed
        if (pagination && pagination.total_pages > 1) {
            html += '<div class="mt-logs-pagination">';
            
            if (pagination.current_page > 1) {
                html += '<button class="button" data-page="' + (pagination.current_page - 1) + '">Previous</button>';
            }
            
            html += '<span class="pagination-info">Page ' + pagination.current_page + ' of ' + pagination.total_pages + '</span>';
            
            if (pagination.current_page < pagination.total_pages) {
                html += '<button class="button" data-page="' + (pagination.current_page + 1) + '">Next</button>';
            }
            
            html += '</div>';
        }
        
        $('#mt-logs-content').html(html);
        
        // Add click handler for log headers to toggle details
        $('.log-header').on('click', function() {
            const logEntry = $(this).closest('.mt-log-entry');
            const logDetails = logEntry.find('.log-details');
            
            // Toggle details visibility
            logDetails.slideToggle(300);
            
            // Add/remove expanded class for styling
            logEntry.toggleClass('expanded');
        });
    }

    // Get current filters
    function getCurrentFilters() {
        return {
            time_range: $('#smtp-time-filter').val(),
            status: $('#smtp-status-filter').val(),
            search: $('#smtp-search').val().trim()
        };
    }

    // Event handlers
    $('#refresh-smtp-logs').on('click', function() {
        currentPage = 1;
        loadSMTPLogs(currentPage, getCurrentFilters());
    });

    $('#clear-smtp-logs').on('click', function() {
        if (confirm('Are you sure you want to clear all SMTP logs? This action cannot be undone.')) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'mt_clear_smtp_logs',
                    nonce: '<?php echo wp_create_nonce('mt_smtp_logs_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        currentPage = 1;
                        loadSMTPLogs(currentPage, getCurrentFilters());
                        location.reload(); // Reload to update stats
                    } else {
                        alert('Error clearing logs: ' + (response.data || 'Unknown error'));
                    }
                }
            });
        }
    });

    $('#download-smtp-logs').on('click', function() {
        let filters = getCurrentFilters();
        let params = new URLSearchParams({
            action: 'mt_download_smtp_logs',
            nonce: '<?php echo wp_create_nonce('mt_smtp_logs_nonce'); ?>'
        });
        
        Object.keys(filters).forEach(key => {
            if (filters[key]) {
                params.append('filters[' + key + ']', filters[key]);
            }
        });
        
        window.location.href = ajaxurl + '?' + params.toString();
    });

    $('#test-email, #send-test-email').on('click', function() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mt_send_test_email',
                nonce: '<?php echo wp_create_nonce('mt_smtp_logs_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert('Test email sent successfully!');
                    setTimeout(function() {
                        currentPage = 1;
                        loadSMTPLogs(currentPage, getCurrentFilters());
                    }, 1000);
                } else {
                    alert('Error sending test email: ' + (response.data || 'Unknown error'));
                }
            }
        });
    });

    // Filter change handlers
    $('#smtp-time-filter, #smtp-status-filter').on('change', function() {
        currentPage = 1;
        loadSMTPLogs(currentPage, getCurrentFilters());
    });

    $('#smtp-search').on('keyup', function() {
        clearTimeout(window.searchTimeout);
        window.searchTimeout = setTimeout(function() {
            currentPage = 1;
            loadSMTPLogs(currentPage, getCurrentFilters());
        }, 500);
    });

    $('#clear-smtp-filters').on('click', function() {
        $('#smtp-time-filter').val('24h');
        $('#smtp-status-filter').val('');
        $('#smtp-search').val('');
        currentPage = 1;
        loadSMTPLogs(currentPage, getCurrentFilters());
    });

    // Pagination handler
    $(document).on('click', '.mt-logs-pagination button[data-page]', function() {
        currentPage = parseInt($(this).data('page'));
        loadSMTPLogs(currentPage, getCurrentFilters());
    });

    // Initial load if SMTP logging is enabled and there are logs
    <?php if ($smtp_status['smtp_logging_enabled'] && $smtp_status['total_logs'] > 0): ?>
    loadSMTPLogs(1, getCurrentFilters());
    <?php endif; ?>
});
</script>