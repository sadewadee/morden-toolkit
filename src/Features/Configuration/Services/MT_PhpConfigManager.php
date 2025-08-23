<?php

declare(strict_types=1);

namespace ModernToolkit\Features\Configuration\Services;

use ModernToolkit\Infrastructure\Contracts\ServiceInterface;
use ModernToolkit\Infrastructure\Exceptions\ServiceException;

/**
 * PHP Configuration Manager Service
 *
 * Provides comprehensive PHP configuration management including
 * preset application, custom settings validation, and multi-method deployment.
 */
class MT_PhpConfigManager implements ServiceInterface
{
    private array $presets = [];
    private array $supportedMethods = ['wp_config', 'htaccess', 'php_ini'];

    public function __construct()
    {
        $this->loadPresets();
    }

    /**
     * Initialize the service
     */
    public function init(): void
    {
        $this->initialize();
    }

    /**
     * Get the service name
     */
    public function getName(): string
    {
        return 'PHP Configuration Manager';
    }

    /**
     * Check if the service is enabled
     */
    public function isEnabled(): bool
    {
        return true; // Always enabled
    }

    /**
     * Initialize the service
     */
    public function initialize(): void
    {
        // Service initialization logic
    }

    /**
     * Get all available configuration presets
     *
     * @return array Array of configuration presets
     */
    public function getPresets(): array
    {
        try {
            $presets = $this->presets;

            if (isset($presets['custom'])) {
                // Check if user has saved custom settings
                $savedCustomSettings = \get_option('mt_custom_preset_settings', null);

                if ($savedCustomSettings && is_array($savedCustomSettings)) {
                    // Use saved custom settings
                    $presets['custom']['settings'] = $savedCustomSettings;
                    $presets['custom']['description'] = 'User-defined configuration';
                } else {
                    // Use default optimal memory for new custom preset
                    $memoryInfo = $this->getServerMemoryInfo();
                    $presets['custom']['settings']['memory_limit'] = $memoryInfo['optimal_memory'];
                    $presets['custom']['description'] = sprintf(
                        'Maximum performance with %s memory (%d%% server capacity)',
                        $memoryInfo['optimal_memory'],
                        $memoryInfo['safe_percentage']
                    );
                }
            }

            return $presets;
        } catch (\Exception $e) {
            error_log('MT PHP Config: Error getting presets - ' . $e->getMessage());
            return $this->presets;
        }
    }

    /**
     * Get a specific configuration preset
     *
     * @param string $presetName The preset name to retrieve
     * @return array|null The preset configuration or null if not found
     */
    public function getPreset(string $presetName): ?array
    {
        if (empty($presetName)) {
            return null;
        }

        return $this->presets[$presetName] ?? null;
    }

