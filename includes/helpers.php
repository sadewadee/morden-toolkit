<?php
/**
 * Helper functions for MT
 */

if (!defined('ABSPATH')) {
    exit;
}

function mt_can_manage() {
    return function_exists('current_user_can') ? current_user_can('manage_options') : false;
}

function mt_verify_nonce($nonce, $action = 'mt_action') {
    return function_exists('wp_verify_nonce') ? wp_verify_nonce($nonce, $action) : false;
}

function mt_send_json($data) {
    if (function_exists('wp_send_json')) {
        wp_send_json($data);
    }
}

function mt_send_json_success($data = null) {
    if (function_exists('wp_send_json_success')) {
        wp_send_json_success($data);
    }
}

function mt_send_json_error($data = null) {
    if (function_exists('wp_send_json_error')) {
        wp_send_json_error($data);
    }
}

function mt_get_wp_config_path() {
    $config_path = ABSPATH . 'wp-config.php';
    if (file_exists($config_path)) {
        return $config_path;
    }


    $parent_config = dirname(ABSPATH) . '/wp-config.php';
    if (file_exists($parent_config)) {
        return $parent_config;
    }

    return false;
}

function mt_get_htaccess_path() {
    return ABSPATH . '.htaccess';
}

function mt_format_bytes($size) {
    $units = array('B', 'KB', 'MB', 'GB');
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    return round($size, 2) . ' ' . $units[$i];
}

function mt_format_time($time) {
    if ($time < 1) {
        return round($time * 1000) . 'ms';
    }
    return round($time, 3) . 's';
}

function mt_get_debug_log_path() {
    // Check if WP_DEBUG_LOG is defined with a custom path
    if (defined('WP_DEBUG_LOG') && is_string(WP_DEBUG_LOG) && !in_array(WP_DEBUG_LOG, ['true', 'false', '1', '0'], true)) {
        // WP_DEBUG_LOG contains a custom path
        $custom_path = WP_DEBUG_LOG;

        // If it's a relative path, make it absolute
        if (!is_absolute_path($custom_path)) {
            $custom_path = ABSPATH . ltrim($custom_path, '/');
        }

        return $custom_path;
    }

    // Check for custom path in wp-config.php (backup detection)
    $wp_config_path = mt_get_wp_config_path();
    if ($wp_config_path && file_exists($wp_config_path)) {
        $content = file_get_contents($wp_config_path);

        // Enhanced regex patterns to handle various quote escaping scenarios
        $patterns = [
            // Standard pattern: define( 'WP_DEBUG_LOG', '/path/to/file' )
            '/define\s*\(\s*[\'"]WP_DEBUG_LOG[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]\s*\)/',
            // Handle escaped quotes: define( 'WP_DEBUG_LOG', '\''/path/to/file'\'' )
            '/define\s*\(\s*[\'"]WP_DEBUG_LOG[\'"]\s*,\s*[\'"]\\\\?[\'"]([^\\\\]+)\\\\?[\'"][\'"] *\)/',
            // Handle double quotes with single quotes: define( "WP_DEBUG_LOG", '/path/to/file' )
            '/define\s*\(\s*"WP_DEBUG_LOG"\s*,\s*[\'"]([^\'"]+)[\'"]\s*\)/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $path = $matches[1];
                // Clean any remaining escape characters
                $path = stripslashes($path);
                // Only return if it looks like a custom log path
                if (strpos($path, 'wp-errors-') !== false || (strpos($path, '/') !== false && strpos($path, '.log') !== false)) {
                    return $path;
                }
            }
        }
    }

    // Default WordPress debug log path
    return WP_CONTENT_DIR . '/debug.log';
}

/**
 * Check if a path is absolute
 *
 * @param string $path Path to check
 * @return bool True if absolute path
 */
function is_absolute_path($path) {
    return (substr($path, 0, 1) === '/' || preg_match('/^[a-zA-Z]:[\\\\]/', $path));
}


