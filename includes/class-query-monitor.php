<?php
/**
 * Query Monitor Service - Performance metrics display
 */

if (!defined('ABSPATH')) {
    exit;
}

class MT_Query_Monitor {

    private $metrics = array();

    public function __construct() {
        if (get_option('mt_query_monitor_enabled') && is_user_logged_in()) {
            add_action('init', array($this, 'start_performance_tracking'));
            add_action('admin_bar_menu', array($this, 'add_admin_bar_metrics'), 999);
        }
    }

    public function start_performance_tracking() {
        $this->metrics['start_time'] = microtime(true);
        $this->metrics['start_memory'] = memory_get_usage();


        add_action('shutdown', array($this, 'capture_final_metrics'), 0);
    }

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

        ?>        <div id="mt-perf-details" class="mt-perf-details" style="display: none;">            <div class="mt-perf-details-content">                <div class="mt-perf-sidebar">                    <ul class="mt-perf-tabs">                        <li class="mt-perf-tab active" data-tab="overview">                            <span class="dashicons dashicons-dashboard"></span>                            <?php _e('Overview', 'morden-toolkit'); ?>                        </li>                        <li class="mt-perf-tab" data-tab="queries">                            <span class="dashicons dashicons-database"></span>                            <?php _e('Queries', 'morden-toolkit'); ?>                        </li>                        <?php if (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG): ?>                        <li class="mt-perf-tab" data-tab="scripts">                            <span class="dashicons dashicons-media-code"></span>                            <?php _e('Scripts', 'morden-toolkit'); ?>                        </li>                        <li class="mt-perf-tab" data-tab="styles">                            <span class="dashicons dashicons-admin-appearance"></span>                            <?php _e('Styles', 'morden-toolkit'); ?>                        </li>                        <?php endif; ?>                    </ul>                </div>                <div class="mt-perf-content">                    <!-- Overview Tab -->                    <div id="mt-perf-tab-overview" class="mt-perf-tab-content active">                        <h4><?php _e('Performance Details', 'morden-toolkit'); ?></h4>                        <table class="mt-perf-table">                            <tr>                                <td><?php _e('Database Queries:', 'morden-toolkit'); ?></td>                                <td><?php echo esc_html($query_count); ?></td>                            </tr>                            <tr>                                <td><?php _e('Execution Time:', 'morden-toolkit'); ?></td>                                <td><?php echo esc_html($time_formatted); ?></td>                            </tr>                            <tr>                                <td><?php _e('Peak Memory:', 'morden-toolkit'); ?></td>                                <td><?php echo esc_html($memory_formatted); ?></td>                            </tr>                            <tr>                                <td><?php _e('Memory Used:', 'morden-toolkit'); ?></td>                                <td><?php echo esc_html(mt_format_bytes($metrics['memory_usage'] ?? 0)); ?></td>                            </tr>                            <tr>                                <td><?php _e('PHP Version:', 'morden-toolkit'); ?></td>                                <td><?php echo esc_html(PHP_VERSION); ?></td>                            </tr>                            <tr>                                <td><?php _e('WordPress Version:', 'morden-toolkit'); ?></td>                                <td><?php echo esc_html(get_bloginfo('version')); ?></td>                            </tr>                        </table>                    </div>                    <!-- Queries Tab -->                    <div id="mt-perf-tab-queries" class="mt-perf-tab-content">                        <h4><?php _e('Database Queries', 'morden-toolkit'); ?></h4>                        <div class="mt-queries-container">                            <?php $this->render_queries_tab(); ?>                        </div>                    </div>                    <?php if (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG): ?>                    <!-- Scripts Tab -->                    <div id="mt-perf-tab-scripts" class="mt-perf-tab-content">                        <h4><?php _e('Loaded Scripts', 'morden-toolkit'); ?></h4>                        <div class="mt-scripts-container">                            <?php $this->render_scripts_tab(); ?>                        </div>                    </div>                    <!-- Styles Tab -->                    <div id="mt-perf-tab-styles" class="mt-perf-tab-content">                        <h4><?php _e('Loaded Styles', 'morden-toolkit'); ?></h4>                        <div class="mt-styles-container">                            <?php $this->render_styles_tab(); ?>                        </div>                    </div>                    <?php endif; ?>                </div>            </div>        </div>

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

