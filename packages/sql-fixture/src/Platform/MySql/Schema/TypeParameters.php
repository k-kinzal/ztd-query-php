<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql\Schema;

use PhpMyAdmin\SqlParser\Components\DataType;

/**
 * Reads length, precision and scale from a type declaration.
 *
 * @visibility root
 */
final class TypeParameters
{
    /**
     * Recognizes the dialect numeric types that accept precision and scale.
     */
    public function isDecimalType(string $type): bool
    {
        return in_array($type, ['DECIMAL', 'NUMERIC', 'DEC', 'FIXED'], true);
    }

    /**
     * Recognizes the bit type whose parameter measures bits.
     */
    public function isBitType(string $type): bool
    {
        return $type === 'BIT';
    }

    /**
     * @template TParameter
     * @param array<TParameter> $parameters
     * @return list<string>
     */
    public function extractEnumValues(array $parameters): array
    {
        $values = [];
        foreach ($parameters as $param) {
            if (is_string($param)) {
                $value = trim($param, '\'"');
                $values[] = $value;
            }
        }
        return $values;
    }

    /**
     * Interprets the declared type parameters before column constraints are applied.
     */
    public function parse(DataType $type): \SqlFixture\Schema\TypeShape
    {
        $length = null;
        $precision = null;
        $scale = null;

        $parameters = $type->parameters;
        if ($parameters !== []) {
            if ((new TypeParameters())->isDecimalType(strtoupper($type->name))) {
                $precision = isset($parameters[0]) ? (int) $parameters[0] : 10;
                $scale = isset($parameters[1]) ? (int) $parameters[1] : 0;
            } elseif ((new TypeParameters())->isBitType(strtoupper($type->name))) {
                $length = isset($parameters[0]) ? (int) $parameters[0] : 1;
            } else {
                $length = isset($parameters[0]) ? (int) $parameters[0] : null;
            }
        }
        return new \SqlFixture\Schema\TypeShape(strtoupper($type->name), $length, $precision, $scale);
    }
}
