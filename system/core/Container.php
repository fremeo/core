<?php
/**
 * @doc Klasse_Container
 * Die Klasse Container ist ein einfacher Dependency Injection Container, der es ermöglicht, 
 * Dienste (Klasseninstanzen oder Closures) zu registrieren und bei Bedarf zu instanziieren. 
 * Sie unterstützt die Registrierung von Diensten innerhalb von Modul-Containern, sodass Dienste für 
 * verschiedene Module getrennt verwaltet werden können.
 */

namespace fremeo\core;

use ArrayAccess;
use ArrayIterator;
use Closure;
use Countable;
use IteratorAggregate;
use RuntimeException;
use Traversable;

class Container implements ArrayAccess, IteratorAggregate, Countable
{
    /**
     * @var array<string, mixed>
     */
    private array $services = [];

    /**
     * @var array<string, mixed>
     */
    private array $instances = [];

    /**
     * Registriert einen Dienst im Container.
     * Wenn $moduleId angegeben ist, wird der Dienst innerhalb eines Modul-Containers registriert
     */
    public function set(string $key, mixed $value, ?string $moduleId = null): self
    {
        if ($moduleId !== null && $moduleId !== '') {
            if (!isset($this->services[$moduleId])) {
                $this->services[$moduleId] = new self();
            }

            if ($this->services[$moduleId] instanceof self) {
                $this->services[$moduleId]->set($key, $value);
                return $this;
            }
        }

        $this->services[$key] = $value;
        return $this;
    }

    /**
     * Erstellt eine Instanz des Dienstes, wenn er noch nicht existiert, und gibt sie zurück.
     */
    public function make(string $key, array $args = [], ?string $moduleId = null): mixed
    {
        if ($moduleId !== null && $moduleId !== '') {
            if (!isset($this->services[$moduleId])) {
                throw new RuntimeException("Service '{$moduleId}' not found.");
            }

            $moduleContainer = $this->services[$moduleId];
            if ($moduleContainer instanceof self) {
                return $moduleContainer->make($key, $args);
            }
        }

        $serviceKey = $key;
        if (isset($this->instances[$serviceKey])) {
            return $this->instances[$serviceKey];
        }

        if (isset($this->services[$serviceKey])) {
            $service = $this->services[$serviceKey];

            if ($service instanceof Closure) {
                $instance = $service(...$args);
            } elseif (is_string($service) && class_exists($service)) {
                $instance = new $service(...$args);
            } else {
                $instance = $service;
            }

            $this->instances[$serviceKey] = $instance;

            return $instance;
        }

        foreach ($this->services as $candidateKey => $candidate) {
            if ($candidate instanceof self && $candidate->hasService($serviceKey)) {
                return $candidate->make($serviceKey, $args);
            }
        }

        throw new RuntimeException("Service '{$serviceKey}' not found.");
    }

    /**
     * Gibt den Container für ein bestimmtes Modul zurück.
     * Wenn der Modul-Container noch nicht existiert, wird er erstellt.
     */
    public function get(string $key, ?string $moduleId = null, array $args = []): mixed
    {
        return $this->make($key, $args, $moduleId);
    }

    /**
     * Gibt den Container für ein bestimmtes Modul zurück.
     * Wenn der Modul-Container noch nicht existiert, wird er erstellt.
     */
    public function getByModule(string $moduleId, string $key, array $args = []): mixed
    {
        return $this->make($key, $args, $moduleId);
    }

    public function __get(string $key): mixed
    {
        return $this->make($key);
    }

    public function __set(string $key, mixed $value): void
    {
        $this->set($key, $value);
    }

    /**
     * ArrayAccess-Methoden
     * Ermöglichen den Zugriff auf Dienste über Array-Syntax.
     */
    private function hasService(string $key): bool
    {
        return isset($this->services[$key]) || isset($this->instances[$key]);
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->hasService((string) $offset)
            || array_reduce(
                $this->services,
                fn (bool $found, mixed $candidate) => $found || ($candidate instanceof self && $candidate->hasService((string) $offset)),
                false
            );
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->make((string) $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->set((string) $offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->services[$offset], $this->instances[$offset]);
    }

    /**
     * Gibt einen Iterator für die registrierten Dienste zurück.
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->services);
    }

    /**
     * Gibt die Anzahl der registrierten Dienste zurück.
     */
    public function count(): int
    {
        return count($this->services);
    }

}
