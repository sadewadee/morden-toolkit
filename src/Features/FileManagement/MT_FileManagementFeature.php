<?php

namespace ModernToolkit\Features\FileManagement;

use ModernToolkit\Features\MT_AbstractFeature;

class MT_FileManagementFeature extends MT_AbstractFeature {
    public function getId(): string {
        return 'file_management';
    }

    public function getName(): string {
        return \__('File Management', 'morden-toolkit');
    }

    public function getDescription(): string {
        return \__('Safe .htaccess and file editing with backup system', 'morden-toolkit');
    }

    public function getServices(): array {
        return [
            'file_management.htaccess_manager' => [
                'concrete' => 'ModernToolkit\\Features\\FileManagement\\Services\\MT_HtaccessManager',
                'options' => ['singleton' => true]
            ],
            'file_management.backup_manager' => [
                'concrete' => 'ModernToolkit\\Features\\FileManagement\\Services\\MT_BackupManager',
                'options' => ['singleton' => true]
            ]
        ];
    }

    protected function registerServices(): void {
        $this->container->singleton('file_management.htaccess_manager', function() {
            return new Services\MT_HtaccessManager();
        });

        $this->container->singleton('file_management.backup_manager', function() {
            return new Services\MT_BackupManager();
        });
    }

    protected function registerHooks(): void {
        if (\is_admin()) {
            // htaccess editor is now integrated into the main toolkit page
            // No separate menu needed
            \add_action('admin_enqueue_scripts', [$this, 'enqueueScripts']);
        }
    }

    public function enqueueScripts(string $hook): void {
        // JavaScript is handled by main MT_Plugin admin.js
        // No additional scripts needed for file management functionality
    }
}