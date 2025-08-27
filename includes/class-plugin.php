<?php
/**
 * Main plugin class - Service Container
 *
 * @package Morden Toolkit
 * @author Morden Team
 * @license GPL v3 or later
 * @link https://github.com/sadewadee/morden-toolkit
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
            add_action('wp_ajax_mt_clear_all_query_logs', array($this, 'ajax_clear_all_query_logs'));
            add_action('wp_ajax_mt_cleanup_query_logs', array($this, 'ajax_cleanup_query_logs'));
            add_action('wp_ajax_mt_cleanup_debug_logs', array($this, 'ajax_cleanup_debug_logs'));
            add_action('wp_ajax_mt_clear_all_debug_logs', array($this, 'ajax_clear_all_debug_logs'));
            add_action('wp_ajax_mt_cleanup_all_logs', array($this, 'ajax_cleanup_all_logs'));
            add_action('wp_ajax_mt_cleanup_query_rotation_logs', array($this, 'ajax_cleanup_query_rotation_logs'));
            add_action('wp_ajax_mt_get_log_info', array($this, 'ajax_get_log_info'));
            add_action('wp_ajax_mt_download_query_logs', array($this, 'ajax_download_query_logs'));
            add_action('wp_ajax_mt_toggle_query_monitor', array($this, 'ajax_toggle_query_monitor'));
            add_action('wp_ajax_mt_save_htaccess', array($this, 'ajax_save_htaccess'));
            add_action('wp_ajax_mt_restore_htaccess', array($this, 'ajax_restore_htaccess'));
            add_action('wp_ajax_mt_apply_php_preset', array($this, 'ajax_apply_php_preset'));
            add_action('wp_ajax_mt_test_debug_transformer', array($this, 'ajax_test_debug_transformer'));
            add_action('wp_ajax_mt_toggle_smtp_logging', array($this, 'ajax_toggle_smtp_logging'));
            add_action('wp_ajax_mt_toggle_smtp_ip_logging', array($this, 'ajax_toggle_smtp_ip_logging'));
            add_action('wp_ajax_mt_get_smtp_logs', array($this, 'ajax_get_smtp_logs'));
            add_action('wp_ajax_mt_clear_smtp_logs', array($this, 'ajax_clear_smtp_logs'));
            add_action('wp_ajax_mt_cleanup_smtp_logs', array($this, 'ajax_cleanup_smtp_logs'));
            add_action('wp_ajax_mt_download_smtp_logs', array($this, 'ajax_download_smtp_logs'));

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
        $this->services['smtp_logger'] = new MT_SMTP_Logger();
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

    public function enqueue_admin_scripts($hook) {

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

            wp_enqueue_script(
                'mt-performance-tabs',
                MT_PLUGIN_URL . 'public/assets/performance-tabs.js',
                array(),
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

    /**
     * Render SMTP logs page
     */
    public function render_smtp_logs_page() {
        include MT_PLUGIN_DIR . 'admin/views/page-smtp-logs.php';
    }



    public function ajax_toggle_debug() {
        check_ajax_referer('mt_action', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'morden-toolkit'));
        }

        $enabled = isset($_POST['enabled']) && sanitize_key($_POST['enabled']) === 'true';
        $result = $this->services['debug']->toggle_debug($enabled);

        if ($result) {
            update_option('mt_debug_enabled', $enabled);
            wp_send_json_success(array(
                'enabled' => $enabled,
                'message' => $enabled ? __('Debug mode enabled.', 'morden-toolkit') : __('Debug mode disabled.', 'morden-toolkit')
            ));
        } else {
            wp_send_json_error(__('Failed to toggle debug mode.', 'morden-toolkit'));
        }
    }

    public function ajax_toggle_debug_constant() {
        check_ajax_referer('mt_action', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'morden-toolkit'));
        }

        if (!isset($_POST['constant'])) {
            wp_send_json_error(__('Missing constant parameter.', 'morden-toolkit'));
        }


        $raw_constant = sanitize_text_field( wp_unslash( $_POST['constant'] ) );
        $constant     = ( 'display_errors' === $raw_constant ) ? 'display_errors' : strtoupper( $raw_constant );
        $enabled      = isset($_POST['enabled']) && sanitize_key($_POST['enabled']) === 'true';


        $allowed_constants = array( 'WP_DEBUG', 'WP_DEBUG_LOG', 'WP_DEBUG_DISPLAY', 'SCRIPT_DEBUG', 'SAVEQUERIES', 'SMTP_LOGGING', 'display_errors' );
        if (!in_array($constant, $allowed_constants)) {
            wp_send_json_error(__('Invalid debug constant.', 'morden-toolkit'));
        }

        $result = $this->services['debug']->toggle_debug_constant($constant, $enabled);

        if ($result) {

            $status = $this->services['debug']->get_debug_status();

            wp_send_json_success(array(
                'constant' => $constant,
                'enabled' => $enabled,
                'status' => $status,
                'message' => sprintf(
                    $enabled ? __('%s enabled.', 'morden-toolkit') : __('%s disabled.', 'morden-toolkit'),
                    $constant
                )
            ));
        } else {
            wp_send_json_error(__('Failed to toggle debug constant.', 'morden-toolkit'));
        }
    }

    public function ajax_clear_debug_log() {
        check_ajax_referer('mt_action', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'morden-toolkit'));
        }

        $result = $this->services['debug']->clear_debug_log();

        if ($result) {
            wp_send_json_success(__('Debug log cleared.', 'morden-toolkit'));
        } else {
            wp_send_json_error(__('Failed to clear debug log.', 'morden-toolkit'));
        }
    }

    public function ajax_get_debug_log() {
        check_ajax_referer('mt_action', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'morden-toolkit'));
        }

        $logs = $this->services['debug']->get_debug_log_entries();
        wp_send_json_success($logs);
    }

    public function ajax_get_query_logs() {
        check_ajax_referer('mt_action', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'morden-toolkit'));
        }

        $logs = $this->services['debug']->get_query_log_entries();
        wp_send_json_success($logs);
    }

    public function ajax_clear_query_log() {
        check_ajax_referer('mt_action', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'morden-toolkit'));
        }

        $result = $this->services['debug']->clear_query_log();

        if ($result) {
            wp_send_json_success(__('Active query log cleared successfully (content deleted).', 'morden-toolkit'));
        } else {
            wp_send_json_error(__('Failed to clear active query log.', 'morden-toolkit'));
        }
    }

    public function ajax_clear_all_query_logs() {
        check_ajax_referer('mt_action', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'morden-toolkit'));
        }

        $result = $this->services['debug']->clear_all_query_logs();

        if ($result) {
            wp_send_json_success(__('All query logs cleared successfully (active content + rotation files removed).', 'morden-toolkit'));
        } else {
            wp_send_json_error(__('Failed to clear all query logs.', 'morden-toolkit'));
        }
    }

    public function ajax_cleanup_query_logs() {
        check_ajax_referer('mt_action', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'morden-toolkit'));
        }

        $cleaned = $this->services['debug']->cleanup_old_query_logs();

        if ($cleaned >= 0) {
            wp_send_json_success(sprintf(__('Cleaned up %d rotation/archived log files (query.log.1, query.log.2, etc.). Active query.log preserved.', 'morden-toolkit'), $cleaned));
        } else {
            wp_send_json_error(__('Failed to cleanup rotation/archived logs.', 'morden-toolkit'));
        }
    }

    public function ajax_cleanup_debug_logs() {
        check_ajax_referer('mt_action', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'morden-toolkit'));
        }

        $keep_count = isset($_POST['keep_count']) ? absint($_POST['keep_count']) : 3;
        $cleaned = 0;

        if (function_exists('mt_cleanup_old_debug_logs')) {
            $cleaned = mt_cleanup_old_debug_logs($keep_count);
        }

        if ($cleaned >= 0) {
            wp_send_json_success(sprintf(__('Cleaned up %d old debug log files.', 'morden-toolkit'), $cleaned));
        } else {
            wp_send_json_error(__('Failed to cleanup old debug logs.', 'morden-toolkit'));
        }
    }

    public function ajax_clear_all_debug_logs() {
        check_ajax_referer('mt_action', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'morden-toolkit'));
        }

        $cleaned = 0;

        if (function_exists('mt_clear_all_debug_logs_except_active')) {
            $cleaned = mt_clear_all_debug_logs_except_active();
        }

        if ($cleaned >= 0) {
            wp_send_json_success(sprintf(__('Cleared %d old debug log files. Current active log preserved.', 'morden-toolkit'), $cleaned));
        } else {
            wp_send_json_error(__('Failed to clear debug logs.', 'morden-toolkit'));
        }
    }

    public function ajax_cleanup_all_logs() {
        check_ajax_referer('mt_action', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'morden-toolkit'));
        }

        $include_current = isset($_POST['include_current']) && sanitize_key($_POST['include_current']) === 'true';
        $cleaned = 0;

        if (function_exists('mt_cleanup_all_log_files')) {
            $cleaned = mt_cleanup_all_log_files($include_current);
        }

        if ($cleaned >= 0) {
            $message = $include_current ?
                sprintf(__('Removed all %d log files.', 'morden-toolkit'), $cleaned) :
                sprintf(__('Cleaned up %d old log files.', 'morden-toolkit'), $cleaned);
            wp_send_json_success($message);
        } else {
            wp_send_json_error(__('Failed to cleanup log files.', 'morden-toolkit'));
        }
    }

    public function ajax_cleanup_query_rotation_logs() {
        check_ajax_referer('mt_action', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'morden-toolkit'));
        }

        $keep_latest = isset($_POST['keep_latest']) && sanitize_key($_POST['keep_latest']) === 'true';
        $cleaned = 0;

        if (function_exists('mt_cleanup_query_log_rotation_files')) {
            $cleaned = mt_cleanup_query_log_rotation_files($keep_latest);
        }

        if ($cleaned >= 0) {
            $message = $keep_latest ?
                sprintf(__('Cleaned up %d old rotation files. Latest backup (query.log.1) preserved.', 'morden-toolkit'), $cleaned) :
                sprintf(__('Cleaned up %d rotation files.', 'morden-toolkit'), $cleaned);
            wp_send_json_success($message);
        } else {
            wp_send_json_error(__('Failed to cleanup rotation log files.', 'morden-toolkit'));
        }
    }

    public function ajax_get_log_info() {
        check_ajax_referer('mt_action', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'morden-toolkit'));
        }

        $debug_status = $this->services['debug']->get_debug_status();


        $log_info = array(
            'query_log_file_exists' => $debug_status['query_log_file_exists'],
            'query_log_file_size' => $debug_status['query_log_file_size'],
            'query_log_total_size' => isset($debug_status['query_log_total_size']) ? $debug_status['query_log_total_size'] : '',
            'query_log_max_size' => isset($debug_status['query_log_max_size']) ? $debug_status['query_log_max_size'] : ''
        );

        wp_send_json_success($log_info);
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
        if (!current_user_can('manage_options')) {
            return;
        }


        $this->services['debug']->cleanup_old_query_logs();


        $cleaned_debug_logs = mt_cleanup_old_debug_logs();
        if ($cleaned_debug_logs > 0) {
            mt_debug_log("Cleaned up {$cleaned_debug_logs} old debug log files");
        }


        $debug_log_path = mt_get_debug_log_path();
        if (file_exists($debug_log_path)) {
            $debug_log_size = filesize($debug_log_path);
            $max_debug_size = mt_get_debug_log_max_size();

            if ($debug_log_size > $max_debug_size) {

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

            mt_debug_log("Debug log truncated to {$max_lines} lines");
        }
    }

    public function ajax_download_query_logs() {
        check_ajax_referer('mt_action', 'nonce');
        if (!current_user_can('manage_options')) {
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
        check_ajax_referer('mt_action', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'morden-toolkit'));
        }

        $enabled = isset($_POST['enabled']) && sanitize_key($_POST['enabled']) === 'true';
        update_option('mt_query_monitor_enabled', $enabled);

        wp_send_json_success(array(
            'enabled' => $enabled,
            'message' => $enabled ? __('Query Monitor enabled.', 'morden-toolkit') : __('Query Monitor disabled.', 'morden-toolkit')
        ));
    }

    public function ajax_save_htaccess() {
        check_ajax_referer('mt_action', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'morden-toolkit'));
        }

        if (!isset($_POST['content'])) {
            wp_send_json_error(__('Missing content parameter.', 'morden-toolkit'));
        }


        $content = wp_unslash($_POST['content']);


        $content = wp_kses($content, array());

        $result = $this->services['htaccess']->save_htaccess($content);

        if ($result) {
            wp_send_json_success(__('.htaccess file saved successfully.', 'morden-toolkit'));
        } else {
            wp_send_json_error(__('Failed to save .htaccess file.', 'morden-toolkit'));
        }
    }

    public function ajax_restore_htaccess() {
        check_ajax_referer('mt_action', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'morden-toolkit'));
        }

        if (!isset($_POST['backup_index'])) {
            wp_send_json_error(__('Missing backup index parameter.', 'morden-toolkit'));
        }

        $backup_index = absint($_POST['backup_index']);
        $result = $this->services['htaccess']->restore_htaccess($backup_index);

        if ($result) {
            wp_send_json_success(__('.htaccess file restored successfully.', 'morden-toolkit'));
        } else {
            wp_send_json_error(__('Failed to restore .htaccess file.', 'morden-toolkit'));
        }
    }

    public function ajax_apply_php_preset() {
        check_ajax_referer('mt_action', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'morden-toolkit'));
        }

        if (!isset($_POST['preset'])) {
            wp_send_json_error(__('Missing preset parameter.', 'morden-toolkit'));
        }

        $preset = sanitize_key($_POST['preset']);
        $allowed_presets = array('basic', 'medium', 'high');
        if (!in_array($preset, $allowed_presets)) {
            wp_send_json_error(__('Invalid preset value.', 'morden-toolkit'));
        }

        $result = $this->services['php_config']->apply_preset($preset);

        if ($result) {
            update_option('mt_php_preset', $preset);
            wp_send_json_success(__('PHP configuration applied successfully.', 'morden-toolkit'));
        } else {
            wp_send_json_error(__('Failed to apply PHP configuration.', 'morden-toolkit'));
        }
    }


    public function ajax_test_debug_transformer() {
        check_ajax_referer('mt_action', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'morden-toolkit'));
        }

        mt_debug_log('=== DEBUG TRANSFORMER TEST (via AJAX) ===');


        $test_result = $this->services['debug']->test_wp_config_transformer();


        $reflection = new ReflectionClass($this->services['debug']);
        $method = $reflection->getMethod('get_custom_debug_log_path');
        $method->setAccessible(true);
        $custom_path = $method->invoke($this->services['debug']);

        $test_settings = [
            'WP_DEBUG' => true,
            'WP_DEBUG_LOG' => $custom_path
        ];

        mt_debug_log('Attempting to apply test debug settings via WPConfigTransformer');
        $apply_result = MT_WP_Config_Integration::apply_debug_constants($test_settings);
        mt_debug_log('Apply result: ' . ($apply_result ? 'SUCCESS' : 'FAILED'));

        $response = [
            'transformer_test' => $test_result,
            'apply_test' => $apply_result,
            'wp_config_path' => mt_get_wp_config_path(),
            'wp_config_writable' => is_writable(mt_get_wp_config_path()),
            'message' => 'Test completed. Check error logs for detailed results.'
        ];

        wp_send_json_success($response);
    }



    public function ajax_toggle_smtp_logging() {
        check_ajax_referer('mt_action', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'morden-toolkit'));
        }

        $enabled = isset($_POST['enabled']) && sanitize_key($_POST['enabled']) === 'true';


        update_option('mt_smtp_logging_enabled', $enabled);


        if (isset($this->services['smtp_logger'])) {
            $reflection = new ReflectionClass($this->services['smtp_logger']);
            $property = $reflection->getProperty('log_enabled');
            $property->setAccessible(true);
            $property->setValue($this->services['smtp_logger'], $enabled);


            if ($enabled) {
                $init_method = $reflection->getMethod('init_hooks');
                $init_method->setAccessible(true);
                $init_method->invoke($this->services['smtp_logger']);
            }
        }

        wp_send_json_success(array(
            'enabled' => $enabled,
            'message' => $enabled ? __('SMTP logging enabled.', 'morden-toolkit') : __('SMTP logging disabled.', 'morden-toolkit')
        ));
    }

    public function ajax_toggle_smtp_ip_logging() {
        check_ajax_referer('mt_action', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'morden-toolkit'));
        }

        $enabled = isset($_POST['enabled']) && sanitize_key($_POST['enabled']) === 'true';


        update_option('mt_smtp_log_ip_address', $enabled);


        if (isset($this->services['smtp_logger'])) {
            $reflection = new ReflectionClass($this->services['smtp_logger']);
            $property = $reflection->getProperty('log_ip_address');
            $property->setAccessible(true);
            $property->setValue($this->services['smtp_logger'], $enabled);
        }

        wp_send_json_success(array(
            'enabled' => $enabled,
            'message' => $enabled ? __('IP address logging enabled.', 'morden-toolkit') : __('IP address logging disabled.', 'morden-toolkit')
        ));
    }

    public function ajax_get_smtp_logs() {
        check_ajax_referer('mt_action', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'morden-toolkit'));
        }

        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : null;
        $logs = $this->services['smtp_logger']->get_log_entries($date);
        wp_send_json_success($logs);
    }

    public function ajax_clear_smtp_logs() {
        check_ajax_referer('mt_action', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'morden-toolkit'));
        }

        $result = $this->services['smtp_logger']->clear_current_log();

        if ($result) {
            wp_send_json_success(__('SMTP logs cleared successfully.', 'morden-toolkit'));
        } else {
            wp_send_json_error(__('Failed to clear SMTP logs.', 'morden-toolkit'));
        }
    }

    public function ajax_cleanup_smtp_logs() {
        check_ajax_referer('mt_action', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'morden-toolkit'));
        }

        $keep_days = isset($_POST['keep_days']) ? absint($_POST['keep_days']) : 30;
        $cleaned = $this->services['smtp_logger']->cleanup_old_logs($keep_days);

        if ($cleaned >= 0) {
            wp_send_json_success(sprintf(__('Cleaned up %d old SMTP log files.', 'morden-toolkit'), $cleaned));
        } else {
            wp_send_json_error(__('Failed to cleanup old SMTP logs.', 'morden-toolkit'));
        }
    }

    public function ajax_download_smtp_logs() {
        check_ajax_referer('mt_action', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied.', 'morden-toolkit'));
        }

        $date = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : date('dmY');
        $log_file = ABSPATH . 'wp-content/morden-toolkit/smtp-' . $date . '.log';

        if (!file_exists($log_file)) {
            wp_die(__('SMTP log file not found.', 'morden-toolkit'));
        }

        $content = file_get_contents($log_file);
        $filename = 'smtp-logs-' . $date . '.log';

        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));

        echo $content;
        exit;
    }
}
