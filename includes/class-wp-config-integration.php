<?php

/**
 * Integration class untuk menggunakan WPConfigTransformer dalam MT PHP Config
 *
 * Menggantikan metode manual editing wp-config.php yang rentan error
 * dengan implementasi yang lebih aman menggunakan WPConfigTransformer
 *
 * @package Morden_Toolkit
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

require_once MT_PLUGIN_DIR . 'includes/WPConfigTransformer.php';

class MT_WP_Config_Integration {

    /**
     * Apply PHP configuration using WPConfigTransformer
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
            mt_config_log(' wp-config.php not found');
            return false;
        }

        // Create backup
        $backup_path = self::create_backup($wp_config_path);
        if (!$backup_path) {
            mt_config_log(' Failed to create backup');
            return false;
        }

        try {
            $transformer = new WPConfigTransformer($wp_config_path);

            // Remove existing MT configuration
            self::remove_existing_mt_config($transformer);

            // Apply new settings
            foreach ($settings as $setting => $value) {
                self::apply_setting_safe($transformer, $setting, $value);
            }

            // Validate the changes
            if (self::validate_wp_config($wp_config_path)) {
                mt_config_log(' Configuration applied successfully');
                return true;
            } else {
                // Restore backup on validation failure
                copy($backup_path, $wp_config_path);
                mt_config_log(' Validation failed, backup restored');
                return false;
            }

        } catch (Exception $e) {
            // Restore backup on exception
            copy($backup_path, $wp_config_path);
            mt_config_log(' Exception - ' . $e->getMessage() . ', backup restored');
            return false;
        }
    }

    /**
     * Apply debugging constants with enhanced WP_DEBUG_LOG support
     *
     * @param array $debug_settings Debug constants to apply
     * @param bool $use_custom_log_path Whether to use custom log path for WP_DEBUG_LOG
     * @return bool Success status
     */
    public static function apply_debug_constants_enhanced($debug_settings, $use_custom_log_path = true) {
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
            $transformer = new WPConfigTransformer($wp_config_path);

            // Handle display_errors separately as ini_set
            if (isset($debug_settings['display_errors'])) {
                self::apply_ini_set_safe($transformer, 'display_errors', $debug_settings['display_errors']);
                unset($debug_settings['display_errors']);
            }

            // If WP_DEBUG_LOG is being enabled and custom paths are requested
            if ($use_custom_log_path && isset($debug_settings['WP_DEBUG_LOG']) && $debug_settings['WP_DEBUG_LOG']) {
                $custom_path = self::get_or_create_debug_log_path();
                $debug_settings['WP_DEBUG_LOG'] = $custom_path;

                mt_config_log(' Using custom debug log path: ' . $custom_path);
            }

            // Apply remaining debug constants
            if (!empty($debug_settings)) {
                foreach ($debug_settings as $constant => $value) {
                    $formatted_value = self::format_debug_value($value);

                    // For custom paths, we need raw=false so WPConfigTransformer quotes the string properly
                    $is_raw = is_bool($value) || is_numeric($value);
                    if (is_string($value) && (strpos($value, '/') !== false || strpos($value, 'wp-errors-') !== false)) {
                        $is_raw = false; // Force string quoting for file paths
                    }

                    if ($transformer->exists('constant', $constant)) {
                        $transformer->update('constant', $constant, $formatted_value, [
                            'raw' => $is_raw,
                            'normalize' => true
                        ]);
                    } else {
                        $anchor_config = self::get_safe_anchor($wp_config_path);
                        $add_options = array_merge([
                            'raw' => $is_raw,
                            'normalize' => true
                        ], $anchor_config);

                        $transformer->add('constant', $constant, $formatted_value, $add_options);
                    }
                }
            }

            if (self::validate_wp_config($wp_config_path)) {
                return true;
            } else {
                copy($backup_path, $wp_config_path);
                return false;
            }

        } catch (Exception $e) {
            copy($backup_path, $wp_config_path);
            mt_config_log(' Enhanced debug constants failed - ' . $e->getMessage());
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
            $transformer = new WPConfigTransformer($wp_config_path);

            foreach ($debug_settings as $constant => $value) {
                $formatted_value = self::format_debug_value($value);
                $is_raw = is_bool($value) || is_numeric($value);

                if ($transformer->exists('constant', $constant)) {
                    $transformer->update('constant', $constant, $formatted_value, [
                        'raw' => $is_raw,
                        'normalize' => true
                    ]);
                } else {
                    $anchor_config = self::get_safe_anchor($wp_config_path);
                    $add_options = array_merge([
                        'raw' => $is_raw,
                        'normalize' => true
                    ], $anchor_config);

                    $transformer->add('constant', $constant, $formatted_value, $add_options);
                }
            }

            if (self::validate_wp_config($wp_config_path)) {
                return true;
            } else {
                copy($backup_path, $wp_config_path);
                return false;
            }

        } catch (Exception $e) {
            copy($backup_path, $wp_config_path);
            mt_config_log(' Debug constants failed - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get safe placement anchor for WPConfigTransformer
     *
     * @param string $wp_config_path Path to wp-config.php
     * @return array Anchor configuration
     */
    private static function get_safe_anchor($wp_config_path) {
        $content = file_get_contents($wp_config_path);

        // Try multiple anchor options in order of preference
        $anchors = [
            "/* That's all, stop editing! Happy publishing. */",
            "/* That's all, stop editing!",
            "require_once ABSPATH . 'wp-settings.php';",
            "require_once( ABSPATH . 'wp-settings.php' );",
            "<?php"
        ];

        foreach ($anchors as $anchor) {
            if (strpos($content, $anchor) !== false) {
                return [
                    'anchor' => $anchor,
                    'placement' => $anchor === "<?php" ? 'after' : 'before',
                    'separator' => "\n"
                ];
            }
        }

        // If no anchor found, use append mode (safest fallback)
        return [
            'raw' => true,
            'placement' => 'append'
        ];
    }

    /**
     * Apply single setting safely
     *
     * @param WPConfigTransformer $transformer Transformer instance
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
     * @param WPConfigTransformer $transformer Transformer instance
     * @param string $constant Constant name
     * @param mixed $value Constant value
     * @param string $original_setting Original PHP setting name
     */
    private static function apply_wordpress_constant($transformer, $constant, $value, $original_setting) {
        // Format value properly for WordPress constants
        $formatted_value = $value;

        $wp_config_path = mt_get_wp_config_path();

        if ($transformer->exists('constant', $constant)) {
            $transformer->update('constant', $constant, $formatted_value, [
                'raw' => false,
                'normalize' => true
            ]);
        } else {
            $anchor_config = self::get_safe_anchor($wp_config_path);
            $add_options = array_merge([
                'raw' => false,
                'normalize' => true
            ], $anchor_config);

            $transformer->add('constant', $constant, $formatted_value, $add_options);
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
                $anchor_config = self::get_safe_anchor($wp_config_path);
                $add_options = array_merge([
                    'raw' => false,
                    'normalize' => true
                ], $anchor_config);

                $transformer->add('constant', 'WP_MAX_MEMORY_LIMIT', $admin_memory, $add_options);
            }
        }
    }

    /**
     * Apply ini_set directive safely
     *
     * @param WPConfigTransformer $transformer Transformer instance
     * @param string $setting PHP setting name
     * @param mixed $value Setting value
     */
    private static function apply_ini_set_safe($transformer, $setting, $value) {
        // Sanitize setting name to prevent code injection
        $safe_setting = preg_replace('/[^a-zA-Z0-9_.]/', '', $setting);
        if ($safe_setting !== $setting) {
            mt_config_log(" Invalid setting name: $setting");
            return;
        }

        // Sanitize value
        $safe_value = self::sanitize_ini_value($value);

        // Get current wp-config.php content
        $wp_config_path = mt_get_wp_config_path();
        $content = file_get_contents($wp_config_path);

        // Create ini_set statement with proper formatting
        $ini_set_line = "ini_set('$safe_setting', '$safe_value');";

        // Find the MT configuration block or create it
        $mt_start_marker = '/* BEGIN Morden Toolkit PHP Configuration */';
        $mt_end_marker = '/* END Morden Toolkit PHP Configuration */';

        // Check if MT block exists
        if (strpos($content, $mt_start_marker) !== false) {
            // Update existing block
            $pattern = '/\/\* BEGIN Morden Toolkit PHP Configuration \*\/.*?\/\* END Morden Toolkit PHP Configuration \*\//s';

            // Get existing ini_set statements
            preg_match($pattern, $content, $matches);
            $existing_block = isset($matches[0]) ? $matches[0] : '';

            // Parse existing ini_set statements
            $existing_statements = [];
            if (preg_match_all("/ini_set\('([^']+)', '([^']+)'\);/", $existing_block, $ini_matches, PREG_SET_ORDER)) {
                foreach ($ini_matches as $match) {
                    $existing_statements[$match[1]] = $match[0];
                }
            }

            // Add or update the current setting
            $existing_statements[$safe_setting] = $ini_set_line;

            // Rebuild the block
            $new_block = $mt_start_marker . "\n";
            foreach ($existing_statements as $statement) {
                $new_block .= " $statement\n";
            }
            $new_block .= " $mt_end_marker";

            // Replace the block
            $content = preg_replace($pattern, $new_block, $content);
        } else {
            // Create new block
            $new_block = "\n$mt_start_marker\n $ini_set_line\n $mt_end_marker\n";

            // Insert before "/* That's all, stop editing!" or at the end
            $stop_editing_pos = strpos($content, "/* That's all, stop editing!");
            if ($stop_editing_pos !== false) {
                $content = substr_replace($content, $new_block, $stop_editing_pos, 0);
            } else {
                $content .= $new_block;
            }
        }

        // Write the updated content
        file_put_contents($wp_config_path, $content);
    }

    /**
     * Sanitize ini_set value to prevent code injection
     *
     * @param mixed $value Value to sanitize
     * @return string Sanitized value
     */
    private static function sanitize_ini_value($value) {
        // Convert to string and escape quotes
        $string_value = (string) $value;

        // Remove or escape dangerous characters
        $safe_value = str_replace([
            "'", '"', '\\', '\n', '\r', '\t', ';', '<?', '?>'
        ], [
            "\\''", '\\"', '\\\\', '\\n', '\\r', '\\t', '', '', ''
        ], $string_value);

        return $safe_value;
    }

    /**
     * Generate custom debug log path with random string
     *
     * @return string Custom debug log path
     */
    private static function generate_custom_debug_log_path() {
        // Generate random string for unique log file
        $random_string = wp_generate_password(8, false, false);
        $log_filename = 'wp-errors-' . $random_string . '.log';

        // Use wp-content/morden-toolkit directory as specified in requirements
        $log_directory = ABSPATH . 'wp-content/morden-toolkit/';

        // Ensure directory exists
        if (!file_exists($log_directory)) {
            wp_mkdir_p($log_directory);
        }

        return $log_directory . $log_filename;
    }

    /**
     * Generate custom query log path with random string
     *
     * @return string Custom query log path
     */
    private static function generate_custom_query_log_path() {
        // Generate random string for unique log file
        $random_string = wp_generate_password(8, false, false);
        $log_filename = 'wp-queries-' . $random_string . '.log';

        // Use wp-content/morden-toolkit directory as specified in requirements
        $log_directory = ABSPATH . 'wp-content/morden-toolkit/';

        // Ensure directory exists
        if (!file_exists($log_directory)) {
            wp_mkdir_p($log_directory);
        }

        return $log_directory . $log_filename;
    }

    /**
     * Get custom query log path if it exists, or generate new one
     *
     * @return string Query log path
     */
    private static function get_or_create_query_log_path() {
        // Check if custom path already exists in wp-config
        $wp_config_path = mt_get_wp_config_path();
        if ($wp_config_path && file_exists($wp_config_path)) {
            $content = file_get_contents($wp_config_path);

            // Enhanced regex patterns to handle various quote escaping scenarios
            $patterns = [
                // Standard pattern: define( 'MT_QUERY_LOG', '/path/to/file' )
                '/define\s*\(\s*[\'"]MT_QUERY_LOG[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]\s*\)/',
                // Handle escaped quotes: define( 'MT_QUERY_LOG', '\''/path/to/file'\'' )
                '/define\s*\(\s*[\'"]MT_QUERY_LOG[\'"]\s*,\s*[\'"]\\\\?[\'"]([^\\\\]+)\\\\?[\'"][\'"] *\)/',
                // Handle double quotes with single quotes: define( "MT_QUERY_LOG", '/path/to/file' )
                '/define\s*\(\s*"MT_QUERY_LOG"\s*,\s*[\'"]([^\'"]+)[\'"]\s*\)/',
            ];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $content, $matches)) {
                    $existing_path = $matches[1];
                    // Clean any remaining escape characters
                    $existing_path = stripslashes($existing_path);
                    // Only use if it's a custom query log path
                    if (strpos($existing_path, 'wp-queries-') !== false && (file_exists($existing_path) || is_writable(dirname($existing_path)))) {
                        return $existing_path;
                    }
                }
            }
        }

        // Generate new custom path
        return self::generate_custom_query_log_path();
    }

    /**
     * Apply custom query log path constant to wp-config.php
     *
     * @param bool $enable_custom_path Whether to enable custom query log path
     * @return bool Success status
     */
    public static function apply_custom_query_log_path($enable_custom_path = true) {
        $wp_config_path = mt_get_wp_config_path();
        if (!$wp_config_path || !file_exists($wp_config_path)) {
            return false;
        }

        $backup_path = self::create_backup($wp_config_path);
        if (!$backup_path) {
            return false;
        }

        try {
            $transformer = new WPConfigTransformer($wp_config_path);

            if ($enable_custom_path) {
                // Get or create custom query log path
                $custom_path = self::get_or_create_query_log_path();

                // Apply custom path constant
                $formatted_value = self::format_debug_value($custom_path);

                if ($transformer->exists('constant', 'MT_QUERY_LOG')) {
                    $transformer->update('constant', 'MT_QUERY_LOG', $formatted_value, [
                        'raw' => false, // Force string quoting for file paths
                        'normalize' => true
                    ]);
                } else {
                    $anchor_config = self::get_safe_anchor($wp_config_path);
                    $add_options = array_merge([
                        'raw' => false, // Force string quoting for file paths
                        'normalize' => true
                    ], $anchor_config);

                    $transformer->add('constant', 'MT_QUERY_LOG', $formatted_value, $add_options);
                }

                mt_config_log(' Applied custom query log path: ' . $custom_path);
            } else {
                // Remove custom path constant
                if ($transformer->exists('constant', 'MT_QUERY_LOG')) {
                    $transformer->remove('constant', 'MT_QUERY_LOG');
                }
            }

            if (self::validate_wp_config($wp_config_path)) {
                return true;
            } else {
                copy($backup_path, $wp_config_path);
                return false;
            }

        } catch (Exception $e) {
            copy($backup_path, $wp_config_path);
            mt_config_log(' Custom query log path failed - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get custom debug log path if it exists, or generate new one
     *
     * @return string Debug log path
     */
    private static function get_or_create_debug_log_path() {
        // Check if custom path already exists in wp-config
        $wp_config_path = mt_get_wp_config_path();
        if ($wp_config_path && file_exists($wp_config_path)) {
            $content = file_get_contents($wp_config_path);

            // Enhanced regex patterns to handle various quote escaping scenarios
            $patterns = [
                // Standard pattern: define( 'WP_DEBUG_LOG', '/path/to/file' )
                '/define\s*\(\s*[\'"]WP_DEBUG_LOG[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]\s*\)/',
                // Handle escaped quotes: define( 'WP_DEBUG_LOG', '\''/path/to/file'\'' )
                '/define\s*\(\s*[\'"]WP_DEBUG_LOG[\'"]\s*,\s*[\'"]\\\\?[\'"]([^\\\\]+)\\\\?[\'"][\'"] *\)/',
                // Handle double quotes with single quotes: define( "WP_DEBUG_LOG", '/path/to/file' )
                '/define\s*\(\s*"WP_DEBUG_LOG"\s*,\s*[\'"]([^\'"]+)[\'"]\s*\)/',
            ];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $content, $matches)) {
                    $existing_path = $matches[1];
                    // Clean any remaining escape characters
                    $existing_path = stripslashes($existing_path);
                    // Only use if it's a custom debug log path
                    if (strpos($existing_path, 'wp-errors-') !== false && (file_exists($existing_path) || is_writable(dirname($existing_path)))) {
                        return $existing_path;
                    }
                }
            }
        }

        // Generate new custom path
        return self::generate_custom_debug_log_path();
    }

    /**
     * Format debug value for constants
     *
     * @param mixed $value Debug value
     * @return string Formatted value
     */
    private static function format_debug_value($value) {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        } elseif (is_numeric($value)) {
            return (string) $value;
        } elseif (is_string($value)) {
            // Handle custom paths (for WP_DEBUG_LOG)
            if (strpos($value, 'wp-errors-') !== false || strpos($value, '/') !== false) {
                // For file paths, just return the raw path - WPConfigTransformer will handle quoting
                return $value;
            }
            return $value;
        } else {
            return (string) $value;
        }
    }

    /**
     * Remove existing MT configuration
     *
     * @param WPConfigTransformer $transformer Transformer instance
     */
    private static function remove_existing_mt_config($transformer) {
        // Remove WordPress constants that MT manages
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

        // Remove existing MT PHP Configuration block
        $wp_config_path = mt_get_wp_config_path();
        $content = file_get_contents($wp_config_path);

        // Remove the entire MT block if it exists
        $pattern = '/\s*\/\* BEGIN Morden Toolkit PHP Configuration \*\/.*?\/\* END Morden Toolkit PHP Configuration \*\/\s*/s';
        $cleaned_content = preg_replace($pattern, "\n", $content);

        // Write cleaned content if changes were made
        if ($cleaned_content !== $content) {
            file_put_contents($wp_config_path, $cleaned_content, LOCK_EX);
        }
    }

    /**
     * Create backup of wp-config.php
     *
     * @param string $wp_config_path Path to wp-config.php
     * @return string|false Backup file path or false on failure
     */
    private static function create_backup($wp_config_path) {
        $backup_dir = MT_PLUGIN_DIR . 'backups/wp-config/';

        // Create backup directory if it doesn't exist
        if (!file_exists($backup_dir)) {
            if (!mkdir($backup_dir, 0755, true) && !is_dir($backup_dir)) {
                return false;
            }
        }

        $timestamp = date('Y-m-d_H-i-s');
        $backup_file = $backup_dir . "wp-config-backup-{$timestamp}.php";

        if (copy($wp_config_path, $backup_file)) {
            // Keep only last 10 backups
            self::cleanup_old_backups($backup_dir);
            return $backup_file;
        }

        return false;
    }

    /**
     * Cleanup old backup files
     *
     * @param string $backup_dir Backup directory path
     */
    private static function cleanup_old_backups($backup_dir) {
        $files = glob($backup_dir . 'wp-config-backup-*.php');
        if (count($files) > 10) {
            // Sort by modification time, oldest first
            usort($files, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });

            // Remove oldest files
            $to_remove = array_slice($files, 0, count($files) - 10);
            foreach ($to_remove as $file) {
                unlink($file);
            }
        }
    }

    /**
     * Validate wp-config.php syntax and basic functionality
     *
     * @param string $wp_config_path Path to wp-config.php
     * @return bool Valid configuration
     */
    public static function validate_wp_config($wp_config_path) {
        // Basic syntax validation
        if (function_exists('shell_exec') && !empty(shell_exec('which php'))) {
            $output = shell_exec("php -l {$wp_config_path} 2>&1");
            if (strpos($output, 'No syntax errors detected') === false) {
                mt_config_log(' PHP syntax error detected');
                return false;
            }
        }

        // Basic content validation
        $content = file_get_contents($wp_config_path);

        // Check for required WordPress elements
        if (strpos($content, '<?php') === false || strpos($content, 'wp-settings.php') === false) {
            mt_config_log(' wp-config.php structure invalid');
            return false;
        }

        // Check for obvious syntax issues
        $syntax_issues = [
            '/define\s*\(\s*[^,)]*\s*\)/' => 'Incomplete define statement',
            '/\$\w+\s*=\s*;/' => 'Empty variable assignment'
        ];

        foreach ($syntax_issues as $pattern => $description) {
            if (preg_match($pattern, $content)) {
                mt_config_log(" Syntax issue - $description");
                return false;
            }
        }

        return true;
    }

    /**
     * Calculate admin memory limit (50% more than base, minimum 256M)
     *
     * @param string $memory_limit Base memory limit
     * @return string Admin memory limit
     */
    private static function calculate_admin_memory_limit($memory_limit) {
        $bytes = self::convert_to_bytes($memory_limit);
        $admin_bytes = max($bytes * 1.5, 256 * 1024 * 1024);
        return self::convert_bytes_to_readable($admin_bytes);
    }

    /**
     * Convert memory string to bytes
     *
     * @param string $memory_string Memory string (e.g., '256M')
     * @return int Bytes
     */
    private static function convert_to_bytes($memory_string) {
        $memory_string = trim($memory_string);
        $last_char = strtolower(substr($memory_string, -1));
        $number = (int) substr($memory_string, 0, -1);

        switch ($last_char) {
            case 'g':
                return $number * 1024 * 1024 * 1024;
            case 'm':
                return $number * 1024 * 1024;
            case 'k':
                return $number * 1024;
            default:
                return (int) $memory_string;
        }
    }

    /**
     * Convert bytes to readable format
     *
     * @param int $bytes Bytes
     * @return string Readable format
     */
    private static function convert_bytes_to_readable($bytes) {
        if ($bytes >= 1024 * 1024 * 1024) {
            return round($bytes / (1024 * 1024 * 1024)) . 'G';
        } elseif ($bytes >= 1024 * 1024) {
            return round($bytes / (1024 * 1024)) . 'M';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024) . 'K';
        } else {
            return $bytes;
        }
    }

    /**
     * Check if constant exists in wp-config.php
     *
     * @param string $constant_name Constant name
     * @return bool Constant exists
     */
    public static function constant_exists($constant_name) {
        try {
            $wp_config_path = mt_get_wp_config_path();
            if (!$wp_config_path || !file_exists($wp_config_path)) {
                return false;
            }

            $transformer = new WPConfigTransformer($wp_config_path, true); // read-only
            return $transformer->exists('constant', $constant_name);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get constant value from wp-config.php
     *
     * @param string $constant_name Constant name
     * @return string|null Constant value or null
     */
    public static function get_constant_value($constant_name) {
        try {
            $wp_config_path = mt_get_wp_config_path();
            if (!$wp_config_path || !file_exists($wp_config_path)) {
                return null;
            }

            $transformer = new WPConfigTransformer($wp_config_path, true); // read-only
            if ($transformer->exists('constant', $constant_name)) {
                return $transformer->get_value('constant', $constant_name);
            }
        } catch (Exception $e) {
            mt_config_log(' Failed to get constant value - ' . $e->getMessage());
        }
        return null;
    }
}