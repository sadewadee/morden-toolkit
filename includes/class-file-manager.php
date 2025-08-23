<?php

namespace ModernToolkit;

if (!defined('ABSPATH')) {
    exit;
}

class FileManager {

    public function create_backup($file_path, $backup_key) {
        if (!file_exists($file_path)) {
            return false;
        }

        $content = file_get_contents($file_path);
        $backups = get_option("morden_{$backup_key}_backups", array());

        $backup = array(
            'timestamp' => current_time('timestamp'),
            'content' => $content,
            'size' => strlen($content),
            'file_path' => $file_path
        );

        array_unshift($backups, $backup);

        if (count($backups) > 3) {
            $backups = array_slice($backups, 0, 3);
        }

        return update_option("morden_{$backup_key}_backups", $backups);
    }

    public function get_backups($backup_key) {
        return get_option("morden_{$backup_key}_backups", array());
    }

    public function restore_backup($backup_key, $backup_index) {
        $backups = $this->get_backups($backup_key);

        if (!isset($backups[$backup_index])) {
            return false;
        }

        $backup = $backups[$backup_index];

        if (!isset($backup['file_path']) || !isset($backup['content'])) {
            return false;
        }

        $this->create_backup($backup['file_path'], $backup_key);

        return file_put_contents($backup['file_path'], $backup['content']) !== false;
    }

    public function delete_backup($backup_key, $backup_index) {
        $backups = $this->get_backups($backup_key);

        if (!isset($backups[$backup_index])) {
            return false;
        }

        unset($backups[$backup_index]);
        $backups = array_values($backups);

        return update_option("morden_{$backup_key}_backups", $backups);
    }

    public function clear_backups($backup_key) {
        return delete_option("morden_{$backup_key}_backups");
    }

    public function get_backup_stats($backup_key) {
        $backups = $this->get_backups($backup_key);

        $stats = array(
            'count' => count($backups),
            'total_size' => 0,
            'oldest_backup' => null,
            'newest_backup' => null
        );

        if (!empty($backups)) {
            foreach ($backups as $backup) {
                $stats['total_size'] += $backup['size'];
            }

            $stats['oldest_backup'] = end($backups);
            $stats['newest_backup'] = reset($backups);
        }

        return $stats;
    }

    public function export_backup($backup_key, $backup_index) {
        $backups = $this->get_backups($backup_key);

        if (!isset($backups[$backup_index])) {
            return false;
        }

        $backup = $backups[$backup_index];
        $filename = $backup_key . '_backup_' . date('Y-m-d_H-i-s', $backup['timestamp']) . '.txt';

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($backup['content']));

        echo $backup['content'];
        exit;
    }

    public function import_backup($backup_key, $uploaded_file) {
        if (!isset($uploaded_file['tmp_name']) || !is_uploaded_file($uploaded_file['tmp_name'])) {
            return false;
        }

        $content = file_get_contents($uploaded_file['tmp_name']);

        if ($content === false) {
            return false;
        }

        if (!$this->validate_backup_content($backup_key, $content)) {
            return false;
        }

        $backups = get_option("morden_{$backup_key}_backups", array());

        $backup = array(
            'timestamp' => current_time('timestamp'),
            'content' => $content,
            'size' => strlen($content),
            'imported' => true,
            'original_filename' => $uploaded_file['name']
        );

        array_unshift($backups, $backup);

        if (count($backups) > 3) {
            $backups = array_slice($backups, 0, 3);
        }

        return update_option("morden_{$backup_key}_backups", $backups);
    }

    private function validate_backup_content($backup_key, $content) {
        switch ($backup_key) {
            case 'htaccess':
                return $this->validate_htaccess_content($content);

            case 'wp_config':
                return strpos($content, '<?php') !== false;

            default:
                return true;
        }
    }

    private function validate_htaccess_content($content) {
        $dangerous_patterns = array(
            '/<\?php/i',
            '/eval\s*\(/i',
            '/exec\s*\(/i',
            '/system\s*\(/i'
        );

        foreach ($dangerous_patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return false;
            }
        }

        return true;
    }

    public function get_file_info($file_path) {
        $info = array(
            'exists' => false,
            'readable' => false,
            'writable' => false,
            'size' => 0,
            'modified' => null,
            'permissions' => null
        );

        if (file_exists($file_path)) {
            $info['exists'] = true;
            $info['readable'] = is_readable($file_path);
            $info['writable'] = is_writable($file_path);
            $info['size'] = filesize($file_path);
            $info['modified'] = filemtime($file_path);
            $info['permissions'] = substr(sprintf('%o', fileperms($file_path)), -4);
        }

        return $info;
    }

    public function safe_file_write($file_path, $content) {
        $temp_file = $file_path . '.tmp';

        $result = file_put_contents($temp_file, $content, LOCK_EX);

        if ($result === false) {
            return false;
        }

        if (!rename($temp_file, $file_path)) {
            unlink($temp_file);
            return false;
        }

        return true;
    }

    public function ensure_directory($dir_path) {
        if (!is_dir($dir_path)) {
            return wp_mkdir_p($dir_path);
        }

        return true;
    }

    public function get_directory_size($dir_path) {
        if (!is_dir($dir_path)) {
            return 0;
        }

        $size = 0;
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir_path),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }

    public function cleanup_temp_files() {
        $temp_pattern = ABSPATH . '*.tmp';
        $temp_files = glob($temp_pattern);
        $cleaned = 0;

        foreach ($temp_files as $temp_file) {
            if (filemtime($temp_file) < (time() - 3600)) {
                if (unlink($temp_file)) {
                    $cleaned++;
                }
            }
        }

        return $cleaned;
    }
}
