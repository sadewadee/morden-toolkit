<?php

namespace ModernToolkit\Features\Debug\Services;

use ModernToolkit\Infrastructure\WordPress\MT_WpConfigIntegration;

class MT_DebugManager {
    private $wpConfigPath;

    public function __construct() {
        $this->wpConfigPath = ABSPATH . 'wp-config.php';
    }

    public function enableDebug(): array {
        try {
            $this->setDebugConstants(true);
            \update_option('mt_debug_enabled', true);

            return [
                'success' => true,
                'message' => \__('Debug mode enabled successfully. Log files will be saved to wp-content/morden-toolkit/', 'morden-toolkit')
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function disableDebug(): array {
        try {
            $this->setDebugConstants(false);
            \update_option('mt_debug_enabled', false);

            return [
                'success' => true,
                'message' => \__('Debug mode disabled successfully.', 'morden-toolkit')
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function isDebugEnabled(): bool {
        return (bool) \get_option('mt_debug_enabled', false);
    }

    /**
     * Get comprehensive debug status information
     * Compatible with legacy admin views
     */
    public function get_debug_status(): array {
        $actual_wp_debug = defined('WP_DEBUG') && WP_DEBUG;

        // Sync stored option with actual status
        if (function_exists('get_option') && function_exists('update_option')) {
            $stored_option = \get_option('mt_debug_enabled', false);
            if ($stored_option !== $actual_wp_debug) {
                \update_option('mt_debug_enabled', $actual_wp_debug);
            }
        }

        $display_errors = ini_get('display_errors') == '1' || ini_get('display_errors') === 'On';

        return [
            'enabled' => $actual_wp_debug, // Use actual status, not stored option
            'wp_debug' => $actual_wp_debug,
            'wp_debug_log' => defined('WP_DEBUG_LOG') && WP_DEBUG_LOG,
            'wp_debug_display' => defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY,
            'script_debug' => defined('SCRIPT_DEBUG') && SCRIPT_DEBUG,
            'savequeries' => defined('SAVEQUERIES') && SAVEQUERIES,
            'display_errors' => $display_errors,
            'log_file_exists' => file_exists(\mt_get_debug_log_path()),
            'log_file_size' => file_exists(\mt_get_debug_log_path()) ?
                \mt_format_bytes(filesize(\mt_get_debug_log_path())) : '0 B',
            'query_log_file_exists' => file_exists(\mt_get_query_log_path()),
            'query_log_file_size' => file_exists(\mt_get_query_log_path()) ?
                \mt_format_bytes(filesize(\mt_get_query_log_path())) : '0 B',
            'query_log_total_size' => \mt_format_bytes($this->get_query_log_total_size()),
            'query_log_max_size' => \mt_format_bytes(\mt_get_query_log_max_size())
        ];
    }

    /**
     * Calculate total size of all query log files including backups
     */
    private function get_query_log_total_size(): int {
        $log_path = \mt_get_query_log_path();
        $total_size = 0;

        // Get main log file size
        if (file_exists($log_path)) {
            $total_size += filesize($log_path);
        }

        // Get backup files size
        $backup_pattern = dirname($log_path) . '/query-*.log';
        $backup_files = glob($backup_pattern);

        if ($backup_files) {
            foreach ($backup_files as $backup_file) {
                if (file_exists($backup_file)) {
                    $total_size += filesize($backup_file);
                }
            }
        }

        return $total_size;
    }

    private function setDebugConstants(bool $enabled): void {
        $constants = [
            'WP_DEBUG' => $enabled,
            'WP_DEBUG_LOG' => $enabled,  // This will be converted to custom path by MT_WpConfigIntegration
            'WP_DEBUG_DISPLAY' => false,
            'SCRIPT_DEBUG' => $enabled,
            'SAVEQUERIES' => $enabled
        ];

        // Use the enhanced WpConfigIntegration to apply debug constants
        $result = MT_WpConfigIntegration::apply_debug_constants($constants);

        if (!$result) {
            throw new \Exception('Failed to apply debug constants to wp-config.php');
        }
    }

    /**
     * Legacy compatibility method for toggle_debug
     */
    public function toggle_debug(bool $enabled): bool {
        try {
            if ($enabled) {
                $result = $this->enableDebug();
            } else {
                $result = $this->disableDebug();
            }
            return $result['success'];
        } catch (\Exception $e) {
            \error_log('MT Debug Toggle Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Legacy compatibility method for toggle_debug_constant
     */
    public function toggle_debug_constant(string $constant, bool $enabled): bool {
        try {
            switch ($constant) {
                case 'WP_DEBUG_LOG':
                    return MT_WpConfigIntegration::apply_debug_constants(['WP_DEBUG_LOG' => $enabled]);
                case 'WP_DEBUG_DISPLAY':
                    return MT_WpConfigIntegration::apply_debug_constants(['WP_DEBUG_DISPLAY' => $enabled]);
                case 'SCRIPT_DEBUG':
                    return MT_WpConfigIntegration::apply_debug_constants(['SCRIPT_DEBUG' => $enabled]);
                case 'SAVEQUERIES':
                    return MT_WpConfigIntegration::apply_debug_constants(['SAVEQUERIES' => $enabled]);
                case 'display_errors':
                    return ini_set('display_errors', $enabled ? '1' : '0') !== false;
                default:
                    return false;
            }
        } catch (\Exception $e) {
            \error_log('MT Debug Constant Toggle Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Legacy compatibility method for clear_debug_log
     */
    public function clear_debug_log(): bool {
        try {
            $log_path = \mt_get_debug_log_path();
            if (file_exists($log_path)) {
                return file_put_contents($log_path, '') !== false;
            }
            return true;
        } catch (\Exception $e) {
            \error_log('MT Clear Debug Log Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Legacy compatibility method for get_debug_log_entries
     */
    public function get_debug_log_entries(int $limit = 100): array {
        try {
            $log_path = \mt_get_debug_log_path();
            if (!file_exists($log_path)) {
                return [];
            }

            $content = file_get_contents($log_path);
            if ($content === false) {
                return [];
            }

            $lines = explode("\n", trim($content));
            $lines = array_filter($lines); // Remove empty lines
            $lines = array_slice($lines, -$limit); // Get last N lines

            $entries = [];
            foreach ($lines as $line) {
                if (!empty($line)) {
                    $entries[] = [
                        'timestamp' => $this->extractTimestamp($line),
                        'level' => $this->extractLogLevel($line),
                        'message' => $line
                    ];
                }
            }

            return array_reverse($entries); // Most recent first
        } catch (\Exception $e) {
            \error_log('MT Get Debug Log Entries Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Legacy compatibility method for get_query_log_entries
     */
    public function get_query_log_entries(int $limit = 100): array {
        try {
            $log_path = \mt_get_query_log_path();
            if (!file_exists($log_path)) {
                return [];
            }

            $content = file_get_contents($log_path);
            if ($content === false) {
                return [];
            }

            $lines = explode("\n", trim($content));
            $lines = array_filter($lines); // Remove empty lines
            $lines = array_slice($lines, -$limit); // Get last N lines

            $entries = [];
            foreach ($lines as $line) {
                if (!empty($line)) {
                    // Parse query log line format
                    $parsed = $this->parseQueryLogLine($line);
                    if ($parsed) {
                        $entries[] = $parsed;
                    }
                }
            }

            return array_reverse($entries); // Most recent first
        } catch (\Exception $e) {
            \error_log('MT Get Query Log Entries Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Legacy compatibility method for cleanup_old_query_logs
     */
    public function cleanup_old_query_logs(): array {
        try {
            $log_dir = dirname(\mt_get_query_log_path());
            $backup_pattern = $log_dir . '/query-*.log';
            $backup_files = glob($backup_pattern);

            $cleaned_files = [];
            $cleanup_time = time() - (7 * 24 * 60 * 60); // 7 days ago

            if ($backup_files) {
                foreach ($backup_files as $backup_file) {
                    if (file_exists($backup_file) && filemtime($backup_file) < $cleanup_time) {
                        if (unlink($backup_file)) {
                            $cleaned_files[] = basename($backup_file);
                        }
                    }
                }
            }

            return [
                'success' => true,
                'cleaned_files' => $cleaned_files,
                'count' => count($cleaned_files)
            ];
        } catch (\Exception $e) {
            \error_log('MT Cleanup Query Logs Error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'cleaned_files' => [],
                'count' => 0
            ];
        }
    }

    /**
     * Parse a query log line into structured data
     */
    private function parseQueryLogLine(string $line): array|false {
        // Try to parse common query log formats
        if (preg_match('/^\[(.*?)\]\s*(.*?)\s*-\s*(.*)$/', $line, $matches)) {
            return [
                'timestamp' => $matches[1],
                'query_type' => trim($matches[2]),
                'query' => trim($matches[3]),
                'raw_line' => $line
            ];
        }

        // Fallback format
        return [
            'timestamp' => $this->extractTimestamp($line),
            'query_type' => 'unknown',
            'query' => $line,
            'raw_line' => $line
        ];
    }

    /**
     * Legacy compatibility method for clear_query_log
     */
    public function clear_query_log(): bool {
        try {
            $log_path = \mt_get_query_log_path();
            if (file_exists($log_path)) {
                return file_put_contents($log_path, '') !== false;
            }
            return true;
        } catch (\Exception $e) {
            \error_log('MT Clear Query Log Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Extract timestamp from log line
     */
    private function extractTimestamp(string $line): string {
        // Try to extract timestamp from common log formats
        if (preg_match('/\[(\d{2}-\w{3}-\d{4} \d{2}:\d{2}:\d{2} \w+)\]/', $line, $matches)) {
            return $matches[1];
        }
        if (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $line, $matches)) {
            return $matches[1];
        }
        return date('Y-m-d H:i:s');
    }

    /**
     * Extract log level from log line
     */
    private function extractLogLevel(string $line): string {
        if (stripos($line, 'fatal') !== false) return 'fatal';
        if (stripos($line, 'error') !== false) return 'error';
        if (stripos($line, 'warning') !== false) return 'warning';
        if (stripos($line, 'notice') !== false) return 'notice';
        if (stripos($line, 'deprecated') !== false) return 'deprecated';
        return 'info';
    }
}