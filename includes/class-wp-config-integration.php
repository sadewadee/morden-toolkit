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
            $transformer = new WPConfigTransformer($wp_config_path);
            
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
            
        } catch (Exception $e) {
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
            
        } catch (Exception $e) {
            copy($backup_path, $wp_config_path);
            error_log('MT WP Config Integration: Debug constants failed - ' . $e->getMessage());
            return false;
        }
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
        $formatted_value = "'$value'";
        
        if ($transformer->exists('constant', $constant)) {
            $transformer->update('constant', $constant, $formatted_value, [
                'normalize' => true
            ]);
        } else {
            $transformer->add('constant', $constant, $formatted_value, [
                'anchor' => "/* That's all, stop editing!",
                'placement' => 'before',
                'separator' => "\n"
            ]);
        }
        
        // Special handling for memory_limit - also set WP_MAX_MEMORY_LIMIT
        if ($constant === 'WP_MEMORY_LIMIT') {
            $admin_memory = self::calculate_admin_memory_limit($value);
            $admin_formatted = "'$admin_memory'";
            
            if ($transformer->exists('constant', 'WP_MAX_MEMORY_LIMIT')) {
                $transformer->update('constant', 'WP_MAX_MEMORY_LIMIT', $admin_formatted, [
                    'normalize' => true
                ]);
            } else {
                $transformer->add('constant', 'WP_MAX_MEMORY_LIMIT', $admin_formatted, [
                    'anchor' => "/* That's all, stop editing!",
                    'placement' => 'before',
                    'separator' => "\n"
                ]);
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
            error_log("MT WP Config Integration: Invalid setting name: $setting");
            return;
        }
        
        // Sanitize value
        $safe_value = self::sanitize_ini_value($value);
        
        // Create ini_set statement
        $ini_set_code = "ini_set('$safe_setting', '$safe_value');";
        
        // Use a comment-based approach to store ini_set directives
        $comment_constant = 'MT_PHP_CONFIG_' . strtoupper(str_replace('.', '_', $safe_setting));
        
        if ($transformer->exists('constant', $comment_constant)) {
            $transformer->update('constant', $comment_constant, $ini_set_code, [
                'raw' => true,
                'normalize' => false
            ]);
        } else {
            $transformer->add('constant', $comment_constant, $ini_set_code, [
                'raw' => true,
                'anchor' => "/* That's all, stop editing!",
                'placement' => 'before',
                'separator' => "\n"
            ]);
        }
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
        } else {
            return $value;
        }
    }
    
    /**
     * Remove existing MT configuration
     * 
     * @param WPConfigTransformer $transformer Transformer instance
     */
    private static function remove_existing_mt_config($transformer) {
        // List of MT constants to remove
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
        
        // Remove MT_PHP_CONFIG_* constants
        $wp_config_content = file_get_contents($transformer->wp_config_path ?? mt_get_wp_config_path());
        $lines = explode("\n", $wp_config_content);
        $cleaned_lines = [];
        
        foreach ($lines as $line) {
            // Skip lines that contain MT_PHP_CONFIG_ constants
            if (!preg_match('/define\s*\(\s*[\'"]MT_PHP_CONFIG_[^\'"]++[\'"]/', $line)) {
                $cleaned_lines[] = $line;
            }
        }
        
        $cleaned_content = implode("\n", $cleaned_lines);
        if ($cleaned_content !== $wp_config_content) {
            file_put_contents(mt_get_wp_config_path(), $cleaned_content, LOCK_EX);
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
                error_log('MT WP Config Integration: PHP syntax error detected');
                return false;
            }
        }
        
        // Basic content validation
        $content = file_get_contents($wp_config_path);
        
        // Check for required WordPress elements
        if (strpos($content, '<?php') === false || strpos($content, 'wp-settings.php') === false) {
            error_log('MT WP Config Integration: wp-config.php structure invalid');
            return false;
        }
        
        // Check for obvious syntax issues
        $syntax_issues = [
            '/define\s*\(\s*[^,)]*\s*\)/' => 'Incomplete define statement',
            '/\$\w+\s*=\s*;/' => 'Empty variable assignment'
        ];
        
        foreach ($syntax_issues as $pattern => $description) {
            if (preg_match($pattern, $content)) {
                error_log("MT WP Config Integration: Syntax issue - $description");
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
            error_log('MT WP Config Integration: Failed to get constant value - ' . $e->getMessage());
        }
        return null;
    }
}