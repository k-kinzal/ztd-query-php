<?php

declare(strict_types=1);

namespace Bench;

use PhpBench\Attributes as Benchmark;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect as PostgreSqlDialect;

/**
 * Measures the public SQL-to-semantic-statement pipeline.
 */
final class SemanticsBench
{
    private Semantics $semantics;

    /**
     * Loads parser resources before measurement.
     */
    public function setUp(): void
    {
        $this->semantics = new Semantics(PostgreSqlDialect::PostgreSql);
    }

    /**
     * Analyzes a self join without supplied declarations on each iteration.
     */
    #[Benchmark\BeforeMethods('setUp')]
    #[Benchmark\Revs(100)]
    #[Benchmark\Iterations(5)]
    public function benchAnalyze(): void
    {
        $this->semantics->analyze('SELECT child.id, parent.score, COALESCE(parent.score, 0) AS effective_score FROM users child LEFT JOIN users parent ON child.parent_id=parent.id WHERE child.score>0 ORDER BY child.id LIMIT 10');
    }
}
