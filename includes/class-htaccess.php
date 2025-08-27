<?php
/**
 * Htaccess Service - Safe .htaccess file editing with auto-backup
 *
 * @package Morden Toolkit
 * @author Morden Team
 * @license GPL v3 or later
 * @link https://github.com/sadewadee/morden-toolkit
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

        // Check for duplicate snippets before saving
        $content = $this->remove_duplicate_snippets($content);

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

        // Enhanced testing with 503 error detection
        if (!$this->test_htaccess_validity_enhanced()) {
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
     * Enhanced .htaccess validity test with 503 error detection
     */
    private function test_htaccess_validity_enhanced() {
        $site_url = home_url('/');

        // Make multiple requests to different endpoints
        $test_urls = array(
            $site_url,
            home_url('/wp-admin/'),
            home_url('/wp-content/'),
            home_url('/wp-includes/')
        );

        foreach ($test_urls as $test_url) {
            $response = wp_remote_head($test_url, array(
                'timeout' => 15,
                'sslverify' => false,
                'user-agent' => 'Morden-Toolkit-HTAccess-Validator/1.0'
            ));

            if (is_wp_error($response)) {
                // Network error
                continue;
            }

            $response_code = wp_remote_retrieve_response_code($response);

            // Check for 503 Service Unavailable specifically
            if ($response_code === 503) {
                return false;
            }

            // Check for internal server errors (500-599)
            if ($response_code >= 500) {
                return false;
            }

            // If we get a valid response from any URL, consider it working
            if ($response_code >= 200 && $response_code < 400) {
                return true;
            }
        }

        // If all URLs failed, consider it invalid
        return false;
    }

    /**
     * Remove duplicate snippets from .htaccess content
     */
    private function remove_duplicate_snippets($content) {
        // Define snippet patterns with their identifiers
        $snippet_patterns = array(
            'wordpress_rewrite' => array(
                'start' => '# BEGIN WordPress',
                'end' => '# END WordPress'
            ),
            'cache_control' => array(
                'start' => '# Browser Caching',
                'end' => '</IfModule>'
            ),
            'gzip_compression' => array(
                'start' => '# GZIP Compression',
                'end' => '</IfModule>'
            ),
            'security_headers' => array(
                'start' => '# Security Headers',
                'end' => '</IfModule>'
            ),
            'morden_toolkit' => array(
                'start' => '# BEGIN Morden Toolkit',
                'end' => '# END Morden Toolkit'
            )
        );

        $found_snippets = array();
        $lines = explode("\n", $content);
        $cleaned_lines = array();
        $skip_lines = false;
        $current_snippet = null;

        foreach ($lines as $line) {
            $line_trimmed = trim($line);

            // Check if this line starts a known snippet
            foreach ($snippet_patterns as $snippet_name => $pattern) {
                if (strpos($line_trimmed, $pattern['start']) !== false) {
                    // Check if we've already seen this snippet
                    if (isset($found_snippets[$snippet_name])) {
                        // Skip this duplicate snippet
                        $skip_lines = true;
                        $current_snippet = $snippet_name;
                        break;
                    } else {
                        // Mark this snippet as found
                        $found_snippets[$snippet_name] = true;
                        $skip_lines = false;
                        $current_snippet = $snippet_name;
                    }
                }
            }

            // Check if this line ends the current snippet
            if ($skip_lines && $current_snippet &&
                strpos($line_trimmed, $snippet_patterns[$current_snippet]['end']) !== false) {
                $skip_lines = false;
                $current_snippet = null;
                continue; // Skip the end line of duplicate snippet
            }

            // Add line if we're not skipping
            if (!$skip_lines) {
                $cleaned_lines[] = $line;
            }
        }

        return implode("\n", $cleaned_lines);
    }

    /**
     * Add a snippet to .htaccess with duplicate prevention
     */
    public function add_snippet($snippet_name, $snippet_content) {
        $current_content = $this->get_htaccess_content();

        // Check if snippet already exists
        if ($this->snippet_exists($current_content, $snippet_name)) {
            return array(
                'success' => false,
                'message' => sprintf(__('Snippet "%s" already exists in .htaccess file.', 'morden-toolkit'), $snippet_name)
            );
        }

        // Add snippet with proper formatting
        $formatted_snippet = $this->format_snippet($snippet_name, $snippet_content);
        $new_content = $current_content . "\n\n" . $formatted_snippet;

        // Save with validation
        if ($this->save_htaccess($new_content)) {
            return array(
                'success' => true,
                'message' => sprintf(__('Snippet "%s" added successfully.', 'morden-toolkit'), $snippet_name)
            );
        } else {
            return array(
                'success' => false,
                'message' => __('Failed to add snippet. .htaccess validation failed or caused 503 error.', 'morden-toolkit')
            );
        }
    }

    /**
     * Check if a snippet already exists in content
     */
    private function snippet_exists($content, $snippet_name) {
        $snippet_markers = array(
            "# BEGIN {$snippet_name}",
            "# {$snippet_name}",
            "# BEGIN Morden Toolkit - {$snippet_name}",
            "# Browser Caching", // for cache_control
            "# GZIP Compression", // for gzip_compression
            "# Security Headers", // for security_headers
            "# BEGIN WordPress" // for wordpress_rewrite
        );

        foreach ($snippet_markers as $marker) {
            if (strpos($content, $marker) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Format snippet with proper markers
     */
    private function format_snippet($snippet_name, $snippet_content) {
        $timestamp = date('Y-m-d H:i:s');

        $formatted = "# BEGIN Morden Toolkit - {$snippet_name}\n";
        $formatted .= "# Added on: {$timestamp}\n";
        $formatted .= trim($snippet_content) . "\n";
        $formatted .= "# END Morden Toolkit - {$snippet_name}";

        return $formatted;
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
     * Get common .htaccess snippets with status information
     */
    public function get_common_snippets() {
        $current_content = $this->get_htaccess_content();

        $snippets = array(
            'wordpress_rewrite' => array(
                'title' => __('WordPress Rewrite Rules', 'morden-toolkit'),
                'content' => "# BEGIN WordPress\n<IfModule mod_rewrite.c>\nRewriteEngine On\nRewriteRule ^index\\.php$ - [L]\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteCond %{REQUEST_FILENAME} !-d\nRewriteRule . /index.php [L]\n</IfModule>\n# END WordPress",
                'exists' => $this->snippet_exists($current_content, 'wordpress_rewrite')
            ),
            'cache_control' => array(
                'title' => __('Browser Caching', 'morden-toolkit'),
                'content' => "# Browser Caching\n<IfModule mod_expires.c>\nExpiresActive On\nExpiresByType text/css \"access plus 1 year\"\nExpiresByType application/javascript \"access plus 1 year\"\nExpiresByType image/png \"access plus 1 year\"\nExpiresByType image/jpg \"access plus 1 year\"\nExpiresByType image/jpeg \"access plus 1 year\"\nExpiresByType image/gif \"access plus 1 year\"\n</IfModule>",
                'exists' => $this->snippet_exists($current_content, 'cache_control')
            ),
            'gzip_compression' => array(
                'title' => __('GZIP Compression', 'morden-toolkit'),
                'content' => "# GZIP Compression\n<IfModule mod_deflate.c>\nAddOutputFilterByType DEFLATE text/plain\nAddOutputFilterByType DEFLATE text/html\nAddOutputFilterByType DEFLATE text/xml\nAddOutputFilterByType DEFLATE text/css\nAddOutputFilterByType DEFLATE application/xml\nAddOutputFilterByType DEFLATE application/xhtml+xml\nAddOutputFilterByType DEFLATE application/rss+xml\nAddOutputFilterByType DEFLATE application/javascript\nAddOutputFilterByType DEFLATE application/x-javascript\n</IfModule>",
                'exists' => $this->snippet_exists($current_content, 'gzip_compression')
            ),
            'security_headers' => array(
                'title' => __('Security Headers', 'morden-toolkit'),
                'content' => "# Security Headers\n<IfModule mod_headers.c>\nHeader always set X-Content-Type-Options nosniff\nHeader always set X-Frame-Options SAMEORIGIN\nHeader always set X-XSS-Protection \"1; mode=block\"\nHeader always set Referrer-Policy \"strict-origin-when-cross-origin\"\n</IfModule>",
                'exists' => $this->snippet_exists($current_content, 'security_headers')
            )
        );

        return $snippets;
    }

    /**
     * Remove a specific snippet from .htaccess
     */
    public function remove_snippet($snippet_name) {
        $current_content = $this->get_htaccess_content();

        if (!$this->snippet_exists($current_content, $snippet_name)) {
            return array(
                'success' => false,
                'message' => sprintf(__('Snippet "%s" not found in .htaccess file.', 'morden-toolkit'), $snippet_name)
            );
        }

        // Remove the snippet
        $new_content = $this->remove_snippet_from_content($current_content, $snippet_name);

        // Save with validation
        if ($this->save_htaccess($new_content)) {
            return array(
                'success' => true,
                'message' => sprintf(__('Snippet "%s" removed successfully.', 'morden-toolkit'), $snippet_name)
            );
        } else {
            return array(
                'success' => false,
                'message' => __('Failed to remove snippet. .htaccess validation failed.', 'morden-toolkit')
            );
        }
    }

    /**
     * Remove specific snippet from content
     */
    private function remove_snippet_from_content($content, $snippet_name) {
        $lines = explode("\n", $content);
        $cleaned_lines = array();
        $skip_lines = false;

        $snippet_patterns = array(
            'wordpress_rewrite' => array('# BEGIN WordPress', '# END WordPress'),
            'cache_control' => array('# Browser Caching', '</IfModule>'),
            'gzip_compression' => array('# GZIP Compression', '</IfModule>'),
            'security_headers' => array('# Security Headers', '</IfModule>')
        );

        $pattern = $snippet_patterns[$snippet_name] ?? array("# BEGIN Morden Toolkit - {$snippet_name}", "# END Morden Toolkit - {$snippet_name}");

        foreach ($lines as $line) {
            $line_trimmed = trim($line);

            // Check if this line starts the snippet to remove
            if (strpos($line_trimmed, $pattern[0]) !== false) {
                $skip_lines = true;
                continue;
            }

            // Check if this line ends the snippet to remove
            if ($skip_lines && strpos($line_trimmed, $pattern[1]) !== false) {
                $skip_lines = false;
                continue;
            }

            // Add line if we're not skipping
            if (!$skip_lines) {
                $cleaned_lines[] = $line;
            }
        }

        return implode("\n", $cleaned_lines);
    }
}
