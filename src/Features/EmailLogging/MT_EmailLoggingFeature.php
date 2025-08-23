<?php

namespace ModernToolkit\Features\EmailLogging;

use ModernToolkit\Features\MT_AbstractFeature;
use ModernToolkit\Core\MT_ServiceContainer;
use ModernToolkit\Core\MT_EventDispatcher;

class MT_EmailLoggingFeature extends MT_AbstractFeature {

    public function __construct(MT_ServiceContainer $container, MT_EventDispatcher $eventDispatcher) {
        parent::__construct($container, $eventDispatcher);
    }

    public function getId(): string {
        return 'email_logging';
    }

    public function getName(): string {
        return 'Email Logging';
    }

    public function getDescription(): string {
        return 'SMTP email logging and monitoring feature';
    }

    public function getDependencies(): array {
        return [];
    }

    public function boot(): void {
        $this->registerServices();
        $this->registerHooks();
    }

    protected function registerServices(): void {
        $this->container->singleton('email_logging.smtp_logger', function() {
            return new Services\MT_SmtpLogger();
        });
    }

    protected function registerHooks(): void {
        // Register SMTP logging hooks
        if (\get_option('mt_smtp_logging_enabled', false)) {
            \add_action('wp_mail', [$this, 'logEmail'], 10, 1);
            \add_action('wp_mail_failed', [$this, 'logEmailFailure'], 10, 1);
        }
    }

    public function logEmail($mail_data): array {
        $smtp_logger = $this->container->get('email_logging.smtp_logger');
        if ($smtp_logger) {
            $smtp_logger->log_email($mail_data);
        }
        return $mail_data;
    }

    public function logEmailFailure($wp_error): void {
        $smtp_logger = $this->container->get('email_logging.smtp_logger');
        if ($smtp_logger) {
            $smtp_logger->log_email_failure($wp_error);
        }
    }
}