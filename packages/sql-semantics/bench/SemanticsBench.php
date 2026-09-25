<?php

declare(strict_types=1);

namespace Bench;

use PhpBench\Attributes as Benchmark;
use SqlSemantics\Core\Binder;
use SqlSemantics\Core\SchemaBuilder;
use SqlSemantics\Facade\Dialect;

/**
 * Measures the public SQL-to-bound-statement pipeline with a reusable schema.
 */
final class SemanticsBench
{
    private Binder $binder;

    /**
     * Builds declarations and loads parser resources before measurement.
     */
    public function setUp(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)');
        $this->binder = new Binder($schema);
    }

    /**
     * Parses and binds a self join against the same schema on each iteration.
     */
    #[Benchmark\BeforeMethods('setUp')]
    #[Benchmark\Revs(100)]
    #[Benchmark\Iterations(5)]
    public function benchBind(): void
    {
        $this->binder->bind('SELECT child.id, parent.score, COALESCE(parent.score, 0) AS effective_score FROM users child LEFT JOIN users parent ON child.parent_id=parent.id WHERE child.score>0 ORDER BY child.id LIMIT 10');
    }
}
