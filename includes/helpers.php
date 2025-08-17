<?php
/**
 * Helper functions for MT
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check if current user has required capability
 */
function mt_can_manage() {
    return current_user_can('manage_options');
}

/**
 * Verify nonce for security
 */
function mt_verify_nonce($nonce, $action = 'mt_action') {
    return wp_verify_nonce($nonce, $action);
}

/**
 * Send JSON response
 */
function mt_send_json($data) {
    wp_send_json($data);
}

/**
 * Send JSON success response
 */
function mt_send_json_success($data = null) {
    wp_send_json_success($data);
}

/**
 * Send JSON error response
 */
function mt_send_json_error($data = null) {
    wp_send_json_error($data);
}

/**
 * Get wp-config.php file path
 */
function mt_get_wp_config_path() {
    // Try current WordPress root first
    $config_path = ABSPATH . 'wp-config.php';
    if (file_exists($config_path)) {
        return $config_path;
    }

    // Try parent directory (common in some installations)
    $parent_config = dirname(ABSPATH) . '/wp-config.php';
    if (file_exists($parent_config)) {
        return $parent_config;
    }

    return false;
}

/**
 * Get .htaccess file path
 */
function mt_get_htaccess_path() {
    return ABSPATH . '.htaccess';
}/**
 * Format file size
 */
function mt_format_bytes($size) {
    $units = array('B', 'KB', 'MB', 'GB');
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    return round($size, 2) . ' ' . $units[$i];
}

/**
 * Format execution time
 */
function mt_format_time($time) {
    if ($time < 1) {
        return round($time * 1000) . 'ms';
    }
    return round($time, 3) . 's';
}

/**
 * Get debug log file path
 */
function mt_get_debug_log_path() {
    return WP_CONTENT_DIR . '/debug.log';
}

/**
 * Get query log file path
 */
function mt_get_query_log_path() {
    return WP_CONTENT_DIR . '/query.log';
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
            wp_mkdir_p($dir);
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
    // Remove any potential malicious code patterns
    $patterns = array(
        '/(<\?php|<\?)/i',  // PHP tags
        '/(eval|exec|system|shell_exec|passthru)/i', // Dangerous functions
        '/(<script|javascript:)/i', // JavaScript
    );

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $content)) {
            return false; // Reject content with suspicious patterns
        }
    }

    return $content;
}