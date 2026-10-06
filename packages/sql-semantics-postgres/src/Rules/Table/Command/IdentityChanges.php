<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Table\Command;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column\IdentityGeneration;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column\IdentityRestart;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column\IdentitySetting;

/**
 * Checks the changes of an identity column ALTER COLUMN writes.
 *
 * Rule: PG-IDENTITY-CHANGES-001. The changes are RESTART, SET with a
 * sequence option, and SET GENERATED, at least one, in the order written.
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class IdentityChanges
{
    /**
     * Checks a list of identity changes.
     *
     * @param array<array-key, object|scalar|null> $changes
     * @return non-empty-list<IdentityRestart|IdentitySetting|IdentityGeneration>
     */
    public function checked(array $changes): array
    {
        $checked = [];
        foreach (Check::listOf($changes, \SqlSemantics\Statement\Node::class, 'Identity changes are an ordered list.') as $change) {
            Check::input($change instanceof IdentityRestart || $change instanceof IdentitySetting || $change instanceof IdentityGeneration, 'An identity change is RESTART, SET with a sequence option, or SET GENERATED.');
            $checked[] = $change;
        }
        Check::input($checked !== [], 'An identity change list holds at least one change.');

        return $checked;
    }
}
