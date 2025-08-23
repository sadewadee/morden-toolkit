<?php

namespace ModernToolkit\Core;

use ModernToolkit\Infrastructure\Contracts\MT_FeatureInterface;

class MT_FeatureRegistry {
    private $features = [];
    private $container;
    private $eventDispatcher;

    public function __construct(MT_ServiceContainer $container, ?MT_EventDispatcher $eventDispatcher = null) {
        $this->container = $container;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function register(string $id, MT_FeatureInterface $feature): void {
        $this->features[$id] = $feature;

        $this->eventDispatcher?->dispatch('feature.registered', [
            'feature' => $feature,
            'id' => $id
        ]);
    }

    public function get(string $id): ?MT_FeatureInterface {
        return $this->features[$id] ?? null;
    }

    public function has(string $id): bool {
        return isset($this->features[$id]);
    }

    public function getAll(): array {
        return $this->features;
    }

    public function getEnabled(): array {
        return array_filter($this->features, function(MT_FeatureInterface $feature) {
            return $feature->isEnabled();
        });
    }

    public function bootAll(): void {
        foreach ($this->features as $feature) {
            if ($feature->isEnabled() && !$this->isBooted($feature)) {
                $this->bootFeature($feature);
            }
        }
    }

    public function bootFeature(MT_FeatureInterface $feature): void {
        $dependencies = $feature->getDependencies();

        foreach ($dependencies as $dependency) {
            if (!$this->has($dependency)) {
                throw new \Exception("Feature dependency '{$dependency}' not found.");
            }

            $dependentFeature = $this->get($dependency);
            if (!$this->isBooted($dependentFeature)) {
                $this->bootFeature($dependentFeature);
            }
        }

        $feature->boot();
    }

    public function activateFeature(string $id): bool {
        if (!$this->has($id)) {
            return false;
        }

        $feature = $this->get($id);
        $feature->activate();

        return true;
    }

    public function deactivateFeature(string $id): bool {
        if (!$this->has($id)) {
            return false;
        }

        $feature = $this->get($id);
        $feature->deactivate();

        return true;
    }

    private function isBooted(MT_FeatureInterface $feature): bool {
        return property_exists($feature, 'booted') && $feature->booted === true;
    }
}