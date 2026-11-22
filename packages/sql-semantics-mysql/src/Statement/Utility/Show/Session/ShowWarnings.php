<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Show\Session;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;
use SqlSemantics\Platform\MySql\Rules\Utility\Report;
use SqlSemantics\Platform\MySql\Rules\Utility\ShowFacts;
use SqlSemantics\Platform\MySql\Statement\Query\Limit;

/**
 * SHOW WARNINGS: the errors, warnings and notes of the last statement of the session.
 *
 * Rule: MYSQL-SHOW-WARNINGS-001. LIMIT selects rows as in SELECT; its operands are derived at a
 * position that sees no relation. The columns are those of the layout of
 * MYSQL-SHOW-ROWS-001. Terminates: a fixed layout.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-warnings.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class ShowWarnings implements Statement
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
        $out->keyword('SHOW', 'WARNINGS')->node($this->limit);
    }
}
