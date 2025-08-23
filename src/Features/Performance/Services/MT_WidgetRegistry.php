<?php

namespace ModernToolkit\Features\Performance\Services;

use ModernToolkit\Infrastructure\Contracts\MT_WidgetInterface;

class MT_WidgetRegistry {
    private $widgets = [];

    public function register(string $id, MT_WidgetInterface $widget): void {
        $this->widgets[$id] = $widget;
    }

    public function get(string $id): ?MT_WidgetInterface {
        return $this->widgets[$id] ?? null;
    }

    public function getAll(): array {
        return $this->widgets;
    }

    public function getEnabled(): array {
        return array_filter($this->widgets, function(MT_WidgetInterface $widget) {
            return $widget->isEnabled();
        });
    }

    public function getByCategory(string $category): array {
        return array_filter($this->widgets, function(MT_WidgetInterface $widget) use ($category) {
            return $widget->getCategory() === $category && $widget->isEnabled();
        });
    }

    public function renderAll(): array {
        $data = [];

        foreach ($this->getEnabled() as $id => $widget) {
            $data[$id] = $widget->render();
        }

        return $data;
    }
}