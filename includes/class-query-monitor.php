<?php
/**
 * Query Monitor Service - Performance metrics display
 */

if (!defined('ABSPATH')) {
    exit;
}

// WordPress function fallbacks for standalone testing
if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in() { return true; }
}
if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10) { return true; }
}
if (!function_exists('current_user_can')) {
    function current_user_can($capability) { return true; }
}
if (!function_exists('esc_html')) {
    function esc_html($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('esc_attr')) {
    function esc_attr($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('esc_url')) {
    function esc_url($url) { return htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('_e')) {
    function _e($text, $domain = 'default') { echo $text; }
}
if (!function_exists('__')) {
    function __($text, $domain = 'default') { return $text; }
}
if (!function_exists('printf')) {
    function printf($format, ...$args) { echo sprintf($format, ...$args); }
}
if (!function_exists('get_transient')) {
    function get_transient($transient) { return false; }
}
if (!function_exists('set_transient')) {
    function set_transient($transient, $value, $expiration = 0) { return true; }
}
if (!function_exists('get_current_user_id')) {
    function get_current_user_id() { return 1; }
}
if (!function_exists('get_bloginfo')) {
    function get_bloginfo($show = '') { return 'Test Site'; }
}
if (!function_exists('get_option')) {
    function get_option($option, $default = false) { return $default; }
}
if (!function_exists('mt_format_bytes')) {
    function mt_format_bytes($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . 'GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . 'MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . 'KB';
        } else {
            return $bytes . 'B';
        }
    }
}

// Mock global variables for testing
if (!isset($wpdb)) {
    $wpdb = new stdClass();
    $wpdb->num_queries = 0;
    $wpdb->queries = array();
}
if (!isset($wp_scripts)) {
    $wp_scripts = new stdClass();
    $wp_scripts->done = array();
    $wp_scripts->registered = array();
    $wp_scripts->groups = array();
}
if (!isset($wp_styles)) {
    $wp_styles = new stdClass();
    $wp_styles->done = array();
    $wp_styles->registered = array();
}
if (!defined('ABSPATH')) {
    define('ABSPATH', '/var/www/html/');
}
if (!defined('SAVEQUERIES')) {
    define('SAVEQUERIES', false);
}
if (!defined('SCRIPT_DEBUG')) {
    define('SCRIPT_DEBUG', false);
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
     * Get accurate query count using consistent logic
     */
    private function get_accurate_query_count($metrics) {
        global $wpdb;
        
        // Prioritize SAVEQUERIES data for accuracy
        if (defined('SAVEQUERIES') && SAVEQUERIES && !empty($wpdb->queries)) {
            return count($wpdb->queries);
        }
        
        // Fallback to stored metrics
        return isset($metrics['query_count']) ? $metrics['query_count'] : 0;
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

        // Use consistent query count calculation
        $query_count = $this->get_accurate_query_count($metrics);
        $execution_time = isset($metrics['execution_time']) ? $metrics['execution_time'] : 0;
        $peak_memory = isset($metrics['peak_memory']) ? $metrics['peak_memory'] : 0;
        $query_time = isset($metrics['query_time']) ? $metrics['query_time'] : 0;

        $time_formatted = number_format($execution_time, 3) . 's';
        $memory_formatted = $this->mt_format_bytes($peak_memory);
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

        $this->render_performance_bar($metrics);
    }

    /**
     * Render performance details panel for admin bar integration
     */
    private function render_performance_bar($metrics) {
        // Use consistent calculation methods
        $query_count = $this->get_accurate_query_count($metrics);
        $execution_time = isset($metrics['execution_time']) ? $metrics['execution_time'] : 0;
        $peak_memory = isset($metrics['peak_memory']) ? $metrics['peak_memory'] : 0;
        $query_time = isset($metrics['query_time']) ? $metrics['query_time'] : 0;

        $time_formatted = number_format($execution_time, 3) . 's';
        $memory_formatted = $this->mt_format_bytes($peak_memory);
        $db_time_formatted = number_format($query_time * 1000, 1) . 'ms';

        // Get counts for tab titles - use same logic as admin bar
        global $wpdb, $wp_scripts, $wp_styles;
        $tab_query_count = $query_count; // Use consistent query count
        // Scripts count: jumlah script yang sudah di-load oleh WordPress
        $scripts_count = !empty($wp_scripts->done) ? count($wp_scripts->done) : 0;
        // Styles count: jumlah stylesheet yang sudah di-load oleh WordPress
        $styles_count = !empty($wp_styles->done) ? count($wp_styles->done) : 0;
        ?>
        <div id="mt-perf-details" class="mt-perf-details" style="display: none;">
            <div class="mt-perf-details-content">
                <div class="mt-perf-sidebar">
                    <ul class="mt-perf-tabs">
                        <li class="mt-perf-tab active" data-tab="overview">
                            <span class="dashicons dashicons-dashboard"></span>
                            <?php
                            if (function_exists('_e')) {
                                _e('Overview', 'morden-toolkit');
                            } else {
                                echo 'Overview';
                            }
                            ?>
                        </li>
                        <li class="mt-perf-tab" data-tab="queries">
                            <span class="dashicons dashicons-database"></span>
                            <?php
                            if (function_exists('printf') && function_exists('__')) {
                                printf(__('Queries (%d)', 'morden-toolkit'), $tab_query_count);
                            } else {
                                echo 'Queries';
                            }
                            ?>
                        </li>
                        <?php if (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG): ?>
                        <li class="mt-perf-tab" data-tab="scripts">
                            <span class="dashicons dashicons-media-code"></span>
                            <?php
                            if (function_exists('printf') && function_exists('__')) {
                                printf(__('Scripts (%d)', 'morden-toolkit'), $scripts_count);
                            } else {
                                echo 'Scripts';
                            }
                            ?>
                        </li>
                        <li class="mt-perf-tab" data-tab="styles">
                            <span class="dashicons dashicons-admin-appearance"></span>
                            <?php
                            if (function_exists('printf') && function_exists('__')) {
                                printf(__('Styles (%d)', 'morden-toolkit'), $styles_count);
                            } else {
                                echo 'Styles';
                            }
                            ?>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="mt-perf-content">
                    <!-- Overview Tab -->
                    <div id="mt-perf-tab-overview" class="mt-perf-tab-content active">
                        <h4><?php
                        if (function_exists('_e')) {
                            _e('Performance Details', 'morden-toolkit');
                        } else {
                            echo 'Performance Details';
                        }
                        ?></h4>
                        <table class="mt-perf-table">
                            <tr>
                                <td><?php
                                if (function_exists('_e')) {
                                    _e('Database Queries:', 'morden-toolkit');
                                } else {
                                    echo 'Database Queries:';
                                }
                                ?></td>
                                <td><?php echo function_exists('esc_html') ? esc_html($tab_query_count) : htmlspecialchars($tab_query_count); ?></td>
                            </tr>
                            <tr>
                                <td><?php
                                if (function_exists('_e')) {
                                    _e('Execution Time:', 'morden-toolkit');
                                } else {
                                    echo 'Execution Time:';
                                }
                                ?></td>
                                <td><?php echo function_exists('esc_html') ? esc_html($time_formatted) : htmlspecialchars($time_formatted); ?></td>
                            </tr>
                            <tr>
                                <td><?php
                                if (function_exists('_e')) {
                                    _e('Database Time:', 'morden-toolkit');
                                } else {
                                    echo 'Database Time:';
                                }
                                ?></td>
                                <td><?php echo function_exists('esc_html') ? esc_html($db_time_formatted) : htmlspecialchars($db_time_formatted); ?></td>
                            </tr>
                            <tr>
                                <td><?php
                                if (function_exists('_e')) {
                                    _e('Peak Memory:', 'morden-toolkit');
                                } else {
                                    echo 'Peak Memory:';
                                }
                                ?></td>
                                <td><?php echo function_exists('esc_html') ? esc_html($memory_formatted) : htmlspecialchars($memory_formatted); ?></td>
                            </tr>
                            <tr>
                                <td><?php
                                if (function_exists('_e')) {
                                    _e('Memory Used:', 'morden-toolkit');
                                } else {
                                    echo 'Memory Used:';
                                }
                                ?></td>
                                <td><?php echo function_exists('esc_html') ? esc_html(function_exists('mt_format_bytes') ? mt_format_bytes($metrics['memory_usage'] ?? 0) : $this->mt_format_bytes($metrics['memory_usage'] ?? 0)) : htmlspecialchars($this->mt_format_bytes($metrics['memory_usage'] ?? 0)); ?></td>
                            </tr>
                            <tr>
                                <td><?php
                                if (function_exists('_e')) {
                                    _e('PHP Version:', 'morden-toolkit');
                                } else {
                                    echo 'PHP Version:';
                                }
                                ?></td>
                                <td><?php echo function_exists('esc_html') ? esc_html(PHP_VERSION) : htmlspecialchars(PHP_VERSION); ?></td>
                            </tr>
                            <tr>
                                <td><?php
                                if (function_exists('_e')) {
                                    _e('WordPress Version:', 'morden-toolkit');
                                } else {
                                    echo 'WordPress Version:';
                                }
                                ?></td>
                                <td><?php echo function_exists('esc_html') && function_exists('get_bloginfo') ? esc_html(get_bloginfo('version')) : 'N/A'; ?></td>
                            </tr>
                        </table>
                    </div>
                    <!-- Queries Tab -->
                    <div id="mt-perf-tab-queries" class="mt-perf-tab-content">
                        <h4><?php
                        if (function_exists('printf') && function_exists('__')) {
                             printf(__('Database Queries (%d)', 'morden-toolkit'), $tab_query_count);
                         } else {
                             echo 'Database Queries (' . $tab_query_count . ')';
                         }
                        ?></h4>
                        <div class="mt-queries-container">
                            <?php $this->render_queries_tab(); ?>
                        </div>
                    </div>
                    <?php if (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG): ?>
                    <!-- Scripts Tab -->
                    <div id="mt-perf-tab-scripts" class="mt-perf-tab-content">
                        <h4><?php
                        if (function_exists('_e')) {
                            _e('Loaded Scripts', 'morden-toolkit');
                        } else {
                            echo 'Loaded Scripts';
                        }
                        ?></h4>
                        <div class="mt-scripts-container">
                            <?php $this->render_scripts_tab(); ?>
                        </div>
                    </div>
                    <!-- Styles Tab -->
                    <div id="mt-perf-tab-styles" class="mt-perf-tab-content">
                        <h4><?php
                        if (function_exists('_e')) {
                            _e('Loaded Styles', 'morden-toolkit');
                        } else {
                            echo 'Loaded Styles';
                        }
                        ?></h4>
                        <div class="mt-styles-container">
                            <?php $this->render_styles_tab(); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
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
            echo '<p>' . (function_exists('__') ? __('Query logging is not enabled. Add define(\'SAVEQUERIES\', true); to wp-config.php to enable.', 'morden-toolkit') : 'Query logging is not enabled. Add define(\'SAVEQUERIES\', true); to wp-config.php to enable.') . '</p>';
            return;
        }

        if (empty($wpdb->queries)) {
            echo '<p>' . (function_exists('__') ? __('No queries recorded for this page.', 'morden-toolkit') : 'No queries recorded for this page.') . '</p>';
            return;
        }

        echo '<table class="query-log-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>No</th>';
        echo '<th>Time</th>';
        echo '<th>Query</th>';
        echo '<th>Caller Stack</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($wpdb->queries as $index => $query) {
            $sql = $query[0];
            $time = $query[1];
            $stack = $query[2] ?? '';

            $query_type = $this->get_query_type($sql);
            $time_class = $time > 0.05 ? 'slow-query' : '';
            $time_formatted = number_format($time * 1000, 2) . 'ms';

            echo '<tr class="' . esc_attr($time_class) . '" data-query-type="' . esc_attr($query_type) . '">';
            echo '<td class="query-number">' . ($index + 1) . '</td>';
            echo '<td class="query-time">';
            if ($time > 0.05) {
                echo '<span class="slow-indicator" title="Slow Query">⚠️</span> ';
            }
            echo (function_exists('esc_html') ? esc_html($time_formatted) : htmlspecialchars($time_formatted)) . '</td>';
            echo '<td class="query-sql">';
            echo '<div class="sql-container">';
            echo '<code>' . (function_exists('esc_html') ? esc_html($sql) : htmlspecialchars($sql)) . '</code>';
            echo '<button type="button" class="button-link copy-sql" title="Copy SQL">';
            echo '<span class="dashicons dashicons-admin-page"></span>';
            echo '</button>';
            echo '</div>';
            echo '</td>';
            echo '<td class="query-caller">' . (function_exists('esc_html') ? esc_html($stack) : htmlspecialchars($stack)) . '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
    }

    /**
     * Get remote file size with safe measurement
     */
    private function get_remote_file_size($url) {
        // Use cached result if available
        $cache_key = 'mt_file_size_' . md5($url);
        $cached_size = get_transient($cache_key);
        if ($cached_size !== false) {
            return $cached_size;
        }

        // Try to get actual file size with safe timeout
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3); // 3 second timeout
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2); // 2 second connect timeout
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Morden Toolkit Performance Monitor/1.0');

            $headers = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code === 200 && $headers) {
                if (preg_match('/Content-Length:\s*(\d+)/i', $headers, $matches)) {
                    $size_bytes = intval($matches[1]);
                    $formatted_size = function_exists('mt_format_bytes') ? mt_format_bytes($size_bytes) : $this->mt_format_bytes($size_bytes);
                    // Cache for 1 hour
                    if (function_exists('set_transient')) {
                        set_transient($cache_key, $formatted_size, 3600);
                    }
                    return $formatted_size;
                }
            }
        }

        // Fallback to domain-based estimates
        $domain = parse_url($url, PHP_URL_HOST);
        $fallback_size = 'Unknown';

        if (strpos($domain, 'fonts.googleapis.com') !== false) {
            $fallback_size = '~3KB';
        } elseif (strpos($domain, 'fonts.gstatic.com') !== false) {
            $fallback_size = '~25KB';
        } else {
            $fallback_size = '~50KB';
        }

        // Cache fallback for 30 minutes
        if (function_exists('set_transient')) {
            set_transient($cache_key, $fallback_size, 1800);
        }
        return $fallback_size;
    }

    /**
     * Get estimated load time with real measurement
     */
    private function get_estimated_load_time($url, $file_size = null) {
        // Use cached result if available
        $cache_key = 'mt_load_time_' . md5($url);
        $cached_time = get_transient($cache_key);
        if ($cached_time !== false) {
            return $cached_time;
        }

        // For local files, estimate based on file size
        if ($file_size && !filter_var($url, FILTER_VALIDATE_URL)) {
            if ($file_size < 10000) return $this->format_load_time_with_color(5, 'good');
            if ($file_size < 50000) return $this->format_load_time_with_color(25, 'good');
            if ($file_size < 100000) return $this->format_load_time_with_color(75, 'warning');
            if ($file_size < 500000) return $this->format_load_time_with_color(150, 'warning');
            return $this->format_load_time_with_color(300, 'danger');
        }

        // For external files, try real measurement
        if (strpos($url, 'http') === 0 && function_exists('curl_init')) {
            $start_time = microtime(true);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5); // 5 second timeout
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3); // 3 second connect timeout
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Morden Toolkit Performance Monitor/1.0');

            $result = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $end_time = microtime(true);
            $load_time_ms = round(($end_time - $start_time) * 1000);

            if ($http_code === 200) {
                $formatted_time = $this->format_load_time_with_color($load_time_ms, $this->get_load_time_status($load_time_ms));
                // Cache for 30 minutes
                if (function_exists('set_transient')) {
                    set_transient($cache_key, $formatted_time, 1800);
                }
                return $formatted_time;
            }
        }

        // Fallback estimates
        if (strpos($url, 'http') === 0) {
            return $this->format_load_time_with_color(120, 'warning'); // Default external estimate
        }

        return 'N/A';
    }

    /**
     * Get load time status based on milliseconds
     */
    private function get_load_time_status($ms) {
        if ($ms <= 100) return 'good';
        if ($ms <= 300) return 'warning';
        return 'danger';
    }

    /**
     * Format load time with color coding
     */
    private function format_load_time_with_color($ms, $status) {
        $class = 'load-time-' . $status;
        return '<span class="' . $class . '">' . $ms . 'ms</span>';
    }

    /**
     * Get file size status and format with color
     */
    private function format_file_size_with_color($size_str) {
        if (empty($size_str) || $size_str === 'N/A' || $size_str === 'Unknown') {
            return $size_str;
        }

        // Extract numeric value for comparison
        $size_bytes = 0;
        if (preg_match('/(\d+(?:\.\d+)?)\s*(KB|MB|GB|B)/i', $size_str, $matches)) {
            $value = floatval($matches[1]);
            $unit = strtoupper($matches[2]);

            switch ($unit) {
                case 'GB':
                    $size_bytes = $value * 1024 * 1024 * 1024;
                    break;
                case 'MB':
                    $size_bytes = $value * 1024 * 1024;
                    break;
                case 'KB':
                    $size_bytes = $value * 1024;
                    break;
                default:
                    $size_bytes = $value;
            }
        }

        $status = 'good';
        if ($size_bytes > 500000) { // > 500KB
            $status = 'danger';
        } elseif ($size_bytes > 100000) { // > 100KB
            $status = 'warning';
        }

        $class = 'file-size-' . $status;
        return '<span class="' . $class . '">' . $size_str . '</span>';
    }

    /**
     * Format bytes to human readable format
     */
    private function mt_format_bytes($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . 'GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . 'MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . 'KB';
        } else {
            return $bytes . 'B';
        }
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
        echo '<th>File Size</th>';
        echo '<th>Load Time</th>';
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
            $file_size = 'N/A';
            $load_time = 'N/A';

            if ($src) {
                // Handle full URLs
                if (strpos($src, 'http') === 0) {
                    $parsed_url = parse_url($src);
                    if (isset($parsed_url['host'])) {
                        $hostname = $parsed_url['host'];

                        // Check for external sources like Google Fonts
                        if (strpos($hostname, 'fonts.googleapis.com') !== false ||
                            strpos($hostname, 'fonts.gstatic.com') !== false) {
                            $component_type = 'WordPress Core Component (Herald Fonts)';
                        }
                    }
                    $clickable_url = '<a href="' . (function_exists('esc_url') ? esc_url($src) : htmlspecialchars($src)) . '" target="_blank" style="color: #0073aa; text-decoration: none;">' . (function_exists('esc_html') ? esc_html($src) : htmlspecialchars($src)) . '</a>';

                    // Try to get file size for external files (simulated)
                    $file_size = $this->get_remote_file_size($src);
                    $load_time = $this->get_estimated_load_time($src);
                } else {
                    // Handle relative URLs - get site URL
                    $site_url = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
                    $hostname = $site_url;
                    $full_url = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $site_url . $src;
                    $clickable_url = '<a href="' . (function_exists('esc_url') ? esc_url($full_url) : htmlspecialchars($full_url)) . '" target="_blank" style="color: #0073aa; text-decoration: none;">' . (function_exists('esc_html') ? esc_html($src) : htmlspecialchars($src)) . '</a>';

                    // Try to get local file size
                    $local_path = ABSPATH . ltrim($src, '/');
                    if (file_exists($local_path)) {
                        $file_size = function_exists('mt_format_bytes') ? mt_format_bytes(filesize($local_path)) : $this->mt_format_bytes(filesize($local_path));
                        $load_time = $this->get_estimated_load_time($src, filesize($local_path));
                    }
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
            echo '<td>' . (function_exists('esc_html') ? esc_html($position) : htmlspecialchars($position)) . '</td>';
            echo '<td class="mt-script-handle">' . (function_exists('esc_html') ? esc_html($handle) : htmlspecialchars($handle)) . '</td>';
            echo '<td>' . (function_exists('esc_html') ? esc_html($hostname) : htmlspecialchars($hostname)) . '</td>';
            echo '<td class="query-sql"><div class="sql-container"><code>' . $clickable_url . '</code></div></td>';
            echo '<td>' . (function_exists('esc_html') ? esc_html($component_type) : htmlspecialchars($component_type)) . '</td>';
            echo '<td>' . (function_exists('esc_html') ? esc_html($version) : htmlspecialchars($version)) . '</td>';
            echo '<td>' . $this->format_file_size_with_color($file_size) . '</td>';
            echo '<td>' . $load_time . '</td>';
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
        echo '<th>File Size</th>';
        echo '<th>Load Time</th>';
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
            $file_size = 'N/A';
            $load_time = 'N/A';

            if ($src) {
                // Handle full URLs
                if (strpos($src, 'http') === 0) {
                    $parsed_url = parse_url($src);
                    if (isset($parsed_url['host'])) {
                        $hostname = $parsed_url['host'];

                        // Check for external sources like Google Fonts
                        if (strpos($hostname, 'fonts.googleapis.com') !== false ||
                            strpos($hostname, 'fonts.gstatic.com') !== false) {
                            $component_type = 'WordPress Core Component (Herald Fonts)';
                        }
                    }
                    $clickable_url = '<a href="' . (function_exists('esc_url') ? esc_url($src) : htmlspecialchars($src)) . '" target="_blank" style="color: #0073aa; text-decoration: none;">' . (function_exists('esc_html') ? esc_html($src) : htmlspecialchars($src)) . '</a>';

                    // Try to get file size for external files (simulated)
                    $file_size = $this->get_remote_file_size($src);
                    $load_time = $this->get_estimated_load_time($src);
                } else {
                    // Handle relative URLs - get site URL
                    $site_url = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
                    $hostname = $site_url;
                    $full_url = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $site_url . $src;
                    $clickable_url = '<a href="' . (function_exists('esc_url') ? esc_url($full_url) : htmlspecialchars($full_url)) . '" target="_blank" style="color: #0073aa; text-decoration: none;">' . (function_exists('esc_html') ? esc_html($src) : htmlspecialchars($src)) . '</a>';

                    // Try to get local file size
                    $local_path = ABSPATH . ltrim($src, '/');
                    if (file_exists($local_path)) {
                        $file_size = function_exists('mt_format_bytes') ? mt_format_bytes(filesize($local_path)) : $this->mt_format_bytes(filesize($local_path));
                        $load_time = $this->get_estimated_load_time($src, filesize($local_path));
                    }
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
            echo '<td>' . (function_exists('esc_html') ? esc_html($position) : htmlspecialchars($position)) . '</td>';
            echo '<td class="mt-style-handle">' . (function_exists('esc_html') ? esc_html($handle) : htmlspecialchars($handle)) . '</td>';
            echo '<td>' . (function_exists('esc_html') ? esc_html($hostname) : htmlspecialchars($hostname)) . '</td>';
            echo '<td class="query-sql"><div class="sql-container"><code>' . $clickable_url . '</code></div></td>';
            echo '<td>' . (function_exists('esc_html') ? esc_html($component_type) : htmlspecialchars($component_type)) . '</td>';
            echo '<td>' . (function_exists('esc_html') ? esc_html($version) : htmlspecialchars($version)) . '</td>';
            echo '<td>' . $this->format_file_size_with_color($file_size) . '</td>';
            echo '<td>' . $load_time . '</td>';
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
