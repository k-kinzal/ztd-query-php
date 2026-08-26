<?php

declare(strict_types=1);

namespace SqlFixture\Hydrator;

use ReflectionNamedType;
use ReflectionType;

/**
 * Reads a value as the type a property or parameter was declared with.
 *
 * A database driver hands back what the wire format gave it, which for most
 * drivers means integers and booleans arrive as strings. Assigning one of
 * those to a typed property would fail, so the declared type is what decides
 * how the value is read.
 *
 * A value that cannot be read as the declared type is passed through
 * unchanged, so PHP raises the type error at the assignment where it can name
 * the property, rather than this silently turning the wrong value into a
 * plausible one.
 */
final class DeclaredTypeCast
{
    /**
     * Reads a value as the declared type.
     *
     * A union or intersection type says nothing about which member the value
     * should be read as, so such a value is left alone.
     *
     * @param mixed $value Value as it arrived
     * @param ReflectionType|null $type Type it is being assigned to, or null when none was declared
     *
     * @return mixed The value, read as that type where it can be
     */
    public function of(mixed $value, ?ReflectionType $type): mixed
    {
        return $type instanceof ReflectionNamedType ? $this->asType($value, $type->getName()) : $value;
    }

    /**
     * Reads a value as the type of that name.
     *
     * @param mixed $value Value as it arrived
     * @param string $typeName Name of the type it is being read as
     *
     * @return mixed The value, read as that type where it can be
     */
    public function asType(mixed $value, string $typeName): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($typeName) {
            'int' => is_numeric($value) ? (int) $value : $value,
            'float' => is_numeric($value) ? (float) $value : $value,
            'string' => is_scalar($value) ? (string) $value : $value,
            'bool' => (bool) $value,
            'array' => is_string($value) ? json_decode($value, true) ?? [$value] : (array) $value,
            default => $value,
        };
    }
}
