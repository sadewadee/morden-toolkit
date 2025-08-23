<?php

if (!defined('ABSPATH')) {
    exit;
}

spl_autoload_register(function ($class) {
    $prefix = 'ModernToolkit\\';
    $base_dir = MT_PLUGIN_DIR . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);

    // Map class names to file names
    $class_map = array(
        'Plugin' => 'class-plugin.php',
        'Debug' => 'class-debug.php',
        'QueryMonitor' => 'class-query-monitor.php',
        'Htaccess' => 'class-htaccess.php',
        'PhpConfig' => 'class-php-config.php',
        'FileManager' => 'class-file-manager.php',
        'SmtpLogger' => 'class-smtp-logger.php',
        'WpConfigIntegration' => 'class-wp-config-integration.php'
    );

    if (isset($class_map[$relative_class])) {
        $file = $base_dir . $class_map[$relative_class];
    } else {
        // Fallback to default naming convention
        $file = $base_dir . 'class-' . strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $relative_class)) . '.php';
    }

    if (file_exists($file)) {
        require $file;

        // Add backward compatibility aliases after class is loaded
        // Note: MT_Plugin alias removed - use ModernToolkit\Core\MT_Plugin::getInstance() instead
        if ($relative_class === 'Debug' && class_exists('ModernToolkit\Debug')) {
            class_alias('ModernToolkit\Debug', 'MT_Debug');
        }
        if ($relative_class === 'QueryMonitor' && class_exists('ModernToolkit\QueryMonitor')) {
            class_alias('ModernToolkit\QueryMonitor', 'MT_Query_Monitor');
        }
        if ($relative_class === 'Htaccess' && class_exists('ModernToolkit\Htaccess')) {
            class_alias('ModernToolkit\Htaccess', 'MT_Htaccess');
        }
        if ($relative_class === 'PhpConfig' && class_exists('ModernToolkit\PhpConfig')) {
            class_alias('ModernToolkit\PhpConfig', 'MT_PHP_Config');
        }
        if ($relative_class === 'FileManager' && class_exists('ModernToolkit\FileManager')) {
            class_alias('ModernToolkit\FileManager', 'MT_File_Manager');
        }
        if ($relative_class === 'SmtpLogger' && class_exists('ModernToolkit\SmtpLogger')) {
            class_alias('ModernToolkit\SmtpLogger', 'MT_SMTP_Logger');
        }
        if ($relative_class === 'WpConfigIntegration' && class_exists('ModernToolkit\WpConfigIntegration')) {
            class_alias('ModernToolkit\WpConfigIntegration', 'MT_WP_Config_Integration');
        }
    } else {
        // Fallback to new modular architecture for WpConfigIntegration
        if ($relative_class === 'WpConfigIntegration') {
            $new_file = MT_PLUGIN_DIR . 'src/Infrastructure/WordPress/MT_WpConfigIntegration.php';
            if (file_exists($new_file)) {
                require_once $new_file;
                if (class_exists('ModernToolkit\\Infrastructure\\WordPress\\MT_WpConfigIntegration')) {
                    class_alias('ModernToolkit\\Infrastructure\\WordPress\\MT_WpConfigIntegration', 'ModernToolkit\\WpConfigIntegration');
                    class_alias('ModernToolkit\\WpConfigIntegration', 'MT_WP_Config_Integration');
                }
            }
        }
    }
});