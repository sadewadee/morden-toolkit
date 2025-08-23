<?php

namespace ModernToolkit;

if (!defined('ABSPATH')) {
    exit;
}

class SmtpLogger {

    private $log_file;
    private $log_enabled;
    private $log_dir;
    private $max_file_size;

    public function __construct() {
        $this->log_dir = mt_ensure_log_directory();
        $this->log_file = mt_get_smtp_log_path();
        $this->log_enabled = get_option('mt_smtp_logging_enabled', false);
        $this->max_file_size = 10 * 1024 * 1024; // 10MB
        
        $this->init_hooks();
    }

    private function init_hooks() {
        if (!$this->log_enabled) {
            return;
        }

        add_filter('wp_mail', array($this, 'log_email_attempt'), 10, 1);
        add_action('wp_mail_failed', array($this, 'log_email_failure'), 10, 1);
        if (function_exists('wp_mail_succeeded')) {
            add_action('wp_mail_succeeded', array($this, 'log_email_success'), 10, 1);
        }
    }



    public function log_email_attempt($args) {
        if (!$this->log_enabled) {
            return $args;
        }

        $to_email = is_array($args['to']) ? implode(', ', $args['to']) : $args['to'];
        $headers = is_array($args['headers']) ? implode("\n", $args['headers']) : $args['headers'];
        $attachments = is_array($args['attachments']) ? implode(', ', $args['attachments']) : $args['attachments'];
        
        $from_email = $this->extract_from_email($headers);
        
        $current_user = wp_get_current_user();
        $user_id = $current_user->ID ?: null;
        
        $server_ip = $this->get_server_ip();
        $email_source = $this->get_email_source();
        $mailer = $this->get_mailer_info();
        $log_id = uniqid('mail_', true);
        $this->current_log_id = $log_id;
        
        $log_entry = array(
            'id' => $log_id,
            'timestamp' => current_time('mysql'),
            'to_email' => $to_email,
            'from_email' => $from_email,
            'subject' => $args['subject'],
            'message' => $args['message'],
            'headers' => $headers,
            'attachments' => $attachments,
            'status' => 'queued',
            'error_message' => '',
            'server_ip' => $server_ip,
            'email_source' => $email_source,
            'mailer' => $mailer,
            'user_id' => $user_id,
            'sent_at' => '',
            // Enhanced diagnostic information
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown',
            'request_uri' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'Unknown',
            'is_admin' => is_admin(),
            'is_ajax' => wp_doing_ajax(),
            'is_cron' => wp_doing_cron(),
            'current_action' => current_action(),
            'site_url' => get_site_url()
        );
        
        $this->write_log_entry($log_entry);

        return $args;
    }

    public function log_email_failure($wp_error) {
        if (!$this->log_enabled || !isset($this->current_log_id)) {
            return;
        }

        $this->update_log_status($this->current_log_id, 'failed', $wp_error->get_error_message());
    }

    public function log_email_success($mail_data) {
        if (!$this->log_enabled || !isset($this->current_log_id)) {
            return;
        }

        $this->update_log_status($this->current_log_id, 'sent');
    }

    private function extract_from_email($headers) {
        if (empty($headers)) {
            return get_option('admin_email');
        }

        $headers_array = is_array($headers) ? $headers : explode("\n", $headers);
        
        foreach ($headers_array as $header) {
            if (stripos($header, 'From:') === 0) {
                preg_match('/From:.*?([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/', $header, $matches);
                if (!empty($matches[1])) {
                    return $matches[1];
                }
            }
        }

        return get_option('admin_email');
    }

    private function write_log_entry($log_entry) {
        $this->check_file_size();
        
        $log_line = json_encode($log_entry) . "\n";
        file_put_contents($this->log_file, $log_line, FILE_APPEND | LOCK_EX);
    }

    private function update_log_status($log_id, $status, $error_message = '') {
        if (!file_exists($this->log_file)) {
            return;
        }
        
        $lines = file($this->log_file, FILE_IGNORE_NEW_LINES);
        $updated = false;
        
        for ($i = 0; $i < count($lines); $i++) {
            $log_entry = json_decode($lines[$i], true);
            if ($log_entry && $log_entry['id'] === $log_id) {
                $log_entry['status'] = $status;
                $log_entry['sent_at'] = current_time('mysql');
                if ($error_message) {
                    $log_entry['error_message'] = $error_message;
                }
                $lines[$i] = json_encode($log_entry);
                $updated = true;
                break;
            }
        }
        
        if ($updated) {
            file_put_contents($this->log_file, implode("\n", $lines) . "\n", LOCK_EX);
        }
    }

