<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\UnknownPartition;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\UnpartitionedTable;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;

/**
 * Checks the partitions a table reference selects against the partitions its table declares.
 *
 * A table that is not partitioned accepts no PARTITION clause; a partitioned table accepts the
 * names of its partitions and subpartitions, compared without regard to case. The first problem
 * is reported. A table whose declaration does not say how it is partitioned is not checked.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/partitioning-selection.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class PartitionSelection
{
    /**
     * Reports the first partition a reference cannot select.
     *
     * @param list<Name> $selected The partitions the reference names
     */
    public function check(array $selected, RelationFact $fact, Derivation $derivation): void
    {
        $table = $fact->table;
        if ($selected === [] || !$table instanceof DeclaredTable || $table->table->partitions === null) {
            return;
        }
        $declared = $table->table->partitions;
        if ($declared === []) {
            $derivation->report(new UnpartitionedTable());

            return;
        }
        $known = array_map(static fn (Name $name): string => strtolower($name->value), $declared);
        foreach ($selected as $partition) {
            if (!in_array(strtolower($partition->value), $known, true)) {
                $derivation->report(new UnknownPartition($partition->value, $table->table->name->name->value));

                return;
            }
        }
    }
}
