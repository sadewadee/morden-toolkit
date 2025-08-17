<?php
/**
 * Main plugin class - Service Container
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MT_Plugin {
    /**
     * Plugin instance
     */
    private static $instance = null;

    /**
     * Services container
     */
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

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
        $this->init_services();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
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

        // Schedule daily cleanup of old query logs
        add_action('init', array($this, 'schedule_log_cleanup'));
        add_action('mt_daily_log_cleanup', array($this, 'daily_log_cleanup'));
    }

    /**
     * Initialize services
     */
    private function init_services() {
        $this->services['debug'] = new MT_Debug();
        $this->services['query_monitor'] = new MT_Query_Monitor();
        $this->services['htaccess'] = new MT_Htaccess();
        $this->services['php_config'] = new MT_PHP_Config();
        $this->services['file_manager'] = new MT_File_Manager();
    }

    /**
     * Get service instance
     */
    public function get_service($name) {
        return isset($this->services[$name]) ? $this->services[$name] : null;
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_management_page(
            __('Morden Toolkit', 'mt'),
            __('Morden Toolkit', 'mt'),
            'manage_options',
            'mt',
            array($this, 'render_admin_page')
        );

        add_submenu_page(
            'tools.php',
            __('Debug Logs', 'mt'),
            __('Debug Logs', 'mt'),
            'manage_options',
            'mt-logs',
            array($this, 'render_logs_page')
        );

        add_submenu_page(
            'tools.php',
            __('Query Logs', 'mt'),
            __('Query Logs', 'mt'),
            'manage_options',
            'mt-query-logs',
            array($this, 'render_query_logs_page')
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if (!in_array($hook, array('tools_page_mt', 'tools_page_mt-logs', 'tools_page_mt-query-logs'))) {
            return;
        }

        wp_enqueue_style(
            'mt-admin',
            MT_PLUGIN_URL . 'admin/assets/admin.css',
            array(),
            MT_VERSION
        );

        // Enqueue query logs CSS on query logs page
        if ($hook === 'tools_page_mt-query-logs') {
            wp_enqueue_style(
                'mt-query-logs',
                MT_PLUGIN_URL . 'admin/assets/css/query-logs.css',
                array(),
                MT_VERSION
            );
        }

        wp_enqueue_script(
            'mt-admin',
            MT_PLUGIN_URL . 'admin/assets/admin.js',
            array('jquery'),
            MT_VERSION,
            false  // Load in header, not footer
        );

        wp_localize_script('mt-admin', 'mtToolkit', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mt_action'),
            'strings' => array(
                'confirm_clear_logs' => __('Are you sure you want to clear all debug logs?', 'mt'),
                'confirm_restore_htaccess' => __('Are you sure you want to restore this backup?', 'mt'),
                'error_occurred' => __('An error occurred. Please try again.', 'mt'),
                'success' => __('Operation completed successfully.', 'mt'),
            )
        ));
    }

    /**
     * Enqueue frontend scripts for performance bar
     */
    public function enqueue_frontend_scripts() {
        if (!get_option('mt_query_monitor_enabled') || !is_user_logged_in() || !current_user_can('manage_options')) {
            return;
        }

        // Enqueue admin.js for admin bar performance functionality
        wp_enqueue_script(
            'mt-admin',
            MT_PLUGIN_URL . 'admin/assets/admin.js',
            array('jquery'),
            MT_VERSION,
            false // Load in header to ensure it's available for admin bar
        );

        // Localize script with necessary data
        wp_localize_script('mt-admin', 'mtToolkit', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mt_action'),
            'strings' => array(
                'error_occurred' => __('An error occurred', 'mt'),
                'confirm_delete' => __('Are you sure you want to delete this?', 'mt'),
                'loading' => __('Loading...', 'mt')
            )
        ));

        wp_enqueue_style(
            'mt-performance-bar',
            MT_PLUGIN_URL . 'public/assets/performance-bar.css',
            array(),
            MT_VERSION
        );

        wp_enqueue_script(
            'mt-performance-bar',
            MT_PLUGIN_URL . 'public/assets/performance-bar.js',
            array('jquery'),
            MT_VERSION,
            true
        );
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
            mt_send_json_error(__('Permission denied.', 'mt'));
        }

        $enabled = isset($_POST['enabled']) && $_POST['enabled'] === 'true';
        $result = $this->services['debug']->toggle_debug($enabled);

        if ($result) {
            update_option('mt_debug_enabled', $enabled);
            mt_send_json_success(array(
                'enabled' => $enabled,
                'message' => $enabled ? __('Debug mode enabled.', 'mt') : __('Debug mode disabled.', 'mt')
            ));
        } else {
            mt_send_json_error(__('Failed to toggle debug mode.', 'mt'));
        }
    }

    public function ajax_toggle_debug_constant() {
        if (!mt_can_manage() || !mt_verify_nonce($_POST['nonce'])) {
            mt_send_json_error(__('Permission denied.', 'mt'));
        }

        $constant = sanitize_text_field($_POST['constant']);
        $enabled = isset($_POST['enabled']) && $_POST['enabled'] === 'true';

        // Validate constant name
        $allowed_constants = array('WP_DEBUG_LOG', 'WP_DEBUG_DISPLAY', 'SCRIPT_DEBUG', 'SAVEQUERIES', 'display_errors');
        if (!in_array($constant, $allowed_constants)) {
            mt_send_json_error(__('Invalid debug constant.', 'mt'));
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
                    $enabled ? __('%s enabled.', 'mt') : __('%s disabled.', 'mt'),
                    $constant
                )
            ));
        } else {
            mt_send_json_error(__('Failed to toggle debug constant.', 'mt'));
        }
    }

    public function ajax_clear_debug_log() {
        if (!mt_can_manage() || !mt_verify_nonce($_POST['nonce'])) {
            mt_send_json_error(__('Permission denied.', 'mt'));
        }

        $result = $this->services['debug']->clear_debug_log();

        if ($result) {
            mt_send_json_success(__('Debug log cleared.', 'mt'));
        } else {
            mt_send_json_error(__('Failed to clear debug log.', 'mt'));
        }
    }

    public function ajax_get_debug_log() {
        if (!mt_can_manage() || !mt_verify_nonce($_POST['nonce'])) {
            mt_send_json_error(__('Permission denied.', 'mt'));
        }

        $logs = $this->services['debug']->get_debug_log_entries();
        mt_send_json_success($logs);
    }

    public function ajax_get_query_logs() {
        if (!mt_can_manage() || !mt_verify_nonce($_POST['nonce'])) {
            mt_send_json_error(__('Permission denied.', 'mt'));
        }

        $logs = $this->services['debug']->get_query_log_entries();
        mt_send_json_success($logs);
    }

    public function ajax_clear_query_log() {
        if (!mt_can_manage() || !mt_verify_nonce($_POST['nonce'])) {
            mt_send_json_error(__('Permission denied.', 'mt'));
        }

        $result = $this->services['debug']->clear_query_log();

        if ($result) {
            mt_send_json_success(__('Query logs cleared successfully.', 'mt'));
        } else {
            mt_send_json_error(__('Failed to clear query logs.', 'mt'));
        }
    }

    public function ajax_cleanup_query_logs() {
        if (!mt_can_manage() || !mt_verify_nonce($_POST['nonce'])) {
            mt_send_json_error(__('Permission denied.', 'mt'));
        }

        $cleaned = $this->services['debug']->cleanup_old_query_logs();

        if ($cleaned >= 0) {
            mt_send_json_success(sprintf(__('Cleaned up %d old log files.', 'mt'), $cleaned));
        } else {
            mt_send_json_error(__('Failed to cleanup old logs.', 'mt'));
        }
    }

    public function ajax_get_log_info() {
        if (!mt_can_manage() || !mt_verify_nonce($_POST['nonce'])) {
            mt_send_json_error(__('Permission denied.', 'mt'));
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
        if (!wp_next_scheduled('mt_daily_log_cleanup')) {
            wp_schedule_event(time(), 'daily', 'mt_daily_log_cleanup');
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
            wp_die(__('Permission denied.', 'mt'));
        }

        $query_log_path = mt_get_query_log_path();

        if (!file_exists($query_log_path)) {
            wp_die(__('Query log file not found.', 'mt'));
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
            mt_send_json_error(__('Permission denied.', 'mt'));
        }

        $enabled = isset($_POST['enabled']) && $_POST['enabled'] === 'true';
        update_option('mt_query_monitor_enabled', $enabled);

        mt_send_json_success(array(
            'enabled' => $enabled,
            'message' => $enabled ? __('Query Monitor enabled.', 'mt') : __('Query Monitor disabled.', 'mt')
        ));
    }

    public function ajax_save_htaccess() {
        if (!mt_can_manage() || !mt_verify_nonce($_POST['nonce'])) {
            mt_send_json_error(__('Permission denied.', 'mt'));
        }

        $content = sanitize_textarea_field($_POST['content']);
        $result = $this->services['htaccess']->save_htaccess($content);

        if ($result) {
            mt_send_json_success(__('.htaccess file saved successfully.', 'mt'));
        } else {
            mt_send_json_error(__('Failed to save .htaccess file.', 'mt'));
        }
    }

    public function ajax_restore_htaccess() {
        if (!mt_can_manage() || !mt_verify_nonce($_POST['nonce'])) {
            mt_send_json_error(__('Permission denied.', 'mt'));
        }

        $backup_index = intval($_POST['backup_index']);
        $result = $this->services['htaccess']->restore_htaccess($backup_index);

        if ($result) {
            mt_send_json_success(__('.htaccess file restored successfully.', 'mt'));
        } else {
            mt_send_json_error(__('Failed to restore .htaccess file.', 'mt'));
        }
    }

    public function ajax_apply_php_preset() {
        if (!mt_can_manage() || !mt_verify_nonce($_POST['nonce'])) {
            mt_send_json_error(__('Permission denied.', 'mt'));
        }

        $preset = sanitize_text_field($_POST['preset']);
        $result = $this->services['php_config']->apply_preset($preset);

        if ($result) {
            update_option('mt_php_preset', $preset);
            mt_send_json_success(__('PHP configuration applied successfully.', 'mt'));
        } else {
            mt_send_json_error(__('Failed to apply PHP configuration.', 'mt'));
        }
    }
}
