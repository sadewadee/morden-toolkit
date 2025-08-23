<?php

namespace ModernToolkit\Features;

use ModernToolkit\Infrastructure\Contracts\MT_FeatureInterface;
use ModernToolkit\Core\MT_ServiceContainer;
use ModernToolkit\Core\MT_EventDispatcher;

abstract class MT_AbstractFeature implements MT_FeatureInterface {
    protected $container;
    protected $eventDispatcher;
    protected $config = [];
    protected $enabled = true;
    public $booted = false;

    public function __construct(?MT_ServiceContainer $container = null, ?MT_EventDispatcher $eventDispatcher = null) {
        $this->container = $container;
        $this->eventDispatcher = $eventDispatcher;
        $this->loadConfig();
    }

    public function getVersion(): string {
        return '1.0.0';
    }

    public function getDependencies(): array {
        return [];
    }

    public function getServices(): array {
        return [];
    }

    public function boot(): void {
        if ($this->booted) {
            return;
        }

        $this->registerServices();
        $this->registerHooks();
        $this->initializeComponents();

        $this->booted = true;

        $this->eventDispatcher?->dispatch('feature.booted', [
            'feature' => $this,
            'id' => $this->getId()
        ]);
    }

    public function activate(): void {
        $this->enabled = true;
        $this->onActivate();

        $this->eventDispatcher?->dispatch('feature.activated', [
            'feature' => $this,
            'id' => $this->getId()
        ]);
    }

    public function deactivate(): void {
        $this->enabled = false;
        $this->onDeactivate();

        $this->eventDispatcher?->dispatch('feature.deactivated', [
            'feature' => $this,
            'id' => $this->getId()
        ]);
    }

    public function isEnabled(): bool {
        return $this->enabled;
    }

    public function getConfig(): array {
        return $this->config;
    }

    protected function registerServices(): void {
        foreach ($this->getServices() as $serviceId => $serviceConfig) {
            if ($this->container) {
                $this->container->bind(
                    $serviceId,
                    $serviceConfig['concrete'],
                    $serviceConfig['options'] ?? []
                );
            }
        }
    }

    protected function registerHooks(): void {
        // Override in child classes
    }

    protected function initializeComponents(): void {
        // Override in child classes
    }

    protected function onActivate(): void {
        // Override in child classes
    }

    protected function onDeactivate(): void {
        // Override in child classes
    }

    protected function loadConfig(): void {
        $configFile = $this->getConfigPath();
        if (file_exists($configFile)) {
            $this->config = include $configFile;
        }
    }

    protected function getConfigPath(): string {
        $featureDir = dirname((new \ReflectionClass($this))->getFileName());
        return $featureDir . '/config.php';
    }
}