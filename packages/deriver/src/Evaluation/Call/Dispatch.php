<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Call;

use Deriver\ControlFlow\Program;
use Deriver\Evaluation\Call\Creation\Builtins;

/**
 * Resolves ordinary PHP inheritance, traits, and known method candidates.
 * @visibility root
 */
final class Dispatch
{
    /**
     * @param Program $program Declaration world
     */
    public function __construct(public readonly Program $program)
    {
    }

    /**
     * Finds the implementation inherited by one known runtime class.
     * @param string $class Runtime class
     * @param string $method Method name
     * @param list<string> $seen Visited inheritance nodes
     * @return string|null Implementing callable identity
     */
    public function method(string $class, string $method, array $seen = []): ?string
    {
        $key = strtolower($class);
        if (in_array($key, $seen, true)) {
            return null;
        }
        $declaration = $this->program->classes()[$key] ?? null;
        if ($declaration === null) {
            return null;
        }
        $seen[] = $key;
        if (isset($declaration->methods[strtolower($method)])) {
            return $declaration->methods[strtolower($method)];
        }
        foreach ($declaration->composed ? [] : $declaration->traits as $trait) {
            $candidate = $this->method($trait, $method, $seen);
            if ($candidate !== null) {
                return $candidate;
            }
        }
        return $declaration->parent === '' ? null : $this->method($declaration->parent, $method, $seen);
    }

    /**
     * Checks whether every parent and used trait is available for method lookup.
     * @param string $class Runtime class
     * @param list<string> $seen Visited declarations
     * @return bool Whether a missing method is proved absent
     */
    public function complete(string $class, array $seen = []): bool
    {
        $key = strtolower($class);
        if (in_array($key, $seen, true)) {
            return false;
        }
        $declaration = $this->program->classes()[$key] ?? null;
        if ($declaration === null) {
            return (new Builtins())->name($class) !== null;
        }
        foreach ([$declaration->parent, ...$declaration->traits] as $ancestor) {
            if ($ancestor !== '' && !$this->complete($ancestor, [...$seen, $key])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Determines whether a source class is a subtype of a declared bound.
     * @param string $class Runtime class
     * @param string $bound Declared type
     * @param list<string> $seen Visited inheritance nodes
     * @return bool Whether the relation is established
     */
    public function subtype(string $class, string $bound, array $seen = []): bool
    {
        if (strcasecmp($class, $bound) === 0 || $bound === 'object') {
            return true;
        }
        if (strcasecmp($bound, 'Stringable') === 0 && $this->method($class, '__toString') !== null) {
            return true;
        }
        if (in_array(strtolower($class), $seen, true)) {
            return false;
        }
        $seen[] = strtolower($class);
        $declaration = $this->program->classes()[strtolower($class)] ?? null;
        if ($declaration === null) {
            $parent = (new Builtins())->parent($class);
            return $parent !== '' && $this->subtype($parent, $bound, $seen);
        }
        foreach ([$declaration->parent, ...$declaration->interfaces] as $parent) {
            if ($parent !== '' && $this->subtype($parent, $bound, $seen)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Enumerates concrete source implementations consistent with a type bound.
     * @param string $bound Receiver type bound
     * @param string $method Method name
     * @return array<string, string> Runtime class to implementing callable
     */
    public function candidates(string $bound, string $method): array
    {
        $candidates = [];
        foreach ($this->program->classes() as $class) {
            $bounds = explode('|', $bound);
            if (!$class->interface && !$class->abstract && array_filter($bounds, fn (string $type): bool => $this->subtype($class->name, $type)) !== []) {
                $target = $this->method($class->name, $method) ?? $this->method($class->name, '__call');
                if ($target !== null) {
                    $candidates[$class->name] = $target;
                }
            }
        }
        ksort($candidates);
        return $candidates;
    }

    /**
     * Resolves self, parent, and late static names.
     * @param string $name Spelled class reference
     * @param string $scope Lexical class
     * @param string $lateStaticClass Bound runtime class
     * @return string Resolved class
     */
    public function className(string $name, string $scope, string $lateStaticClass): string
    {
        return match (strtolower($name)) {
            'self' => $scope,
            'parent' => $this->program->classes()[strtolower($scope)]->parent ?? '',
            'static' => $lateStaticClass === '' ? $scope : $lateStaticClass,
            default => $name,
        };
    }
}
