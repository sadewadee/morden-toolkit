<?php
/**
 * Query Monitor Service - Performance metrics display
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MT_Query_Monitor {

    /**
     * Performance metrics
     */
    private $metrics = array();

    /**
     * Constructor
     */
    public function __construct() {
        if (get_option('mt_query_monitor_enabled') && is_user_logged_in()) {
            add_action('init', array($this, 'start_performance_tracking'));
            add_action('admin_bar_menu', array($this, 'add_admin_bar_metrics'), 999);
        }
    }

    /**
     * Start performance tracking
     */
    public function start_performance_tracking() {
        $this->metrics['start_time'] = microtime(true);
        $this->metrics['start_memory'] = memory_get_usage();

        // Hook into shutdown to capture final metrics
        add_action('shutdown', array($this, 'capture_final_metrics'), 0);
    }

    /**
     * Capture final performance metrics
     */
    public function capture_final_metrics() {
        global $wpdb;

        $this->metrics['end_time'] = microtime(true);
        $this->metrics['end_memory'] = memory_get_peak_usage();
        $this->metrics['execution_time'] = $this->metrics['end_time'] - $this->metrics['start_time'];
        $this->metrics['memory_usage'] = $this->metrics['end_memory'] - $this->metrics['start_memory'];
        $this->metrics['peak_memory'] = memory_get_peak_usage();
        $this->metrics['query_count'] = $wpdb->num_queries;

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

        $time_formatted = mt_format_time($execution_time);
        $memory_formatted = mt_format_bytes($peak_memory);

        // Format like Query Monitor: time, memory, database time, queries
        $label_content = sprintf(
            '%s&nbsp;&nbsp;%s&nbsp;&nbsp;%s<small>Q</small>',
            esc_html($time_formatted),
            esc_html($memory_formatted),
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

        $this->render_performance_bar($metrics);
    }

    /**
     * Render performance details panel for admin bar integration
     */
    private function render_performance_bar($metrics) {
        $query_count = isset($metrics['query_count']) ? $metrics['query_count'] : 0;
        $execution_time = isset($metrics['execution_time']) ? $metrics['execution_time'] : 0;
        $peak_memory = isset($metrics['peak_memory']) ? $metrics['peak_memory'] : 0;

        $time_formatted = mt_format_time($execution_time);
        $memory_formatted = mt_format_bytes($peak_memory);

        ?>
        <div id="mt-perf-details" class="mt-perf-details" style="display: none;">
            <div class="mt-perf-details-content">
                <h4><?php _e('Performance Details', 'mt'); ?></h4>
                <table class="mt-perf-table">
                    <tr>
                        <td><?php _e('Database Queries:', 'mt'); ?></td>
                        <td><?php echo esc_html($query_count); ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('Execution Time:', 'mt'); ?></td>
                        <td><?php echo esc_html($time_formatted); ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('Peak Memory:', 'mt'); ?></td>
                        <td><?php echo esc_html($memory_formatted); ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('Memory Used:', 'mt'); ?></td>
                        <td><?php echo esc_html(mt_format_bytes($metrics['memory_usage'] ?? 0)); ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('PHP Version:', 'mt'); ?></td>
                        <td><?php echo esc_html(PHP_VERSION); ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('WordPress Version:', 'mt'); ?></td>
                        <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <style>
        /* Admin bar MT performance styling - similar to Query Monitor */
        #wp-admin-bar-mt-performance-monitor .ab-icon {
            background-color: #0073aa !important;
            color: #fff !important;
            font-weight: bold !important;
            width: auto !important;
            padding: 0 6px !important;
            border-radius: 2px !important;
            margin-right: 6px !important;
            font-size: 11px !important;
            line-height: 20px !important;
        }

        #wp-admin-bar-mt-performance-monitor .ab-icon {
            display: none !important;
        }

        #wp-admin-bar-mt-performance-monitor .ab-label small {
            font-size: 9px !important;
            font-weight: normal !important;
        }

        #wp-admin-bar-mt-performance-monitor:hover .ab-icon {
            background-color: #005a87 !important;
        }

        #wp-admin-bar-mt-performance-monitor.menupop .ab-item {
            cursor: pointer !important;
        }

        .mt-perf-details {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #32373c;
            color: #ffffff;
            z-index: 99999;
            border-top: 2px solid #0073aa;

            font-size: 13px;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
        }

        .mt-perf-details-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 15px 20px;
        }

        .mt-perf-details h4 {
            margin: 0 0 10px 0;
            color: #ffffff;
            font-size: 14px;
        }

        .mt-perf-table {
            width: 100%;
            max-width: 400px;
        }

        .mt-perf-table td {
            padding: 3px 0;
            color: #a0a5aa;
        }

        .mt-perf-table td:first-child {
            width: 40%;
        }

        .mt-perf-table td:last-child {
            color: #ffffff;
            font-weight: 500;
        }
        </style>
        <?php
    }

    /**
     * Get performance status class based on metrics
     */
    private function get_performance_status_class($execution_time, $query_count, $memory) {
        // Define thresholds
        $time_warning = 2.0; // seconds
        $time_poor = 5.0;
        $query_warning = 50;
        $query_poor = 100;
        $memory_warning = 64 * 1024 * 1024; // 64MB
        $memory_poor = 128 * 1024 * 1024; // 128MB

        $issues = 0;

        if ($execution_time > $time_poor || $query_count > $query_poor || $memory > $memory_poor) {
            return 'poor';
        }

        if ($execution_time > $time_warning || $query_count > $query_warning || $memory > $memory_warning) {
            return 'warning';
        }

        return 'good';
    }

    /**
     * Get detailed query information
     */
    public function get_query_details() {
        global $wpdb;

        if (!defined('SAVEQUERIES') || !SAVEQUERIES) {
            return array();
        }

        $queries = array();

        foreach ($wpdb->queries as $query) {
            $queries[] = array(
                'sql' => $query[0],
                'time' => $query[1],
                'stack' => $query[2] ?? ''
            );
        }

        return $queries;
    }
}