function mt_get_query_log_path() {
    // Check if custom query log path is defined (similar to debug log)
    $wp_config_path = mt_get_wp_config_path();
    if ($wp_config_path && file_exists($wp_config_path)) {
        $content = file_get_contents($wp_config_path);

        // Enhanced regex patterns to handle various quote escaping scenarios for MT_QUERY_LOG
        $patterns = [
            // Standard pattern: define( 'MT_QUERY_LOG', '/path/to/file' )
            '/define\s*\(\s*[\'"]MT_QUERY_LOG[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]\s*\)/',
            // Handle escaped quotes: define( 'MT_QUERY_LOG', '\''/path/to/file'\'' )
            '/define\s*\(\s*[\'"]MT_QUERY_LOG[\'"]\s*,\s*[\'"]\\\\?[\'"]([^\\\\]+)\\\\?[\'"][\'"] *\)/',
            // Handle double quotes with single quotes: define( "MT_QUERY_LOG", '/path/to/file' )
            '/define\s*\(\s*"MT_QUERY_LOG"\s*,\s*[\'"]([^\'"]+)[\'"]\s*\)/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $path = $matches[1];
                // Clean any remaining escape characters
                $path = stripslashes($path);
                // Only return if it looks like a custom query log path
                if (strpos($path, 'wp-queries-') !== false || (strpos($path, '/') !== false && strpos($path, '.log') !== false)) {
                    return $path;
                }
            }
        }
    }

    // Default path: use morden-toolkit directory like debug.log
    $log_directory = ABSPATH . 'wp-content/morden-toolkit/';

    // Ensure directory exists
    if (!file_exists($log_directory)) {
        if (function_exists('wp_mkdir_p')) {
            wp_mkdir_p($log_directory);
        } else {
            mkdir($log_directory, 0755, true);
        }
    }

    return $log_directory . 'query.log';
}

/**
 * Generate custom query log path with random string
 *
 * @return string Custom query log path
 */
function mt_generate_custom_query_log_path() {
    // Generate random string for unique log file (similar to debug log)
    $random_string = function_exists('wp_generate_password') ?
        wp_generate_password(8, false, false) :
        substr(md5(uniqid(mt_rand(), true)), 0, 8);

    $log_filename = 'wp-queries-' . $random_string . '.log';

    // Use wp-content/morden-toolkit directory
    $log_directory = ABSPATH . 'wp-content/morden-toolkit/';

    // Ensure directory exists
    if (!file_exists($log_directory)) {
        if (function_exists('wp_mkdir_p')) {
            wp_mkdir_p($log_directory);
        } else {
            mkdir($log_directory, 0755, true);
        }
    }

    return $log_directory . $log_filename;
}

/**
 * Get query log maximum size before rotation
 *
 * @return int Maximum size in bytes (default: 10MB)
 */
function mt_get_query_log_max_size() {
    return apply_filters('mt_query_log_max_size', 10 * 1024 * 1024);
}

/**
 * Get debug log maximum size before truncation
 *
 * @return int Maximum size in bytes (default: 50MB)
 */
function mt_get_debug_log_max_size() {
    return apply_filters('mt_debug_log_max_size', 50 * 1024 * 1024);
}

/**
 * Check if file is writable with proper error handling
 */
function mt_is_file_writable($file_path) {
    if (!file_exists($file_path)) {
        // Try to create parent directory if it doesn't exist
        $dir = dirname($file_path);
        if (!is_dir($dir)) {
            if (function_exists('wp_mkdir_p')) {
                wp_mkdir_p($dir);
            } else {
                mkdir($dir, 0755, true);
            }
        }

        // Try to create file
        $handle = fopen($file_path, 'a');
        if ($handle) {
            fclose($handle);
            return true;
        }
        return false;
    }

    return is_writable($file_path);
}

/**
 * Sanitize file content before saving
 */
function mt_sanitize_file_content($content) {
    // For .htaccess files, we need to be more careful about what we consider malicious
    // Only block actual PHP execution, not legitimate .htaccess directives
    $dangerous_patterns = array(
        '/(<\?php|<\?=)/i',  // PHP opening tags
        '/(eval|exec|system|shell_exec|passthru)\s*\(/i', // Dangerous PHP functions with parentheses
        '/<script[^>]*>/i', // Script tags
        '/javascript:\s*[^\s]/i', // JavaScript protocol
    );

    foreach ($dangerous_patterns as $pattern) {
        if (preg_match($pattern, $content)) {
            return false; // Reject content with suspicious patterns
        }
    }

    // Don't modify the content - just validate it
    return $content;
}

/**
 * Clear all debug log files except the currently active one
 *
 * @return int Number of files cleaned up
 */
