<?php

declare(strict_types=1);

namespace Bench;

use PhpBench\Attributes as Benchmark;
use SqlSemantics\Testing\PostgreSql\Inspection;

/**
 * Measures analyzing a PostgreSQL statement into its semantic model and back to SQL.
 */
final class AnalyzeBench
{
    private Inspection $semantics;

    /**
     * Loads the profile and the parser before measurement.
     */
    public function setUp(): void
    {
        $this->semantics = new Inspection();
    }

    /**
     * Analyzes a selection with typed constants, names and comparisons.
     */
    #[Benchmark\BeforeMethods('setUp')]
    #[Benchmark\Revs(50)]
    #[Benchmark\Iterations(5)]
    public function benchAnalyze(): void
    {
        $this->semantics->inspect("SELECT a, x.b AS total, 0x1F, 1.50e3, timestamp(3) with time zone '2024-01-01' FROM app.t AS x WHERE a >= \$1");
    }
}
