<?php

namespace ModernToolkit\Features\FileManagement\Services;

class MT_BackupManager {

    private $backup_dir;
    private $max_backups_per_type;

    public function __construct() {
        $this->backup_dir = WP_CONTENT_DIR . '/mt-backups/';
        $this->max_backups_per_type = 10;
        $this->ensureBackupDirectory();
    }

    /**
     * Create a backup of any file
     */
    public function create_file_backup(string $file_path, string $backup_type = 'manual'): bool {
        try {
            if (!file_exists($file_path)) {
                return false;
            }

            $filename = basename($file_path);
            $backup_filename = $this->generateBackupFilename($filename, $backup_type);
            $backup_path = $this->backup_dir . $backup_filename;

            $result = copy($file_path, $backup_path);

            if ($result) {
                $this->saveBackupMetadata($backup_filename, [
                    'original_file' => $file_path,
                    'backup_type' => $backup_type,
                    'created_at' => current_time('mysql'),
                    'file_size' => filesize($file_path),
                    'checksum' => md5_file($file_path)
                ]);

                $this->cleanupOldBackups($filename);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            \error_log('MT Backup Manager Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Restore a file from backup
     */
    public function restore_file_backup(string $backup_filename, string $target_file): bool {
        try {
            $backup_path = $this->backup_dir . $backup_filename;

            if (!file_exists($backup_path)) {
                return false;
            }

            // Create a backup of current file before restoring
            if (file_exists($target_file)) {
                $this->create_file_backup($target_file, 'pre_restore');
            }

            $result = copy($backup_path, $target_file);

            if ($result) {
                // Set proper permissions
                $this->setFilePermissions($target_file);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            \error_log('MT Backup Manager Restore Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get list of backups for a specific file
     */
    public function get_file_backups(string $filename): array {
        $backups = [];
        $pattern = $this->backup_dir . $this->escapeFilename($filename) . '_*';
        $backup_files = glob($pattern);

        foreach ($backup_files as $backup_file) {
            $backup_filename = basename($backup_file);
            $metadata = $this->getBackupMetadata($backup_filename);

            $backups[] = [
                'filename' => $backup_filename,
                'path' => $backup_file,
                'size' => filesize($backup_file),
                'size_formatted' => \mt_format_bytes(filesize($backup_file)),
                'created_at' => $metadata['created_at'] ?? '',
                'backup_type' => $metadata['backup_type'] ?? 'unknown',
                'original_file' => $metadata['original_file'] ?? '',
                'checksum' => $metadata['checksum'] ?? ''
            ];
        }

        // Sort by creation time (newest first)
        usort($backups, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        return $backups;
    }

    /**
     * Delete a specific backup
     */
    public function delete_backup(string $backup_filename): bool {
        try {
            $backup_path = $this->backup_dir . $backup_filename;

            if (file_exists($backup_path)) {
                $result = unlink($backup_path);

                if ($result) {
                    $this->deleteBackupMetadata($backup_filename);
                    return true;
                }
            }

            return false;
        } catch (\Exception $e) {
            \error_log('MT Backup Manager Delete Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get backup statistics
     */
    public function get_backup_statistics(): array {
        try {
            $backup_files = glob($this->backup_dir . '*');
            $total_size = 0;
            $backup_count = 0;
            $types = [];

            foreach ($backup_files as $file) {
                if (is_file($file)) {
                    $backup_count++;
                    $total_size += filesize($file);

                    $metadata = $this->getBackupMetadata(basename($file));
                    $type = $metadata['backup_type'] ?? 'unknown';

                    if (!isset($types[$type])) {
                        $types[$type] = 0;
                    }
                    $types[$type]++;
                }
            }

            return [
                'total_backups' => $backup_count,
                'total_size' => $total_size,
                'total_size_formatted' => \mt_format_bytes($total_size),
                'backup_types' => $types,
                'backup_directory' => $this->backup_dir,
                'directory_writable' => is_writable($this->backup_dir)
            ];
        } catch (\Exception $e) {
            \error_log('MT Backup Manager Statistics Error: ' . $e->getMessage());
            return [
                'total_backups' => 0,
                'total_size' => 0,
                'total_size_formatted' => '0 B',
                'backup_types' => [],
                'backup_directory' => $this->backup_dir,
                'directory_writable' => false
            ];
        }
    }

    /**
     * Clean up old backups
     */
    public function cleanup_old_backups(?string $filename = null): int {
        $cleaned = 0;

        try {
            if ($filename) {
                // Clean up specific file's backups
                $backups = $this->get_file_backups($filename);

                if (count($backups) > $this->max_backups_per_type) {
                    $excess_backups = array_slice($backups, $this->max_backups_per_type);

                    foreach ($excess_backups as $backup) {
                        if ($this->delete_backup($backup['filename'])) {
                            $cleaned++;
                        }
                    }
                }
            } else {
                // Clean up all old backups
                $backup_files = glob($this->backup_dir . '*');
                $cutoff_date = strtotime('-30 days'); // Keep backups for 30 days

                foreach ($backup_files as $file) {
                    if (is_file($file) && filemtime($file) < $cutoff_date) {
                        if (unlink($file)) {
                            $this->deleteBackupMetadata(basename($file));
                            $cleaned++;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            \error_log('MT Backup Manager Cleanup Error: ' . $e->getMessage());
        }

        return $cleaned;
    }

    /**
     * Verify backup integrity
     */
    public function verify_backup_integrity(string $backup_filename): bool {
        try {
            $backup_path = $this->backup_dir . $backup_filename;

            if (!file_exists($backup_path)) {
                return false;
            }

            $metadata = $this->getBackupMetadata($backup_filename);
            $stored_checksum = $metadata['checksum'] ?? '';

            if (empty($stored_checksum)) {
                return false; // No checksum to verify against
            }

            $current_checksum = md5_file($backup_path);
            return $current_checksum === $stored_checksum;
        } catch (\Exception $e) {
            \error_log('MT Backup Manager Verify Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Private helper methods
     */
    private function ensureBackupDirectory(): void {
        if (!file_exists($this->backup_dir)) {
            wp_mkdir_p($this->backup_dir);

            // Create index.php to prevent directory listing
            $index_content = "<?php\n// Silence is golden.\n";
            file_put_contents($this->backup_dir . 'index.php', $index_content);
        }
    }

    private function generateBackupFilename(string $filename, string $backup_type): string {
        $timestamp = date('Y-m-d_H-i-s');
        $escaped_filename = $this->escapeFilename($filename);
        return "{$escaped_filename}_{$backup_type}_{$timestamp}";
    }

    private function escapeFilename(string $filename): string {
        return preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
    }

    private function saveBackupMetadata(string $backup_filename, array $metadata): void {
        $metadata_file = $this->backup_dir . $backup_filename . '.meta';
        file_put_contents($metadata_file, json_encode($metadata));
    }

    private function getBackupMetadata(string $backup_filename): array {
        $metadata_file = $this->backup_dir . $backup_filename . '.meta';

        if (!file_exists($metadata_file)) {
            return [];
        }

        $content = file_get_contents($metadata_file);
        $metadata = json_decode($content, true);

        return is_array($metadata) ? $metadata : [];
    }

    private function deleteBackupMetadata(string $backup_filename): void {
        $metadata_file = $this->backup_dir . $backup_filename . '.meta';

        if (file_exists($metadata_file)) {
            unlink($metadata_file);
        }
    }

    private function setFilePermissions(string $file_path): void {
        if (file_exists($file_path)) {
            $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

            switch ($ext) {
                case 'htaccess':
                case 'php':
                    chmod($file_path, 0644);
                    break;
                default:
                    chmod($file_path, 0644);
                    break;
            }
        }
    }
}