function mt_clear_all_debug_logs_except_active() {
    $log_directory = ABSPATH . 'wp-content/morden-toolkit/';

    if (!is_dir($log_directory)) {
        return 0;
    }

    // Get current active debug log path
    $current_debug_log = mt_get_debug_log_path();
    $current_filename = basename($current_debug_log);

    // Find all debug log files
    $debug_pattern = $log_directory . 'wp-errors-*.log';
    $debug_files = glob($debug_pattern);

    if (empty($debug_files)) {
        return 0; // Nothing to clean up
    }

    $removed_count = 0;

    foreach ($debug_files as $file) {
        $filename = basename($file);

        // Skip the currently active log file
        if ($filename === $current_filename) {
            continue;
        }

        if (file_exists($file) && unlink($file)) {
            $removed_count++;
            error_log('MT: Cleared debug log file: ' . basename($file));
        }
    }

    return $removed_count;
}

/**
 * Cleanup old debug log files, keeping only the most recent ones
 *
 * @param int $keep_count Number of recent files to keep (default: 3)
 * @return int Number of files cleaned up
 */
function mt_cleanup_old_debug_logs($keep_count = 3) {
    $log_directory = ABSPATH . 'wp-content/morden-toolkit/';

    if (!is_dir($log_directory)) {
        return 0;
    }

    // Find all debug log files
    $debug_pattern = $log_directory . 'wp-errors-*.log';
    $debug_files = glob($debug_pattern);

    if (empty($debug_files) || count($debug_files) <= $keep_count) {
        return 0; // Nothing to clean up
    }

    // Sort files by modification time (newest first)
    usort($debug_files, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });

    // Keep only the most recent files, remove the rest
    $files_to_remove = array_slice($debug_files, $keep_count);
    $removed_count = 0;

    foreach ($files_to_remove as $file) {
        if (file_exists($file) && unlink($file)) {
            $removed_count++;
            error_log('MT: Cleaned up old debug log file: ' . basename($file));
        }
    }

    return $removed_count;
}

/**
 * Cleanup all log files (debug and query logs) from morden-toolkit directory
 *
 * @param bool $include_current Whether to include current log files (default: false)
 * @return int Number of files cleaned up
 */
function mt_cleanup_all_log_files($include_current = false) {
    $log_directory = ABSPATH . 'wp-content/morden-toolkit/';

    if (!is_dir($log_directory)) {
        return 0;
    }

    $patterns = [
        'wp-errors-*.log',    // Debug logs
        'wp-queries-*.log',   // Query logs
        'query.log.*',        // Query log rotation files (query.log.1, query.log.2, etc.)
    ];

    if ($include_current) {
        $patterns[] = 'query.log';       // Current query log
        $patterns[] = 'debug.log';       // Current debug log
    }

    $removed_count = 0;

    foreach ($patterns as $pattern) {
        $log_files = glob($log_directory . $pattern);

        foreach ($log_files as $log_file) {
            if (file_exists($log_file) && unlink($log_file)) {
                $removed_count++;
                error_log('MT: Cleaned up log file: ' . basename($log_file));
            }
        }
    }

    return $removed_count;
}

/**
 * Cleanup query log rotation files (query.log.1, query.log.2, etc.)
 *
 * @param bool $keep_latest Whether to keep the latest rotation file (query.log.1)
 * @return int Number of files cleaned up
 */
function mt_cleanup_query_log_rotation_files($keep_latest = true) {
    $log_directory = ABSPATH . 'wp-content/morden-toolkit/';

    if (!is_dir($log_directory)) {
        return 0;
    }

    // Find all query log rotation files using multiple patterns to be thorough
    $patterns = [
        $log_directory . 'query.log.*',  // Standard rotation files
        $log_directory . 'query.log.[0-9]*'  // Numbered rotation files specifically
    ];

    $rotation_files = [];
    foreach ($patterns as $pattern) {
        $files = glob($pattern);
        if ($files) {
            $rotation_files = array_merge($rotation_files, $files);
        }
    }

    // Remove duplicates
    $rotation_files = array_unique($rotation_files);

    if (empty($rotation_files)) {
        return 0; // Nothing to clean up
    }

    $removed_count = 0;

    foreach ($rotation_files as $file) {
        $filename = basename($file);

        // Skip files that are not actually rotation files (e.g., query.log.backup)
        if (!preg_match('/^query\.log\.[0-9]+$/', $filename)) {
            continue;
        }

        // If keep_latest is true, skip query.log.1 (the most recent rotation)
        if ($keep_latest && $filename === 'query.log.1') {
            continue;
        }

        if (file_exists($file) && is_writable($file) && unlink($file)) {
            $removed_count++;
            error_log('MT: Cleaned up query rotation file: ' . $filename);
        }
    }

    return $removed_count;
}
