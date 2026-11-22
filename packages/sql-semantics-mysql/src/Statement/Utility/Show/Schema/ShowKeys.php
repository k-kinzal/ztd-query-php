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
use SqlSemantics\Platform\MySql\Rules\Utility\ShowTargets;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\InspectedTable;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowWhere;

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
 * @visibility SqlSemantics
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
