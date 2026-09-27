<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql;

use SqlSemantics\Core\CompositionException;
use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Writer;

/**
 * Spells the targets of MySQL's CAST.
 *
 * CAST names few types, each in the releases that read it. `CHAR(n)`
 * gives a VARCHAR(n), and `CHAR` a VARCHAR as long as the value; `BINARY`
 * gives a VARBINARY as long as the value, while `BINARY(n)` pads the value
 * to n bytes, which no declared type does. `SIGNED` and `UNSIGNED` give a
 * BIGINT; then `DECIMAL`, `DOUBLE`, `FLOAT`, the temporal types, `JSON`,
 * and the spatial types. No target names INT, CHAR, TEXT, or ENUM, so a
 * cast to such a type is a CompositionException rather than a cast to a
 * nearby type.
 *
 * @visibility SqlSemantics
 */
trait Casts
{
    /**
     * Spells a type as the target of MySQL's CAST.
     *
     * @throws CompositionException When CAST has no target of that type or cannot state one of its facts
     */
    protected function castType(TypeDescriptor $type): string
    {
        $name = $type->name;
        [$spelling, $facts] = match (true) {
            $name === Builtin::VarChar => ['CHAR' . $this->modifiers($type->length) . ($type->characterSet === null ? '' : ' CHARACTER SET ' . Writer::render($this->identifier($type->characterSet))) . ($type->binaryCollation ? ' BINARY' : ''), ['length', 'characterSet', 'binaryCollation']],
            $name === Builtin::VarBinary && $type->length === null => ['BINARY', []],
            $name === Builtin::BigInt => [$type->unsigned ? 'UNSIGNED' : 'SIGNED', ['unsigned']],
            $name === Builtin::Numeric => ['DECIMAL' . $this->modifiers($type->precision, $type->scale), ['precision', 'scale']],
            $name === Builtin::DoublePrecision => ['DOUBLE', []],
            $name === Builtin::Real => ['FLOAT' . $this->modifiers($type->precision), ['precision']],
            $name === Builtin::Date => ['DATE', []],
            $name === Builtin::Year => ['YEAR', []],
            $name === Builtin::Time => ['TIME' . $this->modifiers($type->precision), ['precision']],
            $name === Builtin::DateTime => ['DATETIME' . $this->modifiers($type->precision), ['precision']],
            $name === Builtin::Json => ['JSON', []],
            $name instanceof Builtin && in_array($name, [Builtin::Point, Builtin::LineString, Builtin::Polygon, Builtin::MultiPoint, Builtin::MultiLineString, Builtin::MultiPolygon, Builtin::GeometryCollection], true) => [strtoupper($name->value), []],
            default => throw new CompositionException('MySQL\'s CAST has no target of type ' . $type->label() . '.'),
        };
        $this->statesOnly($type, $facts);

        return $spelling;
    }

    /**
     * Spells the numeric modifiers of a type, the ones given, in parentheses.
     */
    protected function modifiers(?int ...$values): string
    {
        $given = array_filter($values, static fn (?int $value): bool => $value !== null);

        return $given === [] ? '' : '(' . implode(',', $given) . ')';
    }
}
