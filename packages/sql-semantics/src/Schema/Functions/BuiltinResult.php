<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Functions;

use SqlSemantics\Dialect;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Argument-dependent result rules used by the default function signatures.
 *
 * @visibility SqlSemantics
 */
final class BuiltinResult
{
    /**
     * Uses the selected overload's function name and dialect.
     */
    public function __construct(public readonly string $name, public readonly Dialect $dialect)
    {
    }

    /**
     * Retains modifiers when a result has the argument's type.
     *
     * @param list<TypeDescriptor> $arguments
     */
    public function resolve(array $arguments): TypeDescriptor
    {
        $name = $this->name;
        $dialect = $this->dialect;
        $input = $arguments[0] ?? new TypeDescriptor($dialect, 'unknown');
        if ($name === 'ARRAY_AGG') {
            return new TypeDescriptor($dialect, $input->name === 'unknown' ? 'unknown' : $input->name . '[]', $input->modifiers);
        }
        if (!in_array($name, ['AVG', 'SUM'], true)) {
            return $input;
        }
        if ($input->name === 'unknown') {
            return $input;
        }
        if ($name === 'AVG') {
            return new TypeDescriptor($dialect, $dialect === Dialect::Sqlite ? 'real' : (in_array($input->name, ['real', 'double precision'], true) ? 'double precision' : 'numeric'));
        }
        return new TypeDescriptor($dialect, $dialect === Dialect::Sqlite ? 'dynamic' : ($dialect === Dialect::PostgreSql && in_array($input->name, ['smallint', 'integer'], true) ? 'bigint' : (in_array($input->name, ['real', 'double precision'], true) ? $input->name : 'numeric')));
    }
}
