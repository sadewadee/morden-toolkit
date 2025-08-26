<?php
/**
 * Debug Service - WP_DEBUG management
 */

if (!defined('ABSPATH')) {
    exit;
}

class MT_Debug {

    public function __construct() {
        $this->sync_debug_status();

        if (defined('SAVEQUERIES') && SAVEQUERIES && function_exists('add_action')) {
            add_action('wp_footer', array($this, 'write_queries_to_log'), 999);
            add_action('admin_footer', array($this, 'write_queries_to_log'), 999);
            add_action('shutdown', array($this, 'write_queries_to_log'), 1);
        }
    }

    public function sync_debug_status() {
        $actual_wp_debug = defined('WP_DEBUG') && WP_DEBUG;

        if (function_exists('get_option') && function_exists('update_option')) {
            $stored_option = get_option('mt_debug_enabled', null);
            if ($stored_option === null || $stored_option !== $actual_wp_debug) {
                update_option('mt_debug_enabled', $actual_wp_debug);
            }
        }
    }

    public function can_detect_debug_status() {
        return defined('WP_DEBUG');
    }

    public function toggle_debug($enable = true) {
        // Use WPConfigTransformer for safe debug constant management
        $debug_settings = $this->get_debug_constants();

        if (!$enable) {
            // Convert all values to false for disabling
            foreach ($debug_settings as $key => $value) {
                $debug_settings[$key] = false;
            }
        } else {
            // Convert string values to boolean for enabling
            foreach ($debug_settings as $key => $value) {
                if ($value === 'true') {
                    $debug_settings[$key] = true;
                } elseif ($value === 'false') {
                    $debug_settings[$key] = false;
                }
            }
        }

        // Use enhanced method for custom WP_DEBUG_LOG paths
        return MT_WP_Config_Integration::apply_debug_constants_enhanced($debug_settings, $enable);
    }

    public function enable_debug() {
        return $this->toggle_debug(true);
    }

    public function disable_debug() {
        $result = $this->toggle_debug(false);

        // Optional: Auto-cleanup logs when debug is disabled
        // This can be controlled via filter hook
        $auto_cleanup = apply_filters('mt_debug_auto_cleanup_on_disable', false);

        if ($auto_cleanup && $result) {
            $this->cleanup_debug_logs();
        }

        return $result;
    }

    public function toggle_debug_constant($constant, $enable) {
        // Prepare debug settings for WPConfigTransformer
        $debug_settings = array();

        // Auto-enable WP_DEBUG if any debug constant is being enabled
        if ($enable && $constant !== 'WP_DEBUG') {
            $debug_settings['WP_DEBUG'] = true;
        }

        // Handle special case for display_errors (ini setting)
        if ($constant === 'display_errors') {
            // For display_errors, we still need to handle it as ini_set
            // But let's use WPConfigTransformer's ini_set handling if available
            $debug_settings['display_errors'] = $enable ? '1' : '0';
        } else {
            // Regular debug constants
            $debug_settings[$constant] = $enable;
        }

        // Use enhanced method for custom WP_DEBUG_LOG paths
        return MT_WP_Config_Integration::apply_debug_constants_enhanced($debug_settings, true);
    }

