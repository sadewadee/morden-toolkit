<?php
/**
 * Uninstall script for Morden Toolkit
 *
 * This file is executed when the plugin is deleted through WordPress admin.
 * It cleans up all plugin data and settings.
 */

// Prevent direct access
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Clean up plugin options
 */
function morden_toolkit_cleanup_options() {
    $options_to_delete = array(
        'morden_debug_enabled',
        'morden_query_monitor_enabled',
        'morden_htaccess_backups',
        'morden_php_preset',
        'morden_wp_config_backups',
        'morden_user_ini_backups',
        'morden_toolkit_version'
    );

    foreach ($options_to_delete as $option) {
        delete_option($option);
    }
}

/**
 * Clean up debug settings from wp-config.php
 */
function morden_toolkit_cleanup_wp_config() {
    $wp_config_path = ABSPATH . 'wp-config.php';

    // Try parent directory if not found in root
    if (!file_exists($wp_config_path)) {
        $wp_config_path = dirname(ABSPATH) . '/wp-config.php';
    }

    if (!file_exists($wp_config_path) || !is_writable($wp_config_path)) {
        return;
    }

    $config_content = file_get_contents($wp_config_path);

    // Remove Morden Toolkit PHP config block
    $pattern = '/\/\/ BEGIN Morden Toolkit PHP Config.*?\/\/ END Morden Toolkit PHP Config\s*/s';
    $config_content = preg_replace($pattern, '', $config_content);

    // Disable debug constants
    $constants_to_disable = array(
        'WP_DEBUG',
        'WP_DEBUG_LOG',
        'WP_DEBUG_DISPLAY',
        'SCRIPT_DEBUG'
    );

    foreach ($constants_to_disable as $constant) {
        $pattern = "/define\s*\(\s*['\"]" . $constant . "['\"]\s*,\s*[^)]+\s*\)\s*;/i";
        $replacement = "define('" . $constant . "', false);";

        if (preg_match($pattern, $config_content)) {
            $config_content = preg_replace($pattern, $replacement, $config_content);
        }
    }

    file_put_contents($wp_config_path, $config_content);
}

/**
 * Clean up .htaccess modifications
 */
function morden_toolkit_cleanup_htaccess() {
    $htaccess_path = ABSPATH . '.htaccess';

    if (!file_exists($htaccess_path) || !is_writable($htaccess_path)) {
        return;
    }

    $htaccess_content = file_get_contents($htaccess_path);

    // Remove Morden Toolkit PHP config block
    $pattern = '/# BEGIN Morden Toolkit PHP Config.*?# END Morden Toolkit PHP Config/s';
    $htaccess_content = preg_replace($pattern, '', $htaccess_content);

    // Clean up extra newlines
    $htaccess_content = preg_replace('/\n{3,}/', "\n\n", $htaccess_content);

    file_put_contents($htaccess_path, $htaccess_content);
}

/**
 * Clean up .user.ini file
 */
function morden_toolkit_cleanup_user_ini() {
    $user_ini_path = ABSPATH . '.user.ini';

    if (!file_exists($user_ini_path)) {
        return;
    }

    $content = file_get_contents($user_ini_path);

    // If the file only contains Morden Toolkit config, delete it
    if (strpos($content, '; Morden Toolkit PHP Config') !== false) {
        $lines = explode("\n", $content);
        $non_morden_lines = array();

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) ||
                strpos($line, '; Morden Toolkit') !== false ||
                in_array(explode(' = ', $line)[0] ?? '', array(
                    'memory_limit',
                    'upload_max_filesize',
                    'post_max_size',
                    'max_execution_time',
                    'max_input_vars',
                    'max_input_time'
                ))) {
                continue;
            }
            $non_morden_lines[] = $line;
        }

        if (empty($non_morden_lines)) {
            unlink($user_ini_path);
        } else {
            file_put_contents($user_ini_path, implode("\n", $non_morden_lines));
        }
    }
}

/**
 * Clean up temporary files
 */
function morden_toolkit_cleanup_temp_files() {
    $temp_pattern = ABSPATH . '*.tmp';
    $temp_files = glob($temp_pattern);

    foreach ($temp_files as $temp_file) {
        if (is_file($temp_file) && is_writable($temp_file)) {
            unlink($temp_file);
        }
    }
}

/**
 * Clean up transients
 */
function morden_toolkit_cleanup_transients() {
    global $wpdb;

    // Delete performance metrics transients
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_morden_toolkit_metrics_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_morden_toolkit_metrics_%'");
}

/**
 * Log uninstall action
 */
function morden_toolkit_log_uninstall() {
    if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        error_log('Morden Toolkit: Plugin uninstalled and cleaned up');
    }
}

// Execute cleanup functions
try {
    morden_toolkit_cleanup_options();
    morden_toolkit_cleanup_wp_config();
    morden_toolkit_cleanup_htaccess();
    morden_toolkit_cleanup_user_ini();
    morden_toolkit_cleanup_temp_files();
    morden_toolkit_cleanup_transients();
    morden_toolkit_log_uninstall();
} catch (Exception $e) {
    // Silently fail to prevent blocking uninstall process
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Morden Toolkit uninstall error: ' . $e->getMessage());
    }
}