    /**
     * Apply a configuration preset
     *
     * @param string $presetName The preset to apply
     * @return bool Success status
     */
    public function applyPreset(string $presetName): bool
    {
        try {
            if (empty($presetName) || !isset($this->presets[$presetName])) {
                error_log("MT PHP Config: Preset '$presetName' not found");
                return false;
            }

            $preset = $this->presets[$presetName];
            if (empty($preset['settings'])) {
                error_log("MT PHP Config: Preset '$presetName' has no settings");
                return false;
            }

            $originalValues = $this->getCurrentConfig();

            // Try wp-config method first (safest)
            if ($this->tryApplyViaWpConfigWithTesting($preset['settings'], $originalValues)) {
                error_log("MT PHP Config: Applied preset '$presetName' via wp-config");
                return true;
            }

            // Try php.ini if writable
            $phpIniPath = ABSPATH . 'php.ini';
            if (is_writable($phpIniPath)) {
                if ($this->applyViaPhpIni($preset['settings'])) {
                    error_log("MT PHP Config: Applied preset '$presetName' via php.ini");
                    return true;
                }
            }

            // Try .htaccess for Apache servers
            $htaccessPath = ABSPATH . '.htaccess';
            if (is_writable($htaccessPath)) {
                $serverType = $this->detectServerType();
                if ($serverType === 'apache') {
                    if ($this->tryApplyViaHtaccessWithTesting($preset['settings'], $originalValues)) {
                        error_log("MT PHP Config: Applied preset '$presetName' via .htaccess");
                        return true;
                    }
                }
            }

            error_log("MT PHP Config: Failed to apply preset '$presetName' - no suitable method available");
            return false;
        } catch (\Exception $e) {
            error_log("MT PHP Config: Exception applying preset '$presetName' - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Detect the best configuration method available
     *
     * @return string The recommended configuration method
     */
    public function detectConfigurationMethod(): string
    {
        $methods = [];

        // Check wp-config method
        $wpConfigPath = $this->getWpConfigPath();
        if ($wpConfigPath && is_writable($wpConfigPath)) {
            $methods[] = 'wp_config';
        }

        // Check php.ini method
        $phpIniPath = ABSPATH . 'php.ini';
        if (is_writable($phpIniPath)) {
            $methods[] = 'php_ini';
        }

        // Check .htaccess method (Apache only)
        $htaccessPath = ABSPATH . '.htaccess';
        if (is_writable($htaccessPath) && $this->detectServerType() === 'apache') {
            $methods[] = 'htaccess';
        }

        return !empty($methods) ? $methods[0] : 'none';
    }

    /**
     * Get current PHP configuration values
     *
     * @return array Current PHP settings
     */
    public function getCurrentConfig(): array
    {
        $settings = [
            'memory_limit' => ini_get('memory_limit'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'max_execution_time' => ini_get('max_execution_time'),
            'max_input_vars' => ini_get('max_input_vars'),
            'max_input_time' => ini_get('max_input_time')
        ];

        // Convert boolean values to string
        foreach ($settings as $key => $value) {
            if (is_bool($value)) {
                $settings[$key] = $value ? '1' : '0';
            }
        }

        return $settings;
    }

    /**
     * Compare current settings with a preset
     *
     * @param string $presetName The preset to compare against
     * @return array Comparison results
     */
    public function compareWithPreset(string $presetName): array
    {
        $preset = $this->getPreset($presetName);
        if (!$preset || !isset($preset['settings'])) {
            return ['error' => 'Preset not found'];
        }

        $current = $this->getCurrentConfig();
        $comparison = [];

        foreach ($preset['settings'] as $setting => $targetValue) {
            $currentValue = $current[$setting] ?? 'unknown';
            $comparison[$setting] = [
                'current' => $currentValue,
                'target' => $targetValue,
                'matches' => $this->compareSettingValues($currentValue, $targetValue)
            ];
        }

        return $comparison;
    }

    /**
     * Validate custom PHP settings
     *
     * @param array $settings The settings to validate
     * @return array Validation results
     */
    public function validateCustomSettings(array $settings): array
    {
        $errors = [];
        $warnings = [];

        foreach ($settings as $setting => $value) {
            switch ($setting) {
                case 'memory_limit':
                    $bytes = $this->convertToBytes($value);
                    if ($bytes < 128 * 1024 * 1024) {
                        $warnings[] = "Memory limit {$value} is quite low for WordPress";
                    }
                    if ($bytes > 8 * 1024 * 1024 * 1024) {
                        $errors[] = "Memory limit {$value} is extremely high";
                    }
                    break;

                case 'upload_max_filesize':
                case 'post_max_size':
                    $bytes = $this->convertToBytes($value);
                    if ($bytes > 1024 * 1024 * 1024) {
                        $warnings[] = "{$setting} {$value} is very high";
                    }
                    break;

                case 'max_execution_time':
                    if ($value > 600) {
                        $warnings[] = "Execution time {$value}s is very high";
                    }
                    break;
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }

    /**
     * Update custom preset settings
     *
     * @param array $settings The custom settings to save
     * @return bool Success status
     */
    public function updateCustomPreset(array $settings): bool
    {
        $validation = $this->validateCustomSettings($settings);
        if (!$validation['valid']) {
            return false;
        }

        return \update_option('mt_custom_preset_settings', $settings);
    }

    /**
     * Reset custom preset to defaults
     *
     * @return bool Success status
     */
    public function resetCustomPreset(): bool
    {
        return \delete_option('mt_custom_preset_settings');
    }

    /**
     * Get configuration method information
     *
     * @return array Available methods and their status
     */
    public function getConfigMethodInfo(): array
    {
        $methods = [];

        // wp-config method
        $wpConfigPath = $this->getWpConfigPath();
        $methods['wp_config'] = [
            'name' => 'WP-Config Constants',
            'available' => $wpConfigPath && is_writable($wpConfigPath),
            'recommended' => true,
            'description' => 'Safest method using WordPress constants'
        ];

        // php.ini method
        $phpIniPath = ABSPATH . 'php.ini';
        $methods['php_ini'] = [
            'name' => 'PHP.ini File',
            'available' => is_writable($phpIniPath),
            'recommended' => false,
            'description' => 'Direct PHP configuration file'
        ];

        // .htaccess method
        $htaccessPath = ABSPATH . '.htaccess';
        $methods['htaccess'] = [
            'name' => 'Apache .htaccess',
            'available' => is_writable($htaccessPath) && $this->detectServerType() === 'apache',
            'recommended' => false,
            'description' => 'Apache server configuration'
        ];

        return $methods;
    }

    /**
     * Get server memory information and recommendations
     *
     * @return array Server memory analysis
     */
    public function getServerMemoryInfo(): array
    {
        $memoryInfo = [
            'total_memory' => 'Unknown',
            'current_limit' => ini_get('memory_limit'),
            'recommended_memory' => '512M',
            'optimal_memory' => '1024M',
            'safe_percentage' => 70
        ];

        // Try to detect total server memory
        if (function_exists('shell_exec')) {
            $output = shell_exec('free -m 2>/dev/null | grep "^Mem:" | awk \'{print $2}\'');
            if ($output) {
                $totalMB = (int) trim($output);
                if ($totalMB > 0) {
                    $memoryInfo['total_memory'] = $totalMB . 'M';
                    $safeMemoryMB = (int) ($totalMB * 0.7); // 70% of total
                    $memoryInfo['optimal_memory'] = $safeMemoryMB . 'M';
                }
            }
        }

        return $memoryInfo;
    }

    /**
     * Load default configuration presets
     */
    private function loadPresets(): void
    {
        $this->presets = [
            'basic' => [
                'name' => 'Basic',
                'description' => 'Suitable for small sites with light traffic',
                'settings' => [
                    'memory_limit' => '256M',
                    'upload_max_filesize' => '8M',
                    'post_max_size' => '16M',
                    'max_execution_time' => '60',
                    'max_input_vars' => '1000',
                    'max_input_time' => '60'
                ]
            ],
            'medium' => [
                'name' => 'Medium',
                'description' => 'Good for most WordPress sites',
                'settings' => [
                    'memory_limit' => '512M',
                    'upload_max_filesize' => '16M',
                    'post_max_size' => '32M',
                    'max_execution_time' => '120',
                    'max_input_vars' => '3000',
                    'max_input_time' => '120'
                ]
            ],
            'high' => [
                'name' => 'High Performance',
                'description' => 'For high-traffic sites and complex applications',
                'settings' => [
                    'memory_limit' => '1024M',
                    'upload_max_filesize' => '32M',
                    'post_max_size' => '64M',
                    'max_execution_time' => '300',
                    'max_input_vars' => '5000',
                    'max_input_time' => '300'
                ]
            ],
            'custom' => [
                'name' => 'Custom High Memory',
                'description' => 'Maximum performance with 2GB memory (70% server capacity)',
                'settings' => [
                    'memory_limit' => '2048M',
                    'upload_max_filesize' => '64M',
                    'post_max_size' => '128M',
                    'max_execution_time' => '600',
                    'max_input_vars' => '10000',
                    'max_input_time' => '600'
                ]
            ]
        ];
    }

    /**
     * Try applying configuration via wp-config with testing
     */
    private function tryApplyViaWpConfigWithTesting(array $settings, array $originalValues): bool
    {
        // Implementation would use WpConfigIntegration class
        // For now, return false to try other methods
        return false;
    }

    /**
     * Try applying configuration via .htaccess with testing
     */
    private function tryApplyViaHtaccessWithTesting(array $settings, array $originalValues): bool
    {
        // Implementation would use Htaccess service
        // For now, return false
        return false;
    }

    /**
     * Apply configuration via php.ini file
     */
    private function applyViaPhpIni(array $settings): bool
    {
        $phpIniPath = ABSPATH . 'php.ini';
        if (!is_writable($phpIniPath)) {
            return false;
        }

        $content = "; Morden Toolkit PHP Config\n";
        foreach ($settings as $key => $value) {
            $content .= "{$key} = {$value}\n";
        }

        return file_put_contents($phpIniPath, $content) !== false;
    }

    /**
     * Detect server type (Apache, Nginx, etc.)
     */
    private function detectServerType(): string
    {
        $server = $_SERVER['SERVER_SOFTWARE'] ?? '';

        if (stripos($server, 'apache') !== false) {
            return 'apache';
        } elseif (stripos($server, 'nginx') !== false) {
            return 'nginx';
        } elseif (stripos($server, 'litespeed') !== false) {
            return 'litespeed';
        }

        return 'unknown';
    }

    /**
     * Get wp-config.php file path
     */
    private function getWpConfigPath(): ?string
    {
        $paths = [
            ABSPATH . 'wp-config.php',
            dirname(ABSPATH) . '/wp-config.php'
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Convert memory string to bytes
     */
    private function convertToBytes(string $memoryString): int
    {
        $memoryString = trim($memoryString);
        $lastChar = strtolower(substr($memoryString, -1));
        $number = (int) substr($memoryString, 0, -1);

        switch ($lastChar) {
            case 'g':
                return $number * 1024 * 1024 * 1024;
            case 'm':
                return $number * 1024 * 1024;
            case 'k':
                return $number * 1024;
            default:
                return (int) $memoryString;
        }
    }

    /**
     * Compare two setting values
     */
    private function compareSettingValues(string $current, string $target): bool
    {
        // For memory/size values, convert to bytes for comparison
        if (in_array(substr($current, -1), ['M', 'G', 'K']) ||
            in_array(substr($target, -1), ['M', 'G', 'K'])) {
            return $this->convertToBytes($current) >= $this->convertToBytes($target);
        }

        // For numeric values
        if (is_numeric($current) && is_numeric($target)) {
            return (int) $current >= (int) $target;
        }

        // String comparison
        return $current === $target;
    }
}