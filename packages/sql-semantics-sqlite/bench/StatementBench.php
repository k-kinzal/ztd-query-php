<?php

declare(strict_types=1);

namespace Bench;

use PhpBench\Attributes as Bench;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;

/**
 * Measures the analysis of a statement: parsing, lowering, derivation and rendering with its checks.
 */
#[Bench\Revs(10)]
#[Bench\Iterations(3)]
final class StatementBench
{
    /**
     * Analyzes a join query with a predicate and an aggregate and writes it back.
     */
    public function benchSelect(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $semantics->analyze('SELECT t.a, count(*) AS n FROM t LEFT JOIN u USING (id) WHERE t.b > 1 GROUP BY t.a ORDER BY n DESC LIMIT 10')->toString();
    }

    /**
     * Analyzes an INSERT with an upsert clause and writes it back.
     */
    public function benchInsert(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $semantics->analyze("INSERT INTO items (id, name) VALUES (1, 'example') ON CONFLICT (id) DO UPDATE SET name = excluded.name RETURNING id")->toString();
    }
}