    /**
     * Set individual debug constant
     */
    private function set_debug_constant($content, $constant, $enable) {
        $value = $enable ? 'true' : 'false';

        // Handle special cases
        if ($constant === 'display_errors') {
            return $this->set_ini_setting($content, 'display_errors', $enable ? '1' : '0');
        }

        // Handle WP_DEBUG conditional wrapper
        if ($constant === 'WP_DEBUG') {
            // Pattern for conditional WP_DEBUG - SAFE version
            $conditional_pattern = "/if\s*\(\s*!\s*defined\s*\(\s*['\"]WP_DEBUG['\"]\s*\)\s*\)\s*{\s*define\s*\(\s*['\"]WP_DEBUG['\"]\s*,\s*[^;)]+\s*\)\s*;\s*}/i";
            if (preg_match($conditional_pattern, $content)) {
                $replacement = "if ( ! defined( 'WP_DEBUG' ) ) {\n\tdefine('WP_DEBUG', " . $value . ");\n}";
                $content = preg_replace($conditional_pattern, $replacement, $content);
                return $content;
            }
        }

        // Standard define pattern - more flexible regex
        $patterns = array(
            "/define\s*\(\s*['\"]" . $constant . "['\"]\s*,\s*[^;)]+\s*\)\s*;/i",
            "/define\s*\(\s*['\"]" . $constant . "['\"]\s*,\s*[^;)]+\s*\);/i"
        );

        $replacement = "define('" . $constant . "', " . $value . ");";

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, $replacement, $content);
                return $content;
            }
        }

        // If constant not found, add it before "/* That's all" comment
        $insert_position = strpos($content, "/* That's all");
        if ($insert_position !== false) {
            $before = substr($content, 0, $insert_position);
            $after = substr($content, $insert_position);
            $content = $before . "define('" . $constant . "', " . $value . ");\n\n" . $after;
        } else {
            // Fallback: add at the end before closing PHP tag
            $content = rtrim($content);
            if (substr($content, -2) === '?>') {
                $content = substr($content, 0, -2) . "\ndefine('" . $constant . "', " . $value . ");\n?>";
            } else {
                $content .= "\ndefine('" . $constant . "', " . $value . ");\n";
            }
        }

        return $content;
    }

    /**
     * Set ini_set directive in wp-config.php
     */
    private function set_ini_setting($content, $setting, $value) {
        // Pattern for existing ini_set calls
        $patterns = array(
            "/@?ini_set\s*\(\s*['\"]" . $setting . "['\"]\s*,\s*[^)]+\s*\)\s*;/i",
            "/ini_set\s*\(\s*['\"]" . $setting . "['\"]\s*,\s*[^)]+\s*\)\s*;/i"
        );

        $replacement = "@ini_set('" . $setting . "', '" . $value . "');";

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, $replacement, $content);
                return $content;
            }
        }

        // If not found, add it before "/* That's all" comment
        $insert_position = strpos($content, "/* That's all");
        if ($insert_position !== false) {
            $before = substr($content, 0, $insert_position);
            $after = substr($content, $insert_position);
            $content = $before . "@ini_set('" . $setting . "', '" . $value . "');\n\n" . $after;
        } else {
            // Fallback: add at the end before closing PHP tag
            $content = rtrim($content);
            if (substr($content, -2) === '?>') {
                $content = substr($content, 0, -2) . "\n@ini_set('" . $setting . "', '" . $value . "');\n?>";
            } else {
                $content .= "\n@ini_set('" . $setting . "', '" . $value . "');\n";
            }
        }

        return $content;
    }

    /**
     * Get debug constants with filter hook for extensibility
     */
    private function get_debug_constants() {
        return array(
            'WP_DEBUG' => 'true',
            'WP_DEBUG_LOG' => 'true',
            'WP_DEBUG_DISPLAY' => 'false',
            'SCRIPT_DEBUG' => 'false',
            'SAVEQUERIES' => 'false'
        );
    }

    /**
     * Clear debug log file
     */
    public function clear_debug_log() {
        $log_path = mt_get_debug_log_path();

        if (!file_exists($log_path)) {
            return true; // Already cleared
        }

        return file_put_contents($log_path, '') !== false;
    }

    /**
     * Comprehensive cleanup of all debug-related files
     *
     * @param array $options Cleanup options
     * @return array Results of cleanup operations
     */
    public function cleanup_debug_logs($options = array()) {
        $default_options = array(
            'clear_debug_log' => true,
            'clear_query_log' => true,
            'remove_custom_log_files' => true,
            'remove_wp_config_constants' => false // Keep constants but set to false
        );

        $options = array_merge($default_options, $options);
        $results = array();

        // Clear main debug log
        if ($options['clear_debug_log']) {
            $results['debug_log_cleared'] = $this->clear_debug_log();
        }

        // Clear query log
        if ($options['clear_query_log']) {
            $results['query_log_cleared'] = $this->clear_query_log();
            $results['old_query_logs_cleaned'] = $this->cleanup_old_query_logs();
        }

        // Remove custom log files in morden-toolkit directory
        if ($options['remove_custom_log_files']) {
            $results['custom_logs_removed'] = $this->remove_custom_debug_files();
        }

        // Optionally remove/disable WP_DEBUG constants
        if ($options['remove_wp_config_constants']) {
            $results['constants_removed'] = $this->remove_debug_constants_from_config();
        }

        return $results;
    }

    /**
     * Get information about existing debug log files
     *
     * @return array Information about log files
     */
    public function get_debug_log_files_info() {
        $info = array(
            'main_debug_log' => array(),
            'custom_debug_logs' => array(),
            'query_logs' => array(),
            'total_size' => 0,
            'total_files' => 0
        );

        // Main debug log
        $main_log_path = mt_get_debug_log_path();
        if (file_exists($main_log_path)) {
            $size = filesize($main_log_path);
            $info['main_debug_log'] = array(
                'path' => $main_log_path,
                'size' => $size,
                'size_formatted' => size_format($size),
                'modified' => filemtime($main_log_path),
                'modified_formatted' => date('Y-m-d H:i:s', filemtime($main_log_path))
            );
            $info['total_size'] += $size;
            $info['total_files']++;
        }

        // Custom debug logs in morden-toolkit directory
        $log_directory = ABSPATH . 'wp-content/morden-toolkit/';
        if (is_dir($log_directory)) {
            // Debug logs
            $debug_pattern = $log_directory . 'wp-errors-*.log';
            $custom_debug_logs = glob($debug_pattern);

            foreach ($custom_debug_logs as $log_file) {
                if (file_exists($log_file)) {
                    $size = filesize($log_file);
                    $info['custom_debug_logs'][] = array(
                        'path' => $log_file,
                        'filename' => basename($log_file),
                        'size' => $size,
                        'size_formatted' => size_format($size),
                        'modified' => filemtime($log_file),
                        'modified_formatted' => date('Y-m-d H:i:s', filemtime($log_file))
                    );
                    $info['total_size'] += $size;
                    $info['total_files']++;
                }
            }

            // Custom query logs
            $query_pattern = $log_directory . 'wp-queries-*.log';
            $custom_query_logs = glob($query_pattern);

            foreach ($custom_query_logs as $log_file) {
                if (file_exists($log_file)) {
                    $size = filesize($log_file);
                    $info['query_logs']['custom'][] = array(
                        'path' => $log_file,
                        'filename' => basename($log_file),
                        'size' => $size,
                        'size_formatted' => size_format($size),
                        'modified' => filemtime($log_file),
                        'modified_formatted' => date('Y-m-d H:i:s', filemtime($log_file))
                    );
                    $info['total_size'] += $size;
                    $info['total_files']++;
                }
            }
        }

        // Query logs
        $query_log_path = mt_get_query_log_path();
        if (file_exists($query_log_path)) {
            $size = filesize($query_log_path);
            $info['query_logs']['main'] = array(
                'path' => $query_log_path,
                'size' => $size,
                'size_formatted' => size_format($size),
                'modified' => filemtime($query_log_path),
                'modified_formatted' => date('Y-m-d H:i:s', filemtime($query_log_path))
            );
            $info['total_size'] += $size;
            $info['total_files']++;
        }

        // Query log backups
        $query_log_dir = dirname($query_log_path);
        $query_log_name = basename($query_log_path);
        $backup_pattern = $query_log_dir . '/' . $query_log_name . '.*';
        $backup_logs = glob($backup_pattern);

        foreach ($backup_logs as $backup_log) {
            if (file_exists($backup_log)) {
                $size = filesize($backup_log);
                $info['query_logs']['backups'][] = array(
                    'path' => $backup_log,
                    'filename' => basename($backup_log),
                    'size' => $size,
                    'size_formatted' => size_format($size),
                    'modified' => filemtime($backup_log),
                    'modified_formatted' => date('Y-m-d H:i:s', filemtime($backup_log))
                );
                $info['total_size'] += $size;
                $info['total_files']++;
            }
        }

        $info['total_size_formatted'] = size_format($info['total_size']);

        return $info;
    }

    /**
     * Remove custom debug log files from morden-toolkit directory
     *
     * @return int Number of files removed
     */
    private function remove_custom_debug_files() {
        $removed_count = 0;

        // Look for wp-errors-*.log and wp-queries-*.log files in morden-toolkit directory
        $log_directory = ABSPATH . 'wp-content/morden-toolkit/';

        if (!is_dir($log_directory)) {
            return 0;
        }

        // Patterns for both debug and query logs
        $patterns = [
            $log_directory . 'wp-errors-*.log',   // Debug logs
            $log_directory . 'wp-queries-*.log',  // Query logs
            $log_directory . 'query.log'          // Default query log
        ];

        foreach ($patterns as $pattern) {
            $log_files = glob($pattern);

            foreach ($log_files as $log_file) {
                if (file_exists($log_file) && unlink($log_file)) {
                    $removed_count++;
                    mt_debug_log('Removed custom log file: ' . basename($log_file));
                }
            }
        }

        return $removed_count;
    }

    /**
     * Remove or disable debug constants from wp-config.php
     *
     * @return bool Success status
     */
    private function remove_debug_constants_from_config() {
        // Use WPConfigTransformer to safely disable debug constants
        $debug_settings = array(
            'WP_DEBUG' => false,
            'WP_DEBUG_LOG' => false,
            'WP_DEBUG_DISPLAY' => false,
            'SCRIPT_DEBUG' => false,
            'SAVEQUERIES' => false
        );

        return MT_WP_Config_Integration::apply_debug_constants($debug_settings);
    }

    /**
     * Enable custom query log path
     *
     * @param bool $enable Whether to enable custom query log path
     * @return bool Success status
     */
    public function toggle_custom_query_log_path($enable = true) {
        return MT_WP_Config_Integration::apply_custom_query_log_path($enable);
    }

    /**
     * Enable custom query log path
     *
     * @return bool Success status
     */
    public function enable_custom_query_log_path() {
        return $this->toggle_custom_query_log_path(true);
    }

    /**
     * Disable custom query log path
     *
     * @return bool Success status
     */
    public function disable_custom_query_log_path() {
        return $this->toggle_custom_query_log_path(false);
    }

    /**
     * Get debug log entries (latest 50)
     */
    public function get_debug_log_entries($limit = 50) {
        $log_path = mt_get_debug_log_path();

        if (!file_exists($log_path)) {
            return array();
        }

        $content = file_get_contents($log_path);
        $lines = explode("\n", trim($content));

        // Get latest entries
        $lines = array_slice($lines, -$limit);
        $entries = array();

        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }

            $entry = $this->parse_log_line($line);
            if ($entry) {
                $entries[] = $entry;
            }
        }

        return array_reverse($entries); // Show newest first
    }

    /**
     * Enhanced log parsing with improved caller stack
     */
    public function get_enhanced_debug_log_entries($limit = 50) {
        $entries = $this->get_debug_log_entries($limit);

        // Enhance each entry with better caller formatting
        foreach ($entries as &$entry) {
            if (!empty($entry['raw']) && strpos($entry['raw'], 'Caller:') !== false) {
                // Extract caller information
                if (preg_match('/Caller: (.+)$/', $entry['raw'], $matches)) {
                    $raw_caller = trim($matches[1]);
                    $entry['enhanced_caller'] = $this->format_caller_stack($raw_caller);
                }
            }
        }

        return $entries;
    }

    /**
     * Format a single log entry with enhanced caller information
     */
    public function format_enhanced_log_entry($entry) {
        $output = array();

        // Header with timestamp and level
        $level_colors = array(
            'ERROR' => '\033[1;31m',     // Bright red
            'WARNING' => '\033[1;33m',   // Bright yellow
            'NOTICE' => '\033[1;36m',    // Bright cyan
            'DEPRECATED' => '\033[0;35m' // Purple
        );

        $reset = '\033[0m';
        $level = $entry['level'] ?? 'NOTICE';
        $color = $level_colors[$level] ?? $level_colors['NOTICE'];

        $output[] = sprintf(
            "[%s] %s%s%s: %s",
            $entry['timestamp'] ?? 'Unknown',
            $color,
            $level,
            $reset,
            $entry['message'] ?? 'No message'
        );

        // File and line information
        if (!empty($entry['file']) && !empty($entry['line'])) {
            $output[] = sprintf("    File: %s:%s", $entry['file'], $entry['line']);
        }

        // Enhanced caller stack if available
        if (!empty($entry['enhanced_caller'])) {
            $output[] = "    Call Stack:";
            $caller_lines = explode("\n", $entry['enhanced_caller']);
            foreach ($caller_lines as $line) {
                if (!empty(trim($line))) {
                    $output[] = "    " . $line;
                }
            }
        }

        return implode("\n", $output);
    }

    /**
     * Parse single log line
     */
    private function parse_log_line($line) {
        // Common WordPress debug.log format: [timestamp] PHP Error: message in file on line
        $pattern = '/^\[([^\]]+)\]\s+(PHP\s+)?([^:]+):\s+(.+?)(\s+in\s+(.+?)\s+on\s+line\s+(\d+))?$/';

        if (preg_match($pattern, $line, $matches)) {
            return array(
                'timestamp' => $matches[1],
                'level' => $this->normalize_log_level($matches[3]),
                'message' => $matches[4],
                'file' => isset($matches[6]) ? $matches[6] : '',
                'line' => isset($matches[7]) ? $matches[7] : '',
                'raw' => $line
            );
        }

        // Fallback for non-standard format
        return array(
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => 'NOTICE',
            'message' => $line,
            'file' => '',
            'line' => '',
            'raw' => $line
        );
    }

    /**
     * Normalize log level names
     */
    private function normalize_log_level($level) {
        $level = strtoupper(trim($level));

        $level_map = array(
            'FATAL ERROR' => 'ERROR',
            'PARSE ERROR' => 'ERROR',
            'WARNING' => 'WARNING',
            'NOTICE' => 'NOTICE',
            'DEPRECATED' => 'DEPRECATED'
        );

        return isset($level_map[$level]) ? $level_map[$level] : 'NOTICE';
    }





    /**
     * Get current debug status
     */
    public function get_debug_status() {
        // Check actual wp-config.php status
        $actual_wp_debug = defined('WP_DEBUG') && WP_DEBUG;

        // Sync option with actual status if different
        if (function_exists('get_option') && function_exists('update_option')) {
            $stored_option = get_option('mt_debug_enabled', false);
            if ($stored_option !== $actual_wp_debug) {
                update_option('mt_debug_enabled', $actual_wp_debug);
            }
        }

        // Check display_errors ini setting
        $display_errors = ini_get('display_errors') == '1' || ini_get('display_errors') === 'On';

        // Enhanced WP_DEBUG_LOG detection
        $wp_debug_log_enabled = false;
        $wp_debug_log_path = null;
        $wp_debug_log_custom = false;

        if (defined('WP_DEBUG_LOG')) {
            if (is_string(WP_DEBUG_LOG) && !in_array(WP_DEBUG_LOG, ['true', 'false', '1', '0'], true)) {
                // Custom path detected
                $wp_debug_log_enabled = true;
                $wp_debug_log_path = WP_DEBUG_LOG;
                $wp_debug_log_custom = true;
            } else {
                // Boolean value
                $wp_debug_log_enabled = (bool) WP_DEBUG_LOG;
                $wp_debug_log_path = mt_get_debug_log_path();
            }
        }

        return array(
            'enabled' => $actual_wp_debug, // Use actual status, not stored option
            'wp_debug' => $actual_wp_debug,
            'wp_debug_log' => $wp_debug_log_enabled,
            'wp_debug_log_path' => $wp_debug_log_path,
            'wp_debug_log_custom' => $wp_debug_log_custom,
            'wp_debug_display' => defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY,
            'script_debug' => defined('SCRIPT_DEBUG') && SCRIPT_DEBUG,
            'savequeries' => defined('SAVEQUERIES') && SAVEQUERIES,
            'display_errors' => $display_errors,
            'log_file_exists' => file_exists(mt_get_debug_log_path()),
            'log_file_size' => file_exists(mt_get_debug_log_path()) ?
                size_format(filesize(mt_get_debug_log_path())) : '0 B',
            'query_log_file_exists' => file_exists(mt_get_query_log_path()),
            'query_log_file_size' => file_exists(mt_get_query_log_path()) ?
                size_format(filesize(mt_get_query_log_path())) : '0 B',
            'query_log_total_size' => size_format($this->get_query_log_total_size()),
            'query_log_max_size' => size_format(mt_get_query_log_max_size())
        );
    }

    /**
     * Get database query logs from $wpdb->queries
     */
    public function get_query_logs() {
        global $wpdb;

        if (!defined('SAVEQUERIES') || !SAVEQUERIES || empty($wpdb->queries)) {
            return array(
                'queries' => array(),
                'total_queries' => 0,
                'total_time' => 0,
                'slow_queries' => 0,
                'duplicate_queries' => 0,
                'memory_usage' => memory_get_usage(),
                'peak_memory' => memory_get_peak_usage()
            );
        }

        $queries = array();
        $total_time = 0;
        $slow_queries = 0;
        $query_hashes = array();
        $duplicate_queries = 0;

        foreach ($wpdb->queries as $index => $query) {
            list($sql, $time, $caller) = $query;

            // Calculate query hash for duplicate detection
            $query_hash = md5(preg_replace('/\s+/', ' ', trim($sql)));
            if (isset($query_hashes[$query_hash])) {
                $duplicate_queries++;
            } else {
                $query_hashes[$query_hash] = true;
            }

            // Determine query type
            $sql_upper = strtoupper(trim($sql));
            if (strpos($sql_upper, 'SELECT') === 0) {
                $type = 'SELECT';
            } elseif (strpos($sql_upper, 'INSERT') === 0) {
                $type = 'INSERT';
            } elseif (strpos($sql_upper, 'UPDATE') === 0) {
                $type = 'UPDATE';
            } elseif (strpos($sql_upper, 'DELETE') === 0) {
                $type = 'DELETE';
            } else {
                $type = 'OTHER';
            }

            // Check if slow query (>10ms)
            $is_slow = $time > 0.01;
            if ($is_slow) {
                $slow_queries++;
            }

            $total_time += $time;

            $queries[] = array(
                'id' => $index + 1,
                'sql' => $sql,
                'time' => round($time * 1000, 2), // Convert to milliseconds
                'caller' => $caller,
                'type' => $type,
                'is_slow' => $is_slow,
                'is_duplicate' => isset($query_hashes[$query_hash]) && $query_hashes[$query_hash] > 1
            );
        }

        return array(
            'queries' => $queries,
            'total_queries' => count($wpdb->queries),
            'total_time' => round($total_time * 1000, 2), // Convert to milliseconds
            'slow_queries' => $slow_queries,
            'duplicate_queries' => $duplicate_queries,
            'memory_usage' => memory_get_usage(),
            'peak_memory' => memory_get_peak_usage()
        );
    }

    /**
     * Write database queries to query.log file
     */
    public function write_queries_to_log() {
        global $wpdb;

        // Prevent multiple writes per request
        static $written = false;
        if ($written) {
            return false;
        }

        if (!defined('SAVEQUERIES') || !SAVEQUERIES || empty($wpdb->queries)) {
            return false;
        }

        $query_log_path = mt_get_query_log_path();

        // Check and rotate log if needed before writing
        $this->rotate_query_log_if_needed($query_log_path);

        $log_content = '';

        // Header dengan informasi request
        $log_content .= "[" . date('Y-m-d H:i:s') . "] === NEW REQUEST ===\n";
        $log_content .= "URL: " . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'CLI') . "\n";
        $log_content .= "Total Queries: " . count($wpdb->queries) . "\n";

        $total_time = 0;
        foreach ($wpdb->queries as $query) {
            $total_time += $query[1];
        }
        $log_content .= "Total Time: " . round($total_time * 1000, 2) . "ms\n";
        $log_content .= "Memory Usage: " . size_format(memory_get_peak_usage()) . "\n";
        $log_content .= "----------------------------------------\n";

        // Detail setiap query dengan enhanced caller stack
        foreach ($wpdb->queries as $index => $query) {
            list($sql, $time, $caller) = $query;

            $log_content .= "Query #" . ($index + 1) . ":\n";
            $log_content .= "SQL: " . $sql . "\n";
            $log_content .= "Time: " . round($time * 1000, 2) . "ms\n";

            // Get enhanced caller stack
            $enhanced_caller = $this->enhance_caller_with_backtrace($caller);
            $log_content .= "Caller Stack:\n" . $enhanced_caller . "\n";

            // Mark slow queries
            if ($time > 0.01) {
                $log_content .= "*** SLOW QUERY WARNING ***\n";
            }

            $log_content .= "---\n";
        }

        $log_content .= "\n";

        // Write to file
        $result = file_put_contents($query_log_path, $log_content, FILE_APPEND | LOCK_EX);
        $written = true;

        return $result !== false;
    }

    /**
     * Rotate query log if it exceeds size limit
     */
    private function rotate_query_log_if_needed($log_path) {
        if (!file_exists($log_path)) {
            return;
        }

        // Default max size: 10MB (dapat dikonfigurasi melalui filter)
        $max_size = mt_get_query_log_max_size();
        $current_size = filesize($log_path);

        if ($current_size <= $max_size) {
            return;
        }

        // Rotate: query.log -> query.log.1, dan truncate query.log
        $backup_path = $log_path . '.1';

        // Remove old backup if exists
        if (file_exists($backup_path)) {
            unlink($backup_path);
        }

        // Move current log to backup
        rename($log_path, $backup_path);

        // Create new empty log file
        file_put_contents($log_path, '');

        // Log rotation info
        mt_debug_log("Query log rotated. Size was " . size_format($current_size));
    }

    /**
     * Clean up old query log files (remove all rotation/archived files like query.log.1, query.log.2, etc.)
     * This removes retention/rotation/archived logs but keeps the active query.log
     */
    public function cleanup_old_query_logs() {
        $log_path = mt_get_query_log_path();
        $log_dir = dirname($log_path);
        $log_name = basename($log_path);

        // Look for all rotation log files (query.log.1, query.log.2, etc)
        $pattern = $log_dir . '/' . $log_name . '.*';
        $old_logs = glob($pattern);

        $cleaned = 0;
        foreach ($old_logs as $old_log) {
            // Remove ALL rotation files (.1, .2, .3, etc.) - this is what cleanup should do
            if (preg_match('/\.\d+$/', $old_log)) {
                if (file_exists($old_log) && unlink($old_log)) {
                    $cleaned++;
                    mt_debug_log('Cleaned up rotation log file: ' . basename($old_log));
                }
            }
        }

        return $cleaned;
    }

    /**
     * Get total query log size including backups
     */
    public function get_query_log_total_size() {
        $log_path = mt_get_query_log_path();
        $log_dir = dirname($log_path);
        $log_name = basename($log_path);

        $total_size = 0;

        // Main log file
        if (file_exists($log_path)) {
            $total_size += filesize($log_path);
        }

        // Backup files
        $pattern = $log_dir . '/' . $log_name . '.*';
        $backup_logs = glob($pattern);

        foreach ($backup_logs as $backup_log) {
            if (file_exists($backup_log)) {
                $total_size += filesize($backup_log);
            }
        }

        return $total_size;
    }

    /**
     * Format caller stack trace for better readability
     * Improved version with line splitting, bootstrap filtering, and colored output
     */
    private function format_caller_stack($raw_caller) {
        if (empty($raw_caller)) {
            return "No caller information available";
        }

        // Parse and enhance the trace
        $trace_entries = $this->parse_enhanced_trace($raw_caller);

        if (empty($trace_entries)) {
            return $raw_caller; // Fallback to original if parsing fails
        }

        // Format with colors and indentation
        return $this->build_formatted_trace($trace_entries);
    }

    /**
     * Parse enhanced trace with filtering and classification
     */
    private function parse_enhanced_trace($caller) {
        // Split by comma and clean each part
        $parts = explode(',', $caller);
        $trace_entries = array();
        $prev_file_info = null;

        foreach ($parts as $i => $part) {
            $part = trim($part);
            if (empty($part)) continue;

            // Skip file includes like ('wp-load.php')
            if (preg_match("/^\(['\"](.+?)['\"]?\)$/", $part, $matches)) {
                continue;
            }

            // Check for file:line pattern
            if (preg_match('/^(.+\.php):(\d+)$/', $part, $matches)) {
                $prev_file_info = array(
                    'file' => $matches[1],
                    'line' => $matches[2]
                );
                continue;
            }

            // Parse function call
            $entry = $this->parse_enhanced_function_call($part, $prev_file_info);
            if ($entry && $this->should_include_in_trace($entry)) {
                $trace_entries[] = $entry;
            }

            $prev_file_info = null; // Reset after use
        }

        return $this->deduplicate_trace_entries($trace_entries);
    }

    /**
     * Parse enhanced function call with classification
     */
    private function parse_enhanced_function_call($call, $file_info = null) {
        $call = trim($call);

        if (empty($call)) {
            return null;
        }

        // Clean up function name
        $function_name = $this->normalize_function_name($call);

        if (empty($function_name)) {
            return null;
        }

        // Classify the function type
        $type = $this->classify_function_type($function_name);

        // Get file information
        if ($file_info) {
            $file_path = $file_info['file'];
            $line_number = $file_info['line'];
        } else {
            $file_data = $this->get_enhanced_file_info($function_name);
            $file_path = $file_data['file'] ?? null;
            $line_number = $file_data['line'] ?? null;
        }

        // Apply namespace aliasing
        $display_name = $this->apply_namespace_aliasing($function_name);

        return array(
            'function' => $function_name,
            'display_name' => $display_name,
            'type' => $type,
            'file' => $file_path,
            'line' => $line_number,
            'is_plugin_entry' => $this->is_plugin_entry_point($function_name, $file_path)
        );
    }

    /**
     * Normalize function/method names
     */
    private function normalize_function_name($name) {
        // Remove extra whitespace
        $name = preg_replace('/\s+/', ' ', trim($name));

        // Handle different function call formats
        $name = str_replace(' (', '(', $name);
        $name = str_replace(' ->', '->', $name);
        $name = str_replace(' ::', '::', $name);

        // Remove trailing parentheses if they exist
        $name = preg_replace('/\(\)$/', '', $name);

        return $name;
    }

    /**
     * Classify function type for better filtering and display
     */
    private function classify_function_type($function_name) {
        // WordPress core wrapper/bootstrap functions to remove
        $wp_wrappers = array(
            'require', 'require_once', 'include', 'include_once',
            "require('wp-blog-header.php')", "require_once('wp-load.php')",
            "require_once('wp-config.php')", "require_once('wp-settings.php')",
            'wp_initial_constants', 'wp_check_php_mysql_versions',
            'wp_maintenance', 'timer_start', 'wp_debug_mode',
            'wp_set_internal_encoding', 'wp_cache_init',
            'wp_start_scraping_edited_file_errors', 'wp_load_alloptions',
            'wp_start_object_cache', 'wp_not_installed', 'wp_blog_header',
            'wp_loaded'
        );

        // Hook functions (should be grouped)
        $hook_functions = array(
            'WP_Hook->do_action', 'WP_Hook->apply_filters',
            'do_action', 'apply_filters', 'do_action_ref_array',
            'apply_filters_ref_array'
        );

        // Check if it's a WordPress wrapper/bootstrap function
        foreach ($wp_wrappers as $wrapper) {
            if (strpos($function_name, $wrapper) !== false) {
                return 'wp_wrapper';
            }
        }

        // Check if it's a hook function
        foreach ($hook_functions as $hook) {
            if (strpos($function_name, $hook) !== false) {
                return 'hook';
            }
        }

        // Check for FQCN (Fully Qualified Class Name) with namespace
        if (strpos($function_name, '\\') !== false) {
            return 'fqcn_plugin';
        }

        // Plugin functions (non-core, non-vendor)
        $plugin_patterns = array(
            '/^MT_/', '/^mt_/', '/morden-toolkit/', '/class-.*\.php/',
            '/^wpforms/', '/^woocommerce/', '/^yoast/', '/^elementor/',
            '/^jetpack/', '/^gravityforms/', '/^acf/', '/^bbpress/',
            '/^buddypress/', '/^wpcf7/', '/^wp_/', '/^WP_/',
            '/plugins\/[^\/]+\/[^\/]+\.(php|inc)/',
            '/themes\/[^\/]+\/functions\.php/'
        );

        // User/theme functions
        $user_patterns = array(
            '/themes\//', '/mu-plugins\//', '/uploads\//',
            '/wp-content\/((?!plugins\/wordpress).)*/',
            '/child-theme\//', '/custom\//', '/site-specific\//',
            '/^custom_/', '/^theme_/', '/^child_/'
        );

        foreach ($plugin_patterns as $pattern) {
            if (preg_match($pattern, $function_name)) {
                return 'plugin';
            }
        }

        foreach ($user_patterns as $pattern) {
            if (preg_match($pattern, $function_name)) {
                return 'user';
            }
        }

        // Check if it's a WordPress core function (more comprehensive)
        if (strpos($function_name, 'wp_') === 0 || strpos($function_name, 'WP_') === 0 ||
            strpos($function_name, '_wp_') === 0 || strpos($function_name, 'wp-') !== false ||
            strpos($function_name, 'get_') === 0 || strpos($function_name, 'is_') === 0 ||
            strpos($function_name, 'has_') === 0 || strpos($function_name, 'the_') === 0 ||
            strpos($function_name, 'wp-includes/') !== false ||
            strpos($function_name, 'wp-admin/') !== false) {
            return 'core';
        }

        // Check for vendor/composer functions
        if (strpos($function_name, 'vendor/') !== false ||
            strpos($function_name, 'Composer\\') !== false ||
            strpos($function_name, 'Symfony\\') !== false ||
            strpos($function_name, 'Psr\\') !== false) {
            return 'vendor';
        }

        return 'unknown';
    }



    /**
     * Check if function should be included in trace (filtering logic)
     */
    private function should_include_in_trace($entry) {
        if (!is_array($entry)) {
            return false;
        }

        $type = $entry['type'] ?? 'unknown';
        $function = $entry['function'] ?? '';

        // Always include plugin entry points
        if ($entry['is_plugin_entry'] ?? false) {
            return true;
        }

        // Skip WordPress wrappers and vendor functions
        if (in_array($type, array('wp_wrapper', 'vendor'))) {
            return false;
        }

        // Include FQCN plugin functions (they're important)
        if ($type === 'fqcn_plugin') {
            return true;
        }

        // Skip bootstrap functions unless they're plugin-related
        if ($type === 'bootstrap' && $entry['type'] !== 'plugin') {
            return false;
        }

        // Include user and plugin functions
        if (in_array($type, array('user', 'plugin'))) {
            return true;
        }

        // Include important core functions but not repetitive hooks
        if ($type === 'core') {
            $important_functions = array(
                'wp_die', 'wp_redirect', 'wp_enqueue_script',
                'wp_enqueue_style', 'wp_head', 'wp_footer',
                'current_user_can', 'get_user_by', 'wp_validate_auth_cookie',
                'get_user_meta', 'get_metadata', 'WP_User'
            );

            foreach ($important_functions as $important) {
                if (strpos($function, $important) !== false) {
                    return true;
                }
            }
            return false;
        }

        // Limit hook functions but include some important ones
        if ($type === 'hook') {
            // Include hook functions with specific action names
            if (preg_match('/(plugins_loaded|init|wp_loaded|admin_init|wpforms_loaded)/', $function)) {
                return true;
            }
            return false;
        }

        return $type !== 'unknown'; // Include everything except unknown by default
    }

    /**
     * Remove duplicate consecutive entries
     */
    private function deduplicate_trace_entries($entries) {
        if (empty($entries)) {
            return $entries;
        }

        $deduplicated = array();
        $prev_function = null;
        $consecutive_count = 0;

        foreach ($entries as $entry) {
            $current_function = $entry['function'] ?? '';

            if ($current_function === $prev_function) {
                $consecutive_count++;
                if ($consecutive_count <= 2) { // Allow up to 2 consecutive
                    $deduplicated[] = $entry;
                } elseif ($consecutive_count === 3) {
                    // Add "repeated" indicator
                    $deduplicated[] = array(
                        'function' => '... (repeated)',
                        'display_name' => '... (repeated)',
                        'type' => 'repeat',
                        'file' => null,
                        'line' => null
                    );
                }
            } else {
                $consecutive_count = 1;
                $deduplicated[] = $entry;
            }

            $prev_function = $current_function;
        }

        return $deduplicated;
    }

    /**
     * Apply namespace aliasing for better readability
     */
    private function apply_namespace_aliasing($function_name) {
        // Handle FQCN (Fully Qualified Class Names) with namespaces
        if (strpos($function_name, '\\') !== false) {
            // Extract namespace and convert to plugin alias
            if (preg_match('/^([^\\\\]+)\\\\(.+)$/', $function_name, $matches)) {
                $namespace = $matches[1];
                $class_method = $matches[2];

                // Handle closures and anonymous functions
                if (strpos($class_method, '{closure}') !== false) {
                    return $namespace . ' → anon-function';
                }

                // Handle regular class methods
                if (strpos($class_method, '->') !== false || strpos($class_method, '::') !== false) {
                    return $namespace . ' → ' . $class_method;
                }

                // Just the class name
                return $namespace . ' → ' . $class_method;
            }
        }

        $aliases = array(
            // Plugin namespace aliases
            'MT_Plugin' => 'Plugin',
            'MT_Debug' => 'Debug',
            'MT_Query_Monitor' => 'QueryMonitor',
            'MT_Htaccess' => 'Htaccess',
            'MT_PHP_Config' => 'PhpConfig',
            'MT_SMTP_Logger' => 'SmtpLogger',
            'MT_WP_Config_Integration' => 'WpConfigIntegration',
            'MT_File_Manager' => 'FileManager',

            // WordPress core aliases
            'WP_Hook->do_action' => 'Hook::do_action',
            'WP_Hook->apply_filters' => 'Hook::apply_filters',
            'WP_User->get_caps_data' => 'User::get_caps_data',
            'WP_User->for_site' => 'User::for_site',
            'WP_User->init' => 'User::init',
            'WP_User::get_data_by' => 'User::get_data_by'
        );

        foreach ($aliases as $original => $alias) {
            if (strpos($function_name, $original) !== false) {
                return str_replace($original, $alias, $function_name);
            }
        }

        // Shorten long file paths
        $function_name = str_replace('/wp-content/plugins/morden-toolkit/', 'MT/', $function_name);
        $function_name = str_replace('/wp-includes/', 'WP/', $function_name);
        $function_name = str_replace('/wp-admin/', 'Admin/', $function_name);
        $function_name = str_replace('/wp-content/plugins/', 'Plugin/', $function_name);
        $function_name = str_replace('/wp-content/themes/', 'Theme/', $function_name);

        return $function_name;
    }

    /**
     * Detect plugin entry points
     */
    private function is_plugin_entry_point($function_name, $file_path = null) {
        $entry_patterns = array(
            // Plugin initialization functions
            'mt_init', 'MT_Plugin::get_instance', 'register_activation_hook',
            'register_deactivation_hook', 'add_action', 'add_filter',

            // AJAX handlers
            'wp_ajax_', 'wp_ajax_nopriv_',

            // Plugin class constructors
            'MT_Plugin->__construct', 'MT_Debug->__construct'
        );

        foreach ($entry_patterns as $pattern) {
            if (strpos($function_name, $pattern) !== false) {
                return true;
            }
        }

        // Check file path for plugin entry
        if ($file_path && strpos($file_path, 'morden-toolkit') !== false) {
            $entry_files = array('morden-toolkit.php', 'class-plugin.php');
            foreach ($entry_files as $file) {
                if (strpos($file_path, $file) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Build formatted trace with colors and indentation
     */
    private function build_formatted_trace($trace_entries) {
        if (empty($trace_entries)) {
            return "No trace information available";
        }

        $output = array();

        foreach ($trace_entries as $i => $entry) {
            $line = $this->format_trace_entry($entry, $i);
            if (!empty($line)) {
                $output[] = $line;
            }
        }

        return implode("\n", $output);
    }

    /**
     * Format individual trace entry with colors and indentation
     */
    private function format_trace_entry($entry, $index) {
        if (!is_array($entry)) {
            return '';
        }

        $function = $entry['display_name'] ?? $entry['function'] ?? 'unknown';
        $type = $entry['type'] ?? 'unknown';
        $file = $entry['file'] ?? null;
        $line = $entry['line'] ?? null;
        $is_entry = $entry['is_plugin_entry'] ?? false;

        // Color coding based on type
        $colors = array(
            'plugin' => '\033[1;32m',        // Bright green
            'fqcn_plugin' => '\033[1;32m',   // Bright green for FQCN plugins
            'user' => '\033[1;34m',          // Bright blue
            'core' => '\033[0;37m',          // Light gray
            'hook' => '\033[0;33m',          // Yellow
            'wp_wrapper' => '\033[0;90m',    // Dark gray
            'vendor' => '\033[0;35m',        // Purple
            'bootstrap' => '\033[0;90m',     // Dark gray
            'repeat' => '\033[0;90m',        // Dark gray
            'unknown' => '\033[0;37m'        // Light gray
        );

        $reset = '\033[0m';
        $color = $colors[$type] ?? $colors['unknown'];

        // Build the line
        $output = sprintf("%s%2d. %s%s%s()%s",
            $is_entry ? '>>> ' : '    ',
            $index + 1,
            $color,
            $function,
            $reset,
            $is_entry ? ' [ENTRY]' : ''
        );

        // Add file information if available
        if ($file || $line) {
            $file_info = '';
            if ($file) {
                $file_info .= basename($file);
            }
            if ($line) {
                $file_info .= ':' . $line;
            }
            $output .= "\n      " . $color . "-> " . $file_info . $reset;
        }

        return $output;
    }

    /**
     * Get enhanced file information with line numbers
     */
    private function get_enhanced_file_info($function_name) {
        // This extends the original get_file_info_for_function with more complete mapping
        $enhanced_map = array(
            // Plugin functions with line numbers
            'mt_init' => array('file' => 'morden-toolkit.php', 'line' => 45),
            'MT_Plugin::get_instance' => array('file' => 'includes/class-plugin.php', 'line' => 26),
            'MT_Plugin->__construct' => array('file' => 'includes/class-plugin.php', 'line' => 35),
            'MT_Plugin->init_services' => array('file' => 'includes/class-plugin.php', 'line' => 72),
            'MT_Debug->__construct' => array('file' => 'includes/class-debug.php', 'line' => 23),
            'MT_Query_Monitor->__construct' => array('file' => 'includes/class-query-monitor.php', 'line' => 23),

            // WordPress core functions
            'update_meta_cache' => array('file' => 'wp-includes/meta.php', 'line' => 1189),
            'get_metadata_raw' => array('file' => 'wp-includes/meta.php', 'line' => 659),
            'get_metadata' => array('file' => 'wp-includes/meta.php', 'line' => 586),
            'get_user_meta' => array('file' => 'wp-includes/user.php', 'line' => 1271),
            'get_user_by' => array('file' => 'wp-includes/pluggable.php', 'line' => 109),
            'wp_validate_auth_cookie' => array('file' => 'wp-includes/pluggable.php', 'line' => 750),
            '_wp_get_current_user' => array('file' => 'wp-includes/user.php', 'line' => 3753),
            'wp_get_current_user' => array('file' => 'wp-includes/pluggable.php', 'line' => 70),
            'is_user_logged_in' => array('file' => 'wp-includes/pluggable.php', 'line' => 1234),
            'apply_filters' => array('file' => 'wp-includes/plugin.php', 'line' => 205),
            'do_action' => array('file' => 'wp-includes/plugin.php', 'line' => 456),
            'WP_User->get_caps_data' => array('file' => 'wp-includes/class-wp-user.php', 'line' => 906),
            'WP_User->for_site' => array('file' => 'wp-includes/class-wp-user.php', 'line' => 881),
            'WP_User->init' => array('file' => 'wp-includes/class-wp-user.php', 'line' => 185),
            'WP_Hook->do_action' => array('file' => 'wp-includes/class-wp-hook.php', 'line' => 312),
            'WP_Hook->apply_filters' => array('file' => 'wp-includes/class-wp-hook.php', 'line' => 324),
            'get_option' => array('file' => 'wp-includes/option.php', 'line' => 143),
            'update_option' => array('file' => 'wp-includes/option.php', 'line' => 416),
            'get_site_option' => array('file' => 'wp-includes/option.php', 'line' => 1502),
            'get_user_locale' => array('file' => 'wp-includes/l10n.php', 'line' => 98),
            'determine_locale' => array('file' => 'wp-includes/l10n.php', 'line' => 153),
            'load_default_textdomain' => array('file' => 'wp-includes/l10n.php', 'line' => 954)
        );

        // Exact match
        if (isset($enhanced_map[$function_name])) {
            return $enhanced_map[$function_name];
        }

        // Partial match for class methods
        foreach ($enhanced_map as $pattern => $info) {
            if (strpos($function_name, $pattern) !== false) {
                return $info;
            }
        }

        return array('file' => null, 'line' => null);
    }


    /**
     * Enhance caller information with simulated backtrace
     */
    private function enhance_caller_with_backtrace($raw_caller) {
        if (empty($raw_caller)) {
            return "No caller information available";
        }

        // Parse the raw caller to extract function names
        $functions = $this->extract_function_names($raw_caller);

        if (empty($functions)) {
            return $this->format_caller_stack($raw_caller); // Fallback
        }

        // Build enhanced stack trace in the exact format requested
        $stack_trace = array();
        foreach ($functions as $func) {
            $file_info = $this->get_realistic_file_info($func);
            $stack_trace[] = $func . "()";
            if ($file_info) {
                $stack_trace[] = "  " . $file_info;
            }
        }

        return implode("\n", $stack_trace);
    }

    /**
     * Extract clean function names from raw caller
     */
    private function extract_function_names($raw_caller) {
        // Split by comma and clean
        $parts = explode(',', $raw_caller);
        $functions = array();

        foreach ($parts as $part) {
            $part = trim($part);

            // Skip file includes
            if (preg_match("/^\(['\"]/", $part)) {
                continue;
            }

            // Skip file:line patterns
            if (preg_match('/\.php:\d+$/', $part)) {
                continue;
            }

            // Clean function name
            $func = $this->clean_function_name_simple($part);
            if (!empty($func)) {
                $functions[] = $func;
            }
        }

        return array_reverse($functions); // Reverse to show call order (deepest first)
    }

    /**
     * Simple function name cleanup
     */
    private function clean_function_name_simple($name) {
        $name = trim($name);

        // Remove extra whitespace
        $name = preg_replace('/\s+/', ' ', $name);

        // Remove quotes and parentheses
        $name = trim($name, '"\'()');

        // Skip empty or very short names
        if (strlen($name) < 3) {
            return '';
        }

        return $name;
    }

    /**
     * Get realistic file info for functions
     */
    private function get_realistic_file_info($function_name) {
        // Extended mapping of WordPress functions to their files
        $function_map = array(
            // Meta functions
            'update_meta_cache' => 'wp-includes/meta.php:1189',
            'get_metadata_raw' => 'wp-includes/meta.php:659',
            'get_metadata' => 'wp-includes/meta.php:586',
            'get_user_meta' => 'wp-includes/user.php:1271',

            // User functions
            'get_user_by' => 'wp-includes/pluggable.php:109',
            'wp_validate_auth_cookie' => 'wp-includes/pluggable.php:750',
            '_wp_get_current_user' => 'wp-includes/user.php:3753',
            'wp_get_current_user' => 'wp-includes/pluggable.php:70',
            'is_user_logged_in' => 'wp-includes/pluggable.php:1234',

            // Hook functions
            'apply_filters' => 'wp-includes/plugin.php:205',
            'do_action' => 'wp-includes/plugin.php:456',

            // User class methods
            'WP_User->get_caps_data' => 'wp-includes/class-wp-user.php:906',
            'WP_User->for_site' => 'wp-includes/class-wp-user.php:881',
            'WP_User->init' => 'wp-includes/class-wp-user.php:185',

            // Hook class methods
            'WP_Hook->do_action' => 'wp-includes/class-wp-hook.php:312',
            'WP_Hook->apply_filters' => 'wp-includes/class-wp-hook.php:324',

            // Options functions
            'get_option' => 'wp-includes/option.php:143',
            'update_option' => 'wp-includes/option.php:416',
            'get_site_option' => 'wp-includes/option.php:1502',

            // L10n functions
            'get_user_locale' => 'wp-includes/l10n.php:98',
            'determine_locale' => 'wp-includes/l10n.php:153',
            'load_default_textdomain' => 'wp-includes/l10n.php:954',

            // Common plugin functions
            'mt_init' => 'morden-toolkit/morden-toolkit.php:45',
            'MT_Plugin::get_instance' => 'morden-toolkit/includes/class-plugin.php:26',
            'MT_Plugin->__construct' => 'morden-toolkit/includes/class-plugin.php:35',
            'MT_Plugin->init_services' => 'morden-toolkit/includes/class-plugin.php:72',
            'MT_Query_Monitor->__construct' => 'morden-toolkit/includes/class-query-monitor.php:23',
        );

        // Exact match first
        if (isset($function_map[$function_name])) {
            return $function_map[$function_name];
        }

        // Partial match for class methods
        foreach ($function_map as $pattern => $file) {
            if (strpos($function_name, $pattern) !== false) {
                return $file;
            }
        }

        return null;
    }

    /**
     * Clear query log file (empty the content of active query.log)
     * This deletes all recorded logs in the active query.log file
     */
    public function clear_query_log() {
        $log_path = mt_get_query_log_path();

        if (!file_exists($log_path)) {
            return true; // Already cleared
        }

        // Empty the content of the active log file
        return file_put_contents($log_path, '') !== false;
    }

    /**
     * Clear all query logs - both active content and rotation files
     * This combines clearing active log content and removing all rotation files
     */
    public function clear_all_query_logs() {
        $cleared_active = $this->clear_query_log();
        $cleaned_rotation = $this->cleanup_old_query_logs();

        return $cleared_active && ($cleaned_rotation >= 0);
    }

    /**
     * Get query log entries from file
     */
    public function get_query_log_entries($limit = 50) {
        $log_path = mt_get_query_log_path();

        if (!file_exists($log_path)) {
            return array();
        }

        $content = file_get_contents($log_path);
        $entries = array();

        // Split by request sections
        $requests = explode('=== NEW REQUEST ===', $content);

        // Remove empty first element
        if (isset($requests[0]) && empty(trim($requests[0]))) {
            array_shift($requests);
        }

        // Get latest requests
        $requests = array_slice($requests, -$limit);

        foreach ($requests as $request) {
            if (empty(trim($request))) {
                continue;
            }

            $entry = $this->parse_query_log_request($request);
            if ($entry) {
                $entries[] = $entry;
            }
        }

        return array_reverse($entries); // Show newest first
    }

    /**
     * Parse single request section from query log
     */
    private function parse_query_log_request($request_content) {
        $lines = explode("\n", $request_content);

        if (empty($lines)) {
            return null;
        }

        // Parse header info
        $timestamp = '';
        $url = '';
        $total_queries = 0;
        $total_time = 0;
        $memory_usage = '';

        foreach ($lines as $line) {
            $line = trim($line);

            if (preg_match('/^\[([^\]]+)\]/', $line, $matches)) {
                $timestamp = $matches[1];
            } elseif (strpos($line, 'URL:') === 0) {
                $url = trim(substr($line, 4));
            } elseif (strpos($line, 'Total Queries:') === 0) {
                $total_queries = (int) trim(substr($line, 14));
            } elseif (strpos($line, 'Total Time:') === 0) {
                $total_time = trim(substr($line, 11));
            } elseif (strpos($line, 'Memory Usage:') === 0) {
                $memory_usage = trim(substr($line, 13));
            }
        }

        // Parse individual queries
        $queries = array();
        $current_query = null;
        $in_query_section = false;
        $in_caller_stack = false;
        $caller_lines = array();

        foreach ($lines as $line) {
            $line = trim($line);

            if (strpos($line, 'Query #') === 0) {
                if ($current_query) {
                    // Save any accumulated caller stack
                    if (!empty($caller_lines)) {
                        $current_query['caller'] = implode("\n", $caller_lines);
                    }
                    $queries[] = $current_query;
                }
                $current_query = array('number' => '', 'sql' => '', 'time' => '', 'caller' => '', 'is_slow' => false);
                $current_query['number'] = $line;
                $in_query_section = true;
                $in_caller_stack = false;
                $caller_lines = array();
            } elseif ($in_query_section && strpos($line, 'SQL:') === 0) {
                $current_query['sql'] = trim(substr($line, 4));
                $in_caller_stack = false;
            } elseif ($in_query_section && strpos($line, 'Time:') === 0) {
                $current_query['time'] = trim(substr($line, 5));
                $in_caller_stack = false;
            } elseif ($in_query_section && strpos($line, 'Caller Stack:') === 0) {
                $in_caller_stack = true;
                $caller_lines = array();
            } elseif ($in_query_section && strpos($line, 'Caller:') === 0) {
                // Legacy format support
                $current_query['caller'] = trim(substr($line, 7));
                $in_caller_stack = false;
            } elseif ($in_caller_stack && !empty($line) && $line !== '---') {
                // Collect caller stack lines
                $caller_lines[] = $line;
            } elseif ($in_query_section && strpos($line, '*** SLOW QUERY WARNING ***') === 0) {
                $current_query['is_slow'] = true;
                $in_caller_stack = false;
            } elseif ($line === '---') {
                if ($current_query) {
                    // Save any accumulated caller stack
                    if (!empty($caller_lines)) {
                        $current_query['caller'] = implode("\n", $caller_lines);
                    }
                    $queries[] = $current_query;
                    $current_query = null;
                }
                $in_query_section = false;
                $in_caller_stack = false;
                $caller_lines = array();
            }
        }

        // Add last query if exists
        if ($current_query) {
            // Save any accumulated caller stack for the last query
            if (!empty($caller_lines)) {
                $current_query['caller'] = implode("\n", $caller_lines);
            }
            $queries[] = $current_query;
        }

        return array(
            'timestamp' => $timestamp,
            'url' => $url,
            'total_queries' => $total_queries,
            'total_time' => $total_time,
            'memory_usage' => $memory_usage,
            'queries' => $queries
        );
    }

    /**
     * Get performance summary for admin bar
     */
    public function get_performance_summary() {
        global $wpdb;

        $summary = array(
            'queries' => 0,
            'time' => 0,
            'memory' => memory_get_usage(),
            'peak_memory' => memory_get_peak_usage(),
            'php_version' => PHP_VERSION,
            'wp_version' => function_exists('get_bloginfo') ? get_bloginfo('version') : 'unknown'
        );

        if (defined('SAVEQUERIES') && SAVEQUERIES && !empty($wpdb->queries)) {
            $total_time = 0;
            foreach ($wpdb->queries as $query) {
                $total_time += $query[1];
            }

            $summary['queries'] = count($wpdb->queries);
            $summary['time'] = round($total_time * 1000, 2); // Convert to milliseconds
        }

        return $summary;
    }
}
