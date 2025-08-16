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
            'SCRIPT_DEBUG' => 'false'
        );

        foreach ($constants as $constant => $value) {
            $pattern = "/define\s*\(\s*['\"]" . $constant . "['\"]\s*,\s*[^)]+\s*\)\s*;/i";
            $replacement = "define('" . $constant . "', " . $value . ");";

            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, $replacement, $content);
            }
        }

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
        return array(
            'enabled' => get_option('mt_debug_enabled', false),
            'wp_debug' => defined('WP_DEBUG') && WP_DEBUG,
            'wp_debug_log' => defined('WP_DEBUG_LOG') && WP_DEBUG_LOG,
            'wp_debug_display' => defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY,
            'script_debug' => defined('SCRIPT_DEBUG') && SCRIPT_DEBUG,
            'log_file_exists' => file_exists(mt_get_debug_log_path()),
            'log_file_size' => file_exists(mt_get_debug_log_path()) ?
                mt_format_bytes(filesize(mt_get_debug_log_path())) : '0 B'
        );
    }
}
