<?php

namespace ModernToolkit\Infrastructure\WordPress;

use ModernToolkit\Infrastructure\Utilities\MT_WPConfigTransformer;

if (!defined('ABSPATH')) {
    exit;
}

class MT_WpConfigIntegration {

    /**
     * Apply PHP configuration using MT_WPConfigTransformer
     *
     * @param array $settings PHP settings to apply
     * @return bool Success status
     */
    public static function apply_php_config_safe($settings) {
        if (empty($settings) || !is_array($settings)) {
            return false;
        }

        $wp_config_path = mt_get_wp_config_path();
        if (!$wp_config_path || !file_exists($wp_config_path)) {
            error_log('MT WP Config Integration: wp-config.php not found');
            return false;
        }

        // Create backup
        $backup_path = self::create_backup($wp_config_path);
        if (!$backup_path) {
            error_log('MT WP Config Integration: Failed to create backup');
            return false;
        }

        try {
            $transformer = new MT_WPConfigTransformer($wp_config_path);

            // Remove existing MT configuration
            self::remove_existing_mt_config($transformer);

            // Apply new settings
            foreach ($settings as $setting => $value) {
                self::apply_setting_safe($transformer, $setting, $value);
            }

            // Validate the changes
            if (self::validate_wp_config($wp_config_path)) {
                error_log('MT WP Config Integration: Configuration applied successfully');
                return true;
            } else {
                // Restore backup on validation failure
                copy($backup_path, $wp_config_path);
                error_log('MT WP Config Integration: Validation failed, backup restored');
                return false;
            }

        } catch (\Exception $e) {
            // Restore backup on exception
            copy($backup_path, $wp_config_path);
            error_log('MT WP Config Integration: Exception - ' . $e->getMessage() . ', backup restored');
            return false;
        }
    }

