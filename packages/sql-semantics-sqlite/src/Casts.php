<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite;

use SqlSemantics\Core\CompositionException;
use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Declaration\TypeName;
use SqlSemantics\Statement\Writer;

/**
 * Spells the targets of SQLite's CAST, a type name whose affinity the value takes.
 *
 * A builtin type is spelled by its name and a named type by its words, with
 * the length, or precision and scale, it declares. SQLite converts to the
 * affinity of the name, so a type whose affinity differs from the one its
 * spelling has, such as `ANY` in a STRICT table, is an error, and so is a
 * column without a declared type.
 *
 * @visibility SqlSemantics
 */
trait Casts
{
    /**
     * Spells a type as the target of SQLite's CAST.
     *
     * @throws CompositionException When the type has no name SQLite reads with its affinity, or states a fact a type name cannot
     */
    protected function castType(TypeDescriptor $type): string
    {
        $name = $type->name;
        if (in_array($name, [Builtin::Dynamic, Builtin::Unknown], true)) {
            throw new CompositionException('SQLite\'s CAST needs a type name, ' . $type->label() . ' given.');
        }
        $words = $name instanceof TypeName ? implode(' ', array_map(fn (string $part): string => Writer::render($this->identifier($part)), $name->parts)) : \SqlSemantics\Statement\Identifier\Ascii::upper($name->value);
        $affinity = (new TypeReader())->affinity(\SqlSemantics\Statement\Identifier\Ascii::upper($name instanceof TypeName ? implode(' ', $name->parts) : $name->value));
        if ($type->affinity !== null && $type->affinity !== $affinity) {
            throw new CompositionException('SQLite reads ' . $words . ' with ' . $affinity->name . ' affinity, not ' . $type->affinity->name . '.');
        }
        $this->statesOnly($type, ['length', 'precision', 'scale', 'affinity']);
        $modifiers = array_filter($type->precision !== null ? [$type->precision, $type->scale] : [$type->length], static fn (?int $value): bool => $value !== null);

        return $words . ($modifiers === [] ? '' : '(' . implode(',', $modifiers) . ')');
    }
}
