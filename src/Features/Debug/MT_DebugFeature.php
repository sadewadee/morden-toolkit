<?php

namespace ModernToolkit\Features\Debug;

use ModernToolkit\Features\MT_AbstractFeature;

class MT_DebugFeature extends MT_AbstractFeature {
    public function getId(): string {
        return 'debug';
    }

    public function getName(): string {
        return \__('Debug Management', 'morden-toolkit');
    }

    public function getDescription(): string {
        return \__('WordPress debug configuration and log management', 'morden-toolkit');
    }

    public function getServices(): array {
        return [
            'debug.manager' => [
                'concrete' => 'ModernToolkit\\Features\\Debug\\Services\\MT_DebugManager',
                'options' => ['singleton' => true]
            ],
            'debug.log_viewer' => [
                'concrete' => 'ModernToolkit\\Features\\Debug\\Services\\MT_LogViewer',
                'options' => ['singleton' => true]
            ]
        ];
    }

    protected function registerHooks(): void {
        if (\is_admin()) {
            // Debug logs are integrated into the main toolkit page
            // No separate menu needed
            \add_action('wp_ajax_mt_clear_debug_log', [$this, 'handleClearLog']);
            \add_action('wp_ajax_mt_get_debug_log', [$this, 'handleGetLog']);
            \add_action('admin_enqueue_scripts', [$this, 'enqueueScripts']);
        }
    }

    public function enqueueScripts(string $hook): void {
        // JavaScript is handled by main MT_Plugin admin.js
        // No additional scripts needed for debug functionality
    }

    public function handleClearLog(): void {
        \check_ajax_referer('mt_debug_nonce', 'nonce');

        if (!\current_user_can('manage_options')) {
            \wp_die('Unauthorized');
        }

        $logViewer = $this->container->get('debug.log_viewer');
        $result = $logViewer->clearLog();

        \wp_send_json($result);
    }

    public function handleGetLog(): void {
        \check_ajax_referer('mt_debug_nonce', 'nonce');

        if (!\current_user_can('manage_options')) {
            \wp_die('Unauthorized');
        }

        $logViewer = $this->container->get('debug.log_viewer');
        $result = $logViewer->getLog();

        \wp_send_json($result);
    }
}