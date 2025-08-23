<?php

namespace ModernToolkit\Core;

use ModernToolkit\Core\Bootstrap\MT_Autoloader;
use ModernToolkit\Core\MT_ServiceContainer;
use ModernToolkit\Core\MT_EventDispatcher;
use ModernToolkit\Core\MT_FeatureRegistry;

class MT_Plugin {
    private static $instance = null;
    private $container;
    private $eventDispatcher;
    private $featureRegistry;
    private $initialized = false;

    public static function getInstance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->setupContainer();
        $this->setupEventDispatcher();
        $this->setupFeatureRegistry();
    }

    public function init(): void {
        if ($this->initialized) {
            return;
        }

        $this->setupAutoloader();
        $this->registerCoreServices();
        $this->registerFeatures();
        $this->bootFeatures();
        $this->registerBackwardCompatibility();
        $this->registerAdminHooks();

        // Mark that we're using the new architecture
        \update_option('mt_using_new_architecture', true);

        $this->initialized = true;
    }

    public function getContainer(): MT_ServiceContainer {
        return $this->container;
    }

    public function getEventDispatcher(): MT_EventDispatcher {
        return $this->eventDispatcher;
    }

    public function getFeatureRegistry(): MT_FeatureRegistry {
        return $this->featureRegistry;
    }

    /**
     * Legacy compatibility method - maps old get_service() calls to new architecture
     * @param string $name Legacy service name
     * @return object|null Service instance or null if not found
     */
    public function get_service(string $name) {
        // Map legacy service names to new architecture
        $serviceMap = [
            'debug' => function() {
                if ($this->featureRegistry->has('debug')) {
                    $feature = $this->featureRegistry->get('debug');
                    if ($this->container->has('debug.manager')) {
                        return $this->container->get('debug.manager');
                    }
                }
                // Fallback to legacy-style wrapper
                return new class {
                    public function get_debug_status() {
                        $actual_wp_debug = defined('WP_DEBUG') && WP_DEBUG;

                        // Sync stored option with actual status
                        if (function_exists('get_option') && function_exists('update_option')) {
                            $stored_option = \get_option('mt_debug_enabled', false);
                            if ($stored_option !== $actual_wp_debug) {
                                \update_option('mt_debug_enabled', $actual_wp_debug);
                            }
                        }

                        $display_errors = ini_get('display_errors') == '1' || ini_get('display_errors') === 'On';

                        return [
                            'enabled' => $actual_wp_debug, // Use actual status, not stored option
                            'wp_debug' => $actual_wp_debug,
                            'wp_debug_log' => defined('WP_DEBUG_LOG') && WP_DEBUG_LOG,
                            'wp_debug_display' => defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY,
                            'script_debug' => defined('SCRIPT_DEBUG') && SCRIPT_DEBUG,
                            'savequeries' => defined('SAVEQUERIES') && SAVEQUERIES,
                            'display_errors' => $display_errors,
                            'log_file_exists' => file_exists(\mt_get_debug_log_path()),
                            'log_file_size' => file_exists(\mt_get_debug_log_path()) ?
                                \mt_format_bytes(filesize(\mt_get_debug_log_path())) : '0 B',
                            'query_log_file_exists' => file_exists(\mt_get_query_log_path()),
                            'query_log_file_size' => file_exists(\mt_get_query_log_path()) ?
                                \mt_format_bytes(filesize(\mt_get_query_log_path())) : '0 B',
                            'query_log_total_size' => \mt_format_bytes($this->get_query_log_total_size()),
                            'query_log_max_size' => \mt_format_bytes(\mt_get_query_log_max_size())
                        ];
                    }

                    private function get_query_log_total_size() {
                        $log_path = \mt_get_query_log_path();
                        $total_size = 0;

                        // Get main log file size
                        if (file_exists($log_path)) {
                            $total_size += filesize($log_path);
                        }

                        // Get backup files size
                        $backup_pattern = dirname($log_path) . '/query-*.log';
                        $backup_files = glob($backup_pattern);

                        if ($backup_files) {
                            foreach ($backup_files as $backup_file) {
                                if (file_exists($backup_file)) {
                                    $total_size += filesize($backup_file);
                                }
                            }
                        }

                        return $total_size;
                    }
                };
            },
            'query_monitor' => function() {
                // Get the legacy QueryMonitor instance from the legacy plugin if available
                $legacyPlugin = \ModernToolkit\Plugin::get_instance();
                if ($legacyPlugin) {
                    $legacyQueryMonitor = $legacyPlugin->get_service('query_monitor');
                    if ($legacyQueryMonitor) {
                        return $legacyQueryMonitor;
                    }
                }

                // Fallback: create new legacy QueryMonitor instance
                if (class_exists('ModernToolkit\\QueryMonitor')) {
                    return new \ModernToolkit\QueryMonitor();
                }

                // Final fallback to basic wrapper
                return new class {
                    public function get_metrics() {
                        return \get_transient('mt_metrics_' . \get_current_user_id()) ?: [];
                    }

                    public function add_admin_bar_metrics($wp_admin_bar) {
                        // Basic implementation
                        return false;
                    }
                };
            },
            'htaccess' => function() {
                if ($this->featureRegistry->has('file_management')) {
                    $feature = $this->featureRegistry->get('file_management');
                    if ($this->container->has('file_management.htaccess_manager')) {
                        return $this->container->get('file_management.htaccess_manager');
                    }
                }
                // Fallback to legacy-style wrapper
                return new class {
                    public function get_htaccess_info() {
                        $htaccess_path = \mt_get_htaccess_path();
                        return [
                            'path' => $htaccess_path,
                            'exists' => file_exists($htaccess_path),
                            'writable' => \mt_is_file_writable($htaccess_path),
                            'size' => file_exists($htaccess_path) ? filesize($htaccess_path) : 0
                        ];
                    }

                    public function get_backups() {
                        return \get_option('mt_htaccess_backups', []);
                    }

                    public function get_htaccess_content() {
                        $htaccess_path = \mt_get_htaccess_path();
                        return file_exists($htaccess_path) ? file_get_contents($htaccess_path) : '';
                    }

                    public function save_htaccess(string $content): bool {
                        try {
                            $htaccess_path = \mt_get_htaccess_path();

                            // Create backup before saving
                            if (file_exists($htaccess_path)) {
                                $backups = \get_option('mt_htaccess_backups', []);
                                if (!is_array($backups)) {
                                    $backups = [];
                                }
                                $backup_content = file_get_contents($htaccess_path);
                                $backups[] = [
                                    'content' => $backup_content,
                                    'timestamp' => \current_time('mysql'),
                                    'size' => strlen($backup_content)
                                ];

                                // Keep only last 10 backups
                                if (count($backups) > 10) {
                                    $backups = array_slice($backups, -10);
                                }

                                \update_option('mt_htaccess_backups', $backups);
                            }

                            return file_put_contents($htaccess_path, $content) !== false;
                        } catch (\Exception $e) {
                            \error_log('MT Htaccess Save Error: ' . $e->getMessage());
                            return false;
                        }
                    }

                    public function restore_htaccess(int $backup_index): bool {
                        try {
                            $backups = \get_option('mt_htaccess_backups', []);

                            if (!is_array($backups) || !isset($backups[$backup_index])) {
                                return false;
                            }

                            $htaccess_path = \mt_get_htaccess_path();
                            $backup_content = $backups[$backup_index]['content'];

                            return file_put_contents($htaccess_path, $backup_content) !== false;
                        } catch (\Exception $e) {
                            \error_log('MT Htaccess Restore Error: ' . $e->getMessage());
                            return false;
                        }
                    }

                    public function get_common_snippets() {
                        return [
                            'gzip_compression' => [
                                'title' => 'Enable Gzip Compression',
                                'description' => 'Compress files to improve page load speed',
                                'content' => "# Enable Gzip compression\n<IfModule mod_deflate.c>\n    AddOutputFilterByType DEFLATE text/plain\n    AddOutputFilterByType DEFLATE text/html\n    AddOutputFilterByType DEFLATE text/xml\n    AddOutputFilterByType DEFLATE text/css\n    AddOutputFilterByType DEFLATE application/xml\n    AddOutputFilterByType DEFLATE application/xhtml+xml\n    AddOutputFilterByType DEFLATE application/rss+xml\n    AddOutputFilterByType DEFLATE application/javascript\n    AddOutputFilterByType DEFLATE application/x-javascript\n</IfModule>"
                            ],
                            'browser_caching' => [
                                'title' => 'Browser Caching',
                                'description' => 'Set browser cache headers for better performance',
                                'content' => "# Browser Caching\n<IfModule mod_expires.c>\n    ExpiresActive On\n    ExpiresByType text/css \"access plus 1 year\"\n    ExpiresByType application/javascript \"access plus 1 year\"\n    ExpiresByType image/png \"access plus 1 year\"\n    ExpiresByType image/jpg \"access plus 1 year\"\n    ExpiresByType image/jpeg \"access plus 1 year\"\n    ExpiresByType image/gif \"access plus 1 year\"\n</IfModule>"
                            ],
                            'security_headers' => [
                                'title' => 'Security Headers',
                                'description' => 'Add security headers to protect your site',
                                'content' => "# Security Headers\n<IfModule mod_headers.c>\n    Header always set X-Content-Type-Options nosniff\n    Header always set X-Frame-Options DENY\n    Header always set X-XSS-Protection \"1; mode=block\"\n    Header always set Strict-Transport-Security \"max-age=31536000; includeSubDomains\"\n</IfModule>"
                            ]
                        ];
                    }
                };
            },
            'php_config' => function() {
                if ($this->featureRegistry->has('configuration')) {
                    $feature = $this->featureRegistry->get('configuration');
                    if ($this->container->has('configuration.php_config')) {
                        return $this->container->get('configuration.php_config');
                    }
                }
                // Fallback to legacy-style wrapper
                return new class {
                    public function get_presets() {
                        return [
                            'basic' => [
                                'name' => 'Basic Resources',
                                'description' => 'Minimal configuration for small WordPress sites',
                                'settings' => [
                                    'memory_limit' => '128M',
                                    'upload_max_filesize' => '32M',
                                    'post_max_size' => '32M',
                                    'max_execution_time' => '120',
                                    'max_input_vars' => '1000',
                                    'max_input_time' => '120'
                                ]
                            ],
                            'medium' => [
                                'name' => 'Medium Resources',
                                'description' => 'Balanced configuration for most WordPress sites',
                                'settings' => [
                                    'memory_limit' => '256M',
                                    'upload_max_filesize' => '64M',
                                    'post_max_size' => '64M',
                                    'max_execution_time' => '300',
                                    'max_input_vars' => '3000',
                                    'max_input_time' => '300'
                                ]
                            ],
                            'high' => [
                                'name' => 'High Performance',
                                'description' => 'Optimized configuration for large WordPress sites',
                                'settings' => [
                                    'memory_limit' => '512M',
                                    'upload_max_filesize' => '128M',
                                    'post_max_size' => '128M',
                                    'max_execution_time' => '600',
                                    'max_input_vars' => '5000',
                                    'max_input_time' => '600'
                                ]
                            ],
                            'custom' => [
                                'name' => 'Custom Configuration',
                                'description' => 'Customizable settings for specific needs',
                                'settings' => \get_option('mt_custom_preset_settings', [
                                    'memory_limit' => '256M',
                                    'upload_max_filesize' => '64M',
                                    'post_max_size' => '64M',
                                    'max_execution_time' => '300',
                                    'max_input_vars' => '3000',
                                    'max_input_time' => '300'
                                ])
                            ]
                        ];
                    }

                    public function get_config_method_info() {
                        $available_methods = [];
                        $current_method = 'not_available';

                        // Check .htaccess availability
                        $htaccess_path = \mt_get_htaccess_path();
                        if (file_exists($htaccess_path) && is_writable($htaccess_path)) {
                            $available_methods[] = 'htaccess';
                            if ($current_method === 'not_available') {
                                $current_method = 'htaccess';
                            }
                        }

                        // Check wp-config.php availability
                        $wp_config_path = \mt_get_wp_config_path();
                        if ($wp_config_path && is_writable($wp_config_path)) {
                            $available_methods[] = 'wp_config';
                            $current_method = 'wp_config'; // Prefer wp-config
                        }

                        // Check php.ini availability
                        $php_ini_path = ABSPATH . 'php.ini';
                        if (is_writable(dirname($php_ini_path))) {
                            $available_methods[] = 'php_ini';
                        }

                        return [
                            'current_method' => $current_method,
                            'available_methods' => $available_methods,
                            'recommended_method' => !empty($available_methods) ? $available_methods[0] : 'not_available'
                        ];
                    }

                    public function get_current_config() {
                        return [
                            'memory_limit' => ini_get('memory_limit'),
                            'upload_max_filesize' => ini_get('upload_max_filesize'),
                            'post_max_size' => ini_get('post_max_size'),
                            'max_execution_time' => ini_get('max_execution_time'),
                            'max_input_vars' => ini_get('max_input_vars'),
                            'max_input_time' => ini_get('max_input_time')
                        ];
                    }

                    public function get_server_memory_info() {
                        return [
                            'current_usage' => \mt_format_bytes(memory_get_usage()),
                            'peak_usage' => \mt_format_bytes(memory_get_peak_usage()),
                            'limit' => ini_get('memory_limit')
                        ];
                    }

                    public function apply_preset(string $preset): bool {
                        try {
                            $presets = $this->get_presets();
                            if (!isset($presets[$preset])) {
                                return false;
                            }

                            $settings = $presets[$preset]['settings'];
                            return $this->applyPhpSettings($settings);
                        } catch (\Exception $e) {
                            \error_log('MT PHP Config Apply Preset Error: ' . $e->getMessage());
                            return false;
                        }
                    }

                    public function validate_custom_settings(array $settings): array|false {
                        $valid_settings = [];
                        $allowed_settings = [
                            'memory_limit' => '/^\d+[MG]$/',
                            'upload_max_filesize' => '/^\d+[MG]$/',
                            'post_max_size' => '/^\d+[MG]$/',
                            'max_execution_time' => '/^\d+$/',
                            'max_input_vars' => '/^\d+$/',
                            'max_input_time' => '/^\d+$/'
                        ];

                        foreach ($settings as $key => $value) {
                            if (!isset($allowed_settings[$key])) {
                                continue;
                            }

                            $value = sanitize_text_field($value);
                            if (preg_match($allowed_settings[$key], $value)) {
                                $valid_settings[$key] = $value;
                            }
                        }

                        return empty($valid_settings) ? false : $valid_settings;
                    }

                    public function update_custom_preset(array $settings): bool {
                        try {
                            \update_option('mt_custom_preset_settings', $settings);
                            return true;
                        } catch (\Exception $e) {
                            \error_log('MT PHP Config Update Custom Preset Error: ' . $e->getMessage());
                            return false;
                        }
                    }

                    private function applyPhpSettings(array $settings): bool {
                        try {
                            // Use the MT_WpConfigIntegration class to safely apply PHP settings
                            if (class_exists('ModernToolkit\\Infrastructure\\WordPress\\MT_WpConfigIntegration')) {
                                return \ModernToolkit\Infrastructure\WordPress\MT_WpConfigIntegration::apply_php_config_safe($settings);
                            }

                            // Fallback to legacy integration if available
                            if (class_exists('WpConfigIntegration')) {
                                return \WpConfigIntegration::apply_php_config_safe($settings);
                            }

                            // Alternative fallback - htaccess approach for Apache servers
                            $htaccess_path = \mt_get_htaccess_path();
                            if (file_exists($htaccess_path) && is_writable($htaccess_path)) {
                                $htaccess_content = file_get_contents($htaccess_path);

                                // Remove existing MT PHP config block
                                $htaccess_content = preg_replace(
                                    '/# BEGIN MT PHP Config.*?# END MT PHP Config\s*/s',
                                    '',
                                    $htaccess_content
                                );

                                // Add new MT PHP config block
                                $php_block = "\n# BEGIN MT PHP Config\n";
                                foreach ($settings as $key => $value) {
                                    switch ($key) {
                                        case 'memory_limit':
                                            $php_block .= "php_value memory_limit {$value}\n";
                                            break;
                                        case 'upload_max_filesize':
                                            $php_block .= "php_value upload_max_filesize {$value}\n";
                                            break;
                                        case 'post_max_size':
                                            $php_block .= "php_value post_max_size {$value}\n";
                                            break;
                                        case 'max_execution_time':
                                            $php_block .= "php_value max_execution_time {$value}\n";
                                            break;
                                        case 'max_input_vars':
                                            $php_block .= "php_value max_input_vars {$value}\n";
                                            break;
                                        case 'max_input_time':
                                            $php_block .= "php_value max_input_time {$value}\n";
                                            break;
                                    }
                                }
                                $php_block .= "# END MT PHP Config\n";

                                $htaccess_content .= $php_block;
                                return file_put_contents($htaccess_path, $htaccess_content) !== false;
                            }

                            // Final fallback - basic ini_set approach (limited effectiveness)
                            foreach ($settings as $setting => $value) {
                                if (ini_set($setting, $value) === false) {
                                    \error_log('MT PHP Settings: Failed to set ' . $setting . ' to ' . $value);
                                }
                            }

                            return true;
                        } catch (\Exception $e) {
                            \error_log('MT PHP Settings Apply Error: ' . $e->getMessage());
                            return false;
                        }
                    }
                };
            },
            'smtp_logger' => function() {
                if ($this->featureRegistry->has('email_logging')) {
                    $feature = $this->featureRegistry->get('email_logging');
                    if ($this->container->has('email_logging.smtp_logger')) {
                        return $this->container->get('email_logging.smtp_logger');
                    }
                }
                // Fallback to legacy-style wrapper
                return new class {
                    public function get_smtp_status() {
                        return [
                            'enabled' => \get_option('mt_smtp_logging_enabled', false),
                            'total_logs' => 0,
                            'sent_logs' => 0,
                            'failed_logs' => 0,
                            'success_rate' => 0,
                            'last_24h_count' => 0
                        ];
                    }

                    public function get_logs(int $limit = 20, int $offset = 0, array $filters = []): array {
                        try {
                            $log_file = \mt_get_smtp_log_path();
                            if (!file_exists($log_file)) {
                                return [];
                            }

                            $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                            $logs = [];

                            foreach (array_reverse($lines) as $line) {
                                $log_entry = json_decode($line, true);
                                if ($log_entry && $this->matchesFilters($log_entry, $filters)) {
                                    $logs[] = $log_entry;
                                }
                            }

                            return array_slice($logs, $offset, $limit);
                        } catch (\Exception $e) {
                            \error_log('MT SMTP Get Logs Error: ' . $e->getMessage());
                            return [];
                        }
                    }

                    public function get_logs_count(array $filters = []): int {
                        try {
                            $log_file = \mt_get_smtp_log_path();
                            if (!file_exists($log_file)) {
                                return 0;
                            }

                            $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                            $count = 0;

                            foreach ($lines as $line) {
                                $log_entry = json_decode($line, true);
                                if ($log_entry && $this->matchesFilters($log_entry, $filters)) {
                                    $count++;
                                }
                            }

                            return $count;
                        } catch (\Exception $e) {
                            \error_log('MT SMTP Get Logs Count Error: ' . $e->getMessage());
                            return 0;
                        }
                    }

                    public function clear_logs(): bool {
                        try {
                            $log_file = \mt_get_smtp_log_path();
                            if (file_exists($log_file)) {
                                return file_put_contents($log_file, '') !== false;
                            }
                            return true;
                        } catch (\Exception $e) {
                            \error_log('MT SMTP Clear Logs Error: ' . $e->getMessage());
                            return false;
                        }
                    }

                    public function export_logs_csv(array $filters = []): array {
                        try {
                            $logs = $this->get_logs(1000, 0, $filters); // Get up to 1000 logs
                            $csv_data = [];

                            // CSV header
                            $csv_data[] = [
                                'ID', 'Timestamp', 'To', 'From', 'Subject', 'Status', 'Error', 'Server IP', 'Source'
                            ];

                            foreach ($logs as $log) {
                                $csv_data[] = [
                                    $log['id'] ?? '',
                                    $log['timestamp'] ?? '',
                                    $log['to_email'] ?? '',
                                    $log['from_email'] ?? '',
                                    $log['subject'] ?? '',
                                    $log['status'] ?? '',
                                    $log['error_message'] ?? '',
                                    $log['server_ip'] ?? '',
                                    $log['email_source'] ?? ''
                                ];
                            }

                            return $csv_data;
                        } catch (\Exception $e) {
                            \error_log('MT SMTP Export Logs Error: ' . $e->getMessage());
                            return [];
                        }
                    }

                    private function matchesFilters(array $log_entry, array $filters): bool {
                        if (empty($filters)) {
                            return true;
                        }

                        foreach ($filters as $field => $value) {
                            if (empty($value)) {
                                continue;
                            }

                            if (!isset($log_entry[$field])) {
                                return false;
                            }

                            if (stripos($log_entry[$field], $value) === false) {
                                return false;
                            }
                        }

                        return true;
                    }
                };
            },
            'file_manager' => function() {
                if ($this->featureRegistry->has('file_management')) {
                    $feature = $this->featureRegistry->get('file_management');
                    if ($this->container->has('file_management.backup_manager')) {
                        return $this->container->get('file_management.backup_manager');
                    }
                }
                return null;
            }
        ];

        if (isset($serviceMap[$name])) {
            return $serviceMap[$name]();
        }

        return null;
    }

    private function setupContainer(): void {
        $this->container = new MT_ServiceContainer();
    }

    private function setupEventDispatcher(): void {
        $this->eventDispatcher = new MT_EventDispatcher();
        $this->container->singleton('event_dispatcher', $this->eventDispatcher);
    }

    private function setupFeatureRegistry(): void {
        $this->featureRegistry = new MT_FeatureRegistry($this->container, $this->eventDispatcher);
        $this->container->singleton('feature_registry', $this->featureRegistry);
    }

    private function setupAutoloader(): void {
        $autoloader = MT_Autoloader::getInstance();
        $autoloader->addNamespace('ModernToolkit\\Core', MT_PLUGIN_DIR . 'src/Core');
        $autoloader->addNamespace('ModernToolkit\\Features', MT_PLUGIN_DIR . 'src/Features');
        $autoloader->addNamespace('ModernToolkit\\Infrastructure', MT_PLUGIN_DIR . 'src/Infrastructure');
        $autoloader->addNamespace('ModernToolkit\\Admin', MT_PLUGIN_DIR . 'src/Admin');
        $autoloader->register();
    }

    private function registerCoreServices(): void {
        $this->container->singleton('plugin', $this);
        $this->container->singleton('container', $this->container);
    }

    private function registerFeatures(): void {
        $features = [
            'debug' => 'ModernToolkit\\Features\\Debug\\MT_DebugFeature',
            'performance' => 'ModernToolkit\\Features\\Performance\\MT_PerformanceFeature',
            'file_management' => 'ModernToolkit\\Features\\FileManagement\\MT_FileManagementFeature',
            'configuration' => 'ModernToolkit\\Features\\Configuration\\MT_ConfigurationFeature',
            'email_logging' => 'ModernToolkit\\Features\\EmailLogging\\MT_EmailLoggingFeature'
        ];

        foreach ($features as $id => $className) {
            if (class_exists($className)) {
                try {
                    $feature = new $className($this->container, $this->eventDispatcher);
                    $this->featureRegistry->register($id, $feature);
                } catch (\Exception $e) {
                    \error_log("MT: Failed to register feature {$id}: " . $e->getMessage());
                }
            } else {
                \error_log("MT: Feature class {$className} not found");
            }
        }
    }

    private function bootFeatures(): void {
        $this->featureRegistry->bootAll();
    }

    private function registerBackwardCompatibility(): void {
        // Create function-based accessor for compatibility
        if (!function_exists('mt_get_plugin_instance')) {
            function mt_get_plugin_instance() {
                return \ModernToolkit\Core\MT_Plugin::getInstance();
            }
        }
    }

    /**
     * Register admin hooks for asset loading and menu management
     */
    private function registerAdminHooks(): void {
        if (\is_admin()) {
            \add_action('admin_menu', [$this, 'addAdminMenus']);
            \add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);

            // Register legacy AJAX handlers for compatibility
            $this->registerLegacyAjaxHandlers();
        }

        // Add performance bar to admin bar
        \add_action('admin_bar_menu', [$this, 'addPerformanceBar'], 999);
        \add_action('wp_enqueue_scripts', [$this, 'enqueueFrontendAssets']);
    }

    /**
     * Add admin menus for the plugin
     */
    public function addAdminMenus(): void {
        // Main admin page
        \add_submenu_page(
            'tools.php',
            \__('Morden Toolkit', 'morden-toolkit'),
            \__('Morden Toolkit', 'morden-toolkit'),
            'manage_options',
            'mt',
            [$this, 'renderMainAdminPage']
        );

        // Debug logs page
        \add_submenu_page(
            'tools.php',
            \__('Debug Logs', 'morden-toolkit'),
            \__('Debug Logs', 'morden-toolkit'),
            'manage_options',
            'mt-logs',
            [$this, 'renderLogsPage']
        );

        // Query logs page
        \add_submenu_page(
            'tools.php',
            \__('Query Logs', 'morden-toolkit'),
            \__('Query Logs', 'morden-toolkit'),
            'manage_options',
            'mt-query-logs',
            [$this, 'renderQueryLogsPage']
        );

        // SMTP logs page (only if SMTP logging is enabled)
        if (\get_option('mt_smtp_logging_enabled', false)) {
            \add_submenu_page(
                'tools.php',
                \__('SMTP Logs', 'morden-toolkit'),
                \__('SMTP Logs', 'morden-toolkit'),
                'manage_options',
                'mt-smtp-logs',
                [$this, 'renderSmtpLogsPage']
            );
        }
    }

    /**
     * Enqueue admin assets (CSS/JS)
     */
    public function enqueueAdminAssets(string $hook): void {
        // Only load on Morden Toolkit pages
        $mt_pages = ['tools_page_mt', 'tools_page_mt-logs', 'tools_page_mt-query-logs', 'tools_page_mt-smtp-logs'];

        if (!in_array($hook, $mt_pages)) {
            return;
        }

        // Clean up any old individual script registrations that might cause 404s
        $old_scripts = ['mt-debug', 'mt-performance', 'mt-htaccess', 'debug.js', 'performance.js', 'htaccess.js'];
        foreach ($old_scripts as $script) {
            if (\wp_script_is($script, 'registered')) {
                \wp_deregister_script($script);
            }
        }

        // Enqueue main admin CSS
        \wp_enqueue_style(
            'mt-admin',
            MT_PLUGIN_URL . 'admin/assets/admin.css',
            [],
            MT_VERSION
        );

        // Enqueue page-specific CSS
        if ($hook === 'tools_page_mt-query-logs') {
            \wp_enqueue_style(
                'mt-query-logs',
                MT_PLUGIN_URL . 'admin/assets/css/query-logs.css',
                [],
                MT_VERSION
            );
        }

        // Enqueue main admin JS
        \wp_enqueue_script(
            'mt-admin',
            MT_PLUGIN_URL . 'admin/assets/admin.js',
            ['jquery'],
            MT_VERSION,
            false
        );

        // Localize script with AJAX data
        \wp_localize_script('mt-admin', 'mtToolkit', [
            'ajaxurl' => \admin_url('admin-ajax.php'),
            'nonce' => \wp_create_nonce('mt_action'),
            'debug_nonce' => \wp_create_nonce('mt_debug_nonce'),
            'performance_nonce' => \wp_create_nonce('mt_performance_nonce'),
            'htaccess_nonce' => \wp_create_nonce('mt_htaccess_nonce'),
            'strings' => [
                'confirm_clear_logs' => \__('Are you sure you want to clear all debug logs?', 'morden-toolkit'),
                'confirm_restore_htaccess' => \__('Are you sure you want to restore this backup?', 'morden-toolkit'),
                'error_occurred' => \__('An error occurred. Please try again.', 'morden-toolkit'),
                'success' => \__('Operation completed successfully.', 'morden-toolkit'),
            ]
        ]);
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueueFrontendAssets(): void {
        $enabled = \get_option('mt_query_monitor_enabled', false) &&
                   \is_user_logged_in() &&
                   \current_user_can('manage_options');

        if (!$enabled) {
            return;
        }

        // Clean up any old individual script registrations that might cause 404s
        $old_scripts = ['mt-debug', 'mt-performance', 'mt-htaccess', 'debug.js', 'performance.js', 'htaccess.js'];
        foreach ($old_scripts as $script) {
            if (\wp_script_is($script, 'registered')) {
                \wp_deregister_script($script);
            }
        }

        \wp_enqueue_style(
            'mt-performance-bar',
            MT_PLUGIN_URL . 'public/assets/performance-bar.css',
            [],
            MT_VERSION
        );

        \wp_enqueue_script(
            'mt-performance-bar',
            MT_PLUGIN_URL . 'public/assets/performance-bar.js',
            ['jquery'],
            MT_VERSION,
            true
        );
    }

    /**
     * Add performance bar to admin bar
     */
    public function addPerformanceBar($wp_admin_bar): void {
        // Only show to users who can manage options and when query monitor is enabled
        if (!\current_user_can('manage_options') || !\get_option('mt_query_monitor_enabled', false)) {
            return;
        }

        // Check if legacy QueryMonitor is available and use it
        $legacy_query_monitor = $this->get_service('query_monitor');
        if ($legacy_query_monitor && method_exists($legacy_query_monitor, 'add_admin_bar_metrics')) {
            $legacy_query_monitor->add_admin_bar_metrics($wp_admin_bar);
            return;
        }

        // Fallback to basic performance bar if legacy not available
        global $wpdb;
        $memory_usage = memory_get_usage();
        $peak_memory = memory_get_peak_usage();
        $execution_time = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
        $query_count = defined('SAVEQUERIES') && SAVEQUERIES && !empty($wpdb->queries) ? count($wpdb->queries) : $wpdb->num_queries;

        $time_formatted = number_format($execution_time, 3) . 's';
        $memory_formatted = \mt_format_bytes($peak_memory);
        $db_time = 0;

        if (defined('SAVEQUERIES') && SAVEQUERIES && !empty($wpdb->queries)) {
            foreach ($wpdb->queries as $query) {
                $db_time += $query[1];
            }
        }
        $db_time_formatted = number_format($db_time * 1000, 1) . 'ms';

        // Format like Query Monitor: time, memory, database time, queries
        $label_content = sprintf(
            '%s&nbsp;&nbsp;%s&nbsp;&nbsp;%s&nbsp;&nbsp;%s<small>Q</small>',
            esc_html($time_formatted),
            esc_html($memory_formatted),
            esc_html($db_time_formatted),
            esc_html($query_count)
        );

        // Add performance monitor to admin bar with correct ID
        $wp_admin_bar->add_node([
            'id' => 'mt-performance-monitor',
            'title' => '<span class="ab-icon">MT</span><span class="ab-label">' . $label_content . '</span>',
            'href' => '#',
            'meta' => [
                'class' => 'menupop mt-admin-perf-toggle',
                'onclick' => 'return false;',
                'title' => 'Morden Toolkit Performance Monitor'
            ]
        ]);

        // Ensure details panel is rendered
        \add_action('wp_footer', [$this, 'renderPerformanceDetails'], 9999);
        \add_action('admin_footer', [$this, 'renderPerformanceDetails'], 9999);
    }

    /**
     * Render performance details panel
     */
    public function renderPerformanceDetails(): void {
        static $rendered = false;

        // Prevent multiple renders
        if ($rendered) {
            return;
        }
        $rendered = true;

        // Only render if query monitor is enabled and user has permissions
        if (!\current_user_can('manage_options') || !\get_option('mt_query_monitor_enabled', false)) {
            return;
        }

        global $wpdb, $wp_scripts, $wp_styles;

        $memory_usage = memory_get_usage();
        $peak_memory = memory_get_peak_usage();
        $execution_time = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
        $query_count = defined('SAVEQUERIES') && SAVEQUERIES && !empty($wpdb->queries) ? count($wpdb->queries) : $wpdb->num_queries;

        $db_time = 0;
        if (defined('SAVEQUERIES') && SAVEQUERIES && !empty($wpdb->queries)) {
            foreach ($wpdb->queries as $query) {
                $db_time += $query[1];
            }
        }

        $scripts_count = !empty($wp_scripts->done) ? count($wp_scripts->done) : 0;
        $styles_count = !empty($wp_styles->done) ? count($wp_styles->done) : 0;

        $time_formatted = number_format($execution_time, 3) . 's';
        $memory_formatted = \mt_format_bytes($peak_memory);
        $db_time_formatted = number_format($db_time * 1000, 1) . 'ms';

        ?>
        <div id="mt-perf-details" class="mt-perf-details" style="display: none;">
            <div class="mt-perf-details-content">
                <div class="mt-perf-sidebar">
                    <ul class="mt-perf-tabs">
                        <li class="mt-perf-tab active" data-tab="overview">
                            <span class="dashicons dashicons-dashboard"></span>
                            <?php \__('Overview', 'morden-toolkit'); ?>
                        </li>
                        <li class="mt-perf-tab" data-tab="queries">
                            <span class="dashicons dashicons-database"></span>
                            <?php printf(\__('Queries (%d)', 'morden-toolkit'), $query_count); ?>
                        </li>
                        <li class="mt-perf-tab" data-tab="scripts">
                            <span class="dashicons dashicons-media-code"></span>
                            <?php printf(\__('Scripts (%d)', 'morden-toolkit'), $scripts_count); ?>
                        </li>
                        <li class="mt-perf-tab" data-tab="styles">
                            <span class="dashicons dashicons-admin-appearance"></span>
                            <?php printf(\__('Styles (%d)', 'morden-toolkit'), $styles_count); ?>
                        </li>
                    </ul>
                </div>
                <div class="mt-perf-content">
                    <div id="mt-perf-tab-overview" class="mt-perf-tab-content active">
                        <h4><?php \__('Performance Overview', 'morden-toolkit'); ?></h4>
                        <table class="mt-perf-table">
                            <tr>
                                <td><?php \__('Execution Time', 'morden-toolkit'); ?></td>
                                <td><?php echo esc_html($time_formatted); ?></td>
                            </tr>
                            <tr>
                                <td><?php \__('Peak Memory', 'morden-toolkit'); ?></td>
                                <td><?php echo esc_html($memory_formatted); ?></td>
                            </tr>
                            <tr>
                                <td><?php \__('Database Time', 'morden-toolkit'); ?></td>
                                <td><?php echo esc_html($db_time_formatted); ?></td>
                            </tr>
                            <tr>
                                <td><?php \__('Query Count', 'morden-toolkit'); ?></td>
                                <td><?php echo esc_html($query_count); ?></td>
                            </tr>
                            <tr>
                                <td><?php \__('PHP Version', 'morden-toolkit'); ?></td>
                                <td><?php echo esc_html(PHP_VERSION); ?></td>
                            </tr>
                            <tr>
                                <td><?php \__('WordPress Version', 'morden-toolkit'); ?></td>
                                <td><?php echo esc_html(\get_bloginfo('version')); ?></td>
                            </tr>
                        </table>
                    </div>
                    <div id="mt-perf-tab-queries" class="mt-perf-tab-content">
                        <h4><?php printf(\__('Database Queries (%d)', 'morden-toolkit'), $query_count); ?></h4>
                        <?php if (defined('SAVEQUERIES') && SAVEQUERIES && !empty($wpdb->queries)): ?>
                            <div class="mt-queries-list">
                                <?php foreach (array_slice($wpdb->queries, -10) as $index => $query): ?>
                                    <div class="mt-query-item">
                                        <div class="mt-query-time"><?php echo number_format($query[1] * 1000, 2); ?>ms</div>
                                        <div class="mt-query-sql"><?php echo esc_html(substr($query[0], 0, 100)); ?>...</div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p><?php \__('Query logging not enabled. Enable SAVEQUERIES to see database queries.', 'morden-toolkit'); ?></p>
                        <?php endif; ?>
                    </div>
                    <div id="mt-perf-tab-scripts" class="mt-perf-tab-content">
                        <h4><?php printf(\__('Loaded Scripts (%d)', 'morden-toolkit'), $scripts_count); ?></h4>
                        <?php if (!empty($wp_scripts->done)): ?>
                            <div class="mt-scripts-list">
                                <?php foreach ($wp_scripts->done as $handle): ?>
                                    <?php if (isset($wp_scripts->registered[$handle])): ?>
                                        <div class="mt-script-item">
                                            <strong><?php echo esc_html($handle); ?></strong>
                                            <div class="mt-script-src"><?php echo esc_html($wp_scripts->registered[$handle]->src); ?></div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p><?php \__('No scripts loaded.', 'morden-toolkit'); ?></p>
                        <?php endif; ?>
                    </div>
                    <div id="mt-perf-tab-styles" class="mt-perf-tab-content">
                        <h4><?php printf(\__('Loaded Styles (%d)', 'morden-toolkit'), $styles_count); ?></h4>
                        <?php if (!empty($wp_styles->done)): ?>
                            <div class="mt-styles-list">
                                <?php foreach ($wp_styles->done as $handle): ?>
                                    <?php if (isset($wp_styles->registered[$handle])): ?>
                                        <div class="mt-style-item">
                                            <strong><?php echo esc_html($handle); ?></strong>
                                            <div class="mt-style-src"><?php echo esc_html($wp_styles->registered[$handle]->src); ?></div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p><?php \__('No styles loaded.', 'morden-toolkit'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <style>
        /* Admin bar MT performance styling */
        #wp-admin-bar-mt-performance-monitor .ab-icon {
            background-color: #0073aa !important;
            color: #fff !important;
            font-weight: bold !important;
            width: auto !important;
            padding: 0 6px !important;
            border-radius: 2px !important;
            margin-right: 6px !important;
            font-size: 11px !important;
            line-height: 20px !important;
        }
        #wp-admin-bar-mt-performance-monitor:hover .ab-icon {
            background-color: #005a87 !important;
        }
        #wp-admin-bar-mt-performance-monitor.menupop .ab-item {
            cursor: pointer !important;
        }
        #wp-admin-bar-mt-performance-monitor .ab-label small {
            font-size: 9px !important;
            font-weight: normal !important;
        }
        </style>
        <?php
    }

    /**
     * Render main admin page
     */
    public function renderMainAdminPage(): void {
        include MT_PLUGIN_DIR . 'admin/views/page-toolkit.php';
    }

    /**
     * Render debug logs page
     */
    public function renderLogsPage(): void {
        include MT_PLUGIN_DIR . 'admin/views/page-logs.php';
    }

    /**
     * Render query logs page
     */
    public function renderQueryLogsPage(): void {
        include MT_PLUGIN_DIR . 'admin/views/page-query-logs.php';
    }

    /**
     * Render SMTP logs page
     */
    public function renderSmtpLogsPage(): void {
        include MT_PLUGIN_DIR . 'admin/views/page-smtp-logs.php';
    }

    /**
     * Register legacy AJAX handlers for backward compatibility
     */
    private function registerLegacyAjaxHandlers(): void {
        // Debug AJAX handlers
        \add_action('wp_ajax_mt_toggle_debug', [$this, 'handleToggleDebug']);
        \add_action('wp_ajax_mt_toggle_debug_constant', [$this, 'handleToggleDebugConstant']);
        \add_action('wp_ajax_mt_clear_debug_log', [$this, 'handleClearDebugLog']);
        \add_action('wp_ajax_mt_get_debug_log', [$this, 'handleGetDebugLog']);

        // Query Monitor AJAX handlers
        \add_action('wp_ajax_mt_toggle_query_monitor', [$this, 'handleToggleQueryMonitor']);
        \add_action('wp_ajax_mt_get_query_logs', [$this, 'handleGetQueryLogs']);
        \add_action('wp_ajax_mt_clear_query_log', [$this, 'handleClearQueryLog']);
        \add_action('wp_ajax_mt_cleanup_query_logs', [$this, 'handleCleanupQueryLogs']);
        \add_action('wp_ajax_mt_download_query_logs', [$this, 'handleDownloadQueryLogs']);

        // Htaccess AJAX handlers
        \add_action('wp_ajax_mt_save_htaccess', [$this, 'handleSaveHtaccess']);
        \add_action('wp_ajax_mt_restore_htaccess', [$this, 'handleRestoreHtaccess']);

        // PHP Config AJAX handlers
        \add_action('wp_ajax_mt_apply_php_preset', [$this, 'handleApplyPhpPreset']);
        \add_action('wp_ajax_mt_save_custom_preset', [$this, 'handleSaveCustomPreset']);
        \add_action('wp_ajax_mt_reset_custom_preset', [$this, 'handleResetCustomPreset']);

        // SMTP AJAX handlers
        \add_action('wp_ajax_mt_get_smtp_logs', [$this, 'handleGetSmtpLogs']);
        \add_action('wp_ajax_mt_clear_smtp_logs', [$this, 'handleClearSmtpLogs']);
        \add_action('wp_ajax_mt_download_smtp_logs', [$this, 'handleDownloadSmtpLogs']);
        \add_action('wp_ajax_mt_send_test_email', [$this, 'handleSendTestEmail']);
        \add_action('wp_ajax_mt_toggle_smtp_logging_setting', [$this, 'handleToggleSmtpLogging']);

        // Performance AJAX handlers
        \add_action('wp_ajax_mt_get_performance_data', [$this, 'handleGetPerformanceData']);
        \add_action('wp_ajax_mt_get_log_info', [$this, 'handleGetLogInfo']);
    }

    // AJAX Handler Methods

    public function handleToggleDebug(): void {
        if (!\current_user_can('manage_options') || !\wp_verify_nonce($_POST['nonce'] ?? '', 'mt_action')) {
            \wp_send_json_error('Permission denied');
        }

        $debug_service = $this->get_service('debug');
        if ($debug_service) {
            $enabled = isset($_POST['enabled']) ? (bool) $_POST['enabled'] : false;
            $result = $debug_service->toggle_debug($enabled);

            if ($result) {
                \wp_send_json_success([
                    'enabled' => $enabled,
                    'message' => $enabled ? 'Debug mode enabled.' : 'Debug mode disabled.'
                ]);
            } else {
                \wp_send_json_error('Failed to toggle debug mode.');
            }
        } else {
            \wp_send_json_error('Debug service not available.');
        }
    }

    public function handleToggleDebugConstant(): void {
        if (!\current_user_can('manage_options') || !\wp_verify_nonce($_POST['nonce'] ?? '', 'mt_action')) {
            \wp_send_json_error('Permission denied');
        }

        $debug_service = $this->get_service('debug');
        if ($debug_service) {
            $constant = \sanitize_text_field($_POST['constant']);
            $enabled = isset($_POST['enabled']) && $_POST['enabled'] === 'true';
            $result = $debug_service->toggle_debug_constant($constant, $enabled);

            if ($result) {
                $status = $debug_service->get_debug_status();
                \wp_send_json_success([
                    'constant' => $constant,
                    'enabled' => $enabled,
                    'status' => $status,
                    'message' => sprintf($enabled ? '%s enabled.' : '%s disabled.', $constant)
                ]);
            } else {
                \wp_send_json_error('Failed to toggle debug constant.');
            }
        } else {
            \wp_send_json_error('Debug service not available.');
        }
    }

    public function handleClearDebugLog(): void {
        if (!\current_user_can('manage_options') || !\wp_verify_nonce($_POST['nonce'] ?? '', 'mt_action')) {
            \wp_send_json_error('Permission denied');
        }

        $debug_service = $this->get_service('debug');
        if ($debug_service) {
            $result = $debug_service->clear_debug_log();

            if ($result) {
                \wp_send_json_success('Debug log cleared.');
            } else {
                \wp_send_json_error('Failed to clear debug log.');
            }
        } else {
            \wp_send_json_error('Debug service not available.');
        }
    }

    public function handleGetDebugLog(): void {
        if (!\current_user_can('manage_options') || !\wp_verify_nonce($_POST['nonce'] ?? '', 'mt_action')) {
            \wp_send_json_error('Permission denied');
        }

        $debug_service = $this->get_service('debug');
        if ($debug_service) {
            $logs = $debug_service->get_debug_log_entries();
            \wp_send_json_success($logs);
        } else {
            \wp_send_json_error('Debug service not available.');
        }
    }

    public function handleToggleQueryMonitor(): void {
        if (!\current_user_can('manage_options') || !\wp_verify_nonce($_POST['nonce'] ?? '', 'mt_action')) {
            \wp_send_json_error('Permission denied');
        }

        $enabled = isset($_POST['enabled']) && $_POST['enabled'] === 'true';
        \update_option('mt_query_monitor_enabled', $enabled);

        \wp_send_json_success([
            'enabled' => $enabled,
            'message' => $enabled ? 'Query Monitor enabled.' : 'Query Monitor disabled.'
        ]);
    }

    public function handleGetQueryLogs(): void {
        if (!\current_user_can('manage_options') || !\wp_verify_nonce($_POST['nonce'] ?? '', 'mt_action')) {
            \wp_send_json_error('Permission denied');
        }

        $debug_service = $this->get_service('debug');
        if ($debug_service) {
            $logs = $debug_service->get_query_log_entries();
            \wp_send_json_success($logs);
        } else {
            \wp_send_json_error('Debug service not available.');
        }
    }

    public function handleClearQueryLog(): void {
        if (!\current_user_can('manage_options') || !\wp_verify_nonce($_POST['nonce'] ?? '', 'mt_action')) {
            \wp_send_json_error('Permission denied');
        }

        $debug_service = $this->get_service('debug');
        if ($debug_service) {
            $result = $debug_service->clear_query_log();

            if ($result) {
                \wp_send_json_success('Query log cleared.');
            } else {
                \wp_send_json_error('Failed to clear query log.');
            }
        } else {
            \wp_send_json_error('Debug service not available.');
        }
    }

    public function handleCleanupQueryLogs(): void {
        if (!\current_user_can('manage_options') || !\wp_verify_nonce($_POST['nonce'] ?? '', 'mt_action')) {
            \wp_send_json_error('Permission denied');
        }

        $debug_service = $this->get_service('debug');
        if ($debug_service) {
            $result = $debug_service->cleanup_old_query_logs();
            \wp_send_json_success($result);
        } else {
            \wp_send_json_error('Debug service not available.');
        }
    }

    public function handleDownloadQueryLogs(): void {
        if (!\current_user_can('manage_options') || !\wp_verify_nonce($_GET['nonce'] ?? '', 'mt_action')) {
            \wp_die('Permission denied');
        }

        $query_log_path = \mt_get_query_log_path();

        if (!file_exists($query_log_path)) {
            \wp_die('Query log file not found.');
        }

        $content = file_get_contents($query_log_path);
        $filename = 'query-logs-' . date('Y-m-d-H-i-s') . '.txt';

        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));

        echo $content;
        exit;
    }

    public function handleSaveHtaccess(): void {
        if (!\current_user_can('manage_options') || !\wp_verify_nonce($_POST['nonce'] ?? '', 'mt_action')) {
            \wp_send_json_error('Permission denied');
        }

        $htaccess_service = $this->get_service('htaccess');
        if ($htaccess_service) {
            $content = \wp_unslash($_POST['content']);
            $content = \wp_kses($content, []);
            $result = $htaccess_service->save_htaccess($content);

            if ($result) {
                \wp_send_json_success('.htaccess file saved successfully.');
            } else {
                \wp_send_json_error('Failed to save .htaccess file.');
            }
        } else {
            \wp_send_json_error('Htaccess service not available.');
        }
    }

    public function handleRestoreHtaccess(): void {
        if (!\current_user_can('manage_options') || !\wp_verify_nonce($_POST['nonce'] ?? '', 'mt_action')) {
            \wp_send_json_error('Permission denied');
        }

        $htaccess_service = $this->get_service('htaccess');
        if ($htaccess_service) {
            $backup_index = intval($_POST['backup_index']);
            $result = $htaccess_service->restore_htaccess($backup_index);

            if ($result) {
                \wp_send_json_success('.htaccess file restored successfully.');
            } else {
                \wp_send_json_error('Failed to restore .htaccess file.');
            }
        } else {
            \wp_send_json_error('Htaccess service not available.');
        }
    }

    public function handleApplyPhpPreset(): void {
        if (!\current_user_can('manage_options') || !\wp_verify_nonce($_POST['nonce'] ?? '', 'mt_action')) {
            \wp_send_json_error('Permission denied');
        }

        $php_config_service = $this->get_service('php_config');
        if ($php_config_service) {
            $preset = \sanitize_text_field($_POST['preset']);
            $result = $php_config_service->applyPreset($preset);

            if ($result) {
                \update_option('mt_php_preset', $preset);
                \wp_send_json_success('PHP configuration applied successfully.');
            } else {
                \wp_send_json_error('Failed to apply PHP configuration.');
            }
        } else {
            \wp_send_json_error('PHP config service not available.');
        }
    }

    public function handleSaveCustomPreset(): void {
        if (!\current_user_can('manage_options') || !\wp_verify_nonce($_POST['nonce'] ?? '', 'mt_action')) {
            \wp_send_json_error('Permission denied');
        }

        $php_config_service = $this->get_service('php_config');
        if ($php_config_service) {
            $settings = $_POST['settings'];
            if (!is_array($settings)) {
                \wp_send_json_error('Invalid settings data.');
            }

            $validated_settings = $php_config_service->validateCustomSettings($settings);
            if (!$validated_settings) {
                \wp_send_json_error('Invalid configuration values.');
            }

            \update_option('mt_custom_preset_settings', $validated_settings);
            $php_config_service->updateCustomPreset($validated_settings);

            \wp_send_json_success('Custom preset saved successfully.');
        } else {
            \wp_send_json_error('PHP config service not available.');
        }
    }

    public function handleResetCustomPreset(): void {
        if (!\current_user_can('manage_options') || !\wp_verify_nonce($_POST['nonce'] ?? '', 'mt_action')) {
            \wp_send_json_error('Permission denied');
        }

        \delete_option('mt_custom_preset_settings');
        \wp_send_json_success('Custom preset reset successfully.');
    }

    public function handleGetSmtpLogs(): void {
        if (!\current_user_can('manage_options') || !\wp_verify_nonce($_POST['nonce'] ?? '', 'mt_smtp_logs_nonce')) {
            \wp_send_json_error('Permission denied');
        }

        $smtp_service = $this->get_service('smtp_logger');
        if ($smtp_service) {
            $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
            $per_page = 20;
            $offset = ($page - 1) * $per_page;
            $filters = isset($_POST['filters']) ? $_POST['filters'] : [];

            $logs = $smtp_service->get_logs($per_page, $offset, $filters);
            $total_logs = $smtp_service->get_logs_count($filters);
            $total_pages = ceil($total_logs / $per_page);

            \wp_send_json_success([
                'logs' => $logs,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $total_pages,
                    'total_logs' => $total_logs,
                    'per_page' => $per_page
                ]
            ]);
        } else {
            \wp_send_json_error('SMTP service not available.');
        }
    }

    public function handleClearSmtpLogs(): void {
        if (!\current_user_can('manage_options') || !\wp_verify_nonce($_POST['nonce'] ?? '', 'mt_smtp_logs_nonce')) {
            \wp_send_json_error('Permission denied');
        }

        $smtp_service = $this->get_service('smtp_logger');
        if ($smtp_service) {
            $result = $smtp_service->clear_logs();

            if ($result !== false) {
                \wp_send_json_success('SMTP logs cleared successfully.');
            } else {
                \wp_send_json_error('Failed to clear SMTP logs.');
            }
        } else {
            \wp_send_json_error('SMTP service not available.');
        }
    }

    public function handleDownloadSmtpLogs(): void {
        if (!\current_user_can('manage_options') || !\wp_verify_nonce($_GET['nonce'] ?? '', 'mt_smtp_logs_nonce')) {
            \wp_die('Permission denied');
        }

        $smtp_service = $this->get_service('smtp_logger');
        if ($smtp_service) {
            $filters = isset($_GET['filters']) ? $_GET['filters'] : [];
            $csv_data = $smtp_service->export_logs_csv($filters);

            $filename = 'mail-logs-' . date('Y-m-d-H-i-s') . '.csv';

            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Expires: 0');

            $output = fopen('php://output', 'w');
            foreach ($csv_data as $row) {
                fputcsv($output, $row);
            }
            fclose($output);
            exit;
        } else {
            \wp_die('SMTP service not available.');
        }
    }

    public function handleSendTestEmail(): void {
        if (!\current_user_can('manage_options') || !\wp_verify_nonce($_POST['nonce'] ?? '', 'mt_smtp_logs_nonce')) {
            \wp_send_json_error('Permission denied');
        }

        $admin_email = \get_option('admin_email');
        $site_name = \get_option('blogname');

        $subject = sprintf('[%s] SMTP Test Email', $site_name);
        $message = sprintf(
            'This is a test email sent from Morden Toolkit SMTP logging feature.\n\nSite: %s\nTime: %s\nUser: %s',
            \home_url(),
            \current_time('mysql'),
            \wp_get_current_user()->display_name
        );

        $result = \wp_mail($admin_email, $subject, $message);

        if ($result) {
            \wp_send_json_success('Test email sent successfully.');
        } else {
            \wp_send_json_error('Failed to send test email.');
        }
    }

    public function handleToggleSmtpLogging(): void {
        if (!\current_user_can('manage_options') || !\wp_verify_nonce($_POST['nonce'] ?? '', 'mt_action')) {
            \wp_send_json_error('Permission denied');
        }

        $enabled = isset($_POST['enabled']) && $_POST['enabled'] === 'true';
        \update_option('mt_smtp_logging_enabled', $enabled);

        \wp_send_json_success([
            'enabled' => $enabled,
            'message' => $enabled ? 'SMTP logging enabled.' : 'SMTP logging disabled.'
        ]);
    }

    public function handleGetPerformanceData(): void {
        if (!\current_user_can('manage_options') || !\wp_verify_nonce($_POST['nonce'] ?? '', 'mt_performance_nonce')) {
            \wp_send_json_error('Permission denied');
        }

        // Return basic performance metrics
        $metrics = [
            'memory_usage' => \mt_format_bytes(memory_get_usage()),
            'peak_memory' => \mt_format_bytes(memory_get_peak_usage()),
            'execution_time' => \mt_format_time(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']),
            'status' => 'good'
        ];

        \wp_send_json_success($metrics);
    }

    public function handleGetLogInfo(): void {
        if (!\current_user_can('manage_options') || !\wp_verify_nonce($_POST['nonce'] ?? '', 'mt_action')) {
            \wp_send_json_error('Permission denied');
        }

        $debug_service = $this->get_service('debug');
        if ($debug_service) {
            $status = $debug_service->get_debug_status();
            \wp_send_json_success($status);
        } else {
            \wp_send_json_error('Debug service not available.');
        }
    }
}