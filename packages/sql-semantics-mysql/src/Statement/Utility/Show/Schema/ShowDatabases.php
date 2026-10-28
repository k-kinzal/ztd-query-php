<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Utility\Report;
use SqlSemantics\Platform\MySql\Rules\Utility\ShowFacts;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowLike;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowWhere;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Relation;

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
 *     [$show->field(0)->name?->value, $show->toString()] // => ['Database (a%)', "SHOW SCHEMAS LIKE 'a%'"]
 */
final class ShowDatabases extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements Statement, Relation, \SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\TableUse
{
    use \SqlSemantics\Statement\Semantics\RelationSemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param ShowLike|ShowWhere|null $filter The LIKE or WHERE clause
     */
    public function __construct(public readonly ShowLike|ShowWhere|null $filter = null, ?RelationFact $relationInformation = null, ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, public readonly DatabaseListing $listing = DatabaseListing::Databases)
    {
        if ($relationInformation !== null) {
            $this->initializeRelationInformation($relationInformation);
        }
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
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
        $heading = new \SqlSemantics\Platform\MySql\Rules\Utility\ShowHeading();
        $name = $this->filter instanceof ShowLike ? 'Database (' . $heading->pattern($derivation, $this->filter->pattern, false) . ')' : 'Database';

        return (new ShowFacts())->fact($derivation, Report::Databases, $heading->name($derivation, $name, false));
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('SHOW', $this->listing->value)->node($this->filter);
    }
}
