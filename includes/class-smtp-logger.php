<?php
/**
 * SMTP Logger - Email logging with JSON Lines format
 * Enhanced to properly hook into wp_mail() and capture comprehensive email data
 *
 * @package Morden Toolkit
 * @author Morden Team
 * @license GPL v3 or later
 * @link https://github.com/sadewadee/morden-toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class MT_SMTP_Logger {
    private $log_directory;
    private $log_enabled;
    private $current_log_file;
    private $log_ip_address;
    private $pending_emails; // Store emails being processed
    private $email_counter; // Counter for unique IDs
    private $current_phpmailer; // Current PHPMailer instance
    private $original_action; // Original PHPMailer action function
    private $current_smtp_config; // Current SMTP configuration

    public function __construct() {
        $this->log_directory = \ABSPATH . 'wp-content/morden-toolkit/';
        $this->log_enabled = \get_option('mt_smtp_logging_enabled', false);
        $this->log_ip_address = \get_option('mt_smtp_log_ip_address', false);
        $this->current_log_file = $this->get_current_log_file();
        $this->pending_emails = array();
        $this->email_counter = 0;

        $this->init_hooks();
        $this->ensure_log_directory();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        if (!$this->log_enabled) {
            return;
        }

        // Hook before wp_mail() processing to capture the original data
        \add_filter('pre_wp_mail', array($this, 'capture_email_data'), 10, 2);

        // Hook after wp_mail() to determine success (WP 5.5+)
        if (function_exists('wp_mail_succeeded')) {
            \add_action('wp_mail_succeeded', array($this, 'log_email_success'), 10, 1);
        }

        // Hook after wp_mail() to determine failure
        \add_action('wp_mail_failed', array($this, 'log_email_failure'), 10, 1);

        // Hook into PHPMailer for more detailed tracking
        \add_action('phpmailer_init', array($this, 'setup_phpmailer_hooks'), 10, 1);
    }

    /**
     * Get current log file path with daily rotation
     */
    private function get_current_log_file() {
        $date_suffix = date('dmY'); // ddmmyyyy format
        return $this->log_directory . 'smtp-' . $date_suffix . '.log';
    }

    /**
     * Ensure log directory exists
     */
    private function ensure_log_directory() {
        if (!is_dir($this->log_directory)) {
            if (function_exists('wp_mkdir_p')) {
                \wp_mkdir_p($this->log_directory);
            } else {
                mkdir($this->log_directory, 0755, true);
            }
        }
    }

    /**
     * Capture email data before wp_mail processing
     * This hook allows us to capture the original email data and return null to continue normal processing
     */
    public function capture_email_data($null, $atts) {
        if (!$this->log_enabled) {
            return $null; // Continue with normal wp_mail processing
        }

        // Generate unique ID for this email
        $email_id = 'email_' . time() . '_' . (++$this->email_counter);

        // Extract email data from wp_mail arguments
        $mail_data = array(
            'to' => $atts['to'],
            'subject' => $atts['subject'],
            'message' => $atts['message'],
            'headers' => isset($atts['headers']) ? $atts['headers'] : array(),
            'attachments' => isset($atts['attachments']) ? $atts['attachments'] : array()
        );

        // Generate email identifier for precise matching
        $email_identifier = $this->generate_email_identifier($mail_data);

        // Create initial log entry
        $log_entry = $this->create_log_entry($mail_data, 'queued', $email_id);
        $log_entry['email_identifier'] = $email_identifier;
        $this->write_log_entry($log_entry);

        // Store for later reference
        $this->pending_emails[$email_id] = $log_entry;

        return $null; // Continue with normal wp_mail processing
    }

    /**
     * Log email failure
     */
    public function log_email_failure($wp_error) {
        if (!$this->log_enabled) {
            return;
        }

        // Try to find the corresponding pending email
        $email_id = $this->find_pending_email_id();

        if ($email_id && isset($this->pending_emails[$email_id])) {
            // Update existing log entry
            $log_entry = $this->pending_emails[$email_id];
            $log_entry['status'] = 'failed';
            $log_entry['error_message'] = $wp_error->get_error_message();
            $log_entry['last_reply'] = $wp_error->get_error_message();
            $log_entry['smtp_reply'] = $wp_error->get_error_code();
            $log_entry['failed_at'] = time();

            $this->write_log_entry($log_entry);
            unset($this->pending_emails[$email_id]);
        } else {
            // Create new log entry for failed email (fallback)
            $mail_data = array(
                'to' => array('unknown'),
                'subject' => 'Email Failed - No Data Captured',
                'message' => '',
                'headers' => array(),
                'attachments' => array()
            );

            $log_entry = $this->create_log_entry($mail_data, 'failed');
            $log_entry['error_message'] = $wp_error->get_error_message();
            $log_entry['last_reply'] = $wp_error->get_error_message();
            $log_entry['smtp_reply'] = $wp_error->get_error_code();

            $this->write_log_entry($log_entry);
        }
    }

    /**
     * Log email success using wp_mail_succeeded action (WP 5.5+)
     */
    public function log_email_success($mail_data) {
        if (!$this->log_enabled) {
            return;
        }

        // Generate email identifier to match with pending emails
        $email_id = $this->generate_email_identifier($mail_data);

        // Find and update the corresponding pending email
        $found = false;
        foreach ($this->pending_emails as $pending_id => $log_entry) {
            if ($this->matches_pending_email($log_entry, $mail_data, $email_id)) {
                $log_entry['status'] = 'sent';
                $log_entry['sent_at'] = time();
                $log_entry['smtp_reply'] = '250'; // Success code
                $log_entry['delivery_confirmed'] = true;
                $log_entry['delivery_method'] = 'wp_mail_succeeded';

                $this->write_log_entry($log_entry);
                unset($this->pending_emails[$pending_id]);
                $found = true;
                break;
            }
        }

        // If no pending email found, create a new success entry
        if (!$found) {
            $log_entry = $this->create_log_entry($mail_data, 'sent');
            $log_entry['delivery_confirmed'] = true;
            $log_entry['delivery_method'] = 'wp_mail_succeeded';
            $log_entry['note'] = 'Success logged without initial capture';
            $this->write_log_entry($log_entry);
        }
    }

    /**
     * Setup PHPMailer hooks for detailed tracking
     */
    public function setup_phpmailer_hooks($phpmailer) {
        if (!$this->log_enabled) {
            return;
        }

        // Store original action function
        $original_action = $phpmailer->action_function;

        // Set custom action function to track post-send events
        $phpmailer->action_function = array($this, 'phpmailer_post_send_action');

        // Store reference to phpmailer and original action for later use
        $this->current_phpmailer = $phpmailer;
        $this->original_action = $original_action;

        // Store SMTP configuration for better server detection
        $this->current_smtp_config = array(
            'host' => $phpmailer->Host,
            'port' => $phpmailer->Port,
            'secure' => $phpmailer->SMTPSecure,
            'auth' => $phpmailer->SMTPAuth,
            'username' => $phpmailer->Username,
            'mailer' => $phpmailer->Mailer // smtp, mail, sendmail, qmail
        );
    }

    /**
     * PHPMailer post-send action to confirm actual delivery
     */
    public function phpmailer_post_send_action($to, $cc, $bcc, $subject, $body, $from, $sent) {
        if (!$this->log_enabled) {
            return;
        }

        // Call original action if it exists
        if ($this->original_action && is_callable($this->original_action)) {
            call_user_func($this->original_action, $to, $cc, $bcc, $subject, $body, $from, $sent);
        }

        // Generate email identifier
        $mail_data = array(
            'to' => $to,
            'subject' => $subject,
            'message' => $body
        );
        $email_id = $this->generate_email_identifier($mail_data);

        // Update pending email with actual send result
        foreach ($this->pending_emails as $pending_id => $log_entry) {
            if ($this->matches_pending_email($log_entry, $mail_data, $email_id)) {
                if ($sent) {
                    $log_entry['status'] = 'sent';
                    $log_entry['sent_at'] = time();
                    $log_entry['smtp_reply'] = '250';
                    $log_entry['delivery_confirmed'] = true;
                    $log_entry['delivery_method'] = 'phpmailer_confirmed';
                } else {
                    $log_entry['status'] = 'failed';
                    $log_entry['failed_at'] = time();
                    $log_entry['error_message'] = 'PHPMailer send failed';
                    $log_entry['smtp_reply'] = '550';
                }

                // Add SMTP configuration info
                if (!empty($this->current_smtp_config)) {
                    $log_entry['smtp_config'] = $this->current_smtp_config;
                }

                $this->write_log_entry($log_entry);
                unset($this->pending_emails[$pending_id]);
                break;
            }
        }
    }

    /**
     * Find pending email ID (simple implementation - DEPRECATED)
     * This method is unreliable for concurrent emails
     */
    private function find_pending_email_id() {
        // For now, return the latest pending email ID
        // In a more sophisticated implementation, we could match by email content
        return !empty($this->pending_emails) ? array_key_last($this->pending_emails) : null;
    }

    /**
     * Generate precise email identifier for matching
     * Uses Message-ID or creates hash from subject+to+timestamp
     */
    private function generate_email_identifier($mail_data) {
        // First try to use Message-ID if available
        if (isset($mail_data['headers'])) {
            $headers = $this->parse_headers($mail_data['headers']);
            if (isset($headers['Message-ID'])) {
                return $headers['Message-ID'];
            }
        }

        // Fallback: create hash from key email components
        $to = is_array($mail_data['to']) ? implode(',', $mail_data['to']) : $mail_data['to'];
        $subject = $mail_data['subject'] ?? '';
        $timestamp = time();

        // Create a more precise hash including microseconds to avoid collisions
        $hash_input = $to . '|' . $subject . '|' . $timestamp . '|' . microtime(true);
        return 'hash_' . md5($hash_input);
    }

    /**
     * Check if pending email matches current email data
     */
    private function matches_pending_email($log_entry, $mail_data, $email_id) {
        // First check by email identifier
        if (isset($log_entry['email_identifier']) && $log_entry['email_identifier'] === $email_id) {
            return true;
        }

        // Fallback: match by subject and primary recipient
        $log_subject = $log_entry['email_subject'] ?? $log_entry['subject'] ?? '';
        $current_subject = $mail_data['subject'] ?? '';

        if ($log_subject !== $current_subject) {
            return false;
        }

        // Check primary recipient
        $log_to = is_array($log_entry['to']) ? $log_entry['to'][0] : $log_entry['to'];
        $current_to = is_array($mail_data['to']) ? $mail_data['to'][0] : $mail_data['to'];

        if ($log_to !== $current_to) {
            return false;
        }

        // Additional check: timestamp should be within last 60 seconds
        $log_time = $log_entry['ts'] ?? 0;
        $current_time = time();

        if ($current_time - $log_time > 60) {
            return false;
        }

        return true;
    }

    /**
     * Create enhanced log entry with comprehensive email data
     */
    private function create_log_entry($mail_data, $status, $email_id = null) {
        // Use provided email ID or generate unique ID
        $uid = $email_id ?: uniqid('smtp_', true);

        // Parse recipients - handle different input formats
        $recipients = array();
        if (is_array($mail_data['to'])) {
            $recipients = $mail_data['to'];
        } elseif (is_string($mail_data['to'])) {
            // Handle comma-separated string
            $recipients = array_map('trim', explode(',', $mail_data['to']));
        }

        // Parse and enhance headers
        $headers = $this->parse_headers($mail_data['headers']);
        $raw_headers = $this->format_raw_headers($mail_data['headers']);

        // Extract email addresses for logging
        $rcptto = array_map(array($this, 'extract_email'), $recipients);

        // Determine sender
        $mailfrom = $this->determine_sender($headers);

        // Detect content type
        $content_type = $this->detect_content_type($mail_data['message'], $headers);

        // Get enhanced caller information
        $caller = $this->get_email_caller();

        // Process attachments
        $attachments_info = $this->process_attachments($mail_data['attachments']);

        // Current timestamp
        $timestamp = time();
        $date_iso = date('c', $timestamp);

        // Create comprehensive log entry with all requested fields
        $log_entry = array(
            // Basic Zeek SMTP structure
            'ts' => $timestamp,
            'date' => $date_iso,
            'uid' => $uid,
            'id' => array(
                'orig_h' => $_SERVER['SERVER_ADDR'] ?? '127.0.0.1',
                'orig_p' => $_SERVER['SERVER_PORT'] ?? 80,
                'resp_h' => $this->get_smtp_server(),
                'resp_p' => 25
            ),
            'trans_depth' => 1,
            'helo' => $_SERVER['SERVER_NAME'] ?? 'localhost',
            'mailfrom' => $this->extract_email($mailfrom),
            'rcptto' => $rcptto,

            // Enhanced email information (user requested)
            'email_subject' => $mail_data['subject'] ?? '',
            'email_content' => $this->should_log_content('body') ? $this->process_email_content($mail_data['message'] ?? '') : '[CONTENT HIDDEN FOR PRIVACY]',
            'email_content_type' => $content_type,
            'email_content_html' => $this->is_html_content($mail_data['message'], $headers),
            'email_attachments' => $attachments_info,
            'email_headers' => array(
                'to' => $recipients,
                'from' => $mailfrom,
                'reply_to' => isset($headers['Reply-To']) ? $headers['Reply-To'] : '',
                'cc' => $this->extract_header_addresses($headers, 'Cc'),
                'bcc' => $this->extract_header_addresses($headers, 'Bcc'),
                'return_path' => isset($headers['Return-Path']) ? $headers['Return-Path'] : '',
                'message_id' => isset($headers['Message-ID']) ? $headers['Message-ID'] : '<' . $uid . '@' . ($_SERVER['SERVER_NAME'] ?? 'localhost') . '>',
                'in_reply_to' => isset($headers['In-Reply-To']) ? $headers['In-Reply-To'] : '',
                'references' => isset($headers['References']) ? $headers['References'] : '',
                'priority' => isset($headers['X-Priority']) ? $headers['X-Priority'] : 'normal',
                'all_headers' => $headers
            ),
            'error_message' => '',
            'ip_address' => $this->log_ip_address ? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1') : 'IP logging disabled',
            'date_time' => $date_iso,
            'receiver' => implode(', ', $recipients),
            'caller_info' => $caller, // Detailed caller tracking

            // Legacy fields for compatibility
            'date_header' => date('r', $timestamp),
            'from' => $mailfrom,
            'to' => $recipients,
            'cc' => $this->extract_header_addresses($headers, 'Cc'),
            'bcc' => $this->extract_header_addresses($headers, 'Bcc'),
            'reply_to' => isset($headers['Reply-To']) ? $headers['Reply-To'] : '',
            'msg_id' => isset($headers['Message-ID']) ? $headers['Message-ID'] : '<' . $uid . '@' . ($_SERVER['SERVER_NAME'] ?? 'localhost') . '>',
            'in_reply_to' => isset($headers['In-Reply-To']) ? $headers['In-Reply-To'] : '',
            'subject' => $mail_data['subject'] ?? '',
            'x_originating_ip' => $this->log_ip_address ? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1') : 'IP logging disabled',
            'first_received' => date('r', $timestamp),
            'second_received' => '',
            'last_reply' => '',
            'path' => array($_SERVER['SERVER_NAME'] ?? 'localhost'),
            'user_agent' => 'WordPress/' . get_bloginfo('version'),
            'tls' => $this->detect_tls_usage(),
            'fuids' => array(),
            'is_webmail' => true,
            'status' => $status,
            'smtp_reply' => $status === 'sent' ? '250' : ($status === 'failed' ? '550' : ''),
            'message_size' => strlen($mail_data['message'] ?? ''),
            'attachments' => $mail_data['attachments'] ?? array(), // Raw attachment list
            'all_headers' => $headers,
            'headers_raw' => $raw_headers, // Raw headers for forensic analysis
            'wordpress_context' => array(
                'hook' => \current_action(),
                'user_id' => \get_current_user_id(),
                'post_id' => \get_the_ID(),
                'url' => $_SERVER['REQUEST_URI'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
                'is_admin' => \is_admin(),
                'is_ajax' => \wp_doing_ajax(),
                'is_cron' => \wp_doing_cron(),
                'wp_version' => \get_bloginfo('version'),
                'php_version' => PHP_VERSION,
                'memory_usage' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true)
            )
        );

        return $log_entry;
    }

    /**
     * Write log entry to file (JSON Lines format)
     */
    private function write_log_entry($log_entry) {
        // Update current log file path in case date changed
        $this->current_log_file = $this->get_current_log_file();

        $json_line = json_encode($log_entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

        // Write to file with lock
        file_put_contents($this->current_log_file, $json_line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Parse email headers with enhanced handling
     */
    private function parse_headers($headers) {
        $parsed = array();

        if (is_array($headers)) {
            foreach ($headers as $header) {
                if (is_string($header) && strpos($header, ':') !== false) {
                    list($key, $value) = explode(':', $header, 2);
                    $key = trim($key);
                    $value = trim($value);

                    // Handle multiple headers with same name (like Received)
                    if (isset($parsed[$key])) {
                        if (!is_array($parsed[$key])) {
                            $parsed[$key] = array($parsed[$key]);
                        }
                        $parsed[$key][] = $value;
                    } else {
                        $parsed[$key] = $value;
                    }
                }
            }
        } elseif (is_string($headers)) {
            // Handle single header string
            $header_lines = explode("\n", $headers);
            foreach ($header_lines as $line) {
                if (strpos($line, ':') !== false) {
                    list($key, $value) = explode(':', $line, 2);
                    $parsed[trim($key)] = trim($value);
                }
            }
        }

        return $parsed;
    }

    /**
     * Determine sender email address
     */
    private function determine_sender($headers) {
        // Priority: From header > Return-Path > admin email
        if (isset($headers['From'])) {
            return $headers['From'];
        }
        if (isset($headers['Return-Path'])) {
            return $headers['Return-Path'];
        }
        return get_option('admin_email', 'unknown@localhost');
    }

    /**
     * Detect content type from message and headers
     */
    private function detect_content_type($message, $headers) {
        // Check Content-Type header first
        if (isset($headers['Content-Type'])) {
            return $headers['Content-Type'];
        }

        // Auto-detect based on content
        if ($this->is_html_content($message, $headers)) {
            return 'text/html; charset=UTF-8';
        }

        return 'text/plain; charset=UTF-8';
    }

    /**
     * Check if content is HTML
     */
    private function is_html_content($message, $headers) {
        // Check Content-Type header
        if (isset($headers['Content-Type']) && stripos($headers['Content-Type'], 'text/html') !== false) {
            return true;
        }

        // Simple HTML detection
        return preg_match('/<[a-z][\s\S]*>/i', $message) !== 0;
    }

    /**
     * Process attachments to get detailed information (enhanced for remote/stream)
     */
    private function process_attachments($attachments) {
        if (empty($attachments) || !is_array($attachments)) {
            return array();
        }

        $processed = array();
        foreach ($attachments as $attachment) {
            $attachment_info = array(
                'original_path' => $attachment,
                'file_name' => 'unknown',
                'file_size' => 0,
                'mime_type' => 'unknown',
                'type' => 'unknown',
                'accessible' => false
            );

            if (is_string($attachment)) {
                $attachment_info['original_path'] = $attachment;

                // Detect attachment type
                if ($this->is_stream_attachment($attachment)) {
                    $attachment_info['type'] = 'stream';
                    $attachment_info['file_name'] = $this->extract_stream_name($attachment);
                    $attachment_info['mime_type'] = $this->get_stream_mime_type($attachment);
                    $attachment_info['accessible'] = $this->is_stream_readable($attachment);
                } elseif ($this->is_remote_url($attachment)) {
                    $attachment_info['type'] = 'remote_url';
                    $attachment_info['file_name'] = basename(parse_url($attachment, PHP_URL_PATH)) ?: 'remote_file';
                    $attachment_info['mime_type'] = $this->get_remote_mime_type($attachment);
                    $attachment_info['accessible'] = $this->is_remote_accessible($attachment);
                } elseif (file_exists($attachment)) {
                    // Local file
                    $attachment_info['type'] = 'local_file';
                    $attachment_info['file_name'] = basename($attachment);
                    $attachment_info['file_size'] = filesize($attachment);
                    $attachment_info['mime_type'] = $this->get_mime_type($attachment);
                    $attachment_info['accessible'] = true;
                } else {
                    // File doesn't exist
                    $attachment_info['type'] = 'missing_file';
                    $attachment_info['file_name'] = basename($attachment);
                    $attachment_info['mime_type'] = $this->guess_mime_by_extension($attachment);
                    $attachment_info['accessible'] = false;
                }
            } else {
                // Non-string attachment (shouldn't happen but handle gracefully)
                $attachment_info['original_path'] = serialize($attachment);
                $attachment_info['type'] = 'unknown_format';
                $attachment_info['file_name'] = 'unknown_attachment';
            }

            $processed[] = $attachment_info;
        }

        return $processed;
    }

    /**
     * Get MIME type of file
     */
    private function get_mime_type($file_path) {
        if (function_exists('mime_content_type')) {
            return mime_content_type($file_path);
        }

        if (function_exists('finfo_file')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file_path);
            finfo_close($finfo);
            return $mime;
        }

        // Fallback based on extension
        $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        $mime_types = array(
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'txt' => 'text/plain',
            'zip' => 'application/zip'
        );

        return isset($mime_types[$ext]) ? $mime_types[$ext] : 'application/octet-stream';
    }

    /**
     * Check if attachment is a stream (php://temp, php://memory, etc.)
     */
    private function is_stream_attachment($path) {
        return strpos($path, 'php://') === 0 ||
               strpos($path, 'data://') === 0 ||
               preg_match('/^[a-z]+:\/\//', $path) === 1;
    }

    /**
     * Check if attachment is a remote URL
     */
    private function is_remote_url($path) {
        return filter_var($path, FILTER_VALIDATE_URL) &&
               (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0);
    }

    /**
     * Extract name from stream path
     */
    private function extract_stream_name($stream_path) {
        if (strpos($stream_path, 'php://temp') === 0) {
            return 'temp_stream';
        }
        if (strpos($stream_path, 'php://memory') === 0) {
            return 'memory_stream';
        }
        if (strpos($stream_path, 'data://') === 0) {
            return 'data_stream';
        }

        // Extract from other stream types
        $parts = explode('://', $stream_path, 2);
        return isset($parts[1]) ? $parts[1] : 'unknown_stream';
    }

    /**
     * Get MIME type for stream
     */
    private function get_stream_mime_type($stream_path) {
        if (strpos($stream_path, 'data://') === 0) {
            // Try to extract MIME type from data URI
            if (preg_match('/^data:([^;]+)/', $stream_path, $matches)) {
                return $matches[1];
            }
        }

        return 'application/octet-stream';
    }

    /**
     * Check if stream is readable
     */
    private function is_stream_readable($stream_path) {
        if (strpos($stream_path, 'php://') === 0) {
            return true; // Most PHP streams are readable
        }

        // For other streams, try to check if resource exists
        return is_resource($stream_path) || filter_var($stream_path, FILTER_VALIDATE_URL);
    }

    /**
     * Get MIME type for remote URL
     */
    private function get_remote_mime_type($url) {
        // Try to get from file extension
        $mime = $this->guess_mime_by_extension($url);

        if ($mime !== 'application/octet-stream') {
            return $mime;
        }

        // Could implement HEAD request here if needed
        return 'application/octet-stream';
    }

    /**
     * Check if remote URL is accessible
     */
    private function is_remote_accessible($url) {
        // Basic check - could be enhanced with actual HTTP request
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Guess MIME type by file extension (enhanced)
     */
    private function guess_mime_by_extension($file_path) {
        $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        $mime_types = array(
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'html' => 'text/html',
            'htm' => 'text/html',
            'xml' => 'text/xml',
            'json' => 'application/json',
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            '7z' => 'application/x-7z-compressed',
            'tar' => 'application/x-tar',
            'gz' => 'application/gzip'
        );

        return isset($mime_types[$ext]) ? $mime_types[$ext] : 'application/octet-stream';
    }

    /**
     * Extract email addresses from header (CC, BCC, etc.)
     */
    private function extract_header_addresses($headers, $header_name) {
        if (!isset($headers[$header_name])) {
            return array();
        }

        $addresses = $headers[$header_name];
        if (is_string($addresses)) {
            $addresses = array_map('trim', explode(',', $addresses));
        } elseif (!is_array($addresses)) {
            $addresses = array($addresses);
        }

        return array_filter($addresses);
    }

    /**
     * Detect if TLS is being used
     */
    private function detect_tls_usage() {
        // Check if HTTPS is used for the request
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            return true;
        }

        // Check common SMTP TLS configurations
        if (defined('SMTP_SECURE') && SMTP_SECURE) {
            return true;
        }

        return false;
    }

    /**
     * Extract email address from string
     */
    private function extract_email($email_string) {
        if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $email_string, $matches)) {
            return $matches[0];
        }
        return $email_string;
    }

    /**
     * Get SMTP server with enhanced detection
     */
    private function get_smtp_server() {
        // First check if we have PHPMailer config from current session
        if (!empty($this->current_smtp_config['host']) && $this->current_smtp_config['host'] !== 'localhost') {
            return $this->current_smtp_config['host'];
        }

        // Try to get from common SMTP plugin constants
        if (defined('SMTP_HOST') && SMTP_HOST !== 'localhost') {
            return SMTP_HOST;
        }

        // Check popular SMTP plugins
        $smtp_info = $this->detect_smtp_plugins();
        if (!empty($smtp_info['host']) && $smtp_info['host'] !== 'localhost') {
            return $smtp_info['host'];
        }

        // Fallback to localhost
        return 'localhost';
    }

    /**
     * Detect SMTP configuration from popular plugins
     */
    private function detect_smtp_plugins() {
        $smtp_info = array(
            'host' => 'localhost',
            'port' => 25,
            'plugin' => 'none',
            'service' => 'unknown'
        );

        // WP Mail SMTP by WPForms
        if (class_exists('WPMailSMTP\Options')) {
            $options = \WPMailSMTP\Options::init();
            $mailer = $options->get('mail', 'mailer');

            if ($mailer === 'smtp') {
                $smtp_info['host'] = $options->get('smtp', 'host');
                $smtp_info['port'] = $options->get('smtp', 'port');
                $smtp_info['plugin'] = 'WP Mail SMTP';
            } elseif ($mailer === 'sendgrid') {
                $smtp_info['host'] = 'smtp.sendgrid.net';
                $smtp_info['service'] = 'SendGrid';
                $smtp_info['plugin'] = 'WP Mail SMTP';
            } elseif ($mailer === 'mailgun') {
                $region = $options->get('mailgun', 'region');
                $smtp_info['host'] = $region === 'EU' ? 'smtp.eu.mailgun.org' : 'smtp.mailgun.org';
                $smtp_info['service'] = 'Mailgun';
                $smtp_info['plugin'] = 'WP Mail SMTP';
            } elseif ($mailer === 'amazonses') {
                $region = $options->get('amazonses', 'region');
                $smtp_info['host'] = 'email-smtp.' . $region . '.amazonaws.com';
                $smtp_info['service'] = 'Amazon SES';
                $smtp_info['plugin'] = 'WP Mail SMTP';
            }
        }
        // Post SMTP Mailer
        elseif (class_exists('PostmanOptions')) {
            $options = \PostmanOptions::getInstance();
            $transport = $options->getTransportType();

            if ($transport === 'smtp') {
                $smtp_info['host'] = $options->getHostname();
                $smtp_info['port'] = $options->getPort();
                $smtp_info['plugin'] = 'Post SMTP';
            } elseif ($transport === 'sendgrid_api') {
                $smtp_info['host'] = 'api.sendgrid.com';
                $smtp_info['service'] = 'SendGrid API';
                $smtp_info['plugin'] = 'Post SMTP';
            } elseif ($transport === 'mailgun_api') {
                $smtp_info['host'] = 'api.mailgun.net';
                $smtp_info['service'] = 'Mailgun API';
                $smtp_info['plugin'] = 'Post SMTP';
            }
        }
        // Easy WP SMTP
        elseif (function_exists('swpsmtp_get_option')) {
            $host = \swpsmtp_get_option('smtp_host');
            if (!empty($host)) {
                $smtp_info['host'] = $host;
                $smtp_info['port'] = \swpsmtp_get_option('smtp_port', 587);
                $smtp_info['plugin'] = 'Easy WP SMTP';
            }
        }
        // WP SMTP
        elseif (defined('WPSMTP_PLUGIN_VER')) {
            $options = get_option('wp_smtp_options');
            if (!empty($options['host'])) {
                $smtp_info['host'] = $options['host'];
                $smtp_info['port'] = $options['port'] ?? 587;
                $smtp_info['plugin'] = 'WP SMTP';
            }
        }
        // FluentSMTP
        elseif (class_exists('FluentMail\App\Services\Mailer\Manager')) {
            $settings = get_option('fluentmail-settings');
            if (!empty($settings['connections']['smtp']['host'])) {
                $smtp_info['host'] = $settings['connections']['smtp']['host'];
                $smtp_info['port'] = $settings['connections']['smtp']['port'] ?? 587;
                $smtp_info['plugin'] = 'FluentSMTP';
            }
        }
        // Sendgrid direct
        elseif (defined('SENDGRID_API_KEY')) {
            $smtp_info['host'] = 'api.sendgrid.com';
            $smtp_info['service'] = 'SendGrid API';
            $smtp_info['plugin'] = 'Direct SendGrid';
        }
        // Mailgun direct
        elseif (defined('MAILGUN_API_KEY')) {
            $smtp_info['host'] = 'api.mailgun.net';
            $smtp_info['service'] = 'Mailgun API';
            $smtp_info['plugin'] = 'Direct Mailgun';
        }

        return $smtp_info;
    }

    /**
     * Format headers as raw string for forensic analysis
     */
    private function format_raw_headers($headers) {
        if (is_string($headers)) {
            // If already a string, preserve original formatting
            return $headers;
        }

        if (!is_array($headers)) {
            return '';
        }

        $raw_lines = array();
        foreach ($headers as $header) {
            if (is_string($header)) {
                // Preserve multiline folded headers
                $raw_lines[] = $header;
            }
        }

        return implode("\r\n", $raw_lines);
    }

    /**
     * Format headers as raw string (legacy method)
     */
    private function format_headers_raw($headers) {
        return $this->format_raw_headers($headers);
    }

    /**
     * Enable IP address logging
     */
    public function enable_ip_logging() {
        $this->log_ip_address = true;
        update_option('mt_smtp_log_ip_address', true);
    }

    /**
     * Disable IP address logging
     */
    public function disable_ip_logging() {
        $this->log_ip_address = false;
        update_option('mt_smtp_log_ip_address', false);
    }

    /**
     * Enable SMTP logging
     */
    public function enable_logging() {
        $this->log_enabled = true;
        update_option('mt_smtp_logging_enabled', true);
        $this->init_hooks();
    }

    /**
     * Disable SMTP logging
     */
    public function disable_logging() {
        $this->log_enabled = false;
        update_option('mt_smtp_logging_enabled', false);

        // Remove hooks
        \remove_filter('pre_wp_mail', array($this, 'capture_email_data'));
        \remove_action('wp_mail_succeeded', array($this, 'log_email_success'));
        \remove_action('wp_mail_failed', array($this, 'log_email_failure'));
        \remove_action('phpmailer_init', array($this, 'setup_phpmailer_hooks'));
    }

    /**
     * Get SMTP log entries
     */
    public function get_log_entries($date = null, $limit = 50) {
        if ($date) {
            $log_file = $this->log_directory . 'smtp-' . $date . '.log';
        } else {
            $log_file = $this->current_log_file;
        }

        if (!file_exists($log_file)) {
            return array();
        }

        $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $entries = array();

        // Get latest entries
        $lines = array_slice($lines, -$limit);

        foreach ($lines as $line) {
            $entry = json_decode($line, true);
            if ($entry) {
                $entries[] = $entry;
            }
        }

        return array_reverse($entries); // Show newest first
    }

    /**
     * Clear current log file
     */
    public function clear_current_log() {
        if (file_exists($this->current_log_file)) {
            return file_put_contents($this->current_log_file, '') !== false;
        }
        return true;
    }

    /**
     * Get available log files
     */
    public function get_available_log_files() {
        $pattern = $this->log_directory . 'smtp-*.log';
        $files = glob($pattern);

        $log_files = array();
        foreach ($files as $file) {
            $filename = basename($file);
            if (preg_match('/smtp-(\d{8})\.log/', $filename, $matches)) {
                $date = $matches[1];
                $log_files[] = array(
                    'date' => $date,
                    'formatted_date' => date('d/m/Y', strtotime($date)),
                    'file' => $file,
                    'size' => filesize($file),
                    'size_formatted' => mt_format_bytes(filesize($file))
                );
            }
        }

        // Sort by date (newest first)
        usort($log_files, function($a, $b) {
            return strcmp($b['date'], $a['date']);
        });

        return $log_files;
    }

    /**
     * Get logging status
     */
    public function get_logging_status() {
        return array(
            'enabled' => $this->log_enabled,
            'current_log_file' => $this->current_log_file,
            'current_log_exists' => file_exists($this->current_log_file),
            'current_log_size' => file_exists($this->current_log_file) ?
                mt_format_bytes(filesize($this->current_log_file)) : '0 B',
            'available_files' => $this->get_available_log_files()
        );
    }

    /**
     * Cleanup old log files (keep last N days)
     */
    public function cleanup_old_logs($keep_days = 30) {
        $cutoff_date = date('dmY', strtotime('-' . $keep_days . ' days'));
        $pattern = $this->log_directory . 'smtp-*.log';
        $files = glob($pattern);

        $deleted = 0;
        foreach ($files as $file) {
            $filename = basename($file);
            if (preg_match('/smtp-(\d{8})\.log/', $filename, $matches)) {
                $file_date = $matches[1];
                if ($file_date < $cutoff_date) {
                    if (unlink($file)) {
                        $deleted++;
                    }
                }
            }
        }

        return $deleted;
    }

    /**
     * Get enhanced caller information (plugin/file that triggered the email)
     */
    private function get_email_caller() {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 20); // Limit to 20 levels
        $caller_info = array(
            'type' => 'Unknown',
            'name' => 'Unknown',
            'file' => 'Unknown',
            'line' => 0,
            'function' => '',
            'class' => '',
            'full_path' => '',
            'plugin_data' => array(),
            'theme_data' => array()
        );

        // Skip internal functions and find the actual caller
        foreach ($trace as $call) {
            // Skip our own methods and WordPress internal functions
            if (isset($call['class']) && $call['class'] === 'MT_SMTP_Logger') {
                continue;
            }

            if (isset($call['function'])) {
                // Skip WordPress internal functions that we don't care about
                $skip_functions = array('wp_mail', 'apply_filters', 'do_action', 'call_user_func_array');
                if (in_array($call['function'], $skip_functions)) {
                    continue;
                }
            }

            if (isset($call['file'])) {
                $file_path = str_replace(ABSPATH, '', $call['file']);
                $caller_info['file'] = $file_path;
                $caller_info['full_path'] = $call['file'];
                $caller_info['line'] = isset($call['line']) ? $call['line'] : 0;
                $caller_info['function'] = isset($call['function']) ? $call['function'] : '';
                $caller_info['class'] = isset($call['class']) ? $call['class'] : '';

                // Determine if it's a plugin
                if (strpos($file_path, 'wp-content/plugins/') === 0) {
                    $parts = explode('/', $file_path);
                    if (isset($parts[2])) {
                        $plugin_folder = $parts[2];
                        $caller_info['type'] = 'Plugin';
                        $caller_info['name'] = $plugin_folder;

                        // Try to get plugin data
                        $plugin_data = $this->get_plugin_data($plugin_folder);
                        if ($plugin_data) {
                            $caller_info['plugin_data'] = $plugin_data;
                            $caller_info['name'] = $plugin_data['name'] ?: $plugin_folder;
                        }
                    }
                    break;
                }
                // Determine if it's a theme
                elseif (strpos($file_path, 'wp-content/themes/') === 0) {
                    $parts = explode('/', $file_path);
                    if (isset($parts[2])) {
                        $theme_folder = $parts[2];
                        $caller_info['type'] = 'Theme';
                        $caller_info['name'] = $theme_folder;

                        // Try to get theme data
                        $theme_data = $this->get_theme_data($theme_folder);
                        if ($theme_data) {
                            $caller_info['theme_data'] = $theme_data;
                            $caller_info['name'] = $theme_data['name'] ?: $theme_folder;
                        }
                    }
                    break;
                }
                // WordPress core
                elseif (strpos($file_path, 'wp-admin/') === 0 || strpos($file_path, 'wp-includes/') === 0) {
                    $caller_info['type'] = 'WordPress Core';
                    $caller_info['name'] = 'WordPress Core';
                    break;
                }
                // Root level files
                else {
                    $caller_info['type'] = 'Root';
                    $caller_info['name'] = basename($call['file']);
                    break;
                }
            }
        }

        return $caller_info;
    }

    /**
     * Process email content according to privacy settings
     */
    private function process_email_content($content) {
        $privacy_mode = \get_option('mt_smtp_privacy_mode', 'full'); // full, truncated, obfuscated, none
        $max_length = \get_option('mt_smtp_content_max_length', 1000);

        switch ($privacy_mode) {
            case 'none':
                return '';

            case 'truncated':
                if (strlen($content) > $max_length) {
                    return substr($content, 0, $max_length) . ' [TRUNCATED FOR PRIVACY]';
                }
                return $content;

            case 'obfuscated':
                return $this->obfuscate_email_content($content);

            case 'headers_only':
                return '[EMAIL CONTENT HIDDEN FOR PRIVACY - HEADERS ONLY MODE]';

            case 'full':
            default:
                return $content;
        }
    }

    /**
     * Obfuscate email content for privacy
     */
    private function obfuscate_email_content($content) {
        // Remove potentially sensitive data
        $patterns = array(
            // Email addresses
            '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/' => '[EMAIL_REDACTED]',
            // Phone numbers (basic patterns)
            '/\b\d{3}[-.]?\d{3}[-.]?\d{4}\b/' => '[PHONE_REDACTED]',
            '/\b\+?\d{1,4}[\s.-]?\d{3,4}[\s.-]?\d{3,4}[\s.-]?\d{3,4}\b/' => '[PHONE_REDACTED]',
            // URLs
            '/https?:\/\/[^\s]+/' => '[URL_REDACTED]',
            // Potential ID numbers or tokens
            '/\b[A-Z0-9]{8,}\b/' => '[TOKEN_REDACTED]',
            // Credit card numbers (basic)
            '/\b\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4}\b/' => '[CARD_REDACTED]',
        );

        $obfuscated = $content;
        foreach ($patterns as $pattern => $replacement) {
            $obfuscated = preg_replace($pattern, $replacement, $obfuscated);
        }

        // Truncate if still too long
        $max_length = \get_option('mt_smtp_content_max_length', 500);
        if (strlen($obfuscated) > $max_length) {
            $obfuscated = substr($obfuscated, 0, $max_length) . ' [TRUNCATED]';
        }

        return $obfuscated;
    }

    /**
     * Check if content should be logged based on privacy settings
     */
    private function should_log_content($content_type = 'email') {
        $privacy_mode = \get_option('mt_smtp_privacy_mode', 'full');

        if ($privacy_mode === 'none') {
            return false;
        }

        if ($privacy_mode === 'headers_only' && $content_type === 'body') {
            return false;
        }

        return true;
    }

    /**
     * Get plugin data from plugin folder
     */
    private function get_plugin_data($plugin_folder) {
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugin_file = WP_PLUGIN_DIR . '/' . $plugin_folder . '/' . $plugin_folder . '.php';

        // Try common plugin file patterns
        $possible_files = array(
            $plugin_file,
            WP_PLUGIN_DIR . '/' . $plugin_folder . '/index.php',
            WP_PLUGIN_DIR . '/' . $plugin_folder . '/main.php'
        );

        // Also check for any PHP file in the plugin directory
        if (!file_exists($plugin_file)) {
            $files = glob(WP_PLUGIN_DIR . '/' . $plugin_folder . '/*.php');
            if (!empty($files)) {
                foreach ($files as $file) {
                    $content = file_get_contents($file, false, null, 0, 1024); // Read first 1KB
                    if (strpos($content, 'Plugin Name:') !== false) {
                        $plugin_file = $file;
                        break;
                    }
                }
            }
        }

        if (file_exists($plugin_file) && function_exists('get_plugin_data')) {
            try {
                $plugin_data = get_plugin_data($plugin_file, false, false);
                return array(
                    'name' => $plugin_data['Name'],
                    'version' => $plugin_data['Version'],
                    'author' => $plugin_data['Author'],
                    'description' => $plugin_data['Description'],
                    'plugin_uri' => $plugin_data['PluginURI'],
                    'file' => $plugin_file
                );
            } catch (Exception $e) {
                // Fallback if get_plugin_data fails
                return array('name' => $plugin_folder, 'error' => $e->getMessage());
            }
        }

        return null;
    }

    /**
     * Get theme data from theme folder
     */
    private function get_theme_data($theme_folder) {
        $theme = wp_get_theme($theme_folder);

        if ($theme && !$theme->errors()) {
            return array(
                'name' => $theme->get('Name'),
                'version' => $theme->get('Version'),
                'author' => $theme->get('Author'),
                'description' => $theme->get('Description'),
                'theme_uri' => $theme->get('ThemeURI'),
                'template' => $theme->get_template(),
                'stylesheet' => $theme->get_stylesheet()
            );
        }

        return null;
    }
}