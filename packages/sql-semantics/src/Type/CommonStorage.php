<?php

declare(strict_types=1);

namespace SqlSemantics\Type;

use SqlSemantics\Dialect;

/**
 * Computes the common declared type of alternative values without evaluating them.
 * @visibility SqlSemantics
 */
final class CommonStorage
{
    /**
     * @param list<TypeDescriptor> $types Known alternatives, excluding untyped literals
     */
    public static function resolve(Dialect $dialect, array $types): TypeDescriptor
    {
        if ($types === []) {
            return TypeDescriptor::builtin($dialect, $dialect === Dialect::PostgreSql ? 'text' : 'unknown');
        }
        $names = array_values(array_unique(array_map(static fn (TypeDescriptor $type): string => $type->name, $types)));
        if (count($names) === 1) {
            return $types[0];
        }
        if ($dialect === Dialect::Sqlite) {
            return TypeDescriptor::builtin($dialect, 'dynamic');
        }
        $numeric = ['smallint', 'integer', 'bigint', 'numeric', 'real', 'double precision'];
        if (array_diff($names, $numeric) === []) {
            $rank = 0;
            foreach ($numeric as $index => $name) {
                if (in_array($name, $names, true)) {
                    $rank = $index;
                }
            }
            return TypeDescriptor::builtin($dialect, $numeric[$rank]);
        }
        if (array_diff($names, ['varchar', 'text', 'char', 'bpchar']) === []) {
            return TypeDescriptor::builtin($dialect, 'text');
        }
        return TypeDescriptor::builtin($dialect, 'unknown');
    }
}
