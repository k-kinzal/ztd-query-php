<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Validation\Correspondence;

use SqlSemantics\Statement\Construction\Query\Inputs;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\SqliteAliasScope;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Validation\Check;

/**
 * Checks actual lexical ownership and named-input positions against new requests.
 * @visibility SqlSemantics
 */
final class EnvironmentMatch
{
    /**
     * Alias environments may be reconstructed only around the exact same output slots.
     */
    public static function same(Scope|SqliteAliasScope|null $expected, Scope|SqliteAliasScope|null $actual): bool
    {
        return $expected === $actual || ($expected instanceof SqliteAliasScope && $actual instanceof SqliteAliasScope && $expected->scope === $actual->scope && $expected->projection === $actual->projection && $expected->aliases === $actual->aliases);
    }

    /**
     * Declarations retain identity; named occurrences retain their requested order and names.
     */
    public function check(Catalog|Scope|SqliteAliasScope $context, Inputs $input, Scope $actual): void
    {
        $catalog = $context instanceof Catalog ? $context : $context->catalog;
        Check::invariant($actual->catalog === $catalog, 'A query must use the requested declaration snapshot.');
        Check::invariant(self::same($context instanceof Catalog ? null : $context, $actual->parent), 'A query must have the requested immediate lexical parent.');
        Check::invariant(count($input->items) === count($actual->tables), 'Every requested named input must have its actual occurrence.');
        foreach ($input->items as $index => $item) {
            $table = $actual->tables[$index];
            Check::invariant(NamesMatch::qualified($item->name, $table->name) && NamesMatch::same($item->alias, $table->alias) && $item->explicitAlias === $table->explicitAlias, 'A named input must retain its exact name and alias request.');
            Check::invariant($table->catalog === $catalog && $table->declarations === $catalog->matchingTables($item->name), 'An occurrence must refer to the actual matching declarations.');
        }
    }
}
