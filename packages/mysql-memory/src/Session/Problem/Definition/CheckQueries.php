<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Problem\Definition;

use MySqlMemory\Command\Definition\Constraint\CheckDefinitions;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Session\Problem\Errors;
use MySqlMemory\Session\Problems;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Key\CheckConstraint;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Query;

/**
 * Opens CHECK subquery tables before refusing the subquery, without resolving its projection.
 *
 * Explicit row-width conflicts are reported first. A missing table then precedes the
 * disallowed-subquery error, which precedes target existence and query-derived widths.
 * Verified by SQL histories against MySQL 8.0, 8.4 and 9.1.
 *
 * @visibility MySqlMemory
 */
final class CheckQueries
{
    /**
     * Prepares subqueries embedded in CREATE TABLE constraints from semantic structures alone.
     */
    public function prepare(Operation $operation, Session $session, Problems $problems): void
    {
        $create = $operation->statement;
        if (!$create instanceof CreateTable || $session->settings()->legacy()) {
            return;
        }
        $walker = new Walker();
        $checks = $walker->find($create, CheckConstraint::class);
        if (array_filter($checks, static fn (CheckConstraint $check): bool => (new Walker())->find($check->condition, Query::class) !== []) === []) {
            return;
        }
        foreach ($operation->facts->diagnostics as $diagnostic) {
            if ($diagnostic instanceof OperandColumns && $diagnostic->expression !== null && $walker->find($diagnostic->expression, Query::class) === []) {
                throw (new Errors())->error($diagnostic, $session, 'field list', $create);
            }
        }
        $problems->opened($operation, $session, $operation->facts->diagnostics);
        CheckDefinitions::forbidden($create, false, release: $session->settings()->release());
    }
}