    /**
     * Render queries tab content
     */
    private function render_queries_tab() {
        global $wpdb;

        if (!defined('SAVEQUERIES') || !SAVEQUERIES) {
            echo '<p>' . __('Query logging is not enabled. Add define(\'SAVEQUERIES\', true); to wp-config.php to enable.', 'morden-toolkit') . '</p>';
            return;
        }

        if (empty($wpdb->queries)) {
            echo '<p>' . __('No queries recorded for this page.', 'morden-toolkit') . '</p>';
            return;
        }

        echo '<div class="mt-queries-list">';
        foreach ($wpdb->queries as $index => $query) {
            $sql = $query[0];
            $time = $query[1];
            $stack = $query[2] ?? '';

            $query_type = $this->get_query_type($sql);
            $time_class = $time > 0.05 ? 'slow' : ($time > 0.01 ? 'medium' : 'fast');

            echo '<div class="mt-query-item ' . esc_attr($time_class) . '">';
            echo '<div class="mt-query-header">';
            echo '<span class="mt-query-type">' . esc_html($query_type) . '</span>';
            echo '<span class="mt-query-time">' . esc_html(number_format($time * 1000, 2)) . 'ms</span>';
            echo '</div>';
            echo '<div class="mt-query-sql">' . esc_html($sql) . '</div>';
            if (!empty($stack)) {
                echo '<div class="mt-query-stack">' . esc_html($stack) . '</div>';
            }
            echo '</div>';
        }
        echo '</div>';
    }

    /**
     * Render scripts tab content
     */
    private function render_scripts_tab() {
        global $wp_scripts;

        if (!$wp_scripts || empty($wp_scripts->done)) {
            echo '<p>' . __('No scripts loaded on this page.', 'morden-toolkit') . '</p>';
            return;
        }

        echo '<table class="query-log-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>No</th>';
        echo '<th>Position</th>';
        echo '<th>Handle</th>';
        echo '<th>Hostname</th>';
        echo '<th>Source</th>';
        echo '<th>Komponen</th>';
        echo '<th>Version</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        $counter = 1;
        foreach ($wp_scripts->done as $handle) {
            if (!isset($wp_scripts->registered[$handle])) continue;

            $script = $wp_scripts->registered[$handle];
            $src = $script->src;
            $version = $script->ver ? $script->ver : 'N/A';

            // Determine position
            $position = 'footer';
            if (isset($wp_scripts->groups[$handle]) && $wp_scripts->groups[$handle] === 0) {
                $position = 'header';
            }

            // Parse hostname and source info
            $hostname = 'Local';
            $source_path = $src;
            $component_type = 'WordPress Core';
            $clickable_url = $src;

            if ($src) {
                // Handle full URLs
                if (strpos($src, 'http') === 0) {
                    $parsed_url = parse_url($src);
                    if (isset($parsed_url['host'])) {
                        $hostname = $parsed_url['host'];
                    }
                    $clickable_url = '<a href="' . esc_url($src) . '" target="_blank" style="color: #0073aa; text-decoration: none;">' . esc_html($src) . '</a>';
                } else {
                    // Handle relative URLs - get site URL
                    $site_url = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
                    $hostname = $site_url;
                    $full_url = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $site_url . $src;
                    $clickable_url = '<a href="' . esc_url($full_url) . '" target="_blank" style="color: #0073aa; text-decoration: none;">' . esc_html($src) . '</a>';
                }

                if (strpos($src, '/plugins/') !== false) {
                    $component_type = 'Plugin';
                    preg_match('/\/plugins\/([^\/]+)/', $src, $matches);
                    if (isset($matches[1])) {
                        $component_type = 'Plugin: ' . ucwords(str_replace('-', ' ', $matches[1]));
                    }
                } elseif (strpos($src, '/themes/') !== false) {
                    $component_type = 'Theme';
                    preg_match('/\/themes\/([^\/]+)/', $src, $matches);
                    if (isset($matches[1])) {
                        $component_type = 'Theme: ' . ucwords(str_replace('-', ' ', $matches[1]));
                    }
                } elseif (strpos($src, '/wp-includes/') !== false || strpos($src, '/wp-admin/') !== false) {
                    $component_type = 'WordPress Core';
                }
            }

            echo '<tr>';
            echo '<td class="query-number">' . $counter . '</td>';
            echo '<td>' . esc_html($position) . '</td>';
            echo '<td class="mt-script-handle">' . esc_html($handle) . '</td>';
            echo '<td>' . esc_html($hostname) . '</td>';
            echo '<td class="query-sql"><div class="sql-container"><code>' . $clickable_url . '</code></div></td>';
            echo '<td>' . esc_html($component_type) . '</td>';
            echo '<td>' . esc_html($version) . '</td>';
            echo '</tr>';

            $counter++;
        }

        echo '</tbody>';
        echo '</table>';
    }

