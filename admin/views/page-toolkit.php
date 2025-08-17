<?php
/**
 * Main Admin Page Template
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get services
$plugin = MT_Plugin::get_instance();
$debug_service = $plugin->get_service('debug');
$query_monitor_service = $plugin->get_service('query_monitor');
$htaccess_service = $plugin->get_service('htaccess');
$php_config_service = $plugin->get_service('php_config');

// Get current states with error handling
try {
    $debug_status = $debug_service ? $debug_service->get_debug_status() : array('enabled' => false);
    $debug_enabled = isset($debug_status['enabled']) ? $debug_status['enabled'] : false;
} catch (Exception $e) {
    $debug_enabled = false;
    error_log('MT Debug Status Error: ' . $e->getMessage());
}

$query_monitor_enabled = get_option('mt_query_monitor_enabled', false);
$display_errors_on = ini_get('display_errors') == '1' || ini_get('display_errors') === 'On';
$htaccess_info = $htaccess_service->get_htaccess_info();
$htaccess_backups = $htaccess_service->get_backups();
$php_presets = $php_config_service->get_presets();
$current_php_preset = get_option('mt_php_preset', 'medium');
$php_config_method = $php_config_service->get_config_method_info();
$current_php_config = $php_config_service->get_current_config();
$server_memory_info = $php_config_service->get_server_memory_info();
?>

<div class="wrap">
    <h1><?php _e('Morden Toolkit', 'mt'); ?></h1>
    <p class="description">
        <?php _e('Developer tools untuk WordPress: Debug Manager, Query Monitor, Htaccess Editor, PHP Config presets.', 'mt'); ?>
    </p>

    <!-- Tab Navigation -->
    <div class="mt-tab-navigation">
        <button class="mt-tab-btn active" data-tab="debug-management">
            <span class="dashicons dashicons-admin-tools"></span>
            <?php _e('Debug Management', 'mt'); ?>
        </button>
        <button class="mt-tab-btn" data-tab="query-monitor">
            <span class="dashicons dashicons-performance"></span>
            <?php _e('Query Monitor', 'mt'); ?>
        </button>
        <button class="mt-tab-btn" data-tab="file-editor">
            <span class="dashicons dashicons-edit"></span>
            <?php _e('File Editor', 'mt'); ?>
        </button>
        <button class="mt-tab-btn" data-tab="php-config">
            <span class="dashicons dashicons-admin-settings"></span>
            <?php _e('PHP Config', 'mt'); ?>
        </button>
    </div>

    <!-- Tab Contents -->
    <div class="mt-tab-contents">

        <!-- Debug Management Tab -->
        <div id="tab-debug-management" class="mt-tab-content active">
            <div class="mt-card">
                <h2><?php _e('Debug Mode Control', 'mt'); ?></h2>

                <div class="mt-form-section">
                    <label class="mt-toggle-label">
                        <span><?php _e('Enable Debug Mode', 'mt'); ?></span>
                        <div class="mt-toggle-wrapper">
                            <input type="checkbox" id="debug-mode-toggle" <?php checked($debug_enabled); ?>>
                            <div class="mt-toggle <?php echo $debug_enabled ? 'active' : ''; ?>">
                                <div class="mt-toggle-slider"></div>
                            </div>
                        </div>
                    </label>
                </div>

                <div class="mt-debug-settings">
                    <h3><?php _e('Debug Settings', 'mt'); ?></h3>
                    <p class="description"><?php _e('WordPress debug constants configuration', 'mt'); ?></p>
                    <div class="mt-toggle-group" <?php echo !$debug_enabled ? 'data-disabled="true"' : ''; ?>>
                        <div class="mt-toggle-wrapper <?php echo !$debug_enabled ? 'disabled' : ''; ?>">
                            <input type="checkbox" id="wp-debug-log-toggle" <?php checked(defined('WP_DEBUG_LOG') && WP_DEBUG_LOG); ?> <?php disabled(!$debug_enabled); ?>>
                            <div class="mt-toggle <?php echo (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG && $debug_enabled) ? 'active' : ''; ?>">
                                <div class="mt-toggle-slider"></div>
                            </div>
                            <label for="wp-debug-log-toggle" class="mt-toggle-label">
                                <span>WP_DEBUG_LOG</span>
                                <small class="description"><?php _e('Log errors to file', 'mt'); ?></small>
                            </label>
                        </div>
                        <div class="mt-toggle-wrapper <?php echo !$debug_enabled ? 'disabled' : ''; ?>">
                            <input type="checkbox" id="wp-debug-display-toggle" <?php checked(defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY); ?> <?php disabled(!$debug_enabled); ?>>
                            <div class="mt-toggle <?php echo (defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY && $debug_enabled) ? 'active' : ''; ?>">
                                <div class="mt-toggle-slider"></div>
                            </div>
                            <label for="wp-debug-display-toggle" class="mt-toggle-label">
                                <span>WP_DEBUG_DISPLAY</span>
                                <small class="description"><?php _e('Display errors on screen', 'mt'); ?></small>
                            </label>
                        </div>
                        <div class="mt-toggle-wrapper <?php echo !$debug_enabled ? 'disabled' : ''; ?>">
                            <input type="checkbox" id="script-debug-toggle" <?php checked(defined('SCRIPT_DEBUG') && SCRIPT_DEBUG); ?> <?php disabled(!$debug_enabled); ?>>
                            <div class="mt-toggle <?php echo (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG && $debug_enabled) ? 'active' : ''; ?>">
                                <div class="mt-toggle-slider"></div>
                            </div>
                            <label for="script-debug-toggle" class="mt-toggle-label">
                                <span>SCRIPT_DEBUG</span>
                                <small class="description"><?php _e('Use unminified JS/CSS', 'mt'); ?></small>
                            </label>
                        </div>
                        <div class="mt-toggle-wrapper <?php echo !$debug_enabled ? 'disabled' : ''; ?>">
                            <input type="checkbox" id="savequeries-toggle" <?php checked(defined('SAVEQUERIES') && SAVEQUERIES); ?> <?php disabled(!$debug_enabled); ?>>
                            <div class="mt-toggle <?php echo (defined('SAVEQUERIES') && SAVEQUERIES && $debug_enabled) ? 'active' : ''; ?>">
                                <div class="mt-toggle-slider"></div>
                            </div>
                            <label for="savequeries-toggle" class="mt-toggle-label">
                                <span>SAVEQUERIES</span>
                                <small class="description"><?php _e('Save database queries to query.log for analysis', 'mt'); ?></small>
                            </label>
                        </div>
                        <div class="mt-toggle-wrapper <?php echo !$debug_enabled ? 'disabled' : ''; ?>">
                            <input type="checkbox" id="display-errors-toggle" <?php checked($display_errors_on); ?> <?php disabled(!$debug_enabled); ?>>
                            <div class="mt-toggle <?php echo ($display_errors_on && $debug_enabled) ? 'active' : ''; ?>">
                                <div class="mt-toggle-slider"></div>
                            </div>
                            <label for="display-errors-toggle" class="mt-toggle-label">
                                <span>display_errors</span>
                                <small class="description"><?php _e('Display PHP errors on screen', 'mt'); ?></small>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="mt-status-info">
                    <div class="mt-status-item">
                        <span class="mt-status-indicator <?php echo $debug_enabled ? 'active' : 'inactive'; ?>"></span>
                        <span><?php _e('Status:', 'mt'); ?>
                            <?php echo $debug_enabled ? __('Debug Enabled', 'mt') : __('Debug Disabled', 'mt'); ?>
                        </span>
                    </div>
                    <?php if (file_exists(mt_get_debug_log_path())): ?>
                    <div class="mt-status-item">
                        <span class="dashicons dashicons-media-text"></span>
                        <span><?php _e('Debug Log Size:', 'mt'); ?> <?php echo mt_format_bytes(filesize(mt_get_debug_log_path())); ?></span>
                        <a href="<?php echo admin_url('tools.php?page=mt-logs'); ?>" class="button button-small" style="margin-left: 10px;">
                            <?php _e('View Debug Logs', 'mt'); ?>
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php if (defined('SAVEQUERIES') && SAVEQUERIES): ?>
                    <div class="mt-status-item">
                        <span class="dashicons dashicons-database"></span>
                        <span><?php _e('Query Logging:', 'mt'); ?>
                            <?php echo defined('SAVEQUERIES') && SAVEQUERIES ? __('Enabled', 'mt') : __('Disabled', 'mt'); ?>
                        </span>
                        <?php if (file_exists(mt_get_query_log_path())): ?>
                        <span style="margin-left: 10px; color: #646970;">
                            <?php _e('Size:', 'mt'); ?> <?php echo mt_format_bytes(filesize(mt_get_query_log_path())); ?>
                            <small style="margin-left: 5px; color: #007cba;">
                                (<?php _e('Auto-rotates at', 'mt'); ?> <?php echo mt_format_bytes(mt_get_query_log_max_size()); ?>)
                            </small>
                        </span>
                        <?php endif; ?>
                        <a href="<?php echo admin_url('tools.php?page=mt-query-logs'); ?>" class="button button-small" style="margin-left: 10px;">
                            <?php _e('View Query Logs', 'mt'); ?>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Query Monitor Tab -->
        <div id="tab-query-monitor" class="mt-tab-content">
            <div class="mt-card">
                <h2><?php _e('Performance Monitoring', 'mt'); ?></h2>

                <div class="mt-form-section">
                    <label class="mt-toggle-label">
                        <span><?php _e('Enable Performance Bar', 'mt'); ?></span>
                        <div class="mt-toggle-wrapper">
                            <input type="checkbox" id="query-monitor-toggle" <?php checked($query_monitor_enabled); ?>>
                            <div class="mt-toggle <?php echo $query_monitor_enabled ? 'active' : ''; ?>">
                                <div class="mt-toggle-slider"></div>
                            </div>
                        </div>
                    </label>
                    <p class="description">
                        <?php _e('Menampilkan bar performa di bagian bawah halaman untuk user yang login.', 'mt'); ?>
                    </p>
                </div>

                <div class="mt-preview-section">
                    <h3><?php _e('Frontend Display Preview', 'mt'); ?></h3>
                    <div class="mt-performance-preview">
                        <div class="mt-perf-preview-bar">
                            <div class="mt-perf-item">
                                <span>🔄</span>
                                <span class="value">15</span>
                                <span class="label">queries</span>
                            </div>
                            <div class="mt-perf-item">
                                <span>⏱️</span>
                                <span class="value">1.2s</span>
                                <span class="label">time</span>
                            </div>
                            <div class="mt-perf-item">
                                <span>💾</span>
                                <span class="value">45.2MB</span>
                                <span class="label">memory</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-form-section">
                    <label>
                        <input type="checkbox" id="query-monitor-logged-only" checked disabled>
                        <?php _e('Show for logged-in users only', 'mt'); ?>
                    </label>
                </div>
            </div>
        </div>

        <!-- File Editor Tab -->
        <div id="tab-file-editor" class="mt-tab-content">
            <div class="mt-card">
                <h2><?php _e('.htaccess Editor', 'mt'); ?></h2>

                <div class="mt-backup-info">
                    <div class="mt-backup-status">
                        <span class="dashicons dashicons-backup"></span>
                        <span><?php _e('Backups:', 'mt'); ?>
                            <strong><?php echo count($htaccess_backups); ?>/3</strong> available
                        </span>
                    </div>
                    <?php if (!empty($htaccess_backups)): ?>
                    <div class="mt-backup-last">
                        <span><?php _e('Last backup:', 'mt'); ?>
                            <?php echo human_time_diff($htaccess_backups[0]['timestamp']); ?> ago
                        </span>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if (!$htaccess_info['writable']): ?>
                <div class="notice notice-error">
                    <p><?php _e('.htaccess file is not writable. Please check file permissions.', 'mt'); ?></p>
                </div>
                <?php endif; ?>

                <div class="mt-editor-section">
                    <textarea id="htaccess-editor" class="mt-code-editor" rows="20" <?php echo !$htaccess_info['writable'] ? 'readonly' : ''; ?>><?php echo esc_textarea($htaccess_service->get_htaccess_content()); ?></textarea>
                </div>

                <div class="mt-editor-actions">
                    <button type="button" id="save-htaccess" class="button button-primary" <?php echo !$htaccess_info['writable'] ? 'disabled' : ''; ?>>
                        <span class="dashicons dashicons-backup"></span>
                        <?php _e('Backup & Save', 'mt'); ?>
                    </button>

                    <?php if (!empty($htaccess_backups)): ?>
                    <div class="mt-restore-dropdown">
                        <button type="button" id="restore-htaccess-btn" class="button">
                            <span class="dashicons dashicons-undo"></span>
                            <?php _e('Restore', 'mt'); ?>
                        </button>
                        <div class="mt-restore-menu">
                            <?php foreach ($htaccess_backups as $index => $backup): ?>
                            <a href="#" class="mt-restore-item" data-index="<?php echo $index; ?>">
                                <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $backup['timestamp']); ?>
                                <span class="size">(<?php echo mt_format_bytes($backup['size']); ?>)</span>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <button type="button" id="cancel-htaccess" class="button">
                        <?php _e('Cancel', 'mt'); ?>
                    </button>
                </div>

                <div class="mt-htaccess-snippets">
                    <h3><?php _e('Common Snippets', 'mt'); ?></h3>
                    <div class="mt-snippet-buttons">
                        <?php foreach ($htaccess_service->get_common_snippets() as $key => $snippet): ?>
                        <button type="button" class="button mt-snippet-btn" data-snippet="<?php echo esc_attr($snippet['content']); ?>">
                            <?php echo esc_html($snippet['title']); ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- PHP Config Tab -->
        <div id="tab-php-config" class="mt-tab-content">
            <div class="mt-card">
                <h2><?php _e('PHP Configuration Presets', 'mt'); ?></h2>

                <!-- Server Memory Information -->
                <div class="mt-server-memory-info" style="background: #f0f0f1; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                    <h3 style="margin-top: 0;">
                        <span class="dashicons dashicons-performance"></span>
                        <?php _e('Server Memory Information', 'mt'); ?>
                    </h3>
                    <div class="mt-memory-stats">
                        <div class="mt-memory-row">
                            <span><?php _e('Server Memory Limit:', 'mt'); ?></span>
                            <strong><?php echo esc_html($server_memory_info['server_memory']); ?></strong>
                        </div>
                        <div class="mt-memory-row">
                            <span><?php _e('Current Usage:', 'mt'); ?></span>
                            <strong><?php echo esc_html($server_memory_info['current_memory']); ?> (<?php echo esc_html($server_memory_info['usage_percentage']); ?>%)</strong>
                        </div>
                        <div class="mt-memory-row">
                            <span><?php _e('Recommended Safe Limit:', 'mt'); ?></span>
                            <strong><?php echo esc_html($server_memory_info['optimal_memory']); ?> (<?php echo esc_html($server_memory_info['safe_percentage']); ?>% capacity)</strong>
                        </div>
                    </div>
                </div>

                <div class="mt-config-method">
                    <p>
                        <span class="dashicons dashicons-info"></span>
                        <?php _e('Configuration Method:', 'mt'); ?>
                        <strong><?php echo ucfirst($php_config_method['method'] ?: 'Not Available'); ?></strong>
                    </p>
                </div>

                <div class="mt-preset-selection">
                    <h3><?php _e('Select Preset:', 'mt'); ?></h3>
                    <div class="mt-preset-options">
                        <?php foreach ($php_presets as $key => $preset): ?>
                        <label class="mt-preset-option <?php echo $current_php_preset === $key ? 'selected' : ''; ?>">
                            <input type="radio" name="php_preset" value="<?php echo esc_attr($key); ?>" <?php checked($current_php_preset, $key); ?>>
                            <div class="mt-preset-card">
                                <h4><?php echo esc_html($preset['name']); ?></h4>
                                <p class="description"><?php echo esc_html($preset['description']); ?></p>
                                <div class="mt-preset-settings">
                                    <?php foreach ($preset['settings'] as $setting => $value): ?>
                                    <div class="mt-setting-item">
                                        <span class="setting-name"><?php echo esc_html(str_replace('_', ' ', ucwords($setting, '_'))); ?>:</span>
                                        <span class="setting-value"><?php echo esc_html($value); ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="mt-current-config">
                    <h3><?php _e('Current Configuration', 'mt'); ?></h3>
                    <div class="mt-config-table">
                        <?php foreach ($current_php_config as $setting => $value): ?>
                        <div class="mt-config-row">
                            <span class="config-name"><?php echo esc_html(str_replace('_', ' ', ucwords($setting, '_'))); ?>:</span>
                            <span class="config-value"><?php echo esc_html($value); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="mt-config-actions">
                    <button type="button" id="apply-php-preset" class="button button-primary">
                        <span class="dashicons dashicons-admin-settings"></span>
                        <?php _e('Apply Configuration', 'mt'); ?>
                    </button>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Loading overlay -->
<div id="mt-loading-overlay" class="mt-loading-overlay" style="display: none;">
    <div class="mt-loading-content">
        <div class="mt-spinner"></div>
        <p><?php _e('Processing...', 'mt'); ?></p>
    </div>
</div>
