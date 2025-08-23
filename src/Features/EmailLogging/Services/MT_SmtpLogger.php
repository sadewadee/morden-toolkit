<?php

namespace ModernToolkit\Features\EmailLogging\Services;

class MT_SmtpLogger {

    private $log_file;
    private $max_log_size;

    public function __construct() {
        $this->log_file = \mt_get_smtp_log_path();
        $this->max_log_size = 10 * 1024 * 1024; // 10MB
    }

    /**
     * Log successful email
     */
    public function log_email($mail_data): void {
        try {
            $log_entry = [
                'id' => $this->generateLogId(),
                'timestamp' => \current_time('mysql'),
                'to_email' => $this->formatEmailAddresses($mail_data['to'] ?? ''),
                'from_email' => $this->extractFromEmail($mail_data),
                'subject' => $mail_data['subject'] ?? '',
                'status' => 'sent',
                'error_message' => '',
                'server_ip' => $_SERVER['SERVER_ADDR'] ?? '',
                'email_source' => $this->detectEmailSource(),
                'headers' => $this->formatHeaders($mail_data['headers'] ?? []),
                'attachments' => count($mail_data['attachments'] ?? [])
            ];

            $this->writeLogEntry($log_entry);
        } catch (\Exception $e) {
            \error_log('MT SMTP Logger Error: ' . $e->getMessage());
        }
    }

    /**
     * Log failed email
     */
    public function log_email_failure($wp_error): void {
        try {
            if (!\is_wp_error($wp_error)) {
                return;
            }

            $log_entry = [
                'id' => $this->generateLogId(),
                'timestamp' => \current_time('mysql'),
                'to_email' => '',
                'from_email' => '',
                'subject' => '',
                'status' => 'failed',
                'error_message' => $wp_error->get_error_message(),
                'server_ip' => $_SERVER['SERVER_ADDR'] ?? '',
                'email_source' => $this->detectEmailSource(),
                'headers' => '',
                'attachments' => 0
            ];

            $this->writeLogEntry($log_entry);
        } catch (\Exception $e) {
            \error_log('MT SMTP Logger Error: ' . $e->getMessage());
        }
    }

