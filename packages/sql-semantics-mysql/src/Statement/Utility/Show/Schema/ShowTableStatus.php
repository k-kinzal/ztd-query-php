<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\MySql\Rules\Utility\Report;
use SqlSemantics\Platform\MySql\Rules\Utility\ShowFacts;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowLike;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowWhere;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * SHOW TABLE STATUS: the storage facts of the tables of a database.
 *
 * Rule: MYSQL-SHOW-TABLE-STATUS-001. The columns are those of the layout of MYSQL-SHOW-ROWS-001 for
 * the release; the database written after FROM or IN only selects the
 * rows. The rows and the WHERE condition are derived by
 * MYSQL-SHOW-FACTS-001. Terminates: a fixed layout.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-table-status.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the statement
 *     $show = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SHOW TABLE STATUS FROM shop LIKE 'a%'");
 *     [$show->statement->database?->value, $show->toString()] // => ['shop', "SHOW TABLE STATUS FROM shop LIKE 'a%'"]
 */
final class ShowTableStatus implements Statement, Relation
{
    use Snapshot;

    /**
     * @param ?Name $database The database written after FROM or IN; null for the current database
     * @param ShowLike|ShowWhere|null $filter The LIKE or WHERE clause
     */
    public function __construct(public readonly ?Name $database = null, public readonly ShowLike|ShowWhere|null $filter = null)
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
        return (new ShowFacts())->fact($derivation, Report::TableStatus);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('SHOW', 'TABLE', 'STATUS');
        if ($this->database !== null) {
            $out->keyword('FROM')->name($this->database, NameUse::Qualifier);
        }
        $out->node($this->filter);
    }
}
