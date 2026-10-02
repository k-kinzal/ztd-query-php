<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Validation\Correspondence;

use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Exact decoded names and their interpretation-sensitive quote conventions.
 * @visibility SqlSemantics
 */
final class NamesMatch
{
    /**
     * Absence, spelling, and quoting each have a distinct meaning.
     */
    public static function same(?Name $expected, ?Name $actual): bool
    {
        return $expected === null || $actual === null ? $expected === $actual : $expected->value === $actual->value && $expected->quote === $actual->quote;
    }

    /**
     * Namespace positions cannot be exchanged or silently omitted.
     */
    public static function qualified(?QualifiedName $expected, ?QualifiedName $actual): bool
    {
        return $expected === null || $actual === null ? $expected === $actual : self::same($expected->name, $actual->name) && self::same($expected->schema, $actual->schema) && self::same($expected->catalog, $actual->catalog);
    }
}
