<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\QueryFact;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Query;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Relation;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Selection;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;
use SqlSemantics\Platform\PostgreSql\Rules\Query\Facts\QueryRoots;
use SqlSemantics\Platform\PostgreSql\Rules\Query\Facts\SelectFacts;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\SelectOptions;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\TableInput;

/**
 * TABLE name: every row and column of one table, the same as `SELECT * FROM name`.
 *
 * Rule: PG-TABLE-QUERY-001. The table is one FROM item without alias; the
 * output is every column of it, and the ORDER BY, LIMIT and locking clauses
 * written after it see its columns as a selection would.
 * Source: https://www.postgresql.org/docs/17/sql-select.html#SQL-TABLE. Status: Implemented.
 *
 * @visibility SqlSemantics
 * @example Refusing a table with an alias
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Query\TableQuery(new \SqlSemantics\Platform\PostgreSql\Statement\Relation\TableInput(new \SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('t'))), new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('x'))) // throws \SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction
 */
final class TableQuery implements Statement, Query, Selection, OutputNaming
{
    use Snapshot;

    /**
     * @param TableInput $table The table, without alias or sample
     * @param SelectOptions|null $options The ORDER BY, LIMIT and locking clauses written after it
     */
    public function __construct(public readonly TableInput $table, public readonly ?SelectOptions $options = null)
    {
        Check::input($table->alias === null && $table->columns === [] && $table->sample === null, 'TABLE names a table without alias or sample.');
    }

    /**
     * Answers the table.
     */
    public function input(): Relation
    {
        return $this->table;
    }

    /**
     * Answers no name: the output starts with the columns of the table.
     */
    public function outputName(): ?Name
    {
        return null;
    }

    /**
     * Derives the query as a statement root.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new QueryRoots())->derive($this, $derivation);
    }

    /**
     * Derives the table and the output columns.
     */
    public function deriveQuery(Derivation $derivation, Environment $outer): QueryFact
    {
        return (new SelectFacts())->table($this, $derivation, $outer);
    }

    /**
     * Writes TABLE, the table and the options.
     */
    public function render(Output $out): void
    {
        $out->keyword('TABLE')->node($this->table)->node($this->options);
    }
}
