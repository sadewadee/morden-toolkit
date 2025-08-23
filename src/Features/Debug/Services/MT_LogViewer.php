<?php

namespace ModernToolkit\Features\Debug\Services;

class MT_LogViewer {
    private $logFile;

    public function __construct() {
        // Use the custom log path from our helper function
        $this->logFile = $this->getActiveDebugLogPath();
    }

    /**
     * Get the active debug log path
     * This could be either the WordPress default or our custom path
     */
    private function getActiveDebugLogPath(): string {
        // Check if WP_DEBUG_LOG is set to a custom path
        if (defined('WP_DEBUG_LOG') && is_string(WP_DEBUG_LOG) && WP_DEBUG_LOG !== 'true' && WP_DEBUG_LOG !== '1') {
            // If it's a relative path, make it absolute
            if (strpos(WP_DEBUG_LOG, '/') === 0) {
                return WP_DEBUG_LOG;
            } else {
                return ABSPATH . WP_DEBUG_LOG;
            }
        }

        // Check for logs in our morden-toolkit directory first
        $morden_log_dir = WP_CONTENT_DIR . '/morden-toolkit';
        if (is_dir($morden_log_dir)) {
            $log_files = glob($morden_log_dir . '/wp-errors-*.log');
            if (!empty($log_files)) {
                // Return the most recent log file
                usort($log_files, function($a, $b) {
                    return filemtime($b) - filemtime($a);
                });
                return $log_files[0];
            }
        }

        // Fallback to default WordPress debug.log
        return WP_CONTENT_DIR . '/debug.log';
    }

    public function getLog(): array {
        if (!file_exists($this->logFile)) {
            return [
                'success' => true,
                'logs' => [],
                'message' => \__('No debug log file found.', 'morden-toolkit')
            ];
        }

        try {
            $content = file_get_contents($this->logFile);
            $logs = $this->parseLogContent($content);

            return [
                'success' => true,
                'logs' => $logs,
                'file_size' => filesize($this->logFile)
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function clearLog(): array {
        try {
            if (file_exists($this->logFile)) {
                file_put_contents($this->logFile, '');
            }

            return [
                'success' => true,
                'message' => \__('Debug log cleared successfully.', 'morden-toolkit')
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function getLogSize(): int {
        return file_exists($this->logFile) ? filesize($this->logFile) : 0;
    }

    private function parseLogContent(string $content): array {
        $lines = explode("\n", trim($content));
        $logs = [];

        foreach (array_reverse($lines) as $line) {
            if (empty(trim($line))) {
                continue;
            }

            $log = $this->parseLogLine($line);
            if ($log) {
                $logs[] = $log;
            }

            if (count($logs) >= 100) {
                break;
            }
        }

        return $logs;
    }

    private function parseLogLine(string $line): ?array {
        $pattern = '/^\[(\d{2}-\w{3}-\d{4} \d{2}:\d{2}:\d{2} UTC)\] (.+)$/';

        if (preg_match($pattern, $line, $matches)) {
            return [
                'timestamp' => $matches[1],
                'message' => $matches[2],
                'level' => $this->determineLogLevel($matches[2])
            ];
        }

        return [
            'timestamp' => '',
            'message' => $line,
            'level' => 'info'
        ];
    }

    private function determineLogLevel(string $message): string {
        if (stripos($message, 'fatal') !== false) {
            return 'fatal';
        }
        if (stripos($message, 'error') !== false) {
            return 'error';
        }
        if (stripos($message, 'warning') !== false) {
            return 'warning';
        }
        if (stripos($message, 'notice') !== false) {
            return 'notice';
        }

        return 'info';
    }
}