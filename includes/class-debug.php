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
            'SCRIPT_DEBUG' => 'false'
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
                mt_format_bytes(filesize(mt_get_debug_log_path())) : '0 B'
        );
    }
}
