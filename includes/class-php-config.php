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
        $presets_file = MORDEN_TOOLKIT_PLUGIN_DIR . 'data/presets/php-config.json';

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
            )
        );
    }

    /**
     * Get all available presets
     */
    public function get_presets() {
        return $this->presets;
    }

    /**
     * Get specific preset
     */
    public function get_preset($preset_name) {
        return isset($this->presets[$preset_name]) ? $this->presets[$preset_name] : null;
    }

    /**
     * Apply configuration preset
     */
    public function apply_preset($preset_name) {
        $preset = $this->get_preset($preset_name);

        if (!$preset) {
            return false;
        }

        $method = $this->detect_configuration_method();

        switch ($method) {
            case 'htaccess':
                return $this->apply_via_htaccess($preset['settings']);
            case 'wp_config':
                return $this->apply_via_wp_config($preset['settings']);
            case 'user_ini':
                return $this->apply_via_user_ini($preset['settings']);
            default:
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
        $htaccess_service = new Morden_Toolkit_Htaccess();
        $current_content = $htaccess_service->get_htaccess_content();

        // Remove existing PHP configuration block
        $current_content = $this->remove_php_config_block($current_content);

        // Add new PHP configuration block
        $php_block = $this->generate_htaccess_php_block($settings);
        $new_content = $current_content . "\n\n" . $php_block;

        return $htaccess_service->save_htaccess($new_content);
    }

    /**
     * Apply configuration via wp-config.php
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
     * Generate wp-config.php PHP configuration block
     */
    private function generate_wp_config_php_block($settings) {
        $block = "// BEGIN Morden Toolkit PHP Config\n";

        foreach ($settings as $key => $value) {
            $block .= "ini_set('{$key}', '{$value}');\n";
        }

        $block .= "// END Morden Toolkit PHP Config\n";

        return $block;
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
     */
    private function remove_wp_config_php_block($content) {
        $pattern = '/\/\/ BEGIN Morden Toolkit PHP Config.*?\/\/ END Morden Toolkit PHP Config\s*/s';
        return preg_replace($pattern, '', $content);
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
}
