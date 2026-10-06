<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Manipulation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\CurrentOf;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Delete;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Update;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Scalar;

/**
 * Derives the facts of UPDATE and DELETE.
 *
 * Rule: PG-CHANGE-001. The common tables, the target, the FROM items of
 * UPDATE and the USING items of DELETE follow PG-MODIFICATION-SCOPE-001.
 * The SET items (PG-ASSIGNMENT-001), the WHERE condition and RETURNING see
 * the target and those items. WHERE CURRENT OF names a cursor, which is
 * session state.
 * Source: https://www.postgresql.org/docs/17/sql-update.html, https://www.postgresql.org/docs/17/sql-delete.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class ChangeFacts
{
    /**
     * Derives an UPDATE and answers its rows.
     */
    public function update(Update $update, Derivation $derivation, Environment $outer): QueryFact
    {
        $scope = new ModificationScope();
        [$base, $target] = $scope->open($update->with, $update->target, $derivation, $outer);
        $visible = [$target, ...$scope->inputs($update->from, $target, $derivation, $base)];
        $environment = new Environment($derivation->context, $base, $visible);
        $assignments = new Assignments();
        $assignments->assign($update->assignments, $target, $derivation, $environment);
        $this->where($update->where, $derivation, $environment);
        (new Placement())->values($update, $assignments->defaults($update->assignments), [], $derivation);

        return $scope->returning($update->returning, $visible, $derivation, $base);
    }

    /**
     * Derives a DELETE and answers its rows.
     */
    public function delete(Delete $delete, Derivation $derivation, Environment $outer): QueryFact
    {
        $scope = new ModificationScope();
        [$base, $target] = $scope->open($delete->with, $delete->target, $derivation, $outer);
        $visible = [$target, ...$scope->inputs($delete->using, $target, $derivation, $base)];
        $this->where($delete->where, $derivation, new Environment($derivation->context, $base, $visible));
        (new Placement())->values($delete, [], [], $derivation);

        return $scope->returning($delete->returning, $visible, $derivation, $base);
    }

    /**
     * Derives the WHERE condition or the cursor position.
     */
    public function where(Scalar|CurrentOf|null $where, Derivation $derivation, Environment $environment): void
    {
        if ($where instanceof CurrentOf) {
            $where->deriveClause($derivation, $environment);
        } elseif ($where !== null) {
            $derivation->scalar($where, $environment);
        }
    }
}
