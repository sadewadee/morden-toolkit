<?php

namespace ModernToolkit\Features\FileManagement\Services;

class MT_HtaccessManager {

    private $htaccess_path;
    private $max_backups;

    public function __construct() {
        $this->htaccess_path = \mt_get_htaccess_path();
        $this->max_backups = 10;
    }

    /**
     * Get .htaccess file information
     */
    public function get_htaccess_info(): array {
        return [
            'path' => $this->htaccess_path,
            'exists' => file_exists($this->htaccess_path),
            'writable' => \mt_is_file_writable($this->htaccess_path),
            'size' => file_exists($this->htaccess_path) ? filesize($this->htaccess_path) : 0,
            'size_formatted' => file_exists($this->htaccess_path) ? \mt_format_bytes(filesize($this->htaccess_path)) : '0 B',
            'last_modified' => file_exists($this->htaccess_path) ? filemtime($this->htaccess_path) : 0,
            'backup_count' => count($this->get_backups())
        ];
    }

    /**
     * Get .htaccess content
     */
    public function get_htaccess_content(): string {
        if (!file_exists($this->htaccess_path)) {
            return '';
        }

        $content = file_get_contents($this->htaccess_path);
        return $content !== false ? $content : '';
    }

    /**
     * Save .htaccess content with automatic backup
     */
    public function save_htaccess(string $content): bool {
        try {
            // Create backup before saving
            if (file_exists($this->htaccess_path)) {
                $this->create_backup();
            }

            $result = file_put_contents($this->htaccess_path, $content, LOCK_EX);

            if ($result !== false) {
                // Set proper permissions
                chmod($this->htaccess_path, 0644);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            \error_log('MT Htaccess Save Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Create a backup of current .htaccess
     */
    public function create_backup(): bool {
        try {
            if (!file_exists($this->htaccess_path)) {
                return false;
            }

            $backups = \get_option('mt_htaccess_backups', []);
            if (!is_array($backups)) {
                $backups = [];
            }

            $backup_content = file_get_contents($this->htaccess_path);
            if ($backup_content === false) {
                return false;
            }

            $backup_entry = [
                'content' => $backup_content,
                'timestamp' => \current_time('mysql'),
                'size' => strlen($backup_content),
                'description' => 'Auto backup before edit'
            ];

            $backups[] = $backup_entry;

            // Keep only the last N backups
            if (count($backups) > $this->max_backups) {
                $backups = array_slice($backups, -$this->max_backups);
            }

            \update_option('mt_htaccess_backups', $backups);
            return true;
        } catch (\Exception $e) {
            \error_log('MT Htaccess Backup Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all backups
     */
    public function get_backups(): array {
        $backups = \get_option('mt_htaccess_backups', []);
        if (!is_array($backups)) {
            return [];
        }

        // Add formatted information to each backup
        foreach ($backups as $index => $backup) {
            $backups[$index]['size_formatted'] = \mt_format_bytes($backup['size'] ?? 0);
            $backups[$index]['timestamp_formatted'] = isset($backup['timestamp'])
                ? \mysql2date('Y-m-d H:i:s', $backup['timestamp'])
                : '';
        }

        return array_reverse($backups); // Show newest first
    }

    /**
     * Restore from backup
     */
    public function restore_htaccess(int $backup_index): bool {
        try {
            $backups = \get_option('mt_htaccess_backups', []);

            if (!is_array($backups) || !isset($backups[$backup_index])) {
                return false;
            }

            $backup_content = $backups[$backup_index]['content'];

            // Create a backup of current state before restoring
            $this->create_backup();

            $result = file_put_contents($this->htaccess_path, $backup_content, LOCK_EX);

            if ($result !== false) {
                chmod($this->htaccess_path, 0644);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            \error_log('MT Htaccess Restore Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a specific backup
     */
    public function delete_backup(int $backup_index): bool {
        try {
            $backups = \get_option('mt_htaccess_backups', []);

            if (!is_array($backups) || !isset($backups[$backup_index])) {
                return false;
            }

            unset($backups[$backup_index]);
            $backups = array_values($backups); // Re-index array

            \update_option('mt_htaccess_backups', $backups);
            return true;
        } catch (\Exception $e) {
            \error_log('MT Htaccess Delete Backup Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Clear all backups
     */
    public function clear_backups(): bool {
        try {
            \delete_option('mt_htaccess_backups');
            return true;
        } catch (\Exception $e) {
            \error_log('MT Htaccess Clear Backups Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get common .htaccess snippets
     */
    public function get_common_snippets(): array {
        return [
            'gzip_compression' => [
                'title' => 'Enable Gzip Compression',
                'description' => 'Compress files to improve page load speed',
                'content' => "# Enable Gzip compression\n<IfModule mod_deflate.c>\n    AddOutputFilterByType DEFLATE text/plain\n    AddOutputFilterByType DEFLATE text/html\n    AddOutputFilterByType DEFLATE text/xml\n    AddOutputFilterByType DEFLATE text/css\n    AddOutputFilterByType DEFLATE application/xml\n    AddOutputFilterByType DEFLATE application/xhtml+xml\n    AddOutputFilterByType DEFLATE application/rss+xml\n    AddOutputFilterByType DEFLATE application/javascript\n    AddOutputFilterByType DEFLATE application/x-javascript\n</IfModule>"
            ],
            'browser_caching' => [
                'title' => 'Browser Caching',
                'description' => 'Set browser cache headers for better performance',
                'content' => "# Browser Caching\n<IfModule mod_expires.c>\n    ExpiresActive On\n    ExpiresByType text/css \"access plus 1 year\"\n    ExpiresByType application/javascript \"access plus 1 year\"\n    ExpiresByType image/png \"access plus 1 year\"\n    ExpiresByType image/jpg \"access plus 1 year\"\n    ExpiresByType image/jpeg \"access plus 1 year\"\n    ExpiresByType image/gif \"access plus 1 year\"\n    ExpiresByType image/webp \"access plus 1 year\"\n    ExpiresByType image/svg+xml \"access plus 1 year\"\n    ExpiresByType application/pdf \"access plus 1 month\"\n    ExpiresByType text/html \"access plus 1 hour\"\n</IfModule>"
            ],
            'security_headers' => [
                'title' => 'Security Headers',
                'description' => 'Add security headers to protect your site',
                'content' => "# Security Headers\n<IfModule mod_headers.c>\n    Header always set X-Content-Type-Options nosniff\n    Header always set X-Frame-Options DENY\n    Header always set X-XSS-Protection \"1; mode=block\"\n    Header always set Referrer-Policy \"strict-origin-when-cross-origin\"\n    Header always set Content-Security-Policy \"default-src 'self'\"\n</IfModule>"
            ],
            'wordpress_security' => [
                'title' => 'WordPress Security',
                'description' => 'Basic WordPress security rules',
                'content' => "# WordPress Security\n# Protect wp-config.php\n<Files wp-config.php>\n    order allow,deny\n    deny from all\n</Files>\n\n# Disable directory browsing\nOptions -Indexes\n\n# Protect .htaccess\n<Files .htaccess>\n    order allow,deny\n    deny from all\n</Files>\n\n# Disable PHP execution in uploads\n<Directory \"/wp-content/uploads/\">\n    <Files \"*.php\">\n        order allow,deny\n        deny from all\n    </Files>\n</Directory>"
            ],
            'redirect_rules' => [
                'title' => 'Common Redirects',
                'description' => 'Examples of redirect rules',
                'content' => "# Redirect Examples\n# Redirect www to non-www\n# RewriteEngine On\n# RewriteCond %{HTTP_HOST} ^www\\.(.+)$ [NC]\n# RewriteRule ^(.*)$ http://%1/$1 [R=301,L]\n\n# Force HTTPS\n# RewriteEngine On\n# RewriteCond %{HTTPS} off\n# RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]"
            ]
        ];
    }

    /**
     * Add a snippet to .htaccess
     */
    public function add_snippet(string $snippet_key): bool {
        $snippets = $this->get_common_snippets();

        if (!isset($snippets[$snippet_key])) {
            return false;
        }

        $current_content = $this->get_htaccess_content();
        $snippet_content = $snippets[$snippet_key]['content'];

        // Check if snippet already exists
        if (strpos($current_content, $snippet_content) !== false) {
            return false; // Already exists
        }

        $new_content = $current_content . "\n\n" . $snippet_content . "\n";

        return $this->save_htaccess($new_content);
    }

    /**
     * Validate .htaccess syntax (basic validation)
     */
    public function validate_htaccess_syntax(string $content): array {
        $errors = [];
        $warnings = [];

        $lines = explode("\n", $content);
        $line_number = 0;

        foreach ($lines as $line) {
            $line_number++;
            $line = trim($line);

            // Skip empty lines and comments
            if (empty($line) || $line[0] === '#') {
                continue;
            }

            // Check for basic syntax issues
            if (strpos($line, '<IfModule') === 0 && !preg_match('/^<IfModule\s+[\w_]+\.c>$/', $line)) {
                $errors[] = "Line {$line_number}: Invalid IfModule syntax";
            }

            if (strpos($line, '</IfModule') === 0 && $line !== '</IfModule>') {
                $errors[] = "Line {$line_number}: Invalid IfModule closing tag";
            }

            // Check for potentially dangerous directives
            if (preg_match('/^(Allow|Deny)\s+from\s+all/i', $line)) {
                $warnings[] = "Line {$line_number}: Global Allow/Deny directive - use with caution";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }

    /**
     * Get .htaccess statistics
     */
    public function get_statistics(): array {
        $content = $this->get_htaccess_content();
        $lines = explode("\n", $content);

        $stats = [
            'total_lines' => count($lines),
            'comment_lines' => 0,
            'empty_lines' => 0,
            'directive_lines' => 0,
            'modules_used' => [],
            'directives_used' => []
        ];

        foreach ($lines as $line) {
            $line = trim($line);

            if (empty($line)) {
                $stats['empty_lines']++;
            } elseif ($line[0] === '#') {
                $stats['comment_lines']++;
            } else {
                $stats['directive_lines']++;

                // Extract module usage
                if (preg_match('/<IfModule\s+([\w_]+\.c)>/', $line, $matches)) {
                    $stats['modules_used'][] = $matches[1];
                }

                // Extract directive usage
                if (preg_match('/^(\w+)/', $line, $matches)) {
                    $stats['directives_used'][] = $matches[1];
                }
            }
        }

        $stats['modules_used'] = array_unique($stats['modules_used']);
        $stats['directives_used'] = array_unique($stats['directives_used']);

        return $stats;
    }
}