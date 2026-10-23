<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableChange;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Name\EmptyObjectName;
use SqlSemantics\Platform\MySql\Statement\Name\ObjectNameKind;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Excludes empty table identities independently of whether the table exists.
 *
 * The absent database qualifier denotes the session database. An explicit
 * empty qualifier does not. Empty correlation names remain valid and never
 * pass through this table-identity rule. Native servers reject these table
 * names even in unexecuted procedure branches.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/identifiers.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class TableIdentity
{
    /**
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When a table identity contains an empty component
     */
    public function check(QualifiedName $name): void
    {
        Check::input($this->problem($name) === null, 'A table name and its explicit database qualifier are nonempty.');
    }

    /**
     * Answers the input-domain problem, inspecting the database before the table.
     */
    public function problem(QualifiedName $name): ?EmptyObjectName
    {
        if ($name->schema?->value === '') {
            return new EmptyObjectName(ObjectNameKind::Database);
        }

        return $name->name->value === '' ? new EmptyObjectName(ObjectNameKind::Table) : null;
    }

    /**
     * Finds identities repeated without requiring a default database or name-comparison policy.
     *
     * @param list<QualifiedName> $names The table identities in source order
     */
    public function repeated(array $names): ?\SqlSemantics\Platform\MySql\Statement\Alter\Problem\RepeatedTable
    {
        $seen = [];
        foreach ($names as $name) {
            $key = serialize([$name->catalog?->value, $name->schema?->value, $name->name->value]);
            if (isset($seen[$key])) {
                return new \SqlSemantics\Platform\MySql\Statement\Alter\Problem\RepeatedTable($name);
            }
            $seen[$key] = true;
        }
        return null;
    }
}