    private function check_file_size() {
        if (!file_exists($this->log_file)) {
            return;
        }
        
        if (filesize($this->log_file) > $this->max_file_size) {
            $this->trim_log_file();
        }
    }

    private function trim_log_file() {
        $lines = file($this->log_file, FILE_IGNORE_NEW_LINES);
        $keep_lines = intval(count($lines) * 0.5);
        $trimmed_lines = array_slice($lines, -$keep_lines);
        file_put_contents($this->log_file, implode("\n", $trimmed_lines) . "\n", LOCK_EX);
    }

    private function get_server_ip() {
        $server_ip = '';
        
        if (!empty($_SERVER['SERVER_ADDR'])) {
            $server_ip = $_SERVER['SERVER_ADDR'];
        } elseif (!empty($_SERVER['LOCAL_ADDR'])) {
            $server_ip = $_SERVER['LOCAL_ADDR'];
        } else {
            $external_ip = wp_remote_get('https://api.ipify.org');
            if (!is_wp_error($external_ip)) {
                $server_ip = wp_remote_retrieve_body($external_ip);
            }
        }
        
        return $server_ip ?: 'unknown';
    }

    private function get_email_source() {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        
        foreach ($backtrace as $trace) {
            if (isset($trace['file'])) {
                $file = $trace['file'];
                
                if (strpos($file, WP_CONTENT_DIR . '/plugins/') !== false) {
                    $plugin_path = str_replace(WP_CONTENT_DIR . '/plugins/', '', $file);
                    $plugin_name = explode('/', $plugin_path)[0];
                    return 'Plugin: ' . $plugin_name;
                } elseif (strpos($file, WP_CONTENT_DIR . '/themes/') !== false) {
                    $theme_path = str_replace(WP_CONTENT_DIR . '/themes/', '', $file);
                    $theme_name = explode('/', $theme_path)[0];
                    return 'Theme: ' . $theme_name;
                } elseif (strpos($file, ABSPATH . 'wp-') !== false) {
                    return 'WordPress Core';
                }
            }
        }
        
        return 'Unknown';
    }

    private function get_mailer_info() {
        global $phpmailer;
        
        if (isset($phpmailer) && is_object($phpmailer)) {
            $mailer = $phpmailer->Mailer;
            
            switch ($mailer) {
                case 'smtp':
                    return 'SMTP: ' . $phpmailer->Host;
                case 'sendmail':
                    return 'Sendmail';
                case 'mail':
                default:
                    return 'PHP Mail';
            }
        }
        
        return 'PHP Mail';
    }

    public function get_logs($limit = 50, $offset = 0, $filters = array()) {
        if (!file_exists($this->log_file)) {
            return array();
        }
        
        $lines = file($this->log_file, FILE_IGNORE_NEW_LINES);
        $logs = array();
        
        foreach ($lines as $line) {
            $log_entry = json_decode($line, true);
            if ($log_entry) {
                if ($this->apply_filters($log_entry, $filters)) {
                    $logs[] = (object) $log_entry;
                }
            }
        }
        
        usort($logs, function($a, $b) {
            return strtotime($b->timestamp) - strtotime($a->timestamp);
        });
        return array_slice($logs, $offset, $limit);
    }

    private function apply_filters($log_entry, $filters) {
        if (!empty($filters['status']) && $log_entry['status'] !== $filters['status']) {
            return false;
        }
        if (!empty($filters['time_range'])) {
            $log_time = strtotime($log_entry['timestamp']);
            $current_time = current_time('timestamp');
            
            switch ($filters['time_range']) {
                case '1h':
                    if ($log_time < ($current_time - 3600)) return false;
                    break;
                case '24h':
                    if ($log_time < ($current_time - 86400)) return false;
                    break;
                case '7d':
                    if ($log_time < ($current_time - 604800)) return false;
                    break;
            }
        }
        
        if (!empty($filters['search'])) {
            $search_term = strtolower($filters['search']);
            $searchable_text = strtolower($log_entry['to_email'] . ' ' . $log_entry['subject'] . ' ' . $log_entry['message']);
            if (strpos($searchable_text, $search_term) === false) {
                return false;
            }
        }
        
        return true;
    }

