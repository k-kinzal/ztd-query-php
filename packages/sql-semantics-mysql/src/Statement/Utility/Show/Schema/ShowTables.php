<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Fact\RelationFact;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Relation;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;
use SqlSemantics\Platform\MySql\Rules\Utility\Report;
use SqlSemantics\Platform\MySql\Rules\Utility\ShowFacts;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowLike;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowListing;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowWhere;

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
 * @visibility SqlSemantics
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
