<?php

namespace ModernToolkit\Core\Bootstrap;

class MT_Autoloader {
    private static $instance = null;
    private $namespaces = [];

    public static function getInstance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function register(): void {
        spl_autoload_register([$this, 'loadClass']);
    }

    public function addNamespace(string $prefix, string $baseDir): void {
        $prefix = trim($prefix, '\\') . '\\';
        $baseDir = rtrim($baseDir, DIRECTORY_SEPARATOR) . '/';

        if (!isset($this->namespaces[$prefix])) {
            $this->namespaces[$prefix] = [];
        }

        array_push($this->namespaces[$prefix], $baseDir);
    }

    public function loadClass(string $class): bool {
        $prefix = $class;

        while (false !== $pos = strrpos($prefix, '\\')) {
            $prefix = substr($class, 0, $pos + 1);
            $relativeClass = substr($class, $pos + 1);

            $mappedFile = $this->loadMappedFile($prefix, $relativeClass);
            if ($mappedFile) {
                return $mappedFile;
            }

            $prefix = rtrim($prefix, '\\');
        }

        return false;
    }

    protected function loadMappedFile(string $prefix, string $relativeClass): bool {
        if (!isset($this->namespaces[$prefix])) {
            return false;
        }

        foreach ($this->namespaces[$prefix] as $baseDir) {
            $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

            if ($this->requireFile($file)) {
                return true;
            }
        }

        return false;
    }

    protected function requireFile(string $file): bool {
        if (file_exists($file)) {
            require $file;
            return true;
        }
        return false;
    }
}