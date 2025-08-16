<?php
/**
 * Htaccess Service - Safe .htaccess file editing with auto-backup
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MT_Htaccess {

    /**
     * Maximum number of backups to keep
     */
    const MAX_BACKUPS = 3;

    /**
     * Get .htaccess file content
     */
    public function get_htaccess_content() {
        $htaccess_path = mt_get_htaccess_path();

        if (!file_exists($htaccess_path)) {
            return '';
        }

        return file_get_contents($htaccess_path);
    }

    /**
     * Save .htaccess file with automatic backup
     */
    public function save_htaccess($content) {
        $htaccess_path = mt_get_htaccess_path();

        // Validate content before saving
        if (!$this->validate_htaccess_content($content)) {
            return false;
        }

        // Create backup before saving
        if (file_exists($htaccess_path)) {
            $this->create_backup();
        }

        // Sanitize content
        $content = mt_sanitize_file_content($content);
        if ($content === false) {
            return false;
        }

        // Save file
        $result = file_put_contents($htaccess_path, $content);

        if ($result === false) {
            return false;
        }

        // Test if the site is still accessible after the change
        if (!$this->test_htaccess_validity()) {
            // Restore from backup if site is broken
            $this->restore_latest_backup();
            return false;
        }

        return true;
    }

    /**
     * Create backup of current .htaccess file
     */
    private function create_backup() {
        $htaccess_path = mt_get_htaccess_path();

        if (!file_exists($htaccess_path)) {
            return false;
        }

        $content = file_get_contents($htaccess_path);
        $backups = get_option('morden_htaccess_backups', array());

        // Add new backup
        $backup = array(
            'timestamp' => current_time('timestamp'),
            'content' => $content,
            'size' => strlen($content)
        );

        array_unshift($backups, $backup);

        // Keep only the latest MAX_BACKUPS
        if (count($backups) > self::MAX_BACKUPS) {
            $backups = array_slice($backups, 0, self::MAX_BACKUPS);
        }

        update_option('morden_htaccess_backups', $backups);
        return true;
    }

    /**
     * Get all backups
     */
    public function get_backups() {
        return get_option('morden_htaccess_backups', array());
    }

    /**
     * Restore .htaccess from backup
     */
    public function restore_htaccess($backup_index) {
        $backups = $this->get_backups();

        if (!isset($backups[$backup_index])) {
            return false;
        }

        $backup = $backups[$backup_index];
        $htaccess_path = mt_get_htaccess_path();

        // Create backup of current state before restoring
        if (file_exists($htaccess_path)) {
            $this->create_backup();
        }

        return file_put_contents($htaccess_path, $backup['content']) !== false;
    }

    /**
     * Restore from latest backup (used for error recovery)
     */
    private function restore_latest_backup() {
        $backups = $this->get_backups();

        if (empty($backups)) {
            return false;
        }

        $latest_backup = $backups[0];
        $htaccess_path = mt_get_htaccess_path();

        return file_put_contents($htaccess_path, $latest_backup['content']) !== false;
    }

    /**
     * Validate .htaccess content for basic syntax errors
     */
    private function validate_htaccess_content($content) {
        // Check for common dangerous patterns
        $dangerous_patterns = array(
            '/php_value\s+auto_prepend_file/i',
            '/php_value\s+auto_append_file/i',
            '/<\?php/i',
            '/eval\s*\(/i',
            '/exec\s*\(/i',
            '/system\s*\(/i',
            '/shell_exec\s*\(/i'
        );

        foreach ($dangerous_patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return false;
            }
        }

        // Check for basic Apache directive syntax
        $lines = explode("\n", $content);
        foreach ($lines as $line_num => $line) {
            $line = trim($line);

            // Skip empty lines and comments
            if (empty($line) || $line[0] === '#') {
                continue;
            }

            // Check for unclosed directives
            if (preg_match('/^<(\w+)/i', $line, $matches)) {
                $directive = $matches[1];
                if (!preg_match('/<\/' . preg_quote($directive, '/') . '>/i', $content)) {
                    // Allow self-closing directives
                    if (!preg_match('/\/>\s*$/', $line)) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * Test if .htaccess is valid by making a simple HTTP request
     */
    private function test_htaccess_validity() {
        $site_url = home_url('/');

        // Simple HEAD request to check if site is accessible
        $response = wp_remote_head($site_url, array(
            'timeout' => 10,
            'sslverify' => false
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);

        // Consider 2xx and 3xx responses as valid
        return $response_code >= 200 && $response_code < 400;
    }

    /**
     * Get .htaccess file info
     */
    public function get_htaccess_info() {
        $htaccess_path = mt_get_htaccess_path();

        $info = array(
            'exists' => false,
            'writable' => false,
            'size' => 0,
            'modified' => null,
            'path' => $htaccess_path
        );

        if (file_exists($htaccess_path)) {
            $info['exists'] = true;
            $info['writable'] = is_writable($htaccess_path);
            $info['size'] = filesize($htaccess_path);
            $info['modified'] = filemtime($htaccess_path);
        } else {
            // Check if we can create the file
            $info['writable'] = is_writable(ABSPATH);
        }

        return $info;
    }

    /**
     * Clear all backups
     */
    public function clear_backups() {
        return delete_option('morden_htaccess_backups');
    }

    /**
     * Get common .htaccess snippets
     */
    public function get_common_snippets() {
        return array(
            'wordpress_rewrite' => array(
                'title' => __('WordPress Rewrite Rules', 'mt'),
                'content' => "# BEGIN WordPress\n<IfModule mod_rewrite.c>\nRewriteEngine On\nRewriteRule ^index\\.php$ - [L]\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteCond %{REQUEST_FILENAME} !-d\nRewriteRule . /index.php [L]\n</IfModule>\n# END WordPress"
            ),
            'cache_control' => array(
                'title' => __('Browser Caching', 'mt'),
                'content' => "# Browser Caching\n<IfModule mod_expires.c>\nExpiresActive On\nExpiresByType text/css \"access plus 1 year\"\nExpiresByType application/javascript \"access plus 1 year\"\nExpiresByType image/png \"access plus 1 year\"\nExpiresByType image/jpg \"access plus 1 year\"\nExpiresByType image/jpeg \"access plus 1 year\"\nExpiresByType image/gif \"access plus 1 year\"\n</IfModule>"
            ),
            'gzip_compression' => array(
                'title' => __('GZIP Compression', 'mt'),
                'content' => "# GZIP Compression\n<IfModule mod_deflate.c>\nAddOutputFilterByType DEFLATE text/plain\nAddOutputFilterByType DEFLATE text/html\nAddOutputFilterByType DEFLATE text/xml\nAddOutputFilterByType DEFLATE text/css\nAddOutputFilterByType DEFLATE application/xml\nAddOutputFilterByType DEFLATE application/xhtml+xml\nAddOutputFilterByType DEFLATE application/rss+xml\nAddOutputFilterByType DEFLATE application/javascript\nAddOutputFilterByType DEFLATE application/x-javascript\n</IfModule>"
            ),
            'security_headers' => array(
                'title' => __('Security Headers', 'mt'),
                'content' => "# Security Headers\n<IfModule mod_headers.c>\nHeader always set X-Content-Type-Options nosniff\nHeader always set X-Frame-Options SAMEORIGIN\nHeader always set X-XSS-Protection \"1; mode=block\"\nHeader always set Referrer-Policy \"strict-origin-when-cross-origin\"\n</IfModule>"
            )
        );
    }
}
