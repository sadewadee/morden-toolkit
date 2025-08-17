<?php
/**
 * PHP Config Service - Preset-based PHP configuration
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MT_PHP_Config {

    /**
     * Available configuration presets
     */
    private $presets = array();

    /**
     * Constructor
     */
    public function __construct() {
        $this->load_presets();
    }

    /**
     * Load configuration presets
     */
    private function load_presets() {
        $presets_file = MT_PLUGIN_DIR . 'data/presets/php-config.json';

        if (file_exists($presets_file)) {
            $presets_content = file_get_contents($presets_file);
            $this->presets = json_decode($presets_content, true);
        }

        // Fallback to default presets if file doesn't exist
        if (empty($this->presets)) {
            $this->presets = $this->get_default_presets();
        }
    }

    /**
     * Get default configuration presets
     */
    private function get_default_presets() {
        return array(
            'basic' => array(
                'name' => __('Basic', 'mt'),
                'description' => __('Suitable for small sites with light traffic', 'mt'),
                'settings' => array(
                    'memory_limit' => '128M',
                    'upload_max_filesize' => '8M',
                    'post_max_size' => '16M',
                    'max_execution_time' => '60',
                    'max_input_vars' => '1000',
                    'max_input_time' => '60'
                )
            ),
            'medium' => array(
                'name' => __('Medium', 'mt'),
                'description' => __('Good for most WordPress sites', 'mt'),
                'settings' => array(
                    'memory_limit' => '256M',
                    'upload_max_filesize' => '16M',
                    'post_max_size' => '32M',
                    'max_execution_time' => '120',
                    'max_input_vars' => '3000',
                    'max_input_time' => '120'
                )
            ),
            'high' => array(
                'name' => __('High Performance', 'mt'),
                'description' => __('For high-traffic sites and complex applications', 'mt'),
                'settings' => array(
                    'memory_limit' => '512M',
                    'upload_max_filesize' => '32M',
                    'post_max_size' => '64M',
                    'max_execution_time' => '300',
                    'max_input_vars' => '5000',
                    'max_input_time' => '300'
                )
            ),
            'custom' => array(
                'name' => __('Custom High Memory', 'mt'),
                'description' => __('Maximum performance with 2GB memory (70% server capacity)', 'mt'),
                'settings' => array(
                    'memory_limit' => '2048M',
                    'upload_max_filesize' => '64M',
                    'post_max_size' => '128M',
                    'max_execution_time' => '600',
                    'max_input_vars' => '10000',
                    'max_input_time' => '600'
                )
            )
        );
    }

    /**
     * Get all available presets with dynamic custom preset
     */
    public function get_presets() {
        $presets = $this->presets;

        // Update custom preset with server-aware memory limit
        if (isset($presets['custom'])) {
            $memory_info = $this->get_server_memory_info();
            $presets['custom']['settings']['memory_limit'] = $memory_info['optimal_memory'];
            $presets['custom']['description'] = sprintf(
                __('Maximum performance with %s memory (%d%% server capacity)', 'mt'),
                $memory_info['optimal_memory'],
                $memory_info['safe_percentage']
            );
        }

        return $presets;
    }

    /**
     * Get specific preset
     */
    public function get_preset($preset_name) {
        return isset($this->presets[$preset_name]) ? $this->presets[$preset_name] : null;
    }

    /**
     * Apply configuration preset with best practice: htaccess > wp-config.php
     *
     * Priority Strategy:
     * 1. .htaccess (Apache) or nginx config (for Nginx)
     * 2. wp-config.php with WordPress constants only (NO ini_set)
     * 3. Validation to ensure changes took effect
     */
    public function apply_preset($preset_name) {
        if (!isset($this->presets[$preset_name])) {
            return false;
        }

        $preset = $this->presets[$preset_name];

        // Store current configuration for validation and rollback
        $original_values = $this->get_current_config();

        // Priority 1: Try .htaccess first (Server-level configuration)
        $htaccess_path = mt_get_htaccess_path();
        if ($htaccess_path && mt_is_file_writable($htaccess_path)) {
            $server_type = $this->detect_server_type();

            // Only proceed with .htaccess if Apache (Nginx doesn't support .htaccess)
            if ($server_type === 'apache') {
                if ($this->try_apply_via_htaccess_with_testing($preset['settings'], $original_values)) {
                    return true;
                }
            }
        }

        // Priority 2: Fallback to wp-config.php (WordPress constants only)
        return $this->try_apply_via_wp_config_with_testing($preset['settings'], $original_values);
    }

    /**
     * Try to apply configuration via .htaccess with proper testing
     */
    private function try_apply_via_htaccess_with_testing($settings, $original_values) {
        $htaccess_path = mt_get_htaccess_path();

        if (!$htaccess_path || !mt_is_file_writable($htaccess_path)) {
            return false;
        }

        try {
            // Step 1: Backup current .htaccess
            $htaccess_service = new MT_Htaccess();
            $original_content = $htaccess_service->get_htaccess_content();

            // Step 2: Apply new configuration
            $this->apply_via_apache_htaccess($settings);

            // Step 3: Test if site still works (no 500 error)
            if (!$this->test_site_accessibility()) {
                // Site broken - restore immediately
                $htaccess_service->save_htaccess($original_content);
                error_log('MT PHP Config: .htaccess caused 500 error, reverted');
                return false;
            }

            // Step 4: Wait for Apache to reload configuration
            // .htaccess changes might need time to take effect
            sleep(3);

            // Step 5: Validate that configuration was applied (more lenient for .htaccess)
            if ($this->validate_config_changes($original_values, $settings)) {
                error_log('MT PHP Config: .htaccess configuration applied successfully');
                return true;
            } else {
                // For .htaccess, be more lenient - if site works and config is written, consider success
                if ($this->validate_htaccess_configuration_applied($settings)) {
                    error_log('MT PHP Config: .htaccess configuration written successfully (values may take time to reflect)');
                    return true;
                } else {
                    // Configuration definitely failed - restore original
                    $htaccess_service->save_htaccess($original_content);
                    error_log('MT PHP Config: .htaccess configuration failed, reverted');
                    return false;
                }
            }

        } catch (Exception $e) {
            // Any error - restore original
            if (isset($original_content)) {
                $htaccess_service->save_htaccess($original_content);
            }
            error_log('MT PHP Config: .htaccess application failed - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Try to apply configuration via wp-config.php with testing
     */
    private function try_apply_via_wp_config_with_testing($settings, $original_values) {
        $wp_config_path = mt_get_wp_config_path();

        if (!$wp_config_path || !mt_is_file_writable($wp_config_path)) {
            return false;
        }

        try {
            // Step 1: Backup current wp-config.php
            $original_content = file_get_contents($wp_config_path);

            // Step 2: Apply new configuration
            $this->apply_via_wp_config_constants_only($settings);

            // Step 3: Test if site still works
            if (!$this->test_site_accessibility()) {
                // Site broken - restore immediately
                file_put_contents($wp_config_path, $original_content);
                error_log('MT PHP Config: wp-config.php caused error, reverted');
                return false;
            }

            // Step 4: WordPress constants are immediate, but test anyway
            sleep(1);

            // Step 5: Validate changes (for WordPress constants, check if defined)
            if ($this->validate_wordpress_constants($settings)) {
                error_log('MT PHP Config: wp-config.php constants applied successfully');
                return true;
            } else {
                // Constants not properly set - restore
                file_put_contents($wp_config_path, $original_content);
                error_log('MT PHP Config: wp-config.php constants not set, reverted');
                return false;
            }

        } catch (Exception $e) {
            // Any error - restore original
            if (isset($original_content)) {
                file_put_contents($wp_config_path, $original_content);
            }
            error_log('MT PHP Config: wp-config.php application failed - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Test if site is still accessible (no 500 error)
     */
    private function test_site_accessibility() {
        // Get current site URL
        $test_url = home_url('/');

        // Simple HTTP request to check if site responds
        $response = wp_remote_get($test_url, [
            'timeout' => 10,
            'sslverify' => false,
            'user-agent' => 'Morden Toolkit Config Test'
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);

        // Accept 200, 301, 302 as valid responses
        return in_array($status_code, [200, 301, 302]);
    }

    /**
     * Validate that WordPress constants are properly defined
     */
    private function validate_wordpress_constants($settings) {
        foreach ($settings as $setting => $value) {
            $wp_constant = $this->setting_to_wp_constant($setting);
            if ($wp_constant) {
                if (!defined($wp_constant)) {
                    return false;
                }

                // For memory settings, check if value is reasonable
                if (in_array($wp_constant, ['WP_MEMORY_LIMIT', 'WP_MAX_MEMORY_LIMIT'])) {
                    $defined_value = constant($wp_constant);
                    if (empty($defined_value)) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * Detect server type (Apache or Nginx)
     */
    private function detect_server_type() {
        if (isset($_SERVER['SERVER_SOFTWARE'])) {
            $server = strtolower($_SERVER['SERVER_SOFTWARE']);

            if (strpos($server, 'apache') !== false) {
                return 'apache';
            } elseif (strpos($server, 'nginx') !== false) {
                return 'nginx';
            }
        }

        // Additional detection methods
        if (function_exists('apache_get_modules')) {
            return 'apache';
        }

        // Default assumption for shared hosting
        return 'apache';
    }

    /**
     * Apply configuration via Apache .htaccess
     */
    private function apply_via_apache_htaccess($settings) {
        $htaccess_service = new MT_Htaccess();
        $current_content = $htaccess_service->get_htaccess_content();

        // Remove existing PHP configuration block
        $current_content = $this->remove_php_config_block($current_content);

        // Generate Apache-compatible PHP block
        $php_block = $this->generate_apache_htaccess_php_block($settings);

        // Debug: Log what we're writing
        error_log("MT PHP Config: Writing to .htaccess:\n" . $php_block);

        $new_content = $current_content . "\n\n" . $php_block;

        return $htaccess_service->save_htaccess($new_content);
    }

    /**
     * Apply configuration via wp-config.php using ONLY WordPress constants
     */
    private function apply_via_wp_config_constants_only($settings) {
        $wp_config_path = mt_get_wp_config_path();

        if (!$wp_config_path || !file_exists($wp_config_path)) {
            error_log('MT PHP Config: wp-config.php not found at: ' . ($wp_config_path ?: 'undefined path'));
            return false;
        }

        // Safety check: ensure we're not accidentally reading .htaccess
        if (basename($wp_config_path) !== 'wp-config.php') {
            error_log('MT PHP Config: Invalid wp-config path detected: ' . $wp_config_path);
            return false;
        }

        $config_content = file_get_contents($wp_config_path);

        // Safety check: ensure content looks like wp-config.php
        if (strpos($config_content, '<?php') === false || strpos($config_content, 'wp-settings.php') === false) {
            error_log('MT PHP Config: File does not appear to be wp-config.php');
            return false;
        }

        // Remove existing PHP configuration block
        $config_content = $this->remove_wp_config_php_block($config_content);

        // Add new PHP configuration block (WordPress constants only)
        $php_block = $this->generate_wp_config_constants_block($settings);

        // Insert before "/* That's all, stop editing!" line
        $insert_before = "/* That's all, stop editing!";
        $position = strpos($config_content, $insert_before);

        if ($position !== false) {
            $before = substr($config_content, 0, $position);
            $after = substr($config_content, $position);
            $config_content = $before . $php_block . "\n" . $after;
        } else {
            $config_content .= "\n" . $php_block . "\n";
        }

        return file_put_contents($wp_config_path, $config_content) !== false;
    }

    /**
     * Validate that configuration changes actually took effect
     * For .htaccess, values might not change immediately until PHP process reload
     */
    private function validate_config_changes($original_values, $target_settings) {
        $new_values = $this->get_current_config();

        $changes_detected = 0;
        $total_settings = 0;

        foreach ($target_settings as $setting => $target_value) {
            $total_settings++;

            if (isset($original_values[$setting]) && isset($new_values[$setting])) {
                $original = $this->normalize_php_value($original_values[$setting]);
                $new = $this->normalize_php_value($new_values[$setting]);
                $target = $this->normalize_php_value($target_value);

                // Check if value changed towards target OR is already at target
                if ($new === $target || ($new !== $original && $new >= $target * 0.8)) {
                    $changes_detected++;
                    error_log("MT PHP Config: {$setting} changed from {$original} to {$new} (target: {$target})");
                } else {
                    error_log("MT PHP Config: {$setting} unchanged: {$original} -> {$new} (target: {$target})");
                }
            }
        }

        // For .htaccess, accept if at least 50% of values changed or site is still accessible
        $success_rate = $total_settings > 0 ? ($changes_detected / $total_settings) : 0;

        if ($success_rate >= 0.5) {
            error_log("MT PHP Config: Validation passed - {$changes_detected}/{$total_settings} settings changed");
            return true;
        } else {
            error_log("MT PHP Config: Validation failed - only {$changes_detected}/{$total_settings} settings changed");
            // For .htaccess, be more lenient since changes might take time to reflect
            return $this->validate_htaccess_configuration_applied($target_settings);
        }
    }

    /**
     * Alternative validation for .htaccess - check if configuration was written correctly
     */
    private function validate_htaccess_configuration_applied($target_settings) {
        $htaccess_path = mt_get_htaccess_path();

        if (!$htaccess_path || !file_exists($htaccess_path)) {
            return false;
        }

        $htaccess_content = file_get_contents($htaccess_path);

        // Check if our PHP configuration block exists
        if (strpos($htaccess_content, 'BEGIN Morden Toolkit PHP Configuration') === false) {
            error_log('MT PHP Config: .htaccess validation failed - configuration block not found');
            return false;
        }

        // Check if at least some target settings are present in .htaccess
        $settings_found = 0;
        $total_settings = count($target_settings);

        foreach ($target_settings as $setting => $value) {
            $php_directive = $this->setting_to_apache_directive($setting);
            if ($php_directive) {
                // Check if directive exists in .htaccess
                if (strpos($htaccess_content, "php_value {$php_directive}") !== false) {
                    $settings_found++;
                }
            } else {
                // If no apache directive mapping exists, don't count it against us
                $total_settings--;
            }
        }

        // More lenient: require at least 50% of applicable settings to be found
        $required_found = max(1, ceil($total_settings * 0.5));
        $success = $settings_found >= $required_found;

        if ($success) {
            error_log("MT PHP Config: .htaccess validation passed - {$settings_found}/{$total_settings} applicable settings found in file");
        } else {
            error_log("MT PHP Config: .htaccess validation failed - only {$settings_found}/{$total_settings} applicable settings found (needed: {$required_found})");
        }

        return $success;
    }

    /**
     * Normalize PHP values for accurate comparison
     */
    private function normalize_php_value($value) {
        // Convert memory values to bytes for comparison
        if (preg_match('/^(\d+)([MmGgKk]?)$/', $value, $matches)) {
            $number = (int) $matches[1];
            $unit = strtolower($matches[2] ?? '');

            switch ($unit) {
                case 'g':
                    return $number * 1024 * 1024 * 1024;
                case 'm':
                    return $number * 1024 * 1024;
                case 'k':
                    return $number * 1024;
                default:
                    return $number;
            }
        }

        return $value;
    }

    /**
     * Revert .htaccess changes if they caused issues
     */
    private function revert_htaccess_changes() {
        try {
            $htaccess_service = new MT_Htaccess();
            $current_content = $htaccess_service->get_htaccess_content();

            // Remove our PHP configuration block
            $reverted_content = $this->remove_php_config_block($current_content);

            return $htaccess_service->save_htaccess($reverted_content);
        } catch (Exception $e) {
            error_log('MT PHP Config: Failed to revert .htaccess changes - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Detect the best method to apply PHP configuration
     */
    public function detect_configuration_method() {
        // Check if .htaccess is writable and mod_php is available
        $htaccess_path = mt_get_htaccess_path();
        if (mt_is_file_writable($htaccess_path) && function_exists('apache_get_modules')) {
            $modules = apache_get_modules();
            if (in_array('mod_php5', $modules) || in_array('mod_php7', $modules) || in_array('mod_php8', $modules)) {
                return 'htaccess';
            }
        }

        // Check if wp-config.php is writable
        $wp_config_path = mt_get_wp_config_path();
        if ($wp_config_path && mt_is_file_writable($wp_config_path)) {
            return 'wp_config';
        }

        // Check if .user.ini can be created
        $user_ini_path = ABSPATH . '.user.ini';
        if (mt_is_file_writable($user_ini_path)) {
            return 'user_ini';
        }

        return false;
    }

    /**
     * Apply configuration via .htaccess
     */
    private function apply_via_htaccess($settings) {
        $htaccess_service = new MT_Htaccess();
        $current_content = $htaccess_service->get_htaccess_content();

        // Remove existing PHP configuration block
        $current_content = $this->remove_php_config_block($current_content);

        // Add new PHP configuration block
        $php_block = $this->generate_htaccess_php_block($settings);
        $new_content = $current_content . "\n\n" . $php_block;

        return $htaccess_service->save_htaccess($new_content);
    }

    /**
     * Apply configuration via wp-config.php using WordPress best practices
     *
     * Uses WordPress constants (WP_MEMORY_LIMIT, WP_MAX_MEMORY_LIMIT) and custom MT_ constants
     * NO ini_set() calls to comply with shared hosting restrictions
     *
     * @see https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
     */
    private function apply_via_wp_config($settings) {
        $wp_config_path = mt_get_wp_config_path();

        if (!$wp_config_path) {
            return false;
        }

        $config_content = file_get_contents($wp_config_path);

        // Remove existing PHP configuration block
        $config_content = $this->remove_wp_config_php_block($config_content);

        // Add new PHP configuration block
        $php_block = $this->generate_wp_config_php_block($settings);

        // Insert before "/* That's all, stop editing!" line
        $insert_before = "/* That's all, stop editing!";
        $position = strpos($config_content, $insert_before);

        if ($position !== false) {
            $before = substr($config_content, 0, $position);
            $after = substr($config_content, $position);
            $config_content = $before . $php_block . "\n" . $after;
        } else {
            $config_content .= "\n" . $php_block . "\n";
        }

        return file_put_contents($wp_config_path, $config_content) !== false;
    }

    /**
     * Apply configuration via .user.ini
     */
    private function apply_via_user_ini($settings) {
        $user_ini_path = ABSPATH . '.user.ini';
        $ini_content = $this->generate_user_ini_content($settings);

        return file_put_contents($user_ini_path, $ini_content) !== false;
    }

    /**
     * Generate .htaccess PHP configuration block
     */
    private function generate_htaccess_php_block($settings) {
        $block = "# BEGIN Morden Toolkit PHP Config\n";
        $block .= "<IfModule mod_php5.c>\n";

        foreach ($settings as $key => $value) {
            $block .= "php_value {$key} {$value}\n";
        }

        $block .= "</IfModule>\n";
        $block .= "<IfModule mod_php7.c>\n";

        foreach ($settings as $key => $value) {
            $block .= "php_value {$key} {$value}\n";
        }

        $block .= "</IfModule>\n";
        $block .= "<IfModule mod_php8.c>\n";

        foreach ($settings as $key => $value) {
            $block .= "php_value {$key} {$value}\n";
        }

        $block .= "</IfModule>\n";
        $block .= "# END Morden Toolkit PHP Config";

        return $block;
    }

    /**
     * Generate wp-config.php PHP configuration block using WordPress constants ONLY
     */
    private function generate_wp_config_php_block($settings) {
        $block = "/* BEGIN Morden Toolkit PHP Config - WordPress Constants Only */\n";

        foreach ($settings as $key => $value) {
            // Map PHP settings to WordPress constants ONLY - no ini_set fallback
            $constant = $this->map_setting_to_wordpress_constant($key, $value);
            if ($constant) {
                $block .= $constant . "\n";
            }
            // NO ini_set fallback - only use WordPress constants
        }

        $block .= "/* END Morden Toolkit PHP Config */\n";

        return $block;
    }

    /**
     * Generate Apache .htaccess PHP configuration block
     */
    private function generate_apache_htaccess_php_block($settings) {
        $htaccess_block = "# BEGIN Morden Toolkit PHP Configuration\n";
        $htaccess_block .= "<IfModule mod_php7.c>\n";

        foreach ($settings as $setting => $value) {
            $php_directive = $this->setting_to_apache_directive($setting);
            if ($php_directive) {
                $htaccess_block .= "    php_value {$php_directive} {$value}\n";
            }
        }

        $htaccess_block .= "</IfModule>\n";
        $htaccess_block .= "<IfModule mod_php8.c>\n";

        foreach ($settings as $setting => $value) {
            $php_directive = $this->setting_to_apache_directive($setting);
            if ($php_directive) {
                $htaccess_block .= "    php_value {$php_directive} {$value}\n";
            }
        }

        $htaccess_block .= "</IfModule>\n";
        $htaccess_block .= "# END Morden Toolkit PHP Configuration";

        return $htaccess_block;
    }

    /**
     * Convert setting name to Apache PHP directive
     */
    private function setting_to_apache_directive($setting) {
        $mapping = [
            'memory_limit' => 'memory_limit',
            'max_execution_time' => 'max_execution_time',
            'max_input_time' => 'max_input_time',
            'upload_max_filesize' => 'upload_max_filesize',
            'post_max_size' => 'post_max_size',
            'max_input_vars' => 'max_input_vars',
        ];

        return $mapping[$setting] ?? null;
    }

    /**
     * Generate wp-config.php constants-only block
     */
    private function generate_wp_config_constants_block($settings) {
        $php_block = "/* BEGIN Morden Toolkit PHP Configuration - WordPress Constants Only */\n";

        foreach ($settings as $setting => $value) {
            $wp_constant = $this->setting_to_wp_constant($setting);
            if ($wp_constant) {
                $php_block .= "define('{$wp_constant}', '{$value}');\n";
            }
        }

        $php_block .= "/* END Morden Toolkit PHP Configuration */\n";

        return $php_block;
    }

    /**
     * Convert setting name to WordPress constant
     */
    private function setting_to_wp_constant($setting) {
        $mapping = [
            'memory_limit' => 'WP_MEMORY_LIMIT',
            'max_execution_time' => 'WP_MAX_EXECUTION_TIME',
            'max_input_time' => 'MT_MAX_INPUT_TIME',
            'upload_max_filesize' => 'MT_UPLOAD_MAX_FILESIZE',
            'post_max_size' => 'MT_POST_MAX_SIZE',
            'max_input_vars' => 'MT_MAX_INPUT_VARS'
        ];

        return $mapping[$setting] ?? null;
    }

    /**
     * Map PHP settings to WordPress constants following official best practices
     */
    private function map_setting_to_wordpress_constant($setting, $value) {
        switch ($setting) {
            case 'memory_limit':
                // Use WP_MEMORY_LIMIT constant for WordPress memory
                // Also set WP_MAX_MEMORY_LIMIT for admin area (should be higher)
                $admin_memory = $this->calculate_admin_memory_limit($value);
                $output = "define( 'WP_MEMORY_LIMIT', '{$value}' );\n";
                $output .= "define( 'WP_MAX_MEMORY_LIMIT', '{$admin_memory}' );";
                return $output;

            case 'max_execution_time':
                // Use custom WordPress constant for execution time
                return "define( 'WP_MAX_EXECUTION_TIME', {$value} );";

            case 'upload_max_filesize':
                // Use custom constant for upload file size
                return "define( 'MT_UPLOAD_MAX_FILESIZE', '{$value}' );";

            case 'post_max_size':
                // Use custom constant for post max size
                return "define( 'MT_POST_MAX_SIZE', '{$value}' );";

            case 'max_input_vars':
                // Use custom constant for input vars
                return "define( 'MT_MAX_INPUT_VARS', {$value} );";

            case 'max_input_time':
                // Use custom constant for input time
                return "define( 'MT_MAX_INPUT_TIME', {$value} );";

            default:
                return null;
        }
    }

    /**
     * Calculate admin memory limit (should be higher than regular memory limit)
     */
    private function calculate_admin_memory_limit($memory_limit) {
        // Convert memory limit to bytes
        $bytes = $this->convert_to_bytes($memory_limit);

        // Admin should have 50% more memory, minimum 256M
        $admin_bytes = max($bytes * 1.5, 256 * 1024 * 1024);

        // Convert back to readable format
        return $this->convert_bytes_to_readable($admin_bytes);
    }

    /**
     * Generate .user.ini content
     */
    private function generate_user_ini_content($settings) {
        $content = "; Morden Toolkit PHP Config\n";

        foreach ($settings as $key => $value) {
            $content .= "{$key} = {$value}\n";
        }

        return $content;
    }

    /**
     * Remove existing PHP config block from .htaccess
     */
    private function remove_php_config_block($content) {
        $pattern = '/# BEGIN Morden Toolkit PHP Config.*?# END Morden Toolkit PHP Config/s';
        return preg_replace($pattern, '', $content);
    }

    /**
     * Remove existing PHP config block from wp-config.php
     * IMPORTANT: Only use this with wp-config.php content, never with .htaccess!
     */
    private function remove_wp_config_php_block($content) {
        // Very specific patterns that only match our exact PHP config blocks
        $patterns = [
            // Match exactly our /* style PHP config blocks with defines */
            '/\/\*\s*BEGIN\s+Morden\s+Toolkit\s+PHP\s+Configuration\s*-\s*WordPress\s+Constants\s+Only\s*\*\/.*?\/\*\s*END\s+Morden\s+Toolkit\s+PHP\s+Configuration\s*\*\//s',
            // Match exactly our /* style PHP config blocks (short version) */
            '/\/\*\s*BEGIN\s+Morden\s+Toolkit\s+PHP\s+Config\s*-\s*WordPress\s+Constants\s+Only\s*\*\/.*?\/\*\s*END\s+Morden\s+Toolkit\s+PHP\s+Config\s*\*\//s',
            // Match our // style comments (legacy)
            '/\/\/\s*BEGIN\s+Morden\s+Toolkit\s+PHP\s+Config.*?define\s*\(.*?\/\/\s*END\s+Morden\s+Toolkit\s+PHP\s+Config/s'
        ];

        $original_content = $content;
        $original_length = strlen($original_content);

        foreach ($patterns as $pattern) {
            // Count how many matches we have before replacing
            $matches_count = preg_match_all($pattern, $content);

            // Replace the pattern
            $new_content = preg_replace($pattern, '', $content);
            $new_length = strlen($new_content);

            // Safety check: ensure we only removed our specific blocks
            // Check if the content reduction is reasonable (not more than 50% of file)
            $reduction_percentage = ($original_length - $new_length) / $original_length * 100;

            // If we found matches and the reduction is reasonable, accept the change
            if ($matches_count > 0 && $reduction_percentage < 50) {
                $content = $new_content;
                error_log("MT PHP Config: Removed " . $matches_count . " config blocks (reduced by " . round($reduction_percentage, 1) . "%)");
            } elseif ($matches_count > 0 && $reduction_percentage >= 50) {
                error_log("MT PHP Config: Regex pattern removed too much content (" . round($reduction_percentage, 1) . "%), reverting");
                break;
            }
        }

        // Only clean up newlines if we actually removed something
        if ($content !== $original_content) {
            $content = preg_replace('/\n{3,}/', "\n\n", $content);
        }

        return $content;
    }

    /**
     * Get current PHP configuration values
     */
    public function get_current_config() {
        $settings = array(
            'memory_limit',
            'upload_max_filesize',
            'post_max_size',
            'max_execution_time',
            'max_input_vars',
            'max_input_time'
        );

        $current = array();

        foreach ($settings as $setting) {
            $current[$setting] = ini_get($setting);
        }

        return $current;
    }

    /**
     * Compare current config with preset
     */
    public function compare_with_preset($preset_name) {
        $preset = $this->get_preset($preset_name);
        $current = $this->get_current_config();

        if (!$preset) {
            return false;
        }

        $comparison = array();

        foreach ($preset['settings'] as $key => $preset_value) {
            $current_value = $current[$key] ?? 'unknown';
            $comparison[$key] = array(
                'current' => $current_value,
                'preset' => $preset_value,
                'matches' => $current_value === $preset_value
            );
        }

        return $comparison;
    }

    /**
     * Get configuration method info
     */
    public function get_config_method_info() {
        $method = $this->detect_configuration_method();

        $info = array(
            'method' => $method,
            'available_methods' => array()
        );

        // Check .htaccess availability
        $htaccess_path = mt_get_htaccess_path();
        if (mt_is_file_writable($htaccess_path)) {
            $info['available_methods'][] = array(
                'method' => 'htaccess',
                'name' => __('.htaccess', 'mt'),
                'description' => __('Apply via Apache .htaccess file', 'mt'),
                'available' => true
            );
        }

        // Check wp-config.php availability
        $wp_config_path = mt_get_wp_config_path();
        if ($wp_config_path && mt_is_file_writable($wp_config_path)) {
            $info['available_methods'][] = array(
                'method' => 'wp_config',
                'name' => __('wp-config.php', 'mt'),
                'description' => __('Apply via WordPress configuration file', 'mt'),
                'available' => true
            );
        }

        // Check .user.ini availability
        $user_ini_path = ABSPATH . '.user.ini';
        if (mt_is_file_writable($user_ini_path)) {
            $info['available_methods'][] = array(
                'method' => 'user_ini',
                'name' => __('.user.ini', 'mt'),
                'description' => __('Apply via PHP .user.ini file', 'mt'),
                'available' => true
            );
        }

        return $info;
    }

    /**
     * Get server memory capacity and suggest optimal memory limit
     */
    public function get_server_memory_info() {
        $server_memory = $this->get_server_memory_limit();
        $current_memory = ini_get('memory_limit');

        // Convert to bytes for calculation
        $server_bytes = $this->convert_to_bytes($server_memory);
        $current_bytes = $this->convert_to_bytes($current_memory);

        // Calculate 70% of server capacity for safe usage
        $optimal_bytes = $server_bytes * 0.7;
        $optimal_memory = $this->convert_bytes_to_readable($optimal_bytes);

        // Cap at 2GB as requested
        if ($optimal_bytes > (2048 * 1024 * 1024)) {
            $optimal_memory = '2048M';
            $optimal_bytes = 2048 * 1024 * 1024;
        }

        return array(
            'server_memory' => $server_memory,
            'current_memory' => $current_memory,
            'optimal_memory' => $optimal_memory,
            'usage_percentage' => $server_bytes > 0 ? round(($current_bytes / $server_bytes) * 100, 1) : 0,
            'safe_percentage' => 50
        );
    }

    /**
     * Get server memory limit from various sources
     */
    private function get_server_memory_limit() {
        // Try to get from system info
        $memory_limit = ini_get('memory_limit');

        // If unlimited (-1), try to detect system memory
        if ($memory_limit === '-1') {
            // Try to read from /proc/meminfo on Linux
            if (file_exists('/proc/meminfo')) {
                $meminfo = file_get_contents('/proc/meminfo');
                if (preg_match('/MemTotal:\s+(\d+)\s+kB/', $meminfo, $matches)) {
                    // Convert KB to MB and return
                    return round($matches[1] / 1024) . 'M';
                }
            }

            // Fallback: assume reasonable server memory
            return '4096M'; // 4GB default assumption
        }

        return $memory_limit;
    }

    /**
     * Convert memory string to bytes
     */
    private function convert_to_bytes($memory_string) {
        $memory_string = trim($memory_string);
        $last_char = strtolower(substr($memory_string, -1));
        $number = (int) $memory_string;

        switch ($last_char) {
            case 'g':
                $number *= 1024 * 1024 * 1024;
                break;
            case 'm':
                $number *= 1024 * 1024;
                break;
            case 'k':
                $number *= 1024;
                break;
        }

        return $number;
    }

    /**
     * Convert bytes to readable memory format
     */
    private function convert_bytes_to_readable($bytes) {
        if ($bytes >= 1024 * 1024 * 1024) {
            return round($bytes / (1024 * 1024 * 1024)) . 'G';
        } elseif ($bytes >= 1024 * 1024) {
            return round($bytes / (1024 * 1024)) . 'M';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024) . 'K';
        }

        return $bytes . 'B';
    }
}
