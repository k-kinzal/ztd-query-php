<?php

declare(strict_types=1);

namespace Bench;

use PhpBench\Attributes as Bench;
use SqlSemantics\Testing\Sqlite\Inspection;

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
        $semantics = new Inspection();
        $semantics->inspect('SELECT t.a, count(*) AS n FROM t LEFT JOIN u USING (id) WHERE t.b > 1 GROUP BY t.a ORDER BY n DESC LIMIT 10');
    }

    /**
     * Analyzes an INSERT with an upsert clause and writes it back.
     */
    public function benchInsert(): void
    {
        $semantics = new Inspection();
        $semantics->inspect("INSERT INTO items (id, name) VALUES (1, 'example') ON CONFLICT (id) DO UPDATE SET name = excluded.name RETURNING id");
    }
}
