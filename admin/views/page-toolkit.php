<?php
/**
 * Main Admin Page Template
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get services
$plugin = Morden_Toolkit_Plugin::get_instance();
$debug_service = $plugin->get_service('debug');
$query_monitor_service = $plugin->get_service('query_monitor');
$htaccess_service = $plugin->get_service('htaccess');
$php_config_service = $plugin->get_service('php_config');

// Get current states
$debug_status = $debug_service->get_debug_status();
$query_monitor_enabled = get_option('morden_query_monitor_enabled', false);
$htaccess_info = $htaccess_service->get_htaccess_info();
$htaccess_backups = $htaccess_service->get_backups();
$php_presets = $php_config_service->get_presets();
$current_php_preset = get_option('morden_php_preset', 'medium');
$php_config_method = $php_config_service->get_config_method_info();
$current_php_config = $php_config_service->get_current_config();
?>

<div class="wrap">
    <h1><?php _e('Morden Toolkit', 'mt-toolkit'); ?></h1>
    <p class="description">
        <?php _e('Developer tools untuk WordPress: Debug Manager, Query Monitor, Htaccess Editor, PHP Config presets.', 'mt-toolkit'); ?>
    </p>

    <!-- Tab Navigation -->
    <div class="mt-tab-navigation">
        <button class="mt-tab-btn active" data-tab="debug-management">
            <span class="dashicons dashicons-admin-tools"></span>
            <?php _e('Debug Management', 'mt-toolkit'); ?>
        </button>
        <button class="mt-tab-btn" data-tab="query-monitor">
            <span class="dashicons dashicons-performance"></span>
            <?php _e('Query Monitor', 'mt-toolkit'); ?>
        </button>
        <button class="mt-tab-btn" data-tab="file-editor">
            <span class="dashicons dashicons-edit"></span>
            <?php _e('File Editor', 'mt-toolkit'); ?>
        </button>
        <button class="mt-tab-btn" data-tab="php-config">
            <span class="dashicons dashicons-admin-settings"></span>
            <?php _e('PHP Config', 'mt-toolkit'); ?>
        </button>
    </div>

    <!-- Tab Contents -->
    <div class="mt-tab-contents">

        <!-- Debug Management Tab -->
        <div id="tab-debug-management" class="mt-tab-content active">
            <div class="mt-card">
                <h2><?php _e('Debug Mode Control', 'mt-toolkit'); ?></h2>

                <div class="mt-form-section">
                    <label class="mt-toggle-label">
                        <span><?php _e('Enable Debug Mode', 'mt-toolkit'); ?></span>
                        <div class="mt-toggle-wrapper">
                            <input type="checkbox" id="debug-mode-toggle" <?php checked($debug_status['enabled']); ?>>
                            <div class="mt-toggle <?php echo $debug_status['enabled'] ? 'active' : ''; ?>">
                                <div class="mt-toggle-slider"></div>
                            </div>
                        </div>
                    </label>
                </div>

                <div class="mt-debug-settings">
                    <h3><?php _e('Debug Settings', 'mt-toolkit'); ?></h3>
                    <div class="mt-checkbox-group">
                        <label>
                            <input type="checkbox" <?php checked($debug_status['wp_debug']); ?> disabled>
                            <span>WP_DEBUG</span>
                        </label>
                        <label>
                            <input type="checkbox" <?php checked($debug_status['wp_debug_log']); ?> disabled>
                            <span>WP_DEBUG_LOG</span>
                        </label>
                        <label>
                            <input type="checkbox" <?php checked($debug_status['wp_debug_display']); ?> disabled>
                            <span>WP_DEBUG_DISPLAY</span>
                        </label>
                        <label>
                            <input type="checkbox" <?php checked($debug_status['script_debug']); ?> disabled>
                            <span>SCRIPT_DEBUG</span>
                        </label>
                    </div>
                </div>

                <div class="mt-debug-actions">
                    <button type="button" id="clear-debug-log" class="button">
                        <span class="dashicons dashicons-trash"></span>
                        <?php _e('Clear Debug Log', 'mt-toolkit'); ?>
                    </button>
                    <a href="<?php echo admin_url('tools.php?page=mt-toolkit-logs'); ?>" class="button">
                        <span class="dashicons dashicons-visibility"></span>
                        <?php _e('View Logs', 'mt-toolkit'); ?>
                    </a>
                </div>

                <div class="mt-status-info">
                    <div class="mt-status-item">
                        <span class="mt-status-indicator <?php echo $debug_status['enabled'] ? 'active' : 'inactive'; ?>"></span>
                        <span><?php _e('Status:', 'mt-toolkit'); ?>
                            <?php echo $debug_status['enabled'] ? __('Debug Enabled', 'mt-toolkit') : __('Debug Disabled', 'mt-toolkit'); ?>
                        </span>
                    </div>
                    <?php if ($debug_status['log_file_exists']): ?>
                    <div class="mt-status-item">
                        <span class="dashicons dashicons-media-text"></span>
                        <span><?php _e('Log File Size:', 'mt-toolkit'); ?> <?php echo esc_html($debug_status['log_file_size']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Query Monitor Tab -->
        <div id="tab-query-monitor" class="mt-tab-content">
            <div class="mt-card">
                <h2><?php _e('Performance Monitoring', 'mt-toolkit'); ?></h2>

                <div class="mt-form-section">
                    <label class="mt-toggle-label">
                        <span><?php _e('Enable Performance Bar', 'mt-toolkit'); ?></span>
                        <div class="mt-toggle-wrapper">
                            <input type="checkbox" id="query-monitor-toggle" <?php checked($query_monitor_enabled); ?>>
                            <div class="mt-toggle <?php echo $query_monitor_enabled ? 'active' : ''; ?>">
                                <div class="mt-toggle-slider"></div>
                            </div>
                        </div>
                    </label>
                    <p class="description">
                        <?php _e('Menampilkan bar performa di bagian bawah halaman untuk user yang login.', 'mt-toolkit'); ?>
                    </p>
                </div>

                <div class="mt-preview-section">
                    <h3><?php _e('Frontend Display Preview', 'mt-toolkit'); ?></h3>
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
                        <?php _e('Show for logged-in users only', 'mt-toolkit'); ?>
                    </label>
                </div>
            </div>
        </div>

        <!-- File Editor Tab -->
        <div id="tab-file-editor" class="mt-tab-content">
            <div class="mt-card">
                <h2><?php _e('.htaccess Editor', 'mt-toolkit'); ?></h2>

                <div class="mt-backup-info">
                    <div class="mt-backup-status">
                        <span class="dashicons dashicons-backup"></span>
                        <span><?php _e('Backups:', 'mt-toolkit'); ?>
                            <strong><?php echo count($htaccess_backups); ?>/3</strong> available
                        </span>
                    </div>
                    <?php if (!empty($htaccess_backups)): ?>
                    <div class="mt-backup-last">
                        <span><?php _e('Last backup:', 'mt-toolkit'); ?>
                            <?php echo human_time_diff($htaccess_backups[0]['timestamp']); ?> ago
                        </span>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if (!$htaccess_info['writable']): ?>
                <div class="notice notice-error">
                    <p><?php _e('.htaccess file is not writable. Please check file permissions.', 'mt-toolkit'); ?></p>
                </div>
                <?php endif; ?>

                <div class="mt-editor-section">
                    <textarea id="htaccess-editor" class="mt-code-editor" rows="20" <?php echo !$htaccess_info['writable'] ? 'readonly' : ''; ?>><?php echo esc_textarea($htaccess_service->get_htaccess_content()); ?></textarea>
                </div>

                <div class="mt-editor-actions">
                    <button type="button" id="save-htaccess" class="button button-primary" <?php echo !$htaccess_info['writable'] ? 'disabled' : ''; ?>>
                        <span class="dashicons dashicons-backup"></span>
                        <?php _e('Backup & Save', 'mt-toolkit'); ?>
                    </button>

                    <?php if (!empty($htaccess_backups)): ?>
                    <div class="mt-restore-dropdown">
                        <button type="button" id="restore-htaccess-btn" class="button">
                            <span class="dashicons dashicons-undo"></span>
                            <?php _e('Restore', 'mt-toolkit'); ?>
                        </button>
                        <div class="mt-restore-menu">
                            <?php foreach ($htaccess_backups as $index => $backup): ?>
                            <a href="#" class="mt-restore-item" data-index="<?php echo $index; ?>">
                                <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $backup['timestamp']); ?>
                                <span class="size">(<?php echo morden_toolkit_format_bytes($backup['size']); ?>)</span>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <button type="button" id="cancel-htaccess" class="button">
                        <?php _e('Cancel', 'mt-toolkit'); ?>
                    </button>
                </div>

                <div class="mt-htaccess-snippets">
                    <h3><?php _e('Common Snippets', 'mt-toolkit'); ?></h3>
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
                <h2><?php _e('PHP Configuration Presets', 'mt-toolkit'); ?></h2>

                <div class="mt-config-method">
                    <p>
                        <span class="dashicons dashicons-info"></span>
                        <?php _e('Configuration Method:', 'mt-toolkit'); ?>
                        <strong><?php echo ucfirst($php_config_method['method'] ?: 'Not Available'); ?></strong>
                    </p>
                </div>

                <div class="mt-preset-selection">
                    <h3><?php _e('Select Preset:', 'mt-toolkit'); ?></h3>
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
                    <h3><?php _e('Current Configuration', 'mt-toolkit'); ?></h3>
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
                        <?php _e('Apply Configuration', 'mt-toolkit'); ?>
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
        <p><?php _e('Processing...', 'mt-toolkit'); ?></p>
    </div>
</div>
