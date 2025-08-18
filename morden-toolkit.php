<?php
/**
 * Plugin Name: Morden Toolkit
 * Plugin URI: https://github.com/sadewadee/morden-toolkit
 * Description: Lightweight developer tools for WordPress: Debug Manager, Query Monitor, Htaccess Editor, PHP Config presets.
 * Version: 1.2.12
 * Author: Morden Team
 * Author URI: https://mordenhost.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: morden-toolkit
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.6
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MT_VERSION', '1.1.0');
define('MT_PLUGIN_FILE', __FILE__);
define('MT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MT_PLUGIN_BASENAME', plugin_basename(__FILE__));

function mt_load_textdomain() {
    load_plugin_textdomain(
        'morden-toolkit',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages/'
    );
}
add_action('plugins_loaded', 'mt_load_textdomain');

require_once MT_PLUGIN_DIR . 'includes/helpers.php';
require_once MT_PLUGIN_DIR . 'includes/class-plugin.php';
require_once MT_PLUGIN_DIR . 'includes/class-debug.php';
require_once MT_PLUGIN_DIR . 'includes/class-query-monitor.php';
require_once MT_PLUGIN_DIR . 'includes/class-htaccess.php';
require_once MT_PLUGIN_DIR . 'includes/class-php-config.php';
require_once MT_PLUGIN_DIR . 'includes/class-file-manager.php';

function mt_init() {
    MT_Plugin::get_instance();
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
});

register_deactivation_hook(__FILE__, function() {
    if (get_option('mt_debug_enabled')) {
        $debug_service = new MT_Debug();
        $debug_service->disable_debug();
        update_option('mt_debug_enabled', false);
    }
});
