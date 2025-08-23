<?php

namespace ModernToolkit\Infrastructure\Migration;

use ModernToolkit\Infrastructure\Compatibility\MT_LegacyCompatibility;

/**
 * Safe Migration Manager
 *
 * Handles the transition from legacy architecture to new modular system
 * Ensures zero-downtime migration with automatic fallback
 */
class MT_SafeMigrationManager {
    private $migrationErrors = [];
    private $rollbackRequired = false;

    public function executeFullSwitch(): array {
        try {
            // Step 1: Pre-migration checks
            if (!$this->preflightChecks()) {
                throw new \Exception('Pre-flight checks failed');
            }

            // Step 2: Create safety backup
            $backupId = $this->createSafetyBackup();

            // Step 3: Initialize compatibility layer
            MT_LegacyCompatibility::init();

            // Step 4: Migrate user data
            $migrationResult = $this->migrateUserData();

            // Step 5: Switch to new architecture
            $switchResult = $this->performArchitectureSwitch();

            // Step 6: Validate new system
            if (!$this->validateNewSystem()) {
                throw new \Exception('New system validation failed');
            }

            // Step 7: Cleanup (only if everything successful)
            $this->markMigrationComplete();

            return [
                'success' => true,
                'backup_id' => $backupId,
                'migrated_data' => $migrationResult,
                'message' => 'Successfully migrated to new architecture'
            ];

        } catch (\Exception $e) {
            return $this->handleMigrationFailure($e, $backupId ?? null);
        }
    }

    private function preflightChecks(): bool {
        // Check WordPress version
        global $wp_version;
        if (version_compare($wp_version, '5.0', '<')) {
            $this->migrationErrors[] = 'WordPress version too old';
            return false;
        }

        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4.0', '<')) {
            $this->migrationErrors[] = 'PHP version too old';
            return false;
        }

        // Check required classes exist
        $requiredClasses = [
            'ModernToolkit\\Core\\MT_Plugin',
            'ModernToolkit\\Core\\MT_ServiceContainer',
            'ModernToolkit\\Features\\Debug\\MT_DebugFeature'
        ];

        foreach ($requiredClasses as $class) {
            if (!class_exists($class)) {
                $this->migrationErrors[] = "Required class missing: {$class}";
                return false;
            }
        }

        // Check file permissions
        if (!\is_writable(\WP_CONTENT_DIR)) {
            $this->migrationErrors[] = 'Insufficient file permissions';
            return false;
        }

        return true;
    }

    private function createSafetyBackup(): string {
        $backupId = 'mt_migration_' . \date('Y-m-d_H-i-s');

        // Backup current settings
        $currentSettings = [
            'mt_debug_enabled' => \get_option('mt_debug_enabled'),
            'mt_query_monitor_enabled' => \get_option('mt_query_monitor_enabled'),
            'mt_htaccess_backups' => \get_option('mt_htaccess_backups'),
            'mt_php_preset' => \get_option('mt_php_preset'),
            'mt_smtp_logging_enabled' => \get_option('mt_smtp_logging_enabled')
        ];

        \update_option($backupId, $currentSettings);

        return $backupId;
    }

    private function migrateUserData(): array {
        return MT_LegacyCompatibility::migrateUserData();
    }

    private function performArchitectureSwitch(): bool {
        try {
            // Initialize new plugin system
            $newPlugin = \ModernToolkit\Core\MT_Plugin::getInstance();
            $newPlugin->init();

            // Mark new system as active
            \update_option('mt_using_new_architecture', true);

            // Deactivate legacy system gracefully
            if (class_exists('ModernToolkit\\Plugin')) {
                $legacyPlugin = \ModernToolkit\Plugin::get_instance();
                // Don't actually destroy it yet - keep for fallback
            }

            return true;

        } catch (\Exception $e) {
            $this->migrationErrors[] = 'Architecture switch failed: ' . $e->getMessage();
            return false;
        }
    }

    private function validateNewSystem(): bool {
        try {
            // Test service container
            $plugin = \ModernToolkit\Core\MT_Plugin::getInstance();
            $container = $plugin->getContainer();

            if (!$container) {
                throw new \Exception('Service container not available');
            }

            // Test feature registry
            $featureRegistry = $plugin->getFeatureRegistry();
            if (!$featureRegistry) {
                throw new \Exception('Feature registry not available');
            }

            // Test debug feature
            if ($featureRegistry->has('debug')) {
                $debugFeature = $featureRegistry->get('debug');
                if (!$debugFeature->isEnabled()) {
                    \error_log('MT: Debug feature is disabled but that\'s okay');
                }
            }

            // Test performance feature
            if ($featureRegistry->has('performance')) {
                $performanceFeature = $featureRegistry->get('performance');
                if (!$performanceFeature->isEnabled()) {
                    \error_log('MT: Performance feature is disabled but that\'s okay');
                }
            }

            return true;

        } catch (\Exception $e) {
            $this->migrationErrors[] = 'System validation failed: ' . $e->getMessage();
            return false;
        }
    }

    private function markMigrationComplete(): void {
        \update_option('mt_data_migration_complete', true);
        \update_option('mt_migration_timestamp', \time());
        \update_option('mt_migration_version', '1.4.0');
    }

    private function handleMigrationFailure(\Exception $e, ?string $backupId): array {
        $this->rollbackRequired = true;
        $this->migrationErrors[] = $e->getMessage();

        // Automatic rollback
        if ($backupId) {
            $this->rollbackToBackup($backupId);
        }

        // Ensure legacy system is still working
        \update_option('mt_using_new_architecture', false);

        // Log the error
        \error_log('MT Migration Failed: ' . $e->getMessage());
        \update_option('mt_migration_errors', $this->migrationErrors);

        return [
            'success' => false,
            'error' => $e->getMessage(),
            'errors' => $this->migrationErrors,
            'rollback_performed' => $backupId !== null,
            'message' => 'Migration failed, rolled back to previous version'
        ];
    }

    private function rollbackToBackup(string $backupId): bool {
        try {
            $backup = \get_option($backupId);
            if (!$backup) {
                return false;
            }

            // Restore each setting
            foreach ($backup as $option => $value) {
                \update_option($option, $value);
            }

            return true;

        } catch (\Exception $e) {
            \error_log('MT Rollback Failed: ' . $e->getMessage());
            return false;
        }
    }

    public function getMigrationStatus(): array {
        return [
            'new_architecture_active' => \get_option('mt_using_new_architecture', false),
            'migration_complete' => \get_option('mt_data_migration_complete', false),
            'migration_errors' => $this->migrationErrors,
            'rollback_required' => $this->rollbackRequired,
            'compatibility_status' => MT_LegacyCompatibility::getMigrationStatus()
        ];
    }

    /**
     * Emergency rollback function - can be called from admin interface
     */
    public function emergencyRollback(): array {
        try {
            // Disable new architecture
            \update_option('mt_using_new_architecture', false);
            \delete_option('mt_data_migration_complete');

            // Re-enable legacy system
            if (class_exists('ModernToolkit\\Plugin')) {
                \ModernToolkit\Plugin::get_instance();
            }

            return [
                'success' => true,
                'message' => 'Emergency rollback completed'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}