<?php
/**
 * Main Admin Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}


$plugin = MT_Plugin::get_instance();
$debug_service = $plugin->get_service('debug');
$query_monitor_service = $plugin->get_service('query_monitor');
$htaccess_service = $plugin->get_service('htaccess');
$php_config_service = $plugin->get_service('php_config');


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

// Load custom preset settings
$custom_settings = get_option('mt_custom_preset_settings', array());
if (empty($custom_settings)) {
    // Use default custom preset values if none saved
    $custom_settings = array(
        'memory_limit' => '256M',
        'upload_max_filesize' => '64M',
        'post_max_size' => '64M',
        'max_execution_time' => '300',
        'max_input_vars' => '3000',
        'max_input_time' => '300'
    );
}

// Update custom preset with saved settings
if (isset($php_presets['custom'])) {
    $php_presets['custom']['settings'] = $custom_settings;
}

// Setting labels and units for display
$setting_labels = array(
    'memory_limit' => __('Memory Limit', 'morden-toolkit'),
    'upload_max_filesize' => __('Upload Max Filesize', 'morden-toolkit'),
    'post_max_size' => __('Post Max Size', 'morden-toolkit'),
    'max_execution_time' => __('Max Execution Time', 'morden-toolkit'),
    'max_input_vars' => __('Max Input Vars', 'morden-toolkit'),
    'max_input_time' => __('Max Input Time', 'morden-toolkit')
);

$setting_units = array(
    'memory_limit' => 'M',
    'upload_max_filesize' => 'M',
    'post_max_size' => 'M',
    'max_execution_time' => 's',
    'max_input_vars' => '',
    'max_input_time' => 's'
);
?>

<div class="wrap">
    <h1><?php _e('Morden Toolkit', 'morden-toolkit'); ?></h1>
    <p class="description">
        <?php _e('Developer tools untuk WordPress: Debug Manager, Query Monitor, Htaccess Editor, PHP Config presets.', 'morden-toolkit'); ?>
    </p>

    <!-- Tab Navigation -->
    <div class="mt-tab-navigation">
        <button class="mt-tab-btn active" data-tab="debug-management">
            <span class="dashicons dashicons-admin-tools"></span>
            <?php _e('Debug Management', 'morden-toolkit'); ?>
        </button>
        <button class="mt-tab-btn" data-tab="query-monitor">
            <span class="dashicons dashicons-performance"></span>
            <?php _e('Query Monitor', 'morden-toolkit'); ?>
        </button>
        <button class="mt-tab-btn" data-tab="file-editor">
            <span class="dashicons dashicons-edit"></span>
            <?php _e('.htaccess Editor', 'morden-toolkit'); ?>
        </button>
        <button class="mt-tab-btn" data-tab="php-config">
            <span class="dashicons dashicons-admin-settings"></span>
            <?php _e('PHP Config', 'morden-toolkit'); ?>
        </button>
    </div>

    <!-- Tab Contents -->
    <div class="mt-tab-contents">

        <!-- Debug Management Tab -->
        <div id="tab-debug-management" class="mt-tab-content active">
            <div class="mt-card">
                <h2><?php _e('Debug Mode Control', 'morden-toolkit'); ?></h2>

                <div class="mt-form-section">
                        <div class="mt-toggle-wrapper">
                            <input type="checkbox" id="debug-mode-toggle" <?php checked($debug_enabled); ?>>
                            <div class="mt-toggle <?php echo $debug_enabled ? 'active' : ''; ?>">
                                <div class="mt-toggle-slider"></div>
                            </div>
                        <label class="mt-toggle-label">
                            <span><?php _e('Enable Debug Mode', 'morden-toolkit'); ?></span>
                        </label>
                    </div>
                </div>

                <div class="mt-debug-settings">
                    <h3><?php _e('Debug Settings', 'morden-toolkit'); ?></h3>
                    <p class="description"><?php _e('WordPress debug constants configuration', 'morden-toolkit'); ?></p>
                    <div class="mt-toggle-group" <?php echo !$debug_enabled ? 'data-disabled="true"' : ''; ?>>
                        <div class="mt-toggle-wrapper <?php echo !$debug_enabled ? 'disabled' : ''; ?>">
                            <input type="checkbox" id="wp-debug-log-toggle" <?php checked(defined('WP_DEBUG_LOG') && WP_DEBUG_LOG); ?> <?php disabled(!$debug_enabled); ?>>
                            <div class="mt-toggle <?php echo (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG && $debug_enabled) ? 'active' : ''; ?>">
                                <div class="mt-toggle-slider"></div>
                            </div>
                            <label for="wp-debug-log-toggle" class="mt-toggle-label">
                                <span>WP_DEBUG_LOG</span>
                                <small class="description"><?php _e('Log errors to file', 'morden-toolkit'); ?></small>
                            </label>
                        </div>
                        <div class="mt-toggle-wrapper <?php echo !$debug_enabled ? 'disabled' : ''; ?>">
                            <input type="checkbox" id="wp-debug-display-toggle" <?php checked(defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY); ?> <?php disabled(!$debug_enabled); ?>>
                            <div class="mt-toggle <?php echo (defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY && $debug_enabled) ? 'active' : ''; ?>">
                                <div class="mt-toggle-slider"></div>
                            </div>
                            <label for="wp-debug-display-toggle" class="mt-toggle-label">
                                <span>WP_DEBUG_DISPLAY</span>
                                <small class="description"><?php _e('Display errors on screen', 'morden-toolkit'); ?></small>
                            </label>
                        </div>
                        <div class="mt-toggle-wrapper <?php echo !$debug_enabled ? 'disabled' : ''; ?>">
                            <input type="checkbox" id="script-debug-toggle" <?php checked(defined('SCRIPT_DEBUG') && SCRIPT_DEBUG); ?> <?php disabled(!$debug_enabled); ?>>
                            <div class="mt-toggle <?php echo (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG && $debug_enabled) ? 'active' : ''; ?>">
                                <div class="mt-toggle-slider"></div>
                            </div>
                            <label for="script-debug-toggle" class="mt-toggle-label">
                                <span>SCRIPT_DEBUG</span>
                                <small class="description"><?php _e('Use unminified JS/CSS', 'morden-toolkit'); ?></small>
                            </label>
                        </div>
                        <div class="mt-toggle-wrapper <?php echo !$debug_enabled ? 'disabled' : ''; ?>">
                            <input type="checkbox" id="savequeries-toggle" <?php checked(defined('SAVEQUERIES') && SAVEQUERIES); ?> <?php disabled(!$debug_enabled); ?>>
                            <div class="mt-toggle <?php echo (defined('SAVEQUERIES') && SAVEQUERIES && $debug_enabled) ? 'active' : ''; ?>">
                                <div class="mt-toggle-slider"></div>
                            </div>
                            <label for="savequeries-toggle" class="mt-toggle-label">
                                <span>SAVEQUERIES</span>
                                <small class="description"><?php _e('Save database queries to query.log for analysis', 'morden-toolkit'); ?></small>
                            </label>
                        </div>
                        <div class="mt-toggle-wrapper <?php echo !$debug_enabled ? 'disabled' : ''; ?>">
                            <input type="checkbox" id="display-errors-toggle" <?php checked($display_errors_on); ?> <?php disabled(!$debug_enabled); ?>>
                            <div class="mt-toggle <?php echo ($display_errors_on && $debug_enabled) ? 'active' : ''; ?>">
                                <div class="mt-toggle-slider"></div>
                            </div>
                            <label for="display-errors-toggle" class="mt-toggle-label">
                                <span>display_errors</span>
                                <small class="description"><?php _e('Display PHP errors on screen', 'morden-toolkit'); ?></small>
                            </label>
                        </div>
                        <div class="mt-toggle-wrapper <?php echo !$debug_enabled ? 'disabled' : ''; ?>">
                            <input type="checkbox" id="smtp-logging-toggle" <?php checked(get_option('mt_smtp_logging_enabled', false)); ?> <?php disabled(!$debug_enabled); ?>>
                            <div class="mt-toggle <?php echo (get_option('mt_smtp_logging_enabled', false) && $debug_enabled) ? 'active' : ''; ?>">
                                <div class="mt-toggle-slider"></div>
                            </div>
                            <label for="smtp-logging-toggle" class="mt-toggle-label">
                                <span>SMTP Logging</span>
                                <small class="description"><?php _e('Log email sending activities', 'morden-toolkit'); ?></small>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="mt-status-info">
                    <div class="mt-status-item">
                        <span class="mt-status-indicator <?php echo $debug_enabled ? 'active' : 'inactive'; ?>"></span>
                        <span><?php _e('Status:', 'morden-toolkit'); ?>
                            <?php echo $debug_enabled ? __('Debug Enabled', 'morden-toolkit') : __('Debug Disabled', 'morden-toolkit'); ?>
                        </span>
                    </div>
                    <?php if (file_exists(mt_get_debug_log_path())): ?>
                    <div class="mt-status-item">
                        <span class="dashicons dashicons-media-text"></span>
                        <span><?php _e('Debug Log Size:', 'morden-toolkit'); ?> <?php echo mt_format_bytes(filesize(mt_get_debug_log_path())); ?></span>
                        <a href="<?php echo admin_url('tools.php?page=mt-logs'); ?>" class="button button-small" style="margin-left: 10px;">
                            <?php _e('View Debug Logs', 'morden-toolkit'); ?>
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php if (defined('SAVEQUERIES') && SAVEQUERIES): ?>
                    <div class="mt-status-item">
                        <span class="dashicons dashicons-database"></span>
                        <span><?php _e('Query Logging:', 'morden-toolkit'); ?>
                            <?php echo defined('SAVEQUERIES') && SAVEQUERIES ? __('Enabled', 'morden-toolkit') : __('Disabled', 'morden-toolkit'); ?>
                        </span>
                        <?php if (file_exists(mt_get_query_log_path())): ?>
                        <span style="margin-left: 10px; color: #646970;">
                            <?php _e('Size:', 'morden-toolkit'); ?> <?php echo mt_format_bytes(filesize(mt_get_query_log_path())); ?>
                            <small style="margin-left: 5px; color: #007cba;">
                                (<?php _e('Auto-rotates at', 'morden-toolkit'); ?> <?php echo mt_format_bytes(mt_get_query_log_max_size()); ?>)
                            </small>
                        </span>
                        <?php endif; ?>
                        <a href="<?php echo admin_url('tools.php?page=mt-query-logs'); ?>" class="button button-small" style="margin-left: 10px;">
                            <?php _e('View Query Logs', 'morden-toolkit'); ?>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Query Monitor Tab -->
        <div id="tab-query-monitor" class="mt-tab-content">
            <div class="mt-card">
                <h2><?php _e('Performance Monitoring', 'morden-toolkit'); ?></h2>

                <div class="mt-form-section">
                    <label class="mt-toggle-label">
                        <span><?php _e('Enable Performance Bar', 'morden-toolkit'); ?></span>
                        <div class="mt-toggle-wrapper">
                            <input type="checkbox" id="query-monitor-toggle" <?php checked($query_monitor_enabled); ?>>
                            <div class="mt-toggle <?php echo $query_monitor_enabled ? 'active' : ''; ?>">
                                <div class="mt-toggle-slider"></div>
                            </div>
                        </div>
                    </label>
                    <p class="description">
                        <?php _e('Menampilkan bar performa di bagian bawah halaman untuk user yang login.', 'morden-toolkit'); ?>
                    </p>
                </div>

                <div class="mt-preview-section">
                    <h3><?php _e('Frontend Display Preview', 'morden-toolkit'); ?></h3>
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
                        <?php _e('Show for logged-in users only', 'morden-toolkit'); ?>
                    </label>
                </div>
            </div>
        </div>

        <!-- .htaccess Editor Tab -->
        <div id="tab-file-editor" class="mt-tab-content">
            <div class="mt-card">
                <h2><?php _e('.htaccess Editor', 'morden-toolkit'); ?></h2>

                <div class="mt-backup-info">
                    <div class="mt-backup-status">
                        <span class="dashicons dashicons-backup"></span>
                        <span><?php _e('Backups:', 'morden-toolkit'); ?>
                            <strong><?php echo count($htaccess_backups); ?>/3</strong> available
                        </span>
                    </div>
                    <?php if (!empty($htaccess_backups)): ?>
                    <div class="mt-backup-last">
                        <span><?php _e('Last backup:', 'morden-toolkit'); ?>
                            <?php echo human_time_diff($htaccess_backups[0]['timestamp']); ?> ago
                        </span>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if (!$htaccess_info['writable']): ?>
                <div class="notice notice-error">
                    <p><?php _e('.htaccess file is not writable. Please check file permissions.', 'morden-toolkit'); ?></p>
                </div>
                <?php endif; ?>

                <div class="mt-editor-section">
                    <textarea id="htaccess-editor" class="mt-code-editor" rows="20" <?php echo !$htaccess_info['writable'] ? 'readonly' : ''; ?>><?php echo esc_textarea($htaccess_service->get_htaccess_content()); ?></textarea>
                </div>

                <div class="mt-editor-actions">
                    <button type="button" id="save-htaccess" class="button button-primary" <?php echo !$htaccess_info['writable'] ? 'disabled' : ''; ?>>
                        <span class="dashicons dashicons-backup"></span>
                        <?php _e('Backup & Save', 'morden-toolkit'); ?>
                    </button>

                    <?php if (!empty($htaccess_backups)): ?>
                    <div class="mt-restore-dropdown">
                        <button type="button" id="restore-htaccess-btn" class="button">
                            <span class="dashicons dashicons-undo"></span>
                            <?php _e('Restore', 'morden-toolkit'); ?>
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
                        <?php _e('Cancel', 'morden-toolkit'); ?>
                    </button>
                </div>

                <div class="mt-htaccess-snippets">
                    <h3><?php _e('Common Snippets', 'morden-toolkit'); ?></h3>
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
                <h2><?php _e('PHP Configuration Presets', 'morden-toolkit'); ?></h2>

                <div class="mt-config-method">
                    <p>
                        <span class="dashicons dashicons-info"></span>
                        <?php _e('Configuration Method:', 'morden-toolkit'); ?>
                        <strong><?php echo ucfirst($php_config_method['method'] ?: 'Not Available'); ?></strong>
                    </p>
                </div>

                <div class="mt-preset-selection">
                    <h3><?php _e('Select Preset:', 'morden-toolkit'); ?></h3>
                    <div class="mt-preset-options">
                        <?php foreach ($php_presets as $key => $preset): ?>
                        <label class="mt-preset-option <?php echo $current_php_preset === $key ? 'selected' : ''; ?>" data-preset="<?php echo esc_attr($key); ?>">
                            <input type="radio" name="php_preset" value="<?php echo esc_attr($key); ?>" <?php checked($current_php_preset, $key); ?>>
                            <div class="mt-preset-card">
                                <h4><?php echo esc_html($preset['name']); ?></h4>
                                <p class="description"><?php echo esc_html($preset['description']); ?></p>
                                <?php if ($key === 'custom'): ?>
                                <div class="mt-custom-preset-form hide" style="<?php echo $current_php_preset === 'custom' ? '' : 'display: none;'; ?>">
                                    <h5><?php _e('Custom Settings:', 'morden-toolkit'); ?></h5>
                                    <?php
                                    $custom_settings = get_option('mt_custom_preset_settings', $preset['settings']);
                                    $setting_labels = array(
                                        'memory_limit' => __('Memory Limit', 'morden-toolkit'),
                                        'upload_max_filesize' => __('Upload Max Filesize', 'morden-toolkit'),
                                        'post_max_size' => __('Post Max Size', 'morden-toolkit'),
                                        'max_execution_time' => __('Max Execution Time', 'morden-toolkit'),
                                        'max_input_vars' => __('Max Input Vars', 'morden-toolkit'),
                                        'max_input_time' => __('Max Input Time', 'morden-toolkit')
                                    );
                                    $setting_units = array(
                                        'memory_limit' => 'M',
                                        'upload_max_filesize' => 'M',
                                        'post_max_size' => 'M',
                                        'max_execution_time' => 's',
                                        'max_input_vars' => '',
                                        'max_input_time' => 's'
                                    );
                                    ?>
                                    <div class="mt-custom-settings-grid">
                                        <?php foreach ($preset['settings'] as $setting => $default_value): ?>
                                        <div class="mt-custom-setting-item">
                                            <label for="custom_<?php echo esc_attr($setting); ?>">
                                                <?php echo esc_html($setting_labels[$setting] ?? ucwords(str_replace('_', ' ', $setting))); ?>:
                                            </label>
                                            <div class="mt-input-with-unit">
                                                <input type="text"
                                                       id="custom_<?php echo esc_attr($setting); ?>"
                                                       name="custom_settings[<?php echo esc_attr($setting); ?>]"
                                                       value="<?php echo esc_attr(str_replace($setting_units[$setting], '', $custom_settings[$setting] ?? $default_value)); ?>"
                                                       class="mt-custom-input"
                                                       data-setting="<?php echo esc_attr($setting); ?>"
                                                       placeholder="<?php echo esc_attr(str_replace($setting_units[$setting], '', $default_value)); ?>">
                                                <?php if (!empty($setting_units[$setting])): ?>
                                                <span class="mt-input-unit"><?php echo esc_html($setting_units[$setting]); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="mt-custom-preset-actions">
                                        <button type="button" id="save-custom-preset" class="button button-secondary">
                                            <span class="dashicons dashicons-saved"></span>
                                            <?php _e('Save Custom Settings', 'morden-toolkit'); ?>
                                        </button>
                                        <button type="button" id="reset-custom-preset" class="button">
                                            <span class="dashicons dashicons-undo"></span>
                                            <?php _e('Reset to Default', 'morden-toolkit'); ?>
                                        </button>
                                    </div>
                                </div>
                                <?php else: ?>
                                <div class="mt-preset-settings">
                                    <?php foreach ($preset['settings'] as $setting => $value): ?>
                                    <div class="mt-setting-item">
                                        <span class="setting-name"><?php echo esc_html(str_replace('_', ' ', ucwords($setting, '_'))); ?>:</span>
                                        <span class="setting-value"><?php echo esc_html($value); ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="mt-current-config">
                    <h3><?php _e('Current Configuration', 'morden-toolkit'); ?></h3>
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
                        <?php _e('Apply Configuration', 'morden-toolkit'); ?>
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
        <p><?php _e('Processing...', 'morden-toolkit'); ?></p>
    </div>
</div>
