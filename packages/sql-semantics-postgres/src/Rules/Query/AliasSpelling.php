<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Query;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Writes the alias of a FROM item: AS, the correlation name and the column names.
 *
 * The AS word is optional in the grammar and is always written.
 * Source: https://www.postgresql.org/docs/17/sql-select.html#SQL-FROM.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class AliasSpelling
{
    /**
     * Writes the alias and its column names, when there is an alias.
     *
     * @param list<Name> $columns
     */
    public function write(Output $out, ?Name $alias, array $columns = []): void
    {
        if ($alias === null) {
            return;
        }
        $out->keyword('AS')->name($alias, NameUse::Alias);
        $this->names($out, $columns);
    }

    /**
     * Writes a parenthesized list of names, when there is one.
     *
     * @param list<Name> $names
     */
    public function names(Output $out, array $names): void
    {
        if ($names === []) {
            return;
        }
        $out->symbol('(');
        foreach ($names as $position => $name) {
            if ($position > 0) {
                $out->symbol(',');
            }
            $out->name($name, NameUse::Column);
        }
        $out->symbol(')');
    }
}
