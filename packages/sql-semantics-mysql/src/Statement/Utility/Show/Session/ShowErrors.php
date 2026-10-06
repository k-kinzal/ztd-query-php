<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Show\Session;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Utility\Report;
use SqlSemantics\Platform\MySql\Rules\Utility\ShowFacts;
use SqlSemantics\Platform\MySql\Statement\Query\Limit;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * SHOW ERRORS: the errors of the last statement of the session.
 *
 * Rule: MYSQL-SHOW-ERRORS-001. LIMIT selects rows as in SELECT; its operands are derived at a
 * position that sees no relation. The columns are those of the layout of
 * MYSQL-SHOW-ROWS-001. Terminates: a fixed layout.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-errors.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the statement
 *     $show = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SHOW ERRORS LIMIT 1, 2');
 *     [$show->field(2)->name?->value, $show->toString()] // => ['Message', 'SHOW ERRORS LIMIT 1, 2']
 */
final class ShowErrors implements Statement
{
    use Snapshot;

    /**
     * @param ?Limit $limit The LIMIT clause
     */
    public function __construct(public readonly ?Limit $limit = null)
    {
    }

    /**
     * Derives the rows.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $facts = new ShowFacts();
        $facts->limit($derivation, $this->limit);
        $facts->rows($derivation, Report::Diagnostics);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('SHOW', 'ERRORS')->node($this->limit);
    }
}
