<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableDefinition\Index;

use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\InvalidIndexName;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Resolves explicit index-name restrictions without consulting catalog state.
 *
 * PRIMARY is reserved for the primary key. Empty names and names ending in an
 * ASCII space are invalid. Primary-key names are ignored; an explicit index
 * name takes precedence over its CONSTRAINT name. Observed through SQL on
 * MySQL 5.6.51, 5.7.44, 8.0.44, 8.4.7 and 9.1.0.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Names
{
    /**
     * Identifies a fixed condition in an explicitly supplied secondary-index name.
     */
    public function problem(?Name $name): ?InvalidIndexName
    {
        return $name !== null && ($name->value === '' || strcasecmp($name->value, 'PRIMARY') === 0 || str_ends_with($name->value, ' ')) ? new InvalidIndexName($name) : null;
    }

    /**
     * Resolves the effective name, ignoring the written name of a primary key.
     */
    public function definition(IndexDefinition $index): ?InvalidIndexName
    {
        return $index->kind === IndexKind::Primary ? null : $this->problem($index->name->column ?? $index->constraint->name->column ?? null);
    }
}
