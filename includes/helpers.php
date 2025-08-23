<?php

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
    return WP_CONTENT_DIR . '/morden-toolkit/debug.log';
}

function mt_get_query_log_path() {
    return WP_CONTENT_DIR . '/morden-toolkit/query.log';
}

function mt_get_smtp_log_path() {
    return WP_CONTENT_DIR . '/morden-toolkit/mail.log';
}

function mt_get_error_log_path() {
    return WP_CONTENT_DIR . '/morden-toolkit/error.log';
}

function mt_get_log_directory() {
    return WP_CONTENT_DIR . '/morden-toolkit';
}

function mt_ensure_log_directory() {
    $log_dir = mt_get_log_directory();
    if (!is_dir($log_dir)) {
        if (function_exists('wp_mkdir_p')) {
            wp_mkdir_p($log_dir);
        } else {
            mkdir($log_dir, 0755, true);
        }
        

        $htaccess_file = $log_dir . '/.htaccess';
        if (!file_exists($htaccess_file)) {
            file_put_contents($htaccess_file, "Order deny,allow\nDeny from all\n");
        }
        

        $index_file = $log_dir . '/index.php';
        if (!file_exists($index_file)) {
            file_put_contents($index_file, "<?php\n// Silence is golden.\n");
        }
    }
    return $log_dir;
}

function mt_get_query_log_max_size() {
    return apply_filters('mt_query_log_max_size', 10 * 1024 * 1024);
}

function mt_get_debug_log_max_size() {
    return apply_filters('mt_debug_log_max_size', 50 * 1024 * 1024);
}

function mt_is_file_writable($file_path) {
    if (!file_exists($file_path)) {

        $dir = dirname($file_path);
        if (!is_dir($dir)) {
            if (function_exists('wp_mkdir_p')) {
                wp_mkdir_p($dir);
            } else {
                mkdir($dir, 0755, true);
            }
        }


        $handle = fopen($file_path, 'a');
        if ($handle) {
            fclose($handle);
            return true;
        }
        return false;
    }

    return is_writable($file_path);
}

function mt_sanitize_file_content($content) {

    $dangerous_patterns = array(
        '/(<\?php|<\?=)/i',
        '/(eval|exec|system|shell_exec|passthru)\s*\(/i',
        '/<script[^>]*>/i',
        '/javascript:\s*[^\s]/i'
    );

    foreach ($dangerous_patterns as $pattern) {
        if (preg_match($pattern, $content)) {
            return false;
        }
    }


    return $content;
}