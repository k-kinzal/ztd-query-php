<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Server;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionSelection;
use SqlSemantics\Platform\MySql\Statement\Relation\Hint\PrimaryIndex;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Checks and writes the table, partitions and index list of one entry of CACHE INDEX or LOAD INDEX INTO CACHE.
 *
 * Rule: MYSQL-CACHED-INDEXES-001. An entry names a table qualified by at
 * most a database, optionally the partitions `PARTITION (…)`, and
 * optionally an index list `INDEX (…)`: an absent list selects every index,
 * an empty list none, as the server's USE INDEX hints do (init_index_hints
 * with INDEX_HINT_USE); PRIMARY names the primary key. KEY and INDEX are
 * synonyms; INDEX is written. Terminates: one pass over the list.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/cache-index.html,
 * https://dev.mysql.com/doc/refman/8.4/en/load-index.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class CachedIndexes
{
    /**
     * Checks an index list.
     *
     * @param array<object>|null $indexes
     * @return list<Name|PrimaryIndex>|null
     */
    public function checked(QualifiedName $table, ?array $indexes): ?array
    {
        Check::input($table->catalog === null, 'A table is qualified by at most a database.');
        if ($indexes === null) {
            return null;
        }
        $checked = [];
        Check::input(array_is_list($indexes), 'An index list is a list.');
        foreach ($indexes as $index) {
            Check::input($index instanceof Name || $index instanceof PrimaryIndex, 'An index list holds index names and PRIMARY.');
            $checked[] = $index;
        }

        return $checked;
    }

    /**
     * Writes the table, its partitions and its index list.
     *
     * @param list<Name|PrimaryIndex>|null $indexes
     */
    public function render(Output $out, QualifiedName $table, ?PartitionSelection $partitions, ?array $indexes): void
    {
        if ($table->schema !== null) {
            $out->name($table->schema, NameUse::Qualifier)->symbol('.');
        }
        $out->name($table->name, NameUse::Relation);
        if ($partitions !== null) {
            $out->keyword('PARTITION')->symbol('(')->node($partitions)->symbol(')');
        }
        if ($indexes === null) {
            return;
        }
        $out->keyword('INDEX')->symbol('(');
        foreach ($indexes as $position => $index) {
            if ($position > 0) {
                $out->symbol(',');
            }
            if ($index instanceof Name) {
                $out->name($index, NameUse::Identifier);
            } else {
                $out->node($index);
            }
        }
        $out->symbol(')');
    }
}
