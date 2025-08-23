<?php

namespace ModernToolkit\Features\Performance\Services;

class MT_MetricsCollector {
    private $startTime;
    private $startMemory;

    public function __construct() {
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage();
    }

    public function collect(): void {
        // Metrics are collected on demand
    }

    public function getMetrics(): array {
        global $wpdb;

        $executionTime = microtime(true) - $this->startTime;
        $memoryUsage = memory_get_usage() - $this->startMemory;
        $peakMemory = memory_get_peak_usage();

        $queryCount = 0;
        $queryTime = 0;

        if (defined('SAVEQUERIES') && SAVEQUERIES && !empty($wpdb->queries)) {
            $queryCount = count($wpdb->queries);
            foreach ($wpdb->queries as $query) {
                $queryTime += floatval($query[1]);
            }
        }

        $status = $this->determineStatus($executionTime, $memoryUsage, $queryCount);

        return [
            'execution_time' => $executionTime,
            'formatted_execution_time' => $this->formatDuration($executionTime),
            'memory_usage' => $memoryUsage,
            'formatted_memory_usage' => $this->formatBytes($memoryUsage),
            'peak_memory' => $peakMemory,
            'formatted_peak_memory' => $this->formatBytes($peakMemory),
            'query_count' => $queryCount,
            'query_time' => $queryTime,
            'formatted_query_time' => $this->formatDuration($queryTime),
            'status' => $status
        ];
    }

    private function determineStatus(float $executionTime, int $memoryUsage, int $queryCount): string {
        if ($executionTime > 3.0 || $memoryUsage > 50 * 1024 * 1024 || $queryCount > 100) {
            return 'critical';
        }

        if ($executionTime > 1.5 || $memoryUsage > 25 * 1024 * 1024 || $queryCount > 50) {
            return 'warning';
        }

        return 'good';
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

    private function formatBytes(int $size): string {
        if ($size === 0 || $size < 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $base = log($size, 1024);

        // Handle potential NAN or invalid values
        if (!is_finite($base) || $base < 0) {
            return '0 B';
        }

        $unitIndex = (int) floor($base);
        if ($unitIndex >= count($units)) {
            $unitIndex = count($units) - 1;
        }

        return round(pow(1024, $base - floor($base)), 2) . ' ' . $units[$unitIndex];
    }
}