    /**
     * Apply debugging constants safely
     *
     * @param array $debug_settings Debug constants to apply
     * @return bool Success status
     */
    public static function apply_debug_constants($debug_settings) {
        if (empty($debug_settings) || !is_array($debug_settings)) {
            return false;
        }

        $wp_config_path = mt_get_wp_config_path();
        if (!$wp_config_path || !file_exists($wp_config_path)) {
            return false;
        }

        $backup_path = self::create_backup($wp_config_path);
        if (!$backup_path) {
            return false;
        }

        try {
            $transformer = new MT_WPConfigTransformer($wp_config_path);

            foreach ($debug_settings as $constant => $value) {
                $formatted_value = self::format_debug_value($value, $constant);
                $is_raw = self::is_raw_value($value, $constant);

                if ($transformer->exists('constant', $constant)) {
                    $transformer->update('constant', $constant, $formatted_value, [
                        'raw' => $is_raw,
                        'normalize' => true
                    ]);
                } else {
                    $transformer->add('constant', $constant, $formatted_value, [
                        'raw' => $is_raw,
                        'anchor' => "/* That's all, stop editing!",
                        'placement' => 'before',
                        'separator' => "\n"
                    ]);
                }
            }

            if (self::validate_wp_config($wp_config_path)) {
                return true;
            } else {
                copy($backup_path, $wp_config_path);
                return false;
            }

        } catch (\Exception $e) {
            copy($backup_path, $wp_config_path);
            error_log('MT WP Config Integration: Debug constants failed - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Apply single setting safely
     *
     * @param MT_WPConfigTransformer $transformer Transformer instance
     * @param string $setting Setting name
     * @param mixed $value Setting value
     */
    private static function apply_setting_safe($transformer, $setting, $value) {
        // Check if this setting has a WordPress constant equivalent
        $wp_constant = self::get_wordpress_constant_name($setting);

        if ($wp_constant) {
            // Apply as WordPress constant
            self::apply_wordpress_constant($transformer, $wp_constant, $value, $setting);
        } else {
            // Apply as ini_set directive with safe wrapper
            self::apply_ini_set_safe($transformer, $setting, $value);
        }
    }

    /**
     * Get WordPress constant name for PHP setting
     *
     * @param string $setting PHP setting name
     * @return string|null WordPress constant name or null
     */
    private static function get_wordpress_constant_name($setting) {
        $mapping = [
            'memory_limit' => 'WP_MEMORY_LIMIT',
            'max_execution_time' => 'WP_MAX_EXECUTION_TIME'
        ];

        return isset($mapping[$setting]) ? $mapping[$setting] : null;
    }

    /**
     * Apply WordPress constant
     *
     * @param MT_WPConfigTransformer $transformer Transformer instance
     * @param string $constant Constant name
     * @param mixed $value Constant value
     * @param string $original_setting Original PHP setting name
     */
    private static function apply_wordpress_constant($transformer, $constant, $value, $original_setting) {
        // Format value properly for WordPress constants
        $formatted_value = $value;

        if ($transformer->exists('constant', $constant)) {
            $transformer->update('constant', $constant, $formatted_value, [
                'raw' => false,
                'normalize' => true
            ]);
        } else {
            $transformer->add('constant', $constant, $formatted_value, [
                'raw' => false,
                'anchor' => "/* That's all, stop editing!",
                'placement' => 'before',
                'separator' => "\n"
            ]);
        }

        // Special handling for memory_limit - also set WP_MAX_MEMORY_LIMIT
        if ($constant === 'WP_MEMORY_LIMIT') {
            $admin_memory = self::calculate_admin_memory_limit($value);

            if ($transformer->exists('constant', 'WP_MAX_MEMORY_LIMIT')) {
                $transformer->update('constant', 'WP_MAX_MEMORY_LIMIT', $admin_memory, [
                    'raw' => false,
                    'normalize' => true
                ]);
            } else {
                $transformer->add('constant', 'WP_MAX_MEMORY_LIMIT', $admin_memory, [
                    'raw' => false,
                    'anchor' => "/* That's all, stop editing!",
                    'placement' => 'before',
                    'separator' => "\n"
                ]);
            }
        }
    }

    /**
     * Calculate admin memory limit based on user memory setting
     *
     * @param string $user_memory User memory setting (e.g., '512M')
     * @return string Admin memory limit
     */
    private static function calculate_admin_memory_limit($user_memory) {
        $user_bytes = self::parse_memory_value($user_memory);
        $admin_bytes = $user_bytes * 1.5; // 50% more for admin

        return self::format_memory_value($admin_bytes);
    }

    /**
     * Parse memory value to bytes
     *
     * @param string $memory Memory value (e.g., '512M')
     * @return int Memory in bytes
     */
    private static function parse_memory_value($memory) {
        $memory = trim($memory);
        $last_char = strtolower(substr($memory, -1));
        $value = (int) $memory;

        switch ($last_char) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }

        return $value;
    }

    /**
     * Format bytes to memory value
     *
     * @param int $bytes Memory in bytes
     * @return string Formatted memory value
     */
    private static function format_memory_value($bytes) {
        if ($bytes >= 1024 * 1024 * 1024) {
            return round($bytes / (1024 * 1024 * 1024)) . 'G';
        } elseif ($bytes >= 1024 * 1024) {
            return round($bytes / (1024 * 1024)) . 'M';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024) . 'K';
        }

        return $bytes;
    }

    /**
     * Apply ini_set directive safely
     *
     * @param MT_WPConfigTransformer $transformer Transformer instance
     * @param string $setting Setting name
     * @param mixed $value Setting value
     */
    private static function apply_ini_set_safe($transformer, $setting, $value) {
        $ini_set_call = sprintf("ini_set('%s', '%s');", $setting, addslashes($value));
        $variable_name = "mt_ini_set_{$setting}";

        if ($transformer->exists('variable', $variable_name)) {
            $transformer->update('variable', $variable_name, $ini_set_call, [
                'raw' => true,
                'normalize' => false
            ]);
        } else {
            $transformer->add('variable', $variable_name, $ini_set_call, [
                'raw' => true,
                'anchor' => "/* That's all, stop editing!",
                'placement' => 'before',
                'separator' => "\n"
            ]);
        }
    }

    /**
     * Apply single ini_set directive
     *
     * @param string $setting Setting name
     * @param mixed $value Setting value
     * @return bool Success status
     */
    public static function apply_ini_set($setting, $value) {
        $wp_config_path = mt_get_wp_config_path();
        if (!$wp_config_path || !file_exists($wp_config_path)) {
            return false;
        }

        $backup_path = self::create_backup($wp_config_path);
        if (!$backup_path) {
            return false;
        }

        try {
            $transformer = new MT_WPConfigTransformer($wp_config_path);
            self::apply_ini_set_safe($transformer, $setting, $value);

            if (self::validate_wp_config($wp_config_path)) {
                return true;
            } else {
                copy($backup_path, $wp_config_path);
                return false;
            }

        } catch (\Exception $e) {
            copy($backup_path, $wp_config_path);
            error_log('MT WP Config Integration: ini_set failed - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Remove existing MT configuration constants and variables
     *
     * @param MT_WPConfigTransformer $transformer Transformer instance
     */
    private static function remove_existing_mt_config($transformer) {
        $mt_constants = [
            'WP_MEMORY_LIMIT',
            'WP_MAX_MEMORY_LIMIT',
            'WP_MAX_EXECUTION_TIME'
        ];

        foreach ($mt_constants as $constant) {
            if ($transformer->exists('constant', $constant)) {
                $transformer->remove('constant', $constant);
            }
        }

        // Remove MT ini_set variables
        $mt_ini_vars = [
            'mt_ini_set_memory_limit',
            'mt_ini_set_upload_max_filesize',
            'mt_ini_set_post_max_size',
            'mt_ini_set_max_execution_time',
            'mt_ini_set_max_input_vars',
            'mt_ini_set_max_input_time'
        ];

        foreach ($mt_ini_vars as $var) {
            if ($transformer->exists('variable', $var)) {
                $transformer->remove('variable', $var);
            }
        }
    }

    /**
     * Format debug value for wp-config.php
     *
     * @param mixed $value Debug value
     * @param string $constant Constant name
     * @return string Formatted value
     */
    private static function format_debug_value($value, $constant = '') {
        // Special handling for WP_DEBUG_LOG
        if ($constant === 'WP_DEBUG_LOG' && $value === true) {
            // Ensure morden-toolkit directory exists
            self::ensure_morden_toolkit_directory();

            // Generate custom log path with random string
            $random_string = self::generate_random_string(8);
            $log_path = 'wp-content/morden-toolkit/wp-errors-' . $random_string . '.log';
            return $log_path;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        return $value;
    }

    /**
     * Determine if value should be treated as raw
     *
     * @param mixed $value Debug value
     * @param string $constant Constant name
     * @return bool Whether value is raw
     */
    private static function is_raw_value($value, $constant = '') {
        // WP_DEBUG_LOG with custom path should not be raw (needs quotes)
        if ($constant === 'WP_DEBUG_LOG' && $value === true) {
            return false;
        }

        return is_bool($value) || is_numeric($value);
    }

    /**
     * Generate random string for log filenames
     *
     * @param int $length Length of random string
     * @return string Random string
     */
    private static function generate_random_string($length = 8) {
        $characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $random_string = '';

        for ($i = 0; $i < $length; $i++) {
            $random_string .= $characters[rand(0, strlen($characters) - 1)];
        }

        return $random_string;
    }

    /**
     * Ensure morden-toolkit directory exists with proper security
     *
     * @return bool Success status
     */
    private static function ensure_morden_toolkit_directory() {
        $log_dir = WP_CONTENT_DIR . '/morden-toolkit';

        if (!file_exists($log_dir)) {
            if (!\wp_mkdir_p($log_dir)) {
                return false;
            }

            // Create .htaccess file to protect directory
            $htaccess_file = $log_dir . '/.htaccess';
            if (!file_exists($htaccess_file)) {
                file_put_contents($htaccess_file, "Order deny,allow\nDeny from all\n");
            }

            // Create index.php file
            $index_file = $log_dir . '/index.php';
            if (!file_exists($index_file)) {
                file_put_contents($index_file, "<?php\n// Silence is golden.\n");
            }
        }

        return true;
    }

    /**
     * Create backup of wp-config.php
     *
     * @param string $wp_config_path Path to wp-config.php
     * @return string|false Backup path or false on failure
     */
    private static function create_backup($wp_config_path) {
        $backup_dir = dirname($wp_config_path) . '/mt-backups';

        if (!file_exists($backup_dir)) {
            if (!\wp_mkdir_p($backup_dir)) {
                return false;
            }
        }

        $backup_filename = 'wp-config-backup-' . date('Y-m-d-H-i-s') . '.php';
        $backup_path = $backup_dir . '/' . $backup_filename;

        if (copy($wp_config_path, $backup_path)) {
            return $backup_path;
        }

        return false;
    }

    /**
     * Validate wp-config.php syntax
     *
     * @param string $wp_config_path Path to wp-config.php
     * @return bool Whether wp-config.php is valid
     */
    private static function validate_wp_config($wp_config_path) {
        $content = file_get_contents($wp_config_path);

        if ($content === false) {
            return false;
        }

        // Basic syntax validation
        if (strpos($content, '<?php') === false) {
            return false;
        }

        // Check for balanced parentheses, brackets, and braces
        $parentheses = substr_count($content, '(') - substr_count($content, ')');
        $brackets = substr_count($content, '[') - substr_count($content, ']');
        $braces = substr_count($content, '{') - substr_count($content, '}');

        return ($parentheses === 0 && $brackets === 0 && $braces === 0);
    }
}