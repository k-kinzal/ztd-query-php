<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema;

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
 * SHOW DATABASES (SHOW SCHEMAS): the databases the account can see.
 *
 * Rule: MYSQL-SHOW-DATABASES-001. One column, `Database`; with LIKE the
 * server names it `Database (pattern)`. The rows and the WHERE condition are
 * derived by MYSQL-SHOW-FACTS-001 from the layout of MYSQL-SHOW-ROWS-001.
 * Terminates: a fixed layout.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-databases.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the column named by the pattern
 *     $show = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SHOW SCHEMAS LIKE 'a%'");
 *     [$show->field(0)->name?->value, $show->toString()] // => ['Database (a%)', "SHOW DATABASES LIKE 'a%'"]
 */
final class ShowDatabases implements Statement, Relation
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
        return (new ShowFacts())->fact($derivation, Report::Databases, $this->filter instanceof ShowLike ? 'Database (' . $this->filter->pattern->value . ')' : null);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('SHOW', 'DATABASES')->node($this->filter);
    }
}