    public function get_logs_count($filters = array()) {
        if (!file_exists($this->log_file)) {
            return 0;
        }
        
        $lines = file($this->log_file, FILE_IGNORE_NEW_LINES);
        $count = 0;
        
        foreach ($lines as $line) {
            $log_entry = json_decode($line, true);
            if ($log_entry && $this->apply_filters($log_entry, $filters)) {
                $count++;
            }
        }
        
        return $count;
    }

    public function clear_logs() {
        if (file_exists($this->log_file)) {
            return file_put_contents($this->log_file, '') !== false;
        }
        return true;
    }

    public function get_smtp_status() {
        // Get current logging status
        $smtp_logging_enabled = get_option('mt_smtp_logging_enabled', false);
        
        if (!file_exists($this->log_file)) {
            return array(
                'total' => 0,
                'sent' => 0,
                'failed' => 0,
                'recent_logs' => array(),
                'success_rate' => 0,
                'smtp_logging_enabled' => $smtp_logging_enabled,
                'log_file_exists' => false
            );
        }
        
        $lines = file($this->log_file, FILE_IGNORE_NEW_LINES);
        $total = 0;
        $sent = 0;
        $failed = 0;
        $recent_logs = array();
        
        // Get logs from last 24 hours for recent activity
        $last_24h_count = 0;
        $current_time = current_time('timestamp');
        $yesterday = $current_time - 86400;
        
        foreach ($lines as $line) {
            $log_entry = json_decode($line, true);
            if ($log_entry) {
                $total++;
                if ($log_entry['status'] === 'sent') {
                    $sent++;
                } elseif ($log_entry['status'] === 'failed') {
                    $failed++;
                }
                
                // Count logs from last 24 hours
                if (strtotime($log_entry['timestamp']) >= $yesterday) {
                    $last_24h_count++;
                }
                
                $recent_logs[] = (object) $log_entry;
            }
        }
        
        usort($recent_logs, function($a, $b) {
            return strtotime($b->timestamp) - strtotime($a->timestamp);
        });
        $recent_logs = array_slice($recent_logs, 0, 5);
        
        $success_rate = $total > 0 ? round(($sent / $total) * 100, 2) : 0;
        
        return array(
            'total' => $total,
            'sent' => $sent,
            'failed' => $failed,
            'recent_logs' => $recent_logs,
            'success_rate' => $success_rate,
            'smtp_logging_enabled' => $smtp_logging_enabled,
            'log_file_exists' => true,
            'last_24h_count' => $last_24h_count
        );
    }

    public function toggle_logging($enable = true) {
        $this->log_enabled = $enable;
        update_option('mt_smtp_logging_enabled', $enable);
        
        if ($enable) {
            $this->init_hooks();
        }
        
        return true;
    }

    public function export_logs_csv() {
        if (!file_exists($this->log_file)) {
            return false;
        }
        
        $lines = file($this->log_file, FILE_IGNORE_NEW_LINES);
        $logs = array();
        
        foreach ($lines as $line) {
            $log_entry = json_decode($line, true);
            if ($log_entry) {
                $logs[] = $log_entry;
            }
        }
        
        if (empty($logs)) {
            return false;
        }
        
        $filename = 'mail-logs-' . date('Y-m-d-H-i-s') . '.csv';
        $filepath = wp_upload_dir()['path'] . '/' . $filename;
        
        $file = fopen($filepath, 'w');
        
        fputcsv($file, array(
            'ID',
            'To Email',
            'From Email', 
            'Subject',
            'Message',
            'Headers',
            'Attachments',
            'Status',
            'Error Message',
            'Server IP',
            'Email Source',
            'Mailer',
            'User ID',
            'Timestamp',
            'Sent At'
        ));
        
        foreach ($logs as $log) {
            fputcsv($file, array(
                $log['id'],
                $log['to_email'],
                $log['from_email'],
                $log['subject'],
                $log['message'],
                is_array($log['headers']) ? json_encode($log['headers']) : $log['headers'],
                is_array($log['attachments']) ? json_encode($log['attachments']) : $log['attachments'],
                $log['status'],
                $log['error_message'],
                $log['server_ip'],
                $log['email_source'],
                $log['mailer'],
                $log['user_id'],
                $log['timestamp'],
                $log['sent_at']
            ));
        }
        
        fclose($file);
        
        return array(
            'filename' => $filename,
            'filepath' => $filepath,
            'url' => wp_upload_dir()['url'] . '/' . $filename
        );
    }
}