<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Show\Program;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Utility\Report;
use SqlSemantics\Platform\MySql\Rules\Utility\ShowFacts;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowLike;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowWhere;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * SHOW PROCEDURE STATUS: the stored procedures the account can see.
 *
 * Rule: MYSQL-SHOW-PROCEDURESTATUS-001. The rows and the WHERE condition are derived by
 * MYSQL-SHOW-FACTS-001 from the layout of MYSQL-SHOW-ROWS-001; MySQL 8.1
 * added the `Language` column. Terminates: a fixed layout.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-procedure-status.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the statement
 *     $show = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SHOW PROCEDURE STATUS WHERE Db = 'shop'");
 *     [$show->facts->scalar($show->statement->filter->condition->left)->resolution->slot->name?->value, $show->toString()] // => ['Db', "SHOW PROCEDURE STATUS WHERE Db = 'shop'"]
 */
final class ShowProcedureStatus implements Statement, Relation
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
        $out->keyword('SHOW', 'PROCEDURE', 'STATUS')->node($this->filter);
    }
}
