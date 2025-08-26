<?php
/**
 * Query Monitor Class
 *
 * Provides performance monitoring and debugging capabilities
 * similar to Query Monitor plugin but lightweight and integrated
 *
 * @package ModernToolkit
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MT_Query_Monitor {

    private $metrics = array();
    private $hook_collectors = array();
    private $executed_hooks = array();
    private $bootstrap_snapshots = array();
    private $domain_collectors = array();
    private $real_time_hooks = array();
    private $hook_execution_order = 0;

    public function __construct() {
        if (get_option('mt_query_monitor_enabled') && is_user_logged_in()) {
            // Initialize domain collectors
            $this->init_domain_collectors();

            // TEMPORARILY DISABLED: Start real-time hook monitoring immediately
            // $this->start_realtime_hook_monitoring();

            // TEMPORARILY DISABLED: Capture bootstrap snapshots at various phases
            // $this->capture_bootstrap_snapshots();

            add_action('init', array($this, 'start_performance_tracking'));
            add_action('admin_bar_menu', array($this, 'add_admin_bar_metrics'), 999);
        }
    }

    /**
     * Initialize domain-specific collectors similar to Query Monitor
     */
    private function init_domain_collectors() {
        $this->domain_collectors = array(
            'database' => array(
                'hooks' => array(),
                'queries' => array(),
                'transactions' => array()
            ),
            'http' => array(
                'hooks' => array(),
                'requests' => array(),
                'responses' => array()
            ),
            'rewrite' => array(
                'hooks' => array(),
                'rules' => array(),
                'queries' => array()
            ),
            'template' => array(
                'hooks' => array(),
                'hierarchy' => array(),
                'includes' => array()
            ),
            'capabilities' => array(
                'hooks' => array(),
                'checks' => array(),
                'roles' => array()
            ),
            'cache' => array(
                'hooks' => array(),
                'operations' => array(),
                'hits_misses' => array()
            ),
            'assets' => array(
                'hooks' => array(),
                'scripts' => array(),
                'styles' => array()
            )
        );
    }

    /**
     * Start selective hook monitoring instead of resource-intensive 'all' hook
     */
    private function start_realtime_hook_monitoring() {
        // Instead of monitoring 'all', monitor strategic hooks only
        $strategic_hooks = $this->get_strategic_hooks();

        foreach ($strategic_hooks as $hook) {
            add_action($hook, array($this, 'capture_strategic_hook'), -999999);
            add_filter($hook, array($this, 'capture_strategic_hook'), -999999);
        }

        // Track hook registration changes at key phases only
        add_filter('wp_loaded', array($this, 'capture_wp_loaded_hooks'), -1);
        add_action('wp_head', array($this, 'capture_wp_head_hooks'), -1);
        add_action('wp_footer', array($this, 'capture_wp_footer_hooks'), -1);

        // Enable AJAX endpoint for continued monitoring after page load
        add_action('wp_ajax_mt_monitor_hooks', array($this, 'ajax_monitor_hooks'));
        add_action('wp_ajax_nopriv_mt_monitor_hooks', array($this, 'ajax_monitor_hooks'));
    }

    /**
     * Capture bootstrap snapshots at different WordPress phases
     */
    private function capture_bootstrap_snapshots() {
        // Capture at various bootstrap phases
        add_action('muplugins_loaded', array($this, 'snapshot_muplugins_phase'), -999999);
        add_action('plugins_loaded', array($this, 'snapshot_plugins_phase'), -999999);
        add_action('setup_theme', array($this, 'snapshot_theme_setup_phase'), -999999);
        add_action('after_setup_theme', array($this, 'snapshot_after_theme_setup_phase'), -999999);
        add_action('init', array($this, 'snapshot_init_phase'), -999999);
        add_action('wp_loaded', array($this, 'snapshot_wp_loaded_phase'), -999999);
        add_action('parse_request', array($this, 'snapshot_parse_request_phase'), -999999);
        add_action('send_headers', array($this, 'snapshot_send_headers_phase'), -999999);
        add_action('wp', array($this, 'snapshot_wp_phase'), -999999);
    }

    /**
     * Get strategic hooks to monitor (much more selective than 'all')
     */
    private function get_strategic_hooks() {
        return array(
            // Core WordPress lifecycle - most important for debugging
            'muplugins_loaded', 'plugins_loaded', 'setup_theme', 'after_setup_theme',
            'init', 'wp_loaded', 'parse_request', 'wp', 'template_redirect',

            // Template and theming - key performance points
            'wp_head', 'wp_footer', 'wp_enqueue_scripts', 'wp_print_styles',
            'get_header', 'get_footer', 'get_sidebar',

            // Content and queries - database performance
            'pre_get_posts', 'the_posts', 'the_content', 'the_excerpt',
            'wp_insert_post', 'save_post', 'publish_post',

            // User and authentication - security monitoring
            'wp_login', 'wp_logout', 'user_register', 'profile_update',
            'wp_authenticate',

            // Admin and AJAX - backend performance
            'admin_init', 'admin_menu', 'admin_enqueue_scripts',

            // Critical performance hooks
            'shutdown', 'wp_footer', 'admin_footer',

            // Error and debugging
            'wp_die_handler', 'wp_redirect', 'wp_safe_redirect',

            // Plugin/theme specific
            'activated_plugin', 'deactivated_plugin', 'switch_theme'
        );
    }

    public function start_performance_tracking() {
        $this->metrics['start_time'] = microtime(true);
        $this->metrics['start_memory'] = memory_get_usage();
        add_action('shutdown', array($this, 'capture_final_metrics'), 0);
    }

    public function capture_final_metrics() {
        global $wpdb;

        // Initialize $wpdb if not available
        if (!isset($wpdb)) {
            $wpdb = new stdClass();
            $wpdb->num_queries = 0;
            $wpdb->queries = array();
        }

        $this->metrics['end_time'] = microtime(true);
        $this->metrics['end_memory'] = memory_get_peak_usage();
        $this->metrics['execution_time'] = $this->metrics['end_time'] - $this->metrics['start_time'];
        $this->metrics['memory_usage'] = $this->metrics['end_memory'] - $this->metrics['start_memory'];
        $this->metrics['peak_memory'] = memory_get_peak_usage();

        // Use more accurate query count calculation
        if (defined('SAVEQUERIES') && SAVEQUERIES && !empty($wpdb->queries)) {
            $this->metrics['query_count'] = count($wpdb->queries);
            // Calculate total query time for more accurate performance measurement
            $total_query_time = 0;
            foreach ($wpdb->queries as $query) {
                $total_query_time += $query[1];
            }
            $this->metrics['query_time'] = $total_query_time;
        } else {
            $this->metrics['query_count'] = $wpdb->num_queries;
            $this->metrics['query_time'] = 0;
        }

        // Store metrics in transient for display
        set_transient('mt_metrics_' . get_current_user_id(), $this->metrics, 30);
    }

    /**
     * Get current metrics
     */
    public function get_metrics() {
        $cached_metrics = get_transient('mt_metrics_' . get_current_user_id());
        if ($cached_metrics) {
            return $cached_metrics;
        }
        return $this->metrics;
    }

    /**
     * Add performance metrics to admin bar
     */
    public function add_admin_bar_metrics($wp_admin_bar) {
        if (!current_user_can('manage_options')) {
            return;
        }

        $metrics = $this->get_metrics();
        if (empty($metrics)) {
            return;
        }

        $query_count = isset($metrics['query_count']) ? $metrics['query_count'] : 0;
        $execution_time = isset($metrics['execution_time']) ? $metrics['execution_time'] : 0;
        $peak_memory = isset($metrics['peak_memory']) ? $metrics['peak_memory'] : 0;
        $query_time = isset($metrics['query_time']) ? $metrics['query_time'] : 0;

        $time_formatted = number_format($execution_time, 3) . 's';
        $memory_formatted = size_format($peak_memory);
        $db_time_formatted = number_format($query_time * 1000, 1) . 'ms';

        // Format like Query Monitor: time, memory, database time, queries
        $label_content = sprintf(
            '%s&nbsp;&nbsp;%s&nbsp;&nbsp;%s&nbsp;&nbsp;%s<small>Q</small>',
            esc_html($time_formatted),
            esc_html($memory_formatted),
            esc_html($db_time_formatted),
            esc_html($query_count)
        );

        // Add single admin bar item with all metrics
        $wp_admin_bar->add_node(array(
            'id'    => 'mt-performance-monitor',
            'title' => '<span class="ab-icon">MT</span><span class="ab-label">' . $label_content . '</span>',
            'href'  => '#',
            'meta'  => array(
                'class' => 'menupop mt-admin-perf-toggle',
                'onclick' => 'return false;'
            )
        ));

        // Render details panel immediately when admin bar is rendered
        add_action('wp_footer', array($this, 'render_details_panel'), 9999);
        add_action('admin_footer', array($this, 'render_details_panel'), 9999);
    }

    /**
     * Render only the details panel for admin bar integration
     */
    public function render_details_panel() {
        static $rendered = false;

        // Prevent multiple renders
        if ($rendered) {
            return;
        }
        $rendered = true;

        $metrics = $this->get_metrics();
        if (empty($metrics)) {
            return;
        }

        echo '<div id="mt-perf-details" class="mt-perf-details" style="display: none;">';
        echo '<div class="mt-perf-details-content">';
        echo '<div class="mt-perf-sidebar">';
        echo '<ul class="mt-perf-tabs">';
        echo '<li class="mt-perf-tab active" data-tab="overview">Overview</li>';
        echo '</ul>';
        echo '</div>';
        echo '<div class="mt-perf-content">';
        echo '<div id="mt-perf-tab-overview" class="mt-perf-tab-content active">';
        echo '<h4>Performance Details</h4>';
        echo '<table class="mt-perf-table">';
        echo '<tr><td>Database Queries:</td><td>' . esc_html($metrics['query_count'] ?? 0) . '</td></tr>';
        echo '<tr><td>Execution Time:</td><td>' . esc_html(number_format($metrics['execution_time'] ?? 0, 3)) . 's</td></tr>';
        echo '<tr><td>Peak Memory:</td><td>' . esc_html(size_format($metrics['peak_memory'] ?? 0)) . '</td></tr>';
        echo '</table>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        // Add basic CSS
        echo '<style>';
        echo '.mt-perf-details { position: fixed; bottom: 0; left: 0; right: 0; background: #32373c; color: #ffffff; z-index: 99999; border-top: 2px solid #0073aa; font-size: 13px; box-shadow: 0 -2px 10px rgba(0,0,0,0.1); }';
        echo '.mt-perf-details-content { display: flex; }';
        echo '.mt-perf-sidebar { width: 200px; background: #23282d; }';
        echo '.mt-perf-content { flex: 1; padding: 20px; }';
        echo '.mt-perf-table { width: 100%; }';
        echo '.mt-perf-table td { padding: 3px 0; color: #a0a5aa; }';
        echo '.mt-perf-table td:last-child { color: #ffffff; font-weight: 500; }';
        echo '</style>';

        // Add basic JavaScript
        echo '<script>';
        echo 'document.addEventListener("DOMContentLoaded", function() {';
        echo '  var adminBarItem = document.getElementById("wp-admin-bar-mt-performance-monitor");';
        echo '  var perfDetails = document.getElementById("mt-perf-details");';
        echo '  if (adminBarItem && perfDetails) {';
        echo '    adminBarItem.addEventListener("click", function(e) {';
        echo '      e.preventDefault();';
        echo '      perfDetails.style.display = perfDetails.style.display === "none" ? "block" : "none";';
        echo '    });';
        echo '  }';
        echo '});';
        echo '</script>';
    }
}

// Initialize the query monitor
if (get_option('mt_query_monitor_enabled')) {
    new MT_Query_Monitor();
}
