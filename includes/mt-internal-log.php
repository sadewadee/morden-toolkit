<?php
/**
 * Internal Logging Helper
 *
 * Provides controlled internal logging that can be enabled/disabled
 * via wp-config.php constant: MT_INTERNAL_LOGGING
 *
 * @package Morden Toolkit
 * @author Morden Team
 * @license GPL v3 or later
 * @link https://github.com/sadewadee/morden-toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Internal logging function with manual control
 *
 * @param string $message Log message
 * @param string $context Context prefix (optional)
 */
function mt_internal_log($message, $context = 'MT') {
    if (!defined('MT_INTERNAL_LOGGING') || !MT_INTERNAL_LOGGING) {
        return;
    }

    if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        error_log($context . ': ' . $message);
    }
}

/**
 * Debug-level internal logging
 */
function mt_debug_log($message) {
    mt_internal_log($message, 'MT Debug');
}

/**
 * Error-level internal logging
 */
function mt_error_log($message) {
    mt_internal_log($message, 'MT Error');
}

/**
 * Config-level internal logging
 */
function mt_config_log($message) {
    mt_internal_log($message, 'MT Config');
}