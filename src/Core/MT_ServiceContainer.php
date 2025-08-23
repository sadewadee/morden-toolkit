<?php

namespace ModernToolkit\Core;

class MT_ServiceContainer {
    private $services = [];
    private $singletons = [];
    private $aliases = [];

    public function get(string $id) {
        if (isset($this->aliases[$id])) {
            $id = $this->aliases[$id];
        }

        if (isset($this->singletons[$id])) {
            return $this->singletons[$id];
        }

        if (!isset($this->services[$id])) {
            throw new \Exception("Service '{$id}' not found in container.");
        }

        $service = $this->resolve($id);

        if (isset($this->services[$id]['singleton']) && $this->services[$id]['singleton']) {
            $this->singletons[$id] = $service;
        }

        return $service;
    }

    public function has(string $id): bool {
        return isset($this->services[$id]) || isset($this->aliases[$id]);
    }

    public function bind(string $id, $concrete, array $options = []): void {
        $this->services[$id] = [
            'concrete' => $concrete,
            'singleton' => $options['singleton'] ?? false,
            'dependencies' => $options['dependencies'] ?? []
        ];
    }

    public function singleton(string $id, $concrete, array $options = []): void {
        $options['singleton'] = true;
        $this->bind($id, $concrete, $options);
    }

    public function alias(string $alias, string $service): void {
        $this->aliases[$alias] = $service;
    }

    protected function resolve(string $id) {
        $service = $this->services[$id];

        if (is_callable($service['concrete'])) {
            return call_user_func($service['concrete'], $this);
        }

        if (is_string($service['concrete'])) {
            return $this->build($service['concrete'], $service['dependencies']);
        }

        return $service['concrete'];
    }

    protected function build(string $className, array $dependencies = []) {
        $reflectionClass = new \ReflectionClass($className);

        if (!$reflectionClass->isInstantiable()) {
            throw new \Exception("Class '{$className}' is not instantiable.");
        }

        $constructor = $reflectionClass->getConstructor();

        if (null === $constructor) {
            return new $className;
        }

        $parameters = $constructor->getParameters();
        $resolvedDependencies = [];

        foreach ($parameters as $parameter) {
            $dependencyName = $parameter->getName();

            if (isset($dependencies[$dependencyName])) {
                $resolvedDependencies[] = $this->get($dependencies[$dependencyName]);
                continue;
            }

            $parameterType = $parameter->getType();
            if ($parameterType && !$parameterType->isBuiltin()) {
                $className = $parameterType->getName();
                if ($this->has($className)) {
                    $resolvedDependencies[] = $this->get($className);
                    continue;
                }
            }

            if ($parameter->isDefaultValueAvailable()) {
                $resolvedDependencies[] = $parameter->getDefaultValue();
            } else {
                throw new \Exception("Cannot resolve dependency '{$dependencyName}' for class '{$className}'.");
            }
        }

        return $reflectionClass->newInstanceArgs($resolvedDependencies);
    }
}