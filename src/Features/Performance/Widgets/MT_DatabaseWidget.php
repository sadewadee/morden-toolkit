<?php

namespace ModernToolkit\Features\Performance\Widgets;

class MT_DatabaseWidget extends MT_AbstractWidget {
    protected $id = 'database';
    protected $name = 'Database Performance';
    protected $description = 'Database query performance and statistics';

    public function getId(): string {
        return $this->id;
    }

    public function getName(): string {
        return $this->name;
    }

    public function getDescription(): string {
        return $this->description;
    }

    protected function generateData(): array {
        global $wpdb;

        $data = [
            'total_queries' => 0,
            'query_time' => 0,
            'slow_queries' => 0,
            'status' => 'good'
        ];

        if (defined('SAVEQUERIES') && SAVEQUERIES && !empty($wpdb->queries)) {
            $data = $this->analyzeQueries($wpdb->queries);
        }

        $data['database_size'] = $this->getDatabaseSize();
        $data['status'] = $this->determineStatus($data);

        return $data;
    }

    protected function analyzeQueries(array $queries): array {
        $totalTime = 0;
        $slowQueries = 0;
        $slowThreshold = 1.0;

        foreach ($queries as $query) {
            $time = floatval($query[1]);
            $totalTime += $time;

            if ($time > $slowThreshold) {
                $slowQueries++;
            }
        }

        return [
            'total_queries' => count($queries),
            'query_time' => $totalTime,
            'formatted_time' => $this->formatDuration($totalTime),
            'slow_queries' => $slowQueries,
            'avg_query_time' => count($queries) > 0 ? $totalTime / count($queries) : 0
        ];
    }

    protected function getDatabaseSize(): array {
        global $wpdb;

        $tables = $wpdb->get_results("SHOW TABLE STATUS", ARRAY_A);
        $totalSize = 0;
        $totalRows = 0;

        foreach ($tables as $table) {
            $totalSize += $table['Data_length'] + $table['Index_length'];
            $totalRows += $table['Rows'];
        }

        return [
            'size' => $totalSize,
            'formatted_size' => $this->formatBytes($totalSize),
            'total_rows' => $totalRows,
            'table_count' => count($tables)
        ];
    }

    protected function determineStatus(array $data): string {
        if ($data['slow_queries'] > 10 || $data['query_time'] > 2.0) {
            return 'critical';
        }

        if ($data['slow_queries'] > 5 || $data['query_time'] > 1.0) {
            return 'warning';
        }

        return 'good';
    }
}