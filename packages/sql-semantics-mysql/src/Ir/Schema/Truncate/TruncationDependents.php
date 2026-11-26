<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Truncate;

use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;

/**
 * Applies the native referencing-table restriction before a truncation value exists.
 * @visibility SqlSemantics
 */
final class TruncationDependents
{
    /**
     * Admits a persistent self-reference or a table with no referencing relation.
     * @param list<TableIdentity> $referrers The complete set of referencing declarations
     */
    public function check(TableIdentity $target, array $referrers): void
    {
        Check::input($target->kind === ExternalRelationKind::BaseTable || $referrers === [], 'A temporary truncation cannot have native foreign keys.');
        foreach ($referrers as $child) {
            Check::input(
                $child->kind === ExternalRelationKind::BaseTable
                && $child->name->schema->value === $target->name->schema->value
                && $child->name->table->value === $target->name->table->value,
                'TRUNCATE TABLE is blocked by a foreign key from another table.'
            );
        }
    }
}
