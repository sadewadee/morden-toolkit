<?php

namespace ModernToolkit\Features\Configuration;

use ModernToolkit\Features\MT_AbstractFeature;
use ModernToolkit\Core\MT_ServiceContainer;
use ModernToolkit\Core\MT_EventDispatcher;

class MT_ConfigurationFeature extends MT_AbstractFeature {

    public function __construct(MT_ServiceContainer $container, MT_EventDispatcher $eventDispatcher) {
        parent::__construct($container, $eventDispatcher);
    }

    public function getId(): string {
        return 'configuration';
    }

    public function getName(): string {
        return 'PHP Configuration';
    }

    public function getDescription(): string {
        return 'PHP configuration management with preset system';
    }

    public function getDependencies(): array {
        return [];
    }

    public function boot(): void {
        $this->registerServices();
        $this->registerHooks();
    }

    protected function registerServices(): void {
        $this->container->singleton('configuration.php_config', function() {
            return new Services\MT_PhpConfigManager();
        });
    }

    protected function registerHooks(): void {
        if (\is_admin()) {
            // Configuration AJAX endpoints are handled by main MT_Plugin compatibility layer
            \add_action('admin_init', [$this, 'initConfiguration']);
        }
    }

    public function initConfiguration(): void {
        // Initialize any configuration-specific settings
        $this->ensureDefaultOptions();
    }

    private function ensureDefaultOptions(): void {
        // Ensure default PHP preset is set
        if (!\get_option('mt_php_preset')) {
            \add_option('mt_php_preset', 'medium');
        }

        // Ensure custom preset settings exist
        if (!\get_option('mt_custom_preset_settings')) {
            $default_custom_settings = [
                'memory_limit' => '256M',
                'upload_max_filesize' => '64M',
                'post_max_size' => '64M',
                'max_execution_time' => '300',
                'max_input_vars' => '3000',
                'max_input_time' => '300'
            ];
            \add_option('mt_custom_preset_settings', $default_custom_settings);
        }
    }
}