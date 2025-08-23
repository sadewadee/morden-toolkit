<?php
/**
 * Plugin Name: Morden Toolkit
 * Plugin URI: https://github.com/sadewadee/morden-toolkit
 * Description: Lightweight developer tools for WordPress: Debug Manager, Query Monitor, Htaccess Editor, PHP Config presets.
 * Version: 1.4.0
 * Author: Morden Team
 * Author URI: https://mordenhost.com
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: morden-toolkit
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.6
 * Requires PHP: 7.4
 *
 * This plugin includes third-party components:
 * - WPConfigTransformer (MIT License) - See LICENSES.md for details
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MT_VERSION', '1.4.0');
define('MT_PLUGIN_FILE', __FILE__);
define('MT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MT_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('MT_SMTP_LOGGING_ENABLED', get_option('mt_smtp_logging_enabled', false));

function mt_load_textdomain() {
    load_plugin_textdomain(
        'morden-toolkit',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages/'
    );
}
add_action('plugins_loaded', 'mt_load_textdomain');

require_once MT_PLUGIN_DIR . 'includes/autoloader.php';
require_once MT_PLUGIN_DIR . 'includes/helpers.php';
require_once MT_PLUGIN_DIR . 'includes/WPConfigTransformer.php';

// Load new modular architecture
require_once MT_PLUGIN_DIR . 'src/Core/Bootstrap/MT_Autoloader.php';
require_once MT_PLUGIN_DIR . 'src/Core/MT_ServiceContainer.php';
require_once MT_PLUGIN_DIR . 'src/Core/MT_EventDispatcher.php';
require_once MT_PLUGIN_DIR . 'src/Core/MT_FeatureRegistry.php';
require_once MT_PLUGIN_DIR . 'src/Core/MT_Plugin.php';
require_once MT_PLUGIN_DIR . 'src/Infrastructure/Contracts/MT_FeatureInterface.php';
require_once MT_PLUGIN_DIR . 'src/Features/MT_AbstractFeature.php';
require_once MT_PLUGIN_DIR . 'src/Infrastructure/Compatibility/MT_LegacyCompatibility.php';
require_once MT_PLUGIN_DIR . 'src/Infrastructure/Migration/MT_SafeMigrationManager.php';

// Load Infrastructure utilities
require_once MT_PLUGIN_DIR . 'src/Infrastructure/Utilities/MT_WPConfigTransformer.php';
require_once MT_PLUGIN_DIR . 'src/Infrastructure/WordPress/MT_WpConfigIntegration.php';

// Setup PSR-4 autoloader for new architecture
$autoloader = ModernToolkit\Core\Bootstrap\MT_Autoloader::getInstance();
$autoloader->addNamespace('ModernToolkit', MT_PLUGIN_DIR . 'src/');
$autoloader->register();

function mt_init() {
    // Force use of new architecture - eliminate dual system approach
    update_option('mt_using_new_architecture', true);

    // Enable query monitor by default if not already set
    if (get_option('mt_query_monitor_enabled', null) === null) {
        update_option('mt_query_monitor_enabled', true);
    }

    try {
        // Initialize compatibility layer first
        ModernToolkit\Infrastructure\Compatibility\MT_LegacyCompatibility::init();

        // Initialize new modular system
        $plugin = ModernToolkit\Core\MT_Plugin::getInstance();
        $plugin->init();

        // Log successful initialization
    } catch (Exception $e) {
        // Log error but don't fallback to legacy system
        error_log('MT: New architecture initialization error: ' . $e->getMessage());

        // Still try to set the option to prevent legacy system from loading
        update_option('mt_using_new_architecture', true);

        // Show admin notice about the error
        add_action('admin_notices', function() use ($e) {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>Morden Toolkit Error:</strong> ' . esc_html($e->getMessage());
            echo '</p></div>';
        });
    }
}
add_action('plugins_loaded', 'mt_init');

// Note: PHP configuration is now handled through MT_PHP_Config class
// which uses proper methods (.htaccess, .user.ini, wp-config.php constants)
// instead of ini_set() which doesn't work on many hosting providers

register_activation_hook(__FILE__, function() {
    if (!get_option('mt_debug_enabled')) {
        add_option('mt_debug_enabled', false);
    }


    $old_query_monitor = get_option('morden_query_monitor_enabled');
    if ($old_query_monitor !== false) {
        update_option('mt_query_monitor_enabled', $old_query_monitor);
        delete_option('morden_query_monitor_enabled');
    } elseif (!get_option('mt_query_monitor_enabled')) {
        add_option('mt_query_monitor_enabled', false);
    }

    if (!get_option('mt_htaccess_backups')) {
        add_option('mt_htaccess_backups', array());
    }


    $old_php_preset = get_option('morden_php_preset');
    if ($old_php_preset !== false) {
        update_option('mt_php_preset', $old_php_preset);
        delete_option('morden_php_preset');
    } elseif (!get_option('mt_php_preset')) {
        add_option('mt_php_preset', 'medium');
    }

    // Initialize SMTP logging option
    if (!get_option('mt_smtp_logging_enabled')) {
        add_option('mt_smtp_logging_enabled', false);
    }
});

register_deactivation_hook(__FILE__, function() {
    if (get_option('mt_debug_enabled')) {
        $debug_service = new ModernToolkit\Debug();
        $debug_service->disable_debug();
        update_option('mt_debug_enabled', false);
    }
});