    /**
     * Get logs with pagination and filtering
     */
    public function get_logs(int $limit = 20, int $offset = 0, array $filters = []): array {
        try {
            if (!file_exists($this->log_file)) {
                return [];
            }

            $lines = file($this->log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $logs = [];

            foreach (array_reverse($lines) as $line) {
                $log_entry = json_decode($line, true);
                if ($log_entry && $this->matchesFilters($log_entry, $filters)) {
                    $logs[] = $log_entry;
                }
            }

            return array_slice($logs, $offset, $limit);
        } catch (\Exception $e) {
            \error_log('MT SMTP Get Logs Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get total logs count
     */
    public function get_logs_count(array $filters = []): int {
        try {
            if (!file_exists($this->log_file)) {
                return 0;
            }

            $lines = file($this->log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $count = 0;

            foreach ($lines as $line) {
                $log_entry = json_decode($line, true);
                if ($log_entry && $this->matchesFilters($log_entry, $filters)) {
                    $count++;
                }
            }

            return $count;
        } catch (\Exception $e) {
            \error_log('MT SMTP Get Logs Count Error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Clear all logs
     */
    public function clear_logs(): bool {
        try {
            if (file_exists($this->log_file)) {
                return file_put_contents($this->log_file, '') !== false;
            }
            return true;
        } catch (\Exception $e) {
            \error_log('MT SMTP Clear Logs Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Export logs to CSV format
     */
    public function export_logs_csv(array $filters = []): array {
        try {
            $logs = $this->get_logs(1000, 0, $filters); // Get up to 1000 logs
            $csv_data = [];

            // CSV header
            $csv_data[] = [
                'ID', 'Timestamp', 'To', 'From', 'Subject', 'Status', 'Error', 'Server IP', 'Source', 'Attachments'
            ];

            foreach ($logs as $log) {
                $csv_data[] = [
                    $log['id'] ?? '',
                    $log['timestamp'] ?? '',
                    $log['to_email'] ?? '',
                    $log['from_email'] ?? '',
                    $log['subject'] ?? '',
                    $log['status'] ?? '',
                    $log['error_message'] ?? '',
                    $log['server_ip'] ?? '',
                    $log['email_source'] ?? '',
                    $log['attachments'] ?? 0
                ];
            }

            return $csv_data;
        } catch (\Exception $e) {
            \error_log('MT SMTP Export Logs Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get SMTP status and statistics
     */
    public function get_smtp_status(): array {
        try {
            $total_logs = $this->get_logs_count();
            $sent_logs = $this->get_logs_count(['status' => 'sent']);
            $failed_logs = $this->get_logs_count(['status' => 'failed']);

            $success_rate = $total_logs > 0 ? round(($sent_logs / $total_logs) * 100, 1) : 0;

            // Get last 24h count
            $last_24h = date('Y-m-d H:i:s', strtotime('-24 hours'));
            $last_24h_count = $this->getLogsCountSince($last_24h);

            return [
                'enabled' => \get_option('mt_smtp_logging_enabled', false),
                'total_logs' => $total_logs,
                'sent_logs' => $sent_logs,
                'failed_logs' => $failed_logs,
                'success_rate' => $success_rate,
                'last_24h_count' => $last_24h_count,
                'log_file_size' => file_exists($this->log_file) ? \mt_format_bytes(filesize($this->log_file)) : '0 B'
            ];
        } catch (\Exception $e) {
            \error_log('MT SMTP Get Status Error: ' . $e->getMessage());
            return [
                'enabled' => false,
                'total_logs' => 0,
                'sent_logs' => 0,
                'failed_logs' => 0,
                'success_rate' => 0,
                'last_24h_count' => 0,
                'log_file_size' => '0 B'
            ];
        }
    }

    /**
     * Private helper methods
     */
    private function generateLogId(): string {
        return uniqid('smtp_', true);
    }

    private function formatEmailAddresses($addresses): string {
        if (is_array($addresses)) {
            return implode(', ', $addresses);
        }
        return (string) $addresses;
    }

    private function extractFromEmail($mail_data): string {
        $headers = $mail_data['headers'] ?? [];

        if (is_array($headers)) {
            foreach ($headers as $header) {
                if (strpos(strtolower($header), 'from:') === 0) {
                    return trim(substr($header, 5));
                }
            }
        }

        return \get_option('admin_email', '');
    }

    private function detectEmailSource(): string {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);

        foreach ($backtrace as $trace) {
            if (isset($trace['function']) && $trace['function'] === 'wp_mail') {
                continue;
            }

            if (isset($trace['file'])) {
                $file = basename($trace['file']);
                if (strpos($file, 'wp-') === 0) {
                    return 'WordPress Core';
                } elseif (strpos($trace['file'], '/plugins/') !== false) {
                    $plugin_path = explode('/plugins/', $trace['file'])[1];
                    $plugin_name = explode('/', $plugin_path)[0];
                    return 'Plugin: ' . $plugin_name;
                } elseif (strpos($trace['file'], '/themes/') !== false) {
                    $theme_path = explode('/themes/', $trace['file'])[1];
                    $theme_name = explode('/', $theme_path)[0];
                    return 'Theme: ' . $theme_name;
                }
            }
        }

        return 'Unknown';
    }

    private function formatHeaders($headers): string {
        if (is_array($headers)) {
            return implode("\n", $headers);
        }
        return (string) $headers;
    }

    private function writeLogEntry(array $log_entry): void {
        // Rotate log if it's too large
        if (file_exists($this->log_file) && filesize($this->log_file) > $this->max_log_size) {
            $this->rotateLogs();
        }

        $json_line = json_encode($log_entry) . "\n";
        file_put_contents($this->log_file, $json_line, FILE_APPEND | LOCK_EX);
    }

    private function rotateLogs(): void {
        if (!file_exists($this->log_file)) {
            return;
        }

        $backup_file = $this->log_file . '.old';
        if (file_exists($backup_file)) {
            unlink($backup_file);
        }

        rename($this->log_file, $backup_file);
    }

    private function matchesFilters(array $log_entry, array $filters): bool {
        if (empty($filters)) {
            return true;
        }

        foreach ($filters as $field => $value) {
            if (empty($value)) {
                continue;
            }

            if (!isset($log_entry[$field])) {
                return false;
            }

            if (stripos($log_entry[$field], $value) === false) {
                return false;
            }
        }

        return true;
    }

    private function getLogsCountSince(string $since_timestamp): int {
        try {
            if (!file_exists($this->log_file)) {
                return 0;
            }

            $lines = file($this->log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $count = 0;

            foreach ($lines as $line) {
                $log_entry = json_decode($line, true);
                if ($log_entry && isset($log_entry['timestamp'])) {
                    if (strtotime($log_entry['timestamp']) >= strtotime($since_timestamp)) {
                        $count++;
                    }
                }
            }

            return $count;
        } catch (\Exception $e) {
            return 0;
        }
    }
}