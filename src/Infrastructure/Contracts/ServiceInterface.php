<?php

namespace ModernToolkit\Infrastructure\Contracts;

/**
 * Base interface for all services in the ModernToolkit architecture
 */
interface ServiceInterface {
    /**
     * Initialize the service
     * Called when the service is first created
     */
    public function init(): void;

    /**
     * Get the service name/identifier
     */
    public function getName(): string;

    /**
     * Check if the service is available/enabled
     */
    public function isEnabled(): bool;
}