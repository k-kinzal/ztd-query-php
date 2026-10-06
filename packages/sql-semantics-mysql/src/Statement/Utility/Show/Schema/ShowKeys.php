<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\MySql\Rules\Utility\Report;
use SqlSemantics\Platform\MySql\Rules\Utility\ShowFacts;
use SqlSemantics\Platform\MySql\Rules\Utility\ShowTargets;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\InspectedTable;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowWhere;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * SHOW INDEX (SHOW INDEXES, SHOW KEYS): the index parts of a table.
 *
 * Rule: MYSQL-SHOW-KEYS-001. The table resolves by MYSQL-SHOW-TARGET-001; a database written
 * after it replaces the one written with the table name. EXTENDED, from
 * MySQL 8.0 on, also lists hidden index parts. INDEX, INDEXES and KEYS are
 * the same keyword (LeafNoise); the writer emits INDEXES. Only WHERE
 * filters the rows. The rows and the WHERE condition are derived by
 * MYSQL-SHOW-FACTS-001 from the layout of MYSQL-SHOW-ROWS-001. Terminates:
 * a fixed layout.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-index.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the statement
 *     $show = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SHOW KEYS FROM t WHERE Key_name = 'PRIMARY'");
 *     [$show->facts->scalar($show->statement->filter->condition->left)->resolution->slot->name?->value, $show->toString()] // => ['Key_name', "SHOW INDEXES FROM t WHERE Key_name = 'PRIMARY'"]
 */
final class ShowKeys implements Statement, Relation
{
    use Snapshot;

    /**
     * @param InspectedTable $table The table
     * @param bool $extended Whether EXTENDED is written
     * @param ?Name $database The database written after FROM or IN; null for the current database
     * @param ?ShowWhere $filter The WHERE clause
     */
    public function __construct(public readonly InspectedTable $table, public readonly bool $extended = false, public readonly ?Name $database = null, public readonly ?ShowWhere $filter = null)
    {
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
        return (new ShowFacts())->fact($derivation, Report::Keys);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('SHOW');
        if ($this->extended) {
            $out->keyword('EXTENDED');
        }
        $out->keyword('INDEXES', 'FROM')->node($this->table);
        if ($this->database !== null) {
            $out->keyword('FROM')->name($this->database, NameUse::Qualifier);
        }
        $out->node($this->filter);
    }
}
