<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\MySql\Rules\Utility\Report;
use SqlSemantics\Platform\MySql\Rules\Utility\ShowFacts;
use SqlSemantics\Platform\MySql\Rules\Utility\ShowTargets;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\InspectedTable;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowLike;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowListing;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowWhere;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Relation;

/**
 * SHOW COLUMNS (SHOW FIELDS): the columns of a table or view.
 *
 * Rule: MYSQL-SHOW-COLUMNS-001. The table resolves by MYSQL-SHOW-TARGET-001; a database written
 * after it replaces the one written with the table name. FULL adds
 * `Collation`, `Privileges` and `Comment`; EXTENDED, from MySQL 8.0 on,
 * also lists hidden columns. The rows and the WHERE condition are derived
 * by MYSQL-SHOW-FACTS-001 from the layout of MYSQL-SHOW-ROWS-001.
 * Terminates: a fixed layout.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-columns.html.
 * Status: Implemented.
 *
 * @example Rejecting an empty database operand
 *     new \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowColumns(new \SqlSemantics\Platform\MySql\Statement\Utility\Show\InspectedTable(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'))), database: new \SqlSemantics\Statement\Identifier\Name('')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 *
 * @visibility public
 * @example Reading the statement
 *     $show = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SHOW FULL FIELDS IN t FROM shop LIKE 'a%'");
 *     [count($show->fields()), $show->toString()] // => [9, "SHOW FULL COLUMNS FROM t FROM shop LIKE 'a%'"]
 */
final class ShowColumns extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements Statement, Relation, \SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\TableUse
{
    use \SqlSemantics\Statement\Semantics\RelationSemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param InspectedTable $table The table
     * @param ?ShowListing $listing The FULL and EXTENDED keywords
     * @param ?Name $database The database written after FROM or IN; null for the current database
     * @param ShowLike|ShowWhere|null $filter The LIKE or WHERE clause
     */
    public function __construct(public readonly InspectedTable $table, public readonly ?ShowListing $listing = null, public readonly ?Name $database = null, public readonly ShowLike|ShowWhere|null $filter = null, ?RelationFact $relationInformation = null, ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($relationInformation !== null) {
            $this->initializeRelationInformation($relationInformation);
        }
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
        \SqlSemantics\Diagnostic\Check::input($database?->value !== '', 'A SHOW database operand is nonempty.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives the rows and the filter.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new ShowTargets())->derive($derivation, $this->table, $this->database);
        (new ShowFacts())->derive($derivation, $this, $this->filter);
    }

    /**
     * Derives the result columns.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        return (new ShowFacts())->fact($derivation, $this->listing?->full() === true ? Report::FullColumns : Report::Columns);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('SHOW');
        if ($this->listing !== null) {
            $out->keyword(...explode(' ', $this->listing->value));
        }
        $out->keyword('COLUMNS', 'FROM')->node($this->table);
        if ($this->database !== null) {
            $out->keyword('FROM')->name($this->database, NameUse::Qualifier);
        }
        $out->node($this->filter);
    }
}
