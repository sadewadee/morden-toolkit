<?php

namespace ModernToolkit;

if (!defined('ABSPATH')) {
    exit;
}

class Plugin {
    private static $instance = null;
    private $services = array();

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
        $this->init_services();
    }

    private function init_hooks() {
        if (function_exists('add_action')) {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
            add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
            add_action('wp_ajax_mt_toggle_debug', array($this, 'ajax_toggle_debug'));
            add_action('wp_ajax_mt_toggle_debug_constant', array($this, 'ajax_toggle_debug_constant'));
            add_action('wp_ajax_mt_clear_debug_log', array($this, 'ajax_clear_debug_log'));
            add_action('wp_ajax_mt_get_debug_log', array($this, 'ajax_get_debug_log'));
            add_action('wp_ajax_mt_get_query_logs', array($this, 'ajax_get_query_logs'));
            add_action('wp_ajax_mt_clear_query_log', array($this, 'ajax_clear_query_log'));
            add_action('wp_ajax_mt_cleanup_query_logs', array($this, 'ajax_cleanup_query_logs'));
            add_action('wp_ajax_mt_get_log_info', array($this, 'ajax_get_log_info'));
            add_action('wp_ajax_mt_download_query_logs', array($this, 'ajax_download_query_logs'));
            add_action('wp_ajax_mt_toggle_query_monitor', array($this, 'ajax_toggle_query_monitor'));
            add_action('wp_ajax_mt_save_htaccess', array($this, 'ajax_save_htaccess'));
            add_action('wp_ajax_mt_restore_htaccess', array($this, 'ajax_restore_htaccess'));
            add_action('wp_ajax_mt_apply_php_preset', array($this, 'ajax_apply_php_preset'));
            add_action('wp_ajax_mt_save_custom_preset', array($this, 'ajax_save_custom_preset'));
            add_action('wp_ajax_mt_reset_custom_preset', array($this, 'ajax_reset_custom_preset'));
            
            // SMTP Logs AJAX endpoints
            add_action('wp_ajax_mt_get_smtp_logs', array($this, 'ajax_get_smtp_logs'));
            add_action('wp_ajax_mt_clear_smtp_logs', array($this, 'ajax_clear_smtp_logs'));
            add_action('wp_ajax_mt_download_smtp_logs', array($this, 'ajax_download_smtp_logs'));
            add_action('wp_ajax_mt_send_test_email', array($this, 'ajax_send_test_email'));
            add_action('wp_ajax_mt_toggle_smtp_logging_setting', array($this, 'ajax_toggle_smtp_logging_setting'));

            add_action('init', array($this, 'schedule_log_cleanup'));
            add_action('mt_daily_log_cleanup', array($this, 'daily_log_cleanup'));
        }
    }

    private function init_services() {
        $this->services['debug'] = new Debug();
        $this->services['query_monitor'] = new QueryMonitor();
        $this->services['htaccess'] = new Htaccess();
        $this->services['php_config'] = new PhpConfig();
        $this->services['file_manager'] = new FileManager();
        $this->services['smtp_logger'] = new SmtpLogger();
    }

    public function get_service($name) {
        return isset($this->services[$name]) ? $this->services[$name] : null;
    }

    public function add_admin_menu() {
        if (function_exists('add_management_page')) {
            add_management_page(
                function_exists('__') ? __('Morden Toolkit', 'morden-toolkit') : 'Morden Toolkit',
                function_exists('__') ? __('Morden Toolkit', 'morden-toolkit') : 'Morden Toolkit',
                'manage_options',
                'mt',
                array($this, 'render_admin_page')
            );
        }

        if (function_exists('add_submenu_page')) {
            add_submenu_page(
                'tools.php',
                function_exists('__') ? __('Debug Logs', 'morden-toolkit') : 'Debug Logs',
                function_exists('__') ? __('Debug Logs', 'morden-toolkit') : 'Debug Logs',
                'manage_options',
                'mt-logs',
                array($this, 'render_logs_page')
            );

            add_submenu_page(
                'tools.php',
                function_exists('__') ? __('Query Logs', 'morden-toolkit') : 'Query Logs',
                function_exists('__') ? __('Query Logs', 'morden-toolkit') : 'Query Logs',
                'manage_options',
                'mt-query-logs',
                array($this, 'render_query_logs_page')
            );

            // Only show SMTP Logs menu if SMTP logging is enabled
            if (get_option('mt_smtp_logging_enabled', false)) {
                add_submenu_page(
                    'tools.php',
                    function_exists('__') ? __('SMTP Logs', 'morden-toolkit') : 'SMTP Logs',
                    function_exists('__') ? __('SMTP Logs', 'morden-toolkit') : 'SMTP Logs',
                    'manage_options',
                    'mt-smtp-logs',
                    array($this, 'render_smtp_logs_page')
                );
            }
        }
    }

    public function enqueue_admin_scripts($hook) {
        // Load CSS/JS on Morden Toolkit pages only
        if (!in_array($hook, array('tools_page_mt', 'tools_page_mt-logs', 'tools_page_mt-query-logs', 'tools_page_mt-smtp-logs'))) {
            return;
        }

        if (function_exists('wp_enqueue_style')) {
            wp_enqueue_style(
                'mt-admin',
                MT_PLUGIN_URL . 'admin/assets/admin.css',
                array(),
                MT_VERSION
            );

            if ($hook === 'tools_page_mt-query-logs') {
                wp_enqueue_style(
                    'mt-query-logs',
                    MT_PLUGIN_URL . 'admin/assets/css/query-logs.css',
                    array(),
                    MT_VERSION
                );
            }
        }

        if (function_exists('wp_enqueue_script')) {
            wp_enqueue_script(
                'mt-admin',
                MT_PLUGIN_URL . 'admin/assets/admin.js',
                array('jquery'),
                MT_VERSION,
                false
            );
        }

        if (function_exists('wp_localize_script')) {
            wp_localize_script('mt-admin', 'mtToolkit', array(
                'ajaxurl' => function_exists('admin_url') ? admin_url('admin-ajax.php') : '/wp-admin/admin-ajax.php',
                'nonce' => function_exists('wp_create_nonce') ? wp_create_nonce('mt_action') : '',
                'strings' => array(
                    'confirm_clear_logs' => function_exists('__') ? __('Are you sure you want to clear all debug logs?', 'morden-toolkit') : 'Are you sure you want to clear all debug logs?',
                    'confirm_restore_htaccess' => function_exists('__') ? __('Are you sure you want to restore this backup?', 'morden-toolkit') : 'Are you sure you want to restore this backup?',
                    'error_occurred' => function_exists('__') ? __('An error occurred. Please try again.', 'morden-toolkit') : 'An error occurred. Please try again.',
                    'success' => function_exists('__') ? __('Operation completed successfully.', 'morden-toolkit') : 'Operation completed successfully.',
                )
            ));
        }
    }

    public function enqueue_frontend_scripts() {
        $enabled = (function_exists('get_option') ? get_option('mt_query_monitor_enabled') : false) && 
                   (function_exists('is_user_logged_in') ? is_user_logged_in() : false) && 
                   (function_exists('current_user_can') ? current_user_can('manage_options') : false);
        
        if (!$enabled) {
            return;
        }

        if (function_exists('wp_enqueue_script')) {
            wp_enqueue_script(
                'mt-admin',
                MT_PLUGIN_URL . 'admin/assets/admin.js',
                array('jquery'),
                MT_VERSION,
                false
            );
        }

        if (function_exists('wp_localize_script')) {
            wp_localize_script('mt-admin', 'mtToolkit', array(
                'ajaxurl' => function_exists('admin_url') ? admin_url('admin-ajax.php') : '/wp-admin/admin-ajax.php',
                'nonce' => function_exists('wp_create_nonce') ? wp_create_nonce('mt_action') : '',
                'strings' => array(
                    'error_occurred' => function_exists('__') ? __('An error occurred', 'morden-toolkit') : 'An error occurred',
                    'confirm_delete' => function_exists('__') ? __('Are you sure you want to delete this?', 'morden-toolkit') : 'Are you sure you want to delete this?',
                    'loading' => function_exists('__') ? __('Loading...', 'morden-toolkit') : 'Loading...'
                )
            ));
        }

        if (function_exists('wp_enqueue_style')) {
            wp_enqueue_style(
                'mt-performance-bar',
                MT_PLUGIN_URL . 'public/assets/performance-bar.css',
                array(),
                MT_VERSION
            );
        }

        if (function_exists('wp_enqueue_script')) {
            wp_enqueue_script(
                'mt-performance-bar',
                MT_PLUGIN_URL . 'public/assets/performance-bar.js',
                array('jquery'),
                MT_VERSION,
                true
            );
        }
    }

    public function render_admin_page() {
        include MT_PLUGIN_DIR . 'admin/views/page-toolkit.php';
    }

    public function render_logs_page() {
        include MT_PLUGIN_DIR . 'admin/views/page-logs.php';
    }

    public function render_query_logs_page() {
        include MT_PLUGIN_DIR . 'admin/views/page-query-logs.php';
    }

    public function render_smtp_logs_page() {
        include MT_PLUGIN_DIR . 'admin/views/page-smtp-logs.php';
    }

    public function ajax_toggle_debug() {
        if (!mt_can_manage() || !mt_verify_nonce($_POST['nonce'])) {
            mt_send_json_error(__('Permission denied.', 'morden-toolkit'));
        }

        $enabled = isset($_POST['enabled']) && $_POST['enabled'] === 'true';
        $result = $this->services['debug']->toggle_debug($enabled);

        if ($result) {
            update_option('mt_debug_enabled', $enabled);
            mt_send_json_success(array(
                'enabled' => $enabled,
                'message' => $enabled ? __('Debug mode enabled.', 'morden-toolkit') : __('Debug mode disabled.', 'morden-toolkit')
            ));
        } else {
            mt_send_json_error(__('Failed to toggle debug mode.', 'morden-toolkit'));
        }
    }

    public function ajax_toggle_debug_constant() {
        if (!mt_can_manage() || !mt_verify_nonce($_POST['nonce'])) {
            mt_send_json_error(__('Permission denied.', 'morden-toolkit'));
        }

        $constant = sanitize_text_field($_POST['constant']);
        $enabled = isset($_POST['enabled']) && $_POST['enabled'] === 'true';


        $allowed_constants = array('WP_DEBUG_LOG', 'WP_DEBUG_DISPLAY', 'SCRIPT_DEBUG', 'SAVEQUERIES', 'display_errors');
        if (!in_array($constant, $allowed_constants)) {
            mt_send_json_error(__('Invalid debug constant.', 'morden-toolkit'));
        }

        $result = $this->services['debug']->toggle_debug_constant($constant, $enabled);

        if ($result) {
    
            $status = $this->services['debug']->get_debug_status();

            mt_send_json_success(array(
                'constant' => $constant,
                'enabled' => $enabled,
                'status' => $status,
                'message' => sprintf(
                    $enabled ? __('%s enabled.', 'morden-toolkit') : __('%s disabled.', 'morden-toolkit'),
                    $constant
                )
            ));
        } else {
            mt_send_json_error(__('Failed to toggle debug constant.', 'morden-toolkit'));
        }
    }

    public function ajax_clear_debug_log() {
        if (!mt_can_manage() || !mt_verify_nonce($_POST['nonce'])) {
            mt_send_json_error(__('Permission denied.', 'morden-toolkit'));
        }

        $result = $this->services['debug']->clear_debug_log();

        if ($result) {
            mt_send_json_success(__('Debug log cleared.', 'morden-toolkit'));
        } else {
            mt_send_json_error(__('Failed to clear debug log.', 'morden-toolkit'));
        }
    }

    public function ajax_get_debug_log() {
        if (!mt_can_manage() || !mt_verify_nonce($_POST['nonce'])) {
            mt_send_json_error(__('Permission denied.', 'morden-toolkit'));
        }

        $logs = $this->services['debug']->get_debug_log_entries();
        mt_send_json_success($logs);
    }

    public function ajax_get_query_logs() {
        if (!mt_can_manage() || !mt_verify_nonce($_POST['nonce'])) {
            mt_send_json_error(__('Permission denied.', 'morden-toolkit'));
        }

        $logs = $this->services['debug']->get_query_log_entries();
        mt_send_json_success($logs);
    }

    public function ajax_clear_query_log() {
        if (!mt_can_manage() || !mt_verify_nonce($_POST['nonce'])) {
            mt_send_json_error(__('Permission denied.', 'morden-toolkit'));
        }

        $result = $this->services['debug']->clear_query_log();

        if ($result) {
            mt_send_json_success(__('Query logs cleared successfully.', 'morden-toolkit'));
        } else {
            mt_send_json_error(__('Failed to clear query logs.', 'morden-toolkit'));
        }
    }

    public function ajax_cleanup_query_logs() {
        if (!mt_can_manage() || !mt_verify_nonce($_POST['nonce'])) {
            mt_send_json_error(__('Permission denied.', 'morden-toolkit'));
        }

        $cleaned = $this->services['debug']->cleanup_old_query_logs();

        if ($cleaned >= 0) {
            mt_send_json_success(sprintf(__('Cleaned up %d old log files.', 'morden-toolkit'), $cleaned));
        } else {
            mt_send_json_error(__('Failed to cleanup old logs.', 'morden-toolkit'));
        }
    }

    public function ajax_get_log_info() {
        if (!mt_can_manage() || !mt_verify_nonce($_POST['nonce'])) {
            mt_send_json_error(__('Permission denied.', 'morden-toolkit'));
        }

        $debug_status = $this->services['debug']->get_debug_status();

        // Extract only log-related info
        $log_info = array(
            'query_log_file_exists' => $debug_status['query_log_file_exists'],
            'query_log_file_size' => $debug_status['query_log_file_size'],
            'query_log_total_size' => isset($debug_status['query_log_total_size']) ? $debug_status['query_log_total_size'] : '',
            'query_log_max_size' => isset($debug_status['query_log_max_size']) ? $debug_status['query_log_max_size'] : ''
        );

        mt_send_json_success($log_info);
    }

    public function schedule_log_cleanup() {
        if (function_exists('wp_next_scheduled') && function_exists('wp_schedule_event')) {
            if (!wp_next_scheduled('mt_daily_log_cleanup')) {
                wp_schedule_event(time(), 'daily', 'mt_daily_log_cleanup');
            }
        }
    }

    public function daily_log_cleanup() {
        if (!mt_can_manage()) {
            return;
        }


        $this->services['debug']->cleanup_old_query_logs();


        $debug_log_path = mt_get_debug_log_path();
        if (file_exists($debug_log_path)) {
            $debug_log_size = filesize($debug_log_path);
            $max_debug_size = mt_get_debug_log_max_size();

            if ($debug_log_size > $max_debug_size) {
    
                $this->truncate_debug_log($debug_log_path, 10000);
            }
        }
    }

    private function truncate_debug_log($log_path, $max_lines = 10000) {
        $lines = file($log_path, FILE_IGNORE_NEW_LINES);

        if (count($lines) > $max_lines) {
            $lines = array_slice($lines, -$max_lines);
            $truncated_content = implode("\n", $lines);
            file_put_contents($log_path, $truncated_content);

            error_log("MT: Debug log truncated to {$max_lines} lines");
        }
    }

    public function ajax_download_query_logs() {
        if (!mt_can_manage() || !mt_verify_nonce($_GET['nonce'])) {
            wp_die(__('Permission denied.', 'morden-toolkit'));
        }

        $query_log_path = mt_get_query_log_path();

        if (!file_exists($query_log_path)) {
            wp_die(__('Query log file not found.', 'morden-toolkit'));
        }

        $content = file_get_contents($query_log_path);
        $filename = 'query-logs-' . date('Y-m-d-H-i-s') . '.txt';

        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));

        echo $content;
        exit;
    }

    public function ajax_toggle_query_monitor() {
        if (!mt_can_manage() || !mt_verify_nonce($_POST['nonce'])) {
            mt_send_json_error(__('Permission denied.', 'morden-toolkit'));
        }

        $enabled = isset($_POST['enabled']) && $_POST['enabled'] === 'true';
        update_option('mt_query_monitor_enabled', $enabled);

        mt_send_json_success(array(
            'enabled' => $enabled,
            'message' => $enabled ? __('Query Monitor enabled.', 'morden-toolkit') : __('Query Monitor disabled.', 'morden-toolkit')
        ));
    }

    public function ajax_save_htaccess() {
        if (!mt_can_manage() || !mt_verify_nonce($_POST['nonce'])) {
            mt_send_json_error(__('Permission denied.', 'morden-toolkit'));
        }

        // Use wp_unslash to remove WordPress auto-added slashes, then basic sanitization
        $content = wp_unslash($_POST['content']);
        
        // Basic sanitization without escaping special characters needed for .htaccess
        $content = wp_kses($content, array());
        
        $result = $this->services['htaccess']->save_htaccess($content);

        if ($result) {
            mt_send_json_success(__('.htaccess file saved successfully.', 'morden-toolkit'));
        } else {
            mt_send_json_error(__('Failed to save .htaccess file.', 'morden-toolkit'));
        }
    }

    public function ajax_restore_htaccess() {
        if (!mt_can_manage() || !mt_verify_nonce($_POST['nonce'])) {
            mt_send_json_error(__('Permission denied.', 'morden-toolkit'));
        }

        $backup_index = intval($_POST['backup_index']);
        $result = $this->services['htaccess']->restore_htaccess($backup_index);

        if ($result) {
            mt_send_json_success(__('.htaccess file restored successfully.', 'morden-toolkit'));
        } else {
            mt_send_json_error(__('Failed to restore .htaccess file.', 'morden-toolkit'));
        }
    }

    public function ajax_apply_php_preset() {
        if (!mt_can_manage() || !mt_verify_nonce($_POST['nonce'])) {
            mt_send_json_error(__('Permission denied.', 'morden-toolkit'));
        }

        $preset = sanitize_text_field($_POST['preset']);
        $result = $this->services['php_config']->apply_preset($preset);

        if ($result) {
            update_option('mt_php_preset', $preset);
            mt_send_json_success(__('PHP configuration applied successfully.', 'morden-toolkit'));
        } else {
            mt_send_json_error(__('Failed to apply PHP configuration.', 'morden-toolkit'));
        }
    }

    public function ajax_save_custom_preset() {
        if (!mt_can_manage() || !mt_verify_nonce($_POST['nonce'])) {
            mt_send_json_error(__('Permission denied.', 'morden-toolkit'));
        }

        $settings = $_POST['settings'];
        if (!is_array($settings)) {
            mt_send_json_error(__('Invalid settings data.', 'morden-toolkit'));
        }

        // Validate and sanitize settings
        $validated_settings = $this->services['php_config']->validate_custom_settings($settings);
        if (!$validated_settings) {
            mt_send_json_error(__('Invalid configuration values.', 'morden-toolkit'));
        }

        // Save custom preset settings
        update_option('mt_custom_preset_settings', $validated_settings);

        // Update the custom preset in php_config service
        $this->services['php_config']->update_custom_preset($validated_settings);

        mt_send_json_success(__('Custom preset saved successfully.', 'morden-toolkit'));
    }

    public function ajax_reset_custom_preset() {
        if (!mt_can_manage() || !mt_verify_nonce($_POST['nonce'])) {
            mt_send_json_error(__('Permission denied.', 'morden-toolkit'));
        }

        // Reset to default custom preset values
        delete_option('mt_custom_preset_settings');
        
        mt_send_json_success(__('Custom preset reset successfully.', 'morden-toolkit'));
    }

    public function ajax_get_smtp_logs() {
        if (!mt_can_manage() || !mt_verify_nonce($_POST['nonce'], 'mt_smtp_logs_nonce')) {
            mt_send_json_error(__('Permission denied.', 'morden-toolkit'));
        }

        $smtp_service = $this->get_service('smtp_logger');
        if (!$smtp_service) {
            mt_send_json_error(__('SMTP service not available.', 'morden-toolkit'));
        }

        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        
        $filters = isset($_POST['filters']) ? $_POST['filters'] : array();
        
        $logs = $smtp_service->get_logs($per_page, $offset, $filters);
        $total_logs = $smtp_service->get_logs_count($filters);
        $total_pages = ceil($total_logs / $per_page);
        
        $pagination = array(
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_logs' => $total_logs,
            'per_page' => $per_page
        );
        
        mt_send_json_success(array(
            'logs' => $logs,
            'pagination' => $pagination
        ));
    }

    public function ajax_clear_smtp_logs() {
        if (!mt_can_manage() || !mt_verify_nonce($_POST['nonce'], 'mt_smtp_logs_nonce')) {
            mt_send_json_error(__('Permission denied.', 'morden-toolkit'));
        }

        $smtp_service = $this->get_service('smtp_logger');
        if (!$smtp_service) {
            mt_send_json_error(__('SMTP service not available.', 'morden-toolkit'));
        }

        $result = $smtp_service->clear_logs();
        
        if ($result !== false) {
            mt_send_json_success(__('SMTP logs cleared successfully.', 'morden-toolkit'));
        } else {
            mt_send_json_error(__('Failed to clear SMTP logs.', 'morden-toolkit'));
        }
    }

    public function ajax_download_smtp_logs() {
        if (!mt_can_manage() || !mt_verify_nonce($_GET['nonce'], 'mt_smtp_logs_nonce')) {
            wp_die(__('Permission denied.', 'morden-toolkit'));
        }

        $smtp_service = $this->get_service('smtp_logger');
        if (!$smtp_service) {
            wp_die(__('SMTP service not available.', 'morden-toolkit'));
        }

        $filters = isset($_GET['filters']) ? $_GET['filters'] : array();
        $csv_data = $smtp_service->export_logs_csv($filters);
        
        $filename = 'mail-logs-' . date('Y-m-d-H-i-s') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        foreach ($csv_data as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    }

    public function ajax_send_test_email() {
        if (!mt_can_manage() || !mt_verify_nonce($_POST['nonce'], 'mt_smtp_logs_nonce')) {
            mt_send_json_error(__('Permission denied.', 'morden-toolkit'));
        }

        $admin_email = get_option('admin_email');
        $site_name = get_option('blogname');
        
        $subject = sprintf(__('[%s] SMTP Test Email', 'morden-toolkit'), $site_name);
        $message = sprintf(
            __('This is a test email sent from Morden Toolkit SMTP logging feature.\n\nSite: %s\nTime: %s\nUser: %s', 'morden-toolkit'),
            home_url(),
            current_time('mysql'),
            wp_get_current_user()->display_name
        );
        
        $result = wp_mail($admin_email, $subject, $message);
        
        if ($result) {
            mt_send_json_success(__('Test email sent successfully.', 'morden-toolkit'));
        } else {
            mt_send_json_error(__('Failed to send test email.', 'morden-toolkit'));
        }
    }

    public function ajax_toggle_smtp_logging_setting() {
        if (!mt_can_manage() || !mt_verify_nonce($_POST['nonce'], 'mt_action')) {
            mt_send_json_error(__('Permission denied.', 'morden-toolkit'));
        }

        $enabled = isset($_POST['enabled']) && $_POST['enabled'] === 'true';
        update_option('mt_smtp_logging_enabled', $enabled);

        mt_send_json_success(array(
            'enabled' => $enabled,
            'message' => $enabled ? __('SMTP logging enabled.', 'morden-toolkit') : __('SMTP logging disabled.', 'morden-toolkit')
        ));
    }


}
