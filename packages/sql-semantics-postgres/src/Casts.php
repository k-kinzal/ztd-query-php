<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql;

use SqlSemantics\Core\CompositionException;
use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Declaration\TypeName;
use SqlSemantics\Statement\Writer;

/**
 * Spells the targets of PostgreSQL's CAST, which names any type.
 *
 * A builtin type is spelled by its SQL name with the modifiers it declares:
 * a length, a precision and scale, the precision of a time or timestamp,
 * the fields and precision of an interval, and array dimensions. A named
 * type is spelled by its qualified name. A fact PostgreSQL does not have,
 * such as a sign or a character set, is an error.
 *
 * @visibility SqlSemantics
 */
trait Casts
{
    /**
     * Spells a type as the target of PostgreSQL's CAST.
     *
     * @throws CompositionException When the type is not a PostgreSQL type or states a fact PostgreSQL does not have
     */
    protected function castType(TypeDescriptor $type): string
    {
        $name = $type->name;
        if ($name instanceof TypeName) {
            $this->statesOnly($type, ['arrayDimensions']);

            return implode('.', array_map(fn (string $part): string => Writer::render($this->identifier($part)), $name->parts)) . str_repeat('[]', $type->arrayDimensions);
        }
        if (!TypeReader::supports($name)) {
            throw new CompositionException('PostgreSQL has no type ' . $type->label() . '.');
        }
        [$spelling, $facts] = match ($name) {
            Builtin::Numeric => ['numeric' . $this->modifiers($type->precision, $type->scale), ['precision', 'scale']],
            Builtin::Bit, Builtin::BitVarying, Builtin::Char, Builtin::VarChar => [$name->value . $this->modifiers($type->length), ['length']],
            Builtin::Time, Builtin::Timestamp => [$name->value . $this->modifiers($type->precision), ['precision']],
            Builtin::TimeTz, Builtin::TimestampTz => [str_replace(' with time zone', $this->modifiers($type->precision) . ' with time zone', $name->value), ['precision']],
            Builtin::Interval => [$this->interval($type), ['intervalFields', 'precision']],
            default => [$name->value, []],
        };
        $this->statesOnly($type, [...$facts, 'arrayDimensions']);

        return $spelling . str_repeat('[]', $type->arrayDimensions);
    }

    /**
     * Spells an interval with its fields, the precision after the seconds field or the word itself.
     *
     * @throws CompositionException When a precision is declared for fields without seconds
     */
    protected function interval(TypeDescriptor $type): string
    {
        $fields = $type->intervalFields?->value;
        if ($type->precision !== null && $fields !== null && !str_ends_with($fields, 'second')) {
            throw new CompositionException('An interval of ' . $fields . ' has no precision.');
        }

        return 'interval' . ($fields === null ? $this->modifiers($type->precision) : ' ' . $fields . $this->modifiers($type->precision));
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
