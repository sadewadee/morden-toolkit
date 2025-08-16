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
        if (get_option('morden_query_monitor_enabled') && is_user_logged_in()) {
            add_action('init', array($this, 'start_performance_tracking'));
            add_action('wp_footer', array($this, 'display_performance_bar'));
            add_action('admin_footer', array($this, 'display_performance_bar'));
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
     * Display performance bar in frontend
     */
    public function display_performance_bar() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $metrics = $this->get_metrics();

        if (empty($metrics)) {
            return;
        }

        $this->render_performance_bar($metrics);
    }

    /**
     * Render performance bar HTML
     */
    private function render_performance_bar($metrics) {
        $query_count = isset($metrics['query_count']) ? $metrics['query_count'] : 0;
        $execution_time = isset($metrics['execution_time']) ? $metrics['execution_time'] : 0;
        $peak_memory = isset($metrics['peak_memory']) ? $metrics['peak_memory'] : 0;

        $time_formatted = mt_format_time($execution_time);
        $memory_formatted = mt_format_bytes($peak_memory);

        // Determine performance status
        $status_class = $this->get_performance_status_class($execution_time, $query_count, $peak_memory);

        ?>
        <div id="mt-performance-bar" class="mt-performance-bar <?php echo esc_attr($status_class); ?>">
            <div class="mt-perf-container">
                <div class="mt-perf-item">
                    <span class="mt-perf-icon">🔄</span>
                    <span class="mt-perf-value"><?php echo esc_html($query_count); ?></span>
                    <span class="mt-perf-label"><?php _e('queries', 'mt-toolkit'); ?></span>
                </div>
                <div class="mt-perf-item">
                    <span class="mt-perf-icon">⏱️</span>
                    <span class="mt-perf-value"><?php echo esc_html($time_formatted); ?></span>
                    <span class="mt-perf-label"><?php _e('time', 'mt-toolkit'); ?></span>
                </div>
                <div class="mt-perf-item">
                    <span class="mt-perf-icon">💾</span>
                    <span class="mt-perf-value"><?php echo esc_html($memory_formatted); ?></span>
                    <span class="mt-perf-label"><?php _e('memory', 'mt-toolkit'); ?></span>
                </div>
                <div class="mt-perf-toggle">
                    <button type="button" id="mt-perf-details-btn">
                        <span class="dashicons dashicons-info"></span>
                    </button>
                </div>
            </div>

            <div id="mt-perf-details" class="mt-perf-details" style="display: none;">
                <div class="mt-perf-details-content">
                    <h4><?php _e('Performance Details', 'mt-toolkit'); ?></h4>
                    <table class="mt-perf-table">
                        <tr>
                            <td><?php _e('Database Queries:', 'mt-toolkit'); ?></td>
                            <td><?php echo esc_html($query_count); ?></td>
                        </tr>
                        <tr>
                            <td><?php _e('Execution Time:', 'mt-toolkit'); ?></td>
                            <td><?php echo esc_html($time_formatted); ?></td>
                        </tr>
                        <tr>
                            <td><?php _e('Peak Memory:', 'mt-toolkit'); ?></td>
                            <td><?php echo esc_html($memory_formatted); ?></td>
                        </tr>
                        <tr>
                            <td><?php _e('Memory Used:', 'mt-toolkit'); ?></td>
                            <td><?php echo esc_html(mt_format_bytes($metrics['memory_usage'] ?? 0)); ?></td>
                        </tr>
                        <tr>
                            <td><?php _e('PHP Version:', 'mt-toolkit'); ?></td>
                            <td><?php echo esc_html(PHP_VERSION); ?></td>
                        </tr>
                        <tr>
                            <td><?php _e('WordPress Version:', 'mt-toolkit'); ?></td>
                            <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <style>
        .mt-performance-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #23282d;
            color: #ffffff;
            z-index: 99999;
            border-top: 2px solid #0073aa;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            font-size: 13px;
        }

        .mt-perf-container {
            display: flex;
            align-items: center;
            padding: 8px 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .mt-perf-item {
            display: flex;
            align-items: center;
            margin-right: 20px;
        }

        .mt-perf-icon {
            margin-right: 5px;
            font-size: 14px;
        }

        .mt-perf-value {
            font-weight: 600;
            margin-right: 3px;
        }

        .mt-perf-label {
            color: #a0a5aa;
            font-size: 12px;
        }

        .mt-perf-toggle {
            margin-left: auto;
        }

        .mt-perf-toggle button {
            background: none;
            border: none;
            color: #ffffff;
            cursor: pointer;
            padding: 4px;
            border-radius: 3px;
        }

        .mt-perf-toggle button:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .mt-perf-details {
            background: #32373c;
            border-top: 1px solid #464b50;
            padding: 15px 20px;
        }

        .mt-perf-details-content {
            max-width: 1200px;
            margin: 0 auto;
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

        /* Performance status colors */
        .mt-performance-bar.good {
            border-top-color: #00a32a;
        }

        .mt-performance-bar.warning {
            border-top-color: #dba617;
        }

        .mt-performance-bar.poor {
            border-top-color: #d63638;
        }

        /* Admin bar adjustment */
        .admin-bar .mt-performance-bar {
            bottom: 32px;
        }

        @media screen and (max-width: 782px) {
            .admin-bar .mt-performance-bar {
                bottom: 46px;
            }

            .mt-perf-container {
                flex-wrap: wrap;
                padding: 6px 15px;
            }

            .mt-perf-item {
                margin-right: 15px;
                margin-bottom: 2px;
            }
        }
        </style>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var detailsBtn = document.getElementById('mt-perf-details-btn');
            var detailsPanel = document.getElementById('mt-perf-details');

            if (detailsBtn && detailsPanel) {
                detailsBtn.addEventListener('click', function() {
                    if (detailsPanel.style.display === 'none') {
                        detailsPanel.style.display = 'block';
                    } else {
                        detailsPanel.style.display = 'none';
                    }
                });
            }
        });
        </script>
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
