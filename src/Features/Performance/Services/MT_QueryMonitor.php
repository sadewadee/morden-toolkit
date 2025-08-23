<?php

namespace ModernToolkit\Features\Performance\Services;

class MT_QueryMonitor {
    private $queries = [];
    private $totalTime = 0;

    public function init(): void {
        \add_action('admin_bar_init', [$this, 'setupAdminBar']);
    }

    public function setupAdminBar(): void {
        if (!\current_user_can('manage_options')) {
            return;
        }

        \add_action('wp_before_admin_bar_render', [$this, 'addAdminBarStyles']);
    }

    public function addAdminBarStyles(): void {
        echo '<style>
            .mt-perf-indicator { padding: 0 8px; }
            .mt-perf-good { color: #00a32a; }
            .mt-perf-warning { color: #dba617; }
            .mt-perf-critical { color: #d63638; }
        </style>';
    }

    public function getQueryLogs(): array {
        global $wpdb;

        if (!defined('SAVEQUERIES') || !SAVEQUERIES || empty($wpdb->queries)) {
            return [
                'queries' => [],
                'total_time' => 0,
                'total_queries' => 0
            ];
        }

        $queries = [];
        $totalTime = 0;

        foreach ($wpdb->queries as $query) {
            $time = floatval($query[1]);
            $totalTime += $time;

            $queries[] = [
                'sql' => $query[0],
                'time' => $time,
                'formatted_time' => $this->formatDuration($time),
                'stack' => $query[2] ?? '',
                'is_slow' => $time > 1.0
            ];
        }

        usort($queries, function($a, $b) {
            return $b['time'] <=> $a['time'];
        });

        return [
            'queries' => array_slice($queries, 0, 50),
            'total_time' => $totalTime,
            'formatted_total_time' => $this->formatDuration($totalTime),
            'total_queries' => count($wpdb->queries),
            'slow_queries' => count(array_filter($queries, function($q) {
                return $q['is_slow'];
            }))
        ];
    }

    private function formatDuration(float $seconds): string {
        if ($seconds < 0.001) {
            return round($seconds * 1000000, 3) . ' μs';
        } elseif ($seconds < 1) {
            return round($seconds * 1000, 3) . ' ms';
        } else {
            return round($seconds, 3) . ' s';
        }
    }
}