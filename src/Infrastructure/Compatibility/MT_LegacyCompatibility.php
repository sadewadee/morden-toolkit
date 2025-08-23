<?php

namespace ModernToolkit\Infrastructure\Compatibility;

/**
 * Backward Compatibility Layer
 *
 * Ensures smooth migration from legacy classes to new modular architecture
 * Prevents fatal "Class not found" errors during plugin updates
 */
class MT_LegacyCompatibility {
    private static $initialized = false;

    public static function init(): void {
        if (self::$initialized) {
            return;
        }

        self::registerClassAliases();
        self::registerMethodBridges();
        self::$initialized = true;
    }

    /**
     * Register class aliases for backward compatibility
     */
    private static function registerClassAliases(): void {
        // Core legacy classes
        if (!class_exists('ModernToolkit\\Debug')) {
            class_alias(
                'ModernToolkit\\Features\\Debug\\Services\\MT_DebugManager',
                'ModernToolkit\\Debug'
            );
        }

        if (!class_exists('ModernToolkit\\QueryMonitor')) {
            class_alias(
                'ModernToolkit\\Features\\Performance\\Services\\MT_QueryMonitor',
                'ModernToolkit\\QueryMonitor'
            );
        }

        if (!class_exists('ModernToolkit\\Htaccess')) {
            class_alias(
                'ModernToolkit\\Features\\FileManagement\\Services\\MT_HtaccessManager',
                'ModernToolkit\\Htaccess'
            );
        }

        if (!class_exists('ModernToolkit\\PhpConfig')) {
            class_alias(
                'ModernToolkit\\Features\\Configuration\\Services\\MT_PhpConfigManager',
                'ModernToolkit\\PhpConfig'
            );
        }

        if (!class_exists('ModernToolkit\\SmtpLogger')) {
            class_alias(
                'ModernToolkit\\Features\\EmailLogging\\Services\\MT_SmtpLoggerManager',
                'ModernToolkit\\SmtpLogger'
            );
        }

        if (!class_exists('ModernToolkit\\FileManager')) {
            class_alias(
                'ModernToolkit\\Features\\FileManagement\\Services\\MT_BackupManager',
                'ModernToolkit\\FileManager'
            );
        }

        // Infrastructure utilities compatibility
        if (!class_exists('WPConfigTransformer')) {
            class_alias(
                'ModernToolkit\\Infrastructure\\Utilities\\MT_WPConfigTransformer',
                'WPConfigTransformer'
            );
        }

        if (!class_exists('ModernToolkit\\WpConfigIntegration')) {
            class_alias(
                'ModernToolkit\\Infrastructure\\WordPress\\MT_WpConfigIntegration',
                'ModernToolkit\\WpConfigIntegration'
            );
        }

        // Legacy autoloader compatibility
        if (!class_exists('WpConfigIntegration')) {
            class_alias(
                'ModernToolkit\\Infrastructure\\WordPress\\MT_WpConfigIntegration',
                'WpConfigIntegration'
            );
        }
    }

    /**
     * Register method bridges for API compatibility
     */
    private static function registerMethodBridges(): void {
        // Create global functions that map to new methods
        if (!function_exists('mt_legacy_debug_enable')) {
            function mt_legacy_debug_enable() {
                $container = \ModernToolkit\Core\MT_Plugin::getInstance()->getContainer();
                if ($container->has('debug.manager')) {
                    return $container->get('debug.manager')->enableDebug();
                }
                return false;
            }
        }

        if (!function_exists('mt_legacy_debug_disable')) {
            function mt_legacy_debug_disable() {
                $container = \ModernToolkit\Core\MT_Plugin::getInstance()->getContainer();
                if ($container->has('debug.manager')) {
                    return $container->get('debug.manager')->disableDebug();
                }
                return false;
            }
        }
    }

    /**
     * Data migration from old format to new format
     */
    public static function migrateUserData(): array {
        $migrated = [];

        // Migrate debug settings
        $old_debug = \get_option('mt_debug_enabled', null);
        if ($old_debug !== null) {
            \update_option('mt_feature_debug_enabled', $old_debug);
            $migrated['debug'] = true;
        }

        // Migrate performance settings
        $old_performance = \get_option('mt_query_monitor_enabled', null);
        if ($old_performance !== null) {
            \update_option('mt_feature_performance_enabled', $old_performance);
            $migrated['performance'] = true;
        }

        // Migrate file management settings
        $old_backups = \get_option('mt_htaccess_backups', null);
        if ($old_backups !== null) {
            \update_option('mt_feature_file_backups', $old_backups);
            $migrated['file_management'] = true;
        }

        return $migrated;
    }

    /**
     * Check if legacy classes are still being used
     */
    public static function isLegacyInUse(): bool {
        // Check if old plugin class is instantiated
        if (class_exists('ModernToolkit\\Plugin')) {
            $reflection = new \ReflectionClass('ModernToolkit\\Plugin');
            $instance = $reflection->getProperty('instance');
            $instance->setAccessible(true);
            return $instance->getValue() !== null;
        }

        return false;
    }

    /**
     * Generate migration report
     */
    public static function getMigrationStatus(): array {
        return [
            'compatibility_loaded' => self::$initialized,
            'legacy_in_use' => self::isLegacyInUse(),
            'new_architecture_loaded' => class_exists('ModernToolkit\\Core\\MT_Plugin'),
            'data_migrated' => \get_option('mt_data_migration_complete', false),
            'migration_errors' => \get_option('mt_migration_errors', [])
        ];
    }
}