<?php
/**
 * Uninstall script for Morden Toolkit
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Include internal logging helper
require_once __DIR__ . '/includes/mt-internal-log.php';

function mt_cleanup_options() {
    $options_to_delete = array(
        'morden_debug_enabled',
        'mt_query_monitor_enabled',
        'morden_htaccess_backups',
        'mt_php_preset',
        'morden_wp_config_backups',
        'morden_php_ini_backups',
        'mt_version'
    );

    foreach ($options_to_delete as $option) {
        delete_option($option);
    }
}

function mt_cleanup_wp_config() {
    $wp_config_path = ABSPATH . 'wp-config.php';
    if (!file_exists($wp_config_path)) {
        $wp_config_path = dirname(ABSPATH) . '/wp-config.php';
    }

    if (!file_exists($wp_config_path) || !is_writable($wp_config_path)) {
        return;
    }

    $config_content = file_get_contents($wp_config_path);


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

function mt_cleanup_htaccess() {
    $htaccess_path = ABSPATH . '.htaccess';

    if (!file_exists($htaccess_path) || !is_writable($htaccess_path)) {
        return;
    }

    $htaccess_content = file_get_contents($htaccess_path);

    // Remove Morden Toolkit PHP config block
    $pattern = '/# BEGIN MT PHP Config.*?# END MT PHP Config/s';
    $htaccess_content = preg_replace($pattern, '', $htaccess_content);

    // Clean up extra newlines
    $htaccess_content = preg_replace('/\n{3,}/', "\n\n", $htaccess_content);

    file_put_contents($htaccess_path, $htaccess_content);
}

function mt_cleanup_php_ini() {
    $php_ini_path = ABSPATH . 'php.ini';

    if (!file_exists($php_ini_path)) {
        return;
    }

    $content = file_get_contents($php_ini_path);

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
            unlink($php_ini_path);
        } else {
            file_put_contents($php_ini_path, implode("\n", $non_morden_lines));
        }
    }
}

function mt_cleanup_temp_files() {
    $temp_pattern = ABSPATH . '*.tmp';
    $temp_files = glob($temp_pattern);

    foreach ($temp_files as $temp_file) {
        if (is_file($temp_file) && is_writable($temp_file)) {
            unlink($temp_file);
        }
    }
}

function mt_cleanup_transients() {
    global $wpdb;

    // Delete performance metrics transients
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_mt_metrics_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_mt_metrics_%'");
}

function mt_cleanup_log_files() {
    $log_directory = ABSPATH . 'wp-content/morden-toolkit/';

    if (!is_dir($log_directory)) {
        return 0;
    }

    $patterns = [
        'wp-errors-*.log',    // Debug logs
        'wp-queries-*.log',   // Query logs
        'query.log',          // Main query log
        'query.log.*',        // Query log rotation files (query.log.1, query.log.2, etc.)
        'debug.log',          // Main debug log
        '.htaccess',          // Protection file
        'index.php'           // Protection file
    ];

    $removed_count = 0;

    foreach ($patterns as $pattern) {
        $files = glob($log_directory . $pattern);

        foreach ($files as $file) {
            if (file_exists($file) && unlink($file)) {
                $removed_count++;
            }
        }
    }

    // Try to remove the directory if it's empty
    if (is_dir($log_directory)) {
        $remaining_files = glob($log_directory . '*');
        if (empty($remaining_files)) {
            rmdir($log_directory);
        }
    }

    return $removed_count;
}

function mt_log_uninstall() {
    mt_debug_log('Plugin uninstalled and cleaned up');
}

try {
    mt_cleanup_options();
    mt_cleanup_wp_config();
    mt_cleanup_htaccess();
    mt_cleanup_php_ini();
    mt_cleanup_temp_files();
    mt_cleanup_transients();

    $removed_logs = mt_cleanup_log_files();
    if ($removed_logs > 0) {
        mt_debug_log("Removed {$removed_logs} log files during uninstall");
    }

    mt_log_uninstall();
} catch (Exception $e) {
    mt_error_log('Uninstall error: ' . $e->getMessage());
}
