<?php

namespace ModernToolkit\Infrastructure\Contracts;

interface MT_WidgetInterface {
    public function getId(): string;
    public function getName(): string;
    public function getDescription(): string;
    public function getCategory(): string;
    public function getPriority(): int;
    public function isEnabled(): bool;
    public function render(): array;
    public function getRealTimeData(): array;
    public function getScripts(): array;
    public function getStyles(): array;
}