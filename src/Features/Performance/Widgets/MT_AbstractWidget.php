<?php

namespace ModernToolkit\Features\Performance\Widgets;

use ModernToolkit\Infrastructure\Contracts\MT_WidgetInterface;

abstract class MT_AbstractWidget implements MT_WidgetInterface {
    protected $id;
    protected $name;
    protected $description;
    protected $category = 'performance';
    protected $priority = 10;
    protected $enabled = true;
    protected $cacheTimeout = 30;
    protected $cachedData = null;
    protected $cacheExpiry = null;

    public function getCategory(): string {
        return $this->category;
    }

    public function getPriority(): int {
        return $this->priority;
    }

    public function isEnabled(): bool {
        return $this->enabled;
    }

    public function render(): array {
        if (!$this->isEnabled()) {
            return [];
        }

        if ($this->isCacheValid()) {
            return $this->cachedData;
        }

        $data = $this->generateData();
        $this->cacheData($data);

        return $data;
    }

    public function getRealTimeData(): array {
        return $this->generateData();
    }

    public function getScripts(): array {
        return [];
    }

    public function getStyles(): array {
        return [];
    }

    abstract protected function generateData(): array;

    protected function formatBytes(int $size): string {
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

    protected function formatDuration(float $seconds): string {
        if ($seconds < 0.001) {
            return round($seconds * 1000000, 3) . ' μs';
        } elseif ($seconds < 1) {
            return round($seconds * 1000, 3) . ' ms';
        } else {
            return round($seconds, 3) . ' s';
        }
    }

    private function isCacheValid(): bool {
        return $this->cachedData !== null &&
               $this->cacheExpiry !== null &&
               time() < $this->cacheExpiry;
    }

    private function cacheData(array $data): void {
        $this->cachedData = $data;
        $this->cacheExpiry = time() + $this->cacheTimeout;
    }
}