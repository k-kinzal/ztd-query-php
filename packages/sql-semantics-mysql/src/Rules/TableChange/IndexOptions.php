<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableChange;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterOption;

/**
 * Checks the optional ALGORITHM and LOCK pair on CREATE INDEX and DROP INDEX.
 *
 * Each clause occurs at most once; either order is permitted. ALTER TABLE
 * uses a different option list and is not subject to this pair invariant.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-index.html,
 * https://dev.mysql.com/doc/refman/8.4/en/drop-index.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class IndexOptions
{
    /**
     * Retains the written order after checking uniqueness of the clause roles.
     *
     * @param list<AlterOption> $options The supplied clauses
     * @return list<AlterOption>
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When a clause repeats
     */
    public function checked(array $options): array
    {
        $options = Check::listOf($options, AlterOption::class, 'An index statement holds ALGORITHM and LOCK clauses.');
        $roles = array_map(static fn (AlterOption $option): string => $option::class, $options);
        Check::input(count(array_unique($roles)) === count($roles), 'An index statement has at most one ALGORITHM and one LOCK clause.');

        return $options;
    }
}
