<?php

namespace ModernToolkit\Infrastructure\Contracts;

interface MT_FeatureInterface {
    public function getId(): string;
    public function getName(): string;
    public function getDescription(): string;
    public function getVersion(): string;
    public function getDependencies(): array;
    public function getServices(): array;
    public function boot(): void;
    public function activate(): void;
    public function deactivate(): void;
    public function isEnabled(): bool;
    public function getConfig(): array;
}