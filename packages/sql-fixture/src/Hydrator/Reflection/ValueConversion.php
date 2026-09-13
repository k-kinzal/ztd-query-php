<?php

declare(strict_types=1);

namespace SqlFixture\Hydrator\Reflection;

/**
 * Converts database values to declared PHP types.
 *
 */
final class ValueConversion
{
    /**
     * Converts a database value to a compatible declared PHP scalar or array.
     */
    public function castValue(mixed $value, ?ConversionTarget $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            ConversionTarget::Integer => is_numeric($value) ? (int) $value : $value,
            ConversionTarget::Float => is_numeric($value) ? (float) $value : $value,
            ConversionTarget::String => is_scalar($value) ? (string) $value : $value,
            ConversionTarget::Boolean => (bool) $value,
            ConversionTarget::Array => is_string($value) ? json_decode($value, true) ?? [$value] : (array) $value,
            null => $value,
        };
    }
}
