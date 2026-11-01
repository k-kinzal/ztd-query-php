<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Routine\Declaration;

use SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerTable;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Checks whether explicit trigger and table qualifiers can denote one database.
 *
 * Missing qualifiers depend on the current database. Case-only differences depend
 * on the server's database-name comparison. Distinct folded names cannot be made
 * compatible by creating either database or changing the current database.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-trigger.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class TriggerSchemas
{
    /**
     * Leaves state-dependent qualifiers for name resolution.
     */
    public static function compatible(QualifiedName $name, TriggerTable $table): bool
    {
        return $name->schema === null || $table->name->schema === null
            || mb_strtolower($name->schema->value, 'UTF-8') === mb_strtolower($table->name->schema->value, 'UTF-8');
    }
}
