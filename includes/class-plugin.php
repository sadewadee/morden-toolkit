<?php
/**
 * Main plugin class - Service Container
 */

if (!defined('ABSPATH')) {
    exit;
}

class MT_Plugin {
    private static $instance = null;
    private $services = array();

    /**
     * Get plugin instance
     */
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
            add_action('wp_ajax_mt_test_debug_transformer', array($this, 'ajax_test_debug_transformer'));

            add_action('init', array($this, 'schedule_log_cleanup'));
            add_action('mt_daily_log_cleanup', array($this, 'daily_log_cleanup'));
        }
    }

    private function init_services() {
        $this->services['debug'] = new MT_Debug();
        $this->services['query_monitor'] = new MT_Query_Monitor();
        $this->services['htaccess'] = new MT_Htaccess();
        $this->services['php_config'] = new MT_PHP_Config();
        $this->services['file_manager'] = new MT_File_Manager();
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
        }
    }

    public function enqueue_admin_scripts($hook) {
        // Load CSS/JS on Morden Toolkit pages only
        if (!in_array($hook, array('tools_page_mt', 'tools_page_mt-logs', 'tools_page_mt-query-logs'))) {
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

    /**
     * Render main admin page
     */
    public function render_admin_page() {
        include MT_PLUGIN_DIR . 'admin/views/page-toolkit.php';
    }

    /**
     * Render logs page
     */
    public function render_logs_page() {
        include MT_PLUGIN_DIR . 'admin/views/page-logs.php';
    }

    /**
     * Render query logs page
     */
    public function render_query_logs_page() {
        include MT_PLUGIN_DIR . 'admin/views/page-query-logs.php';
    }

    // AJAX Handlers

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

        // Validate constant name
        $allowed_constants = array('WP_DEBUG_LOG', 'WP_DEBUG_DISPLAY', 'SCRIPT_DEBUG', 'SAVEQUERIES', 'display_errors');
        if (!in_array($constant, $allowed_constants)) {
            mt_send_json_error(__('Invalid debug constant.', 'morden-toolkit'));
        }

        $result = $this->services['debug']->toggle_debug_constant($constant, $enabled);

        if ($result) {
            // Get current status to return
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

    /**
     * Schedule daily log cleanup
     */
    public function schedule_log_cleanup() {
        if (function_exists('wp_next_scheduled') && function_exists('wp_schedule_event')) {
            if (!wp_next_scheduled('mt_daily_log_cleanup')) {
                wp_schedule_event(time(), 'daily', 'mt_daily_log_cleanup');
            }
        }
    }

    /**
     * Daily log cleanup task
     */
    public function daily_log_cleanup() {
        if (!mt_can_manage()) {
            return;
        }

        // Cleanup old query logs
        $this->services['debug']->cleanup_old_query_logs();

        // Cleanup old debug logs (keep only 3 most recent)
        $cleaned_debug_logs = mt_cleanup_old_debug_logs();
        if ($cleaned_debug_logs > 0) {
            error_log("MT: Cleaned up {$cleaned_debug_logs} old debug log files");
        }

        // Also cleanup large debug logs if they exceed 50MB
        $debug_log_path = mt_get_debug_log_path();
        if (file_exists($debug_log_path)) {
            $debug_log_size = filesize($debug_log_path);
            $max_debug_size = mt_get_debug_log_max_size();

            if ($debug_log_size > $max_debug_size) {
                // Keep only latest 10000 lines
                $this->truncate_debug_log($debug_log_path, 10000);
            }
        }
    }

    /**
     * Truncate debug log to keep only latest entries
     */
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

        // Reset the custom preset in php_config service
        $this->services['php_config']->reset_custom_preset();

        mt_send_json_success(__('Custom preset reset to default values.', 'morden-toolkit'));
    }

    /**
     * AJAX Test handler for debugging WPConfigTransformer issues
     */
    public function ajax_test_debug_transformer() {
        if (!mt_can_manage() || !mt_verify_nonce($_POST['nonce'])) {
            mt_send_json_error(__('Permission denied.', 'morden-toolkit'));
        }

        error_log('=== MT DEBUG TRANSFORMER TEST (via AJAX) ===');

        // Test WPConfigTransformer functionality
        $test_result = $this->services['debug']->test_wp_config_transformer();

        // Test applying a simple debug constant
        $reflection = new ReflectionClass($this->services['debug']);
        $method = $reflection->getMethod('get_custom_debug_log_path');
        $method->setAccessible(true);
        $custom_path = $method->invoke($this->services['debug']);

        $test_settings = [
            'WP_DEBUG' => true,
            'WP_DEBUG_LOG' => $custom_path
        ];

        error_log('MT Test: Attempting to apply test debug settings via WPConfigTransformer');
        $apply_result = MT_WP_Config_Integration::apply_debug_constants($test_settings);
        error_log('MT Test: Apply result: ' . ($apply_result ? 'SUCCESS' : 'FAILED'));

        $response = [
            'transformer_test' => $test_result,
            'apply_test' => $apply_result,
            'wp_config_path' => mt_get_wp_config_path(),
            'wp_config_writable' => is_writable(mt_get_wp_config_path()),
            'message' => 'Test completed. Check error logs for detailed results.'
        ];

        mt_send_json_success($response);
    }
}
