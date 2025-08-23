<?php

namespace ModernToolkit\Features\Performance;

use ModernToolkit\Features\MT_AbstractFeature;

class MT_PerformanceFeature extends MT_AbstractFeature {
    public function getId(): string {
        return 'performance';
    }

    public function getName(): string {
        return \__('Performance Monitoring', 'morden-toolkit');
    }

    public function getDescription(): string {
        return \__('Real-time performance monitoring and query analysis', 'morden-toolkit');
    }

    public function getServices(): array {
        return [
            'performance.query_monitor' => [
                'concrete' => 'ModernToolkit\\Features\\Performance\\Services\\MT_QueryMonitor',
                'options' => ['singleton' => true]
            ],
            'performance.metrics_collector' => [
                'concrete' => 'ModernToolkit\\Features\\Performance\\Services\\MT_MetricsCollector',
                'options' => ['singleton' => true]
            ],
            'performance.widget_registry' => [
                'concrete' => 'ModernToolkit\\Features\\Performance\\Services\\MT_WidgetRegistry',
                'options' => ['singleton' => true]
            ]
        ];
    }

    protected function initializeComponents(): void {
        $this->registerDefaultWidgets();
        $this->initializePerformanceBar();
    }

    protected function registerDefaultWidgets(): void {
        $widgetRegistry = $this->container->get('performance.widget_registry');

        $defaultWidgets = [
            'database' => 'ModernToolkit\\Features\\Performance\\Widgets\\MT_DatabaseWidget',
            'memory' => 'ModernToolkit\\Features\\Performance\\Widgets\\MT_MemoryWidget'
        ];

        foreach ($defaultWidgets as $id => $class) {
            if (class_exists($class)) {
                $widget = new $class();
                $widgetRegistry->register($id, $widget);
            }
        }
    }

    protected function initializePerformanceBar(): void {
        if (\get_option('mt_query_monitor_enabled', false)) {
            $queryMonitor = $this->container->get('performance.query_monitor');
            $queryMonitor->init();
        }
    }

    protected function registerHooks(): void {
        if (\is_admin()) {
            \add_action('admin_bar_menu', [$this, 'addAdminBarMenu'], 999);
            // Note: mt_get_performance_data and mt_get_query_logs are handled by main MT_Plugin compatibility layer
            \add_action('admin_enqueue_scripts', [$this, 'enqueueScripts']);
        }

        \add_action('shutdown', [$this, 'collectMetrics'], 999);
    }

    public function addAdminBarMenu($wp_admin_bar): void {
        if (!\current_user_can('manage_options')) {
            return;
        }

        $metricsCollector = $this->container->get('performance.metrics_collector');
        $metrics = $metricsCollector->getMetrics();

        $wp_admin_bar->add_menu([
            'id' => 'mt-performance',
            'title' => sprintf(
                '<span class="mt-perf-indicator mt-perf-%s">Performance: %s</span>',
                $metrics['status'],
                ucfirst($metrics['status'])
            ),
            'href' => \admin_url('tools.php?page=mt-debug-logs')
        ]);
    }

    // Note: getPerformanceData and getQueryLogs are now handled by main MT_Plugin compatibility layer
    // This ensures consistent nonce handling across the entire plugin

    public function collectMetrics(): void {
        $metricsCollector = $this->container->get('performance.metrics_collector');
        $metricsCollector->collect();
    }

    public function enqueueScripts(): void {
        // JavaScript is handled by main MT_Plugin admin.js
        // No additional scripts needed for performance functionality
    }
}