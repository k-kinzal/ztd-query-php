<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Show\Program;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Fact\RelationFact;
use SqlSemantics\Platform\MySql\Model\Statement\Relation;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;
use SqlSemantics\Platform\MySql\Rules\Utility\Report;
use SqlSemantics\Platform\MySql\Rules\Utility\ShowFacts;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowLike;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowWhere;

/**
 * SHOW FUNCTION STATUS: the stored functions the account can see.
 *
 * Rule: MYSQL-SHOW-FUNCTIONSTATUS-001. The rows and the WHERE condition are derived by
 * MYSQL-SHOW-FACTS-001 from the layout of MYSQL-SHOW-ROWS-001; MySQL 8.1
 * added the `Language` column. Terminates: a fixed layout.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-function-status.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class ShowFunctionStatus implements Statement, Relation
{
    use Snapshot;

    /**
     * @param ShowLike|ShowWhere|null $filter The LIKE or WHERE clause
     */
    public function __construct(public readonly ShowLike|ShowWhere|null $filter = null)
    {
    }

    /**
     * Derives the rows and the filter.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new ShowFacts())->derive($derivation, $this, $this->filter);
    }

    /**
     * Derives the result columns.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        return (new ShowFacts())->fact($derivation, Report::RoutineStatus);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('SHOW', 'FUNCTION', 'STATUS')->node($this->filter);
    }
}
