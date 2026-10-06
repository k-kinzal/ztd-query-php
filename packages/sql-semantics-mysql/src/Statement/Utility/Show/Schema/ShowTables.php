<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\MySql\Rules\Utility\Report;
use SqlSemantics\Platform\MySql\Rules\Utility\ShowFacts;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowLike;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowListing;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowWhere;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * SHOW TABLES: the tables and views of a database.
 *
 * Rule: MYSQL-SHOW-TABLES-001. One column named `Tables_in_db` after the database written or
 * the current one, `Tables_in_db (pattern)` with LIKE; FULL adds
 * `Table_type`. EXTENDED, from MySQL 8.0 on, also lists hidden tables and
 * adds no column. When neither the statement nor the context names the
 * database, the column name depends on the current database of the session
 * and the shape is open. The rows and the WHERE condition are derived by
 * MYSQL-SHOW-FACTS-001. Terminates: a fixed layout.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-tables.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the statement
 *     $show = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SHOW FULL TABLES FROM shop LIKE 'o%'");
 *     [$show->field(0)->name?->value, $show->field(1)->name?->value, $show->toString()] // => ['Tables_in_shop (o%)', 'Table_type', "SHOW FULL TABLES FROM shop LIKE 'o%'"]
 */
final class ShowTables implements Statement, Relation
{
    use Snapshot;

    /**
     * @param ?ShowListing $listing The FULL and EXTENDED keywords
     * @param ?Name $database The database written after FROM or IN; null for the current database
     * @param ShowLike|ShowWhere|null $filter The LIKE or WHERE clause
     */
    public function __construct(public readonly ?ShowListing $listing = null, public readonly ?Name $database = null, public readonly ShowLike|ShowWhere|null $filter = null)
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
        $facts = new ShowFacts();
        $database = $facts->database($derivation, $this->database);
        if ($database === null) {
            return $facts->open($facts->currentDatabase());
        }
        $name = 'Tables_in_' . $database . ($this->filter instanceof ShowLike ? ' (' . $this->filter->pattern->value . ')' : '');

        return $facts->fact($derivation, $this->listing?->full() === true ? Report::FullTables : Report::Tables, $name);
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
        $out->keyword('TABLES');
        if ($this->database !== null) {
            $out->keyword('FROM')->name($this->database, NameUse::Qualifier);
        }
        $out->node($this->filter);
    }
}
