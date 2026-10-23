<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\View\Problem;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * An IF clause that cannot belong to ALTER VIEW or CREATE OR REPLACE VIEW.
 *
 * The shared MySQL 9.1 view grammar accepts the clause, but its command role
 * refuses it after the name and column list, before the query. Only the input location survives.
 * Source: https://dev.mysql.com/doc/refman/9.1/en/alter-view.html.
 * Observed through text and prepared SQL on MySQL 9.1.0.
 *
 * @visibility public
 * @example Describing incompatible view clauses
 *     (new \SqlSemantics\Platform\MySql\Statement\View\Problem\RefusedExistenceClause(11))->message() // => 'IF NOT EXISTS requires CREATE VIEW without OR REPLACE.'
 */
final class RefusedExistenceClause implements Diagnostic
{
    use Snapshot;

    /**
     * @param int $offset The first byte of the refused IF keyword
     */
    public function __construct(public readonly int $offset)
    {
        Check::input($offset >= 0, 'A refused view clause begins at a nonnegative byte offset.');
    }

    /**
     * Describes the command-role constraint without retaining input SQL.
     */
    public function message(): string
    {
        return 'IF NOT EXISTS requires CREATE VIEW without OR REPLACE.';
    }
}