    /**
     * Render styles tab content
     */
    private function render_styles_tab() {
        global $wp_styles;

        if (!$wp_styles || empty($wp_styles->done)) {
            echo '<p>' . __('No styles loaded on this page.', 'morden-toolkit') . '</p>';
            return;
        }

        echo '<table class="query-log-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>No</th>';
        echo '<th>Position</th>';
        echo '<th>Handle</th>';
        echo '<th>Hostname</th>';
        echo '<th>Source</th>';
        echo '<th>Komponen</th>';
        echo '<th>Version</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        $counter = 1;
        foreach ($wp_styles->done as $handle) {
            if (!isset($wp_styles->registered[$handle])) continue;

            $style = $wp_styles->registered[$handle];
            $src = $style->src;
            $version = $style->ver ? $style->ver : 'N/A';
            $media = $style->args;

            // Determine position (CSS is typically in header)
            $position = 'header';

            // Parse hostname and source info
            $hostname = 'Local';
            $source_path = $src;
            $component_type = 'WordPress Core';
            $clickable_url = $src;

            if ($src) {
                // Handle full URLs
                if (strpos($src, 'http') === 0) {
                    $parsed_url = parse_url($src);
                    if (isset($parsed_url['host'])) {
                        $hostname = $parsed_url['host'];
                    }
                    $clickable_url = '<a href="' . esc_url($src) . '" target="_blank" style="color: #0073aa; text-decoration: none;">' . esc_html($src) . '</a>';
                } else {
                    // Handle relative URLs - get site URL
                    $site_url = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
                    $hostname = $site_url;
                    $full_url = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $site_url . $src;
                    $clickable_url = '<a href="' . esc_url($full_url) . '" target="_blank" style="color: #0073aa; text-decoration: none;">' . esc_html($src) . '</a>';
                }

                if (strpos($src, '/plugins/') !== false) {
                    $component_type = 'Plugin';
                    preg_match('/\/plugins\/([^\/]+)/', $src, $matches);
                    if (isset($matches[1])) {
                        $component_type = 'Plugin: ' . ucwords(str_replace('-', ' ', $matches[1]));
                    }
                } elseif (strpos($src, '/themes/') !== false) {
                    $component_type = 'Theme';
                    preg_match('/\/themes\/([^\/]+)/', $src, $matches);
                    if (isset($matches[1])) {
                        $component_type = 'Theme: ' . ucwords(str_replace('-', ' ', $matches[1]));
                    }
                } elseif (strpos($src, '/wp-includes/') !== false || strpos($src, '/wp-admin/') !== false) {
                    $component_type = 'WordPress Core';
                }
            }

            echo '<tr>';
            echo '<td class="query-number">' . $counter . '</td>';
            echo '<td>' . esc_html($position) . '</td>';
            echo '<td class="mt-style-handle">' . esc_html($handle) . '</td>';
            echo '<td>' . esc_html($hostname) . '</td>';
            echo '<td class="query-sql"><div class="sql-container"><code>' . $clickable_url . '</code></div></td>';
            echo '<td>' . esc_html($component_type) . '</td>';
            echo '<td>' . esc_html($version) . '</td>';
            echo '</tr>';

            $counter++;
        }

        echo '</tbody>';
        echo '</table>';
    }

    /**
     * Get query type from SQL
     */
    private function get_query_type($sql) {
        $sql = trim(strtoupper($sql));

        if (strpos($sql, 'SELECT') === 0) return 'SELECT';
        if (strpos($sql, 'INSERT') === 0) return 'INSERT';
        if (strpos($sql, 'UPDATE') === 0) return 'UPDATE';
        if (strpos($sql, 'DELETE') === 0) return 'DELETE';
        if (strpos($sql, 'SHOW') === 0) return 'SHOW';
        if (strpos($sql, 'DESCRIBE') === 0) return 'DESCRIBE';

        return 'OTHER';
    }
}
