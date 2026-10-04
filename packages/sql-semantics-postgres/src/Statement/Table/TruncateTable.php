<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Targets;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference;
use SqlSemantics\Platform\PostgreSql\Statement\Option\DropBehavior;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to empty tables.
 *
 * Mirrors PostgreSQL's `TruncateStmt` (`relations`, `restart_seqs`, `behavior`). Each table is resolved (PG-
 * TABLE-TARGET-001) and is the relation fact of its reference. The optional TABLE word is not kept; CONTINUE
 * IDENTITY is the default and is kept when written.
 * Source: https://www.postgresql.org/docs/17/sql-truncate.html.
 *
 * @visibility public
 * @example Emptying tables
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('TRUNCATE TABLE ONLY a, b RESTART IDENTITY CASCADE');
 *     [count($statement->statement->tables), $statement->toString()] // => [2, 'TRUNCATE ONLY a, b RESTART IDENTITY CASCADE']
 */
final class TruncateTable implements Statement
{
    use Snapshot;

    /**
     * @var non-empty-list<RelationReference> The tables
     */
    public readonly array $tables;

    /**
     * @param list<RelationReference> $tables The tables
     * @param bool|null $restartIdentity True for RESTART IDENTITY, false for CONTINUE IDENTITY, null when not written
     * @param DropBehavior|null $behavior CASCADE or RESTRICT, when written
     */
    public function __construct(
        array $tables,
        public readonly ?bool $restartIdentity = null,
        public readonly ?DropBehavior $behavior = null,
    ) {
        $this->tables = Check::listOf($tables, RelationReference::class, 'TRUNCATE names at least one table.', 1);
    }

    /**
     * Resolves each table.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        foreach ($this->tables as $table) {
            $derivation->target($table, (new Targets())->resolve($derivation, $table->name));
        }
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('TRUNCATE')->list($this->tables);
        if ($this->restartIdentity !== null) {
            $out->keyword($this->restartIdentity ? 'RESTART' : 'CONTINUE', 'IDENTITY');
        }
        if ($this->behavior !== null) {
            $out->keyword($this->behavior->value);
        }
    }
}
