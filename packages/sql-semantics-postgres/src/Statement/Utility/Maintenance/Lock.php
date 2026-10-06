<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Table\RelationKinds;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Targets;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\KindRule;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Declaration\RelationKind;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `LOCK [TABLE] name [, ...] [IN mode MODE] [NOWAIT]`: a request to lock tables for the current transaction.
 *
 * Rule: PG-LOCK-001. Mirrors PostgreSQL's `LockStmt` (relations, mode,
 * nowait). Without a mode the strongest one, ACCESS EXCLUSIVE, is taken;
 * a written mode is kept as written. The word TABLE has no effect and is not
 * kept. Facts: each table is resolved; only tables and views can be locked
 * (RangeVarCallbackForLockTable; PG-RELATION-KIND-001).
 * Source: https://www.postgresql.org/docs/17/sql-lock.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the mode a LOCK takes
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('LOCK TABLE ONLY a, b NOWAIT');
 *     [$operation->statement->mode, $operation->statement->effectiveMode(), $operation->toString()] // => [null, \SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\LockMode::AccessExclusive, 'LOCK ONLY a, b NOWAIT']
 * @example Refusing a LOCK without a table
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\Lock([]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class Lock implements Statement
{
    use Snapshot;

    /**
     * @var non-empty-list<RelationReference> The tables
     */
    public readonly array $tables;

    /**
     * @param list<RelationReference> $tables The tables; at least one
     * @param LockMode|null $mode The lock mode, when written
     * @param bool $nowait Whether the command fails instead of waiting for a conflicting lock
     */
    public function __construct(array $tables, public readonly ?LockMode $mode = null, public readonly bool $nowait = false)
    {
        $this->tables = Check::listOf($tables, RelationReference::class, 'LOCK names at least one table.', 1);
    }

    /**
     * Answers the mode the command takes: the written one, or ACCESS EXCLUSIVE.
     */
    public function effectiveMode(): LockMode
    {
        return $this->mode ?? LockMode::AccessExclusive;
    }

    /**
     * Resolves each table.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $kinds = new RelationKinds();
        foreach ($this->tables as $table) {
            $kind = $kinds->of($derivation->target($table, (new Targets())->resolve($derivation, $table->name)));
            $kinds->require($derivation, $kind, $table->name->name, [RelationKind::BaseTable, RelationKind::View], KindRule::LockRelation);
        }
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('LOCK')->list($this->tables);
        if ($this->mode !== null) {
            $out->keyword('IN', ...explode(' ', $this->mode->value))->keyword('MODE');
        }
        if ($this->nowait) {
            $out->keyword('NOWAIT');
        }
    }
}
