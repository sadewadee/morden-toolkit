<?php

namespace ModernToolkit\Core;

class MT_EventDispatcher {
    private $listeners = [];

    public function addListener(string $event, callable $listener, int $priority = 10): void {
        if (!isset($this->listeners[$event])) {
            $this->listeners[$event] = [];
        }

        if (!isset($this->listeners[$event][$priority])) {
            $this->listeners[$event][$priority] = [];
        }

        $this->listeners[$event][$priority][] = $listener;
    }

    public function dispatch(string $event, array $data = []): void {
        if (!isset($this->listeners[$event])) {
            return;
        }

        ksort($this->listeners[$event]);

        foreach ($this->listeners[$event] as $priority => $listeners) {
            foreach ($listeners as $listener) {
                call_user_func($listener, $data);
            }
        }
    }

    public function removeListener(string $event, callable $listener): void {
        if (!isset($this->listeners[$event])) {
            return;
        }

        foreach ($this->listeners[$event] as $priority => &$listeners) {
            foreach ($listeners as $index => $registeredListener) {
                if ($registeredListener === $listener) {
                    unset($listeners[$index]);
                    break;
                }
            }
        }
    }

    public function hasListeners(string $event): bool {
        return isset($this->listeners[$event]) && !empty($this->listeners[$event]);
    }

    public function getListeners(?string $event = null): array {
        if ($event !== null) {
            return $this->listeners[$event] ?? [];
        }

        return $this->listeners;
    }
}