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
        add_action('wp_ajax_mt_clear_debug_log', array($this, 'ajax_clear_debug_log'));
        add_action('wp_ajax_mt_get_debug_log', array($this, 'ajax_get_debug_log'));
        add_action('wp_ajax_mt_toggle_query_monitor', array($this, 'ajax_toggle_query_monitor'));
        add_action('wp_ajax_mt_save_htaccess', array($this, 'ajax_save_htaccess'));
        add_action('wp_ajax_mt_restore_htaccess', array($this, 'ajax_restore_htaccess'));
        add_action('wp_ajax_mt_apply_php_preset', array($this, 'ajax_apply_php_preset'));
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
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if (!in_array($hook, array('tools_page_mt', 'tools_page_mt-logs'))) {
            return;
        }

        wp_enqueue_style(
            'mt-admin',
            MT_PLUGIN_URL . 'admin/assets/admin.css',
            array(),
            MT_VERSION
        );

        wp_enqueue_script(
            'mt-admin',
            MT_PLUGIN_URL . 'admin/assets/admin.js',
            array('jquery'),
            MT_VERSION,
            true
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
        if (!get_option('mt_query_monitor_enabled') || !is_user_logged_in()) {
            return;
        }

        wp_enqueue_style(
            'mt-performance-bar',
            MT_PLUGIN_URL . 'public/assets/performance-bar.css',
            array(),
            MT_VERSION
        );

        wp_enqueue_script(
            'mt-performance-bar',
            MT_PLUGIN_URL . 'public/assets/performance-bar.js',
            array(),
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

    public function ajax_toggle_query_monitor() {
        if (!mt_can_manage() || !mt_verify_nonce($_POST['nonce'])) {
            mt_send_json_error(__('Permission denied.', 'mt'));
        }

        $enabled = isset($_POST['enabled']) && $_POST['enabled'] === 'true';
        update_option('morden_query_monitor_enabled', $enabled);

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
            update_option('morden_php_preset', $preset);
            mt_send_json_success(__('PHP configuration applied successfully.', 'mt'));
        } else {
            mt_send_json_error(__('Failed to apply PHP configuration.', 'mt'));
        }
    }
}
