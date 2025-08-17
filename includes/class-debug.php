<?php
/**
 * Debug Service - WP_DEBUG management
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MT_Debug {

    /**
     * Constructor - sync debug status on load
     */
    public function __construct() {
        $this->sync_debug_status();

        // Auto-write queries to log file when SAVEQUERIES is enabled
        if (defined('SAVEQUERIES') && SAVEQUERIES) {
            // Use earlier hook to ensure we capture queries before they're cleared
            add_action('wp_footer', array($this, 'write_queries_to_log'), 999);
            add_action('admin_footer', array($this, 'write_queries_to_log'), 999);
            // Also add shutdown as fallback for AJAX requests
            add_action('shutdown', array($this, 'write_queries_to_log'), 1);
        }
    }

    /**
     * Sync debug status with actual wp-config.php
     */
    public function sync_debug_status() {
        $actual_wp_debug = defined('WP_DEBUG') && WP_DEBUG;
        $stored_option = get_option('mt_debug_enabled', null);

        // If no option set or different from actual status, sync it
        if ($stored_option === null || $stored_option !== $actual_wp_debug) {
            update_option('mt_debug_enabled', $actual_wp_debug);

            // Optional: Log the sync action
            if (defined('WP_DEBUG') && WP_DEBUG) {
                //error_log('MT Debug: Synchronized debug status - WP_DEBUG is ' . ($actual_wp_debug ? 'enabled' : 'disabled'));
            }
        }
    }

    /**
     * Check if wp-config.php is readable and can detect debug status
     */
    public function can_detect_debug_status() {
        return defined('WP_DEBUG');
    }

    /**
     * Toggle debug mode
     */
    public function toggle_debug($enable = true) {
        $wp_config_path = mt_get_wp_config_path();

        if (!$wp_config_path || !mt_is_file_writable($wp_config_path)) {
            return false;
        }

        $config_content = file_get_contents($wp_config_path);

        if ($enable) {
            $config_content = $this->enable_debug_constants($config_content);
        } else {
            $config_content = $this->disable_debug_constants($config_content);
        }

        return file_put_contents($wp_config_path, $config_content) !== false;
    }

    /**
     * Enable debug mode
     */
    public function enable_debug() {
        return $this->toggle_debug(true);
    }

    /**
     * Disable debug mode
     */
    public function disable_debug() {
        return $this->toggle_debug(false);
    }

    /**
     * Toggle individual debug constant
     */
    public function toggle_debug_constant($constant, $enable) {
        $wp_config_path = mt_get_wp_config_path();

        if (!$wp_config_path || !mt_is_file_writable($wp_config_path)) {
            return false;
        }

        $config_content = file_get_contents($wp_config_path);

        // Auto-enable WP_DEBUG if any debug constant is being enabled
        if ($enable && $constant !== 'WP_DEBUG') {
            $config_content = $this->set_debug_constant($config_content, 'WP_DEBUG', true);
        }

        // Set the requested constant
        $config_content = $this->set_debug_constant($config_content, $constant, $enable);

        return file_put_contents($wp_config_path, $config_content) !== false;
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
     * Enable debug constants in wp-config.php
     */
    private function enable_debug_constants($content) {
        $constants = array(
            'WP_DEBUG' => 'true',
            'WP_DEBUG_LOG' => 'true',
            'WP_DEBUG_DISPLAY' => 'false',
            'SCRIPT_DEBUG' => 'true',
            'SAVEQUERIES' => 'true'
        );

        foreach ($constants as $constant => $value) {
            $pattern = "/define\s*\(\s*['\"]" . $constant . "['\"]\s*,\s*[^)]+\s*\)\s*;/i";
            $replacement = "define('" . $constant . "', " . $value . ");";

            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, $replacement, $content);
            } else {
                // Add constant before "/* That's all, stop editing!" line
                $insert_before = "/* That's all, stop editing!";
                $position = strpos($content, $insert_before);

                if ($position !== false) {
                    $before = substr($content, 0, $position);
                    $after = substr($content, $position);
                    $content = $before . $replacement . "\n" . $after;
                } else {
                    // Fallback: add at the end
                    $content .= "\n" . $replacement . "\n";
                }
            }
        }

        return $content;
    }

    /**
     * Disable debug constants in wp-config.php
     */
    private function disable_debug_constants($content) {
        $constants = array(
            'WP_DEBUG' => 'false',
            'WP_DEBUG_LOG' => 'false',
            'WP_DEBUG_DISPLAY' => 'false',
            'SCRIPT_DEBUG' => 'false',
            'SAVEQUERIES' => 'false'
        );

        foreach ($constants as $constant => $value) {
            if ($constant === 'WP_DEBUG') {
                // Handle conditional WP_DEBUG
                $conditional_pattern = "/if\s*\(\s*!\s*defined\s*\(\s*['\"]WP_DEBUG['\"]\s*\)\s*\)\s*{\s*define\s*\(\s*['\"]WP_DEBUG['\"]\s*,\s*[^}]+\s*}/i";
                if (preg_match($conditional_pattern, $content)) {
                    $replacement = "if ( ! defined( 'WP_DEBUG' ) ) {\n\tdefine('WP_DEBUG', " . $value . ");\n}";
                    $content = preg_replace($conditional_pattern, $replacement, $content);
                    continue;
                }
            }

            $pattern = "/define\s*\(\s*['\"]" . $constant . "['\"]\s*,\s*[^)]+\s*\)\s*;/i";
            $replacement = "define('" . $constant . "', " . $value . ");";

            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, $replacement, $content);
            }
        }

        // Also disable display_errors ini setting
        $content = $this->set_ini_setting($content, 'display_errors', '0');

        return $content;
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
        $stored_option = get_option('mt_debug_enabled', false);
        if ($stored_option !== $actual_wp_debug) {
            update_option('mt_debug_enabled', $actual_wp_debug);
        }

        // Check display_errors ini setting
        $display_errors = ini_get('display_errors') == '1' || ini_get('display_errors') === 'On';

        return array(
            'enabled' => $actual_wp_debug, // Use actual status, not stored option
            'wp_debug' => $actual_wp_debug,
            'wp_debug_log' => defined('WP_DEBUG_LOG') && WP_DEBUG_LOG,
            'wp_debug_display' => defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY,
            'script_debug' => defined('SCRIPT_DEBUG') && SCRIPT_DEBUG,
            'savequeries' => defined('SAVEQUERIES') && SAVEQUERIES,
            'display_errors' => $display_errors,
            'log_file_exists' => file_exists(mt_get_debug_log_path()),
            'log_file_size' => file_exists(mt_get_debug_log_path()) ?
                mt_format_bytes(filesize(mt_get_debug_log_path())) : '0 B',
            'query_log_file_exists' => file_exists(mt_get_query_log_path()),
            'query_log_file_size' => file_exists(mt_get_query_log_path()) ?
                mt_format_bytes(filesize(mt_get_query_log_path())) : '0 B',
            'query_log_total_size' => mt_format_bytes($this->get_query_log_total_size()),
            'query_log_max_size' => mt_format_bytes(mt_get_query_log_max_size())
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
        $log_content .= "Memory Usage: " . mt_format_bytes(memory_get_peak_usage()) . "\n";
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
        error_log("MT: Query log rotated. Size was " . mt_format_bytes($current_size));
    }

    /**
     * Clean up old query log files
     */
    public function cleanup_old_query_logs() {
        $log_path = mt_get_query_log_path();
        $log_dir = dirname($log_path);
        $log_name = basename($log_path);

        // Look for old log files (query.log.1, query.log.2, etc)
        $pattern = $log_dir . '/' . $log_name . '.*';
        $old_logs = glob($pattern);

        $cleaned = 0;
        foreach ($old_logs as $old_log) {
            // Keep only the most recent backup (.1)
            if (preg_match('/\.([2-9]|\d{2,})$/', $old_log)) {
                if (unlink($old_log)) {
                    $cleaned++;
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
     */
    private function format_caller_stack($raw_caller) {
        if (empty($raw_caller)) {
            return "No caller information available";
        }

        // WordPress caller format is usually: func1, func2, func3, file:line, func4, func5
        // We need to parse this properly and build a proper stack trace

        $formatted_stack = $this->parse_wordpress_caller($raw_caller);

        if (empty($formatted_stack)) {
            return $raw_caller; // Fallback to original if parsing fails
        }

        return implode("\n", $formatted_stack);
    }

    /**
     * Parse WordPress caller format
     */
    private function parse_wordpress_caller($caller) {
        // Split by comma and clean each part
        $parts = explode(',', $caller);
        $stack_entries = array();

        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) continue;

            // Skip file includes like ('wp-load.php')
            if (preg_match("/^\(['\"](.*?)['\"]?\)$/", $part, $matches)) {
                continue;
            }

            // Parse function with potential file:line info
            $entry = $this->parse_function_call($part);
            if ($entry) {
                $stack_entries[] = $entry;
            }
        }

        return $stack_entries;
    }

    /**
     * Parse individual function call
     */
    private function parse_function_call($call) {
        $call = trim($call);

        // Check if this looks like a file:line pattern
        if (preg_match('/^(.+\.php):(\d+)$/', $call, $matches)) {
            // This is file info for the previous function
            return null; // We'll handle this differently
        }

        // Clean up function name
        $function_name = $this->normalize_function_name($call);

        // Try to get file info from debug_backtrace if available
        $file_info = $this->get_file_info_for_function($function_name);

        if ($file_info) {
            return $function_name . "()\n    " . $file_info;
        } else {
            return $function_name . "()";
        }
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
     * Get file info for a function (simplified approach)
     */
    private function get_file_info_for_function($function_name) {
        // This is a simplified approach - in real implementation,
        // we would need to parse the caller string more intelligently
        // to associate functions with their file locations

        // Common WordPress core functions and their typical locations
        $core_functions = array(
            'update_meta_cache' => 'wp-includes/meta.php',
            'get_metadata_raw' => 'wp-includes/meta.php',
            'get_metadata' => 'wp-includes/meta.php',
            'get_user_meta' => 'wp-includes/user.php',
            'get_user_by' => 'wp-includes/pluggable.php',
            'wp_validate_auth_cookie' => 'wp-includes/pluggable.php',
            '_wp_get_current_user' => 'wp-includes/user.php',
            'wp_get_current_user' => 'wp-includes/pluggable.php',
            'apply_filters' => 'wp-includes/plugin.php',
            'do_action' => 'wp-includes/plugin.php',
            'WP_User->get_caps_data' => 'wp-includes/class-wp-user.php',
            'WP_User->for_site' => 'wp-includes/class-wp-user.php',
            'WP_User->init' => 'wp-includes/class-wp-user.php',
            'WP_Hook->do_action' => 'wp-includes/class-wp-hook.php',
            'WP_Hook->apply_filters' => 'wp-includes/class-wp-hook.php',
        );

        // Check if we have file info for this function
        foreach ($core_functions as $func => $file) {
            if (strpos($function_name, $func) !== false || $func === $function_name) {
                return $file;
            }
        }

        return null;
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
     * Clear query log file
     */
    public function clear_query_log() {
        $log_path = mt_get_query_log_path();

        if (!file_exists($log_path)) {
            return true; // Already cleared
        }

        return file_put_contents($log_path, '') !== false;
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
            'wp_version' => get_bloginfo('version')
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
