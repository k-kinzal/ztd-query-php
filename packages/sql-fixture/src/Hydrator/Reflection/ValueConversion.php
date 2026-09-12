<?php

declare(strict_types=1);

namespace SqlFixture\Hydrator\Reflection;

use ReflectionNamedType;
use ReflectionType;

/**
 * Converts database values to declared PHP types.
 *
 */
final class ValueConversion
{
    /**
     * Converts a database value to a compatible declared PHP scalar or array.
     */
    public function castValue(mixed $value, ?ReflectionType $type): mixed
    {
        if ($value === null) {
            return null;
        }

        if (!$type instanceof ReflectionNamedType) {
            return $value;
        }

        $typeName = $type->getName();

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
