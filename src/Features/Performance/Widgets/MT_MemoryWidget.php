<?php

namespace ModernToolkit\Features\Performance\Widgets;

class MT_MemoryWidget extends MT_AbstractWidget {
    protected $id = 'memory';
    protected $name = 'Memory Usage';
    protected $description = 'Memory usage statistics and limits';

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
        $memoryUsage = memory_get_usage();
        $peakMemory = memory_get_peak_usage();
        $memoryLimit = $this->getMemoryLimit();

        $usagePercentage = $memoryLimit > 0 ? ($memoryUsage / $memoryLimit) * 100 : 0;
        $peakPercentage = $memoryLimit > 0 ? ($peakMemory / $memoryLimit) * 100 : 0;

        return [
            'current_usage' => $memoryUsage,
            'formatted_current' => $this->formatBytes($memoryUsage),
            'peak_usage' => $peakMemory,
            'formatted_peak' => $this->formatBytes($peakMemory),
            'memory_limit' => $memoryLimit,
            'formatted_limit' => $this->formatBytes($memoryLimit),
            'usage_percentage' => round($usagePercentage, 2),
            'peak_percentage' => round($peakPercentage, 2),
            'status' => $this->determineStatus($usagePercentage)
        ];
    }

    protected function getMemoryLimit(): int {
        $limit = ini_get('memory_limit');

        if ($limit === '-1') {
            return 0; // Unlimited
        }

        return $this->parseMemoryLimit($limit);
    }

    protected function parseMemoryLimit(string $limit): int {
        $limit = trim($limit);
        $lastChar = strtolower($limit[strlen($limit) - 1]);
        $size = (int) $limit;

        switch ($lastChar) {
            case 'g':
                $size *= 1024;
            case 'm':
                $size *= 1024;
            case 'k':
                $size *= 1024;
        }

        return $size;
    }

    protected function determineStatus(float $usagePercentage): string {
        if ($usagePercentage > 90) {
            return 'critical';
        }

        if ($usagePercentage > 75) {
            return 'warning';
        }

        return 'good';
    }